import type { AdminRoomMovementResource } from '../types/room-movement-api.model'

const MOVEMENT_TRANSFER = 2
const MOVEMENT_STATUS_PENDING = 1
const MOVEMENT_STATUS_BLOCKED = 3

export function canUpdateTransferDate(movement: Pick<AdminRoomMovementResource, 'movement_type' | 'status' | 'transfer_code'>) {
  return movement.movement_type === MOVEMENT_TRANSFER
    && Boolean(movement.transfer_code)
    && [MOVEMENT_STATUS_PENDING, MOVEMENT_STATUS_BLOCKED].includes(Number(movement.status))
}

export function canCancelTransferSchedule(movement: Pick<AdminRoomMovementResource, 'movement_type' | 'status' | 'transfer_code'>) {
  return movement.movement_type === MOVEMENT_TRANSFER
    && Boolean(movement.transfer_code)
    && [MOVEMENT_STATUS_PENDING, MOVEMENT_STATUS_BLOCKED].includes(Number(movement.status))
}

export function toDateInputValue(value?: string | null) {
  if (!value) return ''

  const dateMatch = value.match(/^\d{4}-\d{2}-\d{2}/)
  return dateMatch?.[0] ?? ''
}
