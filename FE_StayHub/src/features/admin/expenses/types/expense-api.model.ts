export interface AdminExpensePaginationMeta {
  current_page?: number
  from?: number | null
  last_page?: number
  path?: string
  per_page?: number
  to?: number | null
  total?: number
}

export interface AdminExpensePaginator<T> {
  data: T[]
  pagination?: AdminExpensePaginationMeta | null
  meta?: AdminExpensePaginationMeta | null
}

export interface AdminExpenseBuildingSummary {
  id: number
  name?: string | null
  manager_admin_id?: number | null
}

export interface AdminExpenseRoomSummary {
  id: number
  building_id?: number | null
  room_number?: string | null
  floor?: number | null
  status?: number | null
}

export interface AdminExpenseCategorySummary {
  id: number
  name?: string | null
  is_active?: boolean
}

export interface AdminExpenseCreatorSummary {
  id: number
  full_name?: string | null
}

export interface AdminExpenseResource {
  id: number
  expense_code: string
  building_id: number
  building_name?: string | null
  building?: AdminExpenseBuildingSummary | null
  room_id?: number | null
  room_number?: string | null
  room?: AdminExpenseRoomSummary | null
  expense_category_id?: number | null
  category_name?: string | null
  category?: AdminExpenseCategorySummary | null
  title: string
  amount: string
  amount_formatted?: string | null
  expense_date: string
  receipt_images?: string[]
  receipt_image_urls?: string[]
  payment_method?: number | null
  payment_method_label?: string | null
  note?: string | null
  status: number
  status_label?: string | null
  created_by?: number | null
  creator_name?: string | null
  creator?: AdminExpenseCreatorSummary | null
  created_at?: string | null
  updated_at?: string | null
}

export interface AdminExpenseFilters {
  keyword?: string
  building_id?: number
  room_id?: number
  expense_category_id?: number
  payment_method?: number
  status?: number
  expense_date_from?: string
  expense_date_to?: string
  page?: number
  per_page?: number
}

export interface AdminExpensePayload {
  building_id: number
  room_id?: number | null
  expense_category_id?: number | null
  title: string
  amount: string
  expense_date: string
  payment_method?: number
  note?: string | null
  receipt_images?: File[]
  deleted_receipt_images?: string[]
}
