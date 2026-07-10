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
  assert.match(source, /Không hiển thị điện\/nước/)
  assert.match(source, /Lỗi validate hiển thị trong popup/)
  assert.match(source, /updateRoomServicePrices/)
})
