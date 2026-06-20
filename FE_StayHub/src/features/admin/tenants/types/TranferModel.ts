export interface MeterReadingInput {
  meter_device_id: number
  current_reading: number
}

export interface TransferTenantPayload {
  tenant_id: number
  to_room_id: number
  movement_date: string // 'YYYY-MM-DD'
  note?: string
  meter_readings?: MeterReadingInput[]
  // key = service_id, value = chỉ số khởi điểm - chỉ cần khi phòng đích chưa có công tơ
  new_room_opening_readings?: Record<number, number>
  deposit_settlement_amount?: number
  deposit_deduction_amount?: number
  deposit_refund_amount?: number
  transfer_fee?: number
  carry_vehicle_ids?: number[]
}

export interface RoomMovementResource {
  id: number
  tenant_id: number
  contract_id: number
  from_room_id: number
  to_room_id: number
  movement_type: number
  movement_date: string
  final_electric_reading?: number | null
  final_water_reading?: number | null
  deposit_transfer_amount?: number | null
  deduction_amount?: number | null
  deposit_refund_amount?: number | null
  transfer_fee?: number | null
  note?: string | null
  created_at?: string
}