import { useMemo, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { motion } from 'motion/react'
import {
  BarChart3,
  BedDouble,
  Bell,
  Boxes,
  Building2,
  Car,
  CreditCard,
  DoorOpen,
  FileText,
  LayoutDashboard,
  LogOut,
  Receipt,
  Settings,
  ShieldCheck,
  User,
  Users,
  Wrench,
  Zap,
} from 'lucide-react'
import { ADMIN_SESSION_KEY, isSuperAdminRole, useAdminSession } from '../../features/admin/auth/hooks/use-admin-session'
import { logoutAdmin } from '../../features/admin/auth/services/admin-auth.service'
import { cn } from '../../shared/lib/utils/cn'
import { AccountSettingsModal } from './AccountSettingsModal'

interface NavItem {
  id: string
  label: string
  icon: React.ElementType
  group?: string
  href: string
  disabled?: boolean
  superAdminOnly?: boolean
}

const NAV_ITEMS: NavItem[] = [
  { id: 'dashboard', label: 'Tổng quan', icon: LayoutDashboard, href: '/admin/dashboard' },
  { id: 'facilities', label: 'Khu vực & Tòa nhà', icon: Building2, group: 'Cơ sở vật chất', href: '/admin/facilities', superAdminOnly: true },
  { id: 'asset_templates', label: 'Mẫu tài sản', icon: Boxes, group: 'Cơ sở vật chất', href: '/admin/asset-templates' },
  { id: 'room_types', label: 'Loại phòng', icon: BedDouble, group: 'Cơ sở vật chất', href: '/admin/room-types' },
  { id: 'rooms', label: 'Quản lý Phòng', icon: DoorOpen, group: 'Cơ sở vật chất', href: '/admin/rooms' },
  { id: 'tenants', label: 'Khách thuê', icon: Users, group: 'Khách thuê & HĐ', href: '/admin/tenants' },
  { id: 'contracts', label: 'Hợp đồng', icon: FileText, group: 'Khách thuê & HĐ', href: '/admin/contracts' },
  { id: 'meters', label: 'Chốt điện nước', icon: Zap, group: 'Tài chính', href: '/admin/meters' },
  { id: 'invoices', label: 'Phiếu thu', icon: Receipt, group: 'Tài chính', href: '/admin/invoices' },
  { id: 'expenses', label: 'Phiếu chi', icon: CreditCard, group: 'Tài chính', href: '/admin/expenses' },
  { id: 'financials', label: 'Báo cáo Lợi nhuận', icon: BarChart3, group: 'Tài chính', href: '/admin/financials' },
  { id: 'vehicles', label: 'Bãi xe & Phương tiện', icon: Car, group: 'Vận hành', href: '/admin/vehicles' },
  { id: 'maintenance', label: 'Bảo trì', icon: Wrench, group: 'Vận hành', href: '/admin/maintenance' },
  { id: 'notifications', label: 'Thông báo', icon: Bell, group: 'Vận hành', href: '/admin/notifications' },
  { id: 'system_users', label: 'Nhân sự & Phân quyền', icon: ShieldCheck, group: 'Hệ thống', href: '/admin/system-users' },
  { id: 'settings', label: 'Cài đặt chung', icon: Settings, group: 'Hệ thống', href: '/admin/settings' },
]

export function AdminSidebar() {
  const location = useLocation()
  const navigate = useNavigate()
  const { session } = useAdminSession()
  const isSuperAdmin = isSuperAdminRole(session?.admin.role)
  const adminName = session?.admin.full_name || 'Admin'
  const adminRole = isSuperAdmin ? 'Quản trị tổng' : session?.admin.role || 'Quản trị viên'
  const [isAccountModalOpen, setIsAccountModalOpen] = useState(false)
  const [isLoggingOut, setIsLoggingOut] = useState(false)

  const visibleItems = useMemo(() => NAV_ITEMS.filter((item) => !item.superAdminOnly || isSuperAdmin), [isSuperAdmin])

  const groupedItems = useMemo(() => {
    return visibleItems.reduce<Record<string, NavItem[]>>((acc, item) => {
      const group = item.group || 'Chính'
      if (!acc[group]) acc[group] = []
      acc[group].push(item)
      return acc
    }, {})
  }, [visibleItems])

  const activeTab = visibleItems.find((item) => !item.disabled && location.pathname.startsWith(item.href))?.id ?? 'dashboard'

  async function handleLogout() {
    if (isLoggingOut) return

    try {
      setIsLoggingOut(true)
      await logoutAdmin()
    } catch (error) {
      console.error('Admin logout failed', error)
    } finally {
      localStorage.removeItem(ADMIN_SESSION_KEY)
      navigate('/admin/login', { replace: true })
      setIsLoggingOut(false)
    }
  }

  return (
    <aside className="relative z-40 hidden h-screen w-20 shrink-0 overflow-hidden border-r border-[#3d2a18]/10 bg-[#fffaf1]/90 text-[#24170d] shadow-2xl shadow-[#6b3f1d]/10 backdrop-blur-xl xl:flex xl:flex-col 2xl:w-64">
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_25%_8%,rgba(243,197,107,0.38),transparent_28%),radial-gradient(circle_at_110%_18%,rgba(15,118,110,0.14),transparent_30%),linear-gradient(180deg,rgba(255,250,241,0.95),rgba(244,239,230,0.88))]" />
      <div className="pointer-events-none absolute inset-0 opacity-[0.12] [background-image:linear-gradient(90deg,rgba(77,51,25,0.18)_1px,transparent_1px),linear-gradient(rgba(77,51,25,0.12)_1px,transparent_1px)] [background-size:56px_56px]" />
      <div className="relative p-4 2xl:p-8">
        <div className="flex items-center gap-3 rounded-[1.7rem] border border-[#3d2a18]/10 bg-white/45 p-2 shadow-lg shadow-[#6b3f1d]/8 backdrop-blur-md 2xl:p-3">
          <div className="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-[#fffaf1] shadow-lg shadow-[#24170d]/25 ring-1 ring-[#3d2a18]/10">
            <img src="/images/stayhub.png" alt="StayHub" className="h-full w-full object-cover" />
          </div>
          <div className="hidden min-w-0 2xl:block">
            <h1 className="text-xl font-black leading-none tracking-[-0.045em] text-[#24170d]">StayHub</h1>
          </div>
        </div>
      </div>

      <nav className="custom-scrollbar relative flex-1 space-y-8 overflow-y-auto px-3 2xl:px-4">
        {Object.entries(groupedItems).map(([group, items]) => (
          <div key={group}>
            <h2 className="mb-4 hidden px-4 text-[10px] font-black uppercase leading-none tracking-[0.24em] text-[#8b5e34]/55 2xl:block">
              {group}
            </h2>
            <div className="space-y-1.5">
              {items.map((item) => {
                const isActive = activeTab === item.id
                const content = (
                  <>
                    <item.icon className={cn('h-4 w-4 shrink-0 transition-colors duration-200', isActive ? 'text-[#f3c56b] stroke-[2.8]' : item.disabled ? 'text-[#8b5e34]/45' : 'text-[#8b5e34]/70 group-hover:text-[#24170d]')} />
                    <span className={cn('hidden tracking-tight 2xl:inline', isActive && 'font-black text-[#fff4df]')}>{item.label}</span>
                    {isActive && <motion.div layoutId="active-nav" className="absolute right-3 hidden h-1.5 w-1.5 rounded-full bg-[#0f766e] ring-2 ring-[#f3c56b]/35 2xl:block" />}
                  </>
                )

                const className = cn(
                  'group relative flex min-h-11 w-full items-center justify-center gap-3 rounded-2xl px-3 py-3 text-sm font-bold transition-all duration-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#a65f16]/20 2xl:justify-start 2xl:px-4',
                  item.disabled && 'cursor-not-allowed text-[#8b5e34]/45 opacity-55',
                  !item.disabled && 'cursor-pointer active:scale-[0.98]',
                  isActive
                    ? 'bg-[#24170d] text-[#fff4df] shadow-xl shadow-[#24170d]/18'
                    : !item.disabled && 'text-[#6f6254] hover:bg-[#f3c56b]/18 hover:text-[#24170d]',
                )

                if (item.disabled) {
                  return (
                    <button key={item.id} type="button" disabled className={className}>
                      {content}
                    </button>
                  )
                }

                return (
                  <Link key={item.id} to={item.href} className={className}>
                    {content}
                  </Link>
                )
              })}
            </div>
          </div>
        ))}
      </nav>

      <div className="relative mt-auto p-3 2xl:p-6">
        <button
          type="button"
          onClick={() => setIsAccountModalOpen(true)}
          className="mb-4 w-full rounded-[1.45rem] border border-[#3d2a18]/10 bg-white/45 p-3 text-left shadow-lg shadow-[#6b3f1d]/8 backdrop-blur-md transition duration-200 hover:bg-[#fff7e8] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#0f766e]/15 2xl:p-4"
        >
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm">
              <User className="h-5 w-5" />
            </div>
            <div className="hidden overflow-hidden 2xl:block">
              <p className="truncate text-xs font-black text-[#24170d]">{adminName}</p>
              <p className="truncate text-[9px] font-black uppercase tracking-[0.18em] text-[#0f766e]">{adminRole}</p>
            </div>
          </div>
        </button>
        <button
          type="button"
          onClick={handleLogout}
          disabled={isLoggingOut}
          className="group flex min-h-11 w-full cursor-pointer items-center justify-center gap-3 rounded-2xl border border-transparent px-3 py-3 text-xs font-black uppercase tracking-[0.18em] text-rose-700 transition-all duration-200 hover:border-rose-900/10 hover:bg-rose-50/80 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-rose-900/10 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 2xl:px-4"
        >
          <LogOut className="h-4 w-4 transition-transform group-hover:-translate-x-1" />
          <span className="hidden 2xl:inline">{isLoggingOut ? 'Đang đăng xuất...' : 'Đăng xuất'}</span>
        </button>
      </div>

      <AccountSettingsModal isOpen={isAccountModalOpen} onClose={() => setIsAccountModalOpen(false)} />
    </aside>
  )
}
