import { apiRequest } from '../../../../shared/lib/api/api-client'
import type { FinancialReportData } from '../types/financials.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, String(value))
  })
  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchFinancialReport(params: {
  year: number
  month_from: number
  month_to: number
  building_id?: number | null
}) {
  return apiRequest<FinancialReportData>({
    url: `admin/financials/report${buildQuery(params)}`,
    method: 'GET',
  })
}
