import { apiRequest } from '../../../../shared/lib/api/api-client'
import type { AdminDebtFilters, AdminDebtPaginator, AdminDebtResource } from '../types/debt-api.model'

function cleanParams(filters: AdminDebtFilters) {
  return Object.fromEntries(
    Object.entries(filters).filter(([, value]) => value !== undefined && value !== null && value !== '' && value !== 'all'),
  )
}

export async function fetchAdminDebts(filters: AdminDebtFilters = {}) {
  return apiRequest<AdminDebtPaginator<AdminDebtResource>>({
    method: 'GET',
    url: 'admin/debts',
    params: cleanParams(filters),
  })
}
