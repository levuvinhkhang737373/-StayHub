import type { AdminNotificationResource } from '../types/notification-api.model'

type LinkableNotification = Pick<AdminNotificationResource, 'action_url' | 'content' | 'notification_type' | 'tenant_id'> & {
  direct_conversation_id?: number | string | null
}

function normalizeAdminPath(path: string | null | undefined) {
  const trimmed = path?.trim()
  if (!trimmed) return null

  if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
    try {
      const url = new URL(trimmed)
      return `${url.pathname}${url.search}${url.hash}`
    } catch {
      return null
    }
  }

  return trimmed.startsWith('/') ? trimmed : `/${trimmed}`
}

export function resolveNotificationActionPath(notification: LinkableNotification): string | null {
  const actionPath = normalizeAdminPath(notification.action_url)
  if (actionPath) return actionPath

  const content = notification.content || ''
  const scMatch = content.match(/(SC-\d{6})/i)
  const invMatch = content.match(/(INV-[A-Z0-9-]+)/i)
  const hdMatch = content.match(/(HD-[A-Z0-9-]+)/i)

  if (notification.notification_type === 1) {
    return scMatch ? `/admin/maintenance?request_code=${scMatch[1]}` : '/admin/maintenance'
  }

  if (notification.notification_type === 2) {
    return invMatch ? `/admin/invoices?invoice_code=${invMatch[1]}` : '/admin/invoices'
  }

  if (notification.notification_type === 4) {
    return '/admin/fire-safety'
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

  return null
}
