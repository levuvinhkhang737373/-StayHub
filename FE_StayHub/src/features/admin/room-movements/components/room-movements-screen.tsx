import { useCallback, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { useSearchParams } from 'react-router-dom'
import { AlertTriangle, ArrowDown, ArrowRightLeft, Banknote, CalendarDays, ChevronLeft, ChevronRight, Clock3, Eye, FilterX, HandCoins, History, Loader2, ReceiptText, Search, X } from 'lucide-react'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { AdminDateInput } from '../../../../shared/components/AdminDateInput'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatCurrency, formatDateTime } from '../../../../shared/lib/utils/format'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import type { AdminProfile } from '../../auth/types/admin-auth.model'
import { fetchAdminRooms, fetchBuilding } from '../../rooms/services/rooms.service'
import type { AdminRoomResource, BuildingResource } from '../../rooms/types/rooms.model'
import { fetchAdminRoomMovementDetail, fetchAdminRoomMovements, recordAdminRoomMovementSettlementCashPayment } from '../services/room-movements.service'
import type { AdminRoomMovementPaginationMeta, AdminRoomMovementResource } from '../types/room-movement-api.model'

const MOVEMENT_TRANSFER = 2
const MOVEMENT_CHECKOUT = 1
const MOVEMENT_STATUS_PENDING = 1
const MOVEMENT_STATUS_EXECUTED = 2
const MOVEMENT_STATUS_BLOCKED = 3
const MOVEMENT_STATUS_CANCELLED = 4
const SETTLEMENT_STATUS_PAID = 2

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'

const movementTypeOptions = [
  { value: '', label: 'Tất cả biến động', tone: 'default' as const },
  { value: MOVEMENT_TRANSFER, label: 'Chuyển phòng', tone: 'success' as const },
  { value: MOVEMENT_CHECKOUT, label: 'Trả phòng', tone: 'warning' as const },
]

const movementStatusOptions = [
  { value: '', label: 'Tất cả trạng thái', tone: 'default' as const },
  { value: MOVEMENT_STATUS_PENDING, label: 'Chờ xử lý', tone: 'warning' as const },
  { value: MOVEMENT_STATUS_EXECUTED, label: 'Đã chuyển', tone: 'success' as const },
  { value: MOVEMENT_STATUS_BLOCKED, label: 'Đang bị chặn', tone: 'danger' as const },
  { value: MOVEMENT_STATUS_CANCELLED, label: 'Đã hủy', tone: 'default' as const },
]

const perPageOptions = [
  { value: 10, label: '10 dòng', tone: 'default' as const },
  { value: 20, label: '20 dòng', tone: 'default' as const },
  { value: 50, label: '50 dòng', tone: 'default' as const },
  { value: 100, label: '100 dòng', tone: 'default' as const },
]

const tableHeadCellClass = 'whitespace-nowrap px-5 py-4 align-middle'
const tableBodyCellClass = 'whitespace-nowrap px-5 py-4 align-middle'

export function RoomMovementsScreen() {
  const { session } = useAdminSession()
  const isSuperAdmin = useMemo(() => isSuperAdminRole(session?.admin?.role), [session?.admin?.role])
  const managedBuildingId = session?.admin?.managed_buildings?.[0]?.id
  const currentAdmin = session?.admin ?? null

  const [searchParams, setSearchParams] = useSearchParams()
  const tenantIdFilter = searchParams.get('tenant_id') || ''
  const contractIdFilter = searchParams.get('contract_id') || ''
  const keywordFilter = searchParams.get('keyword') || ''
  const deepLinkFilterKey = `${tenantIdFilter}:${contractIdFilter}:${keywordFilter}`
  const [keyword, setKeyword] = useState(keywordFilter)
  const [movementType, setMovementType] = useState<string | number>('')
  const [movementStatus, setMovementStatus] = useState<string | number>('')
  const [buildingId, setBuildingId] = useState<string | number>(isSuperAdmin ? '' : managedBuildingId ? String(managedBuildingId) : '')
  const [roomId, setRoomId] = useState<string | number>('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [perPage, setPerPage] = useState(20)
  const [pageState, setPageState] = useState({ key: deepLinkFilterKey, page: 1 })
  const [movements, setMovements] = useState<AdminRoomMovementResource[]>([])
  const [paginationMeta, setPaginationMeta] = useState<AdminRoomMovementPaginationMeta | null>(null)
  const [buildings, setBuildings] = useState<BuildingResource[]>([])
  const [rooms, setRooms] = useState<AdminRoomResource[]>([])
  const [selectedMovement, setSelectedMovement] = useState<AdminRoomMovementResource | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isOptionsLoading, setIsOptionsLoading] = useState(true)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [cashPaymentMovement, setCashPaymentMovement] = useState<AdminRoomMovementResource | null>(null)
  const [cashPaymentNote, setCashPaymentNote] = useState('')
  const [isCashPaymentSubmitting, setIsCashPaymentSubmitting] = useState(false)
  const [cashPaymentErrorMessage, setCashPaymentErrorMessage] = useState<string | null>(null)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null)
  const currentPage = pageState.key === deepLinkFilterKey ? pageState.page : 1

  const setCurrentPage = useCallback((page: number) => {
    setPageState({ key: deepLinkFilterKey, page })
  }, [deepLinkFilterKey])

  const loadMovements = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)

    try {
      const response = await fetchAdminRoomMovements({
        keyword: keyword.trim() || undefined,
        movement_type: movementType ? Number(movementType) : undefined,
        status: movementStatus ? Number(movementStatus) : undefined,
        building_id: buildingId ? Number(buildingId) : undefined,
        room_id: roomId ? Number(roomId) : undefined,
        tenant_id: tenantIdFilter ? Number(tenantIdFilter) : undefined,
        contract_id: contractIdFilter ? Number(contractIdFilter) : undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
        page: currentPage,
        per_page: perPage,
      })

      setMovements(response.result?.data || [])
      setPaginationMeta(response.result?.meta || null)

      if (response.result?.meta?.last_page && currentPage > response.result.meta.last_page) {
        setCurrentPage(response.result.meta.last_page)
      }
    } catch (error) {
      setMovements([])
      setPaginationMeta(null)
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải lịch sử phòng và cọc.'))
    } finally {
      setIsLoading(false)
    }
  }, [buildingId, contractIdFilter, currentPage, dateFrom, dateTo, keyword, movementStatus, movementType, perPage, roomId, setCurrentPage, tenantIdFilter])

  useEffect(() => {
    queueMicrotask(() => setKeyword(keywordFilter))
  }, [keywordFilter])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadMovements()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [loadMovements])

  useEffect(() => {
    async function loadOptions() {
      setIsOptionsLoading(true)
      try {
        const [buildingResponse, roomResponse] = await Promise.all([
          fetchBuilding(),
          fetchAdminRooms({ per_page: 1000 }),
        ])

        const list = buildingResponse.result || []
        setBuildings(list)
        setRooms(roomResponse.result || [])
        if (!isSuperAdmin && !buildingId && list[0]?.id) {
          setBuildingId(list[0].id)
        }
      } catch (error) {
        console.error('Không thể tải bộ lọc lịch sử phòng', error)
      } finally {
        setIsOptionsLoading(false)
      }
    }

    void loadOptions()
  }, [isSuperAdmin, buildingId])

  useEffect(() => {
    if (!isDetailOpen) return

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') closeDetail()
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [isDetailOpen])

  const buildingOptions = useMemo(() => isSuperAdmin ? [
    { value: '', label: 'Tất cả tòa nhà', tone: 'default' as const },
    ...buildings.map((building) => ({ value: building.id, label: building.name, tone: 'default' as const })),
  ] : buildings.map((building) => ({ value: building.id, label: building.name, tone: 'default' as const })), [buildings, isSuperAdmin])

  const roomOptions = useMemo(() => [
    { value: '', label: 'Tất cả phòng', tone: 'default' as const },
    ...rooms
      .filter((room) => !buildingId || Number(room.building_id) === Number(buildingId))
      .map((room) => ({
        value: room.id,
        label: `Phòng ${room.room_number}${room.building?.name || room.building_name ? ` · ${room.building?.name || room.building_name}` : ''}`,
        tone: 'default' as const,
      })),
  ], [buildingId, rooms])

  const safeCurrentPage = Math.max(1, Math.min(currentPage, paginationMeta?.last_page ?? currentPage))
  const totalPages = Math.max(1, paginationMeta?.last_page ?? (movements.length >= perPage ? currentPage + 1 : currentPage))
  const paginationStart = paginationMeta?.from ?? (movements.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + 1)
  const paginationEnd = paginationMeta?.to ?? (movements.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + movements.length)
  const totalMovements = paginationMeta?.total ?? (safeCurrentPage - 1) * perPage + movements.length
  const visiblePages = useMemo(() => {
    const pages = new Set<number>([1, totalPages, safeCurrentPage - 1, safeCurrentPage, safeCurrentPage + 1])
    return Array.from(pages)
      .filter((page) => page >= 1 && page <= totalPages)
      .sort((left, right) => left - right)
  }, [safeCurrentPage, totalPages])
  const transferCount = useMemo(() => movements.filter((movement) => Number(movement.movement_type) === MOVEMENT_TRANSFER).length, [movements])
  const pendingCount = useMemo(() => movements.filter((movement) => Number(movement.status) === MOVEMENT_STATUS_PENDING).length, [movements])
  const blockedCount = useMemo(() => movements.filter((movement) => Number(movement.status) === MOVEMENT_STATUS_BLOCKED).length, [movements])
  const hasDeepLinkFilter = Boolean(tenantIdFilter || contractIdFilter || keywordFilter)
  const hasActiveFilters = Boolean(keyword || movementType || movementStatus || buildingId || roomId || dateFrom || dateTo || hasDeepLinkFilter)

  function updateFilter(setter: (value: string) => void, value: string) {
    setter(value)
    setCurrentPage(1)
  }

  function updateSelectFilter(setter: (value: string | number) => void, value: string | number) {
    setter(value)
    setCurrentPage(1)
  }

  function clearFilters() {
    setKeyword('')
    setMovementType('')
    setMovementStatus('')
    setBuildingId(isSuperAdmin ? '' : (buildings[0]?.id ? String(buildings[0].id) : ''))
    setRoomId('')
    setDateFrom('')
    setDateTo('')
    setSearchParams({})
    setCurrentPage(1)
  }

  function changePage(page: number) {
    setCurrentPage(Math.min(Math.max(1, page), totalPages))
  }

  function changePerPage(nextValue: string | number) {
    setPerPage(Number(nextValue))
    setCurrentPage(1)
  }

  async function openDetail(movement: AdminRoomMovementResource) {
    setSelectedMovement(movement)
    setIsDetailOpen(true)
    setIsDetailLoading(true)
    setDetailErrorMessage(null)

    try {
      const response = await fetchAdminRoomMovementDetail(movement.id)
      setSelectedMovement(response.result)
    } catch (error) {
      setDetailErrorMessage(getVisibleErrorMessage(error, 'Không thể tải chi tiết lịch sử phòng và cọc.'))
    } finally {
      setIsDetailLoading(false)
    }
  }

  function closeDetail() {
    setIsDetailOpen(false)
    setSelectedMovement(null)
    setDetailErrorMessage(null)
  }

  function openCashPayment(movement: AdminRoomMovementResource) {
    setCashPaymentMovement(movement)
    setCashPaymentNote('')
    setCashPaymentErrorMessage(null)
  }

  function closeCashPayment() {
    if (isCashPaymentSubmitting) return
    setCashPaymentMovement(null)
    setCashPaymentNote('')
    setCashPaymentErrorMessage(null)
  }

  async function submitCashPayment() {
    if (!cashPaymentMovement) return

    setIsCashPaymentSubmitting(true)
    setCashPaymentErrorMessage(null)

    try {
      const response = await recordAdminRoomMovementSettlementCashPayment(cashPaymentMovement.id, {
        note: cashPaymentNote.trim() || undefined,
      })

      const freshMovement = response.result?.movement
      if (freshMovement) {
        setSelectedMovement(freshMovement)
      }

      setCashPaymentMovement(null)
      setCashPaymentNote('')
      await loadMovements()
    } catch (error) {
      setCashPaymentErrorMessage(getVisibleErrorMessage(error, 'Không thể ghi nhận thu tiền mặt chuyển phòng.'))
    } finally {
      setIsCashPaymentSubmitting(false)
    }
  }

  return (
    <>
      <section className="space-y-6 text-[#24170d]">
        <section className="overflow-hidden rounded-[2.15rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
          <div className="relative p-5 text-[#fff4df] sm:p-6 lg:p-7">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_16%_16%,rgba(243,197,107,0.28),transparent_30%),radial-gradient(circle_at_86%_4%,rgba(15,118,110,0.32),transparent_30%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
            <div className="pointer-events-none absolute inset-x-6 bottom-0 h-px bg-gradient-to-r from-transparent via-[#f3c56b]/45 to-transparent" />
            <div className="relative grid gap-6 lg:grid-cols-[1.05fr_0.95fr] lg:items-end">
              <div>
                <span className="block text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]/80">KHÁCH THUÊ & HỢP ĐỒNG</span>
                <h1 className="mt-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem] flex items-center gap-3">
                  <History className="h-8 w-8 text-[#f3c56b] shrink-0" />
                  Lịch sử phòng & cọc
                </h1>
              </div>

              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <MetricCard label="Tổng ghi nhận" value={totalMovements} icon={<ReceiptText className="h-4 w-4" />} />
                <MetricCard label="Chuyển phòng" value={transferCount} icon={<ArrowRightLeft className="h-4 w-4" />} />
                <MetricCard label="Chờ xử lý" value={pendingCount} icon={<Clock3 className="h-4 w-4" />} />
                <MetricCard label="Bị chặn" value={blockedCount} icon={<AlertTriangle className="h-4 w-4" />} />
              </div>
            </div>
          </div>
        </section>

        <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-4 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur lg:p-5">
          <div className="grid grid-cols-1 gap-3 lg:grid-cols-2 lg:grid-cols-[1.25fr_0.82fr_0.82fr_0.9fr_0.9fr_0.8fr_0.8fr_auto]">
            <label className="relative block">
              <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8b5e34]/60" />
              <input value={keyword} onChange={(event) => updateFilter(setKeyword, event.target.value)} placeholder="Tìm khách, phòng, hợp đồng, transfer code..." className={cn(inputClass, 'pl-11')} />
            </label>
            <AdminSelect value={movementType} options={movementTypeOptions} onChange={(value) => updateSelectFilter(setMovementType, value)} />
            <AdminSelect value={movementStatus} options={movementStatusOptions} onChange={(value) => updateSelectFilter(setMovementStatus, value)} />
            <AdminSelect value={buildingId} options={buildingOptions} onChange={(value) => { updateSelectFilter(setBuildingId, value); setRoomId('') }} disabled={isOptionsLoading} />
            <AdminSelect value={roomId} options={roomOptions} onChange={(value) => updateSelectFilter(setRoomId, value)} disabled={isOptionsLoading} />
            <AdminDateInput value={dateFrom} onChange={(value) => updateFilter(setDateFrom, value)} placeholder="Từ ngày" className={inputClass} />
            <AdminDateInput value={dateTo} onChange={(value) => updateFilter(setDateTo, value)} placeholder="Đến ngày" className={inputClass} />
            <button type="button" onClick={clearFilters} disabled={!hasActiveFilters} className="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl border border-[#3d2a18]/10 bg-[#24170d] px-4 text-xs font-black uppercase tracking-[0.16em] text-[#fff4df] shadow-lg shadow-[#24170d]/10 transition hover:bg-[#3d2a18] disabled:cursor-not-allowed disabled:opacity-45">
              <FilterX className="h-4 w-4" /> Xóa lọc
            </button>
          </div>

          {hasDeepLinkFilter && (
            <div className="mt-3 flex flex-wrap gap-2 text-xs font-black text-[#0f5f59]">
              {tenantIdFilter && <span className="rounded-full border border-[#0f766e]/20 bg-[#0f766e]/10 px-3 py-1">Đang lọc khách thuê #{tenantIdFilter}</span>}
              {contractIdFilter && <span className="rounded-full border border-[#0f766e]/20 bg-[#0f766e]/10 px-3 py-1">Đang lọc hợp đồng #{contractIdFilter}</span>}
              {keywordFilter && <span className="rounded-full border border-[#0f766e]/20 bg-[#0f766e]/10 px-3 py-1">Mã/từ khóa: {keywordFilter}</span>}
            </div>
          )}
        </section>

        <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
          {errorMessage && <div className="m-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{errorMessage}</div>}

          <div className="overflow-x-auto custom-scrollbar">
            <table className="w-full text-left">
              <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
                <tr>
                  <th scope="col" className={cn(tableHeadCellClass, 'pl-5')}>Thời điểm</th>
                  <th scope="col" className={cn(tableHeadCellClass, 'text-center')}>Mã lịch</th>
                  <th scope="col" className={tableHeadCellClass}>Khách thuê</th>
                  <th scope="col" className={cn(tableHeadCellClass, 'text-center')}>Luồng phòng</th>
                  <th scope="col" className={cn(tableHeadCellClass, 'text-center')}>Loại</th>
                  <th scope="col" className={cn(tableHeadCellClass, 'text-center')}>Trạng thái</th>
                  <th scope="col" className={cn(tableHeadCellClass, 'text-center')}>Quyết toán</th>
                  <th scope="col" className={cn(tableHeadCellClass, 'text-center')}>Hợp đồng</th>
                  <th scope="col" className={cn(tableHeadCellClass, 'text-center')}>Người xử lý</th>
                  <th scope="col" className={cn(tableHeadCellClass, 'pr-5 text-right')}>Chi tiết</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[#3d2a18]/8 bg-[#fffaf1]/70">
                {isLoading && (
                  <tr>
                    <td colSpan={10} className="px-5 py-16 text-center text-sm font-black text-[#8b5e34]">
                      <span className="inline-flex items-center gap-2"><Loader2 className="h-4 w-4 animate-spin" /> Đang tải lịch sử phòng và cọc...</span>
                    </td>
                  </tr>
                )}

                {!isLoading && movements.map((movement) => (
                  <tr key={movement.id} className="group transition hover:bg-[#f3c56b]/10">
                    <td className={cn(tableBodyCellClass, 'pl-5 text-[13px] font-black text-[#24170d]')}>
                      <div className="flex items-start gap-2">
                        <CalendarDays className="mt-0.5 h-4 w-4 shrink-0 text-[#8b5e34]" />
                        {(() => {
                          const dateTimeStr = formatDateTime(movement.movement_date)
                          if (dateTimeStr === '—') return <span className="whitespace-nowrap tabular-nums text-[13px] font-black text-[#24170d]">—</span>
                          const parts = dateTimeStr.split(' ')
                          const time = parts[0]
                          const date = parts[1]
                          return (
                            <div className="flex flex-col leading-tight">
                              <span className="whitespace-nowrap tabular-nums text-[13px] font-black text-[#24170d]">{date}</span>
                              {time && <span className="mt-0.5 whitespace-nowrap tabular-nums text-[11px] font-bold text-[#8b5e34]">{time}</span>}
                            </div>
                          )
                        })()}
                      </div>
                    </td>
                    <td className={cn(tableBodyCellClass, 'text-center text-[12px] font-black text-[#24170d]')}>
                      <span className="inline-flex whitespace-nowrap rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] px-3 py-1 text-[11px] font-black leading-4 text-[#3d2a18]">{movement.transfer_code || '—'}</span>
                    </td>
                    <td className={tableBodyCellClass}>
                      <p className="truncate text-[13px] font-black leading-5 text-[#24170d]" title={movement.tenant?.full_name || movement.tenant?.username || `#${movement.tenant_id}`}>{movement.tenant?.full_name || movement.tenant?.username || `#${movement.tenant_id}`}</p>
                      <p className="mt-1 truncate text-[11px] font-bold text-[#6f6254]" title={movement.tenant?.phone || movement.tenant?.email || '—'}>{movement.tenant?.phone || movement.tenant?.email || '—'}</p>
                    </td>
                    <td className={cn(tableBodyCellClass, 'text-center')}>
                      <div className="inline-block text-left">
                        <RoomFlow movement={movement} />
                      </div>
                    </td>
                    <td className={cn(tableBodyCellClass, 'text-center')}><MovementBadge movement={movement} /></td>
                    <td className={cn(tableBodyCellClass, 'text-center')}><StatusBadge movement={movement} /></td>
                    <td className={cn(tableBodyCellClass, 'text-center')}><SettlementBadge movement={movement} /></td>
                    <td className={cn(tableBodyCellClass, 'text-center text-[12px] font-black text-[#24170d]')}>
                      <span className="inline-flex whitespace-nowrap rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] px-3 py-1 text-[11px] font-black leading-4 text-[#3d2a18]">{movement.contract?.contract_code || (movement.contract_id ? `#${movement.contract_id}` : '—')}</span>
                    </td>
                    <td className={cn(tableBodyCellClass, 'text-center truncate text-[12px] font-black leading-5 text-[#6f6254]')} title={movement.creator_name || '—'}>{movement.creator_name || '—'}</td>
                    <td className={cn(tableBodyCellClass, 'pr-5 text-right')}>
                      <button type="button" onClick={() => void openDetail(movement)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95" title="Xem chi tiết" aria-label="Xem chi tiết lịch sử phòng và cọc">
                        <Eye className="h-5 w-5" />
                      </button>
                    </td>
                  </tr>
                ))}

                {!isLoading && movements.length === 0 && (
                  <tr>
                    <td colSpan={10} className="px-5 py-20 text-center">
                      <div className="mx-auto flex max-w-sm flex-col items-center rounded-[2rem] border border-dashed border-[#3d2a18]/12 bg-[#fffaf1]/70 px-6 py-8">
                        <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]"><History className="h-9 w-9" /></div>
                        <p className="text-lg font-black tracking-tight text-[#24170d]">Chưa có lịch sử phù hợp</p>
                        <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">Thử đổi bộ lọc hoặc kiểm tra lại nghiệp vụ chuyển/trả phòng.</p>
                      </div>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb]/85 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
            <p className="text-xs font-black text-[#6f6254]">
              Hiển thị <span className="tabular-nums text-[#24170d]">{paginationStart}</span>-<span className="tabular-nums text-[#24170d]">{paginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{totalMovements}</span> bản ghi
            </p>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
              <div className="w-full sm:w-36">
                <AdminSelect value={perPage} options={perPageOptions} onChange={changePerPage} menuPlacement="top" />
              </div>
              <div className="flex items-center justify-end gap-1.5">
                <button type="button" disabled={safeCurrentPage <= 1} onClick={() => changePage(safeCurrentPage - 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang trước">
                  <ChevronLeft className="h-4 w-4" />
                </button>
                {visiblePages.map((page, index) => {
                  const previousPage = visiblePages[index - 1]
                  const hasGap = previousPage && page - previousPage > 1

                  return (
                    <div key={page} className="flex items-center gap-1.5">
                      {hasGap && <span className="px-1 text-xs font-black text-[#8b5e34]/60">...</span>}
                      <button type="button" onClick={() => changePage(page)} className={cn('inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition', page === safeCurrentPage ? 'border-[#24170d] bg-[#24170d] text-[#fff4df] shadow-sm' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15')} aria-current={page === safeCurrentPage ? 'page' : undefined}>
                        {page}
                      </button>
                    </div>
                  )
                })}
                <button type="button" disabled={safeCurrentPage >= totalPages} onClick={() => changePage(safeCurrentPage + 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang sau">
                  <ChevronRight className="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>
        </section>
      </section>

      {isDetailOpen && selectedMovement && (
        <DetailModal movement={selectedMovement} currentAdmin={currentAdmin} isLoading={isDetailLoading} errorMessage={detailErrorMessage} onClose={closeDetail} onOpenCashPayment={openCashPayment} />
      )}

      {cashPaymentMovement && (
        <CashSettlementPaymentModal
          movement={cashPaymentMovement}
          note={cashPaymentNote}
          errorMessage={cashPaymentErrorMessage}
          isSubmitting={isCashPaymentSubmitting}
          onNoteChange={setCashPaymentNote}
          onClose={closeCashPayment}
          onConfirm={() => void submitCashPayment()}
        />
      )}
    </>
  )
}

function MetricCard({ label, value, icon }: { label: string; value: number; icon: ReactNode }) {
  return (
    <div className="rounded-3xl border border-[#f8e8c8]/12 bg-[#f8e8c8]/10 px-4 py-3 text-[#fff4df] backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/10">
      <div className="flex items-center justify-between gap-3 text-[#f3c56b]">{icon}<span className="text-[10px] font-black uppercase tracking-[0.18em] opacity-75">{label}</span></div>
      <p className="mt-2 text-3xl font-black tracking-tight tabular-nums">{value}</p>
    </div>
  )
}

function MovementBadge({ movement }: { movement: AdminRoomMovementResource }) {
  const isTransfer = Number(movement.movement_type) === MOVEMENT_TRANSFER
  return (
    <span className={cn('inline-flex items-center whitespace-nowrap rounded-full border px-3 py-1.5 text-[11px] font-black leading-none', isTransfer ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]' : 'border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#8a4f18]')}>
      {movement.movement_type_label || (isTransfer ? 'Chuyển phòng' : 'Trả phòng')}
    </span>
  )
}

function StatusBadge({ movement }: { movement: AdminRoomMovementResource }) {
  const status = Number(movement.status)
  const className = status === MOVEMENT_STATUS_EXECUTED
    ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]'
    : status === MOVEMENT_STATUS_BLOCKED
      ? 'border-rose-200 bg-rose-50 text-rose-700'
      : status === MOVEMENT_STATUS_CANCELLED
        ? 'border-[#3d2a18]/10 bg-[#efe2cf]/70 text-[#6f6254]'
        : 'border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#8a4f18]'

  return (
    <span className={cn('inline-flex items-center whitespace-nowrap rounded-full border px-3 py-1.5 text-[11px] font-black leading-none', className)}>
      {movement.status_label || 'Chờ xử lý'}
    </span>
  )
}

function SettlementBadge({ movement }: { movement: AdminRoomMovementResource }) {
  const dueAmount = Number(movement.settlement_due_amount ?? 0)
  const remainingAmount = Number(movement.settlement_remaining_amount ?? 0)

  if (!Number.isFinite(dueAmount) || dueAmount <= 0) {
    return <span className="text-[12px] font-black text-[#6f6254]">Không phát sinh</span>
  }

  const isPaid = remainingAmount <= 0

  return (
    <div className="space-y-1 leading-none">
      <p className={cn('text-[12px] font-black tabular-nums', isPaid ? 'text-[#0f5f59]' : 'text-[#8a4f18]')}>{formatCurrency(remainingAmount)}</p>
      <p className="text-[10px] font-black uppercase tracking-[0.14em] text-[#6f6254]">{movement.settlement_payment_status_label || (isPaid ? 'Đã thanh toán' : 'Chờ QR')}</p>
    </div>
  )
}

function RoomFlow({ movement }: { movement: AdminRoomMovementResource }) {
  return (
    <div className="flex flex-col items-center gap-1.5 text-[12px] font-black leading-none text-[#24170d]">
      <span className="inline-flex shrink-0 whitespace-nowrap rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] px-3 py-1.5">{roomLabel(movement.from_room, 'Phòng cũ')}</span>
      <ArrowDown className="h-4 w-4 shrink-0 text-[#8b5e34]" />
      <span className="inline-flex shrink-0 whitespace-nowrap rounded-xl border border-[#0f766e]/15 bg-[#0f766e]/8 px-3 py-1.5 text-[#0f5f59]">{roomLabel(movement.to_room, 'Trả phòng')}</span>
    </div>
  )
}

function DetailModal({ movement, currentAdmin, isLoading, errorMessage, onClose, onOpenCashPayment }: { movement: AdminRoomMovementResource; currentAdmin: AdminProfile | null; isLoading: boolean; errorMessage: string | null; onClose: () => void; onOpenCashPayment: (movement: AdminRoomMovementResource) => void }) {
  const hasMeterReadings = Boolean(movement.final_electric_reading || movement.final_water_reading)
  const settlementBreakdown = useMemo(() => makeSettlementBreakdown(movement), [movement])
  const canCollectCash = canRecordCashSettlementPayment(movement, currentAdmin)

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="room-movement-detail-title">
      <button type="button" aria-label="Đóng chi tiết lịch sử" onClick={onClose} className="absolute inset-0 bg-[#120b06]/75 backdrop-blur-sm" />
      <aside className="relative z-10 max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-[2rem] border border-[#f3c56b]/25 bg-[#fffaf1] shadow-2xl shadow-black/30">
        <div className="sticky top-0 z-10 rounded-t-[2rem] bg-[#24170d] p-5 text-[#fff4df] shadow-xl shadow-black/15">
          <div className="flex items-start justify-between gap-4">
            <div>
              <p className="text-[10px] font-black uppercase tracking-[0.22em] text-[#f3c56b]">Chi tiết ledger</p>
              <h2 id="room-movement-detail-title" className="mt-2 text-2xl font-black tracking-tight">{movement.tenant?.full_name || movement.tenant?.username || `Khách #${movement.tenant_id}`}</h2>
              <div className="mt-2 flex flex-wrap items-center gap-2 text-sm font-semibold text-[#f8e8c8]/78">
                <span>{formatDateTime(movement.movement_date)} · {movement.movement_type_label || 'Biến động phòng'}</span>
                {movement.transfer_code && <span className="rounded-full border border-[#f3c56b]/25 bg-[#f3c56b]/10 px-3 py-1 text-xs font-black text-[#f3c56b]">{movement.transfer_code}</span>}
              </div>
            </div>
            <button type="button" onClick={onClose} className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20" aria-label="Đóng chi tiết lịch sử">
              <X className="h-5 w-5" />
            </button>
          </div>
        </div>

        <div className="space-y-4 p-5">
          {isLoading && <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-800">Đang tải chi tiết...</div>}
          {errorMessage && <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{errorMessage}</div>}

          <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
            <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Lịch chuyển</p>
            <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <DetailTile label="Mã chuyển" value={movement.transfer_code || '—'} />
              <DetailTile label="Trạng thái" value={<StatusBadge movement={movement} />} />
              <DetailTile label="Ngày chuyển" value={formatDateTime(movement.movement_date)} />
              <DetailTile label="Đã execute lúc" value={formatDateTime(movement.executed_at)} />
              <DetailTile label="Người xử lý" value={movement.creator_name || '—'} />
              <DetailTile label="Thanh toán settlement" value={movement.settlement_payment_status_label || '—'} />
            </div>
          </section>

          <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
            <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Luồng phòng</p>
            <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <DetailTile label="Phòng cũ" value={roomLabel(movement.from_room, '—')} />
              <DetailTile label="Phòng mới" value={roomLabel(movement.to_room, 'Trả phòng')} />
              <DetailTile label="Hợp đồng ghi nhận" value={movement.contract?.contract_code || (movement.contract_id ? `#${movement.contract_id}` : '—')} />
              <DetailTile label="Hợp đồng nguồn" value={movement.source_contract?.contract_code || (movement.source_contract_id ? `#${movement.source_contract_id}` : '—')} />
              <DetailTile label="H�            <div className="border-b border-[#3d2a18]/10 bg-[#24170d] px-4 py-3 text-[#fff4df]">
              <p className="text-[10px] font-black uppercase tracking-[0.24em] text-[#f3c56b]">Cọc & thanh toán chuyển phòng</p>
            </div>

            <div className="grid gap-4 p-4 lg:grid-cols-[1.1fr_0.9fr]">
              <div className="space-y-3">
                <div className="rounded-[1.25rem] border border-[#3d2a18]/10 bg-[#fffaf1] p-4">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Cọc hợp đồng cũ</p>
                      <h3 className="mt-1 text-lg font-black text-[#24170d]">{settlementBreakdown.oldDepositTitle}</h3>
                    </div>
                    <span className={cn('rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em]', settlementBreakdown.usesOldDeposit ? 'bg-[#0f766e]/10 text-[#0f5f59]' : 'bg-[#8b5e34]/10 text-[#8b5e34]')}>
                      {settlementBreakdown.usesOldDeposit ? 'Có dùng cọc cũ' : 'Không dùng cọc cũ'}
                    </span>
                  </div>
                  <div className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <MoneyTile label="Số dư cọc cũ" value={movement.old_room_final_amount} tone="neutral" compact />
                    <MoneyTile label="Cọc chuyển sang" value={movement.deposit_transfer_amount} tone="success" compact />
                    <MoneyTile label="Hoàn cọc dư" value={settlementBreakdown.refundAmount} tone="warning" compact />
                  </div>
                </div>

                <div className="rounded-[1.25rem] border border-[#3d2a18]/10 bg-[#fffaf1] p-4">
                  <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Khoản cần thu từ khách</p>
                  <div className="mt-3 space-y-2">
                    <SettlementLine label="Cọc mới còn thiếu" value={movement.deposit_due_amount} tone="danger" />
                    <SettlementLine label="Phí/khấu trừ thu thêm" value={movement.extra_charge_amount} tone="danger" />
                    <SettlementLine label="Khấu trừ admin nhập" value={settlementBreakdown.deductionInputAmount} tone="muted" />
                    <SettlementLine label="Phí chuyển phòng admin nhập" value={movement.transfer_fee} tone="muted" />
                  </div>
                </div>
              </div>

              <div className="rounded-[1.5rem] border border-[#f3c56b]/30 bg-[#2b1a0f] p-4 text-[#fff4df] shadow-xl shadow-[#24170d]/15">
                <p className="text-[10px] font-black uppercase tracking-[0.24em] text-[#f3c56b]">QR settlement</p>
                <p className="mt-3 text-4xl font-black tabular-nums tracking-tight text-white">{formatCurrency(movement.settlement_remaining_amount)}</p>
                <p className="mt-1 text-xs font-bold text-[#f8e8c8]/70">Còn phải thanh toán trên tổng {formatCurrency(movement.settlement_due_amount)}</p>

                <div className="mt-4 grid grid-cols-2 gap-2">
                  <MiniMetric label="Đã thanh toán" value={movement.settlement_paid_amount} tone="success" />
                  <MiniMetric label="Trạng thái" value={movement.settlement_payment_status_label || '—'} />
                </div>            <MiniMetric label="Trạng thái" value={movement.settlement_payment_status_label || '—'} />
                </div>

                <div className="mt-4 rounded-2xl border border-white/10 bg-white/8 p-3">
                  <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]/55">Sau khi SePay ghi nhận</p>
                  <p className="mt-2 text-xs font-bold leading-5 text-[#f8e8c8]/78">Tiền thanh toán được ưu tiên ghi vào cọc mới còn thiếu, phần dư của giao dịch mới ghi vào phí/khấu trừ chuyển phòng.</p>
                </div>

                {movement.settlement_qr_url ? (
                  <a href={movement.settlement_qr_url} target="_blank" rel="noreferrer" className="mt-4 inline-flex w-full items-center justify-center rounded-2xl bg-[#f3c56b] px-4 py-3 text-sm font-black text-[#24170d] transition hover:bg-[#ffd783] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/30">
                    Mở mã QR thanh toán
                  </a>
                ) : (
                  <div className="mt-4 rounded-2xl border border-white/10 bg-white/8 px-4 py-3 text-center text-sm font-black text-[#f8e8c8]/65">Không phát sinh QR</div>
                )}

                {canCollectCash && (
                  <button type="button" onClick={() => onOpenCashPayment(movement)} className="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-[#7ddfd3]/25 bg-[#0f766e] px-4 py-3 text-sm font-black text-white transition hover:bg-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#7ddfd3]/20 active:scale-[0.99]">
                    <HandCoins className="h-4 w-4" /> Thu tiền mặt
                  </button>
                )}
              </div>
            </div>
          </section>

          {movement.failure_reason && (
            <section className="rounded-[1.5rem] border border-rose-200 bg-rose-50 p-4">
              <p className="text-[10px] font-black uppercase tracking-[0.2em] text-rose-700/70">Lý do bị chặn / lỗi</p>
              <p className="mt-3 whitespace-pre-wrap text-sm font-bold leading-6 text-rose-700">{movement.failure_reason}</p>
            </section>
          )}

          {hasMeterReadings && (
            <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
              <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Chỉ số chốt</p>
              <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <DetailTile label="Điện" value={movement.final_electric_reading || '—'} />
                <DetailTile label="Nước" value={movement.final_water_reading || '—'} />
              </div>
            </section>
          )}

          {movement.note && (
            <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
              <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Ghi chú</p>
              <p className="mt-3 whitespace-pre-wrap text-sm font-bold leading-6 text-[#3d2a18]">{movement.note}</p>
            </section>
          )}
        </div>
      </aside>
    </div>
  )
}

function CashSettlementPaymentModal({ movement, note, errorMessage, isSubmitting, onNoteChange, onClose, onConfirm }: { movement: AdminRoomMovementResource; note: string; errorMessage: string | null; isSubmitting: boolean; onNoteChange: (value: string) => void; onClose: () => void; onConfirm: () => void }) {
  const remainingAmount = movement.settlement_remaining_amount || '0.00'
  const depositRemaining = settlementDepositRemainingAmount(movement)
  const extraAmount = settlementExtraCashAmount(movement)

  return (
    <div className="fixed inset-0 z-[90] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="cash-settlement-title">
      <button type="button" aria-label="Đóng xác nhận thu tiền mặt" onClick={onClose} className="absolute inset-0 bg-[#120b06]/78 backdrop-blur-sm" />
      <aside className="relative z-10 w-full max-w-2xl overflow-hidden rounded-[2rem] border border-[#7ddfd3]/25 bg-[#fffaf1] shadow-2xl shadow-black/35">
        <div className="relative bg-[#102f2b] p-5 text-white">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_12%_18%,rgba(125,223,211,0.26),transparent_32%),linear-gradient(135deg,#102f2b_0%,#24170d_100%)]" />
          <div className="relative flex items-start justify-between gap-4">
            <div>
              <p className="text-[10px] font-black uppercase tracking-[0.22em] text-[#7ddfd3]">Xác nhận thu tiền mặt</p>
              <h2 id="cash-settlement-title" className="mt-2 text-2xl font-black tracking-tight">{formatCurrency(remainingAmount)}</h2>
              <p className="mt-1 text-sm font-bold text-[#f8e8c8]/75">BE sẽ thu đủ số còn thiếu và tự tách cọc mới với phí/khấu trừ.</p>
            </div>
            <button type="button" onClick={onClose} disabled={isSubmitting} className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-60" aria-label="Đóng xác nhận thu tiền mặt">
              <X className="h-5 w-5" />
            </button>
          </div>
        </div>

        <div className="space-y-4 p-5">
          {errorMessage && <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{errorMessage}</div>}

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <DetailTile label="Mã chuyển phòng" value={movement.transfer_code || '—'} />
            <DetailTile label="Khách thuê" value={movement.tenant?.full_name || movement.tenant?.username || `#${movement.tenant_id}`} />
            <DetailTile label="Phòng đến" value={roomLabel(movement.to_room, 'Phòng đến')} />
            <DetailTile label="Trạng thái hiện tại" value={movement.settlement_payment_status_label || 'Chờ thanh toán'} />
          </div>

          <section className="rounded-[1.5rem] border border-[#0f766e]/15 bg-[#0f766e]/8 p-4">
            <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#0f5f59]/70">Breakdown ghi nhận</p>
            <div className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
              <MoneyTile label="Số thu tiền mặt" value={remainingAmount} tone="success" />
              <MoneyTile label="Ghi vào cọc mới" value={depositRemaining} tone="neutral" />
              <MoneyTile label="Phí/khấu trừ" value={extraAmount} tone="warning" />
            </div>
            <p className="mt-3 text-xs font-bold leading-5 text-[#0f5f59]">Phần “Ghi vào cọc mới” sẽ tạo giao dịch thu cọc tiền mặt cho hợp đồng đích; phần phí/khấu trừ chỉ lưu trong lịch sử settlement chuyển phòng.</p>
          </section>

          <label className="block">
            <span className="text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/70">Ghi chú nội bộ</span>
            <textarea value={note} onChange={(event) => onNoteChange(event.target.value)} maxLength={500} rows={4} className="mt-2 w-full resize-none rounded-2xl border border-[#3d2a18]/10 bg-white/80 px-4 py-3 text-sm font-bold text-[#24170d] outline-none transition placeholder:text-[#8b5e34]/45 focus:border-[#0f766e] focus:ring-4 focus:ring-[#0f766e]/10" placeholder="Ví dụ: Thu tại quầy, người nhận tiền..." />
          </label>

          <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button type="button" onClick={onClose} disabled={isSubmitting} className="inline-flex items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-white px-5 py-3 text-sm font-black text-[#6f6254] transition hover:bg-[#fff4df] disabled:cursor-not-allowed disabled:opacity-60">Hủy</button>
            <button type="button" onClick={onConfirm} disabled={isSubmitting} className="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#0f766e] px-5 py-3 text-sm font-black text-white shadow-lg shadow-[#0f766e]/20 transition hover:bg-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/20 disabled:cursor-not-allowed disabled:opacity-70">
              {isSubmitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <HandCoins className="h-4 w-4" />}
              Xác nhận thu đủ tiền mặt
            </button>
          </div>
        </div>
      </aside>
    </div>
  )
}

function DetailTile({ label, value }: { label: string; value?: ReactNode }) {
  return (
    <div className="rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3 shadow-sm">
      <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/60">{label}</p>
      <div className="mt-1 break-words text-sm font-black text-[#24170d]">{value ?? '—'}</div>
    </div>
  )
}

function MoneyTile({ label, value, tone, compact = false }: { label: string; value?: string | null; tone: MoneyTone; compact?: boolean }) {
  const toneClassName = {
    neutral: 'text-[#24170d]',
    success: 'text-[#0f5f59]',
    warning: 'text-[#8a4f18]',
    danger: 'text-rose-700',
    muted: 'text-[#6f6254]',
  }[tone]

  return (
    <div className="rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3 shadow-sm">
      <p className="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/60"><Banknote className="h-3.5 w-3.5" /> {label}</p>
      <p className={cn('mt-1 font-black tabular-nums', compact ? 'text-base' : 'text-lg', toneClassName)}>{formatCurrency(value)}</p>
    </div>
  )
}

type MoneyTone = 'neutral' | 'success' | 'warning' | 'danger' | 'muted'

function SettlementLine({ label, value, tone, helper }: { label: string; value?: string | null; tone: MoneyTone; helper?: string }) {
  const toneClassName = {
    neutral: 'text-[#24170d]',
    success: 'text-[#0f5f59]',
    warning: 'text-[#8a4f18]',
    danger: 'text-rose-700',
    muted: 'text-[#6f6254]',
  }[tone]

  return (
    <div className="rounded-2xl border border-[#3d2a18]/10 bg-white/65 px-3 py-2.5">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-xs font-black text-[#24170d]">{label}</p>
          {helper && <p className="mt-0.5 text-[11px] font-bold leading-4 text-[#6f6254]">{helper}</p>}
        </div>
        <p className={cn('shrink-0 text-sm font-black tabular-nums', toneClassName)}>{formatCurrency(value)}</p>
      </div>
    </div>
  )
}

function MiniMetric({ label, value, tone = 'neutral' }: { label: string; value?: string | null; tone?: MoneyTone }) {
  const toneClassName = {
    neutral: 'text-white',
    success: 'text-[#7ddfd3]',
    warning: 'text-[#f3c56b]',
    danger: 'text-rose-200',
    muted: 'text-[#f8e8c8]/70',
  }[tone]

  return (
    <div className="rounded-2xl border border-white/10 bg-white/8 p-3">
      <p className="text-[10px] font-black uppercase tracking-[0.16em] text-[#f8e8c8]/50">{label}</p>
      <p className={cn('mt-1 text-sm font-black tabular-nums', toneClassName)}>{isMoneyLike(value) ? formatCurrency(value) : value || '—'}</p>
    </div>
  )
}

function makeSettlementBreakdown(movement: AdminRoomMovementResource) {
  const oldDepositAmount = moneyNumber(movement.old_room_final_amount)
  const transferredDepositAmount = moneyNumber(movement.deposit_transfer_amount)
  const manualRefundAmount = moneyNumber(movement.manual_refund_amount)
  const legacyRefundAmount = moneyNumber(movement.deposit_refund_amount)
  const extraChargeAmount = moneyNumber(movement.extra_charge_amount)
  const transferFeeAmount = moneyNumber(movement.transfer_fee)
  const deductionInputAmount = stringMoneyFromPayload(movement.scheduled_payload?.deposit_deduction_amount, movement.deduction_amount)
  const scheduledDeductionAmount = moneyNumber(deductionInputAmount)
  const expectedExtraChargeAmount = scheduledDeductionAmount + transferFeeAmount
  const usesOldDeposit = transferredDepositAmount > 0 || manualRefundAmount > 0 || (oldDepositAmount > 0 && extraChargeAmount < expectedExtraChargeAmount)

  return {
    deductionInputAmount,
    refundAmount: manualRefundAmount > 0 ? movement.manual_refund_amount : movement.deposit_refund_amount,
    usesOldDeposit,
    oldDepositTitle: usesOldDeposit ? 'Cọc cũ được quyết toán' : 'Cọc cũ giữ tại hợp đồng nguồn',
    oldDepositDescription: usesOldDeposit
      ? 'Backend dùng số dư cọc cũ để trừ phí/khấu trừ trước, sau đó mới chuyển sang hợp đồng đích hoặc hoàn phần dư.'
      : 'Đây là luồng chuyển một phần hoặc sang hợp đồng đích đã có cọc: cọc cũ không đem bù cọc mới, khoản phát sinh được thu riêng trong settlement.',
    legacyRefundAmount,
  }
}

function moneyNumber(value?: string | number | null): number {
  const normalizedValue = Number(String(value ?? '0').replace(/,/g, ''))

  return Number.isFinite(normalizedValue) ? normalizedValue : 0
}

function canRecordCashSettlementPayment(movement: AdminRoomMovementResource, admin?: AdminProfile | null): boolean {
  return Number(movement.movement_type) === MOVEMENT_TRANSFER
    && Number(movement.status) === MOVEMENT_STATUS_EXECUTED
    && Number(movement.settlement_payment_status) !== SETTLEMENT_STATUS_PAID
    && moneyNumber(movement.settlement_remaining_amount) > 0
    && canAdminCollectDestinationBuildingCash(movement, admin)
}

function canAdminCollectDestinationBuildingCash(movement: AdminRoomMovementResource, admin?: AdminProfile | null): boolean {
  if (!admin) return false
  if (isSuperAdminRole(admin.role)) return true

  const destinationBuildingId = movement.to_room?.building_id
  if (!destinationBuildingId) return false

  return (admin.managed_buildings || []).some((building) => Number(building.id) === Number(destinationBuildingId))
}

function settlementDepositRemainingAmount(movement: AdminRoomMovementResource): string {
  const references = Array.isArray(movement.settlement_payment_references) ? movement.settlement_payment_references : []
  const paidDeposit = references.reduce((total, reference) => total + moneyNumber(reference.deposit_amount), 0)
  const remainingDeposit = Math.max(0, moneyNumber(movement.deposit_due_amount) - paidDeposit)
  const amount = Math.min(moneyNumber(movement.settlement_remaining_amount), remainingDeposit)

  return amount.toFixed(2)
}

function settlementExtraCashAmount(movement: AdminRoomMovementResource): string {
  const amount = Math.max(0, moneyNumber(movement.settlement_remaining_amount) - moneyNumber(settlementDepositRemainingAmount(movement)))

  return amount.toFixed(2)
}

function stringMoneyFromPayload(value: unknown, fallback?: string | null): string | null {
  if (value === null || value === undefined || value === '') {
    return fallback ?? null
  }

  return String(value)
}

function isMoneyLike(value?: string | null): boolean {
  if (value === null || value === undefined || value === '') {
    return false
  }

  return Number.isFinite(Number(String(value).replace(/,/g, '')))
}

function roomLabel(room: AdminRoomMovementResource['from_room'], fallback: string) {
  if (!room) return fallback
  return `Phòng ${room.room_number || room.id}${room.building_name ? ` · ${room.building_name}` : ''}`
}

function getVisibleErrorMessage(error: unknown, fallback: string) {
  if (error instanceof ApiError) return error.message || fallback
  if (error instanceof Error) return error.message
  return fallback
}
