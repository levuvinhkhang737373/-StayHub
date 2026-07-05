<?php

namespace App\Http\Controllers\Tenant;

use App\Helpers\ApiResponse;
use App\Helpers\DecimalMoney;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\InvoiceDetailResource;
use App\Http\Resources\Tenant\InvoiceResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoiceDebtRolloverService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceDebtRolloverService $debtRolloverService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|integer|in:2,3,4,5',
            'billing_month' => 'nullable|integer|min:1|max:12',
            'billing_year' => 'nullable|integer|min:2020|max:2100',
        ], $this->validationMessages());

        try {
            $tenant = $request->user('tenant');

            if (! $tenant) {
                return ApiResponse::responseJson(false, 'Khách thuê chưa đăng nhập', 401, null, 401);
            }

            $invoices = $this->tenantInvoiceQuery((int) $tenant->id)
                ->with(['room:id,building_id,room_number', 'room.building:id,name', 'contract:id,contract_code', 'debtRolloversOut.targetInvoice:id,invoice_code,status'])
                ->when(isset($validated['status']), fn (Builder $query): Builder => $query->where('status', $validated['status']))
                ->when(isset($validated['billing_month']), fn (Builder $query): Builder => $query->where('billing_month', $validated['billing_month']))
                ->when(isset($validated['billing_year']), fn (Builder $query): Builder => $query->where('billing_year', $validated['billing_year']))
                ->orderByDesc('billing_year')
                ->orderByDesc('billing_month')
                ->orderByDesc('id')
                ->paginate($validated['per_page'] ?? 10);

            return ApiResponse::responseJson(true, 'Danh sách hóa đơn của bạn', 200, [
                'data' => InvoiceResource::collection($invoices->items())->resolve(),
                'pagination' => [
                    'current_page' => $invoices->currentPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                    'last_page' => $invoices->lastPage(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    public function show(Request $request, int $invoice): JsonResponse
    {
        try {
            $tenant = $request->user('tenant');

            if (! $tenant) {
                return ApiResponse::responseJson(false, 'Khách thuê chưa đăng nhập', 401, null, 401);
            }

            $invoiceModel = $this->tenantInvoiceQuery((int) $tenant->id)
                ->with($this->detailRelations())
                ->find($invoice);

            if (! $invoiceModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy hóa đơn của bạn', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết hóa đơn', 200, new InvoiceDetailResource($invoiceModel), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    public function uploadProof(Request $request, int $invoice): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'transaction_reference' => 'nullable|string|max:150',
            'note' => 'nullable|string|max:500',
            'proof_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ], $this->validationMessages());

        try {
            $tenant = $request->user('tenant');

            if (! $tenant) {
                return ApiResponse::responseJson(false, 'Khách thuê chưa đăng nhập', 401, null, 401);
            }

            $lockName = 'tenant-invoice-proof:' . sha1((string) ($validated['transaction_reference'] ?? $tenant->id . '-' . $invoice));
            $lock = Cache::lock($lockName, 10);

            if (! $lock->get()) {
                return ApiResponse::responseJson(false, 'Minh chứng đang được xử lý, vui lòng thử lại sau', 409, null, 409);
            }

            try {
                $response = DB::transaction(function () use ($validated, $invoice, $tenant, $request): JsonResponse {
                    $invoiceModel = $this->tenantInvoiceQuery((int) $tenant->id)
                        ->with($this->detailRelations())
                        ->lockForUpdate()
                        ->find($invoice);

                    if (! $invoiceModel) {
                        return ApiResponse::responseJson(false, 'Không tìm thấy hóa đơn của bạn', 404, null, 404);
                    }

                    $rollover = $this->debtRolloverService->activeRolloverOut($invoiceModel);
                    if ($rollover?->targetInvoice) {
                        return ApiResponse::responseJson(false, 'Khoản nợ đã chuyển sang hóa đơn '.$rollover->targetInvoice->invoice_code.', vui lòng thanh toán hóa đơn đó.', 422, [
                            'rolled_to_invoice_id' => $rollover->target_invoice_id,
                            'rolled_to_invoice_code' => $rollover->targetInvoice->invoice_code,
                        ], 422);
                    }

                    if (! in_array((int) $invoiceModel->status, [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_OVERDUE], true)) {
                        return ApiResponse::responseJson(false, 'Hóa đơn không ở trạng thái có thể gửi minh chứng', 422, null, 422);
                    }

                    if (! DecimalMoney::isPositive($validated['amount'])) {
                        return ApiResponse::responseJson(false, 'Số tiền thanh toán phải lớn hơn 0', 422, null, 422);
                    }

                    if (DecimalMoney::toIntegerAmount($validated['amount']) > DecimalMoney::toIntegerAmount($invoiceModel->remaining_amount)) {
                        return ApiResponse::responseJson(false, 'Số tiền gửi minh chứng không được vượt quá số tiền còn lại', 422, null, 422);
                    }

                    if (! empty($validated['transaction_reference']) && Payment::query()->where('transaction_reference', $validated['transaction_reference'])->exists()) {
                        return ApiResponse::responseJson(false, 'Mã tham chiếu giao dịch đã tồn tại', 422, null, 422);
                    }

                    Payment::query()->create([
                        'payment_code' => $this->makePaymentCode(),
                        'invoice_id' => $invoiceModel->id,
                        'amount' => DecimalMoney::normalize($validated['amount']),
                        'payment_date' => now(),
                        'payment_method' => Payment::PAYMENT_METHOD_BANK_TRANSFER,
                        'transaction_reference' => $validated['transaction_reference'] ?? null,
                        'status' => Payment::STATUS_PENDING_CONFIRMATION,
                        'proof_image' => ImageHelper::create($request->file('proof_image'), 'payments'),
                        'note' => $validated['note'] ?? 'Khách thuê gửi minh chứng thanh toán',
                        'collected_by' => null,
                    ]);

                    $invoiceModel->load($this->detailRelations());

                    return ApiResponse::responseJson(true, 'Gửi minh chứng thanh toán thành công, vui lòng chờ admin xác nhận', 201, new InvoiceDetailResource($invoiceModel), 201);
                });
            } finally {
                optional($lock)->release();
            }

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    private function tenantInvoiceQuery(int $tenantId): Builder
    {
        return Invoice::query()
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_PAID, Invoice::STATUS_OVERDUE])
            ->whereHas('contract.contractTenants', function (Builder $query) use ($tenantId): void {
                $query->where('tenant_id', $tenantId);
            });
    }

    private function detailRelations(): array
    {
        return [
            'room:id,building_id,room_number',
            'room.building:id,name',
            'contract:id,contract_code,room_id',
            'items' => fn ($query) => $query->orderBy('id'),
            'items.service:id,name,slug,charge_method,unit_name',
            'items.meterReading:id,meter_device_id,previous_reading,current_reading,consumption,reading_date,image_path',
            'payments' => fn ($query) => $query->orderByDesc('payment_date')->orderByDesc('id'),
            'debtRolloversOut.targetInvoice:id,invoice_code,status',
        ];
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

    private function validationMessages(): array
    {
        return [
            'required' => ':attribute là bắt buộc.',
            'integer' => ':attribute phải là số nguyên.',
            'date' => ':attribute không đúng định dạng ngày.',
            'in' => ':attribute không hợp lệ.',
            'max' => ':attribute vượt quá giới hạn cho phép.',
            'image' => ':attribute phải là hình ảnh.',
            'mimes' => ':attribute chỉ hỗ trợ jpeg, png, jpg hoặc webp.',
            'regex' => ':attribute phải là số tiền hợp lệ và tối đa 2 chữ số thập phân.',
            'amount.required' => 'Vui lòng nhập số tiền đã chuyển khoản.',
            'proof_image.required' => 'Vui lòng tải lên ảnh minh chứng thanh toán.',
        ];
    }
}
