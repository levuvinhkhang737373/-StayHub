export interface AdminDebtPaginationMeta {
  current_page?: number
  per_page?: number
  total?: number
  last_page?: number
}

export interface AdminDebtPaginator<T> {
  data: T[]
  pagination?: AdminDebtPaginationMeta | null
  meta?: AdminDebtPaginationMeta | null
  stats?: AdminDebtStats | null
}

export type AdminDebtStatus = 'all' | 'collectible' | 'rolled' | 'overdue'

export interface AdminDebtFilters {
  keyword?: string
  debt_status?: AdminDebtStatus
  building_id?: number
  room_id?: number
  contract_id?: number
  billing_month?: number
  billing_year?: number
  page?: number
  per_page?: number
}

export interface AdminDebtStats {
  total_collectible_amount: string
  total_rolled_outstanding_amount: string
  invoice_count: number
  collectible_count: number
  rolled_count: number
  overdue_count: number
}

export interface AdminDebtTenantSummary {
  id?: number | null
  full_name?: string | null
  phone?: string | null
  email?: string | null
  is_staying?: boolean
}

export interface AdminDebtRolledSource {
  source_invoice_id?: number | null
  source_invoice_code?: string | null
  amount: string
  settled_amount: string
  remaining_amount: string
  status?: number | null
  status_label?: string | null
}

export interface AdminDebtResource {
  invoice: {
    id: number
    invoice_code: string
    status?: number | null
    status_label?: string | null
  }
  contract?: {
    id?: number | null
    contract_code?: string | null
  } | null
  room?: {
    id?: number | null
    room_number?: string | null
    floor?: number | null
  } | null
  building?: {
    id?: number | null
    name?: string | null
  } | null
  tenants?: AdminDebtTenantSummary[]
  period: {
    billing_month?: number | null
    billing_year?: number | null
    period_start?: string | null
    period_end?: string | null
    due_date?: string | null
  }
  amounts: {
    total_amount: string
    paid_amount: string
    remaining_amount: string
    collectible_remaining_amount: string
    rolled_outstanding_amount: string
    previous_debt_amount?: string | null
  }
  debt: {
    debt_status: 'collectible' | 'partial' | 'overdue' | 'rolled' | 'cleared'
    is_overdue: boolean
    can_collect_directly: boolean
    is_debt_rolled_over: boolean
  }
  rollover?: {
    rolled_to_invoice_id?: number | null
    rolled_to_invoice_code?: string | null
    rolled_sources?: AdminDebtRolledSource[]
  } | null
}
