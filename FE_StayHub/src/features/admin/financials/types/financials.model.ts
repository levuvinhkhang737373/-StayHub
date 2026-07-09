export interface FinancialReportSummary {
  revenue: number
  collected_revenue?: number
  debt: number
  outstanding_debt?: number
  current_debt?: number
  rolled_debt?: number
  expected_revenue?: number
  expenses: number
  profit: number
  profit_margin: number
  expected_profit?: number
  expected_profit_margin?: number
}

export interface FinancialReportChartPoint {
  month: string
  month_key: string
  revenue: number
  collected_revenue?: number
  debt: number
  outstanding_debt?: number
  current_debt?: number
  rolled_debt?: number
  expected_revenue?: number
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
  debt?: number
  outstanding_debt?: number
  expected_revenue?: number
  percentage: number
  debt_percentage?: number
  expected_percentage?: number
}

export interface FinancialReportData {
  summary: FinancialReportSummary
  chart: FinancialReportChartPoint[]
  revenue_breakdown: FinancialBreakdownItem[]
  expense_breakdown: FinancialBreakdownItem[]
  debt_breakdown?: FinancialBreakdownItem[]
  top_buildings?: FinancialBuildingRevenueItem[]
}
