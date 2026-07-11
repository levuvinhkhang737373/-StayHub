import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync, existsSync } from 'node:fs'

const navSource = readFileSync(new URL('../src/features/admin/shared/config/admin-navigation.ts', import.meta.url), 'utf8')
const routeSource = readFileSync(new URL('../src/routes/admin.routes.tsx', import.meta.url), 'utf8')
const screenUrl = new URL('../src/features/admin/room-service-prices/components/room-service-prices-screen.tsx', import.meta.url)

test('admin navigation and routes include room service prices for all admins', () => {
  assert.match(navSource, /id: 'room_service_prices'/)
  assert.match(navSource, /href: '\/admin\/room-service-prices'/)
  assert.match(navSource, /label: 'Giá dịch vụ phòng'/)
  assert.match(navSource, /access: 'all'/)
  assert.match(routeSource, /path: 'room-service-prices'/)
  assert.match(routeSource, /<AdminRouteGuard access="all"><RoomServicePricesScreen \/><\/AdminRouteGuard>/)
})

test('room service price screen defaults to next month and blocks utility services in UI copy', () => {
  assert.equal(existsSync(screenUrl), true)
  const source = readFileSync(screenUrl, 'utf8')

  assert.match(source, /getNextBillingPeriod/)
  assert.match(source, /không bao gồm điện\/nước/)
  assert.match(source, /setModalError\(getVisibleErrorMessage/)
  assert.match(source, /error\.validationErrors/)
  assert.match(source, /updateRoomServicePrices/)
})

test('room service price modal keeps new price inputs empty for manual entry', () => {
  const source = readFileSync(screenUrl, 'utf8')

  assert.match(source, /import \{ formatCurrency, formatMoneyInput, parseMoneyInput \}/)
  assert.doesNotMatch(source, /function formatMoneyInput\(value: string\)/)
  assert.match(source, /setPriceInputs\(Object\.fromEntries\(room\.services\.filter\(isServiceSchedulable\)\.map\(\(service\) => \[service\.id, ''\]\)\)\)/)
  assert.doesNotMatch(source, /setPriceInputs\(Object\.fromEntries\(room\.services\.map\(\(service\) => \[service\.id, formatMoneyInput/)
  assert.match(source, /\.filter\(\(service\) => isServiceSchedulable\(service\) && \(priceInputs\[service\.id\] \?\? ''\)\.trim\(\) !== ''\)/)
})

test('room service price screen blocks scheduling inactive room services in UI', () => {
  const source = readFileSync(screenUrl, 'utf8')

  assert.match(source, /can_schedule_price/)
  assert.match(source, /schedule_block_reason/)
  assert.match(source, /latest_contract_code/)
  assert.match(source, /Hợp đồng cũ/)
  assert.match(source, /function isServiceSchedulable/)
  assert.match(source, /disabled=\{!canEdit\}/)
  assert.match(source, /Ngừng hoạt động/)
})
