export interface AdminActivityLogAdmin {
  id: number
  username: string
  full_name: string
  display_name?: string | null
  email?: string | null
  role?: number | string | null
  role_label?: string | null
  status?: number | string | boolean | null
  status_label?: string | null
}

export interface AdminActivityLogResource {
  id: number
  admin_name?: string | null
  admin?: AdminActivityLogAdmin | null
  action: string
  entity_type: string
  entity_type_label?: string | null
  entity_id?: number | null
  entity_name?: string | null
  old_data?: Record<string, unknown> | null
  new_data?: Record<string, unknown> | null
  changed_fields?: string[]
  old_data_display?: AdminActivityLogDisplayField[]
  new_data_display?: AdminActivityLogDisplayField[]
  changed_fields_display?: string[]
  change_summary?: AdminActivityLogChangeItem[]
  ip_address?: string | null
  user_agent?: string | null
  created_at?: string | null
}

export interface AdminActivityLogDisplayField {
  key: string
  label: string
  value: string
}

export interface AdminActivityLogChangeItem {
  key: string
  label: string
  old_value: string
  new_value: string
}

export interface AdminActivityLogFilters {
  keyword?: string
  admin_id?: number
  action?: string
  entity_type?: string
  entity_id?: number
  date_from?: string
  date_to?: string
  page?: number
  per_page?: number
}

export interface AdminActivityLogPaginationMeta {
  current_page?: number
  from?: number | null
  last_page?: number
  path?: string
  per_page?: number
  to?: number | null
  total?: number
}

export interface AdminActivityLogPaginator<T> {
  data: T[]
  links?: unknown
  meta?: AdminActivityLogPaginationMeta
}
