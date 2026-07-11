import type { AdminNotificationResource } from '../types/notification-api.model'

type LinkableNotification = Pick<AdminNotificationResource, 'action_url' | 'content' | 'notification_type' | 'tenant_id'> & {
  id?: number | string | null
  title?: string | null
  room_id?: number | string | null
  building_id?: number | string | null
  direct_conversation_id?: number | string | null
}

const ADMIN_FALLBACK_PATH = '/admin/notifications'

const supportedAdminSections = new Set([
  'activity-logs',
  'asset-templates',
  'chat',
  'contracts',
  'dashboard',
  'debts',
  'expense-categories',
  'expenses',
  'facilities',
  'financials',
  'invoices',
  'maintenance',
  'meter-readings',
  'meters',
  'notifications',
  'payment-history',
  'room-movements',
  'room-types',
  'rooms',
  'services',
  'settings',
  'system-users',
  'tenants',
  'transfer-room',
  'vehicles',
])

function getNotificationDetailPath(notification: LinkableNotification) {
  return notification.id ? `${ADMIN_FALLBACK_PATH}?id=${notification.id}` : ADMIN_FALLBACK_PATH
}

function normalizeAdminPath(path: string | null | undefined) {
  const trimmed = path?.trim()
  if (!trimmed) return null

  if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
    try {
      const url = new URL(trimmed)
      if (typeof window === 'undefined') return null
      if (typeof window !== 'undefined' && url.origin !== window.location.origin) return null
      return ensureRoutableAdminPath(`${url.pathname}${url.search}${url.hash}`)
    } catch {
      return null
    }
  }

  return ensureRoutableAdminPath(trimmed.startsWith('/') ? trimmed : `/${trimmed}`)
}

function ensureRoutableAdminPath(path: string) {
  if (path !== '/admin' && !path.startsWith('/admin/')) return null

  const section = path.split(/[/?#]/)[2] || 'dashboard'
  return supportedAdminSections.has(section) ? path : null
}

export function resolveNotificationActionPath(notification: LinkableNotification): string | null {
  const actionPath = normalizeAdminPath(notification.action_url)
  if (actionPath) return actionPath

  const content = `${notification.title || ''} ${notification.content || ''}`
  const scMatch = content.match(/(SC-\d{6})/i)
  const invMatch = content.match(/(INV-[A-Z0-9-]+)/i)
  const hdMatch = content.match(/(HD-[A-Z0-9-]+)/i)
  const trfMatch = content.match(/(TRF-[A-Z0-9-]+)/i)

  if (notification.notification_type === 1) {
    return scMatch ? `/admin/maintenance?request_code=${scMatch[1]}` : '/admin/maintenance'
  }

  if (notification.notification_type === 2) {
    return invMatch ? `/admin/invoices?invoice_code=${invMatch[1]}` : '/admin/invoices'
  }

  if (notification.notification_type === 6) {
    if (notification.direct_conversation_id) {
      return `/admin/chat?tab=direct&direct_conversation_id=${notification.direct_conversation_id}`
    }

    return notification.tenant_id ? `/admin/chat?tenant_id=${notification.tenant_id}` : '/admin/chat'
  }

  if (hdMatch) {
    return `/admin/contracts?contract_code=${hdMatch[1]}`
  }

  if (trfMatch) {
    return `/admin/room-movements?keyword=${encodeURIComponent(trfMatch[1])}`
  }

  if (/chuyển phòng|trả phòng|phòng và cọc/iu.test(content)) {
    return '/admin/room-movements'
  }

  if (/hợp đồng/iu.test(content)) {
    return '/admin/contracts'
  }

  if (/hóa đơn|hoá đơn/iu.test(content)) {
    return '/admin/invoices'
  }

  if (/bảo trì|sửa chữa/iu.test(content)) {
    return '/admin/maintenance'
  }

  return getNotificationDetailPath(notification)
}
