<?php

namespace App\Http\Controllers\Webhook;

use App\Events\InvoicePaid;
use App\Events\NotificationSent;
use App\Helpers\ApiResponse;
use App\Helpers\DecimalMoney;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractDepositTransaction;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\RoomMovement;
use App\Services\InvoiceDebtRolloverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SePayWebhookController extends Controller
{
    public function __construct(private readonly InvoiceDebtRolloverService $debtRolloverService) {}

    public function handle(Request $request): JsonResponse
    {
        try {
            Log::info('SePay Webhook received request:', $request->all());

            $token = config('services.sepay.webhook_token');
            if (! empty($token)) {
                $authHeader = $request->header('Authorization');
                if ($authHeader !== $token && $authHeader !== 'Apikey ' . $token) {
                    Log::warning('SePay Webhook unauthorized request.');
                    return ApiResponse::responseJson(false, 'Yêu cầu không được xác thực.', 401, null, 401);
                }
            }

            $transferType = $request->input('transferType');
            if ($transferType && strtolower($transferType) !== 'in') {
                return ApiResponse::responseJson(true, 'Chỉ xử lý giao dịch nhận tiền.', 0, ['status' => 'ignored'], 200);
            }

            $content = (string) ($request->input('content') ?? $request->input('transferDesc') ?? '');
            $reference = $request->input('code');
            $sepayId = $request->input('id');

            if ($reference === 'SEPAYTEST' || $content === 'SEPAY TEST WEBHOOK') {
                Log::info('SePay Webhook: Test request bypassed successfully.');
                return ApiResponse::responseJson(true, 'Nhận webhook thử nghiệm thành công.', 0, null, 200);
            }

            $amount = DecimalMoney::normalize($request->input('amount') ?? $request->input('transferAmount') ?? '0');
            if (! DecimalMoney::isPositive($amount)) {
                return ApiResponse::responseJson(false, 'Số tiền giao dịch phải lớn hơn 0.', 400, null, 400);
            }

            $transactionReference = (string) ($reference ?: $sepayId);
            if ($transactionReference === '') {
                return ApiResponse::responseJson(false, 'Thiếu mã tham chiếu giao dịch.', 400, null, 400);
            }

            if ($this->transactionReferenceExists($transactionReference)) {
                return ApiResponse::responseJson(true, 'Giao dịch đã được xử lý.', 0, ['status' => 'ignored'], 200);
            }

            // Ưu tiên so khớp mã hóa đơn INV-* trước để không lẫn với luồng thu cọc hợp đồng HD-*.
            $invoiceCode = $this->extractInvoiceCode($content);
            if ($invoiceCode) {
                $invoice = Invoice::query()
                    ->with(['room.building', 'contract.contractTenants.tenant'])
                    ->where('invoice_code', $invoiceCode)
                    ->first();

                if ($invoice) {
                    return $this->processInvoicePayment($invoice, $amount, $transactionReference, $content);
                }
            }

            $transferCode = $this->extractTransferCode($content);
            if ($transferCode) {
                return $this->processTransferSettlementPayment($transferCode, $amount, $transactionReference, $content);
            }

            $contract = null;
            $contractCode = null;

            if (preg_match('/(HD-[A-Za-z0-9_\-\.]+)/i', $content, $matches)) {
                $contractCode = $matches[1];
            }

            if (empty($contractCode) && preg_match('/COC\s+([A-Za-z0-9_\-\.]+)/i', $content, $matches)) {
                $contractCode = $matches[1];
            }

            if (! empty($contractCode)) {
                $contract = Contract::where('contract_code', $contractCode)->first();
            }

            if (! $contract) {
                $cleanContent = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $content));

                $pendingContracts = Contract::where('payment_status', Contract::PAYMENT_STATUS_PENDING)->get();
                foreach ($pendingContracts as $pc) {
                    $cleanCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $pc->contract_code));
                    if (! empty($cleanCode) && str_contains($cleanContent, $cleanCode)) {
                        $contract = $pc;
                        $contractCode = $pc->contract_code;
                        break;
                    }
                }

                if (! $contract) {
                    $recentContracts = Contract::orderBy('id', 'desc')->take(200)->get();
                    foreach ($recentContracts as $rc) {
                        $cleanCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rc->contract_code));
                        if (! empty($cleanCode) && str_contains($cleanContent, $cleanCode)) {
                            $contract = $rc;
                            $contractCode = $rc->contract_code;
                            break;
                        }
                    }
                }
            }

            if (! $contract) {
                Log::warning("SePay Webhook: Contract or invoice not found for content: {$content}");
                return ApiResponse::responseJson(false, 'Không tìm thấy hợp đồng/hóa đơn hoặc không thể trích xuất mã từ nội dung giao dịch.', 404, null, 404);
            }

            $lock = Cache::lock('sepay-contract-payment:' . $contract->id, 10);

            if (! $lock->get()) {
                return ApiResponse::responseJson(false, 'Giao dịch đang được xử lý, vui lòng thử lại sau.', 409, null, 409);
            }

            $ignored = null;
            try {
                DB::transaction(function () use ($contract, $amount, $transactionReference, $content, &$ignored): void {
                    if (ContractDepositTransaction::query()->where('transaction_reference', $transactionReference)->exists()) {
                        $ignored = 'duplicate';
                        return;
                    }

                    $contract->refresh();
                    if ($contract->is_deposit_paid) {
                        $ignored = 'excess';
                        return;
                    }

                    ContractDepositTransaction::create([
                        'contract_id' => $contract->id,
                        'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
                        'amount' => $amount,
                        'transaction_date' => now()->toDateString(),
                        'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
                        'transaction_reference' => $transactionReference,
                        'note' => 'Hệ thống tự động ghi nhận qua SePay Webhook. Nội dung gốc: ' . $content,
                        'created_by' => null,
                    ]);

                    if ($contract->status === Contract::STATUS_CANCELLED) {
                        $hasOtherActiveContract = Contract::where('room_id', $contract->room_id)
                            ->where('id', '!=', $contract->id)
                            ->where('status', Contract::STATUS_ACTIVE)
                            ->exists();

                        if (! $hasOtherActiveContract) {
                            $contract->status = Contract::STATUS_ACTIVE;
                            $contract->note = ($contract->note ? $contract->note . "\n" : '') . '[Hệ thống] Tự động khôi phục hợp đồng và kích hoạt lại do đã nhận được tiền cọc qua SePay.';
                            $contract->save();

                            $contract->contractTenants()->update(['is_staying' => true]);
                            $contract->contractVehicles()->update(['is_active' => true]);

                            if ($contract->room_id) {
                                $occupants = \App\Models\ContractTenant::query()
                                    ->where('is_staying', true)
                                    ->whereNull('leave_date')
                                    ->whereHas('contract', fn ($query) => $query->where('room_id', $contract->room_id)->where('status', Contract::STATUS_ACTIVE))
                                    ->distinct('tenant_id')
                                    ->count('tenant_id');

                                \App\Models\Room::query()->whereKey($contract->room_id)->update(['current_occupants' => $occupants]);
                            }

                            Log::info("SePay Webhook: Restored auto-cancelled contract {$contract->contract_code} as deposit paid successfully.");
                        } else {
                            $contract->note = ($contract->note ? $contract->note . "\n" : '') . '[Hệ thống] Nhận được tiền cọc ' . number_format(DecimalMoney::toIntegerAmount($amount), 0, ',', '.') . ' VND qua SePay nhưng KHÔNG THỂ tự động khôi phục hợp đồng vì phòng này đã có hợp đồng hoạt động khác. Vui lòng hoàn tiền hoặc xử lý thủ công.';
                            $contract->save();

                            Log::warning("SePay Webhook: Received payment for auto-cancelled contract {$contract->contract_code} but room {$contract->room_id} is already occupied.");
                        }
                    }
                });
            } finally {
                optional($lock)->release();
            }

            if ($ignored === 'duplicate') {
                return ApiResponse::responseJson(true, 'Giao dịch đã được xử lý.', 0, null, 200);
            }

            if ($ignored === 'excess') {
                try {
                    $contract->loadMissing(['room.building']);
                    $room = $contract->room;
                    $building = $room?->building;
                    $amountText = number_format(DecimalMoney::toIntegerAmount($amount), 0, ',', '.') . ' VND';

                    $adminNotif = Notification::create([
                        'title' => 'Cảnh báo nhận thừa tiền cọc',
                        'content' => "Hợp đồng {$contract->contract_code} (Phòng " . ($room?->room_number ?? 'Chưa rõ') . ' - Tòa nhà ' . ($building?->name ?? 'Chưa rõ') . ") đã nhận được giao dịch {$amountText} qua SePay nhưng tiền cọc đã được đóng đủ trước đó. Mã giao dịch: {$transactionReference}. Vui lòng đối soát và hoàn tiền.",
                        'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
                        'target_type' => Notification::TARGET_TYPE_ADMIN,
                        'building_id' => $room?->building_id,
                        'room_id' => $contract->room_id,
                        'status' => Notification::STATUS_SENT,
                        'published_at' => now(),
                    ]);

                    broadcast(new NotificationSent($adminNotif));
                } catch (\Exception $e) {
                    Log::error('SePay Webhook: Error creating/broadcasting excess deposit notification: ' . $e->getMessage());
                }

                return ApiResponse::responseJson(true, 'Hợp đồng đã đủ cọc, đã cảnh báo giao dịch thừa.', 0, null, 200);
            }

            try {
                $contract->loadMissing(['room.building']);
                $room = $contract->room;
                $building = $room?->building;

                $adminNotif = Notification::create([
                    'title' => 'Thanh toán đặt cọc thành công',
                    'content' => "Hợp đồng {$contract->contract_code} (Phòng " . ($room?->room_number ?? 'Chưa rõ') . ' - Tòa nhà ' . ($building?->name ?? 'Chưa rõ') . ') đã thanh toán tiền cọc thành công qua cổng SePay. Số tiền: ' . number_format(DecimalMoney::toIntegerAmount($amount), 0, ',', '.') . ' VND.',
                    'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
                    'target_type' => Notification::TARGET_TYPE_ADMIN,
                    'building_id' => $room?->building_id,
                    'room_id' => $contract->room_id,
                    'status' => Notification::STATUS_SENT,
                    'published_at' => now(),
                ]);

                broadcast(new NotificationSent($adminNotif));
            } catch (\Exception $e) {
                Log::error('SePay Webhook: Error creating/broadcasting admin notification: ' . $e->getMessage());
            }

            Log::info("SePay Webhook: Successfully processed payment for contract {$contractCode}, amount: {$amount}");

            return ApiResponse::responseJson(true, 'Xử lý thanh toán thành công.', 0, null, 200);
        } catch (\Exception $e) {
            Log::error('SePay Webhook transaction error: ' . $e->getMessage());
            return ApiResponse::responseJson(false, 'Lỗi hệ thống: ' . $e->getMessage(), 500, null, 500);
        }
    }

    private function notifyAdminExcessPayment(Invoice $invoice, Payment $payment, string $amount, string $reason): void
    {
        try {
            $invoice->loadMissing(['room.building']);
            $amountText = number_format(DecimalMoney::toIntegerAmount($amount), 0, ',', '.') . ' VND';

            $notification = Notification::query()->create([
                'title' => 'Cảnh báo giao dịch dư thừa',
                'content' => 'Phòng ' . ($invoice->room?->room_number ?? 'chưa rõ') . ' của tòa nhà ' . ($invoice->room?->building?->name ?? 'chưa rõ') . " nhận được giao dịch {$amountText} cho hóa đơn {$invoice->invoice_code}. Lý do: {$reason}. Mã thanh toán: {$payment->payment_code}. Vui lòng đối soát và hoàn tiền.",
                'notification_type' => Notification::NOTIFICATION_TYPE_INVOICE,
                'target_type' => Notification::TARGET_TYPE_ADMIN,
                'building_id' => $invoice->room?->building_id,
                'room_id' => $invoice->room_id,
                'tenant_id' => null,
                'published_at' => now(),
                'status' => Notification::STATUS_SENT,
                'created_by' => null,
            ]);

            event(new NotificationSent($notification));
        } catch (\Exception $e) {
            Log::error('SePay Webhook: Error creating excess payment admin notification: ' . $e->getMessage());
        }
    }

    private function processInvoicePayment(Invoice $invoice, string $amount, string $transactionReference, string $content): JsonResponse
    {
        $payableInvoice = $this->debtRolloverService->payableInvoiceForIncomingInvoice($invoice);
        $lock = Cache::lock('sepay-invoice-payment:' . $payableInvoice->id, 10);

        if (! $lock->get()) {
            return ApiResponse::responseJson(false, 'Giao dịch đang được xử lý, vui lòng thử lại sau.', 409, null, 409);
        }

        try {
            $response = DB::transaction(function () use ($payableInvoice, $invoice, $amount, $transactionReference, $content): JsonResponse {
                $invoiceModel = Invoice::query()
                    ->with(['room.building', 'contract.contractTenants.tenant'])
                    ->lockForUpdate()
                    ->find($payableInvoice->id);

                if (! $invoiceModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy hóa đơn.', 404, null, 404);
                }

                if (Payment::query()->where('transaction_reference', $transactionReference)->exists()) {
                    return ApiResponse::responseJson(true, 'Giao dịch đã được xử lý.', 0, ['status' => 'ignored'], 200);
                }

                if ((int) $invoiceModel->id !== (int) $invoice->id) {
                    Log::info("SePay Webhook: Redirected rolled debt payment from invoice {$invoice->invoice_code} to {$invoiceModel->invoice_code}");
                }

                if ((int) $invoiceModel->status === Invoice::STATUS_PAID) {
                    $payment = Payment::query()->create([
                        'payment_code' => $this->makePaymentCode(),
                        'invoice_id' => $invoiceModel->id,
                        'amount' => $amount,
                        'payment_date' => now(),
                        'payment_method' => Payment::PAYMENT_METHOD_BANK_TRANSFER,
                        'transaction_reference' => $transactionReference,
                        'status' => Payment::STATUS_PENDING_CONFIRMATION,
                        'proof_image' => null,
                        'note' => '[Hệ thống cảnh báo] Hóa đơn này đã được thanh toán đầy đủ trước đó. Giao dịch này bị dư thừa. Vui lòng đối soát và hoàn tiền cho khách thuê. Nội dung gốc: ' . $content,
                        'collected_by' => null,
                    ]);

                    DB::afterCommit(function () use ($invoiceModel, $payment, $amount): void {
                        $this->notifyAdminExcessPayment($invoiceModel, $payment, $amount, 'Hóa đơn đã thanh toán đầy đủ trước đó');
                    });

                    Log::warning("SePay Webhook: Duplicate payment for already-paid invoice {$invoiceModel->invoice_code}, amount: {$amount}, ref: {$transactionReference}");

                    return ApiResponse::responseJson(true, 'Giao dịch dư thừa đã được ghi nhận để đối soát.', 0, ['status' => 'duplicate_logged'], 200);
                }

                if (! in_array((int) $invoiceModel->status, [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_OVERDUE], true)) {
                    return ApiResponse::responseJson(false, 'Hóa đơn không ở trạng thái có thể thanh toán.', 422, null, 422);
                }

                if (DecimalMoney::toIntegerAmount($amount) > DecimalMoney::toIntegerAmount($invoiceModel->remaining_amount)) {
                    $payment = Payment::query()->create([
                        'payment_code' => $this->makePaymentCode(),
                        'invoice_id' => $invoiceModel->id,
                        'amount' => $amount,
                        'payment_date' => now(),
                        'payment_method' => Payment::PAYMENT_METHOD_BANK_TRANSFER,
                        'transaction_reference' => $transactionReference,
                        'status' => Payment::STATUS_PENDING_CONFIRMATION,
                        'proof_image' => null,
                        'note' => '[Hệ thống cảnh báo] Số tiền giao dịch vượt quá số tiền còn lại của hóa đơn. Vui lòng đối soát và hoàn trả tiền thừa cho khách thuê. Nội dung gốc: ' . $content,
                        'collected_by' => null,
                    ]);

                    DB::afterCommit(function () use ($invoiceModel, $payment, $amount): void {
                        $this->notifyAdminExcessPayment($invoiceModel, $payment, $amount, 'Số tiền giao dịch vượt quá số tiền còn lại');
                    });

                    Log::warning("SePay Webhook: Overpayment for invoice {$invoiceModel->invoice_code}, amount: {$amount}, remaining: {$invoiceModel->remaining_amount}, ref: {$transactionReference}");

                    return ApiResponse::responseJson(true, 'Giao dịch vượt mức đã được ghi nhận để đối soát.', 0, ['status' => 'overpaid_logged'], 200);
                }

                $payment = Payment::query()->create([
                    'payment_code' => $this->makePaymentCode(),
                    'invoice_id' => $invoiceModel->id,
                    'amount' => $amount,
                    'payment_date' => now(),
                    'payment_method' => Payment::PAYMENT_METHOD_BANK_TRANSFER,
                    'transaction_reference' => $transactionReference,
                    'status' => Payment::STATUS_CONFIRMED,
                    'proof_image' => null,
                        'note' => ((int) $invoiceModel->id !== (int) $invoice->id ? 'Tự động chuyển từ mã hóa đơn nợ cũ '.$invoice->invoice_code.'. ' : '').'Hệ thống tự động ghi nhận qua SePay Webhook. Nội dung gốc: ' . $content,
                        'collected_by' => null,
                ]);

                $this->applyConfirmedPayment($invoiceModel, (string) $payment->amount);
                $this->debtRolloverService->allocateConfirmedPaymentToDebtRollovers($invoiceModel->fresh(), $payment);
                $notifications = $this->createInvoicePaidNotifications($invoiceModel->fresh(['room.building', 'contract.contractTenants.tenant']), $payment);

                DB::afterCommit(function () use ($invoiceModel, $notifications): void {
                    event(new InvoicePaid($invoiceModel->fresh(['room.building', 'contract.contractTenants.tenant'])));
                    $this->broadcastNotifications($notifications);
                });

                Log::info("SePay Webhook: Successfully processed payment for invoice {$invoiceModel->invoice_code}, amount: {$amount}");

                return ApiResponse::responseJson(true, 'Xử lý thanh toán hóa đơn thành công.', 0, null, 200);
            });
        } finally {
            optional($lock)->release();
        }

        return $response;
    }

    private function extractInvoiceCode(string $content): ?string
    {
        if (preg_match('/(INV-[A-Za-z0-9_\-\.]+)/i', $content, $matches)) {
            return strtoupper($matches[1]);
        }

        $cleanContent = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $content));
        $recentInvoices = Invoice::query()
            ->select(['id', 'invoice_code'])
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_OVERDUE])
            ->orderByDesc('id')
            ->take(300)
            ->get();

        foreach ($recentInvoices as $invoice) {
            $cleanCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $invoice->invoice_code));
            if (! empty($cleanCode) && str_contains($cleanContent, $cleanCode)) {
                return $invoice->invoice_code;
            }
        }

        return null;
    }

    private function extractTransferCode(string $content): ?string
    {
        if (preg_match('/(TRF-[A-Za-z0-9_\-\.]+)/i', $content, $matches)) {
            return strtoupper($matches[1]);
        }

        $cleanContent = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $content));
        $pendingTransfers = RoomMovement::query()
            ->select(['transfer_code'])
            ->whereNotNull('transfer_code')
            ->whereIn('settlement_payment_status', [
                RoomMovement::SETTLEMENT_PAYMENT_STATUS_PENDING,
                RoomMovement::SETTLEMENT_PAYMENT_STATUS_PARTIAL,
            ])
            ->orderByDesc('id')
            ->take(300)
            ->get()
            ->pluck('transfer_code')
            ->unique();

        foreach ($pendingTransfers as $transferCode) {
            $cleanCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $transferCode));
            if ($cleanCode !== '' && str_contains($cleanContent, $cleanCode)) {
                return (string) $transferCode;
            }
        }

        return null;
    }

    private function transactionReferenceExists(string $transactionReference): bool
    {
        return Payment::query()->where('transaction_reference', $transactionReference)->exists()
            || ContractDepositTransaction::query()->where('transaction_reference', $transactionReference)->exists();
    }

    private function processTransferSettlementPayment(string $transferCode, string $amount, string $transactionReference, string $content): JsonResponse
    {
        $lock = Cache::lock('sepay-transfer-payment:' . $transferCode, 10);

        if (! $lock->get()) {
            return ApiResponse::responseJson(false, 'Giao dịch đang được xử lý, vui lòng thử lại sau.', 409, null, 409);
        }

        try {
            $response = DB::transaction(function () use ($transferCode, $amount, $transactionReference, $content): JsonResponse {
                $movements = RoomMovement::query()
                    ->where('transfer_code', $transferCode)
                    ->where('status', RoomMovement::STATUS_EXECUTED)
                    ->lockForUpdate()
                    ->get();

                if ($movements->isEmpty()) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy lịch chuyển phòng cần thanh toán.', 404, null, 404);
                }

                $firstMovement = $movements->first();
                $references = collect($firstMovement->settlement_payment_references ?? []);
                if ($references->contains(fn (array $reference): bool => ($reference['reference'] ?? null) === $transactionReference)) {
                    return ApiResponse::responseJson(true, 'Giao dịch chuyển phòng đã được xử lý.', 0, ['status' => 'ignored'], 200);
                }

                $remainingAmount = DecimalMoney::maxZero(DecimalMoney::subtract($firstMovement->settlement_due_amount, $firstMovement->settlement_paid_amount));
                if (! DecimalMoney::isPositive($remainingAmount)) {
                    return ApiResponse::responseJson(true, 'Khoản chuyển phòng đã được thanh toán đủ.', 0, ['status' => 'ignored'], 200);
                }

                $appliedAmount = DecimalMoney::min($amount, $remainingAmount);
                $destinationContract = $this->destinationContractForTransferSettlement($firstMovement);
                $allocation = $this->allocateTransferSettlementPayment($firstMovement, $references, $appliedAmount, $destinationContract);

                $this->recordTransferDepositPayment($destinationContract, $allocation['deposit_amount'], $transactionReference, $content);

                $paidAmount = DecimalMoney::add([$firstMovement->settlement_paid_amount, $appliedAmount]);
                $paymentStatus = DecimalMoney::compare($paidAmount, $firstMovement->settlement_due_amount) >= 0
                    ? RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID
                    : RoomMovement::SETTLEMENT_PAYMENT_STATUS_PARTIAL;

                $newReference = [
                    'reference' => $transactionReference,
                    'amount' => $appliedAmount,
                    'deposit_amount' => $allocation['deposit_amount'],
                    'extra_amount' => $allocation['extra_amount'],
                    'paid_at' => now()->toDateTimeString(),
                ];

                $updatedReferences = $references->push($newReference)->values()->all();
                $movements->each(fn (RoomMovement $movement): bool => $movement->forceFill([
                    'settlement_paid_amount' => $paidAmount,
                    'settlement_payment_status' => $paymentStatus,
                    'settlement_payment_references' => $updatedReferences,
                ])->save());

                $notifications = $this->createTransferSettlementPaidNotifications($movements->fresh(['tenant', 'toRoom.building']), $appliedAmount, $paymentStatus);

                DB::afterCommit(function () use ($notifications): void {
                    $this->broadcastNotifications($notifications);
                });

                Log::info("SePay Webhook: Successfully processed transfer settlement {$transferCode}, amount: {$appliedAmount}");

                return ApiResponse::responseJson(true, 'Xử lý thanh toán chuyển phòng thành công.', 0, null, 200);
            });
        } finally {
            optional($lock)->release();
        }

        return $response;
    }

    /**
     * Tách tiền settlement chuyển phòng: ưu tiên bù cọc mới, phần còn lại là phí/khấu trừ.
     */
    private function allocateTransferSettlementPayment(RoomMovement $movement, Collection $references, string $appliedAmount, ?Contract $destinationContract): array
    {
        $depositPaidByTransfer = DecimalMoney::add($references->pluck('deposit_amount')->all());
        $depositRemainingForTransfer = DecimalMoney::maxZero(DecimalMoney::subtract($movement->deposit_due_amount, $depositPaidByTransfer));
        $depositAmount = $destinationContract
            ? DecimalMoney::min($appliedAmount, $depositRemainingForTransfer)
            : '0.00';

        return [
            'deposit_amount' => $depositAmount,
            'extra_amount' => DecimalMoney::maxZero(DecimalMoney::subtract($appliedAmount, $depositAmount)),
        ];
    }

    private function destinationContractForTransferSettlement(RoomMovement $movement): ?Contract
    {
        if (! $movement->destination_contract_id) {
            return null;
        }

        return Contract::query()->lockForUpdate()->find($movement->destination_contract_id);
    }

    private function recordTransferDepositPayment(?Contract $destinationContract, string $depositAmount, string $transactionReference, string $content): void
    {
        if (! $destinationContract || ! DecimalMoney::isPositive($depositAmount)) {
            return;
        }

        $destinationContract->depositTransactions()->create([
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            'amount' => $depositAmount,
            'transaction_date' => now()->toDateString(),
            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
            'transaction_reference' => $transactionReference,
            'note' => 'Hệ thống tự động ghi nhận cọc chuyển phòng qua SePay. Nội dung gốc: '.$content,
            'created_by' => null,
        ]);
    }

    private function applyConfirmedPayment(Invoice $invoice, string $amount): void
    {
        $paidAmount = DecimalMoney::add([$invoice->paid_amount, $amount]);
        $remainingAmount = DecimalMoney::maxZero(DecimalMoney::subtract($invoice->total_amount, $paidAmount));
        if (DecimalMoney::compare($remainingAmount, '1.00') < 0) {
            $remainingAmount = '0.00';
        }
        $status = DecimalMoney::compare($remainingAmount, '0') === 0
            ? Invoice::STATUS_PAID
            : Invoice::STATUS_PARTIALLY_PAID;

        $invoice->forceFill([
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'status' => $status,
        ])->save();
    }

    private function createInvoicePaidNotifications(Invoice $invoice, Payment $payment): Collection
    {
        $invoice->loadMissing(['room.building', 'contract.contractTenants.tenant']);
        $amountText = number_format(DecimalMoney::toIntegerAmount($payment->amount), 0, ',', '.') . ' VND';
        $tenantNotifications = $invoice->contract->contractTenants
            ->filter(fn ($contractTenant): bool => (bool) $contractTenant->is_staying)
            ->map(fn ($contractTenant): Notification => Notification::query()->create([
                'title' => 'Thanh toán hóa đơn thành công',
                'content' => "Hệ thống đã ghi nhận thanh toán {$amountText} cho hóa đơn {$invoice->invoice_code}. Trạng thái hiện tại: " . (Invoice::STATUS_LABELS[$invoice->status] ?? 'Đã cập nhật') . '.',
                'notification_type' => Notification::NOTIFICATION_TYPE_INVOICE,
                'target_type' => Notification::TARGET_TYPE_TENANT,
                'action_url' => '/admin/invoices?id=' . $invoice->id,
                'building_id' => $invoice->room?->building_id,
                'room_id' => $invoice->room_id,
                'tenant_id' => $contractTenant->tenant_id,
                'published_at' => now(),
                'status' => Notification::STATUS_SENT,
                'created_by' => null,
            ]));

        $adminNotification = Notification::query()->create([
            'title' => 'Hóa đơn đã được thanh toán',
            'content' => 'Phòng ' . ($invoice->room?->room_number ?? 'chưa rõ') . ' của tòa nhà ' . ($invoice->room?->building?->name ?? 'chưa rõ') . " đã thanh toán thành công hóa đơn {$invoice->invoice_code}. Số tiền: {$amountText}.",
            'notification_type' => Notification::NOTIFICATION_TYPE_INVOICE,
            'target_type' => Notification::TARGET_TYPE_ADMIN,
            'action_url' => '/admin/invoices?id=' . $invoice->id,
            'building_id' => $invoice->room?->building_id,
            'room_id' => $invoice->room_id,
            'tenant_id' => null,
            'published_at' => now(),
            'status' => Notification::STATUS_SENT,
            'created_by' => null,
        ]);

        return $tenantNotifications->push($adminNotification)->values();
    }

    private function createTransferSettlementPaidNotifications(Collection $movements, string $amount, int $paymentStatus): Collection
    {
        $amountText = number_format(DecimalMoney::toIntegerAmount($amount), 0, ',', '.') . ' VND';
        $statusLabel = RoomMovement::SETTLEMENT_PAYMENT_STATUS_LABELS[$paymentStatus] ?? 'Đã cập nhật';
        $firstMovement = $movements->first();
        $room = $firstMovement?->toRoom;
        $building = $room?->building;

        $tenantNotifications = $movements
            ->unique('tenant_id')
            ->map(fn (RoomMovement $movement): Notification => Notification::query()->create([
                'title' => 'Thanh toán chuyển phòng thành công',
                'content' => "Hệ thống đã ghi nhận {$amountText} cho mã chuyển phòng {$movement->transfer_code}. Trạng thái: {$statusLabel}.",
                'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
                'target_type' => Notification::TARGET_TYPE_TENANT,
                'action_url' => '/admin/room-movements?movement_id=' . $movement->id,
                'building_id' => $movement->toRoom?->building_id,
                'room_id' => $movement->to_room_id,
                'tenant_id' => $movement->tenant_id,
                'published_at' => now(),
                'status' => Notification::STATUS_SENT,
                'created_by' => null,
            ]));

        $adminNotification = Notification::query()->create([
            'title' => 'Thanh toán chuyển phòng',
            'content' => 'Mã chuyển phòng '.($firstMovement?->transfer_code ?? 'không rõ').' tại phòng '.($room?->room_number ?? 'chưa rõ').' - tòa nhà '.($building?->name ?? 'chưa rõ')." đã ghi nhận {$amountText}. Trạng thái: {$statusLabel}.",
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_ADMIN,
            'action_url' => $firstMovement ? '/admin/room-movements?movement_id=' . $firstMovement->id : '/admin/room-movements',
            'building_id' => $room?->building_id,
            'room_id' => $room?->id,
            'tenant_id' => null,
            'published_at' => now(),
            'status' => Notification::STATUS_SENT,
            'created_by' => null,
        ]);

        return $tenantNotifications->push($adminNotification)->values();
    }

    private function broadcastNotifications(Collection $notifications): void
    {
        $notifications->each(fn (Notification $notification): mixed => event(new NotificationSent($notification)));
    }

    private function makePaymentCode(): string
    {
        $prefix = 'PAY-' . now()->format('Y-m') . '-';
        $next = Payment::query()
            ->where('payment_code', 'like', $prefix . '%')
            ->lockForUpdate()
            ->count() + 1;

        do {
            $code = $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (Payment::query()->where('payment_code', $code)->exists());

        return $code;
    }
}
