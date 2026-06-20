<?php

namespace App\Http\Controllers\Admin;

use App\Events\InvoicePaid;
use App\Events\InvoiceIssued;
use App\Events\NotificationSent;
use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\DecimalMoney;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\InvoiceDetailResource;
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
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MeterDevice;
use App\Models\MeterReading;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServicePrice;
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
    private const ADJUSTMENT_ITEM_TYPES = [
        InvoiceItem::ITEM_TYPE_SURCHARGE,
        InvoiceItem::ITEM_TYPE_DISCOUNT,
        InvoiceItem::ITEM_TYPE_ADJUST_INCREASE,
        InvoiceItem::ITEM_TYPE_ADJUST_DECREASE,
    ];

    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            $keyword = trim($validated['keyword'] ?? '');

            $invoices = $keyword !== ''
                ? $this->searchInvoices($keyword, $validated, $admin)
                : $this->queryInvoices($validated, $admin)->paginate($validated['per_page'] ?? 10);

            return ApiResponse::responseJson(true, 'Danh sách hóa đơn', 200, [
                'data' => InvoiceResource::collection($invoices->items())->resolve(),
                'pagination' => [
                    'current_page' => $invoices->currentPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                    'last_page' => $invoices->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

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
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function generate(GenerateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            $response = DB::transaction(function () use ($validated, $admin, $request): JsonResponse {
                $contract = Contract::query()
                    ->with(['room.building', 'contractTenants.tenant', 'contractVehicles.vehicle'])
                    ->lockForUpdate()
                    ->find($validated['contract_id']);

                if (! $contract) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy hợp đồng', 404, null, 404);
                }

                if (! $contract->room || ! $this->canAccessBuilding($admin, (int) $contract->room->building_id)) {
                    return ApiResponse::responseJson(false, 'Bạn không có quyền lập hóa đơn cho phòng này', 403, null, 403);
                }

                if (! in_array((int) $contract->status, [Contract::STATUS_ACTIVE, Contract::STATUS_EXPIRED], true)) {
                    return ApiResponse::responseJson(false, 'Chỉ lập hóa đơn cho hợp đồng đang hiệu lực hoặc vừa hết hạn', 422, null, 422);
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

                $existingInvoice = Invoice::query()
                    ->where('contract_id', $contract->id)
                    ->where('billing_year', $periodStart->year)
                    ->where('billing_month', $periodStart->month)
                    ->lockForUpdate()
                    ->first();

                if ($existingInvoice) {
                    return ApiResponse::responseJson(false, 'Hợp đồng này đã có hóa đơn trong kỳ đã chọn', 422, null, 422);
                }

                $automaticItems = $this->buildAutomaticItems($contract, $periodStart, $periodEnd);
                if (! empty($automaticItems['errors'])) {
                    return ApiResponse::responseJson(false, implode(' ', $automaticItems['errors']), 422, null, 422);
                }

                $items = array_merge($automaticItems['items'], $this->buildAdjustmentItems($validated['adjustments'] ?? []));
                $totalAmount = $this->calculateItemsTotal($items);

                if (DecimalMoney::compare($totalAmount, '0') < 0) {
                    return ApiResponse::responseJson(false, 'Tổng tiền hóa đơn không được âm', 422, null, 422);
                }

                $invoice = Invoice::query()->create([
                    'invoice_code' => $this->makeInvoiceCode($periodStart),
                    'contract_id' => $contract->id,
                    'room_id' => $contract->room_id,
                    'billing_month' => $periodStart->month,
                    'billing_year' => $periodStart->year,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'previous_debt_amount' => $automaticItems['previous_debt_amount'],
                    'total_amount' => $totalAmount,
                    'paid_amount' => '0.00',
                    'remaining_amount' => $totalAmount,
                    'due_date' => $dueDate->toDateString(),
                    'status' => DecimalMoney::compare($totalAmount, '0') <= 0 ? Invoice::STATUS_PAID : Invoice::STATUS_UNPAID,
                    'issued_at' => now(),
                    'created_by' => $admin->id,
                ]);

                $invoice->items()->createMany($items);

                $this->markMeterReadingsInvoiced($invoice);
                $tenantNotifications = $this->createInvoiceIssuedNotifications($invoice, $admin);

                AdminActivityLogger::write($admin, 'generate_and_issue_invoice', Invoice::class, $invoice->id, null, $invoice->toArray(), $request);

                DB::afterCommit(function () use ($invoice, $tenantNotifications): void {
                    event(new InvoiceIssued($invoice->fresh($this->detailRelations())));
                    $this->broadcastNotifications($tenantNotifications);
                });

                $invoice->load($this->detailRelations());

                return ApiResponse::responseJson(true, 'Lập và phát hành hóa đơn thành công', 201, new InvoiceDetailResource($invoice), 201);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function update(UpdateRequest $request, int $invoice): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            $response = DB::transaction(function () use ($validated, $invoice, $admin, $request): JsonResponse {
                $invoiceModel = $this->accessibleInvoiceQuery($admin)
                    ->with('items')
                    ->withCount('payments')
                    ->lockForUpdate()
                    ->find($invoice);

                if (! $invoiceModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy hóa đơn', 404, null, 404);
                }

                if (! in_array((int) $invoiceModel->status, [Invoice::STATUS_UNPAID, Invoice::STATUS_OVERDUE], true)) {
                    return ApiResponse::responseJson(false, 'Chỉ được sửa hóa đơn chưa thanh toán hoặc quá hạn', 422, null, 422);
                }

                if ((int) $invoiceModel->payments_count > 0) {
                    return ApiResponse::responseJson(false, 'Không thể sửa hóa đơn đã phát sinh giao dịch thanh toán', 422, null, 422);
                }

                $oldData = $invoiceModel->load($this->detailRelations())->toArray();

                if (array_key_exists('due_date', $validated)) {
                    $invoiceModel->due_date = $validated['due_date'] ?? null;
                }

                $invoiceModel->items()
                    ->whereIn('item_type', self::ADJUSTMENT_ITEM_TYPES)
                    ->whereNull('service_id')
                    ->whereNull('meter_reading_id')
                    ->delete();

                if (! empty($validated['adjustments'])) {
                    $invoiceModel->items()->createMany($this->buildAdjustmentItems($validated['adjustments']));
                }

                $totalAmount = $this->calculateItemsTotal($invoiceModel->items()->get()->map(fn (InvoiceItem $item): array => [
                    'amount' => (string) $item->amount,
                ])->all());

                if (DecimalMoney::compare($totalAmount, '0') < 0) {
                    return ApiResponse::responseJson(false, 'Tổng tiền hóa đơn không được âm', 422, null, 422);
                }

                $invoiceModel->forceFill([
                    'total_amount' => $totalAmount,
                    'paid_amount' => '0.00',
                    'remaining_amount' => $totalAmount,
                    'previous_debt_amount' => (string) $invoiceModel->items()->where('item_type', InvoiceItem::ITEM_TYPE_OLD_DEBT)->sum('amount'),
                    'status' => DecimalMoney::compare($totalAmount, '0') <= 0 ? Invoice::STATUS_PAID : Invoice::STATUS_UNPAID,
                ])->save();

                AdminActivityLogger::write($admin, 'update_invoice', Invoice::class, $invoiceModel->id, $oldData, $invoiceModel->fresh()->toArray(), $request);

                $invoiceModel->load($this->detailRelations());

                return ApiResponse::responseJson(true, 'Cập nhật hóa đơn thành công', 200, new InvoiceDetailResource($invoiceModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

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
                        'payment_date' => isset($validated['payment_date']) ? Carbon::parse($validated['payment_date']) : now(),
                        'payment_method' => $validated['payment_method'],
                        'transaction_reference' => $validated['transaction_reference'] ?? null,
                        'status' => Payment::STATUS_CONFIRMED,
                        'proof_image' => $request->file('proof_image') ? ImageHelper::create($request->file('proof_image'), 'payments') : null,
                        'note' => $validated['note'] ?? null,
                        'collected_by' => $admin->id,
                    ]);

                    $this->applyConfirmedPayment($invoiceModel, (string) $payment->amount);
                    $notifications = $this->createInvoicePaidNotifications($invoiceModel->fresh($this->detailRelations()), $payment, $admin);

                    AdminActivityLogger::write($admin, 'record_invoice_payment', Payment::class, $payment->id, $oldData, $invoiceModel->fresh()->toArray(), $request);

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
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

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
                $notifications = $this->createInvoicePaidNotifications($invoiceModel->fresh($this->detailRelations()), $paymentModel, $admin);

                AdminActivityLogger::write($admin, 'confirm_invoice_payment', Payment::class, $paymentModel->id, $oldData, $invoiceModel->fresh()->toArray(), $request);

                DB::afterCommit(function () use ($invoiceModel, $notifications): void {
                    event(new InvoicePaid($invoiceModel->fresh($this->detailRelations())));
                    $this->broadcastNotifications($notifications);
                });

                $invoiceModel->load($this->detailRelations());

                return ApiResponse::responseJson(true, 'Xác nhận thanh toán thành công', 200, new InvoiceDetailResource($invoiceModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function cancel(CancelRequest $request, int $invoice): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            $response = DB::transaction(function () use ($validated, $invoice, $admin, $request): JsonResponse {
                $invoiceModel = $this->accessibleInvoiceQuery($admin)
                    ->withCount(['payments' => fn (Builder $query): Builder => $query->where('status', Payment::STATUS_CONFIRMED)])
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

                $oldData = $invoiceModel->toArray();
                $note = trim($validated['note'] ?? '');
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

                AdminActivityLogger::write($admin, 'cancel_invoice', Invoice::class, $invoiceModel->id, $oldData, $invoiceModel->fresh()->toArray(), $request);

                $invoiceModel->load($this->detailRelations());

                return ApiResponse::responseJson(true, 'Hủy hóa đơn thành công', 200, new InvoiceDetailResource($invoiceModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    private function buildAutomaticItems(Contract $contract, Carbon $periodStart, Carbon $periodEnd): array
    {
        $items = [];
        $errors = [];
        $buildingId = (int) $contract->room->building_id;
        $billingMonth = (int) $periodStart->month;
        $billingYear = (int) $periodStart->year;

        $roomAmount = $this->calculateRoomAmount($contract, $periodStart, $periodEnd);
        $items[] = [
            'service_id' => null,
            'meter_reading_id' => null,
            'item_type' => InvoiceItem::ITEM_TYPE_ROOM,
            'description' => 'Tiền phòng tháng '.str_pad((string) $billingMonth, 2, '0', STR_PAD_LEFT).'/'.$billingYear,
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

            $unitPrice = DecimalMoney::normalize($price->price);

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
                    'description' => $service->name.' ('.$reading->previous_reading.' → '.$reading->current_reading.')',
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
                $proratedAmount = $this->calculateProratedAmount($fullAmount, $contract, $periodStart, $periodEnd);

                $items[] = [
                    'service_id' => $service->id,
                    'meter_reading_id' => null,
                    'item_type' => $this->serviceItemType($service),
                    'description' => $service->name.' ('.$tenantCount.' người)',
                    'quantity' => DecimalMoney::normalize((string) $tenantCount),
                    'unit_price' => $unitPrice,
                    'amount' => $proratedAmount,
                ];

                continue;
            }

            if (in_array((int) $service->charge_method, [Service::CHARGE_METHOD_BY_ROOM, Service::CHARGE_METHOD_FIXED], true)) {
                $proratedAmount = $this->calculateProratedAmount($unitPrice, $contract, $periodStart, $periodEnd);

                $items[] = [
                    'service_id' => $service->id,
                    'meter_reading_id' => null,
                    'item_type' => $this->serviceItemType($service),
                    'description' => $service->name,
                    'quantity' => '1.00',
                    'unit_price' => $unitPrice,
                    'amount' => $proratedAmount,
                ];
            }
        }

        $vehicleServiceId = $prices
            ->first(fn (ServicePrice $price): bool => (int) $price->service?->charge_method === Service::CHARGE_METHOD_BY_VEHICLE)
            ?->service_id;

        foreach ($contract->contractVehicles->filter(fn ($contractVehicle): bool => (bool) $contractVehicle->is_active) as $contractVehicle) {
            if (DecimalMoney::compare($contractVehicle->monthly_fee, '0') <= 0) {
                continue;
            }

            $proratedAmount = $this->calculateProratedAmount($contractVehicle->monthly_fee, $contract, $periodStart, $periodEnd);

            $items[] = [
                'service_id' => $vehicleServiceId,
                'meter_reading_id' => null,
                'item_type' => InvoiceItem::ITEM_TYPE_PARKING,
                'description' => 'Gửi xe '.($contractVehicle->vehicle?->license_plate ?? ('#'.$contractVehicle->vehicle_id)),
                'quantity' => '1.00',
                'unit_price' => DecimalMoney::normalize($contractVehicle->monthly_fee),
                'amount' => $proratedAmount,
            ];
        }

        $previousDebtAmount = $this->previousDebtAmount($contract, $billingYear, $billingMonth);
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
        ];
    }

    private function calculateProratedAmount(string $amount, Contract $contract, Carbon $periodStart, Carbon $periodEnd): string
    {
        $chargeStart = $contract->start_date && $contract->start_date->copy()->startOfDay()->greaterThan($periodStart)
            ? $contract->start_date->copy()->startOfDay()
            : $periodStart->copy();

        $contractEndDate = $contract->actual_end_date ?: $contract->end_date;
        $chargeEnd = $contractEndDate && $contractEndDate->copy()->startOfDay()->lessThan($periodEnd)
            ? $contractEndDate->copy()->startOfDay()
            : $periodEnd->copy();

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

    private function calculateRoomAmount(Contract $contract, Carbon $periodStart, Carbon $periodEnd): string
    {
        return $this->calculateProratedAmount($contract->room_price, $contract, $periodStart, $periodEnd);
    }

    private function currentServicePrices(int $buildingId, Carbon $periodEnd): Collection
    {
        return ServicePrice::query()
            ->select(['id', 'service_id', 'building_id', 'price', 'effective_from', 'effective_to', 'status'])
            ->with('service:id,name,slug,charge_method,unit_name,is_active')
            ->where('building_id', $buildingId)
            ->where('status', ServicePrice::STATUS_ACTIVE)
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

    private function previousDebtAmount(Contract $contract, int $billingYear, int $billingMonth): string
    {
        $amounts = Invoice::query()
            ->where('contract_id', $contract->id)
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_OVERDUE])
            ->where(function (Builder $query) use ($billingYear, $billingMonth): void {
                $query->where('billing_year', '<', $billingYear)
                    ->orWhere(function (Builder $sameYearQuery) use ($billingYear, $billingMonth): void {
                        $sameYearQuery->where('billing_year', $billingYear)
                            ->where('billing_month', '<', $billingMonth);
                    });
            })
            ->pluck('remaining_amount')
            ->all();

        return DecimalMoney::add($amounts);
    }

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

    private function calculateItemsTotal(array $items): string
    {
        return DecimalMoney::add(array_map(fn (array $item): string => (string) ($item['amount'] ?? '0'), $items));
    }

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

    private function createInvoiceIssuedNotifications(Invoice $invoice, Admin $admin): Collection
    {
        $invoice->loadMissing(['room.building', 'contract.contractTenants.tenant']);

        return $invoice->contract->contractTenants
            ->filter(fn ($contractTenant): bool => (bool) $contractTenant->is_staying)
            ->map(fn ($contractTenant): Notification => Notification::query()->create([
                'title' => 'Hóa đơn mới đã được phát hành',
                'content' => "Hóa đơn {$invoice->invoice_code} tháng ".str_pad((string) $invoice->billing_month, 2, '0', STR_PAD_LEFT)."/{$invoice->billing_year} của phòng ".($invoice->room?->room_number ?? 'chưa rõ').' đã được phát hành. Số tiền: '.number_format(DecimalMoney::toIntegerAmount($invoice->total_amount), 0, ',', '.').' VND.',
                'notification_type' => Notification::NOTIFICATION_TYPE_INVOICE,
                'target_type' => Notification::TARGET_TYPE_TENANT,
                'building_id' => $invoice->room?->building_id,
                'room_id' => $invoice->room_id,
                'tenant_id' => $contractTenant->tenant_id,
                'published_at' => now(),
                'status' => Notification::STATUS_SENT,
                'created_by' => $admin->id,
            ]))
            ->values();
    }

    private function createInvoicePaidNotifications(Invoice $invoice, Payment $payment, ?Admin $admin = null): Collection
    {
        $invoice->loadMissing(['room.building', 'contract.contractTenants.tenant']);
        $amountText = number_format(DecimalMoney::toIntegerAmount($payment->amount), 0, ',', '.').' VND';
        $tenantNotifications = $invoice->contract->contractTenants
            ->filter(fn ($contractTenant): bool => (bool) $contractTenant->is_staying)
            ->map(fn ($contractTenant): Notification => Notification::query()->create([
                'title' => 'Thanh toán hóa đơn thành công',
                'content' => "Hệ thống đã ghi nhận thanh toán {$amountText} cho hóa đơn {$invoice->invoice_code}. Trạng thái hiện tại: ".(Invoice::STATUS_LABELS[$invoice->status] ?? 'Đã cập nhật').'.',
                'notification_type' => Notification::NOTIFICATION_TYPE_INVOICE,
                'target_type' => Notification::TARGET_TYPE_TENANT,
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
            'building_id' => $invoice->room?->building_id,
            'room_id' => $invoice->room_id,
            'tenant_id' => null,
            'published_at' => now(),
            'status' => Notification::STATUS_SENT,
            'created_by' => $admin?->id,
        ]);

        return $tenantNotifications->push($adminNotification)->values();
    }

    private function broadcastNotifications(Collection $notifications): void
    {
        $notifications->each(fn (Notification $notification): mixed => event(new NotificationSent($notification)));
    }

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

    private function paymentLockName(string $reference): string
    {
        return 'invoice-payment:'.sha1($reference);
    }

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



    private function canAccessBuilding(Admin $admin, int $buildingId): bool
    {
        return AdminScope::ensureBuildingAccess($admin, $buildingId);
    }

    private function listRelations(): array
    {
        return [
            'room:id,building_id,room_number',
            'room.building:id,name,manager_admin_id',
            'contract:id,contract_code',
            'contract.contractTenants:id,contract_id,tenant_id,is_staying',
            'contract.contractTenants.tenant:id,full_name,phone,email',
            'creator:id,full_name',
        ];
    }

    private function detailRelations(): array
    {
        return [
            'room:id,building_id,room_number,floor,status',
            'room.building:id,name,manager_admin_id',
            'contract:id,contract_code,room_id',
            'contract.contractTenants:id,contract_id,tenant_id,is_staying',
            'contract.contractTenants.tenant:id,full_name,phone,email',
            'creator:id,full_name',
            'items' => fn ($query) => $query->orderBy('id'),
            'items.service:id,name,slug,charge_method,unit_name',
            'items.meterReading:id,meter_device_id,previous_reading,current_reading,consumption,reading_date',
            'payments' => fn ($query) => $query->orderByDesc('payment_date')->orderByDesc('id'),
            'payments.collector:id,full_name',
        ];
    }

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
