import { apiRequest } from "../../../../shared/lib/api/api-client"

export interface UtilityPriceHistoryItem {
  month: string
  electric_price: number
  water_price: number
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

export async function fetchUtilityPriceHistory(params: {
  building_id?: number
  months?: number
}) {
  return apiRequest<UtilityPriceHistoryItem[]>({
    url: `admin/dashboard/utility-price-history${buildQuery(params)}`,
    method: 'GET',
  })
}
