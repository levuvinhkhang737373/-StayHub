import { apiRequest } from '../../../../shared/lib/api/api-client'
import type { AdminExpenseFilters, AdminExpensePaginator, AdminExpensePayload, AdminExpenseResource } from '../types/expense-api.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

function appendValue(formData: FormData, key: string, value: string | number | boolean | null | undefined) {
  if (value === undefined || value === null || value === '') return
  formData.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
}

function toExpenseFormData(payload: AdminExpensePayload, method?: 'PATCH') {
  const formData = new FormData()

  if (method) formData.append('_method', method)

  appendValue(formData, 'building_id', payload.building_id)
  appendValue(formData, 'room_id', payload.room_id)
  appendValue(formData, 'expense_category_id', payload.expense_category_id)
  appendValue(formData, 'title', payload.title)
  appendValue(formData, 'amount', payload.amount)
  appendValue(formData, 'expense_date', payload.expense_date)
  appendValue(formData, 'payment_method', payload.payment_method)
  appendValue(formData, 'note', payload.note)

  payload.receipt_images?.forEach((file) => formData.append('receipt_images[]', file))
  payload.deleted_receipt_images?.forEach((path) => formData.append('deleted_receipt_images[]', path))

  return formData
}

export async function fetchAdminExpenses(params: AdminExpenseFilters = {}) {
  return apiRequest<AdminExpensePaginator<AdminExpenseResource>>({
    url: `admin/expenses${buildQuery({ per_page: 10, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminExpenseDetail(expenseId: number) {
  return apiRequest<AdminExpenseResource>({
    url: `admin/expenses/${expenseId}`,
    method: 'GET',
  })
}

export async function createAdminExpense(payload: AdminExpensePayload) {
  return apiRequest<AdminExpenseResource>({
    url: 'admin/expenses',
    method: 'POST',
    data: toExpenseFormData(payload),
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })
}

export async function updateAdminExpense(expenseId: number, payload: AdminExpensePayload) {
  return apiRequest<AdminExpenseResource>({
    url: `admin/expenses/${expenseId}`,
    method: 'POST',
    data: toExpenseFormData(payload, 'PATCH'),
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })
}

export async function cancelAdminExpense(expenseId: number) {
  return apiRequest<AdminExpenseResource>({
    url: `admin/expenses/${expenseId}/cancel`,
    method: 'PATCH',
  })
}
