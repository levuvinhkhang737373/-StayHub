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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SePayWebhookController extends Controller
{
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

            DB::transaction(function () use ($contract, $amount, $transactionReference, $content): void {
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

    private function processInvoicePayment(Invoice $invoice, string $amount, string $transactionReference, string $content): JsonResponse
    {
        $lock = Cache::lock('sepay-invoice-payment:' . sha1($transactionReference), 10);

        if (! $lock->get()) {
            return ApiResponse::responseJson(false, 'Giao dịch đang được xử lý, vui lòng thử lại sau.', 409, null, 409);
        }

        try {
            $response = DB::transaction(function () use ($invoice, $amount, $transactionReference, $content): JsonResponse {
                $invoiceModel = Invoice::query()
                    ->with(['room.building', 'contract.contractTenants.tenant'])
                    ->lockForUpdate()
                    ->find($invoice->id);

                if (! $invoiceModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy hóa đơn.', 404, null, 404);
                }

                if (Payment::query()->where('transaction_reference', $transactionReference)->exists()) {
                    return ApiResponse::responseJson(true, 'Giao dịch đã được xử lý.', 0, ['status' => 'ignored'], 200);
                }

                if ((int) $invoiceModel->status === Invoice::STATUS_PAID) {
                    return ApiResponse::responseJson(true, 'Hóa đơn đã thanh toán trước đó.', 0, ['status' => 'ignored'], 200);
                }

                if (! in_array((int) $invoiceModel->status, [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_OVERDUE], true)) {
                    return ApiResponse::responseJson(false, 'Hóa đơn không ở trạng thái có thể thanh toán.', 422, null, 422);
                }

                if (DecimalMoney::compare($amount, $invoiceModel->remaining_amount) > 0) {
                    return ApiResponse::responseJson(false, 'Số tiền giao dịch vượt quá số tiền hóa đơn còn lại.', 422, null, 422);
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
                    'note' => 'Hệ thống tự động ghi nhận qua SePay Webhook. Nội dung gốc: ' . $content,
                    'collected_by' => null,
                ]);

                $this->applyConfirmedPayment($invoiceModel, (string) $payment->amount);
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

    private function transactionReferenceExists(string $transactionReference): bool
    {
        return Payment::query()->where('transaction_reference', $transactionReference)->exists()
            || ContractDepositTransaction::query()->where('transaction_reference', $transactionReference)->exists();
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
            'building_id' => $invoice->room?->building_id,
            'room_id' => $invoice->room_id,
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
