import { apiRequest } from '../../../../shared/lib/api/api-client'
import type { AdminAccountFilters, AdminAccountPayload, AdminAccountResource, AdminAccountStatusPayload, AdminPaginator } from '../types/admin-account-api.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchAdminAccounts(params: AdminAccountFilters = {}) {
  return apiRequest<AdminPaginator<AdminAccountResource>>({
    url: `admin/accounts${buildQuery({ per_page: 10, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminAccountDetail(accountId: number) {
  return apiRequest<AdminAccountResource>({
    url: `admin/accounts/${accountId}`,
    method: 'GET',
  })
}

export async function createAdminAccount(payload: AdminAccountPayload) {
  return apiRequest<AdminAccountResource>({
    url: 'admin/accounts',
    method: 'POST',
    data: payload,
  })
}

export async function updateAdminAccount(accountId: number, payload: AdminAccountPayload) {
  return apiRequest<AdminAccountResource>({
    url: `admin/accounts/${accountId}`,
    method: 'PUT',
    data: payload,
  })
}

export async function updateAdminAccountStatus(accountId: number, payload: AdminAccountStatusPayload) {
  return apiRequest<AdminAccountResource>({
    url: `admin/accounts/${accountId}/status`,
    method: 'PATCH',
    data: payload,
  })
}

export async function deleteAdminAccount(accountId: number) {
  return apiRequest<null>({
    url: `admin/accounts/${accountId}`,
    method: 'DELETE',
  })
}
