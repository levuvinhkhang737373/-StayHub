<?php

namespace App\Http\Controllers\Admin;

use App\Events\InvoicePaid;
use App\Events\InvoiceIssued;
use App\Events\InvoiceReissued;
use App\Events\NotificationSent;
use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\DecimalMoney;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\InvoiceDetailResource;
use App\Http\Resources\Admin\InvoicePreviewResource;
use App\Http\Resources\Admin\InvoiceResource;
use App\Http\Requests\Admin\Invoice\IndexRequest;
use App\Http\Requests\Admin\Invoice\ShowRequest;
use App\Http\Requests\Admin\Invoice\GenerateRequest;
use App\Http\Requests\Admin\Invoice\UpdateRequest;
use App\Http\Requests\Admin\Invoice\RecordPaymentRequest;
use App\Http\Requests\Admin\Invoice\ConfirmPaymentRequest;
use App\Http\Requests\Admin\Invoice\CancelRequest;
use App\Models\Admin;
use App\Models\Contract;
use App\Models\ContractVehicle;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MeterDevice;
use App\Models\MeterReading;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\RoomMovement;
use App\Models\RoomService;
use App\Models\RoomServicePrice;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Services\RoomServiceLifecycleService;
use App\Services\Invoice\InvoiceDebtRolloverService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    // Khởi tạo controller quản lý hóa đơn
    public function __construct(private readonly InvoiceDebtRolloverService $debtRolloverService) {}

    private const ADJUSTMENT_ITEM_TYPES = [
        InvoiceItem::ITEM_TYPE_SURCHARGE,
        InvoiceItem::ITEM_TYPE_DISCOUNT,
        InvoiceItem::ITEM_TYPE_ADJUST_INCREASE,
        InvoiceItem::ITEM_TYPE_ADJUST_DECREASE,
    ];

    private const DECREASE_ADJUSTMENT_ITEM_TYPES = [
        InvoiceItem::ITEM_TYPE_DISCOUNT,
        InvoiceItem::ITEM_TYPE_ADJUST_DECREASE,
    ];

    private const DECREASE_ADJUSTMENT_LIMIT_MESSAGE = 'Tổng giảm trừ không được vượt quá số tiền hóa đơn';

    // Danh sách hóa đơn
    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            $keyword = trim($validated['keyword'] ?? '');

            $invoices = $keyword !== ''
                ? $this->searchInvoices($keyword, $validated, $admin)
                : $this->queryInvoices($validated, $admin)->paginate($validated['per_page'] ?? 10);

            $stats = $this->calculateStats($validated, $admin, $keyword);

            return ApiResponse::responseJson(true, 'Danh sách hóa đơn', 200, [
                'data' => InvoiceResource::collection($invoices->items())->resolve(),
                'pagination' => [
                    'current_page' => $invoices->currentPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                    'last_page' => $invoices->lastPage(),
                ],
                'stats' => $stats,
            ], 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Tính toán số liệu thống kê hóa đơn của tòa nhà
    private function calculateStats(array $validated, Admin $admin, string $keyword): array
    {
        $query = $this->accessibleInvoiceQuery($admin);

        if (isset($validated['building_id'])) {
            $query->whereHas('room', fn (Builder $roomQuery): Builder => $roomQuery->where('building_id', $validated['building_id']));
        }

        if (isset($validated['room_id'])) {
            $query->where('room_id', $validated['room_id']);
        }

        if (isset($validated['contract_id'])) {
            $query->where('contract_id', $validated['contract_id']);
        }

        if (isset($validated['billing_month'])) {
            $query->where('billing_month', $validated['billing_month']);
        }

        if (isset($validated['billing_year'])) {
            $query->where('billing_year', $validated['billing_year']);
        }

        if ($keyword !== '') {
            $query->where(function (Builder $q) use ($keyword): void {
                $q->where('invoice_code', 'like', "%{$keyword}%")
                  ->orWhereHas('room', fn (Builder $roomQ): Builder => $roomQ->where('room_number', 'like', "%{$keyword}%"))
                  ->orWhereHas('contract.contractTenants.tenant', fn (Builder $tenantQ): Builder => $tenantQ->where('full_name', 'like', "%{$keyword}%"));
            });
        }

        $stats = $query->selectRaw('
            COUNT(CASE WHEN status IN (2, 3, 5) THEN 1 END) as total_unpaid,
            COUNT(CASE WHEN status = 4 THEN 1 END) as total_paid,
            COUNT(*) as total_count
        ')->first();

        return [
            'total_unpaid' => $stats ? (int) $stats->total_unpaid : 0,
            'total_paid' => $stats ? (int) $stats->total_paid : 0,
            'total_count' => $stats ? (int) $stats->total_count : 0,
        ];
    }

    // Xem chi tiết hóa đơn
    public function show(ShowRequest $request, int $invoice): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            $invoiceModel = $this->accessibleInvoiceQuery($admin)
                ->with($this->detailRelations())
                ->find($invoice);

            if (! $invoiceModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy hóa đơn', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết hóa đơn', 200, new InvoiceDetailResource($invoiceModel), 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Xem trước hóa đơn chuẩn bị tạo cho phòng
    public function preview(GenerateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            $draft = $this->prepareInvoiceDraft($validated, $admin, false);
            if ($draft instanceof JsonResponse) {
                return $draft;
            }

            return ApiResponse::responseJson(true, 'Xem trước hóa đơn thành công', 200, new InvoicePreviewResource($draft), 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Phát hành hóa đơn cho phòng
    public function generate(GenerateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            $response = DB::transaction(function () use ($validated, $admin, $request): JsonResponse {
                $draft = $this->prepareInvoiceDraft($validated, $admin, true);
                if ($draft instanceof JsonResponse) {
                    return $draft;
                }

                $contract = $draft['contract'];
                $periodStart = $draft['period_start'];
                $periodEnd = $draft['period_end'];
                $dueDate = $draft['due_date'];
                $items = $draft['items'];
                $totalAmount = $draft['total_amount'];

                $invoice = Invoice::query()->create([
                    'invoice_code' => $this->makeInvoiceCode($periodStart),
                    'contract_id' => $contract->id,
                    'room_id' => $contract->room_id,
                    'billing_month' => $periodStart->month,
                    'billing_year' => $periodStart->year,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'previous_debt_amount' => $draft['previous_debt_amount'],
                    'total_amount' => $totalAmount,
                    'paid_amount' => '0.00',
                    'remaining_amount' => $totalAmount,
                    'due_date' => $dueDate->toDateString(),
                    'status' => $draft['status'],
                    'issued_at' => now(),
                    'created_by' => $admin->id,
                ]);

                $invoice->items()->createMany($items);
                $this->createDebtRollovers($invoice, $draft['previous_debt_rollovers'] ?? []);

                $this->markMeterReadingsInvoiced($invoice);
                $tenantNotifications = $this->createInvoiceIssuedNotifications($invoice, $admin);

                AdminActivityLogger::write($admin, 'Tạo và phát hành hóa đơn', Invoice::class, $invoice->id, null, $invoice->toArray(), $request);

                DB::afterCommit(function () use ($invoice, $tenantNotifications): void {
                    event(new InvoiceIssued($invoice->fresh($this->detailRelations())));
                    $this->broadcastNotifications($tenantNotifications);
                });

                $invoice->load($this->detailRelations());

                return ApiResponse::responseJson(true, 'Lập và phát hành hóa đơn thành công', 201, new InvoiceDetailResource($invoice), 201);
            });

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Chuẩn bị bản nháp hóa đơn
    private function prepareInvoiceDraft(array $validated, Admin $admin, bool $forIssue): array|JsonResponse
    {
        $contractQuery = Contract::query()
            ->with(['room.building', 'contractTenants.tenant', 'contractVehicles.vehicle']);

        if ($forIssue) {
            $contractQuery->lockForUpdate();
        }

        $contract = $contractQuery->find($validated['contract_id']);

        if (! $contract) {
            return ApiResponse::responseJson(false, 'Không tìm thấy hợp đồng', 404, null, 404);
        }

        if (! $contract->room || ! $this->canAccessBuilding($admin, (int) $contract->room->building_id)) {
            return ApiResponse::responseJson(false, 'Bạn không có quyền lập hóa đơn cho phòng này', 403, null, 403);
        }

        if (! in_array((int) $contract->status, [Contract::STATUS_ACTIVE, Contract::STATUS_EXPIRED, Contract::STATUS_LIQUIDATED], true)) {
            return ApiResponse::responseJson(false, 'Chỉ lập hóa đơn cho hợp đồng đang hiệu lực, vừa hết hạn hoặc đã thanh lý trong kỳ', 422, null, 422);
        }

        $periodStart = Carbon::create((int) $validated['billing_year'], (int) $validated['billing_month'], 1)->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth()->startOfDay();
        $dueDate = isset($validated['due_date'])
            ? Carbon::parse($validated['due_date'])->startOfDay()
            : $periodStart->copy()->addMonthNoOverflow()->day(5)->startOfDay();

        if ($contract->start_date && $contract->start_date->copy()->startOfDay()->greaterThan($periodEnd)) {
            return ApiResponse::responseJson(false, 'Hợp đồng chưa bắt đầu trong kỳ hóa đơn này', 422, null, 422);
        }

        $contractEndDate = $contract->actual_end_date ?: $contract->end_date;
        if ($contractEndDate && $contractEndDate->copy()->startOfDay()->lessThan($periodStart)) {
            return ApiResponse::responseJson(false, 'Hợp đồng đã kết thúc trước kỳ hóa đơn này', 422, null, 422);
        }

        $invoicePeriodEnd = $this->invoicePeriodEndForPendingTransfer($contract, $periodStart, $periodEnd);

        $existingInvoiceQuery = Invoice::query()
            ->where('contract_id', $contract->id)
            ->where('billing_year', $periodStart->year)
            ->where('billing_month', $periodStart->month)
            ->withCount(['payments' => fn (Builder $query): Builder => $query->realMoney()])
            ->where('status', '!=', Invoice::STATUS_CANCELLED);

        $existingInvoice = $forIssue
            ? $existingInvoiceQuery->lockForUpdate()->first()
            : $existingInvoiceQuery->first();

        if ($existingInvoice) {
            if ($this->canReplaceExistingInvoiceForTransferCutoff($existingInvoice, $invoicePeriodEnd, $periodEnd)) {
                if ($forIssue) {
                    $this->cancelInvoiceBeforeTransferFinalization($existingInvoice);
                }
            } elseif ($this->isTransferFinalizationBlockedByExistingInvoice($existingInvoice, $invoicePeriodEnd, $periodEnd)) {
                return ApiResponse::responseJson(false, 'Hợp đồng đã có hóa đơn tháng này trước khi lên lịch chuyển phòng. Vui lòng hủy hoặc phát hành lại hóa đơn cũ chưa thu tiền trước khi lập hóa đơn chốt chuyển phòng.', 422, null, 422);
            } else {
                return ApiResponse::responseJson(false, 'Hợp đồng này đã có hóa đơn trong kỳ đã chọn', 422, null, 422);
            }
        }

        if ($forIssue) {
            $conflictingInvoice = Invoice::query()
                ->where('contract_id', $contract->id)
                ->where('billing_year', $periodStart->year)
                ->where('billing_month', $periodStart->month)
                ->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->lockForUpdate()
                ->first();

            if ($conflictingInvoice) {
                return ApiResponse::responseJson(false, 'Hợp đồng này đã có hóa đơn trong kỳ đã chọn', 422, null, 422);
            }
        }

        $automaticItems = $this->buildAutomaticItems($contract, $periodStart, $periodEnd, $forIssue);
        if (! empty($automaticItems['errors'])) {
            return ApiResponse::responseJson(false, implode(' ', $automaticItems['errors']), 422, null, 422);
        }

        $invoicePeriodEnd = $automaticItems['invoice_period_end'] ?? $periodEnd;

        $items = array_merge($automaticItems['items'], $this->buildAdjustmentItems($validated['adjustments'] ?? []));
        $totalAmount = $this->calculateItemsTotal($items);

        if ($message = $this->decreaseAdjustmentLimitMessage($items)) {
            return ApiResponse::responseJson(false, $message, 422, null, 422);
        }

        if (DecimalMoney::compare($totalAmount, '0') < 0) {
            return ApiResponse::responseJson(false, 'Tổng tiền hóa đơn không được âm', 422, null, 422);
        }

        return [
            'admin' => $admin,
            'contract' => $contract,
            'period_start' => $periodStart,
            'period_end' => $invoicePeriodEnd,
            'due_date' => $dueDate,
            'previous_debt_amount' => $automaticItems['previous_debt_amount'],
            'previous_debt_rollovers' => $automaticItems['previous_debt_rollovers'],
            'total_amount' => $totalAmount,
            'items' => $items,
            'transfer_cutoffs' => $automaticItems['transfer_cutoffs'],
            'status' => DecimalMoney::compare($totalAmount, '0') <= 0 ? Invoice::STATUS_PAID : Invoice::STATUS_UNPAID,
        ];
    }

    // Cập nhật thông tin hóa đơn
    public function update(UpdateRequest $request, int $invoice): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            $response = DB::transaction(function () use ($validated, $invoice, $admin, $request): JsonResponse {
                $invoiceModel = $this->accessibleInvoiceQuery($admin)
                    ->with($this->detailRelations())
                    ->withCount(['payments' => fn (Builder $query): Builder => $query->realMoney()])
                    ->lockForUpdate()
                    ->find($invoice);

                if (! $invoiceModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy hóa đơn', 404, null, 404);
                }

                $guardResponse = $this->ensureInvoiceCanBeReissued($invoiceModel);
                if ($guardResponse instanceof JsonResponse) {
                    return $guardResponse;
                }

                $oldData = $invoiceModel->toArray();
                $reason = trim($validated['reason']);
                $affectedInvoiceIds = collect([$invoiceModel->id]);

                if (array_key_exists('due_date', $validated)) {
                    $invoiceModel->due_date = $validated['due_date'] ?? null;
                }

                if (! empty($validated['meter_readings'])) {
                    $affectedInvoiceIds = $affectedInvoiceIds->merge($this->applyMeterReadingCorrections($invoiceModel, $validated['meter_readings']));
                }

                if (array_key_exists('adjustments', $validated)) {
                    $invoiceModel->items()
                        ->whereIn('item_type', self::ADJUSTMENT_ITEM_TYPES)
                        ->whereNull('service_id')
                        ->whereNull('meter_reading_id')
                        ->delete();

                    if (! empty($validated['adjustments'])) {
                        $invoiceModel->items()->createMany($this->buildAdjustmentItems($validated['adjustments']));
                    }
                }

                $affectedInvoiceIds = $affectedInvoiceIds->merge($this->futureDebtInvoiceIds($invoiceModel));

                $affectedInvoices = Invoice::query()
                    ->whereIn('id', $affectedInvoiceIds->unique()->values()->all())
                    ->withCount(['payments' => fn (Builder $query): Builder => $query->realMoney()])
                    ->orderBy('billing_year')
                    ->orderBy('billing_month')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($affectedInvoices as $affectedInvoice) {
                    $guardResponse = $this->ensureInvoiceCanBeReissued($affectedInvoice, $affectedInvoice->id === $invoiceModel->id ? null : 'Hóa đơn phụ thuộc');
                    if ($guardResponse instanceof JsonResponse) {
                        $this->failReissue($guardResponse->getData(true)['message'] ?? 'Không thể phát hành lại hóa đơn phụ thuộc', $guardResponse->getStatusCode());
                    }
                }

                $notifications = collect();
                $reissuedInvoices = collect();
                foreach ($affectedInvoices as $affectedInvoice) {
                    $isSourceInvoice = (int) $affectedInvoice->id === (int) $invoiceModel->id;
                    if (! $isSourceInvoice) {
                        $this->syncPreviousDebtItem($affectedInvoice);
                    }

                    $this->recalculateReissuedInvoice(
                        $affectedInvoice,
                        $admin,
                        $isSourceInvoice ? $reason : "Tự động cập nhật do phát hành lại hóa đơn {$invoiceModel->invoice_code}",
                        $isSourceInvoice ? ($invoiceModel->due_date?->toDateString()) : null,
                        $isSourceInvoice
                    );

                    $freshInvoice = $affectedInvoice->fresh($this->detailRelations());
                    $reissuedInvoices->push($freshInvoice);
                    $notifications = $notifications->merge($this->createInvoiceReissuedNotifications($freshInvoice, $admin, ! $isSourceInvoice));
                }

                AdminActivityLogger::write($admin, 'Phát hành lại hóa đơn', Invoice::class, $invoiceModel->id, $oldData, [
                    'invoice' => $invoiceModel->fresh()->toArray(),
                    'affected_invoice_ids' => $affectedInvoices->pluck('id')->values()->all(),
                    'reason' => $reason,
                ], $request);

                DB::afterCommit(function () use ($reissuedInvoices, $notifications): void {
                    $reissuedInvoices->each(fn (Invoice $reissuedInvoice): mixed => event(new InvoiceReissued($reissuedInvoice)));
                    $this->broadcastNotifications($notifications);
                });

                $invoiceModel = $invoiceModel->fresh($this->detailRelations());

                return ApiResponse::responseJson(true, 'Cập nhật và phát hành lại hóa đơn thành công', 200, new InvoiceDetailResource($invoiceModel), 200);
            });

            return $response;
        } catch (\DomainException $e) {
            $status = in_array((int) $e->getCode(), [403, 404, 409, 422], true) ? (int) $e->getCode() : 422;

            return ApiResponse::responseJson(false, $e->getMessage(), $status, null, $status);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Ghi nhận thanh toán hóa đơn thủ công bằng tiền mặt/chuyển khoản
    public function recordPayment(RecordPaymentRequest $request, int $invoice): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            $lockName = $this->paymentLockName($validated['transaction_reference'] ?? 'manual-'.$invoice);
            $lock = Cache::lock($lockName, 10);

            if (! $lock->get()) {
                return ApiResponse::responseJson(false, 'Giao dịch đang được xử lý, vui lòng thử lại sau', 409, null, 409);
            }

            try {
                $response = DB::transaction(function () use ($validated, $invoice, $admin, $request): JsonResponse {
                    $invoiceModel = $this->accessibleInvoiceQuery($admin)
                        ->with($this->detailRelations())
                        ->lockForUpdate()
                        ->find($invoice);

                    if (! $invoiceModel) {
                        return ApiResponse::responseJson(false, 'Không tìm thấy hóa đơn', 404, null, 404);
                    }

                    if ($rolloverResponse = $this->rejectPaymentForRolledSourceInvoice($invoiceModel)) {
                        return $rolloverResponse;
                    }

                    if (! in_array((int) $invoiceModel->status, [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_OVERDUE], true)) {
                        return ApiResponse::responseJson(false, 'Hóa đơn không ở trạng thái có thể thanh toán', 422, null, 422);
                    }

                    if (! DecimalMoney::isPositive($validated['amount'])) {
                        return ApiResponse::responseJson(false, 'Số tiền thanh toán phải lớn hơn 0', 422, null, 422);
                    }

                    if (DecimalMoney::toIntegerAmount($validated['amount']) > DecimalMoney::toIntegerAmount($invoiceModel->remaining_amount)) {
                        return ApiResponse::responseJson(false, 'Số tiền thanh toán không được vượt quá số tiền còn lại', 422, null, 422);
                    }

                    if (! empty($validated['transaction_reference']) && Payment::query()->where('transaction_reference', $validated['transaction_reference'])->exists()) {
                        return ApiResponse::responseJson(false, 'Mã tham chiếu giao dịch đã tồn tại', 422, null, 422);
                    }

                    $oldData = $invoiceModel->toArray();
                    $payment = Payment::query()->create([
                        'payment_code' => $this->makePaymentCode(),
                        'invoice_id' => $invoiceModel->id,
                        'amount' => DecimalMoney::normalize($validated['amount']),
                        'payment_date' => isset($validated['payment_date']) ? Carbon::parse($validated['payment_date'])->setTimeFrom(now()) : now(),
                        'payment_method' => $validated['payment_method'],
                        'transaction_reference' => $validated['transaction_reference'] ?? null,
                        'status' => Payment::STATUS_CONFIRMED,
                        'proof_image' => $request->file('proof_image') ? ImageHelper::create($request->file('proof_image'), 'payments') : null,
                        'note' => $validated['note'] ?? null,
                        'collected_by' => $admin->id,
                    ]);

                    $this->applyConfirmedPayment($invoiceModel, (string) $payment->amount);
                    $this->allocateConfirmedPaymentToDebtRollovers($invoiceModel->fresh(), $payment);
                    $notifications = $this->createInvoicePaidNotifications($invoiceModel->fresh($this->detailRelations()), $payment, $admin);

                    AdminActivityLogger::write($admin, 'Ghi nhận thanh toán hóa đơn', Payment::class, $payment->id, $oldData, $invoiceModel->fresh()->toArray(), $request);

                    DB::afterCommit(function () use ($invoiceModel, $notifications): void {
                        event(new InvoicePaid($invoiceModel->fresh($this->detailRelations())));
                        $this->broadcastNotifications($notifications);
                    });

                    $invoiceModel->load($this->detailRelations());

                    return ApiResponse::responseJson(true, 'Ghi nhận thanh toán thành công', 201, new InvoiceDetailResource($invoiceModel), 201);
                });
            } finally {
                optional($lock)->release();
            }

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Xác nhận giao dịch thanh toán hóa đơn thành công
    public function confirmPayment(ConfirmPaymentRequest $request, int $invoice, int $payment): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            $response = DB::transaction(function () use ($invoice, $payment, $admin, $request): JsonResponse {
                $invoiceModel = $this->accessibleInvoiceQuery($admin)
                    ->with($this->detailRelations())
                    ->lockForUpdate()
                    ->find($invoice);

                if (! $invoiceModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy hóa đơn', 404, null, 404);
                }

                $paymentModel = Payment::query()
                    ->where('invoice_id', $invoiceModel->id)
                    ->lockForUpdate()
                    ->find($payment);

                if (! $paymentModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy giao dịch thanh toán', 404, null, 404);
                }

                if ($rolloverResponse = $this->rejectPaymentForRolledSourceInvoice($invoiceModel)) {
                    return $rolloverResponse;
                }

                if ((int) $paymentModel->status === Payment::STATUS_CONFIRMED) {
                    return ApiResponse::responseJson(false, 'Giao dịch đã được xác nhận trước đó', 422, null, 422);
                }

                if ((int) $paymentModel->status === Payment::STATUS_CANCELLED) {
                    return ApiResponse::responseJson(false, 'Không thể xác nhận giao dịch đã hủy', 422, null, 422);
                }

                if (DecimalMoney::toIntegerAmount($paymentModel->amount) > DecimalMoney::toIntegerAmount($invoiceModel->remaining_amount)) {
                    return ApiResponse::responseJson(false, 'Số tiền giao dịch vượt quá số tiền hóa đơn còn lại', 422, null, 422);
                }

                $oldData = $invoiceModel->toArray();
                $paymentModel->forceFill([
                    'status' => Payment::STATUS_CONFIRMED,
                    'collected_by' => $admin->id,
                ])->save();

                $this->applyConfirmedPayment($invoiceModel, (string) $paymentModel->amount);
                $this->allocateConfirmedPaymentToDebtRollovers($invoiceModel->fresh(), $paymentModel);
                $notifications = $this->createInvoicePaidNotifications($invoiceModel->fresh($this->detailRelations()), $paymentModel, $admin);

                AdminActivityLogger::write($admin, 'Xác nhận thanh toán hóa đơn', Payment::class, $paymentModel->id, $oldData, $invoiceModel->fresh()->toArray(), $request);

                DB::afterCommit(function () use ($invoiceModel, $notifications): void {
                    event(new InvoicePaid($invoiceModel->fresh($this->detailRelations())));
                    $this->broadcastNotifications($notifications);
                });

                $invoiceModel->load($this->detailRelations());

                return ApiResponse::responseJson(true, 'Xác nhận thanh toán thành công', 200, new InvoiceDetailResource($invoiceModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Hủy hóa đơn
    public function cancel(CancelRequest $request, int $invoice): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            $response = DB::transaction(function () use ($validated, $invoice, $admin, $request): JsonResponse {
                $invoiceModel = $this->accessibleInvoiceQuery($admin)
                    ->withCount(['payments' => fn (Builder $query): Builder => $query->realMoney()->where('status', Payment::STATUS_CONFIRMED)])
                    ->lockForUpdate()
                    ->find($invoice);

                if (! $invoiceModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy hóa đơn', 404, null, 404);
                }

                if ((int) $invoiceModel->payments_count > 0) {
                    return ApiResponse::responseJson(false, 'Không thể hủy hóa đơn đã có giao dịch thanh toán xác nhận', 422, null, 422);
                }

                if ((int) $invoiceModel->status === Invoice::STATUS_CANCELLED) {
                    return ApiResponse::responseJson(false, 'Hóa đơn đã được hủy trước đó', 422, null, 422);
                }

                $rolloverOut = $this->debtRolloverService->activeRolloverOut($invoiceModel);
                if ($rolloverOut?->targetInvoice) {
                    return ApiResponse::responseJson(false, 'Không thể hủy hóa đơn này vì khoản nợ đã chuyển sang hóa đơn '.$rolloverOut->targetInvoice->invoice_code.'.', 422, [
                        'rolled_to_invoice_id' => $rolloverOut->target_invoice_id,
                        'rolled_to_invoice_code' => $rolloverOut->targetInvoice->invoice_code,
                    ], 422);
                }

                $oldData = $invoiceModel->toArray();
                $note = trim($validated['note'] ?? '');
                $this->debtRolloverService->cancelTargetRollovers($invoiceModel);

                $invoiceModel->forceFill([
                    'status' => Invoice::STATUS_CANCELLED,
                    'remaining_amount' => '0.00',
                ])->save();

                if ($note !== '') {
                    $invoiceModel->items()->create([
                        'service_id' => null,
                        'meter_reading_id' => null,
                        'item_type' => InvoiceItem::ITEM_TYPE_ADJUST_DECREASE,
                        'description' => 'Ghi chú hủy hóa đơn: '.$note,
                        'quantity' => '1.00',
                        'unit_price' => '0.00',
                        'amount' => '0.00',
                    ]);
                }

                AdminActivityLogger::write($admin, 'Hủy hóa đơn', Invoice::class, $invoiceModel->id, $oldData, $invoiceModel->fresh()->toArray(), $request);

                $invoiceModel->load($this->detailRelations());

                return ApiResponse::responseJson(true, 'Hủy hóa đơn thành công', 200, new InvoiceDetailResource($invoiceModel), 200);
            });

            return $response;
        } catch (\DomainException $e) {
            $status = in_array((int) $e->getCode(), [403, 404, 409, 422], true) ? (int) $e->getCode() : 422;

            return ApiResponse::responseJson(false, $e->getMessage(), $status, null, $status);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Tạo các khoản mục hóa đơn tự động (tiền phòng, dịch vụ cố định, xe)
    private function buildAutomaticItems(Contract $contract, Carbon $periodStart, Carbon $periodEnd, bool $lockDebt = false): array
    {
        $items = [];
        $errors = [];
        $buildingId = (int) $contract->room->building_id;
        $billingMonth = (int) $periodStart->month;
        $billingYear = (int) $periodStart->year;
        $transferContext = $this->pendingTransferContext($contract, $periodStart, $periodEnd);
        $remainingTransferContext = $this->remainingTransferContext($contract, $periodStart, $periodEnd);
        $servicePeriodStart = $remainingTransferContext['service_period_start'];

        $roomAmount = $this->calculateRoomAmount($contract, $periodStart, $periodEnd, $transferContext['contract_cutoff_date'], $servicePeriodStart);
        $items[] = [
            'service_id' => null,
            'meter_reading_id' => null,
            'item_type' => InvoiceItem::ITEM_TYPE_ROOM,
            'description' => $this->descriptionWithTransferCutoff('Tiền phòng tháng '.str_pad((string) $billingMonth, 2, '0', STR_PAD_LEFT).'/'.$billingYear, $transferContext['contract_cutoff_date']),
            'quantity' => '1.00',
            'unit_price' => DecimalMoney::normalize($contract->room_price),
            'amount' => $roomAmount,
        ];

        $prices = $this->currentServicePrices($buildingId, $periodEnd);
        $meterServiceIds = $prices
            ->filter(fn (ServicePrice $price): bool => (int) $price->service?->charge_method === Service::CHARGE_METHOD_BY_METER)
            ->pluck('service_id')
            ->values()
            ->all();

        $meterDevices = MeterDevice::query()
            ->with(['service', 'readings' => fn ($query) => $query->where('billing_month', $billingMonth)->where('billing_year', $billingYear)])
            ->where('room_id', $contract->room_id)
            ->whereIn('service_id', $meterServiceIds)
            ->where('status', MeterDevice::STATUS_ACTIVE)
            ->get()
            ->keyBy('service_id');

        foreach ($prices as $price) {
            $service = $price->service;
            if (! $service) {
                continue;
            }

            $priceAmount = $price->price;

            // Kiểm tra dịch vụ có phải điện/nước (hoặc tính theo chỉ số) không
            $isMetered = (int) $service->charge_method === Service::CHARGE_METHOD_BY_METER ||
                         in_array($service->slug, ['electric', 'water', 'electricity', 'dien-sinh-hoat', 'nuoc-sinh-hoat'], true);

            if (! $isMetered) {
                $roomService = RoomService::where('room_id', $contract->room_id)
                    ->where('service_id', $service->id)
                    ->usableForPeriod($periodStart, $periodEnd)
                    ->first();

                if (! $roomService) {
                    continue;
                }

                if (! app(RoomServiceLifecycleService::class)->shouldChargeInPeriod($roomService, $periodStart, $periodEnd)) {
                    continue;
                }

                $serviceCutoffDate = $this->serviceChargeEndDate($contract, $roomService, $periodEnd, $transferContext['contract_cutoff_date']);
                $resolvedAmount = $this->roomServicePriceForPeriod($roomService, $serviceCutoffDate, $contract);
                if ($resolvedAmount === null) {
                    $errors[] = "Phòng {$contract->room?->room_number} chưa có giá dịch vụ {$service->name} hiệu lực trong kỳ {$billingMonth}/{$billingYear}.";

                    continue;
                }

                $priceAmount = $resolvedAmount;
            }

            $unitPrice = DecimalMoney::normalize($priceAmount);

            if ((int) $service->charge_method === Service::CHARGE_METHOD_BY_METER) {
                $meterDevice = $meterDevices->get($service->id);
                $reading = $meterDevice?->readings?->first();

                if (! $meterDevice) {
                    $errors[] = "Phòng {$contract->room?->room_number} chưa có đồng hồ đang hoạt động cho dịch vụ {$service->name}.";

                    continue;
                }

                if (! $reading) {
                    $errors[] = "Thiếu chỉ số {$service->name} tháng {$billingMonth}/{$billingYear} cho phòng {$contract->room?->room_number}.";

                    continue;
                }

                if ((int) $reading->status === MeterReading::STATUS_DRAFT) {
                    $errors[] = "Chỉ số {$service->name} tháng {$billingMonth}/{$billingYear} chưa được xác nhận.";

                    continue;
                }

                $items[] = [
                    'service_id' => $service->id,
                    'meter_reading_id' => $reading->id,
                    'item_type' => $this->meterItemType($meterDevice, $service),
                    'description' => $service->name.' ('.((float)$reading->previous_reading).' → '.((float)$reading->current_reading).')',
                    'quantity' => DecimalMoney::normalize($reading->consumption),
                    'unit_price' => $unitPrice,
                    'amount' => DecimalMoney::multiply($reading->consumption, $unitPrice),
                ];

                continue;
            }

            if ((int) $service->charge_method === Service::CHARGE_METHOD_BY_PERSON) {
                $tenantCount = $contract->contractTenants
                    ->filter(fn ($contractTenant): bool => (bool) $contractTenant->is_staying)
                    ->count();

                if ($tenantCount <= 0) {
                    continue;
                }

                $fullAmount = DecimalMoney::multiply((string) $tenantCount, $unitPrice);
                $serviceEndDate = $this->serviceChargeEndDate($contract, $roomService, $periodEnd, $transferContext['contract_cutoff_date']);
                $serviceDescriptionEndDate = $this->serviceDescriptionEndDate($serviceEndDate, $periodEnd);
                $proratedAmount = $this->calculateProratedAmount($fullAmount, $contract, $periodStart, $periodEnd, $serviceEndDate, $servicePeriodStart);

                $items[] = [
                    'service_id' => $service->id,
                    'meter_reading_id' => null,
                    'item_type' => $this->serviceItemType($service),
                    'description' => $this->descriptionWithTransferCutoff($service->name.' ('.$tenantCount.' người)', $serviceDescriptionEndDate),
                    'quantity' => DecimalMoney::normalize((string) $tenantCount),
                    'unit_price' => $unitPrice,
                    'amount' => $proratedAmount,
                ];

                continue;
            }

            if (in_array((int) $service->charge_method, [Service::CHARGE_METHOD_BY_ROOM, Service::CHARGE_METHOD_FIXED], true)) {
                $serviceEndDate = $this->serviceChargeEndDate($contract, $roomService, $periodEnd, $transferContext['contract_cutoff_date']);
                $serviceDescriptionEndDate = $this->serviceDescriptionEndDate($serviceEndDate, $periodEnd);
                $proratedAmount = $this->calculateProratedAmount($unitPrice, $contract, $periodStart, $periodEnd, $serviceEndDate, $servicePeriodStart);

                $items[] = [
                    'service_id' => $service->id,
                    'meter_reading_id' => null,
                    'item_type' => $this->serviceItemType($service),
                    'description' => $this->descriptionWithTransferCutoff($service->name, $serviceDescriptionEndDate),
                    'quantity' => '1.00',
                    'unit_price' => $unitPrice,
                    'amount' => $proratedAmount,
                ];
            }
        }

        $vehicleServiceId = $prices
            ->first(fn (ServicePrice $price): bool => (int) $price->service?->charge_method === Service::CHARGE_METHOD_BY_VEHICLE)
            ?->service_id;

        foreach ($remainingTransferContext['source_vehicle_rows'] as $sourceVehicle) {
            if (DecimalMoney::compare($sourceVehicle->monthly_fee, '0') <= 0) {
                continue;
            }

            $proratedAmount = $this->calculateVehicleProratedAmount($sourceVehicle, $periodStart, $periodEnd, $remainingTransferContext['source_period_end']);
            if (DecimalMoney::compare($proratedAmount, '0') <= 0) {
                continue;
            }

            $description = 'Gửi xe '.($sourceVehicle->vehicle?->license_plate ?? ('#'.$sourceVehicle->vehicle_id));
            $items[] = [
                'service_id' => $vehicleServiceId,
                'meter_reading_id' => null,
                'item_type' => InvoiceItem::ITEM_TYPE_PARKING,
                'description' => $this->descriptionWithRemainingTransferCutoff($description, $remainingTransferContext['source_period_end']),
                'quantity' => '1.00',
                'unit_price' => DecimalMoney::normalize($sourceVehicle->monthly_fee),
                'amount' => $proratedAmount,
            ];
        }

        foreach ($contract->contractVehicles as $contractVehicle) {
            if (DecimalMoney::compare($contractVehicle->monthly_fee, '0') <= 0) {
                continue;
            }

            $vehicleTransferCutoff = $this->vehicleTransferCutoff($contractVehicle, $transferContext['tenant_cutoffs'], $transferContext['contract_cutoff_date']);
            $proratedAmount = $this->calculateVehicleProratedAmount($contractVehicle, $periodStart, $periodEnd, $vehicleTransferCutoff);
            if (DecimalMoney::compare($proratedAmount, '0') <= 0) {
                continue;
            }

            $description = 'Gửi xe '.($contractVehicle->vehicle?->license_plate ?? ('#'.$contractVehicle->vehicle_id));
            $items[] = [
                'service_id' => $vehicleServiceId,
                'meter_reading_id' => null,
                'item_type' => InvoiceItem::ITEM_TYPE_PARKING,
                'description' => $this->descriptionWithTransferCutoff($description, $vehicleTransferCutoff),
                'quantity' => '1.00',
                'unit_price' => DecimalMoney::normalize($contractVehicle->monthly_fee),
                'amount' => $proratedAmount,
            ];
        }

        foreach ($transferContext['incoming_vehicle_rows'] as $incomingVehicle) {
            if (DecimalMoney::compare($incomingVehicle['monthly_fee'], '0') <= 0) {
                continue;
            }

            $proratedAmount = $this->calculatePendingVehicleProratedAmount($incomingVehicle['monthly_fee'], $incomingVehicle['movement_date'], $periodStart, $periodEnd);
            if (DecimalMoney::compare($proratedAmount, '0') <= 0) {
                continue;
            }

            $description = 'Gửi xe '.($incomingVehicle['license_plate'] ?? ('#'.$incomingVehicle['vehicle_id']));
            $items[] = [
                'service_id' => $vehicleServiceId,
                'meter_reading_id' => null,
                'item_type' => InvoiceItem::ITEM_TYPE_PARKING,
                'description' => $this->descriptionWithTransferStart($description, $incomingVehicle['movement_date']),
                'quantity' => '1.00',
                'unit_price' => DecimalMoney::normalize($incomingVehicle['monthly_fee']),
                'amount' => $proratedAmount,
            ];
        }

        $previousDebtRollovers = $this->previousDebtRollovers($contract, $billingYear, $billingMonth, null, $lockDebt);
        $previousDebtAmount = $this->debtRolloverService->previousDebtAmount($previousDebtRollovers);
        if (DecimalMoney::isPositive($previousDebtAmount)) {
            $items[] = [
                'service_id' => null,
                'meter_reading_id' => null,
                'item_type' => InvoiceItem::ITEM_TYPE_OLD_DEBT,
                'description' => 'Nợ cũ các kỳ trước',
                'quantity' => '1.00',
                'unit_price' => $previousDebtAmount,
                'amount' => $previousDebtAmount,
            ];
        }

        return [
            'items' => $items,
            'errors' => $errors,
            'previous_debt_amount' => $previousDebtAmount,
            'previous_debt_rollovers' => $previousDebtRollovers,
            'transfer_cutoffs' => $transferContext['summaries'],
            'invoice_period_end' => $transferContext['contract_cutoff_date'] ?: $periodEnd,
        ];
    }

    // Tính tiền phòng theo số ngày ở thực tế (tính lẻ ngày)
    private function calculateProratedAmount(string $amount, Contract $contract, Carbon $periodStart, Carbon $periodEnd, ?Carbon $cutoffDate = null, ?Carbon $servicePeriodStart = null): string
    {
        $billingStart = $servicePeriodStart ?: $contract->start_date;
        $chargeStart = $billingStart && $billingStart->copy()->startOfDay()->greaterThan($periodStart)
            ? $billingStart->copy()->startOfDay()
            : $periodStart->copy();

        $contractEndDate = $contract->actual_end_date ?: $contract->end_date;
        $chargeEnd = $contractEndDate && $contractEndDate->copy()->startOfDay()->lessThan($periodEnd)
            ? $contractEndDate->copy()->startOfDay()
            : $periodEnd->copy();

        if ($cutoffDate && $cutoffDate->copy()->startOfDay()->lessThan($chargeEnd)) {
            $chargeEnd = $cutoffDate->copy()->startOfDay();
        }

        if ($chargeStart->greaterThan($chargeEnd)) {
            return '0.00';
        }

        $totalDays = (int) $periodEnd->daysInMonth;
        $actualDays = ((int) $chargeStart->diffInDays($chargeEnd)) + 1;

        if ($actualDays >= $totalDays) {
            return DecimalMoney::normalize($amount);
        }

        return DecimalMoney::prorateByDays($amount, $actualDays, $totalDays);
    }

    // Tính tiền xe theo số ngày sử dụng thực tế
    private function calculateVehicleProratedAmount(ContractVehicle $contractVehicle, Carbon $periodStart, Carbon $periodEnd, ?Carbon $cutoffDate = null): string
    {
        $vehicleBillingStart = $contractVehicle->billing_start_date ?: $contractVehicle->started_at;
        $vehicleBillingEnd = $contractVehicle->billing_end_date ?: $contractVehicle->ended_at;

        if (! $contractVehicle->is_active && ! $vehicleBillingEnd) {
            return '0.00';
        }

        $chargeStart = $vehicleBillingStart && $vehicleBillingStart->copy()->startOfDay()->greaterThan($periodStart)
            ? $vehicleBillingStart->copy()->startOfDay()
            : $periodStart->copy();

        $chargeEnd = $vehicleBillingEnd && $vehicleBillingEnd->copy()->startOfDay()->lessThan($periodEnd)
            ? $vehicleBillingEnd->copy()->startOfDay()
            : $periodEnd->copy();

        if ($cutoffDate && $cutoffDate->copy()->startOfDay()->lessThan($chargeEnd)) {
            $chargeEnd = $cutoffDate->copy()->startOfDay();
        }

        if ($chargeStart->greaterThan($chargeEnd)) {
            return '0.00';
        }

        $totalDays = (int) $periodEnd->daysInMonth;
        $actualDays = ((int) $chargeStart->diffInDays($chargeEnd)) + 1;

        if ($actualDays >= $totalDays) {
            return DecimalMoney::normalize($contractVehicle->monthly_fee);
        }

        return DecimalMoney::prorateByDays($contractVehicle->monthly_fee, $actualDays, $totalDays);
    }

    // Tính tổng tiền phòng cho chu kỳ hóa đơn
    private function calculateRoomAmount(Contract $contract, Carbon $periodStart, Carbon $periodEnd, ?Carbon $cutoffDate = null, ?Carbon $servicePeriodStart = null): string
    {
        return $this->calculateProratedAmount($contract->room_price, $contract, $periodStart, $periodEnd, $cutoffDate, $servicePeriodStart);
    }

    private function serviceChargeEndDate(Contract $contract, RoomService $roomService, Carbon $periodEnd, ?Carbon $transferCutoffDate = null): Carbon
    {
        $chargeEnd = $periodEnd->copy()->startOfDay();
        $contractEndDate = $contract->actual_end_date ?: $contract->end_date;

        if ($contractEndDate && $contractEndDate->copy()->startOfDay()->lt($chargeEnd)) {
            $chargeEnd = $contractEndDate->copy()->startOfDay();
        }

        if ($transferCutoffDate && $transferCutoffDate->copy()->startOfDay()->lt($chargeEnd)) {
            $chargeEnd = $transferCutoffDate->copy()->startOfDay();
        }

        $serviceEndedAt = $roomService->ended_at?->copy()->startOfDay();
        if ($serviceEndedAt && $serviceEndedAt->lt($chargeEnd)) {
            $chargeEnd = $serviceEndedAt;
        }

        return $chargeEnd;
    }

    private function serviceDescriptionEndDate(Carbon $serviceEndDate, Carbon $periodEnd): ?Carbon
    {
        return $serviceEndDate->isSameDay($periodEnd) ? null : $serviceEndDate;
    }

    // Lấy thông tin bàn giao/chuyển phòng còn lại
    private function remainingTransferContext(Contract $contract, Carbon $periodStart, Carbon $periodEnd): array
    {
        $empty = [
            'source_contract' => null,
            'source_period_end' => null,
            'source_vehicle_rows' => collect(),
            'service_period_start' => null,
        ];

        if (! $this->isRemainingTransferContract($contract, $periodStart, $periodEnd)) {
            return $empty;
        }

        $sourceContract = $this->remainingTransferSourceContract($contract);
        if (! $sourceContract) {
            return $empty;
        }

        if ($this->hasNonCancelledInvoiceForPeriod($sourceContract, $periodStart)) {
            return $empty;
        }

        $sourcePeriodEnd = $sourceContract->actual_end_date?->copy()->startOfDay();
        if (! $sourcePeriodEnd || $sourcePeriodEnd->lessThan($periodStart) || $sourcePeriodEnd->greaterThan($periodEnd)) {
            return $empty;
        }

        $sourceStartDate = $sourceContract->start_date?->copy()->startOfDay();
        $servicePeriodStart = $sourceStartDate && $sourceStartDate->greaterThan($periodStart)
            ? $sourceStartDate
            : $periodStart->copy();

        return [
            'source_contract' => $sourceContract,
            'source_period_end' => $sourcePeriodEnd,
            'source_vehicle_rows' => $this->remainingTransferSourceVehicles($sourceContract, $periodStart, $periodEnd),
            'service_period_start' => $servicePeriodStart,
        ];
    }

    // Kiểm tra hợp đồng có phải thuộc dạng chuyển phòng còn lại không
    private function isRemainingTransferContract(Contract $contract, Carbon $periodStart, Carbon $periodEnd): bool
    {
        if (! $contract->parent_contract_id || ! $contract->start_date) {
            return false;
        }

        $note = (string) $contract->note;
        if (! str_contains($note, 'người còn ở lại sau khi chuyển phòng')
            && ! str_contains($note, 'người còn ở lại sau khi đại diện chuyển phòng')) {
            return false;
        }

        $startDate = $contract->start_date->copy()->startOfDay();

        return $startDate->greaterThanOrEqualTo($periodStart)
            && $startDate->lessThanOrEqualTo($periodEnd);
    }

    // Lấy hợp đồng gốc của phòng chuyển đi
    private function remainingTransferSourceContract(Contract $contract): ?Contract
    {
        $movementDate = $contract->start_date->copy()->startOfDay();
        $sourceEndDate = $movementDate->copy()->subDay();

        $movements = RoomMovement::query()
            ->with('sourceContract.contractTenants')
            ->where('movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
            ->where('status', RoomMovement::STATUS_EXECUTED)
            ->where('from_room_id', $contract->room_id)
            ->whereDate('movement_date', $movementDate->toDateString())
            ->whereHas('sourceContract', function (Builder $query) use ($contract, $sourceEndDate): void {
                $query->where('room_id', $contract->room_id)
                    ->where('status', Contract::STATUS_LIQUIDATED)
                    ->whereDate('actual_end_date', $sourceEndDate->toDateString());
            })
            ->get();

        $movement = $movements->first(function (RoomMovement $movement) use ($contract): bool {
            $sourceContract = $movement->sourceContract;
            if (! $sourceContract) {
                return false;
            }

            $remainingTenantIds = $contract->contractTenants
                ->pluck('tenant_id')
                ->map(fn ($tenantId): int => (int) $tenantId)
                ->unique();

            $sourceTenantIds = $sourceContract->contractTenants
                ->pluck('tenant_id')
                ->map(fn ($tenantId): int => (int) $tenantId)
                ->unique();

            return $remainingTenantIds->intersect($sourceTenantIds)->isNotEmpty();
        });

        return $movement?->sourceContract;
    }

    // Kiểm tra phòng đã có hóa đơn chưa hủy trong kỳ này chưa
    private function hasNonCancelledInvoiceForPeriod(Contract $contract, Carbon $periodStart): bool
    {
        return Invoice::query()
            ->where('contract_id', $contract->id)
            ->where('billing_year', $periodStart->year)
            ->where('billing_month', $periodStart->month)
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->exists();
    }

    // Kiểm tra có thể thay thế hóa đơn cũ khi chốt chuyển phòng không
    private function canReplaceExistingInvoiceForTransferCutoff(Invoice $invoice, Carbon $invoicePeriodEnd, Carbon $periodEnd): bool
    {
        return $this->isTransferFinalizationBlockedByExistingInvoice($invoice, $invoicePeriodEnd, $periodEnd)
            && $invoice->period_end?->copy()->startOfDay()->isSameDay($periodEnd)
            && (int) ($invoice->payments_count ?? $invoice->payments()->realMoney()->count()) === 0;
    }

    // Kiểm tra việc quyết toán chuyển phòng có bị chặn do hóa đơn hiện tại không
    private function isTransferFinalizationBlockedByExistingInvoice(Invoice $invoice, Carbon $invoicePeriodEnd, Carbon $periodEnd): bool
    {
        return $invoicePeriodEnd->lt($periodEnd)
            && ! $invoice->period_end?->copy()->startOfDay()->isSameDay($invoicePeriodEnd);
    }

    // Hủy hóa đơn cũ trước khi quyết toán chuyển phòng
    private function cancelInvoiceBeforeTransferFinalization(Invoice $invoice): void
    {
        $this->debtRolloverService->cancelTargetRollovers($invoice);
        $invoice->items()->delete();
        $invoice->forceFill([
            'status' => Invoice::STATUS_CANCELLED,
            'remaining_amount' => '0.00',
            'reissued_at' => now(),
            'reissue_reason' => 'Tự động hủy hóa đơn full tháng để lập hóa đơn chốt chuyển phòng theo ngày cắt.',
        ])->save();
    }

    // Lấy danh sách xe của phòng gốc chuyển đi
    private function remainingTransferSourceVehicles(Contract $sourceContract, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        return ContractVehicle::query()
            ->with('vehicle:id,tenant_id,license_plate')
            ->where('contract_id', $sourceContract->id)
            ->where(function (Builder $query) use ($periodStart, $periodEnd): void {
                $query->whereNull('billing_end_date')
                    ->orWhereBetween('billing_end_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                    ->orWhereBetween('ended_at', [$periodStart->toDateString(), $periodEnd->toDateString()]);
            })
            ->orderBy('id')
            ->get();
    }

    // Lấy thông tin giao dịch chuyển phòng đang chờ
    private function pendingTransferContext(Contract $contract, Carbon $periodStart, Carbon $periodEnd): array
    {
        $outgoingMovements = $this->pendingOutgoingTransferMovements($contract, $periodStart, $periodEnd);
        $incomingMovements = $this->pendingIncomingTransferMovements($contract, $periodStart, $periodEnd);

        if ($outgoingMovements->isEmpty() && $incomingMovements->isEmpty()) {
            return [
                'contract_cutoff_date' => null,
                'tenant_cutoffs' => collect(),
                'incoming_vehicle_rows' => collect(),
                'summaries' => [],
            ];
        }

        $activeTenantIds = $contract->contractTenants
            ->filter(fn ($contractTenant): bool => (bool) $contractTenant->is_staying && $contractTenant->leave_date === null)
            ->sortBy(fn ($contractTenant): string => ($contractTenant->join_date?->toDateString() ?? '9999-12-31').'-'.str_pad((string) $contractTenant->id, 10, '0', STR_PAD_LEFT))
            ->pluck('tenant_id')
            ->map(fn ($tenantId): int => (int) $tenantId)
            ->unique()
            ->values();

        $groupedByCode = $outgoingMovements->groupBy('transfer_code');
        $tenantCutoffs = $outgoingMovements
            ->groupBy('tenant_id')
            ->map(fn (Collection $tenantMovements): Carbon => Carbon::parse($tenantMovements->first()->movement_date)->startOfDay()->subDay());

        $contractCutoffDate = null;
        foreach ($groupedByCode as $groupMovements) {
            $movingTenantIds = $groupMovements->pluck('tenant_id')->map(fn ($tenantId): int => (int) $tenantId)->unique()->values();
            $movingAllActiveTenants = $this->pendingTransferClosesSourceContract($contract, $activeTenantIds, $movingTenantIds);

            if (! $movingAllActiveTenants) {
                continue;
            }

            $candidateDate = Carbon::parse($groupMovements->first()->movement_date)->startOfDay()->subDay();
            if (! $contractCutoffDate || $candidateDate->lessThan($contractCutoffDate)) {
                $contractCutoffDate = $candidateDate;
            }
        }

        $incomingVehicleRows = $this->pendingIncomingVehicleRows($contract, $incomingMovements);

        return [
            'contract_cutoff_date' => $contractCutoffDate,
            'tenant_cutoffs' => $tenantCutoffs,
            'incoming_vehicle_rows' => $incomingVehicleRows,
            'summaries' => array_merge(
                $this->transferCutoffSummaries($contract, $groupedByCode, $activeTenantIds),
                $this->transferStartSummaries($incomingVehicleRows)
            ),
        ];
    }

    // Xác định ngày kết thúc kỳ hóa đơn cho phòng đang chờ chuyển
    private function invoicePeriodEndForPendingTransfer(Contract $contract, Carbon $periodStart, Carbon $periodEnd): Carbon
    {
        $transferContext = $this->pendingTransferContext($contract, $periodStart, $periodEnd);

        return $transferContext['contract_cutoff_date'] ?: $periodEnd;
    }

    // Lấy danh sách chuyển phòng đi đang chờ
    private function pendingOutgoingTransferMovements(Contract $contract, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        return RoomMovement::query()
            ->with('tenant:id,full_name')
            ->where('source_contract_id', $contract->id)
            ->where('movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
            ->whereIn('status', [RoomMovement::STATUS_PENDING, RoomMovement::STATUS_BLOCKED])
            ->whereBetween('movement_date', [
                $periodStart->copy()->addDay()->toDateString(),
                $periodEnd->copy()->addDay()->toDateString(),
            ])
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get();
    }

    // Lấy danh sách chuyển phòng đến đang chờ
    private function pendingIncomingTransferMovements(Contract $contract, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        if ((int) $contract->status !== Contract::STATUS_ACTIVE) {
            return collect();
        }

        return RoomMovement::query()
            ->with('tenant:id,full_name')
            ->where('to_room_id', $contract->room_id)
            ->where('source_contract_id', '!=', $contract->id)
            ->where('movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
            ->whereIn('status', [RoomMovement::STATUS_PENDING, RoomMovement::STATUS_BLOCKED])
            ->whereBetween('movement_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get();
    }

    // Lấy danh sách xe chuẩn bị chuyển đến phòng mới
    private function pendingIncomingVehicleRows(Contract $contract, Collection $incomingMovements): Collection
    {
        if ($incomingMovements->isEmpty()) {
            return collect();
        }

        $existingVehicleIds = $contract->contractVehicles
            ->filter(fn (ContractVehicle $contractVehicle): bool => (bool) $contractVehicle->is_active)
            ->pluck('vehicle_id')
            ->map(fn ($vehicleId): int => (int) $vehicleId)
            ->all();

        $movementsByTenantId = $incomingMovements->keyBy(fn (RoomMovement $movement): int => (int) $movement->tenant_id);

        return ContractVehicle::query()
            ->with('vehicle:id,tenant_id,license_plate')
            ->whereIn('contract_id', $incomingMovements->pluck('source_contract_id')->filter()->unique()->all())
            ->where('is_active', true)
            ->whereNotIn('vehicle_id', $existingVehicleIds)
            ->whereHas('vehicle', fn (Builder $query): Builder => $query->whereIn('tenant_id', $movementsByTenantId->keys()->all()))
            ->get()
            ->map(function (ContractVehicle $contractVehicle) use ($movementsByTenantId): ?array {
                $tenantId = (int) ($contractVehicle->vehicle?->tenant_id ?? 0);
                $movement = $movementsByTenantId->get($tenantId);

                if (! $movement) {
                    return null;
                }

                return [
                    'vehicle_id' => (int) $contractVehicle->vehicle_id,
                    'license_plate' => $contractVehicle->vehicle?->license_plate,
                    'monthly_fee' => DecimalMoney::normalize($contractVehicle->monthly_fee),
                    'movement_date' => Carbon::parse($movement->movement_date)->startOfDay(),
                    'transfer_code' => $movement->transfer_code,
                    'tenant_id' => $tenantId,
                    'tenant_name' => $movement->tenant?->full_name,
                ];
            })
            ->filter()
            ->values();
    }

    // Tổng hợp số liệu chốt tiền phòng chuyển đi
    private function transferCutoffSummaries(Contract $contract, Collection $groupedMovements, Collection $activeTenantIds): array
    {
        return $groupedMovements
            ->map(function (Collection $movements, string $transferCode) use ($contract, $activeTenantIds): array {
                $movingTenantIds = $movements->pluck('tenant_id')->map(fn ($tenantId): int => (int) $tenantId)->unique()->values();
                $movementDate = Carbon::parse($movements->first()->movement_date)->startOfDay();
                $closesSourceContract = $this->pendingTransferClosesSourceContract($contract, $activeTenantIds, $movingTenantIds);

                return [
                    'transfer_code' => $transferCode,
                    'direction' => 'outgoing',
                    'movement_date' => $movementDate->toDateString(),
                    'cutoff_date' => $movementDate->copy()->subDay()->toDateString(),
                    'vehicle_start_date' => null,
                    'moving_all_active_tenants' => $this->movingAllActiveTenants($activeTenantIds, $movingTenantIds),
                    'closes_source_contract' => $closesSourceContract,
                    'tenant_ids' => $movingTenantIds->all(),
                    'tenant_names' => $movements->map(fn (RoomMovement $movement): ?string => $movement->tenant?->full_name)->filter()->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    // Tổng hợp số liệu bắt đầu tiền phòng chuyển đến
    private function transferStartSummaries(Collection $incomingVehicleRows): array
    {
        return $incomingVehicleRows
            ->groupBy('transfer_code')
            ->map(function (Collection $vehicles, string $transferCode): array {
                $movingTenantIds = $vehicles->pluck('tenant_id')->map(fn ($tenantId): int => (int) $tenantId)->unique()->values();
                $movementDate = Carbon::parse($vehicles->first()['movement_date'])->startOfDay();

                return [
                    'transfer_code' => $transferCode,
                    'direction' => 'incoming',
                    'movement_date' => $movementDate->toDateString(),
                    'cutoff_date' => null,
                    'vehicle_start_date' => $movementDate->toDateString(),
                    'moving_all_active_tenants' => false,
                    'closes_source_contract' => false,
                    'tenant_ids' => $movingTenantIds->all(),
                    'tenant_names' => $vehicles->pluck('tenant_name')->filter()->unique()->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    // Kiểm tra chuyển phòng có làm đóng hợp đồng gốc không
    private function pendingTransferClosesSourceContract(?Contract $contract, Collection $activeTenantIds, Collection $movingTenantIds): bool
    {
        return $activeTenantIds->isNotEmpty() && $movingTenantIds->isNotEmpty();
    }

    // Kiểm tra có phải chuyển toàn bộ khách thuê hoạt động đi không
    private function movingAllActiveTenants(Collection $activeTenantIds, Collection $movingTenantIds): bool
    {
        return $activeTenantIds->diff($movingTenantIds)->isEmpty()
            && $movingTenantIds->diff($activeTenantIds)->isEmpty();
    }

    // Tính tiền xe chốt đến ngày chuyển phòng
    private function vehicleTransferCutoff(ContractVehicle $contractVehicle, Collection $tenantCutoffs, ?Carbon $contractCutoffDate = null): ?Carbon
    {
        $tenantId = $contractVehicle->vehicle?->tenant_id;

        return ($tenantId ? $tenantCutoffs->get($tenantId) : null) ?: $contractCutoffDate;
    }

    // Tính tiền xe lẻ ngày cho giao dịch chuyển phòng đang chờ
    private function calculatePendingVehicleProratedAmount(string $monthlyFee, Carbon $vehicleBillingStart, Carbon $periodStart, Carbon $periodEnd): string
    {
        $chargeStart = $vehicleBillingStart->copy()->startOfDay()->greaterThan($periodStart)
            ? $vehicleBillingStart->copy()->startOfDay()
            : $periodStart->copy();

        if ($chargeStart->greaterThan($periodEnd)) {
            return '0.00';
        }

        $totalDays = (int) $periodEnd->daysInMonth;
        $actualDays = ((int) $chargeStart->diffInDays($periodEnd)) + 1;

        return $actualDays >= $totalDays
            ? DecimalMoney::normalize($monthlyFee)
            : DecimalMoney::prorateByDays($monthlyFee, $actualDays, $totalDays);
    }

    // Tạo mô tả hóa đơn kèm thông tin chốt chuyển phòng đi
    private function descriptionWithTransferCutoff(string $description, ?Carbon $cutoffDate): string
    {
        return $cutoffDate
            ? $description.' (tính đến '.$cutoffDate->format('d/m/Y').' theo lịch chuyển phòng)'
            : $description;
    }

    // Tạo mô tả hóa đơn kèm thông tin chốt chuyển phòng còn lại
    private function descriptionWithRemainingTransferCutoff(string $description, ?Carbon $cutoffDate): string
    {
        return $cutoffDate
            ? $description.' (tính đến '.$cutoffDate->format('d/m/Y').' trước khi chuyển phòng)'
            : $description;
    }

    // Tạo mô tả hóa đơn kèm thông tin bắt đầu ở phòng mới
    private function descriptionWithTransferStart(string $description, Carbon $startDate): string
    {
        return $description.' (tính từ '.$startDate->format('d/m/Y').' theo lịch chuyển phòng)';
    }

    // Lấy bảng giá dịch vụ hiện tại của phòng
    private function currentServicePrices(int $buildingId, Carbon $periodEnd): Collection
    {
        return ServicePrice::query()
            ->select(['id', 'service_id', 'building_id', 'price', 'effective_from', 'effective_to', 'status'])
            ->with('service:id,name,slug,charge_method,unit_name,is_active')
            ->where('building_id', $buildingId)
            ->whereIn('status', [ServicePrice::STATUS_ACTIVE, ServicePrice::STATUS_EXPIRED])
            ->whereDate('effective_from', '<=', $periodEnd->toDateString())
            ->where(function (Builder $query) use ($periodEnd): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $periodEnd->toDateString());
            })
            ->whereHas('service', fn (Builder $query): Builder => $query->where('is_active', true))
            ->orderBy('service_id')
            ->orderByDesc('effective_from')
            ->get()
            ->unique('service_id')
            ->values();
    }

    // Lấy giá dịch vụ phòng áp dụng cho kỳ chỉ định
    private function roomServicePriceForPeriod(RoomService $roomService, Carbon $periodEnd, Contract $contract): ?string
    {
        $scheduledPrice = $roomService->relationLoaded('prices')
            ? $roomService->prices->first()
            : RoomServicePrice::query()
                ->where('room_service_id', $roomService->id)
                ->forContractOrDefault($contract->id)
                ->effectiveFor($periodEnd)
                ->priorityForContract($contract->id)
                ->first();

        return $scheduledPrice ? DecimalMoney::normalize($scheduledPrice->price) : null;
    }

    // Tổng tiền nợ kỳ trước của phòng
    private function previousDebtAmount(Contract $contract, int $billingYear, int $billingMonth): string
    {
        return $this->debtRolloverService->previousDebtAmount(
            $this->previousDebtRollovers($contract, $billingYear, $billingMonth)
        );
    }

    // Danh sách các khoản nợ chuyển tiếp từ kỳ trước
    private function previousDebtRollovers(Contract $contract, int $billingYear, int $billingMonth, ?int $targetInvoiceId = null, bool $lock = false): Collection
    {
        return $this->debtRolloverService->previousDebtRollovers($contract, $billingYear, $billingMonth, $targetInvoiceId, $lock);
    }

    // Tạo các khoản nợ chuyển tiếp sang kỳ này
    private function createDebtRollovers(Invoice $targetInvoice, Collection|array $rollovers): void
    {
        $this->debtRolloverService->syncTargetRollovers($targetInvoice, $rollovers);
    }

    // Từ chối thanh toán nếu hóa đơn gốc đã chuyển tiếp nợ
    private function rejectPaymentForRolledSourceInvoice(Invoice $invoice): ?JsonResponse
    {
        $rollover = $this->debtRolloverService->activeRolloverOut($invoice);
        if (! $rollover?->targetInvoice) {
            return null;
        }

        return ApiResponse::responseJson(
            false,
            'Khoản nợ đã chuyển sang hóa đơn '.$rollover->targetInvoice->invoice_code.', vui lòng thanh toán hóa đơn đó.',
            422,
            [
                'rolled_to_invoice_id' => $rollover->target_invoice_id,
                'rolled_to_invoice_code' => $rollover->targetInvoice->invoice_code,
            ],
            422
        );
    }

    // Phân bổ số tiền đã thanh toán cho các khoản nợ chuyển tiếp
    private function allocateConfirmedPaymentToDebtRollovers(Invoice $targetInvoice, Payment $payment): void
    {
        $this->debtRolloverService->allocateConfirmedPaymentToDebtRollovers($targetInvoice, $payment);
    }

    // Tạo các khoản mục điều chỉnh hóa đơn (phụ thu/giảm trừ)
    private function buildAdjustmentItems(array $adjustments): array
    {
        return collect($adjustments)
            ->map(function (array $adjustment): array {
                $quantity = DecimalMoney::normalize($adjustment['quantity'] ?? '1');
                $unitPrice = DecimalMoney::normalize($adjustment['unit_price']);
                $amount = DecimalMoney::multiply($quantity, $unitPrice);

                if (in_array((int) $adjustment['item_type'], [InvoiceItem::ITEM_TYPE_DISCOUNT, InvoiceItem::ITEM_TYPE_ADJUST_DECREASE], true)) {
                    $amount = '-'.DecimalMoney::normalize($amount);
                    $unitPrice = '-'.$unitPrice;
                }

                return [
                    'service_id' => null,
                    'meter_reading_id' => null,
                    'item_type' => (int) $adjustment['item_type'],
                    'description' => $adjustment['description'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                ];
            })
            ->values()
            ->all();
    }

    // Tính tổng tiền các khoản mục trong hóa đơn
    private function calculateItemsTotal(array $items): string
    {
        return DecimalMoney::add(array_map(fn (array $item): string => (string) ($item['amount'] ?? '0'), $items));
    }

    // Tạo thông báo lỗi vượt quá giới hạn giảm trừ
    private function decreaseAdjustmentLimitMessage(array|Collection $items): ?string
    {
        $itemCollection = collect($items);

        $invoiceAmount = DecimalMoney::add($itemCollection
            ->reject(fn (array|InvoiceItem $item): bool => in_array((int) data_get($item, 'item_type'), self::ADJUSTMENT_ITEM_TYPES, true))
            ->map(fn (array|InvoiceItem $item): string => (string) data_get($item, 'amount', '0'))
            ->values()
            ->all());

        $decreaseAmount = DecimalMoney::add($itemCollection
            ->filter(fn (array|InvoiceItem $item): bool => in_array((int) data_get($item, 'item_type'), self::DECREASE_ADJUSTMENT_ITEM_TYPES, true))
            ->map(fn (array|InvoiceItem $item): string => $this->absoluteMoney(data_get($item, 'amount', '0')))
            ->values()
            ->all());

        return DecimalMoney::compare($decreaseAmount, $invoiceAmount) > 0
            ? self::DECREASE_ADJUSTMENT_LIMIT_MESSAGE
            : null;
    }

    // Lấy giá trị tuyệt đối của số tiền
    private function absoluteMoney(mixed $value): string
    {
        $amount = DecimalMoney::normalize($value);

        return DecimalMoney::compare($amount, '0') < 0
            ? DecimalMoney::subtract('0', $amount)
            : $amount;
    }

    // Đánh dấu các số đọc điện nước đã được xuất hóa đơn
    private function markMeterReadingsInvoiced(Invoice $invoice): void
    {
        $readingIds = $invoice->items()
            ->whereNotNull('meter_reading_id')
            ->pluck('meter_reading_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($readingIds)) {
            MeterReading::query()->whereIn('id', $readingIds)->update(['status' => MeterReading::STATUS_INVOICED]);
        }
    }

    // Đảm bảo hóa đơn đủ điều kiện để phát hành lại
    private function ensureInvoiceCanBeReissued(Invoice $invoice, ?string $prefix = null): ?JsonResponse
    {
        $label = $prefix ? $prefix.' '.$invoice->invoice_code.': ' : '';

        if (! in_array((int) $invoice->status, [Invoice::STATUS_UNPAID, Invoice::STATUS_OVERDUE], true)) {
            return ApiResponse::responseJson(false, $label.'Chỉ được sửa hóa đơn chưa thanh toán hoặc quá hạn', 422, null, 422);
        }

        $paymentsCount = array_key_exists('payments_count', $invoice->getAttributes())
            ? (int) $invoice->payments_count
            : $invoice->payments()->realMoney()->count();

        if ($paymentsCount > 0) {
            return ApiResponse::responseJson(false, $label.'Không thể sửa hóa đơn đã phát sinh giao dịch hoặc minh chứng thanh toán', 422, null, 422);
        }

        return null;
    }

    // Áp dụng điều chỉnh số đọc điện nước khi phát hành lại hóa đơn
    private function applyMeterReadingCorrections(Invoice $invoice, array $corrections): Collection
    {
        $correctionsByReadingId = collect($corrections)
            ->keyBy(fn (array $correction): int => (int) $correction['meter_reading_id']);
        $readingIds = $correctionsByReadingId->keys()->map(fn ($readingId): int => (int) $readingId)->values();

        if ($readingIds->isEmpty()) {
            return collect([$invoice->id]);
        }

        $linkedReadingIds = $invoice->items()
            ->whereIn('meter_reading_id', $readingIds->all())
            ->pluck('meter_reading_id')
            ->map(fn ($readingId): int => (int) $readingId)
            ->unique()
            ->values();

        if ($linkedReadingIds->count() !== $readingIds->count()) {
            $this->failReissue('Chỉ được chỉnh sửa chỉ số đang thuộc hóa đơn này');
        }

        $correctedReadings = MeterReading::query()
            ->whereIn('id', $readingIds->all())
            ->lockForUpdate()
            ->get();

        if ($correctedReadings->count() !== $readingIds->count()) {
            $this->failReissue('Không tìm thấy đầy đủ chỉ số cần chỉnh sửa', 404);
        }

        $affectedInvoiceIds = collect([$invoice->id]);

        foreach ($correctedReadings->groupBy('meter_device_id') as $meterDeviceId => $readingsForDevice) {
            $earliestReading = $readingsForDevice
                ->sortBy(fn (MeterReading $reading): int => ((int) $reading->billing_year * 100) + (int) $reading->billing_month)
                ->first();

            if (! $earliestReading) {
                continue;
            }

            $deviceReadings = MeterReading::query()
                ->where('meter_device_id', (int) $meterDeviceId)
                ->where(function (Builder $query) use ($earliestReading): void {
                    $query->where('billing_year', '>', $earliestReading->billing_year)
                        ->orWhere(function (Builder $sameYearQuery) use ($earliestReading): void {
                            $sameYearQuery->where('billing_year', $earliestReading->billing_year)
                                ->where('billing_month', '>=', $earliestReading->billing_month);
                        });
                })
                ->orderBy('billing_year')
                ->orderBy('billing_month')
                ->lockForUpdate()
                ->get();

            $previousReading = DecimalMoney::normalize($earliestReading->previous_reading);

            foreach ($deviceReadings as $deviceReading) {
                $correction = $correctionsByReadingId->get((int) $deviceReading->id);
                $currentReading = DecimalMoney::normalize($correction['current_reading'] ?? $deviceReading->current_reading);

                if (DecimalMoney::compare($currentReading, $previousReading) < 0) {
                    $this->failReissue("Chỉ số mới của đồng hồ #{$deviceReading->meter_device_id} tháng {$deviceReading->billing_month}/{$deviceReading->billing_year} không được nhỏ hơn chỉ số trước đó {$previousReading}");
                }

                $readingData = [
                    'previous_reading' => $previousReading,
                    'current_reading' => $currentReading,
                    'consumption' => DecimalMoney::subtract($currentReading, $previousReading),
                ];

                if ($correction) {
                    if (! empty($correction['reading_date'])) {
                        $readingData['reading_date'] = $correction['reading_date'];
                    }

                    if (array_key_exists('note', $correction)) {
                        $readingData['note'] = $correction['note'];
                    }
                }

                $deviceReading->forceFill($readingData)->save();
                $affectedInvoiceIds = $affectedInvoiceIds->merge($this->syncInvoiceItemsForMeterReading($deviceReading));
                $previousReading = $currentReading;
            }

            $this->syncMeterDeviceInitialReading((int) $meterDeviceId);
        }

        return $affectedInvoiceIds->unique()->values();
    }

    // Đồng bộ các khoản mục hóa đơn theo số đọc điện nước mới
    private function syncInvoiceItemsForMeterReading(MeterReading $reading): Collection
    {
        $items = InvoiceItem::query()
            ->whereHas('invoice', function ($q) {
                $q->where('status', '!=', Invoice::STATUS_CANCELLED);
            })
            ->with('service:id,name')
            ->where('meter_reading_id', $reading->id)
            ->lockForUpdate()
            ->get();

        $invoiceIds = collect();
        foreach ($items as $item) {
            $item->forceFill([
                'description' => $this->meterReadingItemDescription($item, $reading),
                'quantity' => DecimalMoney::normalize($reading->consumption),
                'amount' => DecimalMoney::multiply($reading->consumption, $item->unit_price),
            ])->save();

            $invoiceIds->push((int) $item->invoice_id);
        }

        return $invoiceIds->unique()->values();
    }

    // Tạo mô tả khoản mục điện nước trên hóa đơn
    private function meterReadingItemDescription(InvoiceItem $item, MeterReading $reading): string
    {
        $name = $item->service?->name;
        if (! $name) {
            $name = trim(explode('(', $item->description, 2)[0]) ?: 'Chỉ số đồng hồ';
        }

        return $name.' ('.((float)$reading->previous_reading).' → '.((float)$reading->current_reading).')';
    }

    // Đồng bộ chỉ số đầu của công tơ điện nước
    private function syncMeterDeviceInitialReading(int $meterDeviceId): void
    {
        $latestReading = MeterReading::query()
            ->where('meter_device_id', $meterDeviceId)
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->lockForUpdate()
            ->first();

        if ($latestReading) {
            MeterDevice::query()->whereKey($meterDeviceId)->update(['initial_reading' => $latestReading->current_reading]);
        }
    }

    // Lấy danh sách ID hóa đơn nợ trong tương lai
    private function futureDebtInvoiceIds(Invoice $invoice): Collection
    {
        return Invoice::query()
            ->where('contract_id', $invoice->contract_id)
            ->where(function (Builder $query) use ($invoice): void {
                $query->where('billing_year', '>', $invoice->billing_year)
                    ->orWhere(function (Builder $sameYearQuery) use ($invoice): void {
                        $sameYearQuery->where('billing_year', $invoice->billing_year)
                            ->where('billing_month', '>', $invoice->billing_month);
                    });
            })
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->whereHas('items', fn (Builder $query): Builder => $query->where('item_type', InvoiceItem::ITEM_TYPE_OLD_DEBT))
            ->pluck('id')
            ->map(fn ($invoiceId): int => (int) $invoiceId)
            ->values();
    }

    // Đồng bộ khoản mục nợ cũ trên hóa đơn phát hành lại
    private function syncPreviousDebtItem(Invoice $invoice): void
    {
        $previousDebtRollovers = $this->previousDebtRollovers($invoice->contract, (int) $invoice->billing_year, (int) $invoice->billing_month, (int) $invoice->id, true);
        $previousDebtAmount = $this->debtRolloverService->previousDebtAmount($previousDebtRollovers);

        $oldDebtItems = $invoice->items()
            ->where('item_type', InvoiceItem::ITEM_TYPE_OLD_DEBT)
            ->lockForUpdate()
            ->orderBy('id')
            ->get();

        foreach ($oldDebtItems as $index => $item) {
            $amount = $index === 0 ? $previousDebtAmount : '0.00';
            $item->forceFill([
            'description' => 'Nợ cũ các kỳ trước',
            'quantity' => '1.00',
            'unit_price' => $amount,
            'amount' => $amount,
        ])->save();
        }

        if ($oldDebtItems->isEmpty() && DecimalMoney::isPositive($previousDebtAmount)) {
            $invoice->items()->create([
                'service_id' => null,
                'meter_reading_id' => null,
                'item_type' => InvoiceItem::ITEM_TYPE_OLD_DEBT,
                'description' => 'Nợ cũ các kỳ trước',
                'quantity' => '1.00',
                'unit_price' => $previousDebtAmount,
                'amount' => $previousDebtAmount,
            ]);
        }

        $this->createDebtRollovers($invoice, $previousDebtRollovers);
    }

    // Tính toán lại toàn bộ tiền hóa đơn khi phát hành lại
    private function recalculateReissuedInvoice(Invoice $invoice, Admin $admin, string $reason, ?string $dueDate = null, bool $applyDueDate = false): void
    {
        $items = $invoice->items()->lockForUpdate()->get();

        if ($message = $this->decreaseAdjustmentLimitMessage($items)) {
            $this->failReissue($message);
        }

        $totalAmount = $this->calculateItemsTotal($items->map(fn (InvoiceItem $item): array => [
            'amount' => (string) $item->amount,
        ])->all());

        if (DecimalMoney::compare($totalAmount, '0') < 0) {
            $this->failReissue("Tổng tiền hóa đơn {$invoice->invoice_code} không được âm");
        }

        $previousDebtAmount = DecimalMoney::add($items
            ->where('item_type', InvoiceItem::ITEM_TYPE_OLD_DEBT)
            ->map(fn (InvoiceItem $item): string => (string) $item->amount)
            ->values()
            ->all());

        $data = [
            'previous_debt_amount' => $previousDebtAmount,
            'total_amount' => $totalAmount,
            'paid_amount' => '0.00',
            'remaining_amount' => $totalAmount,
            'status' => $this->reissuedInvoiceStatus($totalAmount, $applyDueDate ? $dueDate : optional($invoice->due_date)->toDateString(), (int) $invoice->status),
            'revision' => max(1, (int) ($invoice->revision ?? 1)) + 1,
            'reissued_at' => now(),
            'reissue_reason' => $reason,
            'updated_by' => $admin->id,
        ];

        if ($applyDueDate) {
            $data['due_date'] = $dueDate;
        }

        $invoice->forceFill($data)->save();
    }

    // Xác định trạng thái của hóa đơn phát hành lại
    private function reissuedInvoiceStatus(string $totalAmount, ?string $dueDate, int $currentStatus): int
    {
        if (DecimalMoney::compare($totalAmount, '0') <= 0) {
            return Invoice::STATUS_PAID;
        }

        if ($dueDate && Carbon::parse($dueDate)->startOfDay()->lt(now()->startOfDay())) {
            return Invoice::STATUS_OVERDUE;
        }

        return Invoice::STATUS_UNPAID;
    }

    // Xử lý khi phát hành lại hóa đơn thất bại
    private function failReissue(string $message, int $status = 422): never
    {
        throw new \DomainException($message, $status);
    }

    // Ghi nhận thanh toán hóa đơn thành công vào hệ thống
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

    // Tạo thông báo hóa đơn đã được phát hành
    private function createInvoiceIssuedNotifications(Invoice $invoice, Admin $admin): Collection
    {
        $invoice->loadMissing(['room.building', 'contract.contractTenants.tenant']);
        $periodEnd = $invoice->period_end ? Carbon::parse($invoice->period_end)->endOfDay() : null;

        return $invoice->contract->contractTenants
            ->filter(fn ($contractTenant): bool => (bool) $contractTenant->is_staying)
            ->filter(function ($contractTenant) use ($periodEnd): bool {
                if ($periodEnd !== null) {
                    $joinDate = $contractTenant->join_date ? Carbon::parse($contractTenant->join_date)->startOfDay() : null;
                    $billingStart = $contractTenant->billing_start_date ? Carbon::parse($contractTenant->billing_start_date)->startOfDay() : null;

                    if ($joinDate && $joinDate->gt($periodEnd)) {
                        return false;
                    }
                    if ($billingStart && $billingStart->gt($periodEnd)) {
                        return false;
                    }
                }
                return true;
            })
            ->map(fn ($contractTenant): Notification => Notification::query()->create([
                'title' => 'Hóa đơn mới đã được phát hành',
                'content' => "Hóa đơn {$invoice->invoice_code} tháng ".str_pad((string) $invoice->billing_month, 2, '0', STR_PAD_LEFT)."/{$invoice->billing_year} của phòng ".($invoice->room?->room_number ?? 'chưa rõ').' đã được phát hành. Số tiền: '.number_format(DecimalMoney::toIntegerAmount($invoice->total_amount), 0, ',', '.').' VND.',
                'notification_type' => Notification::NOTIFICATION_TYPE_INVOICE,
                'target_type' => Notification::TARGET_TYPE_TENANT,
                'action_url' => '/admin/invoices?id=' . $invoice->id,
                'building_id' => $invoice->room?->building_id,
                'room_id' => $invoice->room_id,
                'tenant_id' => $contractTenant->tenant_id,
                'published_at' => now(),
                'status' => Notification::STATUS_SENT,
                'created_by' => $admin->id,
            ]))
            ->values();
    }

    // Tạo thông báo hóa đơn đã được phát hành lại
    private function createInvoiceReissuedNotifications(Invoice $invoice, Admin $admin, bool $isCascade = false): Collection
    {
        $invoice->loadMissing(['room.building', 'contract.contractTenants.tenant']);
        $amountText = number_format(DecimalMoney::toIntegerAmount($invoice->remaining_amount), 0, ',', '.').' VND';
        $title = $isCascade ? 'Hóa đơn liên quan đã được cập nhật' : 'Hóa đơn đã được cập nhật và phát hành lại';
        $contentPrefix = $isCascade
            ? "Hóa đơn {$invoice->invoice_code} được cập nhật do thay đổi chỉ số kỳ trước."
            : "Hóa đơn {$invoice->invoice_code} đã được ban quản lý cập nhật và phát hành lại.";
        $periodEnd = $invoice->period_end ? Carbon::parse($invoice->period_end)->endOfDay() : null;

        return $invoice->contract->contractTenants
            ->filter(fn ($contractTenant): bool => (bool) $contractTenant->is_staying)
            ->filter(function ($contractTenant) use ($periodEnd): bool {
                if ($periodEnd !== null) {
                    $joinDate = $contractTenant->join_date ? Carbon::parse($contractTenant->join_date)->startOfDay() : null;
                    $billingStart = $contractTenant->billing_start_date ? Carbon::parse($contractTenant->billing_start_date)->startOfDay() : null;

                    if ($joinDate && $joinDate->gt($periodEnd)) {
                        return false;
                    }
                    if ($billingStart && $billingStart->gt($periodEnd)) {
                        return false;
                    }
                }
                return true;
            })
            ->map(fn ($contractTenant): Notification => Notification::query()->create([
                'title' => $title,
                'content' => $contentPrefix.' Số tiền còn lại: '.$amountText.'. Mã QR thanh toán đã được cập nhật theo số tiền mới.',
                'notification_type' => Notification::NOTIFICATION_TYPE_INVOICE,
                'target_type' => Notification::TARGET_TYPE_TENANT,
                'action_url' => '/admin/invoices?id=' . $invoice->id,
                'building_id' => $invoice->room?->building_id,
                'room_id' => $invoice->room_id,
                'tenant_id' => $contractTenant->tenant_id,
                'published_at' => now(),
                'status' => Notification::STATUS_SENT,
                'created_by' => $admin->id,
            ]))
            ->values();
    }

    // Tạo thông báo hóa đơn đã thanh toán xong
    private function createInvoicePaidNotifications(Invoice $invoice, Payment $payment, ?Admin $admin = null): Collection
    {
        $invoice->loadMissing(['room.building', 'contract.contractTenants.tenant']);
        $amountText = number_format(DecimalMoney::toIntegerAmount($payment->amount), 0, ',', '.').' VND';
        $periodEnd = $invoice->period_end ? Carbon::parse($invoice->period_end)->endOfDay() : null;

        $tenantNotifications = $invoice->contract->contractTenants
            ->filter(fn ($contractTenant): bool => (bool) $contractTenant->is_staying)
            ->filter(function ($contractTenant) use ($periodEnd): bool {
                if ($periodEnd !== null) {
                    $joinDate = $contractTenant->join_date ? Carbon::parse($contractTenant->join_date)->startOfDay() : null;
                    $billingStart = $contractTenant->billing_start_date ? Carbon::parse($contractTenant->billing_start_date)->startOfDay() : null;

                    if ($joinDate && $joinDate->gt($periodEnd)) {
                        return false;
                    }
                    if ($billingStart && $billingStart->gt($periodEnd)) {
                        return false;
                    }
                }
                return true;
            })
            ->map(fn ($contractTenant): Notification => Notification::query()->create([
                'title' => 'Thanh toán hóa đơn thành công',
                'content' => "Hệ thống đã ghi nhận thanh toán {$amountText} cho hóa đơn {$invoice->invoice_code}. Trạng thái hiện tại: ".(Invoice::STATUS_LABELS[$invoice->status] ?? 'Đã cập nhật').'.',
                'notification_type' => Notification::NOTIFICATION_TYPE_INVOICE,
                'target_type' => Notification::TARGET_TYPE_TENANT,
                'action_url' => '/admin/invoices?id=' . $invoice->id,
                'building_id' => $invoice->room?->building_id,
                'room_id' => $invoice->room_id,
                'tenant_id' => $contractTenant->tenant_id,
                'published_at' => now(),
                'status' => Notification::STATUS_SENT,
                'created_by' => $admin?->id,
            ]));

        $adminNotification = Notification::query()->create([
            'title' => 'Hóa đơn đã được thanh toán',
            'content' => 'Phòng '.($invoice->room?->room_number ?? 'chưa rõ').' của tòa nhà '.($invoice->room?->building?->name ?? 'chưa rõ')." đã thanh toán hóa đơn {$invoice->invoice_code}. Số tiền ghi nhận: {$amountText}.",
            'notification_type' => Notification::NOTIFICATION_TYPE_INVOICE,
            'target_type' => Notification::TARGET_TYPE_ADMIN,
            'action_url' => '/admin/invoices?id=' . $invoice->id,
            'building_id' => $invoice->room?->building_id,
            'room_id' => $invoice->room_id,
            'tenant_id' => null,
            'published_at' => now(),
            'status' => Notification::STATUS_SENT,
            'created_by' => $admin?->id,
        ]);

        return $tenantNotifications->push($adminNotification)->values();
    }

    // Phát các thông báo qua realtime
    private function broadcastNotifications(Collection $notifications): void
    {
        $notifications->each(fn (Notification $notification): mixed => event(new NotificationSent($notification)));
    }

    // Xác định loại khoản mục điện hay nước
    private function meterItemType(MeterDevice $meterDevice, Service $service): int
    {
        if ((int) $meterDevice->meter_type === MeterDevice::METER_TYPE_ELECTRIC) {
            return InvoiceItem::ITEM_TYPE_ELECTRIC;
        }

        if ((int) $meterDevice->meter_type === MeterDevice::METER_TYPE_WATER) {
            return InvoiceItem::ITEM_TYPE_WATER;
        }

        return $this->serviceItemType($service);
    }

    // Xác định loại khoản mục dịch vụ
    private function serviceItemType(Service $service): int
    {
        $searchText = mb_strtolower(($service->slug ?? '').' '.($service->name ?? ''));

        if (str_contains($searchText, 'internet')) {
            return InvoiceItem::ITEM_TYPE_INTERNET;
        }

        if (str_contains($searchText, 'rac') || str_contains($searchText, 'rác') || str_contains($searchText, 'trash')) {
            return InvoiceItem::ITEM_TYPE_TRASH;
        }

        if ((int) $service->charge_method === Service::CHARGE_METHOD_BY_VEHICLE) {
            return InvoiceItem::ITEM_TYPE_PARKING;
        }

        return InvoiceItem::ITEM_TYPE_SURCHARGE;
    }

    // Tạo mã hóa đơn tự động
    private function makeInvoiceCode(Carbon $period): string
    {
        $prefix = 'INV-'.$period->format('Y-m').'-';
        $next = Invoice::query()
            ->where('invoice_code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->count() + 1;

        do {
            $code = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (Invoice::query()->where('invoice_code', $code)->exists());

        return $code;
    }

    // Tạo mã thanh toán hóa đơn
    private function makePaymentCode(): string
    {
        $prefix = 'PAY-'.now()->format('Y-m').'-';
        $next = Payment::query()
            ->where('payment_code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->count() + 1;

        do {
            $code = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (Payment::query()->where('payment_code', $code)->exists());

        return $code;
    }

    // Tạo tên khóa đồng bộ thanh toán hóa đơn (tránh trùng lặp)
    private function paymentLockName(string $reference): string
    {
        return 'invoice-payment:'.sha1($reference);
    }

    // Truy vấn hóa đơn trong phạm vi quản lý của admin
    private function accessibleInvoiceQuery(Admin $admin): Builder
    {
        $query = Invoice::query();

        if (AdminScope::isBuildingManager($admin)) {
            $query->whereHas('room.building', fn (Builder $buildingQuery): Builder => $buildingQuery->where('manager_admin_id', $admin->id));
        }

        if (! AdminScope::isSuperAdmin($admin) && ! AdminScope::isBuildingManager($admin)) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }



    // Kiểm tra admin có quyền quản lý tòa nhà chứa hóa đơn không
    private function canAccessBuilding(Admin $admin, int $buildingId): bool
    {
        return AdminScope::ensureBuildingAccess($admin, $buildingId);
    }

    // Các quan hệ liên kết hiển thị trong danh sách hóa đơn
    private function listRelations(): array
    {
        return [
            'room:id,building_id,room_number',
            'room.building:id,name,manager_admin_id',
            'contract:id,contract_code',
            'contract.contractTenants:id,contract_id,tenant_id,is_staying',
            'contract.contractTenants.tenant:id,full_name,phone,email',
            'creator:id,full_name',
            'updater:id,full_name',
            'debtRolloversOut.targetInvoice:id,invoice_code,status',
        ];
    }

    // Các quan hệ liên kết hiển thị trong chi tiết hóa đơn
    private function detailRelations(): array
    {
        return [
            'room:id,building_id,room_number,floor,status',
            'room.building:id,name,manager_admin_id',
            'contract:id,contract_code,room_id',
            'contract.contractTenants:id,contract_id,tenant_id,is_staying',
            'contract.contractTenants.tenant:id,full_name,phone,email',
            'creator:id,full_name',
            'updater:id,full_name',
            'items' => fn ($query) => $query->orderBy('id'),
            'items.service:id,name,slug,charge_method,unit_name',
            'items.meterReading:id,meter_device_id,previous_reading,current_reading,consumption,reading_date,image_path',
            'payments' => fn ($query) => $query->orderByDesc('payment_date')->orderByDesc('id'),
            'payments.collector:id,full_name',
            'debtRolloversOut.targetInvoice:id,invoice_code,status',
            'debtRolloversIn.sourceInvoice:id,invoice_code,billing_year,billing_month,total_amount,paid_amount,remaining_amount,status',
        ];
    }

    // Tạo truy vấn danh sách hóa đơn
    private function queryInvoices(array $validated, Admin $admin): Builder
    {
        return $this->accessibleInvoiceQuery($admin)
            ->with($this->listRelations())
            ->withCount(['items', 'payments'])
            ->when(isset($validated['status']), fn (Builder $query): Builder => $query->where('status', $validated['status']))
            ->when(isset($validated['building_id']), fn (Builder $query): Builder => $query->whereHas('room', fn (Builder $roomQuery): Builder => $roomQuery->where('building_id', $validated['building_id'])))
            ->when(isset($validated['room_id']), fn (Builder $query): Builder => $query->where('room_id', $validated['room_id']))
            ->when(isset($validated['contract_id']), fn (Builder $query): Builder => $query->where('contract_id', $validated['contract_id']))
            ->when(isset($validated['billing_month']), fn (Builder $query): Builder => $query->where('billing_month', $validated['billing_month']))
            ->when(isset($validated['billing_year']), fn (Builder $query): Builder => $query->where('billing_year', $validated['billing_year']))
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->orderByDesc('id');
    }

    // Tìm kiếm hóa đơn theo từ khóa
    private function searchInvoices(string $keyword, array $validated, Admin $admin): LengthAwarePaginator
    {
        $builder = Invoice::search($keyword);

        foreach (['status', 'room_id', 'contract_id', 'billing_month', 'billing_year'] as $field) {
            if (isset($validated[$field])) {
                $builder->where($field, (int) $validated[$field]);
            }
        }

        if (isset($validated['building_id'])) {
            $builder->where('building_id', (int) $validated['building_id']);
        }

        return $builder
            ->orderBy('billing_year', 'desc')
            ->orderBy('billing_month', 'desc')
            ->orderBy('id', 'desc')
            ->query(fn ($query) => $this->accessibleInvoiceQuery($admin)
                ->with($this->listRelations())
                ->withCount(['items', 'payments']))
            ->paginate($validated['per_page'] ?? 10);
    }
}
