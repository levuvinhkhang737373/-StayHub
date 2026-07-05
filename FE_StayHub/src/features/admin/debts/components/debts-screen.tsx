import { useCallback, useEffect, useMemo, useState } from 'react'
import {
  AlertTriangle,
  ArrowRight,
  Banknote,
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  Loader2,
  RefreshCw,
  Search,
  ShieldCheck,
  WalletCards,
} from 'lucide-react'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatCurrency, formatDate } from '../../../../shared/lib/utils/format'
import { AdminSelect } from '../../shared/components/AdminSelect'
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

const perPageOptions = [
  { value: 10, label: '10 dòng', tone: 'default' as const },
  { value: 20, label: '20 dòng', tone: 'default' as const },
  { value: 50, label: '50 dòng', tone: 'default' as const },
]

const defaultStats: AdminDebtStats = {
  total_collectible_amount: '0.00',
  total_rolled_outstanding_amount: '0.00',
  invoice_count: 0,
  collectible_count: 0,
  rolled_count: 0,
  overdue_count: 0,
}

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
      setError(err instanceof ApiError ? err.message : 'Không thể tải danh sách công nợ')
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

  const resetFilters = () => {
    setKeyword('')
    setDebtStatus('all')
    setBuildingId('')
    setBillingMonth('')
    setBillingYear('')
    setPage(1)
  }

  const lastPage = meta?.last_page || 1

  return (
    <section className="space-y-6 p-4 sm:p-6 lg:p-8">
      <div className="overflow-hidden rounded-[2rem] border border-[#f8e8c8]/12 bg-[#24170d] text-[#fff4df] shadow-2xl shadow-[#24170d]/20">
        <div className="grid gap-6 p-6 lg:grid-cols-[1.15fr_0.85fr] lg:p-8">
          <div>
            <p className="text-xs font-black uppercase tracking-[0.28em] text-[#d6b170]">StayHub receivables ledger</p>
            <h1 className="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Bảng quản lý công nợ</h1>
            <p className="mt-3 max-w-2xl text-sm font-semibold leading-6 text-[#f8e8c8]/78">
              Theo dõi số còn có thể thu, khoản đã chuyển sang hóa đơn sau và trạng thái quá hạn mà không cộng trùng dòng tiền.
            </p>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <MetricCard icon={<Banknote className="h-5 w-5" />} label="Cần thu" value={formatCurrency(stats.total_collectible_amount)} tone="emerald" />
            <MetricCard icon={<ArrowRight className="h-5 w-5" />} label="Đã chuyển nợ" value={formatCurrency(stats.total_rolled_outstanding_amount)} tone="amber" />
            <MetricCard icon={<WalletCards className="h-5 w-5" />} label="Hóa đơn" value={stats.invoice_count.toLocaleString('vi-VN')} />
            <MetricCard icon={<AlertTriangle className="h-5 w-5" />} label="Quá hạn" value={stats.overdue_count.toLocaleString('vi-VN')} tone="rose" />
          </div>
        </div>
      </div>

      <div className="rounded-[1.75rem] border border-[#3d2a18]/10 bg-[#fffaf1] p-4 shadow-sm">
        <div className="grid gap-3 lg:grid-cols-[1.3fr_0.8fr_0.8fr_0.55fr_0.55fr_auto]">
          <label className="relative">
            <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8b5e34]" />
            <input value={keyword} onChange={(event) => { setKeyword(event.target.value); setPage(1) }} className={inputClass + ' pl-11'} placeholder="Tìm mã hóa đơn, phòng, hợp đồng, khách thuê" />
          </label>
          <AdminSelect value={debtStatus} options={debtStatusOptions} onChange={(value) => { setDebtStatus(value as AdminDebtStatus); setPage(1) }} />
          <AdminSelect value={buildingId} options={buildingOptions} onChange={(value) => { setBuildingId(String(value)); setPage(1) }} />
          <input value={billingMonth} onChange={(event) => { setBillingMonth(event.target.value); setPage(1) }} className={inputClass} type="number" min={1} max={12} placeholder="Tháng" />
          <input value={billingYear} onChange={(event) => { setBillingYear(event.target.value); setPage(1) }} className={inputClass} type="number" min={2020} max={2100} placeholder="Năm" />
          <button type="button" onClick={resetFilters} className="inline-flex h-11 items-center justify-center rounded-xl border border-[#3d2a18]/10 px-4 text-sm font-black text-[#6f6254] transition hover:bg-[#efe2cf]">Xóa lọc</button>
        </div>
      </div>

      {error && (
        <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-700">{error}</div>
      )}

      <div className="overflow-hidden rounded-[1.75rem] border border-[#3d2a18]/10 bg-white shadow-xl shadow-[#24170d]/5">
        <div className="flex flex-col gap-3 border-b border-[#3d2a18]/10 bg-[#fffaf1] p-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-sm font-black text-[#24170d]">Sổ công nợ hóa đơn</p>
            <p className="text-xs font-bold text-[#8b5e34]">Nợ cũ đã chuyển sẽ hiển thị hóa đơn đích để tránh thu trùng.</p>
          </div>
          <button type="button" onClick={() => void loadDebts()} disabled={isLoading} className="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#24170d] px-4 text-sm font-black text-[#fff4df] transition hover:bg-[#3d2a18] disabled:opacity-60">
            {isLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
            Làm mới
          </button>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[1120px] text-left text-sm">
            <thead className="bg-[#24170d] text-[11px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
              <tr>
                <th className="px-4 py-3">Hóa đơn</th>
                <th className="px-4 py-3">Khách thuê</th>
                <th className="px-4 py-3">Kỳ/Hạn</th>
                <th className="px-4 py-3 text-right">Còn lại</th>
                <th className="px-4 py-3 text-right">Có thể thu</th>
                <th className="px-4 py-3">Chuyển nợ</th>
                <th className="px-4 py-3">Trạng thái</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#3d2a18]/8">
              {isLoading && Array.from({ length: 5 }).map((_, index) => <SkeletonRow key={index} />)}
              {!isLoading && debts.length === 0 && (
                <tr><td colSpan={7} className="px-4 py-12 text-center text-sm font-bold text-[#6f6254]">Không có công nợ phù hợp bộ lọc.</td></tr>
              )}
              {!isLoading && debts.map((debt) => <DebtRow key={debt.invoice.id} debt={debt} />)}
            </tbody>
          </table>
        </div>

        <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fffaf1] p-4 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-xs font-bold text-[#6f6254]">Tổng {meta?.total ?? debts.length} dòng công nợ</p>
          <div className="flex items-center gap-2">
            <AdminSelect value={perPage} options={perPageOptions} onChange={(value) => { setPerPage(Number(value)); setPage(1) }} menuPlacement="top" />
            <button type="button" disabled={page <= 1 || isLoading} onClick={() => setPage((current) => Math.max(1, current - 1))} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-white text-[#24170d] disabled:opacity-40"><ChevronLeft className="h-4 w-4" /></button>
            <span className="text-sm font-black text-[#24170d]">{page}/{lastPage}</span>
            <button type="button" disabled={page >= lastPage || isLoading} onClick={() => setPage((current) => Math.min(lastPage, current + 1))} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-white text-[#24170d] disabled:opacity-40"><ChevronRight className="h-4 w-4" /></button>
          </div>
        </div>
      </div>
    </section>
  )
}

function DebtRow({ debt }: { debt: AdminDebtResource }) {
  const tenantNames = debt.tenants?.map((tenant) => tenant.full_name).filter(Boolean).join(', ') || 'Chưa rõ khách thuê'
  const isRolled = debt.debt.is_debt_rolled_over

  return (
    <tr className="align-top transition hover:bg-[#fffaf1]">
      <td className="px-4 py-4">
        <p className="font-black text-[#24170d]">{debt.invoice.invoice_code}</p>
        <p className="mt-1 text-xs font-bold text-[#8b5e34]">{debt.building?.name || '—'} · Phòng {debt.room?.room_number || '—'}</p>
        <p className="mt-1 text-xs font-bold text-[#6f6254]">{debt.contract?.contract_code || 'Chưa có hợp đồng'}</p>
      </td>
      <td className="px-4 py-4">
        <p className="font-bold text-[#24170d]">{tenantNames}</p>
        <p className="mt-1 text-xs font-semibold text-[#8b5e34]">{debt.tenants?.find((tenant) => tenant.phone)?.phone || '—'}</p>
      </td>
      <td className="px-4 py-4">
        <div className="inline-flex items-center gap-2 rounded-full border border-[#3d2a18]/10 bg-[#fffaf1] px-3 py-1 text-xs font-black text-[#24170d]"><CalendarDays className="h-3.5 w-3.5" /> {String(debt.period.billing_month || '').padStart(2, '0')}/{debt.period.billing_year}</div>
        <p className="mt-2 text-xs font-bold text-[#6f6254]">Hạn: {formatDate(debt.period.due_date)}</p>
      </td>
      <td className="px-4 py-4 text-right font-black text-[#24170d]">{formatCurrency(debt.amounts.remaining_amount)}</td>
      <td className="px-4 py-4 text-right">
        <p className={cn('font-black', Number(debt.amounts.collectible_remaining_amount) > 0 ? 'text-emerald-700' : 'text-[#8b5e34]')}>{formatCurrency(debt.amounts.collectible_remaining_amount)}</p>
        {isRolled && <p className="mt-1 text-[11px] font-bold text-amber-700">Đã khóa thu riêng</p>}
      </td>
      <td className="px-4 py-4">
        {isRolled ? (
          <div className="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">
            <p>Đã chuyển sang <span className="font-black">{debt.rollover?.rolled_to_invoice_code || 'hóa đơn sau'}</span></p>
            <p className="mt-1">Còn trong rollover: {formatCurrency(debt.amounts.rolled_outstanding_amount)}</p>
          </div>
        ) : debt.rollover?.rolled_sources?.length ? (
          <div className="rounded-2xl border border-[#0f766e]/20 bg-[#0f766e]/10 px-3 py-2 text-xs font-bold text-[#0f5f59]">
            Đang thu {debt.rollover.rolled_sources.length} khoản nợ cũ
          </div>
        ) : <span className="text-xs font-bold text-[#8b5e34]">Không chuyển nợ</span>}
      </td>
      <td className="px-4 py-4"><DebtStatusBadge debt={debt} /></td>
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
        : 'border-[#3d2a18]/10 bg-[#efe2cf] text-[#6f6254]'

  return <span className={cn('inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-black', classes)}><ShieldCheck className="h-3.5 w-3.5" /> {label}</span>
}

function MetricCard({ icon, label, value, tone = 'neutral' }: { icon: React.ReactNode; label: string; value: React.ReactNode; tone?: 'neutral' | 'emerald' | 'rose' | 'amber' }) {
  const toneClasses = {
    neutral: 'border-[#f8e8c8]/12 bg-[#f8e8c8]/10 text-[#fff4df]',
    emerald: 'border-[#0f766e]/35 bg-[#0f766e]/16 text-[#c8fff4]',
    rose: 'border-rose-300/35 bg-rose-300/16 text-[#fff4df]',
    amber: 'border-amber-300/35 bg-amber-300/16 text-[#fff4df]',
  }

  return (
    <div className={cn('rounded-3xl border p-4', toneClasses[tone])}>
      <div className="mb-3 flex h-9 w-9 items-center justify-center rounded-2xl bg-white/12">{icon}</div>
      <p className="text-[11px] font-black uppercase tracking-[0.18em] opacity-75">{label}</p>
      <p className="mt-1 text-lg font-black">{value}</p>
    </div>
  )
}

function SkeletonRow() {
  return (
    <tr>
      {Array.from({ length: 7 }).map((_, index) => (
        <td key={index} className="px-4 py-4"><div className="h-10 animate-pulse rounded-2xl bg-stone-100" /></td>
      ))}
    </tr>
  )
}

const inputClass = 'h-11 w-full rounded-xl border border-[#3d2a18]/10 bg-white px-4 text-sm font-bold text-[#24170d] outline-none transition focus:border-[#8b5e34]/45 focus:ring-4 focus:ring-[#d6b170]/18 placeholder:text-[#8b5e34]/50'
