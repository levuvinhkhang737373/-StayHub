import { memo, useId, useMemo, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { Search, X } from 'lucide-react'
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

function normalizeNavSearch(value: string) {
  return value
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim()
}

export const AdminNavList = memo(function AdminNavList({ items, variant, onNavigate }: AdminNavListProps) {
  const location = useLocation()
  const searchInputId = useId()
  const [searchTerm, setSearchTerm] = useState('')
  useAdminNotifications()
  const activeItem = useMemo(() => getActiveAdminNavItem(location.pathname, items), [items, location.pathname])
  const normalizedSearch = useMemo(() => normalizeNavSearch(searchTerm), [searchTerm])
  const filteredItems = useMemo(() => {
    if (!normalizedSearch) return items

    return items.filter((item) => {
      const group = item.group || 'Chính'
      return [item.id, item.label, group, item.href].some((value) => normalizeNavSearch(value).includes(normalizedSearch))
    })
  }, [items, normalizedSearch])
  const groupedItems = useMemo(() => {
    return filteredItems.reduce<Record<string, AdminNavItem[]>>((acc, item) => {
      const group = item.group || 'Chính'
      if (!acc[group]) acc[group] = []
      acc[group].push(item)
      return acc
    }, {})
  }, [filteredItems])
  const hasSearch = searchTerm.trim().length > 0

  return (
    <nav className={cn('relative flex-1 space-y-8 overflow-y-auto', variant === 'sidebar' ? 'custom-scrollbar px-3' : 'px-4 pb-4')} aria-label="Điều hướng quản trị">
      <div className="sticky top-0 z-20 -mx-1 bg-gradient-to-b from-[#fffaf1]/96 via-[#fffaf1]/82 to-[#fffaf1]/0 px-1 pb-3 pt-1 backdrop-blur-sm">
        <label htmlFor={searchInputId} className="sr-only">
          Tìm kiếm chức năng quản trị
        </label>
        <div className="group relative rounded-[1.05rem] bg-[#efe2cc]/55 p-1 shadow-inner shadow-[#6b3f1d]/5 ring-1 ring-[#8b5e34]/10 transition-all duration-200 focus-within:bg-[#fff8eb] focus-within:ring-[#0f766e]/25">
          <Search className="pointer-events-none absolute left-3.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[#8b5e34]/45 transition-colors group-focus-within:text-[#0f766e]" />
          <input
            id={searchInputId}
            type="search"
            value={searchTerm}
            onChange={(event) => setSearchTerm(event.target.value)}
            placeholder="Tìm chức năng"
            className="h-8 w-full appearance-none rounded-xl border-0 bg-transparent pl-8 pr-8 text-[12px] font-extrabold tracking-[-0.01em] text-[#24170d] outline-none placeholder:text-[#9b7655]/55 [&::-webkit-search-cancel-button]:appearance-none"
            autoComplete="off"
          />
          {hasSearch ? (
            <button
              type="button"
              onClick={() => setSearchTerm('')}
              className="absolute right-2 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full text-[#8b5e34]/55 transition hover:bg-[#24170d]/8 hover:text-[#24170d] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#a65f16]/16"
              aria-label="Xóa tìm kiếm menu"
            >
              <X className="h-3 w-3" />
            </button>
          ) : null}
        </div>
      </div>

      {filteredItems.length === 0 ? (
        <div className="mx-1 rounded-[1.35rem] border border-dashed border-[#8b5e34]/25 bg-white/45 px-4 py-5 text-center shadow-sm shadow-[#6b3f1d]/8">
          <p className="text-xs font-black text-[#24170d]">Không thấy menu phù hợp</p>
          <p className="mt-1 text-[11px] font-semibold leading-5 text-[#8b5e34]/70">Thử tìm theo tên chức năng hoặc nhóm quản trị.</p>
        </div>
      ) : null}

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
