import { useEffect, useState } from 'react'
import { Navigate, Outlet } from 'react-router-dom'
import { useAdminSession } from '../../features/admin/auth/hooks/use-admin-session'
import { AdminFooter } from './AdminFooter'
import { AdminHeader } from './AdminHeader'
import { AdminSidebar } from './AdminSidebar'

export function AdminLayout() {
  const { refreshSession } = useAdminSession()
  const [authStatus, setAuthStatus] = useState<'checking' | 'authenticated' | 'guest'>('checking')

  useEffect(() => {
    let isMounted = true

    async function verifySession() {
      const session = await refreshSession()

      if (isMounted) {
        setAuthStatus(session?.admin ? 'authenticated' : 'guest')
      }
    }

    void verifySession()

    return () => {
      isMounted = false
    }
  }, [refreshSession])

  if (authStatus === 'checking') {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[#f4efe6] bg-[radial-gradient(circle_at_20%_10%,rgba(243,197,107,0.3),transparent_28%),radial-gradient(circle_at_85%_18%,rgba(15,118,110,0.12),transparent_30%)] text-sm font-black text-[#6f6254]">
        Đang kiểm tra phiên đăng nhập...
      </div>
    )
  }

  if (authStatus === 'guest') {
    return <Navigate to="/admin/login" replace />
  }

  return (
    <div className="min-h-screen w-full max-w-full overflow-x-hidden bg-[#f4efe6] bg-[radial-gradient(circle_at_20%_8%,rgba(243,197,107,0.22),transparent_30%),radial-gradient(circle_at_88%_14%,rgba(15,118,110,0.1),transparent_32%),linear-gradient(120deg,#fdf8ef_0%,#f4efe6_52%,#efe2cf_100%)]">
      <div className="flex min-h-screen w-full max-w-full min-w-0">
        <AdminSidebar />
        <div className="flex min-h-screen w-full max-w-full min-w-0 flex-1 flex-col xl:pl-60">
          <AdminHeader />
          <main className="w-full max-w-full min-w-0 flex-1 p-3 sm:p-4 lg:p-6">
            <Outlet />
          </main>
          <AdminFooter />
        </div>
      </div>
    </div>
  )
}
