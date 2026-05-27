export interface AdminServicePriceResource {
  id: number
  building_id?: number | null
  building_name?: string | null
  price?: number | null
  effective_from?: string | null
  effective_to?: string | null
  status?: number | null
  status_label?: string | null
}

export interface AdminServiceResource {
  id: number
  service_code: string
  name: string
  slug?: string | null
  service_type: string
  service_type_label?: string | null
  charge_method: number
  charge_method_label?: string | null
  unit_name?: string | null
  is_required: boolean
  is_required_label?: string | null
  is_active: boolean
  is_active_label?: string | null
  created_by?: number | null
  creator_name?: string | null
  creator?: {
    id: number
    full_name?: string | null
  } | null
  prices_count?: number
  meter_devices_count?: number
  invoice_items_count?: number
  prices?: AdminServicePriceResource[]
  created_at?: string | null
  updated_at?: string | null
}

export interface AdminServicePayload {
  service_code: string
  name: string
  service_type: string
  charge_method: number
  unit_name?: string
  is_required?: boolean
  is_active?: boolean
}
