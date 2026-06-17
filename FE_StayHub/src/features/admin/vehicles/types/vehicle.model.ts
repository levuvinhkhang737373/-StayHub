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
  meta?: AdminPaginationMeta | null
}

export interface AdminVehicleTenantResource {
  id: number
  full_name: string
  phone: string | null
  email: string | null
}

export interface AdminVehicleResource {
  id: number
  tenant_id: number
  tenant_name?: string | null
  tenant?: AdminVehicleTenantResource | null
  vehicle_type: number
  vehicle_type_label?: string | null
  license_plate: string
  brand?: string | null
  color?: string | null
  is_active: boolean
  is_active_label?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export interface AdminVehiclePayload {
  tenant_id: number
  vehicle_type: number
  license_plate: string
  brand?: string
  color?: string
  is_active?: boolean
}

export interface AdminVehicleFormValues {
  building_id: string
  tenant_id: string
  vehicle_type: number
  license_plate: string
  brand: string
  color: string
  is_active: boolean
}

export type AdminVehicleFormErrors = Partial<Record<keyof AdminVehicleFormValues, string>>
