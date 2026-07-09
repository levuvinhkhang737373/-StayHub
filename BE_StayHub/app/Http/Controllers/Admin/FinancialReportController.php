<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Building;
use App\Models\ContractDepositTransaction;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceDebtRollover;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\RoomMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialReportController extends Controller
{
    /**
     * Lấy báo cáo doanh thu, công nợ, chi phí, lợi nhuận chi tiết.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
                'month_from' => ['nullable', 'integer', 'min:1', 'max:12'],
                'month_to' => ['nullable', 'integer', 'min:1', 'max:12'],
                'building_id' => ['nullable', 'integer'],
            ]);

            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Unauthorized', 401, null, 401);
            }

            $requestedBuildingId = isset($validated['building_id']) ? (int) $validated['building_id'] : null;
            $availableBuildings = $this->availableBuildings($admin);

            if ($requestedBuildingId && ! $availableBuildings->contains('id', $requestedBuildingId)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền quản lý tòa nhà này', 403, null, 403);
            }

            $selectedBuildingId = $requestedBuildingId ?: (AdminScope::isSuperAdmin($admin) ? null : $availableBuildings->first()?->id);
            $buildingIds = $selectedBuildingId
                ? [$selectedBuildingId]
                : $availableBuildings->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

            if (empty($buildingIds)) {
                return ApiResponse::responseJson(true, 'Báo cáo tài chính rỗng', 200, [
                    'summary' => $this->emptySummary(),
                    'chart' => [],
                    'revenue_breakdown' => [],
                    'expense_breakdown' => [],
                    'debt_breakdown' => [],
                    'top_buildings' => [],
                ], 200);
            }

            $year = (int) ($validated['year'] ?? Carbon::now()->year);
            $monthFrom = (int) ($validated['month_from'] ?? 1);
            $monthTo = (int) ($validated['month_to'] ?? ($year === Carbon::now()->year ? Carbon::now()->month : 12));

            if ($monthFrom > $monthTo) {
                return ApiResponse::responseJson(false, 'Tháng bắt đầu không được lớn hơn tháng kết thúc', 422, null, 422);
            }

            $startDate = Carbon::create($year, $monthFrom)->startOfMonth();
            $endDate = Carbon::create($year, $monthTo)->endOfMonth();
            $monthRange = $this->monthRange($year, $monthFrom, $monthTo);
            $isSystemWide = AdminScope::isSuperAdmin($admin) && $selectedBuildingId === null;
            $roomMovements = $this->scopedRoomMovementExtraChargeQuery($buildingIds)->get();

            $chart = [];
            $totalRevenue = 0.0;
            $totalCollectedRevenue = 0.0;
            $totalExpenses = 0.0;
            $totalDebt = 0.0;
            $totalCurrentDebt = 0.0;
            $totalRolledDebt = 0.0;

            foreach ($monthRange as $month) {
                $totals = $this->periodTotals($buildingIds, $month['start'], $month['end'], $isSystemWide, $roomMovements);
                $revenue = $totals['collected_revenue'] + $totals['debt'];
                $profit = $revenue - $totals['expenses'];

                $chart[] = [
                    'month' => $month['label'],
                    'month_key' => $month['key'],
                    'revenue' => round($revenue, 2),
                    'collected_revenue' => round($totals['collected_revenue'], 2),
                    'debt' => round($totals['debt'], 2),
                    'outstanding_debt' => round($totals['debt'], 2),
                    'current_debt' => round($totals['current_debt'], 2),
                    'rolled_debt' => round($totals['rolled_debt'], 2),
                    'expenses' => round($totals['expenses'], 2),
                    'profit' => round($profit, 2),
                    'expected_revenue' => round($revenue, 2),
                ];

                $totalRevenue += $revenue;
                $totalCollectedRevenue += $totals['collected_revenue'];
                $totalExpenses += $totals['expenses'];
                $totalDebt += $totals['debt'];
                $totalCurrentDebt += $totals['current_debt'];
                $totalRolledDebt += $totals['rolled_debt'];
            }

            $totalProfit = $totalRevenue - $totalExpenses;
            $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0.0;

            $summary = [
                'revenue' => round($totalRevenue, 2),
                'collected_revenue' => round($totalCollectedRevenue, 2),
                'debt' => round($totalDebt, 2),
                'outstanding_debt' => round($totalDebt, 2),
                'current_debt' => round($totalCurrentDebt, 2),
                'rolled_debt' => round($totalRolledDebt, 2),
                'expected_revenue' => round($totalRevenue, 2),
                'expenses' => round($totalExpenses, 2),
                'profit' => round($totalProfit, 2),
                'profit_margin' => round($profitMargin, 1),
                'expected_profit' => round($totalProfit, 2),
                'expected_profit_margin' => round($profitMargin, 1),
            ];

            $revenueBreakdown = $this->revenueBreakdown($buildingIds, $startDate, $endDate, $roomMovements, $totalCollectedRevenue);
            $expenseBreakdown = $this->expenseBreakdown($buildingIds, $isSystemWide, $startDate, $endDate, $totalExpenses);
            $debtBreakdown = $this->debtBreakdown($totalCurrentDebt, $totalRolledDebt, $totalDebt);
            $topBuildings = $this->topBuildings($buildingIds, $startDate, $endDate, $roomMovements);

            return ApiResponse::responseJson(true, 'Báo cáo lợi nhuận chi tiết', 200, [
                'summary' => $summary,
                'chart' => $chart,
                'revenue_breakdown' => $revenueBreakdown,
                'expense_breakdown' => $expenseBreakdown,
                'debt_breakdown' => $debtBreakdown,
                'top_buildings' => $this->withBuildingPercentages($topBuildings, $totalRevenue, $totalDebt, $totalCollectedRevenue),
            ], 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    private function availableBuildings(Admin $admin): Collection
    {
        $query = Building::query()
            ->select(['id', 'name', 'slug', 'status', 'manager_admin_id'])
            ->orderBy('name');

        if (AdminScope::isBuildingManager($admin)) {
            $query->where('manager_admin_id', $admin->id);
        }

        return $query->get();
    }

    private function emptySummary(): array
    {
        return [
            'revenue' => 0,
            'collected_revenue' => 0,
            'debt' => 0,
            'outstanding_debt' => 0,
            'current_debt' => 0,
            'rolled_debt' => 0,
            'expected_revenue' => 0,
            'expenses' => 0,
            'profit' => 0,
            'profit_margin' => 0,
            'expected_profit' => 0,
            'expected_profit_margin' => 0,
        ];
    }

    private function monthRange(int $year, int $monthFrom, int $monthTo): array
    {
        $monthRange = [];
        for ($month = $monthFrom; $month <= $monthTo; $month++) {
            $start = Carbon::create($year, $month)->startOfMonth();
            $monthRange[] = [
                'key' => $start->format('Y-m'),
                'label' => $start->format('m/Y'),
                'start' => $start,
                'end' => $start->copy()->endOfMonth(),
            ];
        }

        return $monthRange;
    }

    private function periodTotals(array $buildingIds, Carbon $start, Carbon $end, bool $includeGlobalExpenses, Collection $roomMovements): array
    {
        $collectedRevenue = $this->periodRevenue($buildingIds, $start, $end, $roomMovements);
        $debt = $this->periodDebt($buildingIds, $start, $end);
        $expenses = (float) $this->scopedExpenseQuery($buildingIds, $includeGlobalExpenses)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');

        return [
            'collected_revenue' => $collectedRevenue,
            'debt' => $debt['total'],
            'current_debt' => $debt['current'],
            'rolled_debt' => $debt['rolled'],
            'expenses' => $expenses,
        ];
    }

    private function periodRevenue(array $buildingIds, Carbon $start, Carbon $end, Collection $roomMovements): float
    {
        $paymentRevenue = (float) $this->scopedPaymentQuery($buildingIds)
            ->whereBetween('payment_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->sum('amount');

        $deductionRevenue = (float) $this->scopedDepositDeductionQuery($buildingIds)
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');

        return $paymentRevenue + $deductionRevenue + $this->roomMovementExtraRevenue($roomMovements, $buildingIds, $start, $end);
    }

    private function periodDebt(array $buildingIds, Carbon $start, Carbon $end): array
    {
        $invoices = $this->scopedInvoiceQuery($buildingIds)
            ->with(['debtRolloversIn', 'debtRolloversOut.targetInvoice:id,status'])
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->where('billing_year', (int) $start->year)
            ->whereBetween('billing_month', [(int) $start->month, (int) $end->month])
            ->get();

        $currentDebt = 0.0;
        $rolledDebt = 0.0;

        foreach ($invoices as $invoice) {
            $rolledInRemaining = $invoice->debtRolloversIn
                ->whereIn('status', [InvoiceDebtRollover::STATUS_ACTIVE, InvoiceDebtRollover::STATUS_SETTLED])
                ->sum(fn (InvoiceDebtRollover $rollover): float => max(0.0, (float) $rollover->amount - (float) $rollover->settled_amount));

            $rolledOutRemaining = $invoice->debtRolloversOut
                ->filter(fn (InvoiceDebtRollover $rollover): bool => (int) $rollover->status === InvoiceDebtRollover::STATUS_ACTIVE
                    && (! $rollover->targetInvoice || (int) $rollover->targetInvoice->status !== Invoice::STATUS_CANCELLED))
                ->sum(fn (InvoiceDebtRollover $rollover): float => max(0.0, (float) $rollover->amount - (float) $rollover->settled_amount));

            $collectibleRemaining = max(0.0, (float) $invoice->remaining_amount - $rolledOutRemaining);
            $invoiceCurrentDebt = max(0.0, $collectibleRemaining - $rolledInRemaining);

            $currentDebt += $invoiceCurrentDebt;
            $rolledDebt += min($rolledInRemaining, $collectibleRemaining);
        }

        return [
            'current' => round($currentDebt, 2),
            'rolled' => round($rolledDebt, 2),
            'total' => round($currentDebt + $rolledDebt, 2),
        ];
    }

    private function revenueBreakdown(array $buildingIds, Carbon $startDate, Carbon $endDate, Collection $roomMovements, float $totalRevenue): array
    {
        $payments = $this->scopedPaymentQuery($buildingIds)
            ->whereBetween('payment_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->with(['invoice.items'])
            ->get();

        $rolledDebtCollections = $payments->isEmpty()
            ? collect()
            : Payment::query()
                ->whereIn('allocated_from_payment_id', $payments->pluck('id'))
                ->where('is_internal_allocation', true)
                ->where('status', Payment::STATUS_CONFIRMED)
                ->select('allocated_from_payment_id', DB::raw('SUM(amount) as amount'))
                ->groupBy('allocated_from_payment_id')
                ->pluck('amount', 'allocated_from_payment_id');

        $itemRevenueMap = [];
        foreach ($payments as $payment) {
            $invoice = $payment->invoice;
            if (! $invoice) {
                continue;
            }

            $paymentAmount = (float) $payment->amount;
            $rolledDebtAmount = min($paymentAmount, max(0.0, (float) ($rolledDebtCollections[(int) $payment->id] ?? 0)));
            if ($rolledDebtAmount > 0) {
                $itemRevenueMap['Thu nợ cũ'] = ($itemRevenueMap['Thu nợ cũ'] ?? 0.0) + $rolledDebtAmount;
            }

            $currentItemPaymentAmount = max(0.0, $paymentAmount - $rolledDebtAmount);
            if ($currentItemPaymentAmount <= 0) {
                continue;
            }

            $netInvoiceAmount = max(0.0, (float) $invoice->total_amount - (float) $invoice->previous_debt_amount);
            if ($netInvoiceAmount <= 0) {
                continue;
            }

            $currentItemPaymentAmount = min($currentItemPaymentAmount, $netInvoiceAmount);
            $ratio = $currentItemPaymentAmount / $netInvoiceAmount;

            foreach ($invoice->items as $item) {
                if ((int) $item->item_type === InvoiceItem::ITEM_TYPE_OLD_DEBT) {
                    continue;
                }

                $itemType = (int) $item->item_type;
                $itemAmount = (float) $item->amount;
                $itemRevenueMap[$itemType] = ($itemRevenueMap[$itemType] ?? 0.0) + ($itemAmount * $ratio);
            }
        }

        $totalDeductions = (float) $this->scopedDepositDeductionQuery($buildingIds)
            ->whereBetween('transaction_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('amount');
        if ($totalDeductions > 0) {
            $itemRevenueMap['Khấu trừ cọc'] = ($itemRevenueMap['Khấu trừ cọc'] ?? 0) + $totalDeductions;
        }

        $totalExtraRevenue = $this->roomMovementExtraRevenue($roomMovements, $buildingIds, $startDate, $endDate);
        if ($totalExtraRevenue > 0) {
            $itemRevenueMap['Phí chuyển phòng'] = ($itemRevenueMap['Phí chuyển phòng'] ?? 0) + $totalExtraRevenue;
        }

        $itemLabels = InvoiceItem::ITEM_TYPE_LABELS + [
            'Khấu trừ cọc' => 'Khấu trừ cọc',
            'Phí chuyển phòng' => 'Phí chuyển phòng',
            'Thu nợ cũ' => 'Thu nợ cũ',
        ];

        $revenueBreakdown = [];
        foreach ($itemRevenueMap as $type => $amount) {
            if (abs($amount) < 0.01) {
                continue;
            }

            $revenueBreakdown[] = [
                'label' => $itemLabels[$type] ?? 'Dịch vụ khác',
                'amount' => round($amount, 2),
                'percentage' => $totalRevenue > 0 ? round(($amount / $totalRevenue) * 100, 1) : 0.0,
            ];
        }

        $totalItemRevenueSum = array_sum(array_column($revenueBreakdown, 'amount'));
        if ($totalRevenue > $totalItemRevenueSum + 0.05) {
            $diff = $totalRevenue - $totalItemRevenueSum;
            $revenueBreakdown[] = [
                'label' => 'Khác',
                'amount' => round($diff, 2),
                'percentage' => $totalRevenue > 0 ? round(($diff / $totalRevenue) * 100, 1) : 0.0,
            ];
        }

        usort($revenueBreakdown, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return $revenueBreakdown;
    }

    private function expenseBreakdown(array $buildingIds, bool $includeGlobalExpenses, Carbon $startDate, Carbon $endDate, float $totalExpenses): array
    {
        $expenses = $this->scopedExpenseQuery($buildingIds, $includeGlobalExpenses)
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with('category')
            ->get();

        $categoryExpenseMap = [];
        foreach ($expenses as $expense) {
            $categoryName = $expense->category?->name ?? 'Chưa phân loại';
            $categoryExpenseMap[$categoryName] = ($categoryExpenseMap[$categoryName] ?? 0.0) + (float) $expense->amount;
        }

        $expenseBreakdown = [];
        foreach ($categoryExpenseMap as $label => $amount) {
            if (abs($amount) < 0.01) {
                continue;
            }

            $expenseBreakdown[] = [
                'label' => $label,
                'amount' => round($amount, 2),
                'percentage' => $totalExpenses > 0 ? round(($amount / $totalExpenses) * 100, 1) : 0.0,
            ];
        }

        usort($expenseBreakdown, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return $expenseBreakdown;
    }

    private function debtBreakdown(float $currentDebt, float $rolledDebt, float $totalDebt): array
    {
        return collect([
            ['label' => 'Nợ kỳ hiện tại', 'amount' => round($currentDebt, 2)],
            ['label' => 'Nợ cũ chuyển sang', 'amount' => round($rolledDebt, 2)],
        ])
            ->filter(fn (array $item): bool => abs((float) $item['amount']) >= 0.01)
            ->map(fn (array $item): array => [
                'label' => $item['label'],
                'amount' => $item['amount'],
                'percentage' => $totalDebt > 0 ? round(((float) $item['amount'] / $totalDebt) * 100, 1) : 0.0,
            ])
            ->values()
            ->all();
    }

    private function topBuildings(array $buildingIds, Carbon $startDate, Carbon $endDate, Collection $roomMovements): array
    {
        $buildingRows = Building::query()
            ->whereIn('id', $buildingIds)
            ->select(['id', 'name'])
            ->get()
            ->mapWithKeys(fn (Building $building): array => [
                (int) $building->id => [
                    'id' => (int) $building->id,
                    'name' => $building->name,
                    'revenue' => 0.0,
                    'debt' => 0.0,
                    'expected_revenue' => 0.0,
                ],
            ])
            ->all();

        $this->applyBuildingRevenueRows($buildingRows, $buildingIds, $startDate, $endDate);
        $this->applyBuildingDebtRows($buildingRows, $buildingIds, $startDate, $endDate);

        foreach ($roomMovements as $roomMovement) {
            $refs = $roomMovement->settlement_payment_references ?? [];
            if (! is_array($refs)) {
                continue;
            }

            foreach ($refs as $ref) {
                if (empty($ref['paid_at'])) {
                    continue;
                }

                $paidDate = Carbon::parse($ref['paid_at']);
                $buildingId = (int) ($roomMovement->toRoom?->building_id ?? 0);
                if (! $buildingId || ! in_array($buildingId, $buildingIds, true) || ! $paidDate->between($startDate->copy()->startOfDay(), $endDate->copy()->endOfDay())) {
                    continue;
                }

                $buildingRows[$buildingId]['revenue'] += (float) ($ref['extra_amount'] ?? 0);
            }
        }

        foreach ($buildingRows as &$row) {
            $row['expected_revenue'] = $row['revenue'] + $row['debt'];
        }
        unset($row);

        usort($buildingRows, fn ($a, $b) => $b['expected_revenue'] <=> $a['expected_revenue']);

        return array_values(array_filter($buildingRows, fn (array $row): bool => $row['revenue'] > 0 || $row['debt'] > 0));
    }

    private function applyBuildingRevenueRows(array &$buildingRows, array $buildingIds, Carbon $startDate, Carbon $endDate): void
    {
        Payment::query()
            ->where('payments.status', Payment::STATUS_CONFIRMED)
            ->where(function (Builder $query): void {
                $query->where('payments.is_internal_allocation', false)
                    ->orWhereNull('payments.is_internal_allocation');
            })
            ->whereBetween('payments.payment_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->join('invoices', 'payments.invoice_id', '=', 'invoices.id')
            ->join('rooms', 'invoices.room_id', '=', 'rooms.id')
            ->whereIn('rooms.building_id', $buildingIds)
            ->select('rooms.building_id', DB::raw('SUM(payments.amount) as revenue'))
            ->groupBy('rooms.building_id')
            ->get()
            ->each(function ($row) use (&$buildingRows): void {
                $buildingRows[(int) $row->building_id]['revenue'] += (float) $row->revenue;
            });

        ContractDepositTransaction::query()
            ->where('transaction_type', ContractDepositTransaction::TRANSACTION_TYPE_DEDUCT)
            ->whereBetween('transaction_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->join('contracts', 'contract_deposit_transactions.contract_id', '=', 'contracts.id')
            ->join('rooms', 'contracts.room_id', '=', 'rooms.id')
            ->whereIn('rooms.building_id', $buildingIds)
            ->select('rooms.building_id', DB::raw('SUM(contract_deposit_transactions.amount) as revenue'))
            ->groupBy('rooms.building_id')
            ->get()
            ->each(function ($row) use (&$buildingRows): void {
                $buildingRows[(int) $row->building_id]['revenue'] += (float) $row->revenue;
            });
    }

    private function applyBuildingDebtRows(array &$buildingRows, array $buildingIds, Carbon $startDate, Carbon $endDate): void
    {
        $this->scopedInvoiceQuery($buildingIds)
            ->with(['room:id,building_id', 'debtRolloversIn', 'debtRolloversOut.targetInvoice:id,status'])
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->where('billing_year', (int) $startDate->year)
            ->whereBetween('billing_month', [(int) $startDate->month, (int) $endDate->month])
            ->get()
            ->each(function (Invoice $invoice) use (&$buildingRows): void {
                $buildingId = (int) ($invoice->room?->building_id ?? 0);
                if (! $buildingId || ! isset($buildingRows[$buildingId])) {
                    return;
                }

                $buildingRows[$buildingId]['debt'] += $this->invoiceReportDebt($invoice);
            });
    }

    private function withBuildingPercentages(array $topBuildings, float $totalRevenue, float $totalDebt, float $totalCollectedRevenue): array
    {
        return collect($topBuildings)
            ->map(fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'revenue' => round((float) $row['revenue'], 2),
                'debt' => round((float) $row['debt'], 2),
                'outstanding_debt' => round((float) $row['debt'], 2),
                'expected_revenue' => round((float) $row['expected_revenue'], 2),
                'percentage' => $totalCollectedRevenue > 0 ? round(((float) $row['revenue'] / $totalCollectedRevenue) * 100, 1) : 0.0,
                'debt_percentage' => $totalDebt > 0 ? round(((float) $row['debt'] / $totalDebt) * 100, 1) : 0.0,
                'expected_percentage' => $totalRevenue > 0 ? round(((float) $row['expected_revenue'] / $totalRevenue) * 100, 1) : 0.0,
            ])
            ->values()
            ->all();
    }

    private function invoiceReportDebt(Invoice $invoice): float
    {
        $rolledInRemaining = $invoice->debtRolloversIn
            ->whereIn('status', [InvoiceDebtRollover::STATUS_ACTIVE, InvoiceDebtRollover::STATUS_SETTLED])
            ->sum(fn (InvoiceDebtRollover $rollover): float => max(0.0, (float) $rollover->amount - (float) $rollover->settled_amount));

        $rolledOutRemaining = $invoice->debtRolloversOut
            ->filter(fn (InvoiceDebtRollover $rollover): bool => (int) $rollover->status === InvoiceDebtRollover::STATUS_ACTIVE
                && (! $rollover->targetInvoice || (int) $rollover->targetInvoice->status !== Invoice::STATUS_CANCELLED))
            ->sum(fn (InvoiceDebtRollover $rollover): float => max(0.0, (float) $rollover->amount - (float) $rollover->settled_amount));

        $collectibleRemaining = max(0.0, (float) $invoice->remaining_amount - $rolledOutRemaining);

        return max(0.0, $collectibleRemaining - $rolledInRemaining) + min($rolledInRemaining, $collectibleRemaining);
    }

    private function roomMovementExtraRevenue(Collection $roomMovements, array $buildingIds, Carbon $start, Carbon $end): float
    {
        $extraRevenue = 0.0;
        foreach ($roomMovements as $roomMovement) {
            $refs = $roomMovement->settlement_payment_references ?? [];
            if (! is_array($refs)) {
                continue;
            }

            foreach ($refs as $ref) {
                if (empty($ref['paid_at'])) {
                    continue;
                }

                $buildingId = (int) ($roomMovement->toRoom?->building_id ?? 0);
                $paidDate = Carbon::parse($ref['paid_at']);
                if ($buildingId && in_array($buildingId, $buildingIds, true) && $paidDate->between($start->copy()->startOfDay(), $end->copy()->endOfDay())) {
                    $extraRevenue += (float) ($ref['extra_amount'] ?? 0);
                }
            }
        }

        return $extraRevenue;
    }

    private function scopedPaymentQuery(array $buildingIds): Builder
    {
        $query = Payment::query()->realMoney()->where('status', Payment::STATUS_CONFIRMED);

        if (empty($buildingIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('invoice.room', fn (Builder $roomQuery): Builder => $roomQuery->whereIn('building_id', $buildingIds));
    }

    private function scopedInvoiceQuery(array $buildingIds): Builder
    {
        $query = Invoice::query();

        if (empty($buildingIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('room', fn (Builder $roomQuery): Builder => $roomQuery->whereIn('building_id', $buildingIds));
    }

    private function scopedExpenseQuery(array $buildingIds, bool $includeGlobalExpenses = false): Builder
    {
        $query = Expense::query()->where('status', Expense::STATUS_RECORDED);

        if (empty($buildingIds)) {
            return $includeGlobalExpenses ? $query : $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scopeQuery) use ($buildingIds, $includeGlobalExpenses): void {
            $scopeQuery->where(function (Builder $query) use ($buildingIds): void {
                $query->whereIn('building_id', $buildingIds)
                    ->orWhereHas('room', fn (Builder $roomQuery): Builder => $roomQuery->whereIn('building_id', $buildingIds));
            })->whereHas('category', function (Builder $categoryQuery): void {
                $categoryQuery->where('name', '!=', 'Hoàn cọc hợp đồng');
            });

            if ($includeGlobalExpenses) {
                $scopeQuery->orWhere(function (Builder $globalQuery): void {
                    $globalQuery->whereNull('building_id')->whereNull('room_id');
                });
            }
        });
    }

    private function scopedDepositDeductionQuery(array $buildingIds): Builder
    {
        $query = ContractDepositTransaction::query()
            ->where('transaction_type', ContractDepositTransaction::TRANSACTION_TYPE_DEDUCT);

        if (empty($buildingIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('contract.room', fn (Builder $roomQuery): Builder => $roomQuery->whereIn('building_id', $buildingIds));
    }

    private function scopedRoomMovementExtraChargeQuery(array $buildingIds): Builder
    {
        $query = RoomMovement::query()
            ->with(['toRoom.building'])
            ->whereIn('settlement_payment_status', [RoomMovement::SETTLEMENT_PAYMENT_STATUS_PARTIAL, RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID]);

        if (empty($buildingIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('toRoom', fn (Builder $roomQuery): Builder => $roomQuery->whereIn('building_id', $buildingIds));
    }
}
