import { useMemo, useState, useEffect, useCallback } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { LogOut, User, Bell, MessageCircle } from 'lucide-react'
import { isBuildingManagerRole, useAdminSession } from '../../features/admin/auth/hooks/use-admin-session'
import { logoutAdmin } from '../../features/admin/auth/services/admin-auth.service'
import { AdminNavList } from '../../features/admin/shared/components/AdminNavList'
import { getVisibleAdminNavItems, resolveAdminRoleLabel } from '../../features/admin/shared/config/admin-navigation'
import { AccountSettingsModal } from './AccountSettingsModal'
import { resolveAssetUrl } from '../../shared/lib/utils/asset-url'
import { useAdminNotifications } from '../../features/admin/notifications/hooks/admin-notification-context'
import { useAdminSocket } from '../../shared/lib/socket/socket-context'
import { fetchAdminChatConversations, fetchAdminDirectConversations } from '../../features/shared/chat/services/chat.service'
import { cn } from '../../shared/lib/utils/cn'

export function AdminSidebar() {
  const { clearSession, session } = useAdminSession()
  const adminName = session?.admin.full_name || session?.admin.username || 'Admin'
  const adminRole = resolveAdminRoleLabel(session?.admin.role, session?.admin.role_label)
  const visibleItems = useMemo(() => getVisibleAdminNavItems(session?.admin.role), [session?.admin.role])
  const [isAccountModalOpen, setIsAccountModalOpen] = useState(false)
  const [isLoggingOut, setIsLoggingOut] = useState(false)

  const location = useLocation()
  const { echo } = useAdminSocket()
  const { unreadCount: notificationUnreadCount } = useAdminNotifications()
  const [chatUnreadCount, setChatUnreadCount] = useState(0)

  const loadUnreadCount = useCallback(async () => {
    try {
      const role = session?.admin?.role
      const adminId = session?.admin?.id
      let total = 0

      if (isBuildingManagerRole(role)) {
        const tenantRes = await fetchAdminChatConversations({ per_page: 100 })
        total += tenantRes.result?.data?.reduce((sum, item) => sum + Number(item.admin_unread_count || 0), 0) || 0
      }

      const directRes = await fetchAdminDirectConversations({ per_page: 100 })
      total += directRes.result?.data?.reduce((sum, item) => {
        const unread = Number(item.super_admin_id) === Number(adminId)
          ? item.admin_unread_count
          : item.tenant_unread_count
        return sum + Number(unread || 0)
      }, 0) || 0
      setChatUnreadCount(total)
    } catch (e) {
      console.error(e)
    }
  }, [session])

  useEffect(() => {
    if (!session?.admin?.id) return
    void loadUnreadCount()
  }, [session?.admin?.id, loadUnreadCount])

  useEffect(() => {
    if (!echo || !session?.admin?.id) return

    const channel = echo.private(`chat.admin.${session.admin.id}`)
    const handleUpdate = () => {
      void loadUnreadCount()
    }

    channel.listen('.ChatMessageSent', handleUpdate)
    channel.listen('.ChatConversationRead', handleUpdate)

    return () => {
      channel.stopListening('.ChatMessageSent')
      channel.stopListening('.ChatConversationRead')
    }
  }, [echo, session?.admin?.id, loadUnreadCount])

  async function handleLogout() {
    if (isLoggingOut) return

    try {
      setIsLoggingOut(true)
      await logoutAdmin()
    } catch (error) {
      console.error('Admin logout failed', error)
    } finally {
      clearSession()
      window.location.replace('/admin/login')
      setIsLoggingOut(false)
    }
  }

  return (
    <aside className="fixed left-0 top-0 z-40 hidden h-screen w-60 shrink-0 overflow-hidden border-r border-[#3d2a18]/10 bg-[#fffaf1]/92 text-[#24170d] shadow-2xl shadow-[#6b3f1d]/10 backdrop-blur-xl xl:flex xl:flex-col">
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_25%_8%,rgba(243,197,107,0.38),transparent_28%),radial-gradient(circle_at_110%_18%,rgba(15,118,110,0.14),transparent_30%),linear-gradient(180deg,rgba(255,250,241,0.95),rgba(244,239,230,0.88))]" />


      <div className="relative shrink-0 p-4">
        <div className="flex items-center gap-3 rounded-[1.7rem] border border-[#3d2a18]/10 bg-white/45 p-3 shadow-lg shadow-[#6b3f1d]/8 backdrop-blur-md">
          <div className="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-[#fffaf1] shadow-lg shadow-[#24170d]/25 ring-1 ring-[#3d2a18]/10">
            <img src="/images/stayhub.png" alt="StayHub" className="h-full w-full object-cover" />
          </div>
          <div className="min-w-0">
            <h1 className="text-xl font-black leading-none tracking-[-0.045em] text-[#24170d]">StayHub</h1>
          </div>
        </div>
      </div>

      {/* Combined Action Pill (Facebook-like Chat & Notifications) */}
      <div className="relative shrink-0 px-4 pb-3">
        <div className="flex items-center rounded-full border border-[#3d2a18]/10 bg-white/45 p-1 shadow-md shadow-[#6b3f1d]/5 backdrop-blur-md">
          {/* Chat Link */}
          <Link
            to="/admin/chat"
            className={cn(
              "relative flex flex-1 h-10 items-center justify-center rounded-full transition-all duration-200 active:scale-95",
              location.pathname.startsWith('/admin/chat')
                ? "bg-[#24170d] text-[#fff4df] shadow-md shadow-black/10"
                : "text-[#8b5e34] hover:bg-[#f3c56b]/15 hover:text-[#24170d]"
            )}
            aria-label="Đoạn chat"
            title="Đoạn chat"
          >
            <MessageCircle className="h-5 w-5 stroke-[2.5]" />
            {chatUnreadCount > 0 && (
              <span className="absolute right-5 top-1 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-rose-600 px-1 text-[9px] font-black text-white ring-1 ring-white">
                {chatUnreadCount}
              </span>
            )}
          </Link>

          {/* Vertical Divider */}
          <div className="h-5 w-[1px] bg-[#3d2a18]/12 shrink-0" />

          {/* Notifications Link */}
          <Link
            to="/admin/notifications"
            className={cn(
              "relative flex flex-1 h-10 items-center justify-center rounded-full transition-all duration-200 active:scale-95",
              location.pathname.startsWith('/admin/notifications')
                ? "bg-[#24170d] text-[#fff4df] shadow-md shadow-black/10"
                : "text-[#8b5e34] hover:bg-[#f3c56b]/15 hover:text-[#24170d]"
            )}
            aria-label="Thông báo"
            title="Thông báo"
          >
            <Bell className="h-5 w-5 stroke-[2.5]" />
            {notificationUnreadCount > 0 && (
              <span className="absolute right-5 top-1 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-rose-600 px-1 text-[9px] font-black text-white ring-1 ring-white">
                {notificationUnreadCount > 99 ? '99+' : notificationUnreadCount}
              </span>
            )}
          </Link>
        </div>
      </div>

      <AdminNavList items={visibleItems} variant="sidebar" />

      <div className="relative mt-auto shrink-0 p-4">
        <button
          type="button"
          onClick={() => setIsAccountModalOpen(true)}
          className="mb-4 w-full rounded-[1.45rem] border border-[#3d2a18]/10 bg-white/45 p-3 text-left shadow-lg shadow-[#6b3f1d]/8 backdrop-blur-md transition duration-200 hover:bg-[#fff7e8] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#0f766e]/15 2xl:p-4"
          aria-label="Mở cài đặt tài khoản"
        >
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm">
              {session?.admin.avatar_url ? (
                <img src={resolveAssetUrl(session.admin.avatar_url)} alt="Avatar" className="h-full w-full object-cover" />
              ) : (
                <User className="h-5 w-5" />
              )}
            </div>
            <div className="min-w-0 overflow-hidden">
              <p className="truncate text-xs font-black text-[#24170d]">{adminName}</p>
              <p className="truncate text-[9px] font-black uppercase tracking-[0.18em] text-[#0f766e]">{adminRole}</p>
            </div>
          </div>
        </button>
        <button
          type="button"
          onClick={handleLogout}
          disabled={isLoggingOut}
          className="group flex min-h-11 w-full cursor-pointer items-center justify-center gap-3 rounded-2xl border border-transparent px-4 py-3 text-xs font-black uppercase tracking-[0.18em] text-rose-700 transition-all duration-200 hover:border-rose-900/10 hover:bg-rose-50/80 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-rose-900/10 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
          aria-label="Đăng xuất khỏi trang quản trị"
        >
          <LogOut className="h-4 w-4 transition-transform group-hover:-translate-x-1" />
          <span>{isLoggingOut ? 'Đang đăng xuất...' : 'Đăng xuất'}</span>
        </button>
      </div>

      <AccountSettingsModal isOpen={isAccountModalOpen} onClose={() => setIsAccountModalOpen(false)} />
    </aside>
  )
}
