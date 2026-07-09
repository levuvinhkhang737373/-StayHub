import test from 'node:test'
import assert from 'node:assert/strict'
import { canUpdateTransferDate, toDateInputValue } from '../src/features/admin/room-movements/utils/transfer-date.helpers.ts'

test('allows updating transfer date only for pending or blocked transfer schedules', () => {
  assert.equal(canUpdateTransferDate({ movement_type: 2, status: 1, transfer_code: 'TRF-001' }), true)
  assert.equal(canUpdateTransferDate({ movement_type: 2, status: 3, transfer_code: 'TRF-001' }), true)
  assert.equal(canUpdateTransferDate({ movement_type: 2, status: 2, transfer_code: 'TRF-001' }), false)
  assert.equal(canUpdateTransferDate({ movement_type: 1, status: 1, transfer_code: 'TRF-001' }), false)
  assert.equal(canUpdateTransferDate({ movement_type: 2, status: 1, transfer_code: null }), false)
})

test('normalizes backend movement date into date input value', () => {
  assert.equal(toDateInputValue('2026-07-15 00:00:00'), '2026-07-15')
  assert.equal(toDateInputValue('2026-07-15T00:00:00.000000Z'), '2026-07-15')
  assert.equal(toDateInputValue(null), '')
})
