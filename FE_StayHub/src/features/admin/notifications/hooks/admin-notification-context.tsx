import { createContext, useContext, useEffect, useState, useCallback, type ReactNode } from 'react'
import { useNavigate } from 'react-router-dom'
import { X } from 'lucide-react'
import { AnimatePresence, motion } from 'motion/react'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { useAdminSocket } from '../../../../shared/lib/socket/socket-context'
import { fetchAdminNotifications, markAdminNotificationRead, markAllAdminNotificationsRead } from '../services/notification.service'
import { resolveNotificationActionPath } from '../utils/notification-link'

export interface ReceivedNotification {
  id: string
  title: string
  description: string
  link?: string
  read: boolean
  createdAt: string
  type: 'maintenance' | 'system' | 'invoice' | 'chat'
}

interface AdminNotificationContextValue {
  notifications: ReceivedNotification[]
  unreadCount: number
  isDrawerOpen: boolean
  setIsDrawerOpen: (open: boolean) => void
  addNotification: (notif: Omit<ReceivedNotification, 'id' | 'read' | 'createdAt'>) => void
  markAsRead: (id: string) => void
  markAllAsRead: () => void
  clearAll: () => void
}

const AdminNotificationContext = createContext<AdminNotificationContextValue | null>(null)

export function AdminNotificationProvider({ children }: { children: ReactNode }) {
  const navigate = useNavigate()
  const { session } = useAdminSession()
  const [notifications, setNotifications] = useState<ReceivedNotification[]>([])
  const [isDrawerOpen, setIsDrawerOpen] = useState(false)
  const [toasts, setToasts] = useState<ReceivedNotification[]>([])

  const adminId = session?.admin?.id

  // Load notifications from API and merge with local read states on mount
  useEffect(() => {
    if (!adminId || !session) {
      setNotifications([])
      return
    }

    let isMounted = true

    const loadNotificationsFromApi = async () => {
      try {


        const res = await fetchAdminNotifications({ per_page: 500 })
        if (!isMounted) return

        if (res.status && res.result) {
          // Safeguard to ensure result is an array
          const list = res.result.data || []

          const mapped = list.reduce<ReceivedNotification[]>((acc, item: any) => {
            // Exclude chat notifications not targeting this admin
            if (Number(item.notification_type) === 6 && Number(item.target_admin_id) !== Number(adminId)) {
              return acc
            }



            // Exclude notifications created by the current admin themselves (outgoing)
            if (Number(item.created_by) === Number(adminId)) {
              return acc
            }

            const notifId = String(item.id)

            let notifType: ReceivedNotification['type'] = 'system'
            if (item.notification_type === 1) {
              notifType = 'maintenance'
            } else if (item.notification_type === 2) {
              notifType = 'invoice'
            } else if (item.notification_type === 6) {
              notifType = 'chat'
            }

            const link = resolveNotificationActionPath(item)

            acc.push({
              id: notifId,
              title: item.title,
              description: item.content || '',
              link: link || undefined,
              read: item.is_read ?? false,
              createdAt: item.created_at,
              type: notifType,
            })

            return acc
          }, [])

          setNotifications(mapped)
          localStorage.setItem(`stayhub_admin_notifications_${adminId}`, JSON.stringify(mapped))
        }
      } catch (e) {
        console.error('Failed to load notifications from API', e)
      }
    }

    void loadNotificationsFromApi()

    return () => {
      isMounted = false
    }
  }, [adminId, session])

  // Save notifications to localStorage whenever they change
  const saveNotifications = useCallback((newNotifs: ReceivedNotification[]) => {
    setNotifications(newNotifs)
    if (adminId) {
      localStorage.setItem(`stayhub_admin_notifications_${adminId}`, JSON.stringify(newNotifs))
    }
  }, [adminId])

  const addNotification = useCallback((notif: Omit<ReceivedNotification, 'id' | 'read' | 'createdAt'>) => {
    const newNotif: ReceivedNotification = {
      ...notif,
      id: `${Date.now()}-${Math.random().toString(36).substring(2, 11)}`,
      read: false,
      createdAt: new Date().toISOString(),
    }

    // Play chime sound
    playChimeSound()

    // Add toast notification
    setToasts((prev) => {
      if (notif.type === 'chat') {
        const filtered = prev.filter((t) => !(t.type === 'chat' && t.link === notif.link))
        return [...filtered, newNotif]
      }
      return [...prev, newNotif]
    })
    setTimeout(() => {
      setToasts((prev) => prev.filter((t) => t.id !== newNotif.id))
    }, 6000)

    // Prepend and cap at 50 notifications to prevent localStorage bloat
    setNotifications((prev) => {
      let updated: ReceivedNotification[]
      if (notif.type === 'chat') {
        const existingIdx = prev.findIndex((n) => n.type === 'chat' && n.link === notif.link)
        if (existingIdx !== -1) {
          const existing = prev[existingIdx]
          const updatedNotif: ReceivedNotification = {
            ...existing,
            description: notif.description,
            read: false,
            createdAt: new Date().toISOString(),
          }
          const filtered = prev.filter((_, idx) => idx !== existingIdx)
          updated = [updatedNotif, ...filtered].slice(0, 500)
        } else {
          updated = [newNotif, ...prev].slice(0, 500)
        }
      } else {
        updated = [newNotif, ...prev].slice(0, 500)
      }

      if (adminId) {
        localStorage.setItem(`stayhub_admin_notifications_${adminId}`, JSON.stringify(updated))
      }
      return updated
    })
  }, [adminId])

  const { echo } = useAdminSocket()

  // Setup WebSocket connection for Admin Notifications
  useEffect(() => {
    if (!adminId || !echo) return

    const channel = echo.private('admin-maintenance')

    channel.listen('.MaintenanceRequestCreated', (event: any) => {
      console.log('WS: Received MaintenanceRequestCreated', event)
      const request = event.request
      if (request) {
        const isSuperAdmin = isSuperAdminRole(session?.admin?.role)
        const managedBuildings = session?.admin?.managed_buildings || []
        const isManagerOfBuilding = managedBuildings.some(b => Number(b.id) === Number(request.building_id))

        if (isSuperAdmin || isManagerOfBuilding) {
          addNotification({
            title: 'Yêu cầu bảo trì mới',
            description: `Phòng ${request.room_number ?? '?'}: ${request.title ?? ''} — ${request.description ?? ''}`,
            link: `/admin/maintenance?id=${request.id}`,
            type: 'maintenance',
          })
        }

        // Dispatch custom event to notify React components (like MaintenanceScreen)
        window.dispatchEvent(new CustomEvent('maintenance-created'))
      }
    })

    channel.listen('.MaintenanceRequestAssigned', (event: any) => {
      console.log('WS: Received MaintenanceRequestAssigned', event)
      window.dispatchEvent(new CustomEvent('maintenance-created'))
    })

    channel.listen('.MaintenanceRequestProcessing', (event: any) => {
      console.log('WS: Received MaintenanceRequestProcessing', event)
      window.dispatchEvent(new CustomEvent('maintenance-created'))
    })

    channel.listen('.MaintenanceRequestCompleted', (event: any) => {
      console.log('WS: Received MaintenanceRequestCompleted', event)
      window.dispatchEvent(new CustomEvent('maintenance-created'))
    })

    channel.listen('.MaintenanceFeedbackCreated', (event: any) => {
      console.log('WS: Received MaintenanceFeedbackCreated', event)
      const feedback = event.feedback
      if (feedback) {
        const isSuperAdmin = isSuperAdminRole(session?.admin?.role)
        const managedBuildings = session?.admin?.managed_buildings || []
        const isManagerOfBuilding = managedBuildings.some(b => Number(b.id) === Number(feedback.building_id))

        if (isSuperAdmin || isManagerOfBuilding) {
          const commentText = feedback.comment?.trim() ? `phản hồi: "${feedback.comment}"` : 'gửi phản hồi (không có nội dung)'
          addNotification({
            title: 'Phản hồi bảo trì mới',
            description: `Phòng ${feedback.room_number ?? '?'}: Khách ${feedback.tenant_name ?? 'không rõ'} ${commentText}`,
            link: `/admin/maintenance?id=${feedback.maintenance_request_id}`,
            type: 'maintenance',
          })
        }
        window.dispatchEvent(new CustomEvent('maintenance-created'))
      }
    })

    channel.listen('.ContractDepositPaid', (event: any) => {
      console.log('WS: Received ContractDepositPaid', event)
      const contract = event.contract
      if (contract) {
        window.dispatchEvent(new CustomEvent('contract-deposit-paid', { detail: contract }))
        window.dispatchEvent(new CustomEvent('contract-refresh', { detail: contract }))
      }
    })

    channel.listen('.InvoicePaid', (event: any) => {
      console.log('WS: Received InvoicePaid', event)
      const invoice = event.invoice
      if (invoice) {
        // Chỉ dispatch event để refresh UI real-time, việc hiển thị toast thông báo sẽ do listener .NotificationSent đảm nhận
        window.dispatchEvent(new CustomEvent('invoice-refresh', { detail: invoice }))
      }
    })

    channel.listen('.InvoiceReissued', (event: any) => {
      console.log('WS: Received InvoiceReissued', event)
      const invoice = event.invoice
      if (invoice) {
        window.dispatchEvent(new CustomEvent('invoice-refresh', { detail: invoice }))
      }
    })

    channel.listen('.NotificationSent', (event: any) => {
      console.log('WS: Received NotificationSent', event)
      const notification = event.notification
      if (notification) {
        const isSuperAdmin = isSuperAdminRole(session?.admin?.role)
        const managedBuildings = session?.admin?.managed_buildings || []
        const isManagerOfBuilding = managedBuildings.some(b => Number(b.id) === Number(notification.building_id))

        if (isSuperAdmin || isManagerOfBuilding || !notification.building_id) {
          // Always dispatch notification-refresh for the admin notifications list screen
          window.dispatchEvent(new CustomEvent('notification-refresh'))

          if (Number(notification.target_type) === 5) { // TARGET_TYPE_ADMIN = 5
            const link = resolveNotificationActionPath(notification)

            if (Number(notification.notification_type) === 6 && Number(notification.target_admin_id) !== Number(adminId)) {
              return
            }

            addNotification({
              title: notification.title,
              description: notification.content,
              link: link || undefined,
              type: notification.notification_type === 1 ? 'maintenance' : notification.notification_type === 2 ? 'invoice' : notification.notification_type === 6 ? 'chat' : 'system',
            })
          }

          if (notification.notification_type === 2) {
            window.dispatchEvent(new CustomEvent('invoice-refresh', { detail: notification }))
          } else if (notification.notification_type === 4) {
            window.dispatchEvent(new CustomEvent('fire-safety-refresh', { detail: notification }))
          } else if (notification.title === 'Hợp đồng đã được ký' || notification.title === 'Hợp đồng hết hạn') {
            window.dispatchEvent(new CustomEvent('contract-refresh', { detail: notification }))
          }
        }
      }
    })

    const managedBuildings = session?.admin?.managed_buildings || []
    const buildingChannels = managedBuildings.map((b) => {
      const bCh = echo.private(`admin-building.${b.id}`)
      bCh.listen('.ContractExpired', (event: any) => {
        console.log('WS: Received ContractExpired', event)
        const contract = event.contract
        if (contract) {
          addNotification({
            title: 'Hợp đồng hết hạn',
            description: `Hợp đồng ${contract.contract_code ?? ''} (Phòng ${contract.room_number ?? '?'}) đã hết hạn.`,
            link: `/admin/contracts?id=${contract.id}`,
            type: 'system',
          })
          window.dispatchEvent(new CustomEvent('contract-refresh', { detail: contract }))
        }
      })
      return { id: b.id, channel: bCh }
    })

    const adminChatChannel = echo.private(`chat.admin.${adminId}`)
    adminChatChannel.listen('.NotificationSent', (event: any) => {
      const notification = event.notification
      if (!notification || Number(notification.notification_type) !== 6) return

      if (Number(notification.target_admin_id) !== Number(adminId)) return

      window.dispatchEvent(new CustomEvent('notification-refresh', { detail: notification }))
      addNotification({
        title: notification.title || 'Tin nhắn mới',
        description: notification.content || 'Bạn có tin nhắn chat mới.',
        link: resolveNotificationActionPath(notification) || (notification.tenant_id ? `/admin/chat?tenant_id=${notification.tenant_id}` : '/admin/chat'),
        type: 'chat',
      })
    })

    return () => {
      channel.stopListening('.MaintenanceRequestCreated')
      channel.stopListening('.MaintenanceRequestAssigned')
      channel.stopListening('.MaintenanceRequestProcessing')
      channel.stopListening('.MaintenanceRequestCompleted')
      channel.stopListening('.MaintenanceFeedbackCreated')
      channel.stopListening('.ContractDepositPaid')
      channel.stopListening('.InvoicePaid')
      channel.stopListening('.InvoiceReissued')
      channel.stopListening('.NotificationSent')
      buildingChannels.forEach((bc) => {
        bc.channel.stopListening('.ContractExpired')
      })
      adminChatChannel.stopListening('.NotificationSent')
    }
  }, [adminId, echo, session, addNotification])

  const markAsRead = (id: string) => {
    const updated = notifications.map((n) => (n.id === id ? { ...n, read: true } : n))
    saveNotifications(updated)
    if (!isNaN(Number(id))) {
      markAdminNotificationRead(Number(id)).catch((err) => {
        console.error('Failed to mark notification as read on backend', err)
      })
    }
  }

  const markAllAsRead = () => {
    const updated = notifications.map((n) => ({ ...n, read: true }))
    saveNotifications(updated)
    markAllAdminNotificationsRead().catch((err) => {
      console.error('Failed to mark all notifications as read on backend', err)
    })
  }

  const clearAll = () => {
    saveNotifications([])
  }

  const unreadCount = notifications.filter((n) => !n.read).length

  // Generate dynamic premium chimes using AudioContext
  const playChimeSound = () => {
    try {
      const audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)()
      const oscillator = audioCtx.createOscillator()
      const gainNode = audioCtx.createGain()

      oscillator.connect(gainNode)
      gainNode.connect(audioCtx.destination)

      oscillator.type = 'sine'
      // Pleasant double chime sound
      oscillator.frequency.setValueAtTime(587.33, audioCtx.currentTime) // D5
      gainNode.gain.setValueAtTime(0.08, audioCtx.currentTime)
      oscillator.start()
      gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.15)

      setTimeout(() => {
        try {
          const osc2 = audioCtx.createOscillator()
          const gain2 = audioCtx.createGain()
          osc2.connect(gain2)
          gain2.connect(audioCtx.destination)
          osc2.type = 'sine'
          osc2.frequency.setValueAtTime(880, audioCtx.currentTime) // A5
          gain2.gain.setValueAtTime(0.08, audioCtx.currentTime)
          osc2.start()
          gain2.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.3)
          setTimeout(() => osc2.stop(), 350)
        } catch { }
      }, 120)

      setTimeout(() => oscillator.stop(), 180)
    } catch { }
  }

  return (
    <AdminNotificationContext.Provider
      value={{
        notifications,
        unreadCount,
        isDrawerOpen,
        setIsDrawerOpen,
        addNotification,
        markAsRead,
        markAllAsRead,
        clearAll,
      }}
    >
      {children}

      {/* Premium Toast Container */}
      <div className="fixed bottom-5 right-5 z-[9999] flex flex-col gap-3 max-w-sm w-full pointer-events-none">
        <AnimatePresence>
          {toasts.map((toast) => (
            <motion.div
              key={toast.id}
              initial={{ opacity: 0, y: 50, scale: 0.9 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              exit={{ opacity: 0, scale: 0.85, y: -20, transition: { duration: 0.2 } }}
              className="pointer-events-auto flex gap-3 overflow-hidden rounded-2xl border border-[#3d2a18]/10 bg-white/95 p-4 text-[#24170d] shadow-2xl shadow-[#6b3f1d]/18 backdrop-blur-md"
            >
              <div
                className="flex-1 cursor-pointer hover:opacity-80 transition-opacity"
                onClick={() => {
                  if (toast.id) markAsRead(toast.id)
                  setToasts((prev) => prev.filter((t) => t.id !== toast.id))
                  if (toast.link) navigate(toast.link)
                }}
              >
                <p className="text-[10px] font-black uppercase tracking-[0.16em] text-[#0f766e]">
                  {toast.type === 'maintenance' ? 'Yêu cầu sửa chữa' : toast.type === 'invoice' ? 'Hóa đơn' : toast.type === 'chat' ? 'Tin nhắn mới' : 'Thông báo'}
                </p>
                <h4 className="mt-1 text-sm font-black text-[#24170d]">{toast.title}</h4>
                <p className="mt-0.5 text-xs text-[#6f6254] font-semibold leading-relaxed">{toast.description}</p>
              </div>
              <button
                type="button"
                onClick={() => setToasts((prev) => prev.filter((t) => t.id !== toast.id))}
                className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-lg border border-[#3d2a18]/10 text-stone-500 hover:bg-stone-100 hover:text-stone-800"
              >
                <X className="h-3.5 w-3.5" />
              </button>
            </motion.div>
          ))}
        </AnimatePresence>
      </div>
    </AdminNotificationContext.Provider>
  )
}

export function useAdminNotifications() {
  const context = useContext(AdminNotificationContext)
  if (!context) {
    throw new Error('useAdminNotifications must be used within AdminNotificationProvider')
  }
  return context
}
