export interface AdminRoomMovementPaginationMeta {
  current_page?: number
  from?: number | null
  last_page?: number
  path?: string
  per_page?: number
  to?: number | null
  total?: number
}

export interface AdminRoomMovementPaginator<T> {
  data: T[]
  links?: unknown
  meta?: AdminRoomMovementPaginationMeta
}

export interface AdminRoomMovementTenantResource {
  id: number
  username?: string | null
  full_name?: string | null
  phone?: string | null
  email?: string | null
}

export interface AdminRoomMovementContractResource {
  id: number
  contract_code?: string | null
  room_id?: number | null
  status?: number | null
  payment_status?: number | null
}

export interface AdminRoomMovementRoomResource {
  id: number
  building_id?: number | null
  building_name?: string | null
  room_code?: string | null
  room_number?: string | null
  floor?: number | null
  status?: number | null
}

export interface AdminRoomMovementResource {
  id: number
  transfer_code?: string | null
  tenant_id: number
  tenant?: AdminRoomMovementTenantResource | null
  contract_id?: number | null
  contract?: AdminRoomMovementContractResource | null
  source_contract_id?: number | null
  source_contract?: AdminRoomMovementContractResource | null
  destination_contract_id?: number | null
  destination_contract?: AdminRoomMovementContractResource | null
  from_room_id?: number | null
  from_room?: AdminRoomMovementRoomResource | null
  to_room_id?: number | null
  to_room?: AdminRoomMovementRoomResource | null
  movement_type: number
  movement_type_label?: string | null
  status?: number | null
  status_label?: string | null
  movement_date?: string | null
  old_room_final_amount?: string | null
  transfer_fee?: string | null
  deposit_transfer_amount?: string | null
  deposit_refund_amount?: string | null
  deduction_amount?: string | null
  manual_refund_amount?: string | null
  deposit_due_amount?: string | null
  extra_charge_amount?: string | null
  settlement_due_amount?: string | null
  settlement_paid_amount?: string | null
  settlement_remaining_amount?: string | null
  settlement_payment_status?: number | null
  settlement_payment_status_label?: string | null
  settlement_qr_url?: string | null
  settlement_payment_references?: unknown[] | Record<string, unknown> | null
  final_electric_reading?: string | null
  final_water_reading?: string | null
  note?: string | null
  scheduled_payload?: Record<string, unknown> | null
  executed_at?: string | null
  failure_reason?: string | null
  created_by?: number | null
  creator_name?: string | null
  created_at?: string | null
}

export interface AdminRoomMovementFilters {
  keyword?: string
  movement_type?: number
  status?: number
  building_id?: number
  room_id?: number
  tenant_id?: number
  contract_id?: number
  date_from?: string
  date_to?: string
  page?: number
  per_page?: number
}
