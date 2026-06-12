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

export interface AdminMeterDeviceResource {
  id: number
  room_id: number
  room_number?: string | null
  building_id?: number | null
  service_id: number
  service_name?: string | null
  meter_code?: string | null
  meter_type: number
  meter_type_label?: string | null
  initial_reading?: string | number | null
  installed_at?: string | null
  status: number
  status_label?: string | null
  image_path?: string | null
  note?: string | null
  replaced_by_meter_id?: number | null
  replacement_meter_code?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export interface AdminMeterDevicePayload {
  room_id?: number
  room_number?: string
  service_id: number
  meter_code?: string
  meter_type: number
  initial_reading: string | number
  installed_at?: string
  status?: number
  replaced_by_meter_id?: number
  note?: string
}

export interface AdminMeterFormValues {
  building_id: string
  room_id: string
  service_id: string
  meter_code: string
  meter_type: number
  initial_reading: string
  installed_at: string
  status: number
  replaced_by_meter_id: string
  note: string
}

export type AdminMeterFormErrors = Partial<Record<keyof AdminMeterFormValues, string>>
