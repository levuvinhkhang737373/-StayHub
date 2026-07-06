import { useMemo, useState } from 'react'
import { LogOut, User } from 'lucide-react'
import { useAdminSession } from '../../features/admin/auth/hooks/use-admin-session'
import { logoutAdmin } from '../../features/admin/auth/services/admin-auth.service'
import { AdminNavList } from '../../features/admin/shared/components/AdminNavList'
import { getAdminRoleLabel, getVisibleAdminNavItems } from '../../features/admin/shared/config/admin-navigation'
import { AccountSettingsModal } from './AccountSettingsModal'
import { resolveAssetUrl } from '../../shared/lib/utils/asset-url'

export function AdminSidebar() {
  const { clearSession, session } = useAdminSession()
  const adminName = session?.admin.full_name || 'Admin'
  const adminRole = session?.admin.role_label || getAdminRoleLabel(session?.admin.role)
  const visibleItems = useMemo(() => getVisibleAdminNavItems(session?.admin.role), [session?.admin.role])
  const [isAccountModalOpen, setIsAccountModalOpen] = useState(false)
  const [isLoggingOut, setIsLoggingOut] = useState(false)

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
