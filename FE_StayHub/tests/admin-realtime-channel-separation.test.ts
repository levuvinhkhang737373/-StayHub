import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const notificationContextSource = readFileSync(new URL('../src/features/admin/notifications/hooks/admin-notification-context.tsx', import.meta.url), 'utf8')
const invoicesScreenSource = readFileSync(new URL('../src/features/admin/invoices/components/invoices-screen.tsx', import.meta.url), 'utf8')
const payDepositModalSource = readFileSync(new URL('../src/features/admin/contracts/components/modals/PayDepositModal.tsx', import.meta.url), 'utf8')
const depositQrModalSource = readFileSync(new URL('../src/features/admin/contracts/components/modals/DepositQRModal.tsx', import.meta.url), 'utf8')

test('admin notification context keeps maintenance and payments on separate private channels', () => {
  assert.match(notificationContextSource, /const maintenanceChannel = echo\.private\('admin-maintenance'\)/)
  assert.match(notificationContextSource, /const paymentsChannel = echo\.private\('admin-payments'\)/)

  const maintenanceEvents = _listenEventsFor(notificationContextSource, 'maintenanceChannel')
  assert.deepEqual(maintenanceEvents, [
    'MaintenanceRequestCreated',
    'MaintenanceRequestAssigned',
    'MaintenanceRequestProcessing',
    'MaintenanceRequestCompleted',
    'MaintenanceFeedbackCreated',
  ])

  const paymentEvents = _listenEventsFor(notificationContextSource, 'paymentsChannel')
  assert.deepEqual(paymentEvents, [
    'ContractDepositPaid',
    'InvoicePaid',
    'InvoiceReissued',
  ])
})

test('admin payment screens subscribe directly to the dedicated payments channel', () => {
  for (const source of [invoicesScreenSource, payDepositModalSource, depositQrModalSource]) {
    assert.match(source, /echo\.private\('admin-payments'\)/)
    assert.doesNotMatch(source, /echo\.private\('admin-maintenance'\)[\s\S]*ContractDepositPaid/)
    assert.doesNotMatch(source, /echo\.private\('admin-maintenance'\)[\s\S]*InvoicePaid/)
  }
})

function _listenEventsFor(source: string, channelVariable: string) {
  return [...source.matchAll(new RegExp(`${channelVariable}\\.listen\\('\\.([^']+)'`, 'g'))]
    .map((match) => match[1])
}
