import { useEffect, useState } from 'react'
import { Navigate, Outlet } from 'react-router-dom'
import { ADMIN_SESSION_KEY } from '../../features/admin/auth/hooks/use-admin-session'
import { fetchAdminMe } from '../../features/admin/auth/services/admin-auth.service'
import { AdminFooter } from './AdminFooter'
import { AdminSidebar } from './AdminSidebar'

export function AdminLayout() {
  const [authStatus, setAuthStatus] = useState<'checking' | 'authenticated' | 'guest'>(() => (localStorage.getItem(ADMIN_SESSION_KEY) ? 'checking' : 'guest'))

  useEffect(() => {
    let isMounted = true

    async function verifySession() {
      try {
        const response = await fetchAdminMe()
        localStorage.setItem(ADMIN_SESSION_KEY, JSON.stringify({ admin: response.result }))
        if (isMounted) setAuthStatus('authenticated')
      } catch {
        localStorage.removeItem(ADMIN_SESSION_KEY)
        if (isMounted) setAuthStatus('guest')
      }
    }

    if (authStatus === 'checking') {
      void verifySession()
    }

    return () => {
      isMounted = false
    }
  }, [authStatus])

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
    <div className="min-h-screen bg-[#f4efe6] bg-[radial-gradient(circle_at_20%_8%,rgba(243,197,107,0.22),transparent_30%),radial-gradient(circle_at_88%_14%,rgba(15,118,110,0.1),transparent_32%),linear-gradient(120deg,#fdf8ef_0%,#f4efe6_52%,#efe2cf_100%)]">
      <div className="flex min-h-screen min-w-0">
        <AdminSidebar />
        <div className="flex min-h-screen min-w-0 flex-1 flex-col">
          <main className="min-w-0 flex-1 p-3 sm:p-4 lg:p-6">
            <Outlet />
          </main>
          <AdminFooter />
        </div>
      </div>
    </div>
  )
}
