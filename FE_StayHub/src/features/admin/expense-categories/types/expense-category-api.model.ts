export interface AdminExpenseCategoryResource {
  id: number
  name: string
  description?: string | null
  is_active: boolean
  status?: boolean
  status_label?: string | null
  created_by?: number | null
  creator_name?: string | null
  creator?: {
    id: number
    full_name?: string | null
  } | null
  expenses_count?: number
  created_at?: string | null
  updated_at?: string | null
}

export interface AdminExpenseCategoryPayload {
  name: string
  description?: string
  is_active?: boolean
}
