import type { AdminInvoiceResource } from '../../invoices/types/invoice-api.model'

export interface MeterReadingInput {
  meter_device_id: number
  current_reading: number
}

export interface TransferDeductionItemInput {
  name: string
  amount: number
  note?: string
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
  refund_payment_method?: number
  new_deposit_amount?: number
  additional_deposit_amount?: number
  additional_deposit_payment_method?: number
  deduction_items?: TransferDeductionItemInput[]
  transfer_fee?: number
  carry_vehicle_ids?: number[]
}

export interface TransferRoomContractSummary {
  id: number
  contract_code: string
  room_id: number
  room_number?: string | null
  building_name?: string | null
  start_date?: string | null
  end_date?: string | null
  room_price?: string | null
  deposit_amount?: string | null
  deposit_balance?: string | null
  payment_status?: number | null
  payment_status_label?: string | null
  status?: number | null
  status_label?: string | null
}

export interface TransferRoomDepositSummary {
  old_balance?: string | null
  deduction_amount?: string | null
  refund_amount?: string | null
  transfer_amount?: string | null
  new_required_amount?: string | null
  new_balance?: string | null
  new_due_amount?: string | null
}

export interface TransferRoomResultResource {
  movement: RoomMovementResource
  old_invoice?: AdminInvoiceResource | null
  new_contract?: TransferRoomContractSummary | null
  deposit?: TransferRoomDepositSummary | null
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
