import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { Menu, User, X, Bell, MessageCircle } from 'lucide-react'
import { AnimatePresence, motion } from 'motion/react'
import { isBuildingManagerRole, useAdminSession } from '../../features/admin/auth/hooks/use-admin-session'
import { logoutAdmin } from '../../features/admin/auth/services/admin-auth.service'
import { AdminNavList } from '../../features/admin/shared/components/AdminNavList'
import { getActiveAdminNavItem, getVisibleAdminNavItems, resolveAdminRoleLabel } from '../../features/admin/shared/config/admin-navigation'
import { AccountSettingsModal } from './AccountSettingsModal'
import { useAdminNotifications } from '../../features/admin/notifications/hooks/admin-notification-context'
import { useAdminSocket } from '../../shared/lib/socket/socket-context'
import { fetchAdminChatConversations, fetchAdminDirectConversations } from '../../features/shared/chat/services/chat.service'
import { cn } from '../../shared/lib/utils/cn'

export function AdminHeader() {
  const location = useLocation()
  const { clearSession, session } = useAdminSession()
  const visibleItems = useMemo(() => getVisibleAdminNavItems(session?.admin.role), [session?.admin.role])
  const activeItem = useMemo(() => getActiveAdminNavItem(location.pathname, visibleItems), [location.pathname, visibleItems])
  const adminName = session?.admin.full_name || session?.admin.username || 'Admin'
  const adminRole = resolveAdminRoleLabel(session?.admin.role, session?.admin.role_label)
  const [isDrawerOpen, setIsDrawerOpen] = useState(false)
  const [isAccountModalOpen, setIsAccountModalOpen] = useState(false)
  const [isLoggingOut, setIsLoggingOut] = useState(false)

  const { echo } = useAdminSocket()
  const { unreadCount: notificationUnreadCount } = useAdminNotifications()
  const [chatUnreadCount, setChatUnreadCount] = useState(0)

  const loadUnreadCount = useCallback(async () => {
    try {
      const role = session?.admin?.role
      const adminId = session?.admin?.id
      let total = 0

      if (isBuildingManagerRole(role)) {
        const tenantRes = await fetchAdminChatConversations({ unread: 1, per_page: 100 })
        total += tenantRes.result?.data?.reduce((sum, item) => sum + Number(item.admin_unread_count || 0), 0) || 0
      }

      const directRes = await fetchAdminDirectConversations({ unread: 1, per_page: 100 })
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
      channel.stopListening('.ChatMessageSent', handleUpdate)
      channel.stopListening('.ChatConversationRead', handleUpdate)
    }
  }, [echo, session?.admin?.id, loadUnreadCount])
  const drawerRef = useRef<HTMLDivElement | null>(null)
  const menuButtonRef = useRef<HTMLButtonElement | null>(null)
  const openedPathnameRef = useRef(location.pathname)

  useEffect(() => {
    if (!isDrawerOpen) return

    openedPathnameRef.current = location.pathname
    const scrollY = window.scrollY
    const previousOverflow = document.body.style.overflow
    const previousPosition = document.body.style.position
    const previousTop = document.body.style.top
    const previousWidth = document.body.style.width

    document.body.style.overflow = 'hidden'
    document.body.style.position = 'fixed'
    document.body.style.top = `-${scrollY}px`
    document.body.style.width = '100%'

    drawerRef.current?.focus()

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setIsDrawerOpen(false)
        menuButtonRef.current?.focus()
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => {
      document.body.style.overflow = previousOverflow
      document.body.style.position = previousPosition
      document.body.style.top = previousTop
      document.body.style.width = previousWidth
      
      if (window.location.pathname === openedPathnameRef.current) {
        window.scrollTo(0, scrollY)
      } else {
        window.scrollTo(0, 0)
      }
      document.removeEventListener('keydown', handleKeyDown)
    }
  }, [isDrawerOpen, location.pathname])

  const handleLogout = useCallback(async () => {
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
  }, [clearSession, isLoggingOut])

  const closeDrawer = useCallback(() => {
    setIsDrawerOpen(false)
    menuButtonRef.current?.focus()
  }, [])

  return (
    <>
      <header className="sticky top-0 z-30 border-b border-[#3d2a18]/10 bg-[#fffaf1]/88 px-3 py-3 text-[#24170d] shadow-lg shadow-[#6b3f1d]/8 backdrop-blur-xl sm:px-4 lg:px-6 xl:hidden">
        <div className="flex items-center justify-between gap-3">
          <button
            ref={menuButtonRef}
            type="button"
            onClick={() => setIsDrawerOpen(true)}
            className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-white/65 text-[#8b5e34] transition hover:border-[#f3c56b]/40 hover:bg-[#f3c56b]/10 hover:text-[#24170d] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#f3c56b]/15"
            aria-label="Mở menu quản trị"
            aria-haspopup="dialog"
            aria-expanded={isDrawerOpen}
          >
            <Menu className="h-[18px] w-[18px]" />
          </button>

          <div className="min-w-0 flex-1">
            <p className="truncate text-[10px] font-black uppercase tracking-[0.22em] text-[#0f766e]">{adminRole}</p>
            <h1 className="truncate text-base font-black tracking-[-0.025em] text-[#24170d] sm:text-lg">{activeItem?.label || 'StayHub Admin'}</h1>
          </div>

          <div className="flex items-center gap-2">
            {/* Combined Action Pill (Facebook-like Chat & Notifications) */}
            <div className="flex items-center rounded-full border border-[#3d2a18]/10 bg-white/45 p-0.5 shadow-md shadow-[#6b3f1d]/5 backdrop-blur-md">
              <Link
                to="/admin/chat"
                className={cn(
                  "relative flex h-9 w-9 items-center justify-center rounded-full transition-all duration-200 active:scale-95",
                  location.pathname.startsWith('/admin/chat')
                    ? "bg-[#24170d] text-[#fff4df]"
                    : "text-[#8b5e34] hover:bg-[#f3c56b]/15 hover:text-[#24170d]"
                )}
                aria-label="Đoạn chat"
                title="Đoạn chat"
              >
                <MessageCircle className="h-[18px] w-[18px] stroke-[2.5]" />
                {chatUnreadCount > 0 && (
                  <span className="absolute right-1 top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 text-[8px] font-black text-white ring-1 ring-white">
                    {chatUnreadCount}
                  </span>
                )}
              </Link>

              <div className="h-4 w-[1px] bg-[#3d2a18]/12 shrink-0" />

              <Link
                to="/admin/notifications"
                className={cn(
                  "relative flex h-9 w-9 items-center justify-center rounded-full transition-all duration-200 active:scale-95",
                  location.pathname.startsWith('/admin/notifications')
                    ? "bg-[#24170d] text-[#fff4df]"
                    : "text-[#8b5e34] hover:bg-[#f3c56b]/15 hover:text-[#24170d]"
                )}
                aria-label="Thông báo"
                title="Thông báo"
              >
                <Bell className="h-[18px] w-[18px] stroke-[2.5]" />
                {notificationUnreadCount > 0 && (
                  <span className="absolute right-1 top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 text-[8px] font-black text-white ring-1 ring-white">
                    {notificationUnreadCount > 99 ? '99+' : notificationUnreadCount}
                  </span>
                )}
              </Link>
            </div>

            <button
              type="button"
              onClick={() => setIsAccountModalOpen(true)}
              className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-white/65 text-[#8b5e34] transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#0f766e]/15"
              aria-label="Mở cài đặt tài khoản"
            >
              <User className="h-5 w-5 stroke-[2.5]" />
            </button>
          </div>
        </div>
      </header>

      <AnimatePresence>
        {isDrawerOpen && (
          <div className="fixed inset-0 z-[80] xl:hidden" role="dialog" aria-modal="true" aria-labelledby="admin-drawer-title">
            <motion.button
              type="button"
              aria-label="Đóng menu quản trị"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={closeDrawer}
              className="absolute inset-0 bg-[#24170d]/60 backdrop-blur-sm"
            />
            <motion.div
              ref={drawerRef}
              tabIndex={-1}
              initial={{ x: '-100%', opacity: 0.96 }}
              animate={{ x: 0, opacity: 1 }}
              exit={{ x: '-100%', opacity: 0.96 }}
              transition={{ duration: 0.22, ease: 'easeOut' }}
              className="relative flex h-dvh w-[min(22rem,calc(100vw-2rem))] flex-col overflow-hidden border-r border-[#3d2a18]/10 bg-[#fffaf1] text-[#24170d] shadow-2xl shadow-[#24170d]/35 outline-none"
            >
              <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_4%,rgba(243,197,107,0.42),transparent_30%),radial-gradient(circle_at_100%_18%,rgba(15,118,110,0.14),transparent_32%),linear-gradient(180deg,rgba(255,250,241,0.96),rgba(244,239,230,0.92))]" />
              <div className="relative flex shrink-0 items-center justify-between gap-3 p-4">
                <div className="flex min-w-0 items-center gap-3">
                  <div className="flex h-11 w-11 shrink-0 overflow-hidden rounded-2xl bg-[#fffaf1] shadow-lg shadow-[#24170d]/20 ring-1 ring-[#3d2a18]/10">
                    <img src="/images/stayhub.png" alt="StayHub" className="h-full w-full object-cover" />
                  </div>
                  <div className="min-w-0">
                    <h2 id="admin-drawer-title" className="text-lg font-black tracking-[-0.035em] text-[#24170d]">StayHub</h2>
                    <p className="truncate text-[10px] font-black uppercase tracking-[0.18em] text-[#0f766e]">{adminRole}</p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={closeDrawer}
                  className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-white/60 text-[#8b5e34] transition hover:bg-rose-50 hover:text-rose-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-rose-100"
                  aria-label="Đóng menu quản trị"
                >
                  <X className="h-5 w-5 stroke-[2.5]" />
                </button>
              </div>

              {/* Icon Bar like Facebook in drawer */}
              <div className="relative shrink-0 px-4 pb-3">
                <div className="flex items-center rounded-full border border-[#3d2a18]/10 bg-white/45 p-1 shadow-md shadow-[#6b3f1d]/5 backdrop-blur-md">
                  {/* Chat Link */}
                  <Link
                    to="/admin/chat"
                    onClick={closeDrawer}
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
                    onClick={closeDrawer}
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

              <AdminNavList items={visibleItems} variant="drawer" onNavigate={closeDrawer} />

              <div className="relative mt-auto shrink-0 border-t border-[#3d2a18]/10 p-4">
                <button
                  type="button"
                  onClick={() => {
                    setIsAccountModalOpen(true)
                    setIsDrawerOpen(false)
                  }}
                  className="mb-3 w-full rounded-[1.35rem] border border-[#3d2a18]/10 bg-white/55 p-3 text-left shadow-sm transition hover:bg-[#fff7e8] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#0f766e]/15"
                >
                  <p className="truncate text-sm font-black text-[#24170d]">{adminName}</p>
                  <p className="mt-1 truncate text-[10px] font-black uppercase tracking-[0.18em] text-[#0f766e]">Cài đặt tài khoản</p>
                </button>
                <button
                  type="button"
                  onClick={handleLogout}
                  disabled={isLoggingOut}
                  className="flex min-h-11 w-full items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 px-4 text-xs font-black uppercase tracking-[0.16em] text-rose-700 transition hover:bg-rose-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-rose-100 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {isLoggingOut ? 'Đang đăng xuất...' : 'Đăng xuất'}
                </button>
              </div>
            </motion.div>
          </div>
        )}
      </AnimatePresence>

      <AccountSettingsModal isOpen={isAccountModalOpen} onClose={() => setIsAccountModalOpen(false)} />
    </>
  )
}
