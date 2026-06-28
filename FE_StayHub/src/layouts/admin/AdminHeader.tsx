import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { Menu, User, X } from 'lucide-react'
import { AnimatePresence, motion } from 'motion/react'
import { useAdminSession } from '../../features/admin/auth/hooks/use-admin-session'
import { logoutAdmin } from '../../features/admin/auth/services/admin-auth.service'
import { AdminNavList } from '../../features/admin/shared/components/AdminNavList'
import { getActiveAdminNavItem, getAdminRoleLabel, getVisibleAdminNavItems } from '../../features/admin/shared/config/admin-navigation'
import { AccountSettingsModal } from './AccountSettingsModal'

export function AdminHeader() {
  const location = useLocation()
  const { clearSession, session } = useAdminSession()
  const visibleItems = useMemo(() => getVisibleAdminNavItems(session?.admin.role), [session?.admin.role])
  const activeItem = useMemo(() => getActiveAdminNavItem(location.pathname, visibleItems), [location.pathname, visibleItems])
  const adminName = session?.admin.full_name || 'Admin'
  const adminRole = session?.admin.role_label || getAdminRoleLabel(session?.admin.role)
  const [isDrawerOpen, setIsDrawerOpen] = useState(false)
  const [isAccountModalOpen, setIsAccountModalOpen] = useState(false)
  const [isLoggingOut, setIsLoggingOut] = useState(false)
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
            className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-white/65 text-[#8b5e34] shadow-sm transition hover:border-[#f3c56b]/45 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#f3c56b]/20"
            aria-label="Mở menu quản trị"
            aria-haspopup="dialog"
            aria-expanded={isDrawerOpen}
          >
            <Menu className="h-5 w-5" />
          </button>

          <div className="min-w-0 flex-1">
            <p className="truncate text-[10px] font-black uppercase tracking-[0.22em] text-[#0f766e]">{adminRole}</p>
            <h1 className="truncate text-base font-black tracking-[-0.025em] text-[#24170d] sm:text-lg">{activeItem?.label || 'StayHub Admin'}</h1>
          </div>

          <button
            type="button"
            onClick={() => setIsAccountModalOpen(true)}
            className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-white/65 text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#0f766e]/15"
            aria-label="Mở cài đặt tài khoản"
          >
            <User className="h-5 w-5" />
          </button>
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
                  <X className="h-5 w-5" />
                </button>
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
