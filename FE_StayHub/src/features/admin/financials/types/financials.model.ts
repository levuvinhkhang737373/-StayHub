export interface FinancialReportSummary {
  revenue: number
  expenses: number
  profit: number
  profit_margin: number
}

export interface FinancialReportChartPoint {
  month: string
  month_key: string
  revenue: number
  expenses: number
  profit: number
}

export interface FinancialBreakdownItem {
  label: string
  amount: number
  percentage: number
}

export interface FinancialBuildingRevenueItem {
  id: number
  name: string
  revenue: number
  percentage: number
}

export interface FinancialReportData {
  summary: FinancialReportSummary
  chart: FinancialReportChartPoint[]
  revenue_breakdown: FinancialBreakdownItem[]
  expense_breakdown: FinancialBreakdownItem[]
  top_buildings?: FinancialBuildingRevenueItem[]
}
