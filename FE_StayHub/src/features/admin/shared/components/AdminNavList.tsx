import { memo, useMemo } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { motion } from 'motion/react'
import { cn } from '../../../../shared/lib/utils/cn'
import type { AdminNavItem } from '../config/admin-navigation'
import { getActiveAdminNavItem } from '../config/admin-navigation'
import { useAdminNotifications } from '../../notifications/hooks/admin-notification-context'

interface AdminNavListProps {
  items: AdminNavItem[]
  variant: 'sidebar' | 'drawer'
  onNavigate?: () => void
}

export const AdminNavList = memo(function AdminNavList({ items, variant, onNavigate }: AdminNavListProps) {
  const location = useLocation()
  const { unreadCount } = useAdminNotifications()
  const activeItem = useMemo(() => getActiveAdminNavItem(location.pathname, items), [items, location.pathname])
  const groupedItems = useMemo(() => {
    return items.reduce<Record<string, AdminNavItem[]>>((acc, item) => {
      const group = item.group || 'Chính'
      if (!acc[group]) acc[group] = []
      acc[group].push(item)
      return acc
    }, {})
  }, [items])

  return (
    <nav className={cn('relative flex-1 space-y-8 overflow-y-auto', variant === 'sidebar' ? 'custom-scrollbar px-3' : 'px-4 pb-4')} aria-label="Điều hướng quản trị">
      {Object.entries(groupedItems).map(([group, groupItems]) => (
        <div key={group}>
          <h2 className="mb-4 px-4 text-[10px] font-black uppercase leading-none tracking-[0.24em] text-[#8b5e34]/55">
            {group}
          </h2>
          <div className="space-y-1.5">
            {groupItems.map((item) => {
              const isActive = activeItem?.id === item.id
              const Icon = item.icon
              const content = (
                <>
                  <Icon className={cn('h-4 w-4 shrink-0 transition-colors duration-200', isActive ? 'text-[#f3c56b] stroke-[2.8]' : item.disabled ? 'text-[#8b5e34]/45' : 'text-[#8b5e34]/70 group-hover:text-[#24170d]')} />
                  <span className={cn('min-w-0 flex-1 tracking-tight', variant === 'drawer' ? 'truncate' : 'whitespace-nowrap text-[13px]', isActive && 'font-black text-[#fff4df]')}>{item.label}</span>
                  {/* Notification count badge removed per request */}
                  {item.readOnlyForAdmin && variant === 'drawer' ? <span className="ml-auto rounded-full bg-[#0f766e]/10 px-2 py-0.5 text-[10px] font-black text-[#0f5f59]">Xem</span> : null}
                  {isActive && variant === 'sidebar' ? <motion.div layoutId="active-nav" className="absolute right-3 h-1.5 w-1.5 rounded-full bg-[#0f766e] ring-2 ring-[#f3c56b]/35" /> : null}
                </>
              )

              const className = cn(
                'group relative flex min-h-11 w-full items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-bold transition-all duration-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#a65f16]/20',
                variant === 'sidebar' ? 'justify-start px-3' : 'justify-start px-4',
                item.disabled && 'cursor-not-allowed text-[#8b5e34]/45 opacity-55',
                !item.disabled && 'cursor-pointer active:scale-[0.98]',
                isActive ? 'bg-[#24170d] text-[#fff4df] shadow-xl shadow-[#24170d]/18' : !item.disabled && 'text-[#6f6254] hover:bg-[#f3c56b]/18 hover:text-[#24170d]',
              )

              if (item.disabled) {
                return (
                  <button key={item.id} type="button" disabled className={className} aria-label={item.label} title={item.label}>
                    {content}
                  </button>
                )
              }

              return (
                <Link key={item.id} to={item.href} className={className} aria-label={item.label} title={item.label} onClick={onNavigate}>
                  {content}
                </Link>
              )
            })}
          </div>
        </div>
      ))}
    </nav>
  )
})
