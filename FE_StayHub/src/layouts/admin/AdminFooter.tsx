import { HeartHandshake, ShieldCheck, Sparkles } from 'lucide-react'

export function AdminFooter() {
  return (
    <footer className="relative overflow-hidden px-3 pb-3 sm:px-4 lg:px-6 lg:pb-6">
      <div className="pointer-events-none absolute inset-x-6 top-0 h-px bg-gradient-to-r from-transparent via-[#8b5e34]/20 to-transparent" />
      <div className="relative overflow-hidden rounded-[1.75rem] border border-[#3d2a18]/10 bg-[#fffaf1]/78 px-4 py-4 text-[#6f6254] shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-xl sm:px-5 lg:px-6">
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_8%_0%,rgba(243,197,107,0.32),transparent_28%),radial-gradient(circle_at_92%_20%,rgba(15,118,110,0.12),transparent_28%),linear-gradient(135deg,rgba(255,255,255,0.82),rgba(255,244,223,0.36))]" />
        <div className="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full border border-[#f3c56b]/35" />
        <div className="pointer-events-none absolute -bottom-14 left-1/3 h-24 w-24 rounded-full bg-[#0f766e]/8 blur-2xl" />

        <div className="relative flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div className="flex items-center gap-3">
            <div className="relative flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-[#fffaf1] shadow-lg shadow-[#24170d]/20 ring-1 ring-[#3d2a18]/10">
              <img src="/images/stayhub.png" alt="StayHub" className="h-full w-full object-cover" />
            </div>
            <div>
              <p className="text-sm font-black leading-tight tracking-[-0.025em] text-[#24170d]">StayHub Admin</p>
              <p className="mt-1 text-[11px] font-bold text-[#8b5e34]/70">© {new Date().getFullYear()} StayHub. Vận hành khu trọ thông minh.</p>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2 text-[11px] font-black uppercase tracking-[0.16em] text-[#8b5e34]">
            <span className="inline-flex items-center gap-2 rounded-full border border-[#3d2a18]/10 bg-white/55 px-3 py-2 shadow-sm shadow-[#6b3f1d]/5">
              <ShieldCheck className="h-3.5 w-3.5 text-[#0f766e]" />
              Bảo mật
            </span>
            <span className="inline-flex items-center gap-2 rounded-full border border-[#3d2a18]/10 bg-white/55 px-3 py-2 shadow-sm shadow-[#6b3f1d]/5">
              <Sparkles className="h-3.5 w-3.5 text-[#c7861e]" />
              Tối ưu
            </span>
            <span className="inline-flex items-center gap-2 rounded-full border border-[#3d2a18]/10 bg-white/55 px-3 py-2 shadow-sm shadow-[#6b3f1d]/5">
              <HeartHandshake className="h-3.5 w-3.5 text-rose-600" />
              Tin cậy
            </span>
          </div>
        </div>
      </div>
    </footer>
  )
}
