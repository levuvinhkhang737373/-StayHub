import { useCallback, useEffect, useMemo, useState } from 'react'
import { ArrowRight, BadgeDollarSign, Building2, CalendarClock, CheckCircle2, ChevronLeft, ChevronRight, FileText, Loader2, Search, ShieldCheck, TrendingDown, TrendingUp, X } from 'lucide-react'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatCurrency, formatMoneyInput, parseMoneyInput } from '../../../../shared/lib/utils/format'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import { fetchAdminRooms } from '../../rooms/services/rooms.service'
import type { AdminRoomResource } from '../../rooms/types/rooms.model'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { getVisibleErrorMessage } from '../../shared/utils/error-message'
import { fetchRoomServicePrices, updateRoomServicePrices } from '../services/room-service-prices.service'
import type { RoomServicePriceRoomResource, RoomServicePriceServiceResource } from '../types/room-service-price.model'

const inputClass = 'h-11 w-full rounded-2xl border border-[#3d2a18]/10 bg-white/85 px-4 text-sm font-bold text-[#24170d] outline-none transition placeholder:text-[#8b7358]/55 focus:border-[#d8912b] focus:ring-4 focus:ring-[#f3c56b]/20 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400'
const inputErrorClass = 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100'

const perPageOptions = [
  { value: 5, label: '5 dòng', tone: 'default' as const },
  { value: 10, label: '10 dòng', tone: 'default' as const },
  { value: 20, label: '20 dòng', tone: 'default' as const },
  { value: 50, label: '50 dòng', tone: 'default' as const },
]

export function getNextBillingPeriod(date = new Date()) {
  const nextMonth = new Date(date.getFullYear(), date.getMonth() + 1, 1)
  return {
    billing_month: nextMonth.getMonth() + 1,
    billing_year: nextMonth.getFullYear(),
  }
}

function normalizeList<T>(result: unknown): T[] {
  if (Array.isArray(result)) return result as T[]
  if (result && typeof result === 'object' && Array.isArray((result as { data?: unknown }).data)) {
    return (result as { data: T[] }).data
  }
  return []
}

function moneyToNumber(value: string) {
  const parsed = Number(parseMoneyInput(value))
  return Number.isFinite(parsed) ? parsed : NaN
}

function monthLabel(month: number, year: number) {
  return `Tháng ${String(month).padStart(2, '0')}/${year}`
}

function buildFuturePeriodOptions() {
  const start = getNextBillingPeriod()
  return Array.from({ length: 24 }, (_, index) => {
    const date = new Date(start.billing_year, start.billing_month - 1 + index, 1)
    const month = date.getMonth() + 1
    const year = date.getFullYear()
    return {
      value: `${year}-${String(month).padStart(2, '0')}`,
      label: monthLabel(month, year),
      month,
      year,
    }
  })
}

function isFutureBillingPeriod(month: number, year: number) {
  const next = getNextBillingPeriod()
  return year > next.billing_year || (year === next.billing_year && month >= next.billing_month)
}

function moneyValue(value: string | number | null | undefined) {
  if (typeof value === 'string') {
    const trimmed = value.trim()
    if (/^\d+\.\d{1,2}$/.test(trimmed)) {
      return Number(trimmed)
    }

    const normalized = trimmed.includes(',')
      ? trimmed.replace(/\./g, '').replace(/,/g, '.')
      : trimmed.replace(/\./g, '')
    const parsed = Number(normalized || 0)

    return Number.isFinite(parsed) ? parsed : 0
  }

  const amount = Number(value ?? 0)

  return Number.isFinite(amount) ? amount : 0
}

function serviceCurrentPrice(service: RoomServicePriceServiceResource) {
  return moneyValue(service.current_price ?? service.old_price ?? service.base_price ?? service.effective_price)
}

function serviceTargetPrice(service: RoomServicePriceServiceResource) {
  return moneyValue(service.new_price ?? service.scheduled_price ?? service.current_price ?? service.base_price ?? service.effective_price)
}

function serviceBaseDisplayPrice(service: RoomServicePriceServiceResource) {
  return moneyValue(service.current_price ?? service.old_price ?? service.base_price ?? service.effective_price)
}

function serviceDisplayPrice(service: RoomServicePriceServiceResource) {
  return moneyValue(service.display_price ?? service.contract_price ?? service.current_price ?? service.old_price ?? service.base_price ?? service.effective_price)
}

function serviceScheduledPrice(service: RoomServicePriceServiceResource) {
  return moneyValue(service.scheduled_price ?? service.new_price)
}

function hasContractScopedPrice(service: RoomServicePriceServiceResource) {
  return service.contract_price !== null && service.contract_price !== undefined
}

function serviceContractLabel(service: RoomServicePriceServiceResource) {
  if (!hasContractScopedPrice(service)) return null

  return formatContractCode(service.active_contract_code, service.active_contract_id)
}

function roomContractLabel(room: RoomServicePriceRoomResource) {
  return formatContractCode(room.active_contract_code, room.active_contract_id)
}

function serviceContractStatusLabel(service: RoomServicePriceServiceResource) {
  if (!hasContractScopedPrice(service)) return null

  return service.contract_is_ended ? 'Đã kết thúc' : service.contract_status_label || 'Đang hiệu lực'
}

function formatContractCode(code?: string | null, id?: number | null) {
  if (code) return code
  return id ? `HD-${String(id).padStart(5, '0')}` : null
}

function serviceStatusText(service: RoomServicePriceServiceResource, isContractEnded = false) {
  if (!service.is_active) {
    return service.schedule_block_reason || 'Ngừng hoạt động'
  }

  if (isContractEnded || service.contract_is_ended) {
    return 'Hết hiệu lực'
  }

  if (service.scheduled_price || service.new_price) {
    return `Đã lên lịch ${formatCurrency(serviceScheduledPrice(service))}`
  }

  if (hasContractScopedPrice(service)) {
    return 'Giá hợp đồng đang áp dụng'
  }

  return service.status_label || 'Giá phòng đang áp dụng'
}

function isServiceSchedulable(service: RoomServicePriceServiceResource) {
  return service.is_active !== false && service.can_schedule_price !== false
}

function roomCanSchedulePrice(room: RoomServicePriceRoomResource) {
  return room.services.some(isServiceSchedulable)
}

function priceDeltaTone(oldPrice: number, newPrice: number) {
  if (newPrice > oldPrice) return 'increase'
  if (newPrice < oldPrice) return 'decrease'
  return 'same'
}

export function RoomServicePricesScreen() {
  const { session, isChecking } = useAdminSession()
  const isSuperAdmin = useMemo(() => {
    if (isChecking || !session) return false
    return isSuperAdminRole(session?.admin?.role)
  }, [session?.admin?.role, isChecking])
  const nextPeriod = useMemo(() => getNextBillingPeriod(), [])
  const periodOptions = useMemo(() => buildFuturePeriodOptions(), [])

  const [billingMonth, setBillingMonth] = useState(nextPeriod.billing_month)
  const [billingYear, setBillingYear] = useState(nextPeriod.billing_year)
  const [buildingId, setBuildingId] = useState<string>('')
  const [roomId, setRoomId] = useState<string>('')
  const [keyword, setKeyword] = useState('')
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(10)

  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [rooms, setRooms] = useState<AdminRoomResource[]>([])
  const [priceRooms, setPriceRooms] = useState<RoomServicePriceRoomResource[]>([])
  const [total, setTotal] = useState(0)
  const [lastPage, setLastPage] = useState(1)

  const [isBuildingsLoaded, setIsBuildingsLoaded] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [isSaving, setIsSaving] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)

  const [editingRoom, setEditingRoom] = useState<RoomServicePriceRoomResource | null>(null)
  const [priceInputs, setPriceInputs] = useState<Record<number, string>>({})
  const [modalError, setModalError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<number, string>>({})

  const periodValue = `${billingYear}-${String(billingMonth).padStart(2, '0')}`

  useEffect(() => {
    if (isChecking) return

    async function loadBuildings() {
      try {
        const response = await fetchAdminBuildings({ per_page: 100 })
        const list = normalizeList<AdminBuildingResource>(response.result)
        setBuildings(list)
        if (!isSuperAdmin && list[0]?.id) {
          setBuildingId(String(list[0].id))
        }
      } catch (error) {
        setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải danh sách tòa nhà.'))
      } finally {
        setIsBuildingsLoaded(true)
      }
    }

    void loadBuildings()
  }, [isChecking, isSuperAdmin])

  useEffect(() => {
    if (!isBuildingsLoaded) return
    async function loadRooms() {
      try {
        const response = await fetchAdminRooms({ building_id: buildingId ? Number(buildingId) : undefined, per_page: 1000 })
        setRooms(normalizeList<AdminRoomResource>(response.result))
      } catch (error) {
        setRooms([])
        setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải danh sách phòng.'))
      }
    }

    void loadRooms()
  }, [isBuildingsLoaded, buildingId])

  const loadPrices = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)
    try {
      const response = await fetchRoomServicePrices({
        billing_month: billingMonth,
        billing_year: billingYear,
        building_id: buildingId || undefined,
        room_id: roomId || undefined,
        keyword: keyword.trim() || undefined,
        page,
        per_page: perPage,
      })
      setPriceRooms(response.result?.data || [])
      setTotal(Number(response.result?.total || 0))
      setLastPage(Number(response.result?.last_page || 1))
    } catch (error) {
      setPriceRooms([])
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải giá dịch vụ phòng.'))
    } finally {
      setIsLoading(false)
    }
  }, [billingMonth, billingYear, buildingId, roomId, keyword, page, perPage])

  useEffect(() => {
    if (!isBuildingsLoaded) return
    void loadPrices()
  }, [isBuildingsLoaded, loadPrices])

  const buildingOptions = useMemo(() => {
    const options = buildings.map((building) => ({ value: String(building.id), label: building.name, tone: 'default' as const }))
    return isSuperAdmin ? [{ value: '', label: 'Tất cả tòa nhà', tone: 'default' as const }, ...options] : options
  }, [buildings, isSuperAdmin])

  const roomOptions = useMemo(() => [
    { value: '', label: 'Tất cả phòng', tone: 'default' as const },
    ...rooms.map((room) => ({ value: String(room.id), label: `Phòng ${room.room_number}`, tone: 'default' as const })),
  ], [rooms])

  const statusStats = useMemo(() => {
    const serviceCount = priceRooms.reduce((sum, room) => sum + room.services.length, 0)
    const scheduledCount = priceRooms.reduce((sum, room) => sum + room.services.filter((service) => service.scheduled_price !== null).length, 0)
    return { serviceCount, scheduledCount }
  }, [priceRooms])

  const totalPages = Math.max(1, lastPage)
  const safeCurrentPage = Math.min(page, totalPages)
  const paginationStart = priceRooms.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + 1
  const paginationEnd = priceRooms.length === 0 ? 0 : Math.min(total, (safeCurrentPage - 1) * perPage + priceRooms.length)
  const visiblePages = useMemo(() => {
    const pages = new Set<number>([1, totalPages, safeCurrentPage - 1, safeCurrentPage, safeCurrentPage + 1])
    return Array.from(pages)
      .filter((page) => page >= 1 && page <= totalPages)
      .sort((a, b) => a - b)
  }, [safeCurrentPage, totalPages])

  const changePage = (nextPage: number) => {
    setPage(Math.min(Math.max(1, nextPage), totalPages))
  }

  const changePerPage = (nextValue: string | number) => {
    setPerPage(Number(nextValue))
    setPage(1)
  }

  const openEditModal = (room: RoomServicePriceRoomResource) => {
    setEditingRoom(room)
    setModalError(null)
    setFieldErrors({})
    setPriceInputs(Object.fromEntries(room.services.filter(isServiceSchedulable).map((service) => [service.id, ''])))
  }

  const closeEditModal = () => {
    if (isSaving) return
    setEditingRoom(null)
    setModalError(null)
    setFieldErrors({})
  }

  const handlePeriodChange = (value: string | number) => {
    const [year, month] = String(value).split('-').map(Number)
    setBillingYear(year)
    setBillingMonth(month)
    setPage(1)
  }

  const handleSave = async () => {
    if (!editingRoom) return

    const nextFieldErrors: Record<number, string> = {}
    const servicesWithInput = editingRoom.services.filter((service) => isServiceSchedulable(service) && (priceInputs[service.id] ?? '').trim() !== '')
    const prices = servicesWithInput.map((service) => {
      const inputValue = priceInputs[service.id] ?? ''
      const price = moneyToNumber(inputValue)
      if (Number.isNaN(price) || price < 0) {
        nextFieldErrors[service.id] = 'Giá dịch vụ phải là số tiền hợp lệ và không âm.'
      }
      return {
        room_service_id: service.id,
        contract_id: service.active_contract_id ?? editingRoom.active_contract_id ?? null,
        price,
      }
    })

    if (!isFutureBillingPeriod(billingMonth, billingYear)) {
      setModalError('Chỉ được lên lịch giá dịch vụ phòng cho tháng sau hoặc tương lai.')
      return
    }

    setFieldErrors(nextFieldErrors)
    if (Object.keys(nextFieldErrors).length > 0) return
    if (prices.length === 0) {
      setModalError('Vui lòng nhập ít nhất một giá dịch vụ cần lên lịch.')
      return
    }

    setIsSaving(true)
    setModalError(null)
    try {
      await updateRoomServicePrices(editingRoom.id, {
        billing_month: billingMonth,
        billing_year: billingYear,
        prices,
      })
      setSuccessMessage(`Đã lên lịch giá dịch vụ phòng ${editingRoom.room_number} từ ${monthLabel(billingMonth, billingYear)}.`)
      setEditingRoom(null)
      await loadPrices()
    } catch (error) {
      setModalError(getVisibleErrorMessage(error, 'Không thể lên lịch giá dịch vụ phòng.'))
      if (error instanceof ApiError && error.validationErrors) {
        const firstMessage = Object.values(error.validationErrors).flat()[0]
        if (firstMessage) setModalError(firstMessage)
      }
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="min-h-screen bg-[#f6efe3] px-3 py-4 text-[#24170d] sm:px-6 sm:py-6 lg:px-8">
      <section className="relative overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] p-5 text-[#fff4df] shadow-xl shadow-[#24170d]/15 sm:p-7">
        <div className="absolute -right-16 -top-16 h-52 w-52 rounded-full bg-[#f3c56b]/20 blur-3xl" />
        <div className="absolute bottom-0 left-1/3 h-24 w-72 rounded-full bg-white/10 blur-2xl" />
        <div className="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
          <div className="max-w-3xl">
            <span className="text-xs font-black uppercase tracking-[0.2em] text-[#f3c56b]">Dịch vụ & Điện nước</span>
            <p className="mt-2 text-xs font-bold leading-5 text-[#f8e8c8]/70">Bảng này không bao gồm điện/nước và tự khóa dịch vụ phòng đã ngừng hoạt động.</p>
            <h1 className="mt-3 flex flex-col gap-3 text-3xl font-black tracking-[-0.04em] sm:flex-row sm:items-center sm:text-4xl">
              <BadgeDollarSign className="h-9 w-9 shrink-0 text-[#f3c56b]" /> Giá dịch vụ phòng
            </h1>
          </div>
          <div className="grid gap-2 sm:grid-cols-3 lg:min-w-[480px]">
            <Metric label="Phòng" value={total.toLocaleString('vi-VN')} />
            <Metric label="Service" value={statusStats.serviceCount.toLocaleString('vi-VN')} />
            <Metric label="Đã lên lịch" value={statusStats.scheduledCount.toLocaleString('vi-VN')} />
          </div>
        </div>
      </section>

      <section className="mt-5 rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/95 p-4 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur sm:p-5">
        <div className="grid gap-3 xl:grid-cols-[1.1fr_0.9fr_0.9fr_0.9fr_auto]">
          <div className="relative">
            <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
            <input
              type="search"
              autoComplete="off"
              value={keyword}
              onChange={(event) => { setKeyword(event.target.value); setPage(1) }}
              className={`${inputClass} pl-11`}
              placeholder="Tìm phòng..."
            />
          </div>
          <AdminSelect value={buildingId} options={buildingOptions} onChange={(value) => { setBuildingId(String(value)); setRoomId(''); setPage(1) }} />
          <AdminSelect value={roomId} options={roomOptions} onChange={(value) => { setRoomId(String(value)); setPage(1) }} />
          <AdminSelect value={periodValue} options={periodOptions.map((option) => ({ value: option.value, label: option.label, tone: 'default' as const }))} onChange={handlePeriodChange} />
          <button
            type="button"
            onClick={() => void loadPrices()}
            className="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl bg-[#24170d] px-5 py-2 text-xs font-black uppercase tracking-wider text-[#fff4df] shadow-md shadow-[#24170d]/12 transition hover:bg-[#3d2a18]"
          >
            <CalendarClock className="h-4 w-4 text-[#f3c56b]" /> Tải lại
          </button>
        </div>
      </section>

      {errorMessage && <Alert tone="error" message={errorMessage} />}
      {successMessage && <Alert tone="success" message={successMessage} onClose={() => setSuccessMessage(null)} />}

      <section className="mt-5 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-xl shadow-[#6b3f1d]/8">
        <div className="hidden overflow-x-auto xl:block">
          <table className="w-full min-w-[1080px] text-left">
            <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
              <tr>
                <th className="px-5 py-4 text-center">Phòng</th>
                <th className="px-5 py-4 text-center">Hợp đồng</th>
                <th className="px-5 py-4 text-center">Dịch vụ</th>
                <th className="px-5 py-4 text-center">Trạng thái</th>
                <th className="px-5 py-4 text-center">Thao tác</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#3d2a18]/8">
              {isLoading && Array.from({ length: 5 }).map((_, index) => <SkeletonRow key={index} />)}
              {!isLoading && priceRooms.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-5 py-14 text-center text-sm font-bold text-[#6f6254]">
                    Không có phòng nào có service phù hợp bộ lọc.
                  </td>
                </tr>
              )}
              {!isLoading && priceRooms.map((room) => <RoomRow key={room.id} room={room} onEdit={() => openEditModal(room)} />)}
            </tbody>
          </table>
        </div>
        <div className="space-y-3 p-3 xl:hidden sm:p-4">
          {isLoading && Array.from({ length: 4 }).map((_, index) => <RoomServicePriceCardSkeleton key={index} />)}
          {!isLoading && priceRooms.length === 0 && (
            <div className="rounded-[1.5rem] border border-dashed border-[#3d2a18]/15 bg-white/60 px-4 py-12 text-center text-sm font-bold text-[#6f6254]">
              Không có phòng nào có service phù hợp bộ lọc.
            </div>
          )}
          {!isLoading && priceRooms.map((room) => <RoomServicePriceCard key={room.id} room={room} onEdit={() => openEditModal(room)} />)}
        </div>
        <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb]/85 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
          <p className="text-xs font-black text-[#6f6254]">
            Hiển thị <span className="tabular-nums text-[#24170d]">{paginationStart}</span>-<span className="tabular-nums text-[#24170d]">{paginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{total.toLocaleString('vi-VN')}</span> phòng
          </p>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div className="w-full sm:w-36">
              <AdminSelect value={perPage} options={perPageOptions} onChange={changePerPage} menuPlacement="top" />
            </div>
            <div className="flex items-center justify-center gap-1.5 sm:justify-end">
              <button type="button" disabled={safeCurrentPage <= 1} onClick={() => changePage(safeCurrentPage - 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang trước">
                <ChevronLeft className="h-4 w-4" />
              </button>
              {visiblePages.map((visiblePage, index) => {
                const previousPage = visiblePages[index - 1]
                const hasGap = previousPage && visiblePage - previousPage > 1

                return (
                  <div key={visiblePage} className="flex items-center gap-1.5">
                    {hasGap && <span className="px-1 text-xs font-black text-[#8b5e34]/60">...</span>}
                    <button type="button" onClick={() => changePage(visiblePage)} className={cn('inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition', visiblePage === safeCurrentPage ? 'border-[#24170d] bg-[#24170d] text-[#fff4df] shadow-sm' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15')} aria-current={visiblePage === safeCurrentPage ? 'page' : undefined}>
                      {visiblePage}
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

      {editingRoom && (
        <div className="fixed inset-0 z-[70] flex items-end justify-center p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="room-service-price-dialog-title">
          <button type="button" className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" onClick={closeEditModal} />
          <div className="relative z-10 flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-t-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-stone-950/30 sm:max-h-[88vh] sm:rounded-[2rem]">
            <div className="shrink-0 bg-[#24170d] p-4 text-[#fff4df] sm:p-5">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <h2 id="room-service-price-dialog-title" className="mt-1.5 text-xl font-black tracking-tight sm:text-2xl">Lên lịch giá phòng {editingRoom.room_number}</h2>
                  <p className="mt-1 text-xs font-bold leading-5 text-[#f8e8c8]/70">Áp dụng từ {monthLabel(billingMonth, billingYear)} · giá theo hợp đồng sẽ lưu riêng theo mã hợp đồng</p>
                </div>
                <button type="button" onClick={closeEditModal} className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20" aria-label="Đóng popup sửa giá">
                  <X className="h-5 w-5" />
                </button>
              </div>
            </div>

            <div className="flex-1 space-y-4 overflow-y-auto p-4 sm:p-5">
              {modalError && (
                <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold leading-relaxed text-rose-700">
                  {modalError}
                </div>
              )}
              {editingRoom.services.map((service) => {
                const canScheduleService = isServiceSchedulable(service)

                return (
                <div key={service.id} className="rounded-2xl border border-[#3d2a18]/10 bg-white/80 p-4">
                  <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                      <p className="text-sm font-black text-[#24170d]">{service.service_name}</p>
                    </div>
                    <span className={cn('w-fit rounded-full px-3 py-1 text-[11px] font-black', !service.is_active ? 'bg-rose-50 text-rose-700' : service.scheduled_price ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-600')}>
                      {!service.is_active ? 'Ngừng hoạt động' : service.scheduled_price ? 'Đã lên lịch' : 'Chưa có giá mới'}
                    </span>
                  </div>
                  <PriceTransition service={service} />
                  <div className="mt-4">
                    <label className="mb-1.5 block text-[11px] font-black uppercase tracking-[0.18em] text-[#8b5e34]">Giá mới</label>
                    <input
                      value={priceInputs[service.id] ?? ''}
                      onChange={(event) => {
                        setPriceInputs((current) => ({ ...current, [service.id]: formatMoneyInput(event.target.value) }))
                        setFieldErrors((current) => ({ ...current, [service.id]: '' }))
                      }}
                      className={cn(inputClass, fieldErrors[service.id] && inputErrorClass)}
                      placeholder="Nhập giá mới"
                      disabled={!canScheduleService}
                    />
                  </div>
                  {!canScheduleService && <p className="mt-2 text-xs font-bold text-rose-600">{service.schedule_block_reason || 'Dịch vụ phòng đã ngừng hoạt động.'}</p>}
                  {fieldErrors[service.id] && <p className="mt-2 text-xs font-bold text-rose-600">{fieldErrors[service.id]}</p>}
                </div>
                )
              })}
            </div>

            <div className="shrink-0 border-t border-[#3d2a18]/10 bg-[#fff7e8]/80 p-4">
              <div className="grid gap-2 sm:flex sm:items-center sm:justify-end">
                <button type="button" onClick={closeEditModal} disabled={isSaving} className="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] px-5 text-xs font-black uppercase tracking-wider text-[#6f6254] disabled:opacity-50">Hủy</button>
                <button type="button" onClick={() => void handleSave()} disabled={isSaving} className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#24170d] px-5 text-xs font-black uppercase tracking-wider text-[#fff4df] shadow-md shadow-[#24170d]/10 disabled:opacity-50">
                  {isSaving ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4 text-[#f3c56b]" />}
                  {isSaving ? 'Đang lưu...' : 'Lưu giá'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/10 px-4 py-3">
      <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#f3c56b]/90">{label}</p>
      <p className="mt-1 text-2xl font-black text-white">{value}</p>
    </div>
  )
}

function Alert({ tone, message, onClose }: { tone: 'success' | 'error'; message: string; onClose?: () => void }) {
  return (
    <div className={cn('mt-5 flex items-center justify-between rounded-2xl border px-4 py-3 text-sm font-bold', tone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700')}>
      <span>{message}</span>
      {onClose && <button type="button" onClick={onClose}><X className="h-4 w-4" /></button>}
    </div>
  )
}

function RoomIdentity({ room, compact = false }: { room: RoomServicePriceRoomResource; compact?: boolean }) {
  return (
    <div className="flex items-start gap-3">
      <div className={cn('flex shrink-0 items-center justify-center rounded-2xl bg-[#24170d] text-[#f3c56b]', compact ? 'h-10 w-10' : 'h-11 w-11')}>
        <Building2 className="h-5 w-5" />
      </div>
      <div className="min-w-0">
        <p className={cn('truncate font-black text-[#24170d]', compact ? 'text-base' : 'text-sm')}>Phòng {room.room_number}</p>
        <p className="mt-0.5 text-xs font-bold text-[#6f6254]">{room.building_name || `Tòa ${room.building_id}`} · Tầng {room.floor ?? '—'}</p>
      </div>
    </div>
  )
}

function ServicesPriceStack({ room, compact = false }: { room: RoomServicePriceRoomResource; compact?: boolean }) {
  return (
    <div className={cn('grid gap-2', compact ? 'sm:grid-cols-2' : 'xl:grid-cols-2')}>
      {room.services.map((service) => <CurrentPriceLine key={service.id} service={service} compact={compact} />)}
    </div>
  )
}

function ServicesStatusStack({ room, compact = false }: { room: RoomServicePriceRoomResource; compact?: boolean }) {
  return (
    <div className={cn('grid gap-2', compact ? 'sm:grid-cols-2' : '')}>
      {room.services.map((service) => <ServiceStatusPill key={service.id} service={service} isContractEnded={room.contract_is_ended} />)}
    </div>
  )
}

function RoomRow({ room, onEdit }: { room: RoomServicePriceRoomResource; onEdit: () => void }) {
  const contract = getRoomContractSummary(room)
  const canEdit = roomCanSchedulePrice(room)

  return (
    <tr className="bg-[#fffaf1] align-top transition hover:bg-[#fff4df]">
      <td className="px-5 py-4">
        <div className="flex justify-center">
          <div className="w-full max-w-[280px]">
            <RoomIdentity room={room} />
          </div>
        </div>
      </td>
      <td className="px-5 py-4">
        <div className="flex justify-center">
          <div className="w-full max-w-[180px]">
            <ContractColumn contract={contract} />
          </div>
        </div>
      </td>
      <td className="px-5 py-4 text-sm font-black text-[#24170d]">
        <div className="flex justify-center">
          <div className="w-full max-w-[480px]">
            <ServicesPriceStack room={room} />
          </div>
        </div>
      </td>
      <td className="px-5 py-4">
        <div className="flex justify-center">
          <div className="w-full max-w-[280px]">
            <ServicesStatusStack room={room} />
          </div>
        </div>
      </td>
      <td className="px-5 py-4">
        <div className="flex justify-center">
          <button type="button" onClick={onEdit} disabled={!canEdit} title={canEdit ? 'Sửa giá' : 'Không còn dịch vụ phòng được lên lịch'} className="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#24170d] px-4 py-2.5 text-xs font-black uppercase tracking-wider text-[#fff4df] shadow-md shadow-[#24170d]/10 transition hover:bg-[#3d2a18] disabled:cursor-not-allowed disabled:bg-stone-200 disabled:text-stone-500 disabled:shadow-none">
            <ShieldCheck className={cn('h-4 w-4', canEdit ? 'text-[#f3c56b]' : 'text-stone-400')} /> {canEdit ? 'Sửa giá' : 'Đã ngừng'}
          </button>
        </div>
      </td>
    </tr>
  )
}

function RoomServicePriceCard({ room, onEdit }: { room: RoomServicePriceRoomResource; onEdit: () => void }) {
  const contract = getRoomContractSummary(room)
  const canEdit = roomCanSchedulePrice(room)

  return (
    <article className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/75 p-3 shadow-sm shadow-[#6b3f1d]/5 sm:p-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <RoomIdentity room={room} compact />
        <button type="button" onClick={onEdit} disabled={!canEdit} title={canEdit ? 'Sửa giá' : 'Không còn dịch vụ phòng được lên lịch'} className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-2xl bg-[#24170d] px-4 py-2.5 text-xs font-black uppercase tracking-wider text-[#fff4df] shadow-md shadow-[#24170d]/10 transition hover:bg-[#3d2a18] disabled:cursor-not-allowed disabled:bg-stone-200 disabled:text-stone-500 disabled:shadow-none sm:w-auto">
          <ShieldCheck className={cn('h-4 w-4', canEdit ? 'text-[#f3c56b]' : 'text-stone-400')} /> {canEdit ? 'Sửa giá' : 'Đã ngừng'}
        </button>
      </div>
      <div className="mt-3 grid gap-3 lg:grid-cols-[0.95fr_1.55fr]">
        <div>
          <p className="mb-2 text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]">Hợp đồng</p>
          <ContractColumn contract={contract} />
        </div>
        <div>
          <p className="mb-2 text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]">Dịch vụ</p>
          <ServicesPriceStack room={room} compact />
        </div>
      </div>
      <div className="mt-3">
        <p className="mb-2 text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]">Trạng thái</p>
        <ServicesStatusStack room={room} compact />
      </div>
    </article>
  )
}

function getRoomContractSummary(room: RoomServicePriceRoomResource) {
  const roomContractCode = roomContractLabel(room)
  if (roomContractCode) {
    return {
      code: roomContractCode,
      status: room.contract_is_ended ? 'Đã kết thúc' : room.contract_status_label || 'Đang hiệu lực',
      ended: room.contract_is_ended,
    }
  }

  const contractService = room.services.find((service) => serviceContractLabel(service))
  if (!contractService) return null

  return {
    code: serviceContractLabel(contractService) || '—',
    status: serviceContractStatusLabel(contractService) || 'Đang hiệu lực',
    ended: contractService.contract_is_ended,
  }
}

function ContractColumn({ contract }: { contract: ReturnType<typeof getRoomContractSummary> }) {
  if (!contract) {
    return (
      <div className="rounded-xl border border-[#3d2a18]/10 bg-white/70 px-3 py-2 text-xs font-black text-[#6f6254] sm:min-w-[150px]">
        <p className="text-[10px] uppercase tracking-widest opacity-70">Hợp đồng</p>
        <p className="mt-0.5 text-[#6f6254]">Chưa có hợp đồng</p>
      </div>
    )
  }

  return (
    <div className={cn('rounded-xl border px-3 py-2 text-xs font-black sm:min-w-[150px]', contract.ended ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700')}>
      <p className="flex items-center gap-1.5 text-[10px] uppercase tracking-widest opacity-70"><FileText className="h-3 w-3" /> Hợp đồng</p>
      <p className="mt-0.5 break-words text-[#24170d]">{contract.code}</p>
      <p className="mt-1 text-[10px]">{contract.status}</p>
    </div>
  )
}

function CurrentPriceLine({ service, compact = false }: { service: RoomServicePriceServiceResource; compact?: boolean }) {
  const price = serviceDisplayPrice(service)
  const isContractPrice = hasContractScopedPrice(service)
  const basePrice = serviceBaseDisplayPrice(service)

  return (
    <div className={cn('rounded-xl border px-3 py-2.5', compact && 'min-w-0', isContractPrice ? 'border-[#d8912b]/30 bg-[#fff8eb]' : 'border-[#3d2a18]/10 bg-white/70')}>
      <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div className="min-w-0">
          <p className="truncate text-xs font-black text-[#6f6254]">{service.service_name}</p>
        </div>
        <div className="text-left sm:text-right">
          <p className="whitespace-nowrap text-sm font-black tabular-nums text-[#24170d]">{formatCurrency(price)}</p>
        </div>
      </div>
      {isContractPrice && (
        <p className="mt-2 text-[10px] font-bold leading-4 text-[#8b5e34]/75">
          Giá phòng gốc {formatCurrency(basePrice)} · đang dùng giá deal theo hợp đồng.
        </p>
      )}
    </div>
  )
}

function ServiceStatusPill({ service, isContractEnded = false }: { service: RoomServicePriceServiceResource; isContractEnded?: boolean }) {
  const hasSchedule = Boolean(service.scheduled_price || service.new_price)
  const isContractPrice = hasContractScopedPrice(service)
  const isEnded = isContractEnded || service.contract_is_ended
  const isInactive = service.is_active === false

  return (
    <div className={cn(
      'rounded-xl px-3 py-2 text-xs font-black leading-relaxed',
      isInactive
        ? 'bg-rose-50 text-rose-700'
        : isEnded
        ? 'bg-rose-50 text-rose-700'
        : hasSchedule
          ? 'bg-emerald-50 text-emerald-700'
          : isContractPrice
            ? 'bg-[#fff4df] text-[#8b5e34]'
            : 'bg-stone-100 text-stone-600',
    )}>
      <span className={cn(isInactive || isEnded ? 'text-rose-900' : 'text-[#24170d]')}>{service.service_name}:</span> {serviceStatusText(service, isEnded)}
      {isContractPrice && serviceContractLabel(service) && (
        <span className="mt-1 block text-[10px] uppercase tracking-wider opacity-75">{serviceContractLabel(service)}</span>
      )}
    </div>
  )
}

function PriceTransition({ service, compact = false }: { service: RoomServicePriceServiceResource; compact?: boolean }) {
  const oldPrice = serviceCurrentPrice(service)
  const newPrice = serviceTargetPrice(service)
  const tone = priceDeltaTone(oldPrice, newPrice)
  const hasSchedule = service.is_active !== false && (service.scheduled_price !== null || service.new_price !== null)

  return (
    <div className={cn('rounded-2xl border bg-white/75', compact ? 'p-2.5' : 'p-3.5', hasSchedule ? 'border-[#d8912b]/35' : 'border-[#3d2a18]/10')}>
      <div className="flex flex-wrap items-center gap-2">
        <span className="rounded-xl bg-stone-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-[#6f6254]">Giá cũ</span>
        <span className="font-black tabular-nums text-[#6f6254]">{formatCurrency(oldPrice)}</span>
        <ArrowRight className="h-4 w-4 text-[#b7894f]" />
        <span className={cn('rounded-xl px-2.5 py-1 text-[10px] font-black uppercase tracking-wider', hasSchedule ? 'bg-[#24170d] text-[#fff4df]' : 'bg-stone-100 text-[#6f6254]')}>Giá mới</span>
        <span className={cn('font-black tabular-nums', tone === 'increase' ? 'text-rose-600' : tone === 'decrease' ? 'text-emerald-700' : 'text-[#24170d]')}>
          {hasSchedule ? formatCurrency(newPrice) : 'Chưa lên lịch'}
        </span>
        {hasSchedule && tone !== 'same' && (
          <span className={cn('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-black', tone === 'increase' ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-700')}>
            {tone === 'increase' ? <TrendingUp className="h-3 w-3" /> : <TrendingDown className="h-3 w-3" />}
            {tone === 'increase' ? '+' : '-'}{formatCurrency(Math.abs(newPrice - oldPrice))}
          </span>
        )}
      </div>
    </div>
  )
}


function SkeletonRow() {
  return (
    <tr className="animate-pulse">
      <td className="px-5 py-4"><div className="h-12 rounded-2xl bg-stone-200" /></td>
      <td className="px-5 py-4"><div className="h-12 rounded-2xl bg-stone-200" /></td>
      <td className="px-5 py-4"><div className="h-12 rounded-2xl bg-stone-200" /></td>
      <td className="px-5 py-4"><div className="h-12 rounded-2xl bg-stone-200" /></td>
      <td className="px-5 py-4"><div className="h-12 rounded-2xl bg-stone-200" /></td>
    </tr>
  )
}

function RoomServicePriceCardSkeleton() {
  return (
    <div className="animate-pulse rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/70 p-4">
      <div className="flex items-center gap-3">
        <div className="h-10 w-10 rounded-2xl bg-stone-200" />
        <div className="flex-1 space-y-2">
          <div className="h-4 w-32 rounded-full bg-stone-200" />
          <div className="h-3 w-44 rounded-full bg-stone-200" />
        </div>
      </div>
      <div className="mt-4 grid gap-2 sm:grid-cols-2">
        <div className="h-16 rounded-2xl bg-stone-200" />
        <div className="h-16 rounded-2xl bg-stone-200" />
      </div>
    </div>
  )
}
