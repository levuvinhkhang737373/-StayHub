import { useCallback, useEffect, useMemo, useState } from 'react'
import { BadgeDollarSign, Building2, CalendarClock, CheckCircle2, Loader2, Search, ShieldCheck, X } from 'lucide-react'
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
const labelClass = 'mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-[#8b5e34]'
const inputErrorClass = 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100'

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

export function RoomServicePricesScreen() {
  const { session } = useAdminSession()
  const isSuperAdmin = useMemo(() => isSuperAdminRole(session?.admin?.role), [session?.admin?.role])
  const nextPeriod = useMemo(() => getNextBillingPeriod(), [])
  const periodOptions = useMemo(() => buildFuturePeriodOptions(), [])

  const [billingMonth, setBillingMonth] = useState(nextPeriod.billing_month)
  const [billingYear, setBillingYear] = useState(nextPeriod.billing_year)
  const [buildingId, setBuildingId] = useState<string>('')
  const [roomId, setRoomId] = useState<string>('')
  const [keyword, setKeyword] = useState('')
  const [page, setPage] = useState(1)

  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [rooms, setRooms] = useState<AdminRoomResource[]>([])
  const [priceRooms, setPriceRooms] = useState<RoomServicePriceRoomResource[]>([])
  const [total, setTotal] = useState(0)
  const [lastPage, setLastPage] = useState(1)

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
      }
    }

    void loadBuildings()
  }, [isSuperAdmin])

  useEffect(() => {
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
  }, [buildingId])

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
        per_page: 12,
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
  }, [billingMonth, billingYear, buildingId, roomId, keyword, page])

  useEffect(() => {
    void loadPrices()
  }, [loadPrices])

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

  const openEditModal = (room: RoomServicePriceRoomResource) => {
    setEditingRoom(room)
    setModalError(null)
    setFieldErrors({})
    setPriceInputs(Object.fromEntries(room.services.map((service) => [service.id, ''])))
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
    const servicesWithInput = editingRoom.services.filter((service) => (priceInputs[service.id] ?? '').trim() !== '')
    const prices = servicesWithInput.map((service) => {
      const inputValue = priceInputs[service.id] ?? ''
      const price = moneyToNumber(inputValue)
      if (Number.isNaN(price) || price < 0) {
        nextFieldErrors[service.id] = 'Giá dịch vụ phải là số tiền hợp lệ và không âm.'
      }
      return { room_service_id: service.id, price }
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
    <div className="min-h-screen bg-[#f6efe3] px-4 py-6 text-[#24170d] sm:px-6 lg:px-8">
      <section className="relative overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] p-5 text-[#fff4df] shadow-xl shadow-[#24170d]/15 sm:p-7">
        <div className="absolute -right-16 -top-16 h-52 w-52 rounded-full bg-[#f3c56b]/20 blur-3xl" />
        <div className="absolute bottom-0 left-1/3 h-24 w-72 rounded-full bg-white/10 blur-2xl" />
        <div className="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <span className="text-xs font-black uppercase tracking-[0.2em] text-[#f3c56b]">Dịch vụ & Điện nước</span>
            <h1 className="mt-3 flex items-center gap-3 text-3xl font-black tracking-[-0.04em] sm:text-4xl">
              <BadgeDollarSign className="h-9 w-9 text-[#f3c56b]" /> Giá dịch vụ phòng
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
            className="inline-flex h-11 items-center justify-center gap-2 rounded-2xl bg-[#24170d] px-5 text-xs font-black uppercase tracking-wider text-[#fff4df] shadow-md shadow-[#24170d]/12 transition hover:bg-[#3d2a18]"
          >
            <CalendarClock className="h-4 w-4 text-[#f3c56b]" /> Tải lại
          </button>
        </div>
        <div className="mt-3 flex flex-wrap gap-2 text-xs font-bold text-[#6f6254]">

        </div>
      </section>

      {errorMessage && <Alert tone="error" message={errorMessage} />}
      {successMessage && <Alert tone="success" message={successMessage} onClose={() => setSuccessMessage(null)} />}

      <section className="mt-5 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-xl shadow-[#6b3f1d]/8">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[980px] text-left">
            <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
              <tr>
                <th className="px-5 py-4">Phòng</th>
                <th className="px-5 py-4">Service phòng</th>
                <th className="px-5 py-4">Giá tháng chọn</th>
                <th className="px-5 py-4">Trạng thái</th>
                <th className="px-5 py-4 text-right">Thao tác</th>
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
        <div className="flex items-center justify-between border-t border-[#3d2a18]/10 bg-[#fff7e8] px-5 py-4 text-sm font-bold text-[#6f6254]">
          <span>Trang {page}/{lastPage} · {total.toLocaleString('vi-VN')} phòng</span>
          <div className="flex gap-2">
            <button type="button" disabled={page <= 1} onClick={() => setPage((value) => Math.max(1, value - 1))} className="rounded-xl border border-[#3d2a18]/10 px-4 py-2 disabled:opacity-40">Trước</button>
            <button type="button" disabled={page >= lastPage} onClick={() => setPage((value) => Math.min(lastPage, value + 1))} className="rounded-xl border border-[#3d2a18]/10 px-4 py-2 disabled:opacity-40">Sau</button>
          </div>
        </div>
      </section>

      {editingRoom && (
        <div className="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="room-service-price-dialog-title">
          <button type="button" className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" onClick={closeEditModal} />
          <div className="relative z-10 w-full max-w-2xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-stone-950/30">
            <div className="bg-[#24170d] p-5 text-[#fff4df]">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <h2 id="room-service-price-dialog-title" className="mt-1.5 text-2xl font-black tracking-tight">Lên lịch giá phòng {editingRoom.room_number}</h2>
                  <p className="mt-1 text-xs font-bold text-[#f8e8c8]/70">Áp dụng từ {monthLabel(billingMonth, billingYear)} · không bao gồm điện/nước</p>
                </div>
                <button type="button" onClick={closeEditModal} className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20">
                  <X className="h-5 w-5" />
                </button>
              </div>
            </div>

            <div className="max-h-[65vh] space-y-4 overflow-y-auto p-5">
              {modalError && (
                <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold leading-relaxed text-rose-700">
                  {modalError}
                </div>
              )}
              {editingRoom.services.map((service) => (
                <div key={service.id} className="rounded-2xl border border-[#3d2a18]/10 bg-white/80 p-4">
                  <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                      <p className="text-sm font-black text-[#24170d]">{service.service_name}</p>
                      <p className="text-xs font-bold text-[#6f6254]">Hiện áp dụng: {formatCurrency(Number(service.effective_price || service.base_price || 0))} / {service.unit_name || 'dịch vụ'}</p>
                    </div>
                    <span className={cn('rounded-full px-3 py-1 text-[11px] font-black', service.scheduled_price ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-600')}>
                      {service.scheduled_price ? 'Đã lên lịch' : ''}
                    </span>
                  </div>
                  <label className={labelClass}>Giá mới</label>
                  <input
                    value={priceInputs[service.id] ?? ''}
                    onChange={(event) => {
                      setPriceInputs((current) => ({ ...current, [service.id]: formatMoneyInput(event.target.value) }))
                      setFieldErrors((current) => ({ ...current, [service.id]: '' }))
                    }}
                    className={cn(inputClass, fieldErrors[service.id] && inputErrorClass)}
                    placeholder="Nhập giá mới"
                  />
                  {fieldErrors[service.id] && <p className="mt-2 text-xs font-bold text-rose-600">{fieldErrors[service.id]}</p>}
                </div>
              ))}
            </div>

            <div className="flex flex-col gap-2 border-t border-[#3d2a18]/10 bg-[#fff7e8]/80 p-4 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex gap-2">
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

function RoomRow({ room, onEdit }: { room: RoomServicePriceRoomResource; onEdit: () => void }) {
  return (
    <tr className="bg-[#fffaf1] align-top transition hover:bg-[#fff4df]">
      <td className="px-5 py-4">
        <div className="flex items-start gap-3">
          <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#24170d] text-[#f3c56b]"><Building2 className="h-5 w-5" /></div>
          <div>
            <p className="font-black text-[#24170d]">Phòng {room.room_number}</p>
            <p className="text-xs font-bold text-[#6f6254]">{room.building_name || `Tòa ${room.building_id}`} · Tầng {room.floor ?? '—'}</p>
          </div>
        </div>
      </td>
      <td className="px-5 py-4">
        <div className="flex flex-wrap gap-2">
          {room.services.map((service) => <ServicePill key={service.id} service={service} />)}
        </div>
      </td>
      <td className="px-5 py-4 text-sm font-black text-[#24170d]">
        {room.services.map((service) => (
          <div key={service.id} className="mb-2 last:mb-0">{service.service_name}: {formatCurrency(Number(service.effective_price || service.base_price || 0))}</div>
        ))}
      </td>
      <td className="px-5 py-4">
        <div className="space-y-2">
          {room.services.map((service) => (
            <div key={service.id} className={cn('rounded-xl px-3 py-2 text-xs font-black', service.scheduled_price ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-600')}>
              {service.service_name}: {service.scheduled_price ? `Đã lên lịch ${formatCurrency(Number(service.scheduled_price))}` : service.status_label || 'Giá mặc định'}
            </div>
          ))}
        </div>
      </td>
      <td className="px-5 py-4 text-right">
        <button type="button" onClick={onEdit} className="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#24170d] px-4 py-2.5 text-xs font-black uppercase tracking-wider text-[#fff4df] shadow-md shadow-[#24170d]/10 transition hover:bg-[#3d2a18]">
          <ShieldCheck className="h-4 w-4 text-[#f3c56b]" /> Sửa giá
        </button>
      </td>
    </tr>
  )
}

function ServicePill({ service }: { service: RoomServicePriceServiceResource }) {
  return (
    <span className="rounded-full border border-[#3d2a18]/10 bg-white px-3 py-1.5 text-xs font-black text-[#6f6254]">
      {service.service_name} · {service.charge_method_label || service.unit_name || 'dịch vụ'}
    </span>
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
