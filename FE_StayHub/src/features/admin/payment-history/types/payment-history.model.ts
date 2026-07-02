export interface AdminPaymentHistoryPaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
  from?: number | null
  to?: number | null
}

export interface AdminPaymentHistoryPaginator<T> {
  data: T[]
  pagination?: AdminPaymentHistoryPaginationMeta | null
  meta?: AdminPaymentHistoryPaginationMeta | null
  summary?: AdminPaymentHistorySummary | null
}

export interface AdminPaymentHistorySummary {
  total_transactions: number
  total_in_amount: string
  total_out_amount: string
  pending_count: number
  by_source: Record<PaymentHistorySourceType, number>
}

export type PaymentHistorySourceType = 'invoice_payment' | 'deposit_transaction' | 'room_transfer'
export type PaymentHistoryStatusGroup = 'pending' | 'confirmed' | 'cancelled' | 'partial' | 'paid'
export type PaymentHistoryAmountDirection = 'in' | 'out' | 'adjustment'

export interface PaymentHistoryBuildingSummary {
  id: number
  name?: string | null
  slug?: string | null
}

export interface PaymentHistoryRoomSummary {
  id: number
  building_id?: number | null
  room_number?: string | null
  floor?: number | null
}

export interface PaymentHistoryContractSummary {
  id: number
  contract_code?: string | null
  room_id?: number | null
  status?: number | null
  payment_status?: number | null
}

export interface PaymentHistoryInvoiceSummary {
  id: number
  invoice_code?: string | null
  contract_id?: number | null
  room_id?: number | null
  billing_month?: number | null
  billing_year?: number | null
  status?: number | null
  total_amount?: string | null
  paid_amount?: string | null
  remaining_amount?: string | null
}

export interface PaymentHistoryTenantSummary {
  id?: number | null
  full_name?: string | null
  phone?: string | null
  email?: string | null
  is_staying?: boolean | null
}

export interface AdminPaymentHistoryRecord {
  uid: string
  source_type: PaymentHistorySourceType
  source_label?: string | null
  source_id: number
  event_date?: string | null
  amount: string
  signed_amount?: string | null
  amount_direction: PaymentHistoryAmountDirection
  payment_method?: number | null
  payment_method_label?: string | null
  status_group?: PaymentHistoryStatusGroup | null
  status_label?: string | null
  transaction_reference?: string | null
  code?: string | null
  building?: PaymentHistoryBuildingSummary | null
  room?: PaymentHistoryRoomSummary | null
  contract?: PaymentHistoryContractSummary | null
  invoice?: PaymentHistoryInvoiceSummary | null
  tenants?: PaymentHistoryTenantSummary[]
  actor_name?: string | null
  proof_image_url?: string | null
  note?: string | null
  can_confirm: boolean
  metadata?: Record<string, unknown>
}

export interface AdminPaymentHistoryFilters {
  keyword?: string
  source_type?: PaymentHistorySourceType | ''
  status_group?: PaymentHistoryStatusGroup | ''
  payment_method?: number | ''
  building_id?: number | ''
  room_id?: number | ''
  contract_id?: number | ''
  invoice_id?: number | ''
  deposit_transaction_type?: number | ''
  date_from?: string
  date_to?: string
  amount_direction?: PaymentHistoryAmountDirection | ''
  page?: number
  per_page?: number
}

export const PAYMENT_METHOD_CASH = 1
export const PAYMENT_METHOD_BANK_TRANSFER = 2

export const DEPOSIT_TRANSACTION_TYPE_COLLECT = 1
export const DEPOSIT_TRANSACTION_TYPE_REFUND = 2
