import { createContext, useContext, useEffect, useState, useCallback, type ReactNode } from 'react'
import { X } from 'lucide-react'
import { AnimatePresence, motion } from 'motion/react'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { useAdminSocket } from '../../../../shared/lib/socket/socket-context'

export interface ReceivedNotification {
  id: string
  title: string
  description: string
  link?: string
  read: boolean
  createdAt: string
  type: 'maintenance' | 'system' | 'invoice'
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
  const { session } = useAdminSession()
  const [notifications, setNotifications] = useState<ReceivedNotification[]>([])
  const [isDrawerOpen, setIsDrawerOpen] = useState(false)
  const [toasts, setToasts] = useState<ReceivedNotification[]>([])

  const adminId = session?.admin?.id

  // Load notifications from localStorage on mount or when admin session changes
  useEffect(() => {
    if (!adminId) {
      setNotifications([])
      return
    }

    try {
      const stored = localStorage.getItem(`stayhub_admin_notifications_${adminId}`)
      if (stored) {
        setNotifications(JSON.parse(stored))
      } else {
        setNotifications([])
      }
    } catch (e) {
      console.error('Failed to parse admin notifications', e)
      setNotifications([])
    }
  }, [adminId])

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
    setToasts((prev) => [...prev, newNotif])
    setTimeout(() => {
      setToasts((prev) => prev.filter((t) => t.id !== newNotif.id))
    }, 6000)

    // Prepend and cap at 50 notifications to prevent localStorage bloat
    setNotifications((prev) => {
      const updated = [newNotif, ...prev].slice(0, 50)
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
            link: '/admin/maintenance',
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
            link: '/admin/maintenance',
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
      }
    })

    return () => {
      channel.stopListening('.MaintenanceRequestCreated')
      channel.stopListening('.MaintenanceRequestAssigned')
      channel.stopListening('.MaintenanceRequestProcessing')
      channel.stopListening('.MaintenanceRequestCompleted')
      channel.stopListening('.MaintenanceFeedbackCreated')
      channel.stopListening('.ContractDepositPaid')
    }
  }, [adminId, echo, session, addNotification])

  const markAsRead = (id: string) => {
    const updated = notifications.map((n) => (n.id === id ? { ...n, read: true } : n))
    saveNotifications(updated)
  }

  const markAllAsRead = () => {
    const updated = notifications.map((n) => ({ ...n, read: true }))
    saveNotifications(updated)
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
        } catch {}
      }, 120)

      setTimeout(() => oscillator.stop(), 180)
    } catch {}
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
              <div className="flex-1">
                <p className="text-[10px] font-black uppercase tracking-[0.16em] text-[#0f766e]">
                  {toast.type === 'maintenance' ? 'Yêu cầu sửa chữa' : 'Thông báo'}
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
