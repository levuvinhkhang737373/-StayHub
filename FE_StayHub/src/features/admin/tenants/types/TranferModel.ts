import type { AdminInvoiceResource } from '../../invoices/types/invoice-api.model'

export interface TransferDeductionItemInput {
  name: string
  amount: number
  note?: string
}

export interface TransferTenantPayload {
  tenant_id?: number
  tenant_ids?: number[]
  to_room_id: number
  movement_date: string // 'YYYY-MM-DD'
  note?: string
  deposit_deduction_amount?: number
  new_deposit_amount?: number
  deduction_items?: TransferDeductionItemInput[]
  transfer_fee?: number
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
  manual_refund_amount?: string | null
  deposit_due_amount?: string | null
  extra_charge_amount?: string | null
  settlement_due_amount?: string | null
}

export interface TransferRoomResultResource {
  transfer_code?: string | null
  movement: RoomMovementResource
  movements?: RoomMovementResource[]
  old_invoice?: AdminInvoiceResource | null
  new_contract?: TransferRoomContractSummary | null
  deposit?: TransferRoomDepositSummary | null
  scheduled_payload?: Record<string, unknown> | null
  status?: number | null
  status_label?: string | null
  execute_result?: Record<string, unknown> | null
  executed_immediately?: boolean
  blocked_immediately?: boolean
}

export interface RoomMovementResource {
  id: number
  transfer_code?: string | null
  tenant_id: number
  contract_id?: number | null
  source_contract_id?: number | null
  destination_contract_id?: number | null
  from_room_id?: number | null
  to_room_id?: number | null
  movement_type: number
  movement_type_label?: string | null
  status?: number | null
  status_label?: string | null
  movement_date: string
  old_room_final_amount?: string | number | null
  final_electric_reading?: string | number | null
  final_water_reading?: string | number | null
  deposit_transfer_amount?: string | number | null
  deduction_amount?: string | number | null
  deposit_refund_amount?: string | number | null
  transfer_fee?: string | number | null
  manual_refund_amount?: string | number | null
  deposit_due_amount?: string | number | null
  extra_charge_amount?: string | number | null
  settlement_due_amount?: string | number | null
  settlement_paid_amount?: string | number | null
  settlement_remaining_amount?: string | number | null
  settlement_payment_status?: number | null
  settlement_payment_status_label?: string | null
  settlement_payment_references?: unknown[] | Record<string, unknown> | null
  settlement_qr_url?: string | null
  scheduled_payload?: Record<string, unknown> | null
  executed_at?: string | null
  failure_reason?: string | null
  note?: string | null
  created_at?: string
}
