import { useCallback, useEffect, useMemo, useState, Fragment } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import {
  ArrowLeft,
  Banknote,
  Building2,
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  Eye,
  Plus,
  Receipt,
  WalletCards,
  X,
} from 'lucide-react'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatCurrency, formatDate, formatDateTime } from '../../../../shared/lib/utils/format'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { fetchAvailableRooms, fetchAdminContracts } from '../../contracts/services/contracts.service'
import type { AdminContractResource } from '../../contracts/types/contract-api.model'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import { AdminSelect } from '../../shared/components/AdminSelect'
import {
  cancelAdminInvoice,
  fetchAdminInvoiceDetail,
  fetchAdminInvoices,
  generateAdminInvoice,
  previewAdminInvoice,
  recordAdminInvoicePayment,
} from '../services/invoices.service'
import type { AdminInvoiceAdjustmentPayload, AdminInvoiceGeneratePayload, AdminInvoicePreviewResource, AdminInvoiceResource } from '../types/invoice-api.model'
import {
  INVOICE_STATUS_CANCELLED,
  INVOICE_STATUS_OVERDUE,
  INVOICE_STATUS_PAID,
  INVOICE_STATUS_PARTIALLY_PAID,
  INVOICE_STATUS_UNPAID,
  ITEM_TYPE_ADJUST_DECREASE,
  ITEM_TYPE_ADJUST_INCREASE,
  ITEM_TYPE_DISCOUNT,
  ITEM_TYPE_SURCHARGE,
  PAYMENT_METHOD_BANK_TRANSFER,
  PAYMENT_METHOD_CASH,
} from '../types/invoice-api.model'
import {
  currentMonthYear,
  getInvoiceStatusLabel,
  getResourceList,
  getVisibleErrorMessage,
  invoiceStatusOptions,
  monthOptions,
  normalizeInvoices,
  perPageOptions,
} from '../utils/invoice.helpers'
import { InvoicePreviewModal } from './invoice-preview-modal'

type RoomOption = {
  id: number
  building_id: number
  room_number?: string | null
  status?: number | null
  max_occupants?: number | null
  current_occupants?: number | null
}

const inputClass = 'h-11 w-full rounded-2xl border border-[#3d2a18]/10 bg-white/80 px-4 text-sm font-bold text-[#24170d] outline-none transition placeholder:text-[#8b5e34]/45 focus:border-[#0f766e]/45 focus:ring-4 focus:ring-[#0f766e]/10 disabled:opacity-60'
const textAreaClass = 'min-h-24 w-full rounded-2xl border border-[#3d2a18]/10 bg-white/80 px-4 py-3 text-sm font-bold text-[#24170d] outline-none transition placeholder:text-[#8b5e34]/45 focus:border-[#0f766e]/45 focus:ring-4 focus:ring-[#0f766e]/10 disabled:opacity-60'
const nowPeriod = currentMonthYear()

export function InvoicesScreen() {
  const { session } = useAdminSession()
  const isSuperAdmin = useMemo(() => isSuperAdminRole(session?.admin?.role), [session?.admin?.role])
  const managedBuildingId = session?.admin?.managed_buildings?.[0]?.id

  const [searchParams] = useSearchParams()
  const invoiceIdParam = searchParams.get('id')
  const invoiceCodeParam = searchParams.get('invoice_code')

  const [keyword, setKeyword] = useState(invoiceCodeParam || '')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [selectedBuildingId, setSelectedBuildingId] = useState(isSuperAdmin ? '' : managedBuildingId ? String(managedBuildingId) : '')
  const [selectedRoomId, setSelectedRoomId] = useState('')
  const [selectedMonth, setSelectedMonth] = useState('')
  const [selectedYear, setSelectedYear] = useState(String(nowPeriod.year))
  const [perPage, setPerPage] = useState(10)
  const [currentPage, setCurrentPage] = useState(1)
  const [paginationMeta, setPaginationMeta] = useState<ReturnType<typeof normalizeInvoices>['meta']>(null)
  const [invoices, setInvoices] = useState<AdminInvoiceResource[]>([])
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [rooms, setRooms] = useState<RoomOption[]>([])
  const [contracts, setContracts] = useState<AdminContractResource[]>([])
  const [detailInvoice, setDetailInvoice] = useState<AdminInvoiceResource | null>(null)
  const [isGenerateOpen, setIsGenerateOpen] = useState(false)
  const [previewInvoice, setPreviewInvoice] = useState<AdminInvoicePreviewResource | null>(null)
  const [pendingGeneratePayload, setPendingGeneratePayload] = useState<AdminInvoiceGeneratePayload | null>(null)
  const [isPaymentOpen, setIsPaymentOpen] = useState(false)
  const [paymentInvoice, setPaymentInvoice] = useState<AdminInvoiceResource | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [isSaving, setIsSaving] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)

  const buildingOptions = useMemo(() => buildings.map((building) => ({ value: building.id, label: building.name, tone: 'default' as const })), [buildings])
  const filterBuildingOptions = useMemo(() => [{ value: '', label: isSuperAdmin ? 'Tất cả tòa nhà' : 'Tòa nhà được phân quyền', tone: 'default' as const }, ...buildingOptions], [buildingOptions, isSuperAdmin])
  const roomOptions = useMemo(
    () => rooms.map((room) => ({ value: room.id, label: `Phòng ${room.room_number || room.id}`, tone: 'default' as const })),
    [rooms]
  )
  const contractOptions = useMemo(
    () => contracts.map((contract) => ({
      value: contract.id,
      label: `${contract.contract_code} · Phòng ${contract.room_number || contract.room_id}`,
      description: contract.contract_tenants?.[0]?.tenant?.full_name || contract.building_name || undefined,
      tone: 'default' as const,
    })),
    [contracts]
  )

  const metrics = useMemo(() => ({
    total: paginationMeta?.total ?? invoices.length,
    unpaid: invoices.filter((invoice) => [INVOICE_STATUS_UNPAID, INVOICE_STATUS_PARTIALLY_PAID, INVOICE_STATUS_OVERDUE].includes(Number(invoice.status))).length,
    paid: invoices.filter((invoice) => Number(invoice.status) === INVOICE_STATUS_PAID).length,
  }), [invoices, paginationMeta?.total])

  const safeCurrentPage = Math.max(1, Math.min(currentPage, paginationMeta?.last_page ?? currentPage))
  const totalPages = Math.max(1, paginationMeta?.last_page ?? (invoices.length >= perPage ? currentPage + 1 : currentPage))
  const paginationStart = paginationMeta?.from ?? (invoices.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + 1)
  const paginationEnd = paginationMeta?.to ?? (invoices.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + invoices.length)
  const visiblePages = useMemo(() => {
    const pages = new Set<number>([1, totalPages, safeCurrentPage - 1, safeCurrentPage, safeCurrentPage + 1])
    return Array.from(pages).filter((page) => page >= 1 && page <= totalPages).sort((a, b) => a - b)
  }, [safeCurrentPage, totalPages])
  const hasActiveFilters = Boolean(keyword.trim() || selectedStatus || selectedBuildingId || selectedRoomId || selectedMonth || selectedYear !== String(nowPeriod.year))

  const loadBuildings = useCallback(async () => {
    try {
      const response = await fetchAdminBuildings({ per_page: 100 })
      const list = getResourceList(response.result)
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
        const roomsResponse = await fetchAvailableRooms({ building_id: Number(buildingId) })
        setRooms(Array.isArray(roomsResponse.result) ? roomsResponse.result : [])
      } else {
        setRooms([])
      }

      const contractsResponse = await fetchAdminContracts({
        per_page: 100,
        status: 1,
        building_id: buildingId ? Number(buildingId) : undefined,
      })
      setContracts(getResourceList(contractsResponse.result))
    } catch (error) {
      setRooms([])
      setContracts([])
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải phòng/hợp đồng để lập hóa đơn.'))
    }
  }, [])

  const loadInvoices = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)

    try {
      const response = await fetchAdminInvoices({
        keyword: keyword.trim() || undefined,
        status: selectedStatus ? Number(selectedStatus) : undefined,
        building_id: selectedBuildingId ? Number(selectedBuildingId) : undefined,
        room_id: selectedRoomId ? Number(selectedRoomId) : undefined,
        billing_month: selectedMonth ? Number(selectedMonth) : undefined,
        billing_year: selectedYear ? Number(selectedYear) : undefined,
        page: currentPage,
        per_page: perPage,
      })
      const { data, meta } = normalizeInvoices(response.result)
      setInvoices(data)
      setPaginationMeta(meta)

      if (meta?.last_page && currentPage > meta.last_page) {
        setCurrentPage(meta.last_page)
      }
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải danh sách hóa đơn.'))
    } finally {
      setIsLoading(false)
    }
  }, [currentPage, keyword, perPage, selectedBuildingId, selectedMonth, selectedRoomId, selectedStatus, selectedYear])

  useEffect(() => {
    void loadBuildings()
  }, [loadBuildings])

  useEffect(() => {
    void loadRoomsAndContracts(selectedBuildingId)
  }, [selectedBuildingId, loadRoomsAndContracts])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadInvoices()
    }, 250)
    return () => window.clearTimeout(timer)
  }, [loadInvoices])

  useEffect(() => {
    const onRefresh = () => void loadInvoices()
    window.addEventListener('invoice-refresh', onRefresh)
    return () => window.removeEventListener('invoice-refresh', onRefresh)
  }, [loadInvoices])

  useEffect(() => {
    if (invoiceIdParam) {
      void viewInvoice({ id: Number(invoiceIdParam) } as any)
    }
  }, [invoiceIdParam])

  useEffect(() => {
    if (!isLoading && invoiceCodeParam && invoices.length > 0) {
      const found = invoices.find((inv) => inv.invoice_code === invoiceCodeParam)
      if (found) {
        void viewInvoice(found)
      }
    }
  }, [isLoading, invoiceCodeParam, invoices])

  const clearFilters = () => {
    setKeyword('')
    setSelectedStatus('')
    setSelectedBuildingId(isSuperAdmin ? '' : managedBuildingId ? String(managedBuildingId) : '')
    setSelectedRoomId('')
    setSelectedMonth('')
    setSelectedYear(String(nowPeriod.year))
    setCurrentPage(1)
  }

  const viewInvoice = async (invoice: AdminInvoiceResource) => {
    setDetailInvoice(invoice)
    setIsDetailLoading(true)

    try {
      const response = await fetchAdminInvoiceDetail(invoice.id)
      setDetailInvoice(response.result)
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải chi tiết hóa đơn.'))
    } finally {
      setIsDetailLoading(false)
    }
  }

  const cancelInvoice = async (invoice: AdminInvoiceResource) => {
    const note = window.prompt(`Nhập ghi chú hủy hóa đơn ${invoice.invoice_code} (không bắt buộc):`) ?? undefined
    if (note === undefined) return

    try {
      setIsSaving(true)
      const response = await cancelAdminInvoice(invoice.id, note)
      setSuccessMessage('Hủy hóa đơn thành công.')
      setDetailInvoice(response.result)
      await loadInvoices()
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể hủy hóa đơn.'))
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <section className="space-y-5 sm:space-y-6 text-[#24170d]">
      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-4 text-[#fff4df] sm:p-5 lg:p-6">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_14%,rgba(243,197,107,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(15,118,110,0.26),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
          <div className="relative flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
              <Link to="/admin/dashboard" className="mb-2 inline-flex items-center gap-2 text-xs font-black text-[#f3c56b] transition hover:text-[#ffd56f]">
                <ArrowLeft className="h-3.5 w-3.5" /> Về dashboard
              </Link>
              <h1 className="mt-3 flex items-center gap-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem]">
                <Receipt className="h-9 w-9 text-[#f3c56b]" /> Quản lý hóa đơn
              </h1>
            </div>
            <button
              type="button"
              onClick={() => setIsGenerateOpen(true)}
              className="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition hover:bg-[#ffd56f] active:scale-[0.98]"
            >
              <Plus className="h-4 w-4" /> Lập hóa đơn
            </button>
          </div>

          <div className="relative mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Tổng hóa đơn" value={metrics.total} tone="neutral" />
            <MetricCard label="Còn phải thu/trang" value={metrics.unpaid} tone="amber" />
            <MetricCard label="Đã thanh toán/trang" value={metrics.paid} tone="emerald" />
          </div>
        </div>
      </section>

      {(errorMessage || successMessage) && (
        <div className={cn('rounded-3xl border px-4 py-3 text-sm font-black shadow-sm', errorMessage ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700')}>
          {errorMessage || successMessage}
        </div>
      )}

      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
        <div className="border-b border-[#3d2a18]/10 bg-[#fff8eb]/85 p-4 sm:p-5">
          <div className="grid gap-3 xl:grid-cols-[minmax(16rem,1fr)_minmax(10rem,12rem)_minmax(10rem,12rem)_minmax(10rem,12rem)_minmax(8rem,10rem)_minmax(6rem,8rem)]">
            <div className="relative min-w-0">
              <SearchIcon className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
              <input
                type="text"
                value={keyword}
                onChange={(event) => {
                  setKeyword(event.target.value)
                  setCurrentPage(1)
                }}
                placeholder="Tìm mã hóa đơn, hợp đồng, phòng, khách thuê..."
                className={`${inputClass} pl-11 pr-28`}
              />
              <button
                type="button"
                onClick={clearFilters}
                disabled={!hasActiveFilters}
                className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] disabled:opacity-45"
              >
                <X className="h-3.5 w-3.5" /> Xóa lọc
              </button>
            </div>
            <AdminSelect value={selectedStatus} options={invoiceStatusOptions} onChange={(nextValue) => { setSelectedStatus(String(nextValue)); setCurrentPage(1) }} />
            <AdminSelect value={selectedBuildingId} options={filterBuildingOptions} disabled={!isSuperAdmin && buildingOptions.length <= 1} onChange={(nextValue) => { setSelectedBuildingId(String(nextValue)); setSelectedRoomId(''); setCurrentPage(1) }} />
            <AdminSelect value={selectedRoomId} options={[{ value: '', label: 'Tất cả phòng', tone: 'default' as const }, ...roomOptions]} disabled={!selectedBuildingId} onChange={(nextValue) => { setSelectedRoomId(String(nextValue)); setCurrentPage(1) }} />
            <AdminSelect value={selectedMonth} options={monthOptions} onChange={(nextValue) => { setSelectedMonth(String(nextValue)); setCurrentPage(1) }} />
            <input
              type="number"
              min={2020}
              max={2100}
              value={selectedYear}
              onChange={(event) => {
                setSelectedYear(event.target.value)
                setCurrentPage(1)
              }}
              className={inputClass}
              placeholder="Năm"
            />
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[1180px] text-left">
            <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
              <tr>
                <th className="px-5 py-4">Hóa đơn</th>
                <th className="px-5 py-4">Phòng / Tòa nhà</th>
                <th className="px-5 py-4">Kỳ tính tiền</th>
                <th className="px-5 py-4">Tổng / Còn lại</th>
                <th className="px-5 py-4 text-center">Trạng thái</th>
                <th className="px-5 py-4"><div className="flex justify-end"><div className="w-[88px] text-center">Thao tác</div></div></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#3d2a18]/8 bg-[#fffaf1]/70">
              {isLoading && Array.from({ length: 5 }).map((_, index) => (
                <tr key={index}>
                  <td colSpan={6} className="px-5 py-4"><div className="h-14 animate-pulse rounded-2xl bg-stone-100" /></td>
                </tr>
              ))}

              {!isLoading && invoices.map((invoice) => (
                <tr key={invoice.id} className="transition hover:bg-[#f3c56b]/10">
                  <td className="px-5 py-4">
                    <p className="text-sm font-black text-[#24170d]">{invoice.invoice_code}</p>
                    <p className="mt-1 text-xs font-bold text-[#8b5e34]/70">HĐ {invoice.contract_code || invoice.contract_id}</p>
                  </td>
                  <td className="px-5 py-4">
                    <p className="flex items-center gap-1.5 text-xs font-black text-[#8a4f18]"><Building2 className="h-4 w-4" /> {invoice.building_name || 'Chưa rõ tòa nhà'}</p>
                    <p className="mt-1 text-xs font-black text-[#24170d]">Phòng {invoice.room_number || invoice.room_id}</p>
                    {invoice.tenant_name && <p className="mt-1 text-xs font-bold text-[#6f6254]">{invoice.tenant_name}</p>}
                  </td>
                  <td className="px-5 py-4">
                    <p className="flex items-center gap-1.5 text-xs font-black text-[#0f5f59]"><CalendarDays className="h-4 w-4" /> {String(invoice.billing_month).padStart(2, '0')}/{invoice.billing_year}</p>
                    <p className="mt-1 text-xs font-bold text-[#6f6254]">Hạn {formatDate(invoice.due_date)}</p>
                  </td>
                  <td className="px-5 py-4">
                    <p className="text-xs font-black text-[#24170d]">{formatCurrency(invoice.total_amount)}</p>
                    <p className="mt-1 text-xs font-bold text-rose-600">Còn {formatCurrency(invoice.remaining_amount)}</p>
                  </td>
                  <td className="px-5 py-4 text-center"><InvoiceStatusBadge status={invoice.status} label={invoice.status_label || getInvoiceStatusLabel(invoice.status)} /></td>
                  <td className="px-5 py-4">
                    <div className="flex items-center justify-end gap-2">
                      <IconButton title="Xem chi tiết" onClick={() => void viewInvoice(invoice)}><Eye className="h-5 w-5" /></IconButton>
                      
                      {[INVOICE_STATUS_UNPAID, INVOICE_STATUS_PARTIALLY_PAID, INVOICE_STATUS_OVERDUE].includes(Number(invoice.status)) && <IconButton title="Ghi nhận thanh toán" onClick={() => { setPaymentInvoice(invoice); setIsPaymentOpen(true) }}><Banknote className="h-5 w-5" /></IconButton>}
                    </div>
                  </td>
                </tr>
              ))}

              {!isLoading && invoices.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-5 py-20 text-center">
                    <div className="mx-auto flex max-w-sm flex-col items-center rounded-[2rem] border border-dashed border-[#3d2a18]/12 bg-white/55 px-6 py-8">
                      <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]"><Receipt className="h-9 w-9" /></div>
                      <p className="text-lg font-black tracking-tight text-[#24170d]">Không tìm thấy hóa đơn</p>
                      <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">{hasActiveFilters ? 'Thử xóa lọc hoặc đổi từ khóa tìm kiếm.' : 'Hãy lập hóa đơn đầu tiên theo kỳ tháng.'}</p>
                    </div>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb]/85 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
          <p className="text-xs font-black text-[#6f6254]">Hiển thị <span className="tabular-nums text-[#24170d]">{paginationStart}</span>-<span className="tabular-nums text-[#24170d]">{paginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{metrics.total}</span> hóa đơn</p>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div className="w-full sm:w-36"><AdminSelect value={perPage} options={perPageOptions} onChange={(nextValue) => { setPerPage(Number(nextValue)); setCurrentPage(1) }} menuPlacement="top" /></div>
            <div className="flex items-center justify-end gap-1.5">
              <button type="button" disabled={safeCurrentPage <= 1} onClick={() => setCurrentPage(safeCurrentPage - 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:opacity-45"><ChevronLeft className="h-4 w-4" /></button>
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
              <button type="button" disabled={safeCurrentPage >= totalPages} onClick={() => setCurrentPage(safeCurrentPage + 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:opacity-45"><ChevronRight className="h-4 w-4" /></button>
            </div>
          </div>
        </div>
      </section>

      {isGenerateOpen && <GenerateInvoiceModal contracts={contractOptions} isSaving={isSaving} onClose={() => {
        if (isSaving) return
        setIsGenerateOpen(false)
        setPreviewInvoice(null)
        setPendingGeneratePayload(null)
      }} onSubmit={async (payload) => {
        try {
          setIsSaving(true)
          setErrorMessage(null)
          setSuccessMessage(null)
          const response = await previewAdminInvoice(payload)
          setPendingGeneratePayload(payload)
          setPreviewInvoice(response.result)
        } catch (error) {
          setErrorMessage(getVisibleErrorMessage(error, 'Không thể xem trước hóa đơn.'))
        } finally {
          setIsSaving(false)
        }
      }} />}

      {previewInvoice && pendingGeneratePayload && <InvoicePreviewModal invoice={previewInvoice} isIssuing={isSaving} onClose={() => {
        if (isSaving) return
        setPreviewInvoice(null)
      }} onConfirm={async () => {
        try {
          setIsSaving(true)
          setErrorMessage(null)
          setSuccessMessage(null)
          const response = await generateAdminInvoice(pendingGeneratePayload)
          setPreviewInvoice(null)
          setPendingGeneratePayload(null)
          setIsGenerateOpen(false)
          setDetailInvoice(response.result)
          setSuccessMessage('Phát hành hóa đơn thành công.')
          await loadInvoices()
        } catch (error) {
          setErrorMessage(getVisibleErrorMessage(error, 'Không thể phát hành hóa đơn.'))
        } finally {
          setIsSaving(false)
        }
      }} />}

      {detailInvoice && <InvoiceDetailModal invoice={detailInvoice} isLoading={isDetailLoading} isSaving={isSaving} onClose={() => setDetailInvoice(null)} onCancel={() => void cancelInvoice(detailInvoice)} onPay={() => { setPaymentInvoice(detailInvoice); setIsPaymentOpen(true) }} />}

      {isPaymentOpen && paymentInvoice && <PaymentModal invoice={paymentInvoice} isSaving={isSaving} onClose={() => { setIsPaymentOpen(false); setPaymentInvoice(null) }} onSubmit={async (payload) => {
        try {
          setIsSaving(true)
          const response = await recordAdminInvoicePayment(paymentInvoice.id, payload)
          setIsPaymentOpen(false)
          setPaymentInvoice(null)
          setDetailInvoice(response.result)
          setSuccessMessage('Ghi nhận thanh toán thành công.')
          await loadInvoices()
        } catch (error) {
          setErrorMessage(getVisibleErrorMessage(error, 'Không thể ghi nhận thanh toán.'))
        } finally {
          setIsSaving(false)
        }
      }} />}
    </section>
  )
}

function GenerateInvoiceModal({ contracts, isSaving, onClose, onSubmit }: { contracts: Array<{ value: number; label: string; description?: string; tone: 'default' }>; isSaving: boolean; onClose: () => void; onSubmit: (payload: AdminInvoiceGeneratePayload) => Promise<void> }) {
  const period = currentMonthYear()
  const [contractId, setContractId] = useState('')
  const [month, setMonth] = useState(String(period.month))
  const [year, setYear] = useState(String(period.year))
  const [dueDate, setDueDate] = useState('')
  const [adjustmentText, setAdjustmentText] = useState('')

  const adjustments = useMemo(() => parseAdjustments(adjustmentText), [adjustmentText])

  return (
    <ModalFrame title="Lập hóa đơn" onClose={onClose}>
      <div className="space-y-3">
        <AdminSelect value={contractId} options={[{ value: '', label: 'Chọn hợp đồng', tone: 'default' as const }, ...contracts]} onChange={(nextValue) => setContractId(String(nextValue))} />
        <div className="grid grid-cols-2 gap-3">
          <input className={inputClass} type="number" min={1} max={12} value={month} onChange={(event) => setMonth(event.target.value)} />
          <input className={inputClass} type="number" min={2020} max={2100} value={year} onChange={(event) => setYear(event.target.value)} />
        </div>
        <input className={inputClass} type="date" value={dueDate} onChange={(event) => setDueDate(event.target.value)} />
        <textarea className={textAreaClass} value={adjustmentText} onChange={(event) => setAdjustmentText(event.target.value)} placeholder="Điều chỉnh tùy chọn, mỗi dòng: phu_thu|Mô tả|100000 hoặc giam_tru|Mô tả|50000" />
        <p className="text-xs font-bold text-[#6f6254]">Hệ thống tự tính tiền phòng, điện nước, dịch vụ, xe và nợ cũ theo plan. Phần điều chỉnh dùng mã: phu_thu, giam_tru, tang, giam.</p>
        <button type="button" disabled={isSaving || !contractId} onClick={() => onSubmit({ contract_id: Number(contractId), billing_month: Number(month), billing_year: Number(year), due_date: dueDate || null, adjustments })} className="h-12 w-full rounded-xl bg-[#24170d] text-sm font-black text-[#fff4df] shadow-md transition hover:bg-[#3d2a18] disabled:opacity-60">
          {isSaving ? 'Đang xem trước...' : 'Xem trước hóa đơn'}
        </button>
      </div>
    </ModalFrame>
  )
}

function PaymentModal({ invoice, isSaving, onClose, onSubmit }: { invoice: AdminInvoiceResource; isSaving: boolean; onClose: () => void; onSubmit: (payload: { amount: string; payment_method: number; payment_date?: string | null; transaction_reference?: string | null; note?: string | null }) => Promise<void> }) {
  const [amount, setAmount] = useState(invoice.remaining_amount || '')
  const [paymentMethod, setPaymentMethod] = useState(PAYMENT_METHOD_CASH)
  const [paymentDate, setPaymentDate] = useState(new Date().toISOString().slice(0, 10))
  const [reference, setReference] = useState('')
  const [note, setNote] = useState('')

  return (
    <ModalFrame title={`Ghi nhận thanh toán ${invoice.invoice_code}`} onClose={onClose}>
      <div className="space-y-3">
        <div className="rounded-2xl border border-[#3d2a18]/10 bg-white/70 p-3 text-xs font-bold text-[#6f6254]">
          <p>Tổng: <span className="font-black text-[#24170d]">{formatCurrency(invoice.total_amount)}</span></p>
          <p>Còn lại: <span className="font-black text-rose-600">{formatCurrency(invoice.remaining_amount)}</span></p>
        </div>
        <input className={inputClass} value={amount} onChange={(event) => setAmount(event.target.value)} placeholder="Số tiền" />
        <AdminSelect value={paymentMethod} options={[{ value: PAYMENT_METHOD_CASH, label: 'Tiền mặt', tone: 'success' as const }, { value: PAYMENT_METHOD_BANK_TRANSFER, label: 'Chuyển khoản', tone: 'default' as const }]} onChange={(nextValue) => setPaymentMethod(Number(nextValue))} />
        <input className={inputClass} type="date" value={paymentDate} onChange={(event) => setPaymentDate(event.target.value)} />
        <input className={inputClass} value={reference} onChange={(event) => setReference(event.target.value)} placeholder="Mã tham chiếu giao dịch (nếu có)" />
        <textarea className={textAreaClass} value={note} onChange={(event) => setNote(event.target.value)} placeholder="Ghi chú" />
        <button type="button" disabled={isSaving || !amount} onClick={() => onSubmit({ amount, payment_method: paymentMethod, payment_date: paymentDate, transaction_reference: reference.trim() || null, note: note.trim() || null })} className="h-12 w-full rounded-xl bg-[#24170d] text-sm font-black text-[#fff4df] shadow-md transition hover:bg-[#3d2a18] disabled:opacity-60">
          {isSaving ? 'Đang ghi nhận...' : 'Xác nhận thanh toán'}
        </button>
      </div>
    </ModalFrame>
  )
}

function InvoiceDetailModal({ invoice, isLoading, isSaving, onClose, onCancel, onPay }: { invoice: AdminInvoiceResource; isLoading: boolean; isSaving: boolean; onClose: () => void; onCancel: () => void; onPay: () => void }) {
  const canPay = [INVOICE_STATUS_UNPAID, INVOICE_STATUS_PARTIALLY_PAID, INVOICE_STATUS_OVERDUE].includes(Number(invoice.status))

  return (
    <ModalFrame title={`Chi tiết ${invoice.invoice_code}`} onClose={onClose} wide>
      {isLoading ? <div className="h-40 animate-pulse rounded-3xl bg-stone-100" /> : (
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <DetailTile label="Phòng" value={`Phòng ${invoice.room?.room_number || invoice.room_number || invoice.room_id}`} />
            <DetailTile label="Kỳ" value={`${String(invoice.billing_month).padStart(2, '0')}/${invoice.billing_year}`} />
            <DetailTile label="Hạn thanh toán" value={formatDate(invoice.due_date)} />
            <DetailTile label="Trạng thái" value={<InvoiceStatusBadge status={invoice.status} label={invoice.status_label || getInvoiceStatusLabel(invoice.status)} />} />
          </div>
          <div className="grid gap-3 lg:grid-cols-3">
            <DetailTile label="Tổng tiền" value={formatCurrency(invoice.total_amount)} />
            <DetailTile label="Đã trả" value={formatCurrency(invoice.paid_amount)} />
            <DetailTile label="Còn lại" value={formatCurrency(invoice.remaining_amount)} />
          </div>
          <div className="overflow-x-auto rounded-3xl border border-[#3d2a18]/10">
            <table className="w-full min-w-[760px] text-left text-xs">
              <thead className="bg-[#24170d] text-[#fff4df]"><tr><th className="px-4 py-3">Khoản mục</th><th className="px-4 py-3">SL</th><th className="px-4 py-3">Đơn giá</th><th className="px-4 py-3 text-right">Thành tiền</th></tr></thead>
              <tbody className="divide-y divide-[#3d2a18]/8 bg-white/65">
                {(invoice.items || []).map((item) => <tr key={item.id}><td className="px-4 py-3 font-black text-[#24170d]"><p>{item.description}</p><p className="font-bold text-[#8b5e34]/70">{item.item_type_label}</p></td><td className="px-4 py-3 font-bold">{item.quantity}</td><td className="px-4 py-3 font-bold">{formatCurrency(item.unit_price)}</td><td className="px-4 py-3 text-right font-black">{formatCurrency(item.amount)}</td></tr>)}
              </tbody>
            </table>
          </div>
          <div className="rounded-3xl border border-[#3d2a18]/10 bg-white/65 p-4">
            <p className="mb-3 text-sm font-black text-[#24170d]">Thanh toán</p>
            <div className="space-y-2">
              {(invoice.payments || []).length === 0 && <p className="text-xs font-bold text-[#6f6254]">Chưa có giao dịch.</p>}
              {(invoice.payments || []).map((payment) => <div key={payment.id} className="flex flex-col gap-1 rounded-2xl bg-[#fffaf1] p-3 text-xs font-bold text-[#6f6254] sm:flex-row sm:items-center sm:justify-between"><span>{payment.payment_code} · {payment.payment_method_label} · {formatDateTime(payment.payment_date)}</span><span className="font-black text-[#24170d]">{formatCurrency(payment.amount)} · {payment.status_label}</span></div>)}
            </div>
          </div>
          <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
            
            {canPay && <button disabled={isSaving} type="button" onClick={onPay} className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#24170d] px-4 text-sm font-black text-[#fff4df] disabled:opacity-60"><WalletCards className="h-4 w-4" /> Ghi nhận thanh toán</button>}
            {![INVOICE_STATUS_PAID, INVOICE_STATUS_CANCELLED].includes(Number(invoice.status)) && <button disabled={isSaving} type="button" onClick={onCancel} className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 text-sm font-black text-rose-700 disabled:opacity-60"><X className="h-4 w-4" /> Hủy</button>}
          </div>
        </div>
      )}
    </ModalFrame>
  )
}

function parseAdjustments(value: string): AdminInvoiceAdjustmentPayload[] {
  return value.split('\n').map((line) => line.trim()).filter(Boolean).map((line) => {
    const [rawType, description, amount] = line.split('|').map((part) => part.trim())
    const type = rawType === 'giam_tru' ? ITEM_TYPE_DISCOUNT : rawType === 'tang' ? ITEM_TYPE_ADJUST_INCREASE : rawType === 'giam' ? ITEM_TYPE_ADJUST_DECREASE : ITEM_TYPE_SURCHARGE
    return { item_type: type, description: description || 'Điều chỉnh hóa đơn', quantity: '1', unit_price: amount || '0' }
  })
}

function ModalFrame({ title, onClose, children, wide }: { title: string; onClose: () => void; children: React.ReactNode; wide?: boolean }) {
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

function MetricCard({ label, value, tone }: { label: string; value: number; tone: 'neutral' | 'emerald' | 'amber' | 'teal' }) {
  const toneClassNames = {
    neutral: 'border-[#f8e8c8]/12 bg-[#f8e8c8]/10 text-[#fff4df]',
    emerald: 'border-[#0f766e]/35 bg-[#0f766e]/16 text-[#c8fff4]',
    amber: 'border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#fff4df]',
    teal: 'border-cyan-200/25 bg-cyan-100/10 text-cyan-50',
  }[tone]

  return <div className={cn('rounded-3xl border px-4 py-3 backdrop-blur', toneClassNames)}><p className="text-[10px] font-black uppercase tracking-[0.18em] opacity-65">{label}</p><p className="mt-1 text-3xl font-black tracking-tight tabular-nums">{value}</p></div>
}

function IconButton({ title, disabled, onClick, children }: { title: string; disabled?: boolean; onClick: () => void; children: React.ReactNode }) {
  return <button type="button" disabled={disabled} onClick={onClick} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] disabled:opacity-45" title={title} aria-label={title}>{children}</button>
}

function DetailTile({ label, value }: { label: string; value?: React.ReactNode }) {
  return <div className="min-w-0 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3 shadow-sm"><p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/60">{label}</p><div className="mt-1 break-words text-sm font-black text-[#24170d]">{value ?? '—'}</div></div>
}

function InvoiceStatusBadge({ status, label }: { status: number; label: string }) {
  const className = Number(status) === INVOICE_STATUS_PAID ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]' : [INVOICE_STATUS_UNPAID, INVOICE_STATUS_OVERDUE].includes(Number(status)) ? 'border-rose-200 bg-rose-50 text-rose-700' : Number(status) === INVOICE_STATUS_CANCELLED ? 'border-stone-200 bg-stone-50 text-stone-600' : 'border-[#f3c56b]/45 bg-[#f3c56b]/18 text-[#8a4f18]'
  return <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-xs font-black shadow-sm', className)}>{label}</span>
}

function SearchIcon(props: React.SVGProps<SVGSVGElement>) {
  return <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></svg>
}
