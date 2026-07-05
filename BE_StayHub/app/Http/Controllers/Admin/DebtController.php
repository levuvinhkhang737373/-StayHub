<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\DecimalMoney;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Debt\IndexRequest;
use App\Http\Resources\Admin\DebtResource;
use App\Models\Admin;
use App\Models\Invoice;
use App\Models\InvoiceDebtRollover;
use App\Services\InvoiceDebtRolloverService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class DebtController extends Controller
{
    public function __construct(private readonly InvoiceDebtRolloverService $debtRolloverService) {}

    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');
            $rows = $this->debtRows($validated, $admin);
            $filteredRows = $this->filterRowsByDebtStatus($rows, $validated['debt_status'] ?? 'all');
            $page = (int) ($validated['page'] ?? 1);
            $perPage = (int) ($validated['per_page'] ?? 10);
            $pageRows = $filteredRows->forPage($page, $perPage)->values();

            return ApiResponse::responseJson(true, 'Danh sách công nợ', 200, [
                'data' => DebtResource::collection($pageRows)->resolve(),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $filteredRows->count(),
                    'last_page' => (int) max(1, ceil($filteredRows->count() / $perPage)),
                ],
                'stats' => $this->summary($filteredRows),
            ], 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    private function debtRows(array $validated, Admin $admin): Collection
    {
        return $this->accessibleInvoiceQuery($admin)
            ->with([
                'room:id,building_id,room_number,floor',
                'room.building:id,name,manager_admin_id',
                'contract:id,contract_code,room_id',
                'contract.contractTenants:id,contract_id,tenant_id,is_staying',
                'contract.contractTenants.tenant:id,full_name,phone,email',
                'debtRolloversOut.targetInvoice:id,invoice_code,status,billing_year,billing_month',
                'debtRolloversIn.sourceInvoice:id,invoice_code,billing_year,billing_month,total_amount,paid_amount,remaining_amount,status',
            ])
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->where(function (Builder $query): void {
                $query->where('remaining_amount', '>', 0)
                    ->orWhereHas('debtRolloversOut', function (Builder $rolloverQuery): void {
                        $rolloverQuery->where('status', InvoiceDebtRollover::STATUS_ACTIVE)
                            ->whereRaw('settled_amount < amount');
                    });
            })
            ->when(isset($validated['building_id']), fn (Builder $query): Builder => $query->whereHas('room', fn (Builder $roomQuery): Builder => $roomQuery->where('building_id', $validated['building_id'])))
            ->when(isset($validated['room_id']), fn (Builder $query): Builder => $query->where('room_id', $validated['room_id']))
            ->when(isset($validated['contract_id']), fn (Builder $query): Builder => $query->where('contract_id', $validated['contract_id']))
            ->when(isset($validated['billing_month']), fn (Builder $query): Builder => $query->where('billing_month', $validated['billing_month']))
            ->when(isset($validated['billing_year']), fn (Builder $query): Builder => $query->where('billing_year', $validated['billing_year']))
            ->when(! empty($validated['keyword']), function (Builder $query) use ($validated): void {
                $keyword = trim($validated['keyword']);
                $query->where(function (Builder $keywordQuery) use ($keyword): void {
                    $keywordQuery->where('invoice_code', 'like', "%{$keyword}%")
                        ->orWhereHas('room', fn (Builder $roomQuery): Builder => $roomQuery->where('room_number', 'like', "%{$keyword}%"))
                        ->orWhereHas('contract', fn (Builder $contractQuery): Builder => $contractQuery->where('contract_code', 'like', "%{$keyword}%"))
                        ->orWhereHas('contract.contractTenants.tenant', fn (Builder $tenantQuery): Builder => $tenantQuery->where('full_name', 'like', "%{$keyword}%"));
                });
            })
            ->orderByRaw('CASE WHEN due_date IS NOT NULL AND due_date < ? THEN 0 ELSE 1 END', [Carbon::today()->toDateString()])
            ->orderBy('due_date')
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Invoice $invoice): array => $this->debtPayload($invoice))
            ->filter(fn (array $row): bool => DecimalMoney::isPositive($row['amounts']['collectible_remaining_amount']) || $row['debt']['is_debt_rolled_over'])
            ->values();
    }

    private function filterRowsByDebtStatus(Collection $rows, string $debtStatus): Collection
    {
        return match ($debtStatus) {
            'collectible' => $rows->filter(fn (array $row): bool => DecimalMoney::isPositive($row['amounts']['collectible_remaining_amount']))->values(),
            'rolled' => $rows->filter(fn (array $row): bool => (bool) $row['debt']['is_debt_rolled_over'])->values(),
            'overdue' => $rows->filter(fn (array $row): bool => (bool) $row['debt']['is_overdue'])->values(),
            default => $rows,
        };
    }

    private function debtPayload(Invoice $invoice): array
    {
        $rolloverOut = $this->activeRolloverOut($invoice);
        $rolledOutstandingAmount = $rolloverOut
            ? DecimalMoney::maxZero(DecimalMoney::subtract($rolloverOut->amount, $rolloverOut->settled_amount))
            : '0.00';
        $collectibleAmount = $this->debtRolloverService->collectibleRemainingAmount($invoice);
        $isOverdue = DecimalMoney::isPositive($collectibleAmount)
            && ((int) $invoice->status === Invoice::STATUS_OVERDUE || ($invoice->due_date && $invoice->due_date->copy()->startOfDay()->lt(Carbon::today())));

        return [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_code' => $invoice->invoice_code,
                'status' => $invoice->status,
                'status_label' => Invoice::STATUS_LABELS[$invoice->status] ?? null,
            ],
            'contract' => [
                'id' => $invoice->contract_id,
                'contract_code' => $invoice->contract?->contract_code,
            ],
            'room' => [
                'id' => $invoice->room_id,
                'room_number' => $invoice->room?->room_number,
                'floor' => $invoice->room?->floor,
            ],
            'building' => [
                'id' => $invoice->room?->building_id,
                'name' => $invoice->room?->building?->name,
            ],
            'tenants' => $this->tenantPayload($invoice),
            'period' => [
                'billing_month' => $invoice->billing_month,
                'billing_year' => $invoice->billing_year,
                'period_start' => optional($invoice->period_start)->toDateString(),
                'period_end' => optional($invoice->period_end)->toDateString(),
                'due_date' => optional($invoice->due_date)->toDateString(),
            ],
            'amounts' => [
                'total_amount' => (string) $invoice->total_amount,
                'paid_amount' => (string) $invoice->paid_amount,
                'remaining_amount' => (string) $invoice->remaining_amount,
                'collectible_remaining_amount' => $collectibleAmount,
                'rolled_outstanding_amount' => $rolledOutstandingAmount,
                'previous_debt_amount' => (string) $invoice->previous_debt_amount,
            ],
            'debt' => [
                'debt_status' => $this->debtStatus($invoice, $collectibleAmount, $rolloverOut, $isOverdue),
                'is_overdue' => $isOverdue,
                'can_collect_directly' => DecimalMoney::isPositive($collectibleAmount) && ! $rolloverOut,
                'is_debt_rolled_over' => $rolloverOut !== null,
            ],
            'rollover' => [
                'rolled_to_invoice_id' => $rolloverOut?->target_invoice_id,
                'rolled_to_invoice_code' => $rolloverOut?->targetInvoice?->invoice_code,
                'rolled_sources' => $this->rolledSourcesPayload($invoice),
            ],
        ];
    }

    private function debtStatus(Invoice $invoice, string $collectibleAmount, ?InvoiceDebtRollover $rolloverOut, bool $isOverdue): string
    {
        if ($rolloverOut) {
            return 'rolled';
        }

        if ($isOverdue) {
            return 'overdue';
        }

        if (DecimalMoney::isPositive($collectibleAmount)) {
            return (int) $invoice->status === Invoice::STATUS_PARTIALLY_PAID ? 'partial' : 'collectible';
        }

        return 'cleared';
    }

    private function activeRolloverOut(Invoice $invoice): ?InvoiceDebtRollover
    {
        if (! $invoice->relationLoaded('debtRolloversOut')) {
            return null;
        }

        return $invoice->debtRolloversOut
            ->first(fn (InvoiceDebtRollover $rollover): bool => (int) $rollover->status === InvoiceDebtRollover::STATUS_ACTIVE
                && DecimalMoney::isPositive(DecimalMoney::subtract($rollover->amount, $rollover->settled_amount))
                && (! $rollover->targetInvoice || (int) $rollover->targetInvoice->status !== Invoice::STATUS_CANCELLED));
    }

    private function tenantPayload(Invoice $invoice): array
    {
        if (! $invoice->contract?->relationLoaded('contractTenants')) {
            return [];
        }

        return $invoice->contract->contractTenants
            ->map(fn ($contractTenant): array => [
                'id' => $contractTenant->tenant?->id,
                'full_name' => $contractTenant->tenant?->full_name,
                'phone' => $contractTenant->tenant?->phone,
                'email' => $contractTenant->tenant?->email,
                'is_staying' => (bool) $contractTenant->is_staying,
            ])
            ->values()
            ->all();
    }

    private function rolledSourcesPayload(Invoice $invoice): array
    {
        if (! $invoice->relationLoaded('debtRolloversIn')) {
            return [];
        }

        return $invoice->debtRolloversIn
            ->filter(fn (InvoiceDebtRollover $rollover): bool => in_array((int) $rollover->status, [InvoiceDebtRollover::STATUS_ACTIVE, InvoiceDebtRollover::STATUS_SETTLED], true))
            ->map(fn (InvoiceDebtRollover $rollover): array => [
                'source_invoice_id' => $rollover->source_invoice_id,
                'source_invoice_code' => $rollover->sourceInvoice?->invoice_code,
                'amount' => (string) $rollover->amount,
                'settled_amount' => (string) $rollover->settled_amount,
                'remaining_amount' => DecimalMoney::maxZero(DecimalMoney::subtract($rollover->amount, $rollover->settled_amount)),
                'status' => $rollover->status,
                'status_label' => InvoiceDebtRollover::STATUS_LABELS[$rollover->status] ?? null,
            ])
            ->values()
            ->all();
    }

    private function summary(Collection $rows): array
    {
        return [
            'total_collectible_amount' => DecimalMoney::add($rows->pluck('amounts.collectible_remaining_amount')->all()),
            'total_rolled_outstanding_amount' => DecimalMoney::add($rows->pluck('amounts.rolled_outstanding_amount')->all()),
            'invoice_count' => $rows->count(),
            'collectible_count' => $rows->filter(fn (array $row): bool => DecimalMoney::isPositive($row['amounts']['collectible_remaining_amount']))->count(),
            'rolled_count' => $rows->filter(fn (array $row): bool => (bool) $row['debt']['is_debt_rolled_over'])->count(),
            'overdue_count' => $rows->filter(fn (array $row): bool => (bool) $row['debt']['is_overdue'])->count(),
        ];
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
}
