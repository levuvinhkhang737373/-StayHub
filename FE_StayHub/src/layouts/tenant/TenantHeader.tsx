import { Link, useLocation } from 'react-router-dom'

export function TenantHeader() {
  const location = useLocation()

  const navItems = [
    { path: '/tenant/dashboard', label: 'Trang chủ' },
    { path: '/tenant/invoices', label: 'Hóa đơn' },
  ]

  return (
    <header className="border-b border-slate-200 bg-white px-4 py-3 lg:px-6 flex items-center justify-between">
      <div className="flex items-center gap-6">
        <p className="text-sm font-bold text-slate-850 tracking-tight flex items-center gap-1.5">
          <span className="h-2.5 w-2.5 rounded-full bg-[#eab308]"></span>
          StayHub Tenant
        </p>
        <nav className="flex items-center gap-4">
          {navItems.map((item) => {
            const isActive = location.pathname === item.path
            return (
              <Link
                key={item.path}
                to={item.path}
                className={`text-xs font-bold transition-colors ${
                  isActive ? 'text-[#eab308]' : 'text-slate-500 hover:text-slate-800'
                }`}
              >
                {item.label}
              </Link>
            )
          })}
        </nav>
      </div>
    </header>
  )
}

