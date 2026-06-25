export interface AdminInvoicePaginationMeta {
  current_page?: number
  from?: number | null
  last_page?: number
  path?: string
  per_page?: number
  to?: number | null
  total?: number
}

export interface AdminPaginator<T> {
  data: T[]
  links?: unknown
  meta?: AdminInvoicePaginationMeta | null
}

export interface AdminInvoiceItemResource {
  id: number
  invoice_id?: number
  service_id?: number | null
  service_name?: string | null
  meter_reading_id?: number | null
  meter_reading?: AdminInvoiceMeterReadingSummary | null
  item_type: number
  item_type_label?: string | null
  description: string
  quantity: string
  unit_price: string
  amount: string
}

export interface AdminInvoiceMeterReadingSummary {
  id: number
  meter_device_id?: number | null
  previous_reading?: string | number | null
  current_reading?: string | number | null
  consumption?: string | number | null
  reading_date?: string | null
  image_url?: string | null
}

export interface AdminInvoicePreviewItemResource extends Omit<AdminInvoiceItemResource, 'id' | 'invoice_id'> {
  id: number | null
  invoice_id?: number | null
}

export interface AdminPaymentResource {
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
  collected_by?: number | null
  collector_name?: string | null
  created_at?: string | null
}

export interface AdminInvoiceTenantSummary {
  id?: number | null
  full_name?: string | null
  phone?: string | null
  email?: string | null
  is_staying?: boolean
}

export interface AdminInvoiceRoomSummary {
  id: number
  building_id?: number | null
  building_name?: string | null
  room_number?: string | null
  floor?: number | null
  status?: number | null
}

export interface AdminInvoiceResource {
  id: number
  invoice_code: string
  contract_id: number
  contract_code?: string | null
  room_id: number
  room_number?: string | null
  building_id?: number | null
  building_name?: string | null
  tenant_name?: string | null
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
  revision?: number | null
  reissued_at?: string | null
  reissue_reason?: string | null
  created_by?: number | null
  updated_by?: number | null
  creator_name?: string | null
  updater_name?: string | null
  payment_qr_url?: string | null
  room?: AdminInvoiceRoomSummary | null
  tenants?: AdminInvoiceTenantSummary[]
  items?: AdminInvoiceItemResource[]
  payments?: AdminPaymentResource[]
  items_count?: number
  payments_count?: number
  created_at?: string | null
  updated_at?: string | null
}

export interface AdminInvoicePreviewResource extends Omit<AdminInvoiceResource, 'id' | 'invoice_code' | 'items' | 'payments'> {
  is_preview: true
  can_issue: boolean
  id: number | null
  invoice_code: string | null
  invoice_code_note?: string | null
  items?: AdminInvoicePreviewItemResource[]
  payments: []
  preview_generated_at?: string | null
}

export interface AdminInvoiceFilters {
  keyword?: string
  status?: number
  building_id?: number
  room_id?: number
  contract_id?: number
  billing_month?: number
  billing_year?: number
  page?: number
  per_page?: number
}

export interface AdminInvoiceAdjustmentPayload {
  item_type: number
  description: string
  quantity?: string
  unit_price: string
}

export interface AdminInvoiceGeneratePayload {
  contract_id: number
  billing_month: number
  billing_year: number
  due_date?: string | null
  adjustments?: AdminInvoiceAdjustmentPayload[]
}

export interface AdminInvoiceUpdatePayload {
  reason: string
  due_date?: string | null
  meter_readings?: AdminInvoiceMeterReadingUpdatePayload[]
  adjustments?: AdminInvoiceAdjustmentPayload[]
}

export interface AdminInvoiceMeterReadingUpdatePayload {
  meter_reading_id: number
  current_reading: string
  reading_date?: string | null
  note?: string | null
}

export interface AdminInvoicePaymentPayload {
  amount: string
  payment_date?: string | null
  payment_method: number
  transaction_reference?: string | null
  note?: string | null
  proof_image?: File | null
}

export const INVOICE_STATUS_UNPAID = 2
export const INVOICE_STATUS_PARTIALLY_PAID = 3
export const INVOICE_STATUS_PAID = 4
export const INVOICE_STATUS_OVERDUE = 5
export const INVOICE_STATUS_CANCELLED = 6

export const PAYMENT_METHOD_CASH = 1
export const PAYMENT_METHOD_BANK_TRANSFER = 2

export const ITEM_TYPE_SURCHARGE = 7
export const ITEM_TYPE_DISCOUNT = 8
export const ITEM_TYPE_ADJUST_INCREASE = 10
export const ITEM_TYPE_ADJUST_DECREASE = 11
