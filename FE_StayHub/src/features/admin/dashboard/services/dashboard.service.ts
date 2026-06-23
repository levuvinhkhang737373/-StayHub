import { apiRequest } from '../../../../shared/lib/api/api-client'

export interface UtilityReadingHistoryItem {
  month: string
  month_key?: string
  electric_consumption: number | null
  water_consumption: number | null
  electric_reading_count: number
  water_reading_count: number
}

export type UtilityPriceHistoryItem = UtilityReadingHistoryItem

export interface DashboardBuildingFilter {
  id: number
  name: string
  slug: string | null
  status: number
}

export interface DashboardMeta {
  role: number
  role_label: string
  scope: 'system' | 'building'
  scope_label: string
  selected_building_id: number | null
  year: number
  month_from: number
  month_to: number
  months: number
  period: {
    from: string
    to: string
  }
  generated_at: string
}

export interface DashboardMetric {
  label: string
  value: number
  previous_value: number | null
  change: number | null
  change_percent: number | null
  unit: 'money' | 'percent' | 'count'
  count?: number
  overdue_count?: number
}

export interface DashboardRevenuePoint {
  month: string
  month_key: string
  revenue: number
  expenses: number
  profit: number
}

export interface DashboardExpenseMonthPoint {
  month: string
  month_key: string
  amount: number
  count: number
}

export interface DashboardExpenseCategoryPoint {
  label: string
  amount: number
  count: number
}

export interface DashboardExpenseChart {
  summary: {
    total_amount: number
    count: number
    average_amount: number
  }
  by_month: DashboardExpenseMonthPoint[]
  by_category: DashboardExpenseCategoryPoint[]
}

export interface DashboardOccupancyItem {
  label: string
  total_rooms: number
  current_occupants: number
  total_capacity: number
  available_slots: number
  occupancy_rate: number
}

export interface DashboardOccupancyChart {
  mode: 'building' | 'floor'
  summary: {
    total_rooms: number
    occupied_rooms: number
    full_rooms: number
    total_capacity: number
    current_occupants: number
    available_slots: number
  }
  occupancy_rate: number
  items: DashboardOccupancyItem[]
}

export interface DashboardInvoiceStatusItem {
  status: number
  label: string
  count: number
  total_amount: number
  remaining_amount: number
}

export interface DashboardMaintenanceStatusItem {
  status: number
  label: string
  count: number
}

export interface DashboardContractExpirationItem {
  label: string
  days: number
  count: number
}

export interface DashboardRecentActivity {
  type: 'payment' | 'maintenance' | 'contract' | 'invoice'
  label: string
  title: string
  description: string
  amount?: number
  status?: number
  status_label?: string
  occurred_at: string
  href: string
}

export interface DashboardOverview {
  meta: DashboardMeta
  filters: {
    buildings: DashboardBuildingFilter[]
  }
  kpis: {
    monthly_revenue: DashboardMetric
    monthly_profit: DashboardMetric
    occupancy_rate: DashboardMetric
    renting_tenants: DashboardMetric
    outstanding_debt: DashboardMetric
    open_maintenance: DashboardMetric
  }
  revenue_chart: DashboardRevenuePoint[]
  expense_chart: DashboardExpenseChart
  occupancy_chart: DashboardOccupancyChart
  invoice_status_chart: DashboardInvoiceStatusItem[]
  maintenance_status_chart: DashboardMaintenanceStatusItem[]
  contract_expiration_chart: DashboardContractExpirationItem[]
  utility_price_chart: UtilityReadingHistoryItem[]
  recent_activities: DashboardRecentActivity[]
}

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, String(value))
  })
  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchDashboardOverview(params: {
  building_id?: number | null
  months?: number
  year?: number
  month_from?: number
  month_to?: number
}) {
  return apiRequest<DashboardOverview>({
    url: `admin/dashboard/overview${buildQuery(params)}`,
    method: 'GET',
  })
}

export async function fetchUtilityPriceHistory(params: {
  building_id?: number
  months?: number
}) {
  return apiRequest<UtilityReadingHistoryItem[]>({
    url: `admin/dashboard/utility-price-history${buildQuery(params)}`,
    method: 'GET',
  })
}
