import { Fragment, useCallback, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import {
  Banknote,
  CalendarDays,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Eye,
  Landmark,
  Loader2,
  Receipt,
  RefreshCw,
  Search,
  ShieldCheck,
  WalletCards,
  X,
} from 'lucide-react'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatCurrency, formatDateTime } from '../../../../shared/lib/utils/format'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import { fetchAdminContracts } from '../../contracts/services/contracts.service'
import type { AdminContractResource } from '../../contracts/types/contract-api.model'
import { fetchAdminRooms } from '../../rooms/services/rooms.service'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { confirmPaymentHistoryInvoicePayment, fetchAdminPaymentHistory } from '../services/payment-history.service'
import type { AdminPaymentHistoryPaginationMeta, AdminPaymentHistoryRecord, AdminPaymentHistorySummary, PaymentHistoryAmountDirection, PaymentHistorySourceType, PaymentHistoryStatusGroup } from '../types/payment-history.model'
import {
  amountDirectionOptions,
  depositTransactionTypeOptions,
  getDirectionClass,
  getDirectionSign,
  getSourceLabel,
  getVisibleErrorMessage,
  normalizePaymentHistory,
  paymentMethodOptions,
  perPageOptions,
  sourceTypeOptions,
  statusGroupOptions,
} from '../utils/payment-history.helpers'

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20 disabled:cursor-not-allowed disabled:opacity-60'
const localDateTimePattern = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2}(?:\.\d+)?)?)?$/

function formatPaymentEventDate(record: AdminPaymentHistoryRecord) {
  const rawValue = record.event_date?.trim()
  if (!rawValue) return '—'

  const localDateTime = record.source_type === 'deposit_transaction' ? rawValue.match(localDateTimePattern) : null
  if (localDateTime) {
    const [, year, month, day, hour = '00', minute = '00'] = localDateTime
    return `${hour}:${minute} ${day}/${month}/${year}`
  }

  return formatDateTime(rawValue)
}

export function PaymentHistoryScreen() {
  const { session } = useAdminSession()
  const isSuperAdmin = useMemo(() => isSuperAdminRole(session?.admin?.role), [session?.admin?.role])
  const managedBuildingId = session?.admin?.managed_buildings?.[0]?.id

  const [records, setRecords] = useState<AdminPaymentHistoryRecord[]>([])
  const [summary, setSummary] = useState<AdminPaymentHistorySummary | null>(null)
  const [paginationMeta, setPaginationMeta] = useState<AdminPaymentHistoryPaginationMeta | null>(null)
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [rooms, setRooms] = useState<Array<{ id: number; room_number?: string | null }>>([])
  const [contracts, setContracts] = useState<AdminContractResource[]>([])

  const [keyword, setKeyword] = useState('')
  const [selectedSourceType, setSelectedSourceType] = useState('')
  const [selectedStatusGroup, setSelectedStatusGroup] = useState('')
  const [selectedPaymentMethod, setSelectedPaymentMethod] = useState('')
  const [selectedBuildingId, setSelectedBuildingId] = useState(isSuperAdmin ? '' : managedBuildingId ? String(managedBuildingId) : '')
  const [selectedRoomId, setSelectedRoomId] = useState('')
  const [selectedContractId, setSelectedContractId] = useState('')
  const [selectedDepositType, setSelectedDepositType] = useState('')
  const [selectedDirection, setSelectedDirection] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [perPage, setPerPage] = useState(10)
  const [currentPage, setCurrentPage] = useState(1)

  const [selectedRecord, setSelectedRecord] = useState<AdminPaymentHistoryRecord | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isConfirming, setIsConfirming] = useState<string | null>(null)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)

  const [activeMessage, setActiveMessage] = useState<string | null>(null)
  const [activeType, setActiveType] = useState<'success' | 'error' | null>(null)

  const buildingOptions = useMemo(() => buildings.map((building) => ({ value: building.id, label: building.name, tone: 'default' as const })), [buildings])
  const filterBuildingOptions = useMemo(() => [{ value: '', label: isSuperAdmin ? 'Tất cả tòa nhà' : 'Tòa nhà được phân quyền', tone: 'default' as const }, ...buildingOptions], [buildingOptions, isSuperAdmin])
  const roomOptions = useMemo(() => [{ value: '', label: 'Tất cả phòng', tone: 'default' as const }, ...rooms.map((room) => ({ value: room.id, label: `Phòng ${room.room_number || room.id}`, tone: 'default' as const }))], [rooms])
  const contractOptions = useMemo(() => [{ value: '', label: 'Tất cả hợp đồng', tone: 'default' as const }, ...contracts.map((contract) => ({
    value: contract.id,
    label: `${contract.contract_code} · Phòng ${contract.room_number || contract.room_id}`,
    description: contract.contract_tenants?.[0]?.tenant?.full_name || contract.building_name || undefined,
    tone: 'default' as const,
  }))], [contracts])

  const metrics = useMemo(() => ({
    total: summary?.total_transactions ?? paginationMeta?.total ?? records.length,
    totalIn: summary?.total_in_amount ?? '0',
    totalOut: summary?.total_out_amount ?? '0',
    pending: summary?.pending_count ?? records.filter((record) => record.status_group === 'pending').length,
  }), [paginationMeta?.total, records, summary])

  const safeCurrentPage = Math.max(1, Math.min(currentPage, paginationMeta?.last_page ?? currentPage))
  const totalPages = Math.max(1, paginationMeta?.last_page ?? (records.length >= perPage ? currentPage + 1 : currentPage))
  const paginationStart = paginationMeta?.from ?? (records.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + 1)
  const paginationEnd = paginationMeta?.to ?? (records.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + records.length)
  const visiblePages = useMemo(() => {
    const pages = new Set<number>([1, totalPages, safeCurrentPage - 1, safeCurrentPage, safeCurrentPage + 1])
    return Array.from(pages).filter((page) => page >= 1 && page <= totalPages).sort((a, b) => a - b)
  }, [safeCurrentPage, totalPages])

  const hasActiveFilters = Boolean(keyword.trim() || selectedSourceType || selectedStatusGroup || selectedPaymentMethod || selectedBuildingId || selectedRoomId || selectedContractId || selectedDepositType || selectedDirection || dateFrom || dateTo)

  const resetPage = useCallback(() => setCurrentPage(1), [])

  useEffect(() => {
    if (successMessage) {
      const timer = setTimeout(() => setSuccessMessage(null), 3000)
      return () => clearTimeout(timer)
    }
  }, [successMessage])

  useEffect(() => {
    if (errorMessage) {
      const timer = setTimeout(() => setErrorMessage(null), 3000)
      return () => clearTimeout(timer)
    }
  }, [errorMessage])

  useEffect(() => {
    if (successMessage) {
      setActiveMessage(successMessage)
      setActiveType('success')
    } else if (errorMessage) {
      setActiveMessage(errorMessage)
      setActiveType('error')
    } else {
      const timer = setTimeout(() => {
        setActiveMessage(null)
        setActiveType(null)
      }, 500)
      return () => clearTimeout(timer)
    }
  }, [successMessage, errorMessage])

  const loadBuildings = useCallback(async () => {
    try {
      const response = await fetchAdminBuildings({ per_page: 100 })
      const list = Array.isArray(response.result) ? response.result : []
      setBuildings(list)

      if (!isSuperAdmin && !selectedBuildingId && list[0]?.id) {
        setSelectedBuildingId(String(list[0].id))
      }
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải danh sách tòa nhà.'))
    }
  }, [isSuperAdmin, selectedBuildingId])

  const loadRoomsAndContracts = useCallback(async (buildingId: string) => {
    try {
      if (buildingId) {
        const roomsResponse = await fetchAdminRooms({ building_id: Number(buildingId), per_page: 100 })
        const roomsResult = roomsResponse.result
        setRooms(Array.isArray(roomsResult) ? roomsResult.filter((room) => Number(room.building_id) === Number(buildingId)) : [])
      } else {
        setRooms([])
      }

      const contractsResponse = await fetchAdminContracts({
        per_page: 100,
        building_id: buildingId ? Number(buildingId) : undefined,
      })
      const result = contractsResponse.result
      setContracts(result && !Array.isArray(result) ? result.data || [] : Array.isArray(result) ? result : [])
    } catch (error) {
      setRooms([])
      setContracts([])
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải phòng/hợp đồng để lọc lịch sử.'))
    }
  }, [])

  const loadPaymentHistory = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)

    try {
      const response = await fetchAdminPaymentHistory({
        keyword: keyword.trim() || undefined,
        source_type: (selectedSourceType || undefined) as PaymentHistorySourceType | undefined,
        status_group: (selectedStatusGroup || undefined) as PaymentHistoryStatusGroup | undefined,
        payment_method: selectedPaymentMethod ? Number(selectedPaymentMethod) : undefined,
        building_id: selectedBuildingId ? Number(selectedBuildingId) : undefined,
        room_id: selectedRoomId ? Number(selectedRoomId) : undefined,
        contract_id: selectedContractId ? Number(selectedContractId) : undefined,
        deposit_transaction_type: selectedDepositType ? Number(selectedDepositType) : undefined,
        amount_direction: (selectedDirection || undefined) as PaymentHistoryAmountDirection | undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
        page: currentPage,
        per_page: perPage,
      })
      const { data, meta, summary: nextSummary } = normalizePaymentHistory(response.result)
      setRecords(data)
      setPaginationMeta(meta)
      setSummary(nextSummary)

      if (meta?.last_page && currentPage > meta.last_page) {
        setCurrentPage(meta.last_page)
      }
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải lịch sử thanh toán.'))
    } finally {
      setIsLoading(false)
    }
  }, [currentPage, dateFrom, dateTo, keyword, perPage, selectedBuildingId, selectedContractId, selectedDepositType, selectedDirection, selectedPaymentMethod, selectedRoomId, selectedSourceType, selectedStatusGroup])

  useEffect(() => {
    void loadBuildings()
  }, [loadBuildings])

  useEffect(() => {
    void loadRoomsAndContracts(selectedBuildingId)
  }, [loadRoomsAndContracts, selectedBuildingId])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadPaymentHistory()
    }, 180)

    return () => window.clearTimeout(timer)
  }, [loadPaymentHistory])

  async function handleConfirm(record: AdminPaymentHistoryRecord) {
    const invoiceId = record.invoice?.id
    if (!invoiceId || record.source_type !== 'invoice_payment') return

    setIsConfirming(record.uid)
    setErrorMessage(null)

    try {
      await confirmPaymentHistoryInvoicePayment(invoiceId, record.source_id)
      setSuccessMessage('Xác nhận thanh toán hóa đơn thành công.')
      setSelectedRecord(null)
      await loadPaymentHistory()
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể xác nhận thanh toán.'))
    } finally {
      setIsConfirming(null)
    }
  }

  function resetFilters() {
    setKeyword('')
    setSelectedSourceType('')
    setSelectedStatusGroup('')
    setSelectedPaymentMethod('')
    setSelectedBuildingId(isSuperAdmin ? '' : managedBuildingId ? String(managedBuildingId) : '')
    setSelectedRoomId('')
    setSelectedContractId('')
    setSelectedDepositType('')
    setSelectedDirection('')
    setDateFrom('')
    setDateTo('')
    setCurrentPage(1)
  }

  const changePage = (nextPage: number) => {
    setCurrentPage(Math.max(1, Math.min(nextPage, totalPages)))
  }

  const changePerPage = (nextValue: string | number) => {
    setPerPage(Number(nextValue))
    setCurrentPage(1)
  }

  return (
    <section className="space-y-5 text-[#24170d] sm:space-y-6">
      {/* ── Dark hero header with metrics ── */}
      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-5 text-[#fff4df] sm:p-6 lg:p-7">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_16%_12%,rgba(243,197,107,0.30),transparent_31%),radial-gradient(circle_at_78%_10%,rgba(15,118,110,0.30),transparent_34%),linear-gradient(135deg,#24170d_0%,#4a2b14_48%,#0f3f3b_100%)]" />
          <div className="pointer-events-none absolute inset-x-6 bottom-0 h-px bg-gradient-to-r from-transparent via-[#f3c56b]/45 to-transparent" />

          <div className="relative flex min-w-0 flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div className="min-w-0">
              <span className="block text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]/80">TÀI CHÍNH &amp; BÁO CÁO</span>
              <h1 className="mt-3 flex items-center gap-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem]">
                <WalletCards className="h-8 w-8 shrink-0 text-[#f3c56b]" />
                Lịch sử thanh toán
              </h1>
            </div>
            <button type="button" onClick={() => void loadPaymentHistory()} className="inline-flex h-11 w-fit shrink-0 items-center justify-center gap-2 self-end whitespace-nowrap rounded-xl bg-[#f3c56b] px-5 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition-all hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98] lg:self-auto">
              <RefreshCw className={cn('h-4 w-4 stroke-[2.8]', isLoading && 'animate-spin')} /> Làm mới
            </button>
          </div>

          <div className="relative mt-6 grid grid-cols-[repeat(auto-fit,minmax(14rem,1fr))] gap-3">
            <MetricCard icon={<Receipt className="h-4 w-4" />} label="Tổng giao dịch" value={metrics.total.toLocaleString('vi-VN')} />
            <MetricCard icon={<Landmark className="h-4 w-4" />} label="Tiền vào" value={formatCurrency(metrics.totalIn)} tone="emerald" />
            <MetricCard icon={<Banknote className="h-4 w-4" />} label="Tiền ra/hoàn" value={formatCurrency(metrics.totalOut)} tone="rose" />
            <MetricCard icon={<ShieldCheck className="h-4 w-4" />} label="Chờ xác nhận" value={metrics.pending.toLocaleString('vi-VN')} tone="amber" />
          </div>
        </div>
      </section>

      {/* ── Animated toast ── */}
      <div
        className={cn(
          'rounded-3xl border px-4 text-sm font-black shadow-sm transition-all duration-500 ease-in-out transform overflow-hidden',
          (successMessage || errorMessage)
            ? 'opacity-100 max-h-20 py-3 translate-y-0 scale-100'
            : 'opacity-0 max-h-0 py-0 -translate-y-2 scale-95 pointer-events-none border-transparent',
          (errorMessage || activeType === 'error')
            ? 'border-rose-200 bg-rose-50 text-rose-700'
            : 'border-emerald-200 bg-emerald-50 text-emerald-700'
        )}
      >
        {activeMessage || errorMessage || successMessage}
      </div>

      {/* ── Filters + Table combined section ── */}
      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
        <div className="border-b border-[#3d2a18]/10 bg-[#fff8eb]/85 p-4 sm:p-5">
          <div className="grid gap-3 lg:grid-cols-[minmax(12rem,1.15fr)_repeat(3,minmax(100px,0.75fr))]">
            <div className="relative min-w-0">
              <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
              <input
                value={keyword}
                onChange={(event) => { setKeyword(event.target.value); resetPage() }}
                placeholder="Tìm mã hóa đơn, hợp đồng, phòng, khách thuê, tham chiếu..."
                className={`${inputClass} pl-11 pr-28`}
              />
              <button type="button" onClick={resetFilters} disabled={!hasActiveFilters} className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20 disabled:cursor-not-allowed disabled:opacity-45">
                <X className="h-3.5 w-3.5" /> Xóa lọc
              </button>
            </div>
            <AdminSelect value={selectedSourceType} options={sourceTypeOptions} onChange={(value) => { setSelectedSourceType(String(value)); setSelectedDepositType(''); resetPage() }} />
            <AdminSelect value={selectedStatusGroup} options={statusGroupOptions} onChange={(value) => { setSelectedStatusGroup(String(value)); resetPage() }} />
            <AdminSelect value={selectedPaymentMethod} options={paymentMethodOptions} onChange={(value) => { setSelectedPaymentMethod(String(value)); resetPage() }} />
          </div>
          <div className="mt-3 grid gap-3 lg:grid-cols-[minmax(100px,14rem)_minmax(100px,14rem)_minmax(100px,14rem)_minmax(100px,14rem)_minmax(100px,14rem)_1fr]">
            <AdminSelect value={selectedBuildingId} options={filterBuildingOptions} onChange={(value) => { setSelectedBuildingId(String(value)); setSelectedRoomId(''); setSelectedContractId(''); resetPage() }} disabled={!isSuperAdmin && Boolean(managedBuildingId)} />
            <AdminSelect value={selectedRoomId} options={roomOptions} onChange={(value) => { setSelectedRoomId(String(value)); resetPage() }} disabled={!selectedBuildingId} />
            <AdminSelect value={selectedContractId} options={contractOptions} onChange={(value) => { setSelectedContractId(String(value)); resetPage() }} />
            <AdminSelect value={selectedDirection} options={amountDirectionOptions} onChange={(value) => { setSelectedDirection(String(value)); resetPage() }} />
            <AdminSelect value={selectedDepositType} options={depositTransactionTypeOptions} onChange={(value) => { setSelectedDepositType(String(value)); resetPage() }} disabled={selectedSourceType !== 'deposit_transaction'} />
          </div>
          <div className="mt-3 grid gap-3 sm:grid-cols-2 xl:max-w-xl">
            <label className="relative block">
              <CalendarDays className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
              <input type="date" value={dateFrom} onChange={(event) => { setDateFrom(event.target.value); resetPage() }} className={`${inputClass} pl-11`} />
            </label>
            <label className="relative block">
              <CalendarDays className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
              <input type="date" value={dateTo} onChange={(event) => { setDateTo(event.target.value); resetPage() }} className={`${inputClass} pl-11`} />
            </label>
          </div>
        </div>

        {/* ── Table ── */}
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1080px] text-left text-sm">
            <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
              <tr>
                <th className="px-5 py-4">Thời gian</th>
                <th className="px-5 py-4 text-center">Nguồn</th>
                <th className="px-5 py-4">Đối tượng</th>
                <th className="px-5 py-4">Tòa/Phòng/Khách</th>
                <th className="px-5 py-4 text-center">Phương thức</th>
                <th className="px-5 py-4 text-center">Số tiền</th>
                <th className="px-5 py-4 text-center">Trạng thái</th>
                <th className="px-5 py-4 w-[180px] text-center">Thao tác</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#3d2a18]/8 bg-[#fffaf1]/70">
              {isLoading && Array.from({ length: 5 }).map((_, index) => (
                <tr key={index}>
                  <td colSpan={8} className="px-5 py-4"><div className="h-14 animate-pulse rounded-2xl bg-[#efe2cf]/45" /></td>
                </tr>
              ))}

              {!isLoading && records.length === 0 && (
                <tr>
                  <td colSpan={8} className="px-5 py-20 text-center">
                    <div className="mx-auto flex max-w-sm flex-col items-center rounded-[2rem] border border-dashed border-[#3d2a18]/12 bg-white/55 px-6 py-8">
                      <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]"><WalletCards className="h-9 w-9" /></div>
                      <p className="text-lg font-black tracking-tight text-[#24170d]">Không có thanh toán</p>
                      <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">{hasActiveFilters ? 'Thử xóa lọc hoặc đổi từ khóa tìm kiếm.' : 'Chưa có giao dịch thanh toán/cọc/chuyển phòng phát sinh.'}</p>
                      {hasActiveFilters && (
                        <button type="button" onClick={resetFilters} className="mt-4 inline-flex h-10 items-center justify-center rounded-2xl bg-[#24170d] px-4 text-xs font-black text-[#fff4df] transition hover:bg-[#3d2a18]">
                          Xóa bộ lọc
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              )}

              {!isLoading && records.map((record) => (
                <tr key={record.uid} className="align-middle transition hover:bg-[#f3c56b]/10 group">
                  <td className="px-5 py-4 text-sm font-bold text-[#3d2a18]">{formatPaymentEventDate(record)}</td>
                  <td className="px-5 py-4 text-center"><SourceBadge record={record} /></td>
                  <td className="px-5 py-4">
                    <p className="font-black text-[#24170d]">{record.code || '—'}</p>
                    <p className="mt-1 text-xs font-bold text-[#8b5e34]">{record.transaction_reference || 'Không có mã tham chiếu'}</p>
                  </td>
                  <td className="px-5 py-4">
                    <p className="font-black text-[#24170d]">{record.building?.name || '—'} · Phòng {record.room?.room_number || '—'}</p>
                    <p className="mt-1 text-xs font-bold text-[#8b5e34]">{record.tenants?.map((tenant) => tenant.full_name).filter(Boolean).join(', ') || 'Chưa có khách thuê'}</p>
                  </td>
                  <td className="px-5 py-4 text-center"><PaymentMethodBadge label={record.payment_method_label} /></td>
                  <td className={cn('px-5 py-4 font-black tabular-nums text-center', record.amount_direction === 'out' ? 'text-rose-700' : 'text-emerald-700')}>{getDirectionSign(record.amount_direction)}{formatCurrency(record.amount)}</td>
                  <td className="px-5 py-4 text-center"><StatusBadge record={record} /></td>
                  <td className="px-5 py-4">
                    <div className="flex justify-center gap-2">
                      <IconButton title="Xem chi tiết" success onClick={() => setSelectedRecord(record)}><Eye className="h-5 w-5" /></IconButton>
                      {record.can_confirm && (
                        <button type="button" onClick={() => void handleConfirm(record)} disabled={isConfirming === record.uid} className="inline-flex h-10 items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-xs font-black uppercase tracking-[0.12em] text-emerald-700 transition hover:bg-emerald-100 disabled:opacity-55">
                          {isConfirming === record.uid ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" />} Xác nhận
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* ── Pagination ── */}
        <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb]/85 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
          <p className="text-xs font-black text-[#6f6254]">Hiển thị <span className="tabular-nums text-[#24170d]">{paginationStart}</span>-<span className="tabular-nums text-[#24170d]">{paginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{metrics.total}</span> thanh toán</p>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div className="w-full sm:w-36">
              <AdminSelect value={perPage} options={perPageOptions} onChange={changePerPage} menuPlacement="top" />
            </div>
            <div className="flex items-center justify-end gap-1.5">
              <button type="button" disabled={safeCurrentPage <= 1} onClick={() => changePage(currentPage - 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang trước">
                <ChevronLeft className="h-4 w-4" />
              </button>
              {visiblePages.map((page, index) => {
                const previousPage = visiblePages[index - 1]
                const hasGap = previousPage && page - previousPage > 1

                return (
                  <Fragment key={page}>
                    {hasGap && <span className="px-1 text-xs font-black text-[#8b5e34]/60">...</span>}
                    <button type="button" onClick={() => changePage(page)} className={cn('inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition', page === safeCurrentPage ? 'border-[#24170d] bg-[#24170d] text-[#fff4df] shadow-sm' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15')}>
                      {page}
                    </button>
                  </Fragment>
                )
              })}
              <button type="button" disabled={safeCurrentPage >= totalPages} onClick={() => changePage(currentPage + 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang sau">
                <ChevronRight className="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      </section>

      {/* ── Detail modal ── */}
      {selectedRecord && (
        <ModalFrame title={`Chi tiết ${selectedRecord.code || 'thanh toán'}`} onClose={() => setSelectedRecord(null)} wide>
          <div className="space-y-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                <SourceBadge record={selectedRecord} />
                <p className="mt-2 text-sm font-semibold text-[#6f6254]">{selectedRecord.code || 'Không có mã'} · {formatPaymentEventDate(selectedRecord)}</p>
              </div>
              <StatusBadge record={selectedRecord} />
            </div>

            <div className="grid gap-3 md:grid-cols-4">
              <DetailTile icon={<Landmark className="h-4 w-4" />} label="Số tiền" value={`${getDirectionSign(selectedRecord.amount_direction)}${formatCurrency(selectedRecord.amount)}`} toneClass={getDirectionClass(selectedRecord.amount_direction)} />
              <DetailTile icon={<ShieldCheck className="h-4 w-4" />} label="Trạng thái" value={selectedRecord.status_label || '—'} />
              <DetailTile icon={<WalletCards className="h-4 w-4" />} label="Phương thức" value={selectedRecord.payment_method_label || '—'} />
              <DetailTile icon={<Receipt className="h-4 w-4" />} label="Mã tham chiếu" value={selectedRecord.transaction_reference || '—'} />
            </div>

            <div className="rounded-3xl border border-[#3d2a18]/10 bg-white/65 p-4">
              <div className="grid gap-3 sm:grid-cols-2">
                <div>
                  <p className="text-xs font-black uppercase tracking-[0.18em] text-[#8b5e34]">Tòa nhà</p>
                  <p className="mt-1 text-sm font-black text-[#24170d]">{selectedRecord.building?.name || '—'}</p>
                </div>
                <div>
                  <p className="text-xs font-black uppercase tracking-[0.18em] text-[#8b5e34]">Phòng</p>
                  <p className="mt-1 text-sm font-black text-[#24170d]">{selectedRecord.room?.room_number ? `Phòng ${selectedRecord.room.room_number}` : '—'}</p>
                </div>
                <div>
                  <p className="text-xs font-black uppercase tracking-[0.18em] text-[#8b5e34]">Hợp đồng</p>
                  <p className="mt-1 text-sm font-black text-[#24170d]">{selectedRecord.contract?.contract_code || '—'}</p>
                </div>
                <div>
                  <p className="text-xs font-black uppercase tracking-[0.18em] text-[#8b5e34]">Hóa đơn</p>
                  <p className="mt-1 text-sm font-black text-[#24170d]">{selectedRecord.invoice?.invoice_code || '—'}</p>
                </div>
              </div>
            </div>

            <div className="rounded-3xl border border-[#3d2a18]/10 bg-white/65 p-4">
              <p className="text-xs font-black uppercase tracking-[0.18em] text-[#8b5e34]">Khách thuê</p>
              <p className="mt-2 text-sm font-bold text-[#24170d]">{selectedRecord.tenants?.map((tenant) => tenant.full_name).filter(Boolean).join(', ') || 'Chưa có khách thuê'}</p>
            </div>

            <div className="rounded-3xl border border-[#3d2a18]/10 bg-white/65 p-4">
              <p className="text-xs font-black uppercase tracking-[0.18em] text-[#8b5e34]">Người ghi nhận</p>
              <p className="mt-2 text-sm font-bold text-[#24170d]">{selectedRecord.actor_name || 'Hệ thống'}</p>
            </div>

            <div className="rounded-3xl border border-[#3d2a18]/10 bg-white/65 p-4">
              <p className="text-xs font-black uppercase tracking-[0.18em] text-[#8b5e34]">Ghi chú</p>
              <p className="mt-2 whitespace-pre-wrap text-sm font-semibold leading-6 text-[#3d2a18]">{selectedRecord.note || '—'}</p>
            </div>

            {selectedRecord.proof_image_url && (
              <div>
                <p className="mb-1.5 text-[11px] font-black uppercase tracking-[0.18em] text-[#8b5e34]">Minh chứng thanh toán</p>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                  <a href={selectedRecord.proof_image_url} target="_blank" rel="noreferrer" className="group overflow-hidden rounded-2xl border border-[#3d2a18]/10 bg-white shadow-sm">
                    <img src={selectedRecord.proof_image_url} alt="Minh chứng thanh toán" className="h-36 w-full object-cover transition duration-300 group-hover:scale-105" />
                  </a>
                </div>
              </div>
            )}
          </div>

          <div className="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
            <button type="button" onClick={() => setSelectedRecord(null)} className="h-11 rounded-xl border border-[#3d2a18]/10 px-5 text-sm font-black text-[#6f6254] transition hover:bg-[#efe2cf] disabled:opacity-60">Đóng</button>
            {selectedRecord.can_confirm && (
              <button type="button" onClick={() => void handleConfirm(selectedRecord)} disabled={isConfirming === selectedRecord.uid} className="h-11 rounded-xl bg-[#24170d] px-5 text-sm font-black text-[#fff4df] shadow-md transition hover:bg-[#3d2a18] disabled:opacity-60">
                {isConfirming === selectedRecord.uid ? <Loader2 className="mr-2 inline h-4 w-4 animate-spin" /> : <CheckCircle2 className="mr-2 inline h-4 w-4" />}
                Xác nhận thanh toán
              </button>
            )}
          </div>
        </ModalFrame>
      )}
    </section>
  )
}

/* ── Shared sub-components ── */

function MetricCard({ icon, label, value, tone = 'neutral' }: { icon: ReactNode; label: string; value: ReactNode; tone?: 'neutral' | 'emerald' | 'rose' | 'amber' }) {
  const toneClasses = {
    neutral: 'border-[#f8e8c8]/12 bg-[#f8e8c8]/10 text-[#fff4df]',
    emerald: 'border-[#0f766e]/35 bg-[#0f766e]/16 text-[#c8fff4]',
    rose: 'border-rose-300/35 bg-rose-300/16 text-[#fff4df]',
    amber: 'border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#fff4df]',
  }

  return (
    <div className={cn('flex h-full min-h-[6.75rem] min-w-0 flex-col rounded-[1.45rem] border p-4 shadow-lg shadow-black/5 backdrop-blur-sm transition duration-200 hover:-translate-y-0.5 hover:bg-white/12', toneClasses[tone])}>
      <div className="flex min-w-0 items-center gap-3">
        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-white/12 text-[#f3c56b]">{icon}</div>
        <p className="min-w-0 whitespace-nowrap text-[10px] font-black uppercase leading-none tracking-[0.14em] opacity-75">{label}</p>
      </div>
      <p className="mt-5 min-w-0 whitespace-nowrap text-[clamp(1.35rem,1.5vw,1.6rem)] font-black leading-none tabular-nums tracking-[-0.04em]">{value}</p>
    </div>
  )
}

function SourceBadge({ record }: { record: AdminPaymentHistoryRecord }) {
  const sourceClass = record.source_type === 'invoice_payment' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : record.source_type === 'deposit_transaction' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]'
  return <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-xs font-black shadow-sm', sourceClass)}>{record.source_label || getSourceLabel(record.source_type)}</span>
}

function PaymentMethodBadge({ label }: { label?: string | null }) {
  const text = label || '—'
  const isCash = /tiền mặt/i.test(text)
  const badgeClass = isCash
    ? 'border-amber-200 bg-amber-50 text-amber-700'
    : 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]'
  return <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-xs font-black shadow-sm', badgeClass)}>{text}</span>
}

function StatusBadge({ record }: { record: AdminPaymentHistoryRecord }) {
  const label = record.status_label || 'Đã ghi nhận'
  let badgeClass = 'border-slate-200 bg-slate-50 text-slate-700'

  if (record.status_group === 'pending') {
    badgeClass = 'border-amber-200 bg-amber-50 text-amber-700'
  } else if (record.status_group === 'partial') {
    badgeClass = 'border-blue-200 bg-blue-50 text-blue-700'
  } else if (record.status_group === 'cancelled') {
    badgeClass = 'border-rose-200 bg-rose-50 text-rose-700'
  } else if (record.status_group === 'paid' || label === 'Đã thanh toán') {
    badgeClass = 'border-emerald-200 bg-emerald-50 text-emerald-700'
  } else if (record.status_group === 'confirmed') {
    if (label === 'Đã xác nhận') {
      badgeClass = 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]' // Teal
    } else {
      badgeClass = 'border-slate-200 bg-slate-50 text-slate-700' // Slate
    }
  }

  return <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-xs font-black shadow-sm', badgeClass)}>{label}</span>
}


function IconButton({
  children,
  onClick,
  title,
  danger,
  success,
  disabled,
}: {
  children: ReactNode
  onClick: () => void
  title: string
  danger?: boolean
  success?: boolean
  disabled?: boolean
}) {
  return (
    <button
      type="button"
      title={title}
      onClick={onClick}
      disabled={disabled}
      className={cn(
        'inline-flex h-10 w-10 items-center justify-center rounded-xl border shadow-sm transition focus:outline-none focus:ring-4 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45',
        danger
          ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 focus:ring-rose-100'
          : success
            ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59] hover:bg-[#0f766e]/16 focus:ring-[#0f766e]/10'
            : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:ring-[#3d2a18]/10'
      )}
    >
      {children}
    </button>
  )
}

function DetailTile({ icon, label, value, toneClass }: { icon: ReactNode; label: string; value: string; toneClass?: string }) {
  return (
    <div className={cn('rounded-2xl border border-[#3d2a18]/10 bg-[#fff8eb] p-4', toneClass)}>
      <div className="mb-2 flex items-center gap-2 text-[#a65f16]">{icon}<span className="text-[11px] font-black uppercase tracking-[0.18em]">{label}</span></div>
      <p className="break-words text-sm font-black text-[#24170d]">{value}</p>
    </div>
  )
}

function ModalFrame({ title, onClose, children, wide }: { title: string; onClose: () => void; children: ReactNode; wide?: boolean }) {
  return (
    <div className="fixed inset-0 z-[75] flex items-center justify-center p-4">
      <button type="button" className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" onClick={onClose} aria-label="Đóng" />
      <div className={cn('relative z-10 max-h-[92vh] w-full overflow-y-auto rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] p-5 shadow-2xl', wide ? 'max-w-5xl' : 'max-w-xl')}>
        <div className="mb-4 flex items-center justify-between gap-3 border-b border-[#3d2a18]/10 pb-3">
          <h2 className="text-lg font-black text-[#24170d]">{title}</h2>
          <button type="button" onClick={onClose} className="rounded-xl p-1.5 text-[#8b5e34] transition hover:bg-rose-50 hover:text-rose-600"><X className="h-4 w-4" /></button>
        </div>
        {children}
      </div>
    </div>
  )
}
