export interface AdminPaginationMeta {
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
  meta?: AdminPaginationMeta
}

export interface AdminTenantCreatorResource {
  id: number
  username?: string | null
  full_name?: string | null
  email?: string | null
  phone?: string | null
  role?: number | null
  status?: number | null
}

export interface AdminTenantCurrentRoomResource {
  room_id: number
  room_number: string
  building_id?: number | null
  building_name?: string | null
}

export interface AdminTenantCurrentContractResource {
  id: number
  contract_code?: string | null
  room_id?: number | null
  start_date?: string | null
  end_date?: string | null
  room_price?: string | null
  deposit_amount?: string | null
  deposit_balance?: string | null
  payment_status?: number | null
  status?: number | null
}

export interface AdminTenantResource {
  id: number
  created_by?: number | null
  room_id?: number | null
  room_number?: string | null
  building_id?: number | null
  building_name?: string | null
  current_room?: AdminTenantCurrentRoomResource | null
  current_contract?: AdminTenantCurrentContractResource | null
  creator?: AdminTenantCreatorResource | null
  username: string
  full_name: string
  phone: string | null
  email: string | null
  avatar_url?: string | null
  date_of_birth?: string | null
  gender?: number | null
  gender_label?: string | null
  permanent_address?: string | null
  current_address?: string | null
  status: number
  status_label?: string | null
  identity_type?: number | null
  identity_type_label?: string | null
  identity_number?: string | null
  front_image_url?: string | null
  back_image_url?: string | null
  identity_verified?: boolean
  vehicles_count?: number
  notification_reads_count?: number
  created_at?: string | null
  updated_at?: string | null
  deleted_at?: string | null
  leave_date?: string | null
}

export interface AdminTenantFilters {
  keyword?: string
  status?: number
  gender?: number
  identity_type?: number
  building_id?: number
  page?: number
  per_page?: number
  without_active_contract?: boolean
  without_reserved_contract?: boolean
  with_active_current_room?: boolean
}

export interface AdminTenantPayload {
  building_id?: number
  username?: string
  full_name?: string
  email?: string
  phone?: string
  date_of_birth?: string
  gender?: number
  status?: number
  identity_type?: number
  identity_number?: string
  permanent_address?: string | null
  current_address?: string | null
  front_image?: File | null
  back_image?: File | null
  delete_front_image?: boolean
  delete_back_image?: boolean
}

export interface AdminTenantStatusPayload {
  status: number
  reason?: string
}
