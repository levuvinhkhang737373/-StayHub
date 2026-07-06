import { ChevronLeft, ChevronRight } from 'lucide-react'
import { cn } from '../../../../shared/lib/utils/cn'
import { AdminSelect } from './AdminSelect'

export interface AdminPaginationMeta {
  current_page?: number
  from?: number | null
  last_page?: number
  path?: string
  per_page?: number
  to?: number | null
  total?: number
}

interface AdminPaginationProps {
  meta: AdminPaginationMeta | null
  currentPage: number
  perPage: number
  totalItems: number
  itemLabel: string
  isLoading?: boolean
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
}

const perPageOptions = [
  { value: 5, label: '5 dòng', tone: 'default' as const },
  { value: 10, label: '10 dòng', tone: 'default' as const },
  { value: 20, label: '20 dòng', tone: 'default' as const },
  { value: 50, label: '50 dòng', tone: 'default' as const },
]

export function AdminPagination({ meta, currentPage, perPage, totalItems, itemLabel, isLoading = false, onPageChange, onPerPageChange }: AdminPaginationProps) {
  const fallbackTotalPages = Math.ceil(totalItems / perPage) || 1
  const totalPages = Math.max(1, meta?.last_page ?? fallbackTotalPages)
  const safeCurrentPage = Math.max(1, Math.min(currentPage, totalPages))
  const paginationStart = meta?.from ?? (totalItems === 0 ? 0 : (safeCurrentPage - 1) * perPage + 1)
  const paginationEnd = meta?.to ?? Math.min(safeCurrentPage * perPage, totalItems)
  const total = meta?.total ?? totalItems
  const pages = new Set<number>([1, totalPages, safeCurrentPage - 1, safeCurrentPage, safeCurrentPage + 1])
  const visiblePages = Array.from(pages)
    .filter((page) => page >= 1 && page <= totalPages)
    .sort((a, b) => a - b)

  if (isLoading || total === 0) return null

  return (
    <div className="flex flex-col gap-4 border-t border-[#3d2a18]/8 bg-[#fff7e8]/72 px-5 py-4 md:flex-row md:items-center md:justify-between">
      <div className="text-sm font-bold text-[#6f6254]">
        Hiển thị <span className="font-black text-[#24170d] tabular-nums">{paginationStart}</span> - <span className="font-black text-[#24170d] tabular-nums">{paginationEnd}</span> / <span className="font-black text-[#24170d] tabular-nums">{total}</span> {itemLabel}
      </div>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <label className="flex items-center gap-2 text-sm font-black text-[#6f6254]">
          Mỗi trang
          <AdminSelect value={perPage} options={perPageOptions} className="w-36" menuPlacement="top" onChange={(nextValue) => onPerPageChange(Number(nextValue))} />
        </label>

        <div className="flex items-center gap-1">
          <button type="button" onClick={() => onPageChange(Math.max(1, safeCurrentPage - 1))} disabled={safeCurrentPage <= 1} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 text-[#8b5e34] transition hover:border-[#f3c56b] hover:text-[#a65f16] disabled:cursor-not-allowed disabled:opacity-40" aria-label="Trang trước">
            <ChevronLeft className="h-4 w-4" />
          </button>

          {visiblePages.map((page, index) => {
            const previousPage = visiblePages[index - 1]
            const hasGap = previousPage && page - previousPage > 1

            return (
              <div key={page} className="flex items-center gap-1">
                {hasGap && <span className="px-1 text-xs font-black text-[#8b5e34]/60">...</span>}
                <button type="button" onClick={() => onPageChange(page)} className={cn('inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition', page === safeCurrentPage ? 'border-[#24170d] bg-[#24170d] text-[#fff4df] shadow-sm' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15')} aria-current={page === safeCurrentPage ? 'page' : undefined}>
                  {page}
                </button>
              </div>
            )
          })}

          <button type="button" onClick={() => onPageChange(Math.min(totalPages, safeCurrentPage + 1))} disabled={safeCurrentPage >= totalPages} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 text-[#8b5e34] transition hover:border-[#f3c56b] hover:text-[#a65f16] disabled:cursor-not-allowed disabled:opacity-40" aria-label="Trang sau">
            <ChevronRight className="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  )
}
