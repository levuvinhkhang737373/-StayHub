import { useCallback, useEffect, useMemo, useState } from 'react'
import {
  CalendarDays,
  Search,
  ShieldCheck,
  WalletCards,
  X,
} from 'lucide-react'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatCurrency, formatDate } from '../../../../shared/lib/utils/format'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { AdminPagination } from '../../shared/components/AdminPagination'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import { fetchAdminDebts } from '../services/debts.service'
import type { AdminDebtFilters, AdminDebtPaginationMeta, AdminDebtResource, AdminDebtStats, AdminDebtStatus } from '../types/debt-api.model'

const debtStatusOptions: Array<{ value: AdminDebtStatus; label: string; tone: 'default' | 'success' | 'warning' | 'danger' }> = [
  { value: 'all', label: 'Tất cả công nợ', tone: 'default' },
  { value: 'collectible', label: 'Còn có thể thu', tone: 'success' },
  { value: 'rolled', label: 'Đã chuyển nợ', tone: 'warning' },
  { value: 'overdue', label: 'Quá hạn', tone: 'danger' },
]

const defaultStats: AdminDebtStats = {
  total_collectible_amount: '0.00',
  total_rolled_outstanding_amount: '0.00',
  invoice_count: 0,
  collectible_count: 0,
  rolled_count: 0,
  overdue_count: 0,
}

const inputClass = 'h-11 w-full rounded-xl border border-[#3d2a18]/10 bg-white px-4 text-sm font-bold text-[#24170d] outline-none transition focus:border-[#8b5e34]/45 focus:ring-4 focus:ring-[#d6b170]/18 placeholder:text-[#8b5e34]/55'

export function DebtsScreen() {
  const [debts, setDebts] = useState<AdminDebtResource[]>([])
  const [stats, setStats] = useState<AdminDebtStats>(defaultStats)
  const [meta, setMeta] = useState<AdminDebtPaginationMeta | null>(null)
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [keyword, setKeyword] = useState('')
  const [debtStatus, setDebtStatus] = useState<AdminDebtStatus>('all')
  const [buildingId, setBuildingId] = useState('')
  const [billingMonth, setBillingMonth] = useState('')
  const [billingYear, setBillingYear] = useState('')
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(10)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const buildingOptions = useMemo(() => [
    { value: '', label: 'Tất cả tòa nhà', tone: 'default' as const },
    ...buildings.map((building) => ({ value: building.id, label: building.name, tone: 'default' as const })),
  ], [buildings])

  const filters = useMemo<AdminDebtFilters>(() => ({
    keyword: keyword.trim() || undefined,
    debt_status: debtStatus,
    building_id: buildingId ? Number(buildingId) : undefined,
    billing_month: billingMonth ? Number(billingMonth) : undefined,
    billing_year: billingYear ? Number(billingYear) : undefined,
    page,
    per_page: perPage,
  }), [billingMonth, billingYear, buildingId, debtStatus, keyword, page, perPage])

  const loadDebts = useCallback(async () => {
    setIsLoading(true)
    setError(null)
    try {
      const response = await fetchAdminDebts(filters)
      const result = response.result
      setDebts(result?.data || [])
      setMeta(result?.pagination || result?.meta || null)
      setStats(result?.stats || defaultStats)
    } catch (err) {
      if (keyword.trim() !== '') {
        setError(err instanceof ApiError ? err.message : 'Không thể tải danh sách công nợ')
      }
    } finally {
      setIsLoading(false)
    }
  }, [filters])

  useEffect(() => {
    void fetchAdminBuildings({ per_page: 100 })
      .then((response) => setBuildings(response.result || []))
      .catch(() => setBuildings([]))
  }, [])

  useEffect(() => {
    void loadDebts()
  }, [loadDebts])

  useEffect(() => {
    function handleRefresh() {
      console.log('WS Event: Refreshing debts list')
      void loadDebts()
    }
    window.addEventListener('invoice-refresh', handleRefresh)
    window.addEventListener('contract-refresh', handleRefresh)
    window.addEventListener('contract-deposit-paid', handleRefresh)
    window.addEventListener('notification-refresh', handleRefresh)
    return () => {
      window.removeEventListener('invoice-refresh', handleRefresh)
      window.removeEventListener('contract-refresh', handleRefresh)
      window.removeEventListener('contract-deposit-paid', handleRefresh)
      window.removeEventListener('notification-refresh', handleRefresh)
    }
  }, [loadDebts])

  const resetFilters = () => {
    setKeyword('')
    setDebtStatus('all')
    setBuildingId('')
    setBillingMonth('')
    setBillingYear('')
    setPage(1)
  }

  const hasActiveFilters = Boolean(
    keyword.trim() ||
    debtStatus !== 'all' ||
    buildingId ||
    billingMonth ||
    billingYear
  )

  return (
    <section className="space-y-5 sm:space-y-6 text-[#24170d]">
      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-5 text-[#fff4df] sm:p-6 lg:p-7">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_14%,rgba(243,197,107,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(15,118,110,0.26),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
          <div className="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <span className="block text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]/80">TÀI CHÍNH & BÁO CÁO</span>
              <h1 className="mt-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem] flex items-center gap-3">
                <WalletCards className="h-8 w-8 text-[#f3c56b] shrink-0" />
                Bảng quản lý công nợ
              </h1>
            </div>
          </div>

          <div className="relative mt-5 grid grid-cols-2 gap-2 sm:grid-cols-4">
            <MetricCard label="Cần thu" value={formatCurrency(stats.total_collectible_amount)} tone="emerald" />
            <MetricCard label="Đã chuyển nợ" value={formatCurrency(stats.total_rolled_outstanding_amount)} tone="amber" />
            <MetricCard label="Hóa đơn" value={stats.invoice_count.toLocaleString('vi-VN')} tone="neutral" />
            <MetricCard label="Quá hạn" value={stats.overdue_count.toLocaleString('vi-VN')} tone="rose" />
          </div>
        </div>
      </section>

      <div
        className={cn(
          'rounded-3xl border px-4 text-sm font-black shadow-sm transition-all duration-500 ease-in-out transform overflow-hidden',
          error
            ? 'opacity-100 max-h-20 py-3 translate-y-0 scale-100'
            : 'opacity-0 max-h-0 py-0 -translate-y-2 scale-95 pointer-events-none border-transparent',
          'border-rose-200 bg-rose-50 text-rose-700'
        )}
      >
        {error}
      </div>

      <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
        <div className="border-b border-[#3d2a18]/10 bg-[#fff8eb]/85 p-4 sm:p-5">
          <div className="grid gap-3 lg:grid-cols-[1.3fr_0.8fr_0.8fr_0.55fr_0.55fr_auto]">
            <div className="relative min-w-0">
              <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
              <input
                value={keyword}
                onChange={(event) => { setKeyword(event.target.value); setPage(1) }}
                className={`${inputClass} pl-11 pr-28`}
                placeholder="Tìm mã hóa đơn, phòng, hợp đồng, khách thuê..."
              />
              <button
                type="button"
                onClick={resetFilters}
                disabled={!hasActiveFilters}
                className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] disabled:opacity-45"
              >
                <X className="h-3.5 w-3.5" /> Xóa lọc
              </button>
            </div>
            <AdminSelect value={debtStatus} options={debtStatusOptions} onChange={(value) => { setDebtStatus(value as AdminDebtStatus); setPage(1) }} />
            <AdminSelect value={buildingId} options={buildingOptions} onChange={(value) => { setBuildingId(String(value)); setPage(1) }} />
            <input value={billingMonth} onChange={(event) => { setBillingMonth(event.target.value); setPage(1) }} className={inputClass} type="number" min={1} max={12} placeholder="Tháng" />
            <input value={billingYear} onChange={(event) => { setBillingYear(event.target.value); setPage(1) }} className={inputClass} type="number" min={2020} max={2100} placeholder="Năm" />
          </div>
        </div>



        <div className="overflow-x-auto custom-scrollbar">
          <table className="w-full min-w-[1120px] text-left">
            <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
              <tr>
                <th className="px-5 py-4">Hóa đơn</th>
                <th className="px-5 py-4">Khách thuê</th>
                <th className="px-5 py-4">Kỳ/Hạn</th>
                <th className="px-5 py-4">Còn lại</th>
                <th className="px-5 py-4">Có thể thu</th>
                <th className="px-5 py-4 text-center">Chuyển nợ</th>
                <th className="px-5 py-4 text-center">Trạng thái</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#3d2a18]/8 bg-[#fffaf1]/70">
              {isLoading && Array.from({ length: 5 }).map((_, index) => <SkeletonRow key={index} />)}
              {!isLoading && debts.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-5 py-12 text-center text-sm font-bold text-[#6f6254]">
                    Không có công nợ phù hợp bộ lọc.
                  </td>
                </tr>
              )}
              {!isLoading && debts.map((debt) => <DebtRow key={debt.invoice.id} debt={debt} />)}
            </tbody>
          </table>
        </div>

        <AdminPagination
          meta={meta}
          currentPage={page}
          perPage={perPage}
          totalItems={meta?.total ?? debts.length}
          itemLabel="dòng công nợ"
          isLoading={isLoading}
          onPageChange={setPage}
          onPerPageChange={(nextPerPage) => {
            setPerPage(nextPerPage)
            setPage(1)
          }}
        />
      </section>
    </section>
  )
}

function DebtRow({ debt }: { debt: AdminDebtResource }) {
  const tenantNames = debt.tenants?.map((tenant) => tenant.full_name).filter(Boolean).join(', ') || 'Chưa rõ khách thuê'
  const isRolled = debt.debt.is_debt_rolled_over

  return (
    <tr className="align-middle transition hover:bg-[#f3c56b]/10">
      <td className="px-5 py-4">
        <p className="font-black text-[#24170d]">{debt.invoice.invoice_code}</p>
        <p className="mt-1 text-xs font-bold text-[#8b5e34]">{debt.building?.name || '—'} · Phòng {debt.room?.room_number || '—'}</p>
        <p className="mt-1 text-xs font-bold text-[#6f6254]">{debt.contract?.contract_code || 'Chưa có hợp đồng'}</p>
      </td>
      <td className="px-5 py-4">
        <p className="font-bold text-[#24170d]">{tenantNames}</p>
        <p className="mt-1 text-xs font-semibold text-[#8b5e34]">{debt.tenants?.find((tenant) => tenant.phone)?.phone || '—'}</p>
      </td>
      <td className="px-5 py-4">
        <div className="inline-flex items-center gap-2 rounded-full border border-[#3d2a18]/10 bg-[#fffaf1] px-3 py-1 text-xs font-black text-[#24170d]">
          <CalendarDays className="h-3.5 w-3.5 text-[#8b5e34]" /> {String(debt.period.billing_month || '').padStart(2, '0')}/{debt.period.billing_year}
        </div>
        <p className="mt-2 text-xs font-bold text-[#6f6254]">Hạn: {formatDate(debt.period.due_date)}</p>
      </td>
      <td className="px-5 py-4 font-black text-[#24170d]">{formatCurrency(debt.amounts.remaining_amount)}</td>
      <td className="px-5 py-4">
        <p className={cn('font-black', Number(debt.amounts.collectible_remaining_amount) > 0 ? 'text-emerald-700' : 'text-[#8b5e34]')}>{formatCurrency(debt.amounts.collectible_remaining_amount)}</p>
        {isRolled && <p className="mt-1 text-[11px] font-bold text-amber-700">Đã khóa thu riêng</p>}
      </td>
      <td className="px-5 py-4 text-center">
        {isRolled ? (
          <div className="mx-auto max-w-xs rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800 text-left">
            <p>Đã chuyển sang <span className="font-black">{debt.rollover?.rolled_to_invoice_code || 'hóa đơn sau'}</span></p>
            <p className="mt-1">Còn trong rollover: {formatCurrency(debt.amounts.rolled_outstanding_amount)}</p>
          </div>
        ) : debt.rollover?.rolled_sources?.length ? (
          <div className="mx-auto max-w-xs rounded-2xl border border-[#0f766e]/20 bg-[#0f766e]/10 px-3 py-2 text-xs font-bold text-[#0f5f59]">
            Đang thu {debt.rollover.rolled_sources.length} khoản nợ cũ
          </div>
        ) : <span className="text-xs font-bold text-[#8b5e34]">Không chuyển nợ</span>}
      </td>
      <td className="px-5 py-4 text-center"><DebtStatusBadge debt={debt} /></td>
    </tr>
  )
}

function DebtStatusBadge({ debt }: { debt: AdminDebtResource }) {
  const status = debt.debt.debt_status
  const label = status === 'rolled'
    ? 'Đã chuyển nợ'
    : status === 'overdue'
      ? 'Quá hạn'
      : status === 'partial'
        ? 'Thu một phần'
        : status === 'collectible'
          ? 'Cần thu'
          : 'Đã xử lý'
  const classes = status === 'rolled'
    ? 'border-amber-200 bg-amber-50 text-amber-700'
    : status === 'overdue'
      ? 'border-rose-200 bg-rose-50 text-rose-700'
      : status === 'collectible' || status === 'partial'
        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
        : 'border-stone-200 bg-stone-100 text-stone-600'

  return (
    <span className={cn('inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[10px] font-black shadow-sm', classes)}>
      <ShieldCheck className="h-3.5 w-3.5" /> {label}
    </span>
  )
}

function MetricCard({ label, value, tone = 'neutral' }: { label: string; value: string | number; tone?: 'neutral' | 'rose' | 'amber' | 'emerald' }) {
  const toneClassNames = {
    neutral: 'border-[#f8e8c8]/12 bg-[#f8e8c8]/10 text-[#fff4df]',
    rose: 'border-rose-400/25 bg-rose-500/10 text-rose-200',
    amber: 'border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#fff4df]',
    emerald: 'border-[#0f766e]/35 bg-[#0f766e]/16 text-[#c8fff4]',
  }[tone]

  return (
    <div className={cn('rounded-2xl border px-3 py-2.5 backdrop-blur', toneClassNames)}>
      <p className="text-[10px] font-black uppercase tracking-[0.18em] opacity-60">{label}</p>
      <p className="mt-0.5 text-2xl font-black tracking-tight tabular-nums">{value}</p>
    </div>
  )
}

function SkeletonRow() {
  return (
    <tr>
      {Array.from({ length: 7 }).map((_, index) => (
        <td key={index} className="px-5 py-4"><div className="h-10 animate-pulse rounded-2xl bg-stone-100" /></td>
      ))}
    </tr>
  )
}
