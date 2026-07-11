export interface RoomServicePriceServiceResource {
  id: number
  room_id: number
  service_id: number
  is_active: boolean
  ended_at: string | null
  can_schedule_price: boolean
  schedule_block_reason: string | null
  service_name: string | null
  service_slug: string | null
  charge_method: number | null
  charge_method_label: string | null
  unit_name: string | null
  base_price: string
  current_price: string
  old_price: string
  effective_price: string
  display_price: string
  display_price_source: 'room' | 'contract'
  new_price: string | null
  scheduled_price: string | null
  active_contract_id: number | null
  active_contract_code: string | null
  contract_status: number | null
  contract_status_label: string | null
  contract_is_ended: boolean
  contract_price: string | null
  contract_effective_from: string | null
  contract_effective_to: string | null
  effective_from: string | null
  effective_to: string | null
  status_label: string | null
  created_by: number | null
  creator_name: string | null
  created_at: string | null
}

export interface RoomServicePriceRoomResource {
  id: number
  building_id: number
  building_name?: string | null
  room_number: string
  floor?: number | null
  status: number
  active_contract_id: number | null
  active_contract_code: string | null
  contract_status: number | null
  contract_status_label: string | null
  contract_is_ended: boolean
  services: RoomServicePriceServiceResource[]
}

export interface RoomServicePriceListResult {
  data: RoomServicePriceRoomResource[]
  current_page: number
  per_page: number
  last_page: number
  total: number
}

export interface RoomServicePriceFilters {
  building_id?: number | string | null
  room_id?: number | string | null
  keyword?: string
  billing_month: number
  billing_year: number
  page?: number
  per_page?: number
}

export interface UpdateRoomServicePricesPayload {
  billing_month: number
  billing_year: number
  prices: Array<{
    room_service_id: number
    contract_id?: number | null
    price: number | string
  }>
}
