<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Building;
use App\Models\Expense;
use App\Models\Invoice;
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

            // 2. Tính toán tổng quan từng tháng (Dùng cho Chart)
            foreach ($monthRange as $month) {
                $revenue = (float) $this->scopedPaymentQuery($buildingIds)
                    ->whereBetween('payment_date', [$month['start']->copy()->startOfDay(), $month['end']->copy()->endOfDay()])
                    ->sum('amount');

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

            $revenueBreakdown = [];
            $itemLabels = \App\Models\InvoiceItem::ITEM_TYPE_LABELS;

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
            $buildingRevenues = Payment::query()
                ->where('payments.status', Payment::STATUS_CONFIRMED)
                ->whereBetween('payments.payment_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->join('invoices', 'payments.invoice_id', '=', 'invoices.id')
                ->join('rooms', 'invoices.room_id', '=', 'rooms.id')
                ->join('buildings', 'rooms.building_id', '=', 'buildings.id')
                ->whereIn('buildings.id', $buildingIds)
                ->select('buildings.id', 'buildings.name', DB::raw('SUM(payments.amount) as revenue'))
                ->groupBy('buildings.id', 'buildings.name')
                ->orderByDesc('revenue')
                ->get();

            $topBuildings = [];
            foreach ($buildingRevenues as $br) {
                $topBuildings[] = [
                    'id' => (int) $br->id,
                    'name' => $br->name,
                    'revenue' => round((float) $br->revenue, 2),
                    'percentage' => $totalRevenue > 0 ? round(($br->revenue / $totalRevenue) * 100, 1) : 0.0,
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
        $query = Payment::query()->where('status', Payment::STATUS_CONFIRMED);

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
            $scopeQuery->whereIn('building_id', $buildingIds)
                ->orWhereHas('room', fn(Builder $roomQuery): Builder => $roomQuery->whereIn('building_id', $buildingIds));

            if ($includeGlobalExpenses) {
                $scopeQuery->orWhere(function(Builder $globalQuery): void {
                    $globalQuery->whereNull('building_id')->whereNull('room_id');
                });
            }
        });
    }
}
