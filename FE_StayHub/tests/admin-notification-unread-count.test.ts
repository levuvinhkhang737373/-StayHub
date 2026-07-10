import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const contextSource = readFileSync(new URL('../src/features/admin/notifications/hooks/admin-notification-context.tsx', import.meta.url), 'utf8')

test('admin notification badge uses server unread stats instead of local list length', () => {
  assert.match(contextSource, /setUnreadCount\(Number\(stats\.unread\s*\?\?\s*0\)\)/)
  assert.doesNotMatch(contextSource, /const\s+unreadCount\s*=\s*notifications\.filter\(\(n\)\s*=>\s*!n\.read\)\.length/)
})

test('realtime admin notifications refresh canonical unread count from API', () => {
  assert.match(contextSource, /void\s+loadNotificationsFromApi\(\)/)
  assert.match(contextSource, /window\.addEventListener\('notification-refresh',\s*handleNotificationRefresh\)/)
})
