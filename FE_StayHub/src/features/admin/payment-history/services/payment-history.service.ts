import { apiRequest } from '../../../../shared/lib/api/api-client'
import { confirmAdminInvoicePayment } from '../../invoices/services/invoices.service'
import type { AdminPaymentHistoryFilters, AdminPaymentHistoryPaginator, AdminPaymentHistoryRecord } from '../types/payment-history.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchAdminPaymentHistory(params: AdminPaymentHistoryFilters = {}) {
  return apiRequest<AdminPaymentHistoryPaginator<AdminPaymentHistoryRecord>>({
    url: `admin/payment-history${buildQuery({ per_page: 10, ...params })}`,
    method: 'GET',
  })
}

export async function confirmPaymentHistoryInvoicePayment(invoiceId: number, paymentId: number) {
  return confirmAdminInvoicePayment(invoiceId, paymentId)
}
