import { apiRequest } from '../../../../shared/lib/api/api-client'
import type {
  TenantInvoiceFilters,
  TenantInvoiceProofPayload,
  TenantInvoiceResource,
  TenantPaginator,
} from '../types/invoice.types'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchTenantInvoices(params: TenantInvoiceFilters = {}) {
  return apiRequest<TenantPaginator<TenantInvoiceResource>>({
    url: `tenant/invoices${buildQuery({ per_page: 10, ...params })}`,
    method: 'GET',
  })
}

export async function fetchTenantInvoiceDetail(invoiceId: number) {
  return apiRequest<TenantInvoiceResource>({
    url: `tenant/invoices/${invoiceId}`,
    method: 'GET',
  })
}

export async function uploadTenantPaymentProof(invoiceId: number, payload: TenantInvoiceProofPayload) {
  const formData = new FormData()
  formData.append('amount', payload.amount)
  
  if (payload.transaction_reference) {
    formData.append('transaction_reference', payload.transaction_reference)
  }
  if (payload.note) {
    formData.append('note', payload.note)
  }
  formData.append('proof_image', payload.proof_image)

  return apiRequest<TenantInvoiceResource>({
    url: `tenant/invoices/${invoiceId}/payment-proof`,
    method: 'POST',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })
}
