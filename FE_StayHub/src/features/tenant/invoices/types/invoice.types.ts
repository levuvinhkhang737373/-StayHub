export interface TenantInvoicePaginationMeta {
  current_page?: number
  from?: number | null
  last_page?: number
  path?: string
  per_page?: number
  to?: number | null
  total?: number
}

export interface TenantPaginator<T> {
  data: T[]
  links?: unknown
  meta?: TenantInvoicePaginationMeta | null
}

export interface TenantInvoiceItemResource {
  id: number
  invoice_id?: number
  service_id?: number | null
  service_name?: string | null
  meter_reading_id?: number | null
  item_type: number
  item_type_label?: string | null
  description: string
  quantity: string
  unit_price: string
  amount: string
}

export interface TenantPaymentResource {
  id: number
  payment_code: string
  invoice_id?: number
  amount: string
  payment_date?: string | null
  payment_method: number
  payment_method_label?: string | null
  transaction_reference?: string | null
  status: number
  status_label?: string | null
  proof_image?: string | null
  proof_image_url?: string | null
  note?: string | null
  created_at?: string | null
}

export interface TenantInvoiceRoomSummary {
  id: number
  building_id?: number | null
  building_name?: string | null
  room_number?: string | null
}

export interface TenantInvoiceResource {
  id: number
  invoice_code: string
  contract_id: number
  contract_code?: string | null
  room_id: number
  room_number?: string | null
  building_id?: number | null
  building_name?: string | null
  billing_month: number
  billing_year: number
  period_start?: string | null
  period_end?: string | null
  previous_debt_amount?: string | null
  total_amount: string
  paid_amount: string
  remaining_amount: string
  due_date?: string | null
  status: number
  status_label?: string | null
  issued_at?: string | null
  payment_qr_url?: string | null
  room?: TenantInvoiceRoomSummary | null
  items?: TenantInvoiceItemResource[]
  payments?: TenantPaymentResource[]
  created_at?: string | null
  updated_at?: string | null
}

export interface TenantInvoiceFilters {
  status?: number
  billing_month?: number
  billing_year?: number
  page?: number
  per_page?: number
}

export interface TenantInvoiceProofPayload {
  amount: string
  transaction_reference?: string | null
  note?: string | null
  proof_image: File
}

export const INVOICE_STATUS_UNPAID = 2
export const INVOICE_STATUS_PARTIALLY_PAID = 3
export const INVOICE_STATUS_PAID = 4
export const INVOICE_STATUS_OVERDUE = 5
export const INVOICE_STATUS_CANCELLED = 6

export const PAYMENT_METHOD_CASH = 1
export const PAYMENT_METHOD_BANK_TRANSFER = 2
