<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\DecimalMoney;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dashboard\OverviewRequest;
use App\Http\Resources\Admin\DashboardOverviewResource;
use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\ContractDepositTransaction;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\MeterDevice;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomMovement;
use App\Models\Service;
use App\Models\Tenant;
use App\Services\Invoice\InvoiceDebtRolloverService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private readonly InvoiceDebtRolloverService $debtRolloverService) {}

    public function overview(OverviewRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');
            $year = (int) ($validated['year'] ?? Carbon::now()->year);
            $monthFrom = (int) ($validated['month_from'] ?? 1);
            $monthTo = (int) ($validated['month_to'] ?? ($year === Carbon::now()->year ? Carbon::now()->month : 12));
            $monthsCount = $monthTo - $monthFrom + 1;
            $requestedBuildingId = isset($validated['building_id']) ? (int) $validated['building_id'] : null;

            $availableBuildings = $this->availableBuildings($admin);

            if ($requestedBuildingId && ! $availableBuildings->contains('id', $requestedBuildingId)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền quản lý tòa nhà này', 403, null, 403);
            }

            $selectedBuildingId = $this->selectedBuildingId($admin, $availableBuildings, $requestedBuildingId);
            $buildingIds = $selectedBuildingId
                ? [$selectedBuildingId]
                : $availableBuildings->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
            $selectedBuilding = $selectedBuildingId
                ? $availableBuildings->firstWhere('id', $selectedBuildingId)
                : null;
            $isSystemWide = AdminScope::isSuperAdmin($admin) && $selectedBuildingId === null;

            $monthRange = $this->monthRange($year, $monthFrom, $monthTo);

            if ($monthFrom === $monthTo) {
                $currentStart = Carbon::create($year, $monthTo)->startOfMonth();
                $currentEnd = $currentStart->copy()->endOfMonth();
                $previousStart = $currentStart->copy()->subMonthNoOverflow()->startOfMonth();
                $previousEnd = $previousStart->copy()->endOfMonth();
                $revenueLabel = 'Doanh thu tháng đang xem';
                $profitLabel = 'Lợi nhuận tháng đang xem';
            } else {
                $currentStart = Carbon::create($year, $monthFrom)->startOfMonth();
                $currentEnd = Carbon::create($year, $monthTo)->endOfMonth();
                $previousStart = Carbon::create($year - 1, $monthFrom)->startOfMonth();
                $previousEnd = Carbon::create($year - 1, $monthTo)->endOfMonth();
                $revenueLabel = 'Doanh thu kỳ đang xem';
                $profitLabel = 'Lợi nhuận kỳ đang xem';
            }

            $roomMovements = $this->scopedRoomMovementExtraChargeQuery($buildingIds)->get();

            $currentFinancial = $this->financialTotals($buildingIds, $currentStart, $currentEnd, $isSystemWide, $roomMovements);
            $previousFinancial = $this->financialTotals($buildingIds, $previousStart, $previousEnd, $isSystemWide, $roomMovements);
            $occupancy = $this->occupancySummary($buildingIds, $isSystemWide, $selectedBuildingId);
            $debt = $this->debtSummary($buildingIds);
            $tenantCount = $this->tenantCount($buildingIds);
            $openMaintenanceCount = $this->openMaintenanceCount($buildingIds);

            $data = [
                'meta' => [
                    'role' => (int) $admin->role,
                    'role_label' => Admin::ROLE_LABELS[$admin->role] ?? 'Admin',
                    'scope' => $isSystemWide ? 'system' : 'building',
                    'scope_label' => $isSystemWide ? 'Toàn hệ thống' : ($selectedBuilding?->name ?? 'Chưa được gán tòa nhà'),
                    'selected_building_id' => $selectedBuildingId,
                    'year' => $year,
                    'month_from' => $monthFrom,
                    'month_to' => $monthTo,
                    'months' => $monthsCount,
                    'period' => [
                        'from' => $monthRange[0]['start']->toDateString(),
                        'to' => $monthRange[count($monthRange) - 1]['end']->toDateString(),
                    ],
                    'generated_at' => Carbon::now()->toDateTimeString(),
                ],
                'filters' => [
                    'buildings' => $availableBuildings->map(fn (Building $building): array => [
                        'id' => (int) $building->id,
                        'name' => $building->name,
                        'slug' => $building->slug,
                        'status' => (int) $building->status,
                    ])->values(),
                ],
                'kpis' => [
                    'monthly_revenue' => $this->metric('Doanh thu tháng đang xem', $currentFinancial['revenue'], $previousFinancial['revenue'], 'money'),
                    'monthly_profit' => $this->metric('Lợi nhuận tháng đang xem', $currentFinancial['profit'], $previousFinancial['profit'], 'money'),
                    'occupancy_rate' => $this->metric('Tỷ lệ lấp đầy', $occupancy['occupancy_rate'], null, 'percent'),
                    'renting_tenants' => $this->metric('Khách đang thuê', $tenantCount, null, 'count'),
                    'outstanding_debt' => $this->metric('Công nợ cần thu', $debt['amount'], null, 'money', [
                        'count' => $debt['count'],
                        'overdue_count' => $debt['overdue_count'],
                    ]),
                    'open_maintenance' => $this->metric('Bảo trì đang mở', $openMaintenanceCount, null, 'count'),
                ],
                'revenue_chart' => $this->revenueChart($buildingIds, $monthRange, $isSystemWide, $roomMovements),
                'expense_chart' => $this->expenseChart($buildingIds, $monthRange, $isSystemWide),
                'occupancy_chart' => $occupancy,
                'invoice_status_chart' => $this->invoiceStatusChart($buildingIds),
                'maintenance_status_chart' => $this->maintenanceStatusChart($buildingIds),
                'contract_expiration_chart' => $this->contractExpirationChart($buildingIds),
                'utility_price_chart' => $this->utilityReadingChart($buildingIds, $monthRange),
                'recent_activities' => $this->recentActivities($buildingIds),
            ];

            return ApiResponse::responseJson(true, 'Tổng quan dashboard admin', 200, new DashboardOverviewResource($data), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function utilityPriceHistory(Request $request): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Unauthorized', 401, null, 401);
            }

            $buildingId = $request->query('building_id');
            if ($buildingId) {
                $buildingId = (int) $buildingId;
                if (! AdminScope::ensureBuildingAccess($admin, $buildingId)) {
                    return ApiResponse::responseJson(false, 'Bạn không có quyền quản lý tòa nhà này', 403, null, 403);
                }
            } else {
                if (AdminScope::isSuperAdmin($admin)) {
                    $building = Building::query()->orderBy('id')->first();
                    $buildingId = $building ? $building->id : null;
                } else {
                    $managedIds = AdminScope::managedBuildingIds($admin);
                    $buildingId = ! empty($managedIds) ? $managedIds[0] : null;
                }
            }

            if (! $buildingId) {
                return ApiResponse::responseJson(true, 'Lịch sử chỉ số điện nước', 200, [], 200);
            }

            $monthsCount = $request->query('months', 6);
            if ($monthsCount < 2 || $monthsCount > 24) {
                $monthsCount = 6;
            }

            return ApiResponse::responseJson(
                true,
                'Lịch sử chỉ số điện nước',
                200,
                $this->utilityReadingChart([(int) $buildingId], $this->rollingMonthRange((int) $monthsCount)),
                200
            );
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

    private function selectedBuildingId(Admin $admin, Collection $availableBuildings, ?int $requestedBuildingId): ?int
    {
        if (AdminScope::isSuperAdmin($admin)) {
            return $requestedBuildingId;
        }

        return $requestedBuildingId ?: $availableBuildings->first()?->id;
    }

    private function monthRange(int $year, int $monthFrom, int $monthTo): array
    {
        $months = [];

        for ($month = $monthFrom; $month <= $monthTo; $month++) {
            $start = Carbon::create($year, $month)->startOfMonth();
            $months[] = [
                'key' => $start->format('Y-m'),
                'label' => $start->format('m/Y'),
                'start' => $start,
                'end' => $start->copy()->endOfMonth(),
            ];
        }

        return $months;
    }

    private function rollingMonthRange(int $monthsCount): array
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $months = [];

        for ($index = $monthsCount - 1; $index >= 0; $index--) {
            $start = $currentMonth->copy()->subMonthsNoOverflow($index)->startOfMonth();
            $months[] = [
                'key' => $start->format('Y-m'),
                'label' => $start->format('m/Y'),
                'start' => $start,
                'end' => $start->copy()->endOfMonth(),
            ];
        }

        return $months;
    }

    private function metric(string $label, int|float $value, int|float|null $previousValue, string $unit, array $extra = []): array
    {
        $change = $previousValue === null ? null : $value - $previousValue;
        $changePercent = null;

        if ($previousValue !== null && abs((float) $previousValue) > 0) {
            $changePercent = round(((float) $change / abs((float) $previousValue)) * 100, 1);
        }

        return array_merge([
            'label' => $label,
            'value' => round((float) $value, 2),
            'previous_value' => $previousValue === null ? null : round((float) $previousValue, 2),
            'change' => $change === null ? null : round((float) $change, 2),
            'change_percent' => $changePercent,
            'unit' => $unit,
        ], $extra);
    }

    private function financialTotals(array $buildingIds, Carbon $start, Carbon $end, bool $includeGlobalExpenses = false, $roomMovements = null): array
    {
        $paymentRevenue = (float) $this->scopedPaymentQuery($buildingIds)
            ->whereBetween('payment_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->sum('amount');

        $deductionRevenue = (float) $this->scopedDepositDeductionQuery($buildingIds)
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');

        $extraRevenue = 0.0;
        if ($roomMovements === null) {
            $roomMovements = $this->scopedRoomMovementExtraChargeQuery($buildingIds)->get();
        }

        foreach ($roomMovements as $rm) {
            $refs = $rm->settlement_payment_references ?? [];
            if (is_array($refs)) {
                foreach ($refs as $ref) {
                    if (!empty($ref['paid_at'])) {
                        $paidDate = Carbon::parse($ref['paid_at']);
                        if ($paidDate->between($start->copy()->startOfDay(), $end->copy()->endOfDay())) {
                            $extraRevenue += (float) ($ref['extra_amount'] ?? 0);
                        }
                    }
                }
            }
        }

        $revenue = $paymentRevenue + $deductionRevenue + $extraRevenue;

        $expenses = (float) $this->scopedExpenseQuery($buildingIds, $includeGlobalExpenses)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');

        return [
            'revenue' => round($revenue, 2),
            'expenses' => round($expenses, 2),
            'profit' => round($revenue - $expenses, 2),
        ];
    }

    private function revenueChart(array $buildingIds, array $monthRange, bool $includeGlobalExpenses, $roomMovements = null): array
    {
        return collect($monthRange)->map(function (array $month) use ($buildingIds, $includeGlobalExpenses, $roomMovements): array {
            $totals = $this->financialTotals($buildingIds, $month['start'], $month['end'], $includeGlobalExpenses, $roomMovements);

            return [
                'month' => $month['label'],
                'month_key' => $month['key'],
                'revenue' => $totals['revenue'],
                'expenses' => $totals['expenses'],
                'profit' => $totals['profit'],
            ];
        })->values()->all();
    }

    private function expenseChart(array $buildingIds, array $monthRange, bool $includeGlobalExpenses): array
    {
        $periodStart = $monthRange[0]['start'];
        $periodEnd = $monthRange[count($monthRange) - 1]['end'];
        $expenses = $this->scopedExpenseQuery($buildingIds, $includeGlobalExpenses)
            ->with('category')
            ->whereBetween('expense_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get();

        $byMonth = collect($monthRange)->map(function (array $month) use ($buildingIds, $includeGlobalExpenses): array {
            $query = $this->scopedExpenseQuery($buildingIds, $includeGlobalExpenses)
                ->whereBetween('expense_date', [$month['start']->toDateString(), $month['end']->toDateString()]);

            return [
                'month' => $month['label'],
                'month_key' => $month['key'],
                'amount' => round((float) (clone $query)->sum('amount'), 2),
                'count' => (clone $query)->count(),
            ];
        })->values()->all();

        $byCategory = $expenses
            ->groupBy(fn (Expense $expense): string => $expense->category?->name ?: 'Chưa phân loại')
            ->map(fn (Collection $group, string $label): array => [
                'label' => $label,
                'amount' => round((float) $group->sum('amount'), 2),
                'count' => $group->count(),
            ])
            ->sortByDesc('amount')
            ->take(8)
            ->values()
            ->all();

        return [
            'summary' => [
                'total_amount' => round((float) $expenses->sum('amount'), 2),
                'count' => $expenses->count(),
                'average_amount' => $expenses->count() > 0 ? round((float) $expenses->avg('amount'), 2) : 0,
            ],
            'by_month' => $byMonth,
            'by_category' => $byCategory,
        ];
    }

    private function occupancySummary(array $buildingIds, bool $isSystemWide, ?int $selectedBuildingId): array
    {
        $baseQuery = $this->scopedRoomQuery($buildingIds)->where('status', Room::STATUS_ACTIVE);
        $totalRooms = (clone $baseQuery)->count();
        $occupiedRooms = (clone $baseQuery)->where('current_occupants', '>', 0)->count();
        $fullRooms = (clone $baseQuery)->whereColumn('current_occupants', '>=', 'max_occupants')->count();
        $totalCapacity = (int) (clone $baseQuery)->sum('max_occupants');
        $currentOccupants = (int) (clone $baseQuery)->sum('current_occupants');
        $availableSlots = max(0, $totalCapacity - $currentOccupants);
        $rate = $totalCapacity > 0 ? round(($currentOccupants / $totalCapacity) * 100, 1) : 0.0;

        return [
            'mode' => $isSystemWide && ! $selectedBuildingId ? 'building' : 'floor',
            'summary' => [
                'total_rooms' => $totalRooms,
                'occupied_rooms' => $occupiedRooms,
                'full_rooms' => $fullRooms,
                'total_capacity' => $totalCapacity,
                'current_occupants' => $currentOccupants,
                'available_slots' => $availableSlots,
            ],
            'occupancy_rate' => $rate,
            'items' => $isSystemWide && ! $selectedBuildingId
                ? $this->occupancyByBuilding($buildingIds)
                : $this->occupancyByFloor($buildingIds),
        ];
    }

    private function occupancyByBuilding(array $buildingIds): array
    {
        if (empty($buildingIds)) {
            return [];
        }

        $buildingNames = Building::query()
            ->whereIn('id', $buildingIds)
            ->pluck('name', 'id');

        return Room::query()
            ->select([
                'building_id',
                DB::raw('COUNT(*) as total_rooms'),
                DB::raw('SUM(current_occupants) as current_occupants'),
                DB::raw('SUM(max_occupants) as total_capacity'),
            ])
            ->whereIn('building_id', $buildingIds)
            ->where('status', Room::STATUS_ACTIVE)
            ->groupBy('building_id')
            ->orderBy('building_id')
            ->get()
            ->map(fn ($row): array => $this->occupancyItem(
                (string) ($buildingNames[$row->building_id] ?? 'Tòa #'.$row->building_id),
                (int) $row->total_rooms,
                (int) $row->current_occupants,
                (int) $row->total_capacity
            ))
            ->values()
            ->all();
    }

    private function occupancyByFloor(array $buildingIds): array
    {
        if (empty($buildingIds)) {
            return [];
        }

        return Room::query()
            ->select([
                'floor',
                DB::raw('COUNT(*) as total_rooms'),
                DB::raw('SUM(current_occupants) as current_occupants'),
                DB::raw('SUM(max_occupants) as total_capacity'),
            ])
            ->whereIn('building_id', $buildingIds)
            ->where('status', Room::STATUS_ACTIVE)
            ->groupBy('floor')
            ->orderByRaw('floor IS NULL, floor ASC')
            ->get()
            ->map(fn ($row): array => $this->occupancyItem(
                $row->floor === null ? 'Chưa phân tầng' : 'Tầng '.$row->floor,
                (int) $row->total_rooms,
                (int) $row->current_occupants,
                (int) $row->total_capacity
            ))
            ->values()
            ->all();
    }

    private function occupancyItem(string $label, int $totalRooms, int $currentOccupants, int $totalCapacity): array
    {
        return [
            'label' => $label,
            'total_rooms' => $totalRooms,
            'current_occupants' => $currentOccupants,
            'total_capacity' => $totalCapacity,
            'available_slots' => max(0, $totalCapacity - $currentOccupants),
            'occupancy_rate' => $totalCapacity > 0 ? round(($currentOccupants / $totalCapacity) * 100, 1) : 0.0,
        ];
    }

    private function tenantCount(array $buildingIds): int
    {
        if (empty($buildingIds)) {
            return 0;
        }

        return Tenant::query()
            ->where('status', Tenant::STATUS_RENTING)
            ->where(function (Builder $query) use ($buildingIds): void {
                $query->whereIn('building_id', $buildingIds)
                    ->orWhereHas('contractTenants.contract.room', fn (Builder $roomQuery): Builder => $roomQuery->whereIn('building_id', $buildingIds));
            })
            ->distinct('tenants.id')
            ->count('tenants.id');
    }

    private function debtSummary(array $buildingIds): array
    {
        $debtInvoices = $this->scopedInvoiceQuery($buildingIds)
            ->with('debtRolloversOut.targetInvoice:id,status')
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_OVERDUE])
            ->where('remaining_amount', '>', 0)
            ->get()
            ->map(function (Invoice $invoice): array {
                return [
                    'invoice' => $invoice,
                    'collectible_amount' => $this->debtRolloverService->collectibleRemainingAmount($invoice),
                ];
            })
            ->filter(fn (array $row): bool => DecimalMoney::isPositive($row['collectible_amount']))
            ->values();

        $overdueCount = $debtInvoices
            ->filter(fn (array $row): bool => (int) $row['invoice']->status === Invoice::STATUS_OVERDUE
                || ($row['invoice']->due_date && $row['invoice']->due_date->copy()->startOfDay()->lt(Carbon::today())))
            ->count();

        return [
            'amount' => round((float) DecimalMoney::add($debtInvoices->pluck('collectible_amount')->all()), 2),
            'count' => $debtInvoices->count(),
            'overdue_count' => $overdueCount,
        ];
    }

    private function invoiceStatusChart(array $buildingIds): array
    {
        $rows = $this->scopedInvoiceQuery($buildingIds)
            ->with('debtRolloversOut.targetInvoice:id,status')
            ->get()
            ->groupBy('status')
            ->map(function (Collection $invoices): array {
                return [
                    'total' => $invoices->count(),
                    'total_amount' => DecimalMoney::add($invoices->pluck('total_amount')->all()),
                    'remaining_amount' => DecimalMoney::add($invoices
                        ->map(fn (Invoice $invoice): string => $this->debtRolloverService->collectibleRemainingAmount($invoice))
                        ->all()),
                ];
            });

        return collect(Invoice::STATUS_LABELS)->map(fn (string $label, int $status): array => [
            'status' => $status,
            'label' => $label,
            'count' => (int) ($rows[$status]['total'] ?? 0),
            'total_amount' => round((float) ($rows[$status]['total_amount'] ?? 0), 2),
            'remaining_amount' => round((float) ($rows[$status]['remaining_amount'] ?? 0), 2),
        ])->values()->all();
    }

    private function openMaintenanceCount(array $buildingIds): int
    {
        return $this->scopedMaintenanceQuery($buildingIds)
            ->whereIn('status', [
                MaintenanceRequest::STATUS_CREATED,
                MaintenanceRequest::STATUS_RECEIVED,
                MaintenanceRequest::STATUS_PROCESSING,
            ])
            ->count();
    }

    private function maintenanceStatusChart(array $buildingIds): array
    {
        $labels = $this->maintenanceLabels();
        $rows = $this->scopedMaintenanceQuery($buildingIds)
            ->select(['status', DB::raw('COUNT(*) as total')])
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return collect($labels)->map(fn (string $label, int $status): array => [
            'status' => $status,
            'label' => $label,
            'count' => (int) ($rows[$status]->total ?? 0),
        ])->values()->all();
    }

    private function maintenanceLabels(): array
    {
        return [
            MaintenanceRequest::STATUS_CREATED => 'Mới tạo',
            MaintenanceRequest::STATUS_RECEIVED => 'Đã tiếp nhận',
            MaintenanceRequest::STATUS_PROCESSING => 'Đang xử lý',
            MaintenanceRequest::STATUS_COMPLETED => 'Đã hoàn thành',
            MaintenanceRequest::STATUS_CANCELLED => 'Đã hủy',
        ];
    }

    private function contractExpirationChart(array $buildingIds): array
    {
        $today = Carbon::today();
        $inSevenDays = $today->copy()->addDays(7);
        $inFifteenDays = $today->copy()->addDays(15);
        $inThirtyDays = $today->copy()->addDays(30);
        $query = $this->scopedContractQuery($buildingIds)
            ->where('status', Contract::STATUS_ACTIVE)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', $today)
            ->whereDate('end_date', '<=', $inThirtyDays);

        return [
            [
                'label' => '0-7 ngày',
                'days' => 7,
                'count' => (clone $query)->whereDate('end_date', '<=', $inSevenDays)->count(),
            ],
            [
                'label' => '8-15 ngày',
                'days' => 15,
                'count' => (clone $query)->whereDate('end_date', '>', $inSevenDays)->whereDate('end_date', '<=', $inFifteenDays)->count(),
            ],
            [
                'label' => '16-30 ngày',
                'days' => 30,
                'count' => (clone $query)->whereDate('end_date', '>', $inFifteenDays)->whereDate('end_date', '<=', $inThirtyDays)->count(),
            ],
        ];
    }

    private function utilityReadingChart(array $buildingIds, array $monthRange): array
    {
        if (empty($buildingIds) || empty($monthRange)) {
            return [];
        }

        $startMonth = $monthRange[0]['start'];
        $endMonth = $monthRange[count($monthRange) - 1]['start'];
        $startPeriod = ((int) $startMonth->format('Y')) * 100 + (int) $startMonth->format('m');
        $endPeriod = ((int) $endMonth->format('Y')) * 100 + (int) $endMonth->format('m');

        $rows = MeterReading::query()
            ->join('meter_devices', 'meter_readings.meter_device_id', '=', 'meter_devices.id')
            ->join('rooms', 'meter_devices.room_id', '=', 'rooms.id')
            ->whereIn('rooms.building_id', $buildingIds)
            ->whereIn('meter_devices.meter_type', [MeterDevice::METER_TYPE_ELECTRIC, MeterDevice::METER_TYPE_WATER])
            ->whereIn('meter_readings.status', [MeterReading::STATUS_CONFIRMED, MeterReading::STATUS_INVOICED])
            ->whereRaw('(meter_readings.billing_year * 100 + meter_readings.billing_month) BETWEEN ? AND ?', [$startPeriod, $endPeriod])
            ->selectRaw('meter_readings.billing_year, meter_readings.billing_month, meter_devices.meter_type, SUM(meter_readings.consumption) as total_consumption, COUNT(*) as readings_count')
            ->groupBy('meter_readings.billing_year', 'meter_readings.billing_month', 'meter_devices.meter_type')
            ->get()
            ->keyBy(fn ($row): string => sprintf('%04d-%02d-%d', (int) $row->billing_year, (int) $row->billing_month, (int) $row->meter_type));

        $electricService = \App\Models\Service::whereIn('slug', ['electric', 'dien-sinh-hoat', 'dien'])->first();
        $waterService = \App\Models\Service::whereIn('slug', ['water', 'nuoc-sinh-hoat', 'nuoc'])->first();

        $servicePrices = collect();
        if ($electricService && $waterService) {
            $servicePrices = \App\Models\ServicePrice::query()
                ->whereIn('building_id', $buildingIds)
                ->whereIn('service_id', [$electricService->id, $waterService->id])
                ->whereIn('status', [\App\Models\ServicePrice::STATUS_ACTIVE, \App\Models\ServicePrice::STATUS_EXPIRED])
                ->orderBy('effective_from', 'desc')
                ->orderBy('id', 'desc')
                ->get();
        }

        return collect($monthRange)->map(function (array $month) use ($rows, $electricService, $waterService, $servicePrices): array {
            $electricPrice = null;
            $waterPrice = null;

            if ($electricService) {
                $ePriceRecord = $servicePrices->first(function ($price) use ($electricService, $month) {
                    if ($price->service_id !== $electricService->id) {
                        return false;
                    }
                    $effectiveFrom = Carbon::parse($price->effective_from)->startOfDay();
                    $effectiveTo = $price->effective_to ? Carbon::parse($price->effective_to)->endOfDay() : null;
                    return $effectiveFrom->lessThanOrEqualTo($month['end']) && 
                           ($effectiveTo === null || $effectiveTo->greaterThanOrEqualTo($month['start']));
                });
                $electricPrice = $ePriceRecord ? (float) $ePriceRecord->price : null;
            }

            if ($waterService) {
                $wPriceRecord = $servicePrices->first(function ($price) use ($waterService, $month) {
                    if ($price->service_id !== $waterService->id) {
                        return false;
                    }
                    $effectiveFrom = Carbon::parse($price->effective_from)->startOfDay();
                    $effectiveTo = $price->effective_to ? Carbon::parse($price->effective_to)->endOfDay() : null;
                    return $effectiveFrom->lessThanOrEqualTo($month['end']) && 
                           ($effectiveTo === null || $effectiveTo->greaterThanOrEqualTo($month['start']));
                });
                $waterPrice = $wPriceRecord ? (float) $wPriceRecord->price : null;
            }

            return [
                'month' => $month['label'],
                'month_key' => $month['key'],
                'electric_consumption' => $this->meterConsumptionValue($rows->get($month['key'].'-'.MeterDevice::METER_TYPE_ELECTRIC)),
                'water_consumption' => $this->meterConsumptionValue($rows->get($month['key'].'-'.MeterDevice::METER_TYPE_WATER)),
                'electric_reading_count' => (int) ($rows->get($month['key'].'-'.MeterDevice::METER_TYPE_ELECTRIC)?->readings_count ?? 0),
                'water_reading_count' => (int) ($rows->get($month['key'].'-'.MeterDevice::METER_TYPE_WATER)?->readings_count ?? 0),
                'electric_price' => $electricPrice,
                'water_price' => $waterPrice,
            ];
        })->values()->all();
    }

    private function meterConsumptionValue(mixed $row): ?float
    {
        return $row ? round((float) $row->total_consumption, 2) : null;
    }

    private function recentActivities(array $buildingIds): array
    {
        if (empty($buildingIds)) {
            return [];
        }

        $activities = collect();

        $this->scopedPaymentQuery($buildingIds)
            ->with(['invoice.room.building'])
            ->orderByDesc('payment_date')
            ->limit(6)
            ->get()
            ->each(function (Payment $payment) use ($activities): void {
                $room = $payment->invoice?->room;
                $activities->push([
                    'type' => 'payment',
                    'label' => 'Thanh toán',
                    'title' => 'Đã xác nhận thanh toán '.$payment->payment_code,
                    'description' => trim(($room?->building?->name ? $room->building->name.' · ' : '').($room?->room_number ? 'Phòng '.$room->room_number : 'Hóa đơn #'.$payment->invoice_id)),
                    'amount' => round((float) $payment->amount, 2),
                    'occurred_at' => optional($payment->payment_date)->toDateTimeString(),
                    'href' => '/admin/invoices',
                ]);
            });

        $this->scopedMaintenanceQuery($buildingIds)
            ->with(['room.building'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get()
            ->each(function (MaintenanceRequest $maintenance) use ($activities): void {
                $activities->push([
                    'type' => 'maintenance',
                    'label' => 'Bảo trì',
                    'title' => $maintenance->title,
                    'description' => trim(($maintenance->room?->building?->name ? $maintenance->room->building->name.' · ' : '').($maintenance->room?->room_number ? 'Phòng '.$maintenance->room->room_number : 'Chưa rõ phòng')),
                    'status' => (int) $maintenance->status,
                    'status_label' => $this->maintenanceLabels()[(int) $maintenance->status] ?? 'Bảo trì',
                    'occurred_at' => optional($maintenance->created_at)->toDateTimeString(),
                    'href' => '/admin/maintenance',
                ]);
            });

        $this->scopedContractQuery($buildingIds)
            ->with(['room.building'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get()
            ->each(function (Contract $contract) use ($activities): void {
                $activities->push([
                    'type' => 'contract',
                    'label' => 'Hợp đồng',
                    'title' => 'Hợp đồng '.$contract->contract_code,
                    'description' => trim(($contract->room?->building?->name ? $contract->room->building->name.' · ' : '').($contract->room?->room_number ? 'Phòng '.$contract->room->room_number : 'Chưa rõ phòng')),
                    'status' => (int) $contract->status,
                    'status_label' => Contract::STATUS_LABELS[(int) $contract->status] ?? 'Hợp đồng',
                    'occurred_at' => optional($contract->created_at)->toDateTimeString(),
                    'href' => '/admin/contracts',
                ]);
            });

        $this->scopedInvoiceQuery($buildingIds)
            ->with(['room.building', 'debtRolloversOut.targetInvoice:id,status'])
            ->where(function (Builder $query): void {
                $query->where('status', Invoice::STATUS_OVERDUE)
                    ->orWhere(function (Builder $dateQuery): void {
                        $dateQuery->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID])
                            ->whereDate('due_date', '<', Carbon::today())
                            ->where('remaining_amount', '>', 0);
                    });
            })
            ->orderByDesc('due_date')
            ->limit(6)
            ->get()
            ->filter(fn (Invoice $invoice): bool => DecimalMoney::isPositive($this->debtRolloverService->collectibleRemainingAmount($invoice)))
            ->each(function (Invoice $invoice) use ($activities): void {
                $activities->push([
                    'type' => 'invoice',
                    'label' => 'Quá hạn',
                    'title' => 'Hóa đơn '.$invoice->invoice_code.' quá hạn',
                    'description' => trim(($invoice->room?->building?->name ? $invoice->room->building->name.' · ' : '').($invoice->room?->room_number ? 'Phòng '.$invoice->room->room_number : 'Chưa rõ phòng')),
                    'amount' => round((float) $this->debtRolloverService->collectibleRemainingAmount($invoice), 2),
                    'occurred_at' => optional($invoice->due_date)->toDateString(),
                    'href' => '/admin/invoices',
                ]);
            });

        return $activities
            ->filter(fn (array $activity): bool => ! empty($activity['occurred_at']))
            ->sortByDesc('occurred_at')
            ->take(8)
            ->values()
            ->all();
    }

    private function scopedPaymentQuery(array $buildingIds): Builder
    {
        $query = Payment::query()->realMoney()->where('status', Payment::STATUS_CONFIRMED);

        if (empty($buildingIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('invoice.room', fn (Builder $roomQuery): Builder => $roomQuery->whereIn('building_id', $buildingIds));
    }

    private function scopedExpenseQuery(array $buildingIds, bool $includeGlobalExpenses = false): Builder
    {
        $query = Expense::query()->where('status', Expense::STATUS_RECORDED);

        if (empty($buildingIds)) {
            return $includeGlobalExpenses ? $query : $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scopeQuery) use ($buildingIds, $includeGlobalExpenses): void {
            $scopeQuery->where(function($q) use ($buildingIds) {
                $q->whereIn('building_id', $buildingIds)
                  ->orWhereHas('room', fn (Builder $roomQuery): Builder => $roomQuery->whereIn('building_id', $buildingIds));
            })->whereHas('category', function(Builder $catQuery) {
                $catQuery->where('name', '!=', 'Hoàn cọc hợp đồng');
            });

            if ($includeGlobalExpenses) {
                $scopeQuery->orWhere(function (Builder $globalQuery): void {
                    $globalQuery->whereNull('building_id')->whereNull('room_id');
                });
            }
        });
    }

    private function scopedRoomQuery(array $buildingIds): Builder
    {
        $query = Room::query();

        return empty($buildingIds) ? $query->whereRaw('1 = 0') : $query->whereIn('building_id', $buildingIds);
    }

    private function scopedInvoiceQuery(array $buildingIds): Builder
    {
        $query = Invoice::query();

        if (empty($buildingIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('room', fn (Builder $roomQuery): Builder => $roomQuery->whereIn('building_id', $buildingIds));
    }

    private function scopedMaintenanceQuery(array $buildingIds): Builder
    {
        $query = MaintenanceRequest::query();

        if (empty($buildingIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('room', fn (Builder $roomQuery): Builder => $roomQuery->whereIn('building_id', $buildingIds));
    }

    private function scopedContractQuery(array $buildingIds): Builder
    {
        $query = Contract::query();

        if (empty($buildingIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('room', fn (Builder $roomQuery): Builder => $roomQuery->whereIn('building_id', $buildingIds));
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
