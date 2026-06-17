import { apiRequest } from '../../../../shared/lib/api/api-client'
import type {
  AdminInvoiceFilters,
  AdminInvoiceGeneratePayload,
  AdminInvoicePaymentPayload,
  AdminInvoiceResource,
  AdminInvoiceUpdatePayload,
  AdminPaginator,
} from '../types/invoice-api.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

function toPaymentFormData(payload: AdminInvoicePaymentPayload) {
  const formData = new FormData()
  formData.append('amount', payload.amount)
  formData.append('payment_method', String(payload.payment_method))

  if (payload.payment_date) formData.append('payment_date', payload.payment_date)
  if (payload.transaction_reference) formData.append('transaction_reference', payload.transaction_reference)
  if (payload.note) formData.append('note', payload.note)
  if (payload.proof_image) formData.append('proof_image', payload.proof_image)

  return formData
}

export async function fetchAdminInvoices(params: AdminInvoiceFilters = {}) {
  return apiRequest<AdminPaginator<AdminInvoiceResource>>({
    url: `admin/invoices${buildQuery({ per_page: 10, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminInvoiceDetail(invoiceId: number) {
  return apiRequest<AdminInvoiceResource>({
    url: `admin/invoices/${invoiceId}`,
    method: 'GET',
  })
}

export async function generateAdminInvoice(payload: AdminInvoiceGeneratePayload) {
  return apiRequest<AdminInvoiceResource>({
    url: 'admin/invoices/generate',
    method: 'POST',
    data: payload,
  })
}

export async function updateAdminInvoice(invoiceId: number, payload: AdminInvoiceUpdatePayload) {
  return apiRequest<AdminInvoiceResource>({
    url: `admin/invoices/${invoiceId}`,
    method: 'PUT',
    data: payload,
  })
}

export async function issueAdminInvoice(invoiceId: number) {
  return apiRequest<AdminInvoiceResource>({
    url: `admin/invoices/${invoiceId}/issue`,
    method: 'POST',
  })
}

export async function recordAdminInvoicePayment(invoiceId: number, payload: AdminInvoicePaymentPayload) {
  return apiRequest<AdminInvoiceResource>({
    url: `admin/invoices/${invoiceId}/payments`,
    method: 'POST',
    data: payload.proof_image ? toPaymentFormData(payload) : payload,
  })
}

export async function confirmAdminInvoicePayment(invoiceId: number, paymentId: number) {
  return apiRequest<AdminInvoiceResource>({
    url: `admin/invoices/${invoiceId}/payments/${paymentId}/confirm`,
    method: 'POST',
  })
}

export async function cancelAdminInvoice(invoiceId: number, note?: string) {
  return apiRequest<AdminInvoiceResource>({
    url: `admin/invoices/${invoiceId}/cancel`,
    method: 'PATCH',
    data: { note: note || undefined },
  })
}
