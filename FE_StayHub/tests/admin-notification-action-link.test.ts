import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolveNotificationActionPath } from '../src/features/admin/notifications/utils/notification-link.ts'

const notificationScreenSource = readFileSync(new URL('../src/features/admin/notifications/components/notifications-screen.tsx', import.meta.url), 'utf8')

test('generic building notification opens notification detail instead of protected building edit page', () => {
  assert.equal(resolveNotificationActionPath({
    id: 123,
    title: 'tết',
    content: 'sts',
    action_url: null,
    notification_type: 3,
    target_type: 2,
    building_id: 7,
    room_id: null,
    tenant_id: null,
  }), '/admin/notifications?id=123')
})

test('explicit action urls and coded notifications still deep link to their target flows', () => {
  assert.equal(resolveNotificationActionPath({
    id: 1,
    title: 'Phiếu sửa chữa mới',
    content: 'Mã SC-123456 cần xử lý',
    action_url: null,
    notification_type: 1,
    target_type: 5,
    tenant_id: null,
  }), '/admin/maintenance?request_code=SC-123456')

  assert.equal(resolveNotificationActionPath({
    id: 2,
    title: 'Hóa đơn',
    content: 'Hóa đơn INV-202607-001 đã phát hành',
    action_url: null,
    notification_type: 2,
    target_type: 4,
    tenant_id: null,
  }), '/admin/invoices?invoice_code=INV-202607-001')

  assert.equal(resolveNotificationActionPath({
    id: 3,
    title: 'Tin nhắn mới',
    content: 'Bạn có tin nhắn mới',
    action_url: '/admin/chat?conversation_id=9',
    notification_type: 6,
    target_type: 5,
    tenant_id: 10,
  }), '/admin/chat?conversation_id=9')
})

test('unsafe or unsupported action urls fall back to notification detail', () => {
  assert.equal(resolveNotificationActionPath({
    id: 456,
    title: 'Thông báo',
    content: 'Không mở external link',
    action_url: 'https://evil.example/admin/dashboard',
    notification_type: 3,
    target_type: 1,
    tenant_id: null,
  }), '/admin/notifications?id=456')
})

test('notification list opens detail in place for notification-detail links', () => {
  assert.match(notificationScreenSource, /const openNotificationAction = \(notif: AdminNotificationResource\) => \{[\s\S]*void openDetail\(notif\)[\s\S]*navigate\(actionPath\)/)
  assert.doesNotMatch(notificationScreenSource, /navigate\(resolveNotificationActionPath\(notif\)/)
})

test('notification screen reacts to changing id query params without one-time stale refs', () => {
  assert.match(notificationScreenSource, /const notificationIdParam = searchParams\.get\('id'\)/)
  assert.match(notificationScreenSource, /lastOpenedNotificationIdRef/)
  assert.doesNotMatch(notificationScreenSource, /initialParamsRef/)
})
