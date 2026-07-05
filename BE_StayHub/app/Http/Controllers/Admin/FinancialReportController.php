<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Building;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\RoomMovement;
use App\Models\ContractDepositTransaction;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialReportController extends Controller
{
    /**
     * Lấy báo cáo doanh thu, chi phí, lợi nhuận chi tiết
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
            if (!$admin) {
                return ApiResponse::responseJson(false, 'Unauthorized', 401, null, 401);
            }

            $requestedBuildingId = isset($validated['building_id']) ? (int) $validated['building_id'] : null;
            $availableBuildings = $this->availableBuildings($admin);

            if ($requestedBuildingId && !$availableBuildings->contains('id', $requestedBuildingId)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền quản lý tòa nhà này', 403, null, 403);
            }

            $selectedBuildingId = $requestedBuildingId ?: (AdminScope::isSuperAdmin($admin) ? null : $availableBuildings->first()?->id);
            $buildingIds = $selectedBuildingId
                ? [$selectedBuildingId]
                : $availableBuildings->pluck('id')->map(fn($id): int => (int) $id)->values()->all();

            if (empty($buildingIds)) {
                return ApiResponse::responseJson(true, 'Báo cáo tài chính rỗng', 200, [
                    'summary' => [
                        'revenue' => 0,
                        'expenses' => 0,
                        'profit' => 0,
                        'profit_margin' => 0,
                    ],
                    'chart' => [],
                    'revenue_breakdown' => [],
                    'expense_breakdown' => [],
                ], 200);
            }

            $year = (int) ($validated['year'] ?? Carbon::now()->year);
            $monthFrom = (int) ($validated['month_from'] ?? 1);
            $monthTo = (int) ($validated['month_to'] ?? ($year === Carbon::now()->year ? Carbon::now()->month : 12));

            $startDate = Carbon::create($year, $monthFrom)->startOfMonth();
            $endDate = Carbon::create($year, $monthTo)->endOfMonth();

            // 1. Tạo khoảng danh sách tháng
            $monthRange = [];
            for ($m = $monthFrom; $m <= $monthTo; $m++) {
                $start = Carbon::create($year, $m)->startOfMonth();
                $monthRange[] = [
                    'key' => $start->format('Y-m'),
                    'label' => $start->format('m/Y'),
                    'start' => $start,
                    'end' => $start->copy()->endOfMonth(),
                ];
            }

            $chart = [];
            $totalRevenue = 0.0;
            $totalExpenses = 0.0;
            $isSystemWide = AdminScope::isSuperAdmin($admin) && $selectedBuildingId === null;

            $roomMovements = $this->scopedRoomMovementExtraChargeQuery($buildingIds)->get();

            // 2. Tính toán tổng quan từng tháng (Dùng cho Chart)
            foreach ($monthRange as $month) {
                $monthStart = $month['start']->copy()->startOfDay();
                $monthEnd = $month['end']->copy()->endOfDay();

                $paymentRevenue = (float) $this->scopedPaymentQuery($buildingIds)
                    ->whereBetween('payment_date', [$monthStart, $monthEnd])
                    ->sum('amount');

                $deductionRevenue = (float) $this->scopedDepositDeductionQuery($buildingIds)
                    ->whereBetween('transaction_date', [$month['start']->toDateString(), $month['end']->toDateString()])
                    ->sum('amount');

                $extraRevenue = 0.0;
                foreach ($roomMovements as $rm) {
                    $refs = $rm->settlement_payment_references ?? [];
                    if (is_array($refs)) {
                        foreach ($refs as $ref) {
                            if (!empty($ref['paid_at'])) {
                                $paidDate = Carbon::parse($ref['paid_at']);
                                if ($paidDate->between($monthStart, $monthEnd)) {
                                    $extraRevenue += (float) ($ref['extra_amount'] ?? 0);
                                }
                            }
                        }
                    }
                }

                $revenue = $paymentRevenue + $deductionRevenue + $extraRevenue;

                $expenses = (float) $this->scopedExpenseQuery($buildingIds, $isSystemWide)
                    ->whereBetween('expense_date', [$month['start']->toDateString(), $month['end']->toDateString()])
                    ->sum('amount');

                $profit = $revenue - $expenses;

                $chart[] = [
                    'month' => $month['label'],
                    'month_key' => $month['key'],
                    'revenue' => round($revenue, 2),
                    'expenses' => round($expenses, 2),
                    'profit' => round($profit, 2),
                ];

                $totalRevenue += $revenue;
                $totalExpenses += $expenses;
            }

            $totalProfit = $totalRevenue - $totalExpenses;
            $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0.0;

            $summary = [
                'revenue' => round($totalRevenue, 2),
                'expenses' => round($totalExpenses, 2),
                'profit' => round($totalProfit, 2),
                'profit_margin' => round($profitMargin, 1),
            ];

            // 3. Cơ cấu doanh thu theo InvoiceItem (loại tiền phòng, điện, nước...)
            $payments = $this->scopedPaymentQuery($buildingIds)
                ->whereBetween('payment_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->with(['invoice.items'])
                ->get();
 
            $itemRevenueMap = [];
            foreach ($payments as $payment) {
                $invoice = $payment->invoice;
                if (!$invoice) continue;
 
                $totalInvoiceAmount = (float) $invoice->total_amount;
                if ($totalInvoiceAmount <= 0) continue;
 
                $paymentAmount = (float) $payment->amount;
                $ratio = $paymentAmount / $totalInvoiceAmount;
 
                foreach ($invoice->items as $item) {
                    $itemType = (int) $item->item_type;
                    $itemAmount = (float) $item->amount;
                    $allocatedRevenue = $itemAmount * $ratio;
 
                    if (!isset($itemRevenueMap[$itemType])) {
                        $itemRevenueMap[$itemType] = 0.0;
                    }
                    $itemRevenueMap[$itemType] += $allocatedRevenue;
                }
            }
 
            // Cộng thêm doanh thu từ phạt cọc và phụ thu vào breakdown
            $totalDeductions = (float) $this->scopedDepositDeductionQuery($buildingIds)
                ->whereBetween('transaction_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->sum('amount');
            if ($totalDeductions > 0) {
                $itemRevenueMap['Khấu trừ cọc'] = ($itemRevenueMap['Khấu trừ cọc'] ?? 0) + $totalDeductions;
            }
 
            $totalExtraRevenue = 0.0;
            foreach ($roomMovements as $rm) {
                $refs = $rm->settlement_payment_references ?? [];
                if (is_array($refs)) {
                    foreach ($refs as $ref) {
                        if (!empty($ref['paid_at'])) {
                            $paidDate = Carbon::parse($ref['paid_at']);
                            if ($paidDate->between($startDate->copy()->startOfDay(), $endDate->copy()->endOfDay())) {
                                $totalExtraRevenue += (float) ($ref['extra_amount'] ?? 0);
                            }
                        }
                    }
                }
            }
            if ($totalExtraRevenue > 0) {
                $itemRevenueMap['Phí chuyển phòng'] = ($itemRevenueMap['Phí chuyển phòng'] ?? 0) + $totalExtraRevenue;
            }
 
            $revenueBreakdown = [];
            $itemLabels = \App\Models\InvoiceItem::ITEM_TYPE_LABELS + [
                'Khấu trừ cọc' => 'Khấu trừ cọc',
                'Phí chuyển phòng' => 'Phí chuyển phòng'
            ];

            foreach ($itemRevenueMap as $type => $amount) {
                if ($amount == 0) continue;
                $label = $itemLabels[$type] ?? 'Dịch vụ khác';
                $revenueBreakdown[] = [
                    'label' => $label,
                    'amount' => round($amount, 2),
                    'percentage' => $totalRevenue > 0 ? round(($amount / $totalRevenue) * 100, 1) : 0.0,
                ];
            }

            // Fallback phòng khi tổng phân loại nhỏ hơn tổng thực thu
            $totalItemRevenueSum = array_sum(array_column($revenueBreakdown, 'amount'));
            if ($totalRevenue > $totalItemRevenueSum + 0.05) {
                $diff = $totalRevenue - $totalItemRevenueSum;
                $revenueBreakdown[] = [
                    'label' => 'Khác',
                    'amount' => round($diff, 2),
                    'percentage' => $totalRevenue > 0 ? round(($diff / $totalRevenue) * 100, 1) : 0.0,
                ];
            }

            usort($revenueBreakdown, fn($a, $b) => $b['amount'] <=> $a['amount']);

            // 4. Cơ cấu chi phí theo danh mục (ExpenseCategory)
            $expensesQuery = $this->scopedExpenseQuery($buildingIds, $isSystemWide)
                ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->with('category')
                ->get();

            $categoryExpenseMap = [];
            foreach ($expensesQuery as $expense) {
                $catName = $expense->category?->name ?? 'Chưa phân loại';
                if (!isset($categoryExpenseMap[$catName])) {
                    $categoryExpenseMap[$catName] = 0.0;
                }
                $categoryExpenseMap[$catName] += (float) $expense->amount;
            }

            $expenseBreakdown = [];
            foreach ($categoryExpenseMap as $label => $amount) {
                if ($amount == 0) continue;
                $expenseBreakdown[] = [
                    'label' => $label,
                    'amount' => round($amount, 2),
                    'percentage' => $totalExpenses > 0 ? round(($amount / $totalExpenses) * 100, 1) : 0.0,
                ];
            }
            usort($expenseBreakdown, fn($a, $b) => $b['amount'] <=> $a['amount']);
            
            // 4.5. Cơ cấu doanh thu theo từng tòa nhà (chỉ lọc trong buildingIds)
            // Lấy từ Payment
            $buildingRevenues = Payment::query()
                ->where('payments.status', Payment::STATUS_CONFIRMED)
                ->where(function (Builder $query): void {
                    $query->where('payments.is_internal_allocation', false)
                        ->orWhereNull('payments.is_internal_allocation');
                })
                ->whereBetween('payments.payment_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->join('invoices', 'payments.invoice_id', '=', 'invoices.id')
                ->join('rooms', 'invoices.room_id', '=', 'rooms.id')
                ->join('buildings', 'rooms.building_id', '=', 'buildings.id')
                ->whereIn('buildings.id', $buildingIds)
                ->select('buildings.id', 'buildings.name', DB::raw('SUM(payments.amount) as revenue'))
                ->groupBy('buildings.id', 'buildings.name')
                ->get()
                ->keyBy('id')
                ->toArray();

            // Lấy từ Khấu trừ cọc
            $buildingDeductions = ContractDepositTransaction::query()
                ->where('transaction_type', ContractDepositTransaction::TRANSACTION_TYPE_DEDUCT)
                ->whereBetween('transaction_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->join('contracts', 'contract_deposit_transactions.contract_id', '=', 'contracts.id')
                ->join('rooms', 'contracts.room_id', '=', 'rooms.id')
                ->join('buildings', 'rooms.building_id', '=', 'buildings.id')
                ->whereIn('buildings.id', $buildingIds)
                ->select('buildings.id', 'buildings.name', DB::raw('SUM(contract_deposit_transactions.amount) as revenue'))
                ->groupBy('buildings.id', 'buildings.name')
                ->get();

            foreach ($buildingDeductions as $bd) {
                if (!isset($buildingRevenues[$bd->id])) {
                    $buildingRevenues[$bd->id] = ['id' => $bd->id, 'name' => $bd->name, 'revenue' => 0];
                }
                $buildingRevenues[$bd->id]['revenue'] += (float) $bd->revenue;
            }

            // (Không gom Phí chuyển phòng vào biểu đồ tòa nhà vì RoomMovement có thể liên quan tới 2 tòa nhà, ta gom vào tòa nhà đích)
            foreach ($roomMovements as $rm) {
                $refs = $rm->settlement_payment_references ?? [];
                if (is_array($refs)) {
                    foreach ($refs as $ref) {
                        if (!empty($ref['paid_at'])) {
                            $paidDate = Carbon::parse($ref['paid_at']);
                            if ($paidDate->between($startDate->copy()->startOfDay(), $endDate->copy()->endOfDay())) {
                                $bId = $rm->toRoom?->building_id;
                                if ($bId && in_array($bId, $buildingIds)) {
                                    if (!isset($buildingRevenues[$bId])) {
                                        $buildingRevenues[$bId] = ['id' => $bId, 'name' => $rm->toRoom->building->name ?? 'Tòa #'.$bId, 'revenue' => 0];
                                    }
                                    $buildingRevenues[$bId]['revenue'] += (float) ($ref['extra_amount'] ?? 0);
                                }
                            }
                        }
                    }
                }
            }

            usort($buildingRevenues, fn($a, $b) => $b['revenue'] <=> $a['revenue']);

            $topBuildings = [];
            foreach ($buildingRevenues as $br) {
                $topBuildings[] = [
                    'id' => (int) $br['id'],
                    'name' => $br['name'],
                    'revenue' => round((float) $br['revenue'], 2),
                    'percentage' => $totalRevenue > 0 ? round(($br['revenue'] / $totalRevenue) * 100, 1) : 0.0,
                ];
            }

            // 5. Gom nhóm dữ liệu trả về
            $data = [
                'summary' => $summary,
                'chart' => $chart,
                'revenue_breakdown' => $revenueBreakdown,
                'expense_breakdown' => $expenseBreakdown,
                'top_buildings' => $topBuildings,
            ];

            return ApiResponse::responseJson(true, 'Báo cáo lợi nhuận chi tiết', 200, $data, 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
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

    private function scopedPaymentQuery(array $buildingIds): Builder
    {
        $query = Payment::query()->realMoney()->where('status', Payment::STATUS_CONFIRMED);

        if (empty($buildingIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('invoice.room', fn(Builder $roomQuery): Builder => $roomQuery->whereIn('building_id', $buildingIds));
    }

    private function scopedExpenseQuery(array $buildingIds, bool $includeGlobalExpenses = false): Builder
    {
        $query = Expense::query()->where('status', Expense::STATUS_RECORDED);

        if (empty($buildingIds)) {
            return $includeGlobalExpenses ? $query : $query->whereRaw('1 = 0');
        }

        return $query->where(function(Builder $scopeQuery) use ($buildingIds, $includeGlobalExpenses): void {
            $scopeQuery->where(function($q) use ($buildingIds) {
                $q->whereIn('building_id', $buildingIds)
                  ->orWhereHas('room', fn(Builder $roomQuery): Builder => $roomQuery->whereIn('building_id', $buildingIds));
            })->whereHas('category', function(Builder $catQuery) {
                $catQuery->where('name', '!=', 'Hoàn cọc hợp đồng');
            });

            if ($includeGlobalExpenses) {
                $scopeQuery->orWhere(function(Builder $globalQuery): void {
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

        return $query->whereHas('contract.room', fn(Builder $roomQuery): Builder => $roomQuery->whereIn('building_id', $buildingIds));
    }

    private function scopedRoomMovementExtraChargeQuery(array $buildingIds): Builder
    {
        $query = RoomMovement::query()
            ->with(['toRoom.building'])
            ->whereIn('settlement_payment_status', [RoomMovement::SETTLEMENT_PAYMENT_STATUS_PARTIAL, RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID]);

        if (empty($buildingIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('toRoom', fn(Builder $roomQuery): Builder => $roomQuery->whereIn('building_id', $buildingIds));
    }
}
