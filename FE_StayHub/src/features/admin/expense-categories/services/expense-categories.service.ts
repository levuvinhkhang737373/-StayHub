import { apiRequest } from '../../../../shared/lib/api/api-client'
import type { AdminExpenseCategoryPayload, AdminExpenseCategoryResource } from '../types/expense-category-api.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchAdminExpenseCategories(
  params: {
    keyword?: string
    is_active?: boolean
    status?: boolean
    per_page?: number
  } = {},
) {
  return apiRequest<AdminExpenseCategoryResource[]>({
    url: `admin/expense-categories${buildQuery({ per_page: 100, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminExpenseCategoryDetail(expenseCategoryId: number) {
  return apiRequest<AdminExpenseCategoryResource>({
    url: `admin/expense-categories/${expenseCategoryId}`,
    method: 'GET',
  })
}

export async function createAdminExpenseCategory(payload: AdminExpenseCategoryPayload) {
  return apiRequest<AdminExpenseCategoryResource>({
    url: 'admin/expense-categories',
    method: 'POST',
    data: payload,
  })
}

export async function updateAdminExpenseCategory(expenseCategoryId: number, payload: AdminExpenseCategoryPayload) {
  return apiRequest<AdminExpenseCategoryResource>({
    url: `admin/expense-categories/${expenseCategoryId}`,
    method: 'PUT',
    data: payload,
  })
}

export async function updateAdminExpenseCategoryStatus(expenseCategoryId: number, status: boolean) {
  return apiRequest<AdminExpenseCategoryResource>({
    url: `admin/expense-categories/${expenseCategoryId}/status`,
    method: 'PATCH',
    data: { status },
  })
}

export async function deleteAdminExpenseCategory(expenseCategoryId: number) {
  return apiRequest<null>({
    url: `admin/expense-categories/${expenseCategoryId}`,
    method: 'DELETE',
  })
}
