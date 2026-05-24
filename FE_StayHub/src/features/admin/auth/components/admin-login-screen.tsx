import { useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { AdminLoginForm } from './admin-login-form'
import { ADMIN_SESSION_KEY, useAdminSession } from '../hooks/use-admin-session'
import type { AdminLoginResult } from '../types/admin-auth.model'

export function AdminLoginScreen() {
  const navigate = useNavigate()
  const { saveSession } = useAdminSession()

  useEffect(() => {
    localStorage.removeItem(ADMIN_SESSION_KEY)
  }, [])

  async function handleLoginSuccess(payload: AdminLoginResult) {
    saveSession(payload)
    navigate('/admin/dashboard', { replace: true })
  }

  return (
    <main className="relative min-h-dvh overflow-hidden bg-[#f4efe6] text-[#20170f]">
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_12%_12%,rgba(180,83,9,0.16),transparent_28%),radial-gradient(circle_at_85%_18%,rgba(13,148,136,0.12),transparent_30%),linear-gradient(120deg,#fdf8ef_0%,#f4efe6_48%,#efe2cf_100%)]" />
      <div className="pointer-events-none absolute inset-0 opacity-[0.18] [background-image:linear-gradient(90deg,rgba(77,51,25,0.18)_1px,transparent_1px),linear-gradient(rgba(77,51,25,0.12)_1px,transparent_1px)] [background-size:88px_88px]" />
      <div className="pointer-events-none absolute -left-20 top-1/4 h-[34rem] w-[34rem] rounded-full border border-[#8b5e34]/15" />
      <div className="pointer-events-none absolute -right-28 bottom-10 h-[26rem] w-[26rem] rotate-12 border border-[#0f766e]/15" />

      <div className="relative mx-auto grid min-h-dvh w-full max-w-[92vw] grid-cols-1 items-center px-2 py-5 sm:px-3 lg:grid-cols-10 lg:gap-3 lg:px-2 lg:py-6">
        <section className="relative col-span-6 hidden h-[min(790px,calc(100dvh-3rem))] overflow-hidden rounded-[2.5rem] border border-[#3d2a18]/10 bg-[#fffaf1]/70 shadow-2xl shadow-[#6b3f1d]/10 lg:block">
          <div className="absolute left-8 top-8 z-10 inline-flex items-center gap-3 rounded-full border border-[#3d2a18]/10 bg-white/70 px-4 py-3 shadow-lg shadow-[#6b3f1d]/8 backdrop-blur-md">
            <img src="/images/stayhub.png" alt="StayHub" className="h-7 w-7 rounded-full object-cover ring-1 ring-[#3d2a18]/10" />
            <span className="text-sm font-black uppercase tracking-[0.28em] text-[#24170d]">StayHub</span>
          </div>

          <div className="absolute left-10 top-30 z-10 max-w-[36rem] xl:left-12">
            <h1 className="mt-5 text-5xl font-black leading-[1.02] tracking-[-0.065em] text-[#24170d] xl:text-7xl">
              Đăng Nhập.
              <span className="block pl-12 text-[#a65f16]">Mở quyền.</span>
              <span className="block">Vận hành.</span>
            </h1>
          </div>


          <div className="absolute right-12 top-1/2 flex h-120 w-120 -translate-y-1/2 items-center justify-center xl:right-16">
            <img
              src="/images/stayhub.png"
              alt="Minh họa hệ thống quản lý StayHub"
              className="h-full w-full object-contain opacity-100 mix-blend-multiply"
            />
          </div>
        </section>

        <section className="col-span-4 flex items-center justify-center py-8 lg:h-[min(790px,calc(100dvh-3rem))] lg:py-0">
          <div className="flex h-full w-full max-w-[630px] flex-col">
            <div className="mb-8 text-center lg:hidden">
              <p className="text-xs font-black uppercase tracking-[0.42em] text-[#8a4f18]">StayHub Admin</p>
              <h1 className="mt-3 text-5xl font-black leading-none tracking-[-0.07em] text-[#24170d]">Keycard Portal</h1>
            </div>
            <AdminLoginForm onLoginSuccess={handleLoginSuccess} />
          </div>
        </section>
      </div>
    </main>
  )
}
