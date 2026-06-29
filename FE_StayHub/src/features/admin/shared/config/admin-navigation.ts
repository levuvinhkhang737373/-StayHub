import type { LucideIcon } from 'lucide-react'
import {
  ArrowRightLeft,
  BarChart3,
  BedDouble,
  Bell,
  MessageCircle,
  Boxes,
  Building2,
  Car,
  Camera,
  CreditCard,
  DoorOpen,
  FileText,
  Gauge,
  History,
  LayoutDashboard,
  Receipt,
  Settings,
  ShieldCheck,
  Tags,
  Users,
  Wrench,
  Zap,
} from 'lucide-react'
import { canManageContractsRole, isBuildingManagerRole, isSuperAdminRole } from '../../auth/hooks/use-admin-session'

export type AdminRouteAccess = 'all' | 'superadmin' | 'contract-manager'

export interface AdminNavItem {
  id: string
  label: string
  icon: LucideIcon
  group?: string
  href: string
  access: AdminRouteAccess
  readOnlyForAdmin?: boolean
  disabled?: boolean
}

export const ADMIN_NAV_ITEMS: AdminNavItem[] = [
  { id: 'dashboard', label: 'Tổng quan', icon: LayoutDashboard, href: '/admin/dashboard', access: 'all' },
  { id: 'facilities', label: 'Khu vực & Tòa nhà', icon: Building2, group: 'Quản lý lưu trú', href: '/admin/facilities', access: 'superadmin' },
  { id: 'room_types', label: 'Loại phòng', icon: BedDouble, group: 'Quản lý lưu trú', href: '/admin/room-types', access: 'superadmin' },
  { id: 'rooms', label: 'Quản lý phòng', icon: DoorOpen, group: 'Quản lý lưu trú', href: '/admin/rooms', access: 'all' },
  { id: 'asset_templates', label: 'Mẫu tài sản', icon: Boxes, group: 'Quản lý lưu trú', href: '/admin/asset-templates', access: 'superadmin' },
  { id: 'tenants', label: 'Khách thuê', icon: Users, group: 'Khách thuê & Hợp đồng', href: '/admin/tenants', access: 'all' },
  { id: 'transfer_room', label: 'Chuyển phòng', icon: ArrowRightLeft, group: 'Khách thuê & Hợp đồng', href: '/admin/transfer-room', access: 'all' },
  { id: 'contracts', label: 'Hợp đồng', icon: FileText, group: 'Khách thuê & Hợp đồng', href: '/admin/contracts', access: 'contract-manager' },
  { id: 'room_movements', label: 'Lịch sử phòng & cọc', icon: History, group: 'Khách thuê & Hợp đồng', href: '/admin/room-movements', access: 'all' },
  { id: 'services', label: 'Danh mục dịch vụ', icon: Settings, group: 'Dịch vụ & Điện nước', href: '/admin/services', access: 'superadmin' },
  { id: 'meters', label: 'Quản lý đồng hồ', icon: Gauge, group: 'Dịch vụ & Điện nước', href: '/admin/meters', access: 'all' },
  { id: 'meter_readings', label: 'Chốt điện nước', icon: Zap, group: 'Dịch vụ & Điện nước', href: '/admin/meter-readings', access: 'all' },
  { id: 'invoices', label: 'Hóa đơn', icon: Receipt, group: 'Tài chính & Báo cáo', href: '/admin/invoices', access: 'all' },
  { id: 'expenses', label: 'Phiếu chi', icon: CreditCard, group: 'Tài chính & Báo cáo', href: '/admin/expenses', access: 'all' },
  { id: 'expense_categories', label: 'Danh mục chi phí', icon: Tags, group: 'Tài chính & Báo cáo', href: '/admin/expense-categories', access: 'all', readOnlyForAdmin: true },
  { id: 'financials', label: 'Báo cáo lợi nhuận', icon: BarChart3, group: 'Tài chính & Báo cáo', href: '/admin/financials', access: 'all' },
  { id: 'vehicles', label: 'Bãi xe & Phương tiện', icon: Car, group: 'Vận hành', href: '/admin/vehicles', access: 'all' },
  { id: 'fire_safety', label: 'AI Camera', icon: Camera, group: 'Vận hành', href: '/admin/fire-safety', access: 'all' },
  { id: 'maintenance', label: 'Bảo trì', icon: Wrench, group: 'Vận hành', href: '/admin/maintenance', access: 'all' },
  { id: 'notifications', label: 'Thông báo', icon: Bell, group: 'Vận hành', href: '/admin/notifications', access: 'all' },
  { id: 'chat', label: 'Đoạn chat', icon: MessageCircle, group: 'Vận hành', href: '/admin/chat', access: 'all' },
  { id: 'system_users', label: 'Tài khoản admin', icon: ShieldCheck, group: 'Hệ thống', href: '/admin/system-users', access: 'superadmin' },
  { id: 'activity_logs', label: 'Nhật ký admin', icon: History, group: 'Hệ thống', href: '/admin/activity-logs', access: 'superadmin' },
  { id: 'settings', label: 'Cài đặt tòa nhà', icon: Settings, group: 'Hệ thống', href: '/admin/settings', access: 'all' },
]

export const SUPERADMIN_ROUTE_PREFIXES = [
  '/admin/facilities',
  '/admin/asset-templates',
  '/admin/room-types',
  '/admin/rooms',
  '/admin/services',
  '/admin/system-users',
  '/admin/activity-logs',
]

export function canAccessAdminItem(item: AdminNavItem, role?: string | number | null) {
  if (item.access === 'superadmin') return isSuperAdminRole(role)
  if (item.access === 'contract-manager') return canManageContractsRole(role)
  return true
}

export function getVisibleAdminNavItems(role?: string | number | null) {
  return ADMIN_NAV_ITEMS.filter((item) => canAccessAdminItem(item, role))
}

export function getActiveAdminNavItem(pathname: string, items: AdminNavItem[] = ADMIN_NAV_ITEMS) {
  return [...items]
    .sort((left, right) => right.href.length - left.href.length)
    .find((item) => pathname === item.href || pathname.startsWith(`${item.href}/`))
}

export function isSuperadminRoutePath(pathname: string) {
  return SUPERADMIN_ROUTE_PREFIXES.some((prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`))
}

export function getAdminRoleLabel(role?: string | number | null) {
  if (isSuperAdminRole(role)) return 'Quản trị tổng'
  if (isBuildingManagerRole(role)) return 'Quản lý tòa nhà'
  return 'Không xác định'
}
