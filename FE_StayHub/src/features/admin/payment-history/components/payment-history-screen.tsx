import { Fragment, useCallback, useEffect, useMemo, useState } from 'react'
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
  getDirectionLabel,
  getDirectionSign,
  getSourceLabel,
  getStatusTone,
  getVisibleErrorMessage,
  normalizePaymentHistory,
  paymentMethodOptions,
  perPageOptions,
  sourceTypeOptions,
  statusGroupOptions,
} from '../utils/payment-history.helpers'

const inputClass = 'h-12 w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 text-sm font-bold text-[#24170d] outline-none transition placeholder:text-[#8b5e34]/45 focus:border-[#0f766e]/45 focus:ring-4 focus:ring-[#0f766e]/10 disabled:opacity-60'
const iconButtonClass = 'inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:border-[#f3c56b]/50 hover:bg-[#f3c56b]/15 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#f3c56b]/20 disabled:cursor-not-allowed disabled:opacity-45'
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

  useEffect(() => {
    if (!successMessage) return
    const timer = window.setTimeout(() => setSuccessMessage(null), 3500)
    return () => window.clearTimeout(timer)
  }, [successMessage])

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

  return (
    <div className="space-y-6 pb-8">
      <section className="relative overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fff7e8] p-5 shadow-xl shadow-[#3d2a18]/8 lg:p-7">
        <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(135deg,rgba(15,118,110,0.12),transparent_38%),radial-gradient(circle_at_top_right,rgba(243,197,107,0.35),transparent_34%)]" />
        <div className="pointer-events-none absolute -right-14 -top-14 h-44 w-44 rounded-full border border-[#0f766e]/15" />
        <div className="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
          <div className="max-w-3xl">
            <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-[#0f766e]/15 bg-white/70 px-3 py-1 text-[10px] font-black uppercase tracking-[0.22em] text-[#0f5f59] shadow-sm">
              <WalletCards className="h-3.5 w-3.5" /> Nhật ký dòng tiền
            </div>
            <h1 className="text-3xl font-black tracking-tight text-[#24170d] sm:text-4xl">Lịch sử thanh toán</h1>

          </div>
          <div className="flex flex-wrap items-center gap-3">
            <button type="button" onClick={() => void loadPaymentHistory()} className="inline-flex min-h-11 items-center gap-2 rounded-2xl border border-[#3d2a18]/10 bg-white/80 px-4 text-xs font-black uppercase tracking-[0.14em] text-[#3d2a18] transition hover:bg-[#f3c56b]/15">
              <RefreshCw className={cn('h-4 w-4', isLoading && 'animate-spin')} /> Làm mới
            </button>
          </div>
        </div>
      </section>

      {(errorMessage || successMessage) && (
        <div className={cn('rounded-2xl border px-4 py-3 text-sm font-black shadow-sm', errorMessage ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700')}>
          {errorMessage || successMessage}
        </div>
      )}

      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <MetricCard icon={Receipt} label="Tổng giao dịch" value={metrics.total.toLocaleString('vi-VN')} tone="neutral" />
        <MetricCard icon={Landmark} label="Tiền vào" value={formatCurrency(metrics.totalIn)} tone="success" />
        <MetricCard icon={Banknote} label="Tiền ra/hoàn" value={formatCurrency(metrics.totalOut)} tone="danger" />
        <MetricCard icon={ShieldCheck} label="Chờ xác nhận" value={metrics.pending.toLocaleString('vi-VN')} tone="warning" />
      </section>

      <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-white/75 p-4 shadow-lg shadow-[#3d2a18]/6 backdrop-blur lg:p-5">
        <div className="grid gap-3 lg:grid-cols-[1.4fr_repeat(3,1fr)]">
          <label className="relative block">
            <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8b5e34]" />
            <input
              value={keyword}
              onChange={(event) => { setKeyword(event.target.value); resetPage() }}
              placeholder="Tìm mã hóa đơn, hợp đồng, phòng, khách thuê, tham chiếu..."
              className={cn(inputClass, 'pl-11')}
            />
          </label>
          <AdminSelect value={selectedSourceType} options={sourceTypeOptions} onChange={(value) => { setSelectedSourceType(String(value)); setSelectedDepositType(''); resetPage() }} />
          <AdminSelect value={selectedStatusGroup} options={statusGroupOptions} onChange={(value) => { setSelectedStatusGroup(String(value)); resetPage() }} />
          <AdminSelect value={selectedPaymentMethod} options={paymentMethodOptions} onChange={(value) => { setSelectedPaymentMethod(String(value)); resetPage() }} />
        </div>
        <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-6">
          <AdminSelect value={selectedBuildingId} options={filterBuildingOptions} onChange={(value) => { setSelectedBuildingId(String(value)); setSelectedRoomId(''); setSelectedContractId(''); resetPage() }} disabled={!isSuperAdmin && Boolean(managedBuildingId)} />
          <AdminSelect value={selectedRoomId} options={roomOptions} onChange={(value) => { setSelectedRoomId(String(value)); resetPage() }} disabled={!selectedBuildingId} />
          <AdminSelect value={selectedContractId} options={contractOptions} onChange={(value) => { setSelectedContractId(String(value)); resetPage() }} />
          <AdminSelect value={selectedDirection} options={amountDirectionOptions} onChange={(value) => { setSelectedDirection(String(value)); resetPage() }} />
          <AdminSelect value={selectedDepositType} options={depositTransactionTypeOptions} onChange={(value) => { setSelectedDepositType(String(value)); resetPage() }} disabled={selectedSourceType !== 'deposit_transaction'} />
          <button type="button" onClick={resetFilters} className="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl border border-[#3d2a18]/10 bg-[#24170d] px-4 text-xs font-black uppercase tracking-[0.14em] text-[#fff4df] transition hover:bg-[#3d2a18] disabled:opacity-60" disabled={!hasActiveFilters}>
            <X className="h-4 w-4" /> Xóa lọc
          </button>
        </div>
        <div className="mt-3 grid gap-3 sm:grid-cols-2 xl:max-w-xl">
          <label className="relative block">
            <CalendarDays className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8b5e34]" />
            <input type="date" value={dateFrom} onChange={(event) => { setDateFrom(event.target.value); resetPage() }} className={cn(inputClass, 'pl-11')} />
          </label>
          <label className="relative block">
            <CalendarDays className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8b5e34]" />
            <input type="date" value={dateTo} onChange={(event) => { setDateTo(event.target.value); resetPage() }} className={cn(inputClass, 'pl-11')} />
          </label>
        </div>
      </section>

      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-white/80 shadow-xl shadow-[#3d2a18]/7">
        <div className="hidden overflow-x-auto lg:block">
          <table className="min-w-full divide-y divide-[#3d2a18]/10 text-left">
            <thead className="bg-[#fff8eb] text-[11px] font-black uppercase tracking-[0.18em] text-[#8b5e34]">
              <tr>
                <th className="px-5 py-4">Thời gian</th>
                <th className="px-5 py-4">Nguồn</th>
                <th className="px-5 py-4">Đối tượng</th>
                <th className="px-5 py-4">Tòa/Phòng/Khách</th>
                <th className="px-5 py-4">Phương thức</th>
                <th className="px-5 py-4 text-right">Số tiền</th>
                <th className="px-5 py-4">Trạng thái</th>
                <th className="px-5 py-4 text-right">Thao tác</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#3d2a18]/8">
              {isLoading && (
                <tr>
                  <td colSpan={8} className="px-5 py-14 text-center">
                    <div className="inline-flex items-center gap-3 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-5 py-3 text-sm font-black text-[#6f6254]"><Loader2 className="h-4 w-4 animate-spin" /> Đang tải lịch sử thanh toán...</div>
                  </td>
                </tr>
              )}
              {!isLoading && records.map((record) => <PaymentHistoryRow key={record.uid} record={record} isConfirming={isConfirming === record.uid} onView={() => setSelectedRecord(record)} onConfirm={() => void handleConfirm(record)} />)}
              {!isLoading && records.length === 0 && (
                <tr>
                  <td colSpan={8} className="px-5 py-16 text-center">
                    <div className="mx-auto flex max-w-sm flex-col items-center rounded-[2rem] border border-dashed border-[#3d2a18]/12 bg-white/55 px-6 py-8">
                      <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]"><WalletCards className="h-9 w-9" /></div>
                      <p className="text-lg font-black tracking-tight text-[#24170d]">Không có thanh toán</p>
                      <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">{hasActiveFilters ? 'Thử xóa lọc hoặc đổi từ khóa tìm kiếm.' : 'Chưa có giao dịch thanh toán/cọc/chuyển phòng phát sinh.'}</p>
                    </div>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <div className="grid gap-3 p-4 lg:hidden">
          {isLoading && <div className="rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-5 text-sm font-black text-[#6f6254]"><Loader2 className="mr-2 inline h-4 w-4 animate-spin" /> Đang tải lịch sử thanh toán...</div>}
          {!isLoading && records.map((record) => <PaymentHistoryCard key={record.uid} record={record} isConfirming={isConfirming === record.uid} onView={() => setSelectedRecord(record)} onConfirm={() => void handleConfirm(record)} />)}
          {!isLoading && records.length === 0 && <div className="rounded-2xl border border-dashed border-[#3d2a18]/12 bg-[#fffaf1] p-6 text-center text-sm font-bold text-[#6f6254]">Không có thanh toán phù hợp.</div>}
        </div>

        <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb]/85 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
          <p className="text-xs font-black text-[#6f6254]">Hiển thị <span className="tabular-nums text-[#24170d]">{paginationStart}</span>-<span className="tabular-nums text-[#24170d]">{paginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{metrics.total}</span> thanh toán</p>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div className="w-full sm:w-36"><AdminSelect value={perPage} options={perPageOptions} onChange={(nextValue) => { setPerPage(Number(nextValue)); setCurrentPage(1) }} menuPlacement="top" /></div>
            <div className="flex items-center justify-end gap-1.5">
              <button type="button" disabled={safeCurrentPage <= 1} onClick={() => setCurrentPage(safeCurrentPage - 1)} className={iconButtonClass}><ChevronLeft className="h-4 w-4" /></button>
              {visiblePages.map((page, index) => {
                const previousPage = visiblePages[index - 1]
                const hasGap = previousPage && page - previousPage > 1

                return (
                  <Fragment key={page}>
                    {hasGap && <span className="px-1 text-xs font-black text-[#8b5e34]/60">...</span>}
                    <button
                      type="button"
                      onClick={() => setCurrentPage(page)}
                      className={cn(
                        'inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition',
                        page === safeCurrentPage ? 'border-[#24170d] bg-[#24170d] text-[#fff4df]' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15'
                      )}
                    >
                      {page}
                    </button>
                  </Fragment>
                )
              })}
              <button type="button" disabled={safeCurrentPage >= totalPages} onClick={() => setCurrentPage(safeCurrentPage + 1)} className={iconButtonClass}><ChevronRight className="h-4 w-4" /></button>
            </div>
          </div>
        </div>
      </section>

      {selectedRecord && <PaymentHistoryDetailModal record={selectedRecord} isConfirming={isConfirming === selectedRecord.uid} onClose={() => setSelectedRecord(null)} onConfirm={() => void handleConfirm(selectedRecord)} />}
    </div>
  )
}

function PaymentHistoryRow({ record, isConfirming, onView, onConfirm }: { record: AdminPaymentHistoryRecord; isConfirming: boolean; onView: () => void; onConfirm: () => void }) {
  return (
    <tr className="align-top transition hover:bg-[#fff8eb]/70">
      <td className="px-5 py-4 text-sm font-bold text-[#3d2a18]">{formatPaymentEventDate(record)}</td>
      <td className="px-5 py-4"><SourceBadge record={record} /></td>
      <td className="px-5 py-4">
        <p className="text-sm font-black text-[#24170d]">{record.code || '—'}</p>
        <p className="mt-1 text-xs font-bold text-[#8b5e34]">{record.transaction_reference || 'Không có mã tham chiếu'}</p>
      </td>
      <td className="px-5 py-4 text-sm font-bold text-[#3d2a18]">
        <p>{record.building?.name || '—'} · Phòng {record.room?.room_number || '—'}</p>
        <p className="mt-1 text-xs text-[#6f6254]">{record.tenants?.map((tenant) => tenant.full_name).filter(Boolean).join(', ') || 'Chưa có khách thuê'}</p>
      </td>
      <td className="px-5 py-4 text-sm font-black text-[#0f5f59]">{record.payment_method_label || '—'}</td>
      <td className="px-5 py-4 text-right"><AmountBadge record={record} /></td>
      <td className="px-5 py-4"><StatusBadge record={record} /></td>
      <td className="px-5 py-4">
        <div className="flex justify-end gap-2">
          <button type="button" onClick={onView} className={iconButtonClass} title="Xem chi tiết"><Eye className="h-4 w-4" /></button>
          {record.can_confirm && <button type="button" onClick={onConfirm} disabled={isConfirming} className="inline-flex h-10 items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-xs font-black uppercase tracking-[0.12em] text-emerald-700 transition hover:bg-emerald-100 disabled:opacity-55">{isConfirming ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" />} Xác nhận</button>}
        </div>
      </td>
    </tr>
  )
}

function PaymentHistoryCard({ record, isConfirming, onView, onConfirm }: { record: AdminPaymentHistoryRecord; isConfirming: boolean; onView: () => void; onConfirm: () => void }) {
  return (
    <article className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-[#fffaf1] p-4 shadow-sm">
      <div className="flex items-start justify-between gap-3">
        <div><SourceBadge record={record} /><p className="mt-2 text-xs font-bold text-[#6f6254]">{formatPaymentEventDate(record)}</p></div>
        <AmountBadge record={record} />
      </div>
      <div className="mt-4 space-y-1 text-sm font-bold text-[#3d2a18]">
        <p>{record.code || '—'}</p>
        <p className="text-xs text-[#6f6254]">{record.building?.name || '—'} · Phòng {record.room?.room_number || '—'}</p>
        <p className="text-xs text-[#6f6254]">{record.tenants?.map((tenant) => tenant.full_name).filter(Boolean).join(', ') || 'Chưa có khách thuê'}</p>
      </div>
      <div className="mt-4 flex items-center justify-between gap-2">
        <StatusBadge record={record} />
        <div className="flex gap-2">
          <button type="button" onClick={onView} className={iconButtonClass}><Eye className="h-4 w-4" /></button>
          {record.can_confirm && <button type="button" onClick={onConfirm} disabled={isConfirming} className="inline-flex h-10 items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-xs font-black uppercase tracking-[0.12em] text-emerald-700 transition hover:bg-emerald-100 disabled:opacity-55">{isConfirming ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" />}</button>}
        </div>
      </div>
    </article>
  )
}

function PaymentHistoryDetailModal({ record, isConfirming, onClose, onConfirm }: { record: AdminPaymentHistoryRecord; isConfirming: boolean; onClose: () => void; onConfirm: () => void }) {
  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center bg-[#24170d]/45 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
      <div className="max-h-[90vh] w-full max-w-3xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-[#24170d]/25">
        <div className="flex items-start justify-between gap-4 border-b border-[#3d2a18]/10 bg-[#fff8eb] p-5">
          <div>
            <SourceBadge record={record} />
            <h2 className="mt-3 text-2xl font-black tracking-tight text-[#24170d]">Chi tiết thanh toán</h2>
            <p className="mt-1 text-sm font-semibold text-[#6f6254]">{record.code || 'Không có mã'} · {formatPaymentEventDate(record)}</p>
          </div>
          <button type="button" onClick={onClose} className={iconButtonClass}><X className="h-4 w-4" /></button>
        </div>
        <div className="max-h-[68vh] overflow-y-auto p-5">
          <div className="grid gap-3 sm:grid-cols-2">
            <DetailTile label="Số tiền" value={`${getDirectionSign(record.amount_direction)}${formatCurrency(record.amount)}`} toneClass={getDirectionClass(record.amount_direction)} />
            <DetailTile label="Trạng thái" value={record.status_label || '—'} />
            <DetailTile label="Phương thức" value={record.payment_method_label || '—'} />
            <DetailTile label="Mã tham chiếu" value={record.transaction_reference || '—'} />
            <DetailTile label="Tòa nhà" value={record.building?.name || '—'} />
            <DetailTile label="Phòng" value={record.room?.room_number ? `Phòng ${record.room.room_number}` : '—'} />
            <DetailTile label="Hợp đồng" value={record.contract?.contract_code || '—'} />
            <DetailTile label="Hóa đơn" value={record.invoice?.invoice_code || '—'} />
            <DetailTile label="Người ghi nhận" value={record.actor_name || 'Hệ thống'} />
            <DetailTile label="Chiều tiền" value={getDirectionLabel(record.amount_direction)} />
          </div>

          <div className="mt-4 rounded-2xl border border-[#3d2a18]/10 bg-white/70 p-4">
            <p className="text-[11px] font-black uppercase tracking-[0.16em] text-[#8b5e34]">Khách thuê</p>
            <p className="mt-2 text-sm font-bold text-[#24170d]">{record.tenants?.map((tenant) => tenant.full_name).filter(Boolean).join(', ') || 'Chưa có khách thuê'}</p>
          </div>

          <div className="mt-4 rounded-2xl border border-[#3d2a18]/10 bg-white/70 p-4">
            <p className="text-[11px] font-black uppercase tracking-[0.16em] text-[#8b5e34]">Ghi chú</p>
            <p className="mt-2 whitespace-pre-wrap text-sm font-semibold leading-6 text-[#3d2a18]">{record.note || '—'}</p>
          </div>

          {record.proof_image_url && (
            <div className="mt-4 rounded-2xl border border-[#3d2a18]/10 bg-white/70 p-4">
              <p className="text-[11px] font-black uppercase tracking-[0.16em] text-[#8b5e34]">Minh chứng thanh toán</p>
              <img src={record.proof_image_url} alt="Minh chứng thanh toán" className="mt-3 max-h-80 w-full rounded-2xl object-contain" />
            </div>
          )}
        </div>
        <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb] p-4 sm:flex-row sm:justify-end">
          <button type="button" onClick={onClose} className="inline-flex min-h-11 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-white px-4 text-xs font-black uppercase tracking-[0.14em] text-[#3d2a18] transition hover:bg-[#f3c56b]/15">Đóng</button>
          {record.can_confirm && <button type="button" onClick={onConfirm} disabled={isConfirming} className="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-600 px-4 text-xs font-black uppercase tracking-[0.14em] text-white transition hover:bg-emerald-700 disabled:opacity-60">{isConfirming ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" />} Xác nhận thanh toán</button>}
        </div>
      </div>
    </div>
  )
}

function MetricCard({ icon: Icon, label, value, tone }: { icon: typeof Receipt; label: string; value: string; tone: 'neutral' | 'success' | 'danger' | 'warning' }) {
  const toneClass = tone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : tone === 'danger' ? 'border-rose-200 bg-rose-50 text-rose-700' : tone === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#24170d]'
  return <div className={cn('rounded-[1.5rem] border p-4 shadow-sm', toneClass)}><div className="flex items-center justify-between gap-3"><p className="text-[10px] font-black uppercase tracking-[0.18em] opacity-75">{label}</p><Icon className="h-5 w-5" /></div><p className="mt-3 text-2xl font-black tracking-tight">{value}</p></div>
}

function SourceBadge({ record }: { record: AdminPaymentHistoryRecord }) {
  const sourceClass = record.source_type === 'invoice_payment' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : record.source_type === 'deposit_transaction' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-[#0f766e]/20 bg-[#0f766e]/8 text-[#0f5f59]'
  return <span className={cn('inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-black uppercase tracking-[0.12em]', sourceClass)}>{record.source_label || getSourceLabel(record.source_type)}</span>
}

function StatusBadge({ record }: { record: AdminPaymentHistoryRecord }) {
  const tone = getStatusTone(record.status_group)
  const className = tone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : tone === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-700' : tone === 'danger' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#6f6254]'
  return <span className={cn('inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-black uppercase tracking-[0.12em]', className)}>{record.status_label || 'Đã ghi nhận'}</span>
}

function AmountBadge({ record }: { record: AdminPaymentHistoryRecord }) {
  return <span className={cn('inline-flex items-center justify-end rounded-2xl border px-3 py-2 text-sm font-black tabular-nums', getDirectionClass(record.amount_direction))}>{getDirectionSign(record.amount_direction)}{formatCurrency(record.amount)}</span>
}

function DetailTile({ label, value, toneClass }: { label: string; value: string; toneClass?: string }) {
  return <div className={cn('rounded-2xl border border-[#3d2a18]/10 bg-white/70 p-4', toneClass)}><p className="text-[10px] font-black uppercase tracking-[0.16em] opacity-70">{label}</p><p className="mt-2 break-words text-sm font-black">{value}</p></div>
}
