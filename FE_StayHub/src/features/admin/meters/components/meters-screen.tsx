import { useCallback, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { ArrowLeft, CheckCircle2, Edit3, Eye, Plus, RefreshCw, Search, Trash2, X, Zap } from 'lucide-react'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { AdminSelect } from '../../shared/components/AdminSelect'
import {
  createAdminMeterDevice,
  deleteAdminMeterDevice,
  fetchAdminMeterDeviceDetail,
  fetchAdminMeterDevices,
  fetchAdminServices,
  updateAdminMeterDevice,
  updateAdminMeterDeviceStatus,
} from '../services/meters.service'
import type { AdminMeterDeviceResource, AdminMeterFormErrors, AdminMeterFormValues } from '../types/meter-api.model'
import { validateMeterForm } from '../validations/meter.validation'

const meterTypeOptions = [
  { value: 1, label: 'Điện', tone: 'warning' as const },
  { value: 2, label: 'Nước', tone: 'success' as const },
]

const statusOptions = [
  { value: '', label: 'Tất cả trạng thái', tone: 'default' as const },
  { value: 1, label: 'Đang sử dụng', tone: 'success' as const },
  { value: 2, label: 'Ngừng sử dụng', tone: 'danger' as const },
  { value: 3, label: 'Đã thay thế', tone: 'warning' as const },
  { value: 4, label: 'Bị hỏng', tone: 'danger' as const },
]

const formStatusOptions = statusOptions.filter((item) => item.value !== '')

const filterMeterTypeOptions = [{ value: '', label: 'Tất cả loại đồng hồ', tone: 'default' as const }, ...meterTypeOptions]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'
const inputErrorClass = 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100'
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65'

const defaultForm: AdminMeterFormValues = {
  room_id: '',
  service_id: '',
  meter_code: '',
  meter_type: 1,
  initial_reading: '',
  final_reading: '',
  installed_at: '',
  status: 1,
  replaced_by_meter_id: '',
  note: '',
}

function getResourceList<T>(result: { data?: T[] } | T[] | null | undefined) {
  if (!result) return []
  if (Array.isArray(result)) return result
  return result.data || []
}

function getVisibleErrorMessage(error: unknown, fallback: string) {
  if (error instanceof ApiError) return error.message || fallback
  if (error instanceof Error) return error.message
  return fallback
}

const getStatusBadgeClass = (status: number) => {
  switch (status) {
    case 1:
      return 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]'
    case 2:
      return 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]'
    case 3:
      return 'border-[#f3c56b]/45 bg-[#f3c56b]/18 text-[#8a4f18]'
    case 4:
      return 'border-rose-200 bg-rose-50 text-rose-700'
    default:
      return 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]'
  }
}

export function MetersScreen() {
  const [keyword, setKeyword] = useState('')
  const [selectedMeterType, setSelectedMeterType] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [perPage, setPerPage] = useState(10)
  const [currentPage, setCurrentPage] = useState(1)
  const [paginationMeta, setPaginationMeta] = useState<{ current_page?: number; from?: number | null; last_page?: number; per_page?: number; to?: number | null; total?: number } | null>(null)
  const [meterDevices, setMeterDevices] = useState<AdminMeterDeviceResource[]>([])
  const [allMeterDevices, setAllMeterDevices] = useState<AdminMeterDeviceResource[]>([])
  const [rawServices, setRawServices] = useState<any[]>([])
  const [editingMeter, setEditingMeter] = useState<AdminMeterDeviceResource | null>(null)
  const [detailMeter, setDetailMeter] = useState<AdminMeterDeviceResource | null>(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [form, setForm] = useState<AdminMeterFormValues>(defaultForm)
  const [errors, setErrors] = useState<AdminMeterFormErrors>({})
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [statusChangingId, setStatusChangingId] = useState<number | null>(null)
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [isFormOpen, setIsFormOpen] = useState(false)

  const loadServices = useCallback(async () => {
    try {
      const response = await fetchAdminServices({ status: 1, per_page: 100 })
      const results = getResourceList(response.result)
      setRawServices(results)
    } catch {
      // service options can be empty if loading fails
    }
  }, [])

  const loadAllMeterDevices = useCallback(async () => {
    try {
      const response = await fetchAdminMeterDevices({ per_page: 1000 })
      const data = response.result?.data ?? []
      setAllMeterDevices(data)
    } catch (error) {
      console.error('Failed to load all meter devices:', error)
    }
  }, [])

  const loadMeterDevices = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)

    try {
      const response = await fetchAdminMeterDevices({
        keyword: keyword.trim() || undefined,
        meter_type: selectedMeterType ? Number(selectedMeterType) : undefined,
        status: selectedStatus ? Number(selectedStatus) : undefined,
        page: currentPage,
        per_page: perPage,
      })

      const result = response.result
      const data = result?.data ?? []
      const meta = result?.meta ?? null

      setMeterDevices(data)
      setPaginationMeta(meta)

      if (meta?.last_page && currentPage > meta.last_page) {
        setCurrentPage(meta.last_page)
      }
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải danh sách đồng hồ.'))
    } finally {
      setIsLoading(false)
    }
  }, [keyword, selectedMeterType, selectedStatus, currentPage, perPage])

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void loadServices()
    void loadAllMeterDevices()
  }, [loadServices, loadAllMeterDevices])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadMeterDevices()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [loadMeterDevices])

  const totalMeters = useMemo(() => allMeterDevices.length, [allMeterDevices])
  const electricityMeters = useMemo(() => allMeterDevices.filter((item) => item.meter_type === 1), [allMeterDevices])
  const waterMeters = useMemo(() => allMeterDevices.filter((item) => item.meter_type === 2), [allMeterDevices])

  const statsElectricity = useMemo(() => {
    return {
      total: electricityMeters.length,
      active: electricityMeters.filter((item) => item.status === 1).length,
      inactive: electricityMeters.filter((item) => item.status === 2 || item.status === 4).length,
      replaced: electricityMeters.filter((item) => item.status === 3).length,
    }
  }, [electricityMeters])

  const statsWater = useMemo(() => {
    return {
      total: waterMeters.length,
      active: waterMeters.filter((item) => item.status === 1).length,
      inactive: waterMeters.filter((item) => item.status === 2 || item.status === 4).length,
      replaced: waterMeters.filter((item) => item.status === 3).length,
    }
  }, [waterMeters])
  const safeCurrentPage = Math.max(1, Math.min(currentPage, paginationMeta?.last_page ?? currentPage))
  const totalPages = Math.max(1, paginationMeta?.last_page ?? (meterDevices.length >= perPage ? currentPage + 1 : currentPage))
  const paginationStart = paginationMeta?.from ?? (meterDevices.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + 1)
  const paginationEnd = paginationMeta?.to ?? (meterDevices.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + meterDevices.length)

  const visiblePages = useMemo(() => {
    const pages = new Set<number>([1, totalPages, safeCurrentPage - 1, safeCurrentPage, safeCurrentPage + 1])
    return Array.from(pages)
      .filter((page) => page >= 1 && page <= totalPages)
      .sort((a, b) => a - b)
  }, [safeCurrentPage, totalPages])

  const updateForm = (key: keyof AdminMeterFormValues, value: string | number) => {
    setForm((current) => {
      const next = { ...current, [key]: value }
      if (key === 'meter_type') {
        const targetTypeKey = Number(value) === 1 ? 'dien' : 'nuoc'
        const matchedService = rawServices.find(s => s.slug?.includes(targetTypeKey))
        if (matchedService) {
          next.service_id = String(matchedService.id)
        }
      }

      // Auto-generate meter code when room_id or meter_type changes
      if (key === 'room_id' || key === 'meter_type') {
        const roomVal = key === 'room_id' ? String(value) : current.room_id
        const typeVal = key === 'meter_type' ? Number(value) : current.meter_type

        if (roomVal.trim()) {
          const typePrefix = typeVal === 1 ? 'DIEN' : 'NUOC'
          const cleanRoom = roomVal.trim().toUpperCase()
          let isExpanded = false
          let buildingShort = 'MTR'
          if (cleanRoom.startsWith('BC')) {
            buildingShort = 'BC-SQUARE'
            isExpanded = true
          } else if (cleanRoom.startsWith('BT')) {
            buildingShort = 'BT-TOWER'
            isExpanded = true
          } else if (cleanRoom.startsWith('COL')) {
            buildingShort = 'COL-RIVER'
            isExpanded = true
          } else if (cleanRoom.startsWith('SG')) {
            buildingShort = 'SG-LUX'
            isExpanded = true
          } else if (cleanRoom.startsWith('TD')) {
            buildingShort = 'TD-HOME'
            isExpanded = true
          } else if (cleanRoom.startsWith('A')) {
            buildingShort = 'SG'
          } else if (cleanRoom.startsWith('B')) {
            buildingShort = 'TD'
          } else if (cleanRoom.startsWith('C')) {
            buildingShort = 'BC'
          } else {
            const prefixMatch = cleanRoom.match(/^([A-Z]+)/)
            buildingShort = prefixMatch ? prefixMatch[1] : 'MTR'
          }

          if (isExpanded) {
            next.meter_code = `${typePrefix}-EX-${buildingShort}-${cleanRoom}`
          } else {
            next.meter_code = `${typePrefix}-${buildingShort}-${cleanRoom}`
          }
        } else {
          next.meter_code = ''
        }
      }

      return next
    })
    setErrors((current) => ({ ...current, [key]: undefined }))
    setSuccessMessage(null)
  }

  const openCreateForm = () => {
    setEditingMeter(null)
    const initialForm = { ...defaultForm }
    const matchedService = rawServices.find(s => s.slug?.includes('dien'))
    if (matchedService) {
      initialForm.service_id = String(matchedService.id)
    }
    setForm(initialForm)
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const resetForm = () => {
    setEditingMeter(null)
    const initialForm = { ...defaultForm }
    const matchedService = rawServices.find(s => s.slug?.includes('dien'))
    if (matchedService) {
      initialForm.service_id = String(matchedService.id)
    }
    setForm(initialForm)
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
  }

  const editMeter = (meter: AdminMeterDeviceResource) => {
    setEditingMeter(meter)
    setForm({
      room_id: meter.room_number || String(meter.room_id),
      service_id: String(meter.service_id),
      meter_code: meter.meter_code || '',
      meter_type: meter.meter_type || 1,
      initial_reading: meter.initial_reading != null ? String(meter.initial_reading) : '',
      final_reading: meter.final_reading != null ? String(meter.final_reading) : '',
      installed_at: meter.installed_at || '',
      status: meter.status || 1,
      replaced_by_meter_id: meter.replaced_by_meter_id ? String(meter.replaced_by_meter_id) : '',
      note: meter.note || '',
    })
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const viewMeter = async (meter: AdminMeterDeviceResource) => {
    setDetailMeter(meter)
    setIsDetailOpen(true)
    setDetailErrorMessage(null)

    try {
      const response = await fetchAdminMeterDeviceDetail(meter.id)
      setDetailMeter(response.result)
    } catch (error) {
      setDetailErrorMessage(getVisibleErrorMessage(error, 'Không thể tải chi tiết đồng hồ.'))
    }
  }

  const closeDetail = () => {
    setIsDetailOpen(false)
    setDetailMeter(null)
    setDetailErrorMessage(null)
  }

  useEffect(() => {
    if (!isDetailOpen) return

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        closeDetail()
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [isDetailOpen])

  const submit = async () => {
    if (isSaving) return

    const nextErrors = validateMeterForm(form)
    setErrors(nextErrors)

    if (Object.keys(nextErrors).length > 0) {
      setErrorMessage('Vui lòng kiểm tra lại thông tin đồng hồ.')
      return
    }

    try {
      setIsSaving(true)
      setErrorMessage(null)
      setSuccessMessage(null)

      const payload = {
        room_number: form.room_id.trim(),
        service_id: Number(form.service_id),
        meter_code: form.meter_code.trim() || undefined,
        meter_type: form.meter_type,
        initial_reading: Number(form.initial_reading),
        installed_at: form.installed_at || undefined,
        final_reading: form.final_reading.trim() ? Number(form.final_reading) : undefined,
        status: form.status,
        replaced_by_meter_id: form.replaced_by_meter_id.trim() ? Number(form.replaced_by_meter_id) : undefined,
        note: form.note.trim() || undefined,
      }

      if (editingMeter) {
        await updateAdminMeterDevice(editingMeter.id, payload)
        setSuccessMessage('Cập nhật đồng hồ thành công.')
      } else {
        await createAdminMeterDevice(payload)
        setSuccessMessage('Tạo đồng hồ thành công.')
      }

      setEditingMeter(null)
      const initialForm = { ...defaultForm }
      const matchedService = rawServices.find(s => s.slug?.includes('dien'))
      if (matchedService) {
        initialForm.service_id = String(matchedService.id)
      }
      setForm(initialForm)
      setErrors({})
      setIsFormOpen(false)
      void loadMeterDevices()
      void loadAllMeterDevices()
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, editingMeter ? 'Không thể cập nhật đồng hồ.' : 'Không thể tạo đồng hồ.'))
    } finally {
      setIsSaving(false)
    }
  }

  const changeStatus = async (meter: AdminMeterDeviceResource, targetStatus: number) => {
    if (statusChangingId) return

    try {
      setStatusChangingId(meter.id)
      setErrorMessage(null)
      setSuccessMessage(null)

      await updateAdminMeterDeviceStatus(meter.id, targetStatus)
      setSuccessMessage('Cập nhật trạng thái đồng hồ thành công.')
      void loadMeterDevices()
      void loadAllMeterDevices()
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể cập nhật trạng thái đồng hồ.'))
    } finally {
      setStatusChangingId(null)
    }
  }

  const removeMeter = async (meter: AdminMeterDeviceResource) => {
    if (deletingId) return

    try {
      setDeletingId(meter.id)
      setErrorMessage(null)
      setSuccessMessage(null)
      await deleteAdminMeterDevice(meter.id)
      setSuccessMessage('Xóa đồng hồ thành công.')
      void loadMeterDevices()
      void loadAllMeterDevices()
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể xóa đồng hồ.'))
    } finally {
      setDeletingId(null)
    }
  }

  const replacementOptions = useMemo(
    () =>
      allMeterDevices
        .filter((item) => item.id !== editingMeter?.id)
        .map((item) => ({
          value: item.id,
          label: `${item.meter_code || `#${item.id}`} · ${item.room_number || 'Phòng không xác định'} · ${item.service_name || 'Dịch vụ'}`,
        }))
        .sort((left, right) => Number(left.value) - Number(right.value)),
    [editingMeter, allMeterDevices],
  )

  return (
    <div className="space-y-6 pb-6">
      <header className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/95 p-6 shadow-sm shadow-[#6b3f1d]/5">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p className="text-sm font-semibold uppercase tracking-[0.3em] text-[#8b5e34]/70">Quản lý</p>
            <h1 className="mt-2 text-3xl font-black tracking-tight text-[#24170d]">Chốt điện nước</h1>
            <p className="mt-2 max-w-2xl text-sm font-medium leading-6 text-[#6f6254]">Danh sách đồng hồ, trạng thái và cấu hình phòng/ dịch vụ.</p>
          </div>
          <div className="flex flex-wrap items-center gap-3">
            <button type="button" onClick={openCreateForm} className="inline-flex h-12 items-center justify-center rounded-2xl bg-[#24170d] px-5 text-sm font-black text-[#fff4df] transition hover:bg-[#3d2a18]">
              <Plus className="mr-2 h-4 w-4" /> Thêm đồng hồ
            </button>
            <button type="button" onClick={() => void loadMeterDevices()} className="inline-flex h-12 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-5 text-sm font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/15">
              <RefreshCw className="mr-2 h-4 w-4" /> Tải lại
            </button>
          </div>
        </div>

        <div className="mt-6 space-y-6">
          {/* Điện */}
          <div>
            <h3 className="text-xs font-black uppercase tracking-[0.2em] text-[#8b5e34]/70 mb-2 px-1">Đồng hồ Điện</h3>
            <div className="grid gap-3 grid-cols-2 md:grid-cols-4">
              <div className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white p-4 shadow-sm shadow-[#6b3f1d]/2">
                <p className="text-[10px] font-black uppercase tracking-[0.3em] text-[#8b5e34]/65">Tổng số điện</p>
                <p className="mt-2 text-2xl font-black text-[#24170d] tabular-nums">{statsElectricity.total}</p>
              </div>
              <div className="rounded-[1.5rem] border border-[#0f766e]/20 bg-[#0f766e]/10 p-4 shadow-sm shadow-[#0f766e]/5">
                <p className="text-[10px] font-black uppercase tracking-[0.3em] text-[#0f5f59]/75">Đang sử dụng</p>
                <p className="mt-2 text-2xl font-black text-[#0f5f59] tabular-nums">{statsElectricity.active}</p>
              </div>
              <div className="rounded-[1.5rem] border border-rose-200 bg-rose-50 p-4 shadow-sm shadow-rose-100/5">
                <p className="text-[10px] font-black uppercase tracking-[0.3em] text-rose-700/75">Ngừng sử dụng/Bị hỏng</p>
                <p className="mt-2 text-2xl font-black text-rose-700 tabular-nums">{statsElectricity.inactive}</p>
              </div>
              <div className="rounded-[1.5rem] border border-[#f3c56b]/45 bg-[#f3c56b]/15 p-4 shadow-sm shadow-[#a65f16]/5">
                <p className="text-[10px] font-black uppercase tracking-[0.3em] text-[#8a4f18]/75">Đã thay thế</p>
                <p className="mt-2 text-2xl font-black text-[#8a4f18] tabular-nums">{statsElectricity.replaced}</p>
              </div>
            </div>
          </div>

          {/* Nước */}
          <div>
            <h3 className="text-xs font-black uppercase tracking-[0.2em] text-[#8b5e34]/70 mb-2 px-1">Đồng hồ Nước</h3>
            <div className="grid gap-3 grid-cols-2 md:grid-cols-4">
              <div className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white p-4 shadow-sm shadow-[#6b3f1d]/2">
                <p className="text-[10px] font-black uppercase tracking-[0.3em] text-[#8b5e34]/65">Tổng số nước</p>
                <p className="mt-2 text-2xl font-black text-[#24170d] tabular-nums">{statsWater.total}</p>
              </div>
              <div className="rounded-[1.5rem] border border-[#0f766e]/20 bg-[#0f766e]/10 p-4 shadow-sm shadow-[#0f766e]/5">
                <p className="text-[10px] font-black uppercase tracking-[0.3em] text-[#0f5f59]/75">Đang sử dụng</p>
                <p className="mt-2 text-2xl font-black text-[#0f5f59] tabular-nums">{statsWater.active}</p>
              </div>
              <div className="rounded-[1.5rem] border border-rose-200 bg-rose-50 p-4 shadow-sm shadow-rose-100/5">
                <p className="text-[10px] font-black uppercase tracking-[0.3em] text-rose-700/75">Ngừng sử dụng/Bị hỏng</p>
                <p className="mt-2 text-2xl font-black text-rose-700 tabular-nums">{statsWater.inactive}</p>
              </div>
              <div className="rounded-[1.5rem] border border-[#f3c56b]/45 bg-[#f3c56b]/15 p-4 shadow-sm shadow-[#a65f16]/5">
                <p className="text-[10px] font-black uppercase tracking-[0.3em] text-[#8a4f18]/75">Đã thay thế</p>
                <p className="mt-2 text-2xl font-black text-[#8a4f18] tabular-nums">{statsWater.replaced}</p>
              </div>
            </div>
          </div>
        </div>
      </header>

      <section className={cn('grid gap-6 grid-cols-1', isFormOpen && 'xl:grid-cols-[1.8fr_1fr]')}>
        <div className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/95 shadow-sm shadow-[#6b3f1d]/5">
          <div className="flex flex-col gap-4 border-b border-[#3d2a18]/10 p-5 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex items-center gap-3">
              <div className="rounded-2xl bg-[#f3c56b]/15 p-3 text-[#a65f16]">
                <Zap className="h-5 w-5" />
              </div>
              <div>
                <p className="text-sm font-semibold text-[#24170d]">Danh sách đồng hồ</p>
                <p className="text-xs font-medium text-[#6f6254]">Tìm kiếm, lọc và quản lý trạng thái đồng hồ.</p>
              </div>
            </div>
            <div className="grid gap-3 sm:grid-cols-3 xl:w-[480px]">
              <div>
                <label className={labelClass}>Tìm theo từ khóa</label>
                <div className="relative">
                  <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8b5e34]/50" />
                  <input value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="Mã đồng hồ, phòng, ghi chú..." className={cn(inputClass, 'pl-11')} />
                </div>
              </div>
              <div>
                <label className={labelClass}>Loại đồng hồ</label>
                <AdminSelect value={selectedMeterType} options={filterMeterTypeOptions} onChange={(nextValue) => setSelectedMeterType(String(nextValue))} />
              </div>
              <div>
                <label className={labelClass}>Trạng thái</label>
                <AdminSelect value={selectedStatus} options={statusOptions} onChange={(nextValue) => setSelectedStatus(String(nextValue))} />
              </div>
            </div>
          </div>

          <div className="overflow-x-auto p-5">
            {errorMessage && (
              <div className="mb-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700">
                {errorMessage}
              </div>
            )}
            <div className="min-w-[780px]">
              <table className="w-full border-separate border-spacing-y-2 text-left text-sm">
                <thead>
                  <tr>
                    <th className="px-4 py-3 text-xs font-black uppercase tracking-[0.25em] text-[#8b5e34]/70">Phòng</th>
                    <th className="px-4 py-3 text-xs font-black uppercase tracking-[0.25em] text-[#8b5e34]/70">Mã</th>
                    <th className="px-4 py-3 text-xs font-black uppercase tracking-[0.25em] text-[#8b5e34]/70">Loại</th>
                    <th className="px-4 py-3 text-xs font-black uppercase tracking-[0.25em] text-[#8b5e34]/70">Chỉ số</th>
                    <th className="px-4 py-3 text-xs font-black uppercase tracking-[0.25em] text-[#8b5e34]/70">Trạng thái</th>
                    <th className="px-4 py-3 text-xs font-black uppercase tracking-[0.25em] text-[#8b5e34]/70">Hành động</th>
                  </tr>
                </thead>
                <tbody>
                  {isLoading ? (
                    <tr>
                      <td colSpan={6} className="px-4 py-20 text-center text-sm font-semibold text-[#6f6254]">
                        Đang tải danh sách đồng hồ...
                      </td>
                    </tr>
                  ) : meterDevices.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="px-4 py-20 text-center">
                        <div className="mx-auto max-w-xl rounded-[2rem] border border-dashed border-[#3d2a18]/12 bg-white/90 px-6 py-8 text-center">
                          <p className="text-lg font-black text-[#24170d]">Không tìm thấy đồng hồ</p>
                          <p className="mt-2 text-sm font-medium text-[#6f6254]">Thử thay đổi bộ lọc hoặc tạo đồng hồ mới.</p>
                        </div>
                      </td>
                    </tr>
                  ) : (
                    meterDevices.map((meter) => (
                      <tr key={meter.id} className="rounded-[1.75rem] border border-[#3d2a18]/10 bg-white align-middle shadow-sm shadow-[#6b3f1d]/5">
                        <td className="px-4 py-4 align-middle text-sm font-semibold text-[#24170d]">{meter.room_number || `Phòng #${meter.room_id}`}</td>
                        <td className="px-4 py-4 align-middle text-sm text-[#6f6254]">{meter.meter_code || '---'}</td>
                        <td className="px-4 py-4 align-middle text-sm text-[#6f6254]">{meter.meter_type_label || (meter.meter_type === 1 ? 'Điện' : 'Nước')}</td>
                        <td className="px-4 py-4 align-middle text-sm text-[#6f6254]">
                          {meter.initial_reading ?? '-'}
                          {meter.final_reading !== null && meter.final_reading !== undefined && meter.final_reading !== '' ? ` → ${meter.final_reading}` : ''}
                        </td>
                        <td className="px-4 py-4 align-middle text-sm text-[#24170d]">
                          <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-xs font-black uppercase tracking-[0.2em]', getStatusBadgeClass(meter.status))}>
                            {meter.status_label || 'Không xác định'}
                          </span>
                        </td>
                        <td className="px-4 py-4 align-middle text-sm text-[#6f6254]">
                          <div className="flex flex-wrap gap-2">
                            <button type="button" onClick={() => void viewMeter(meter)} className="inline-flex h-9 items-center gap-2 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/15">
                              <Eye className="h-4 w-4" /> Xem
                            </button>
                            <button type="button" onClick={() => editMeter(meter)} className="inline-flex h-9 items-center gap-2 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/15">
                              <Edit3 className="h-4 w-4" /> Sửa
                            </button>
                            <button type="button" onClick={() => void removeMeter(meter)} className="inline-flex h-9 items-center gap-2 rounded-2xl border border-rose-200 bg-rose-50 px-3 text-xs font-black text-rose-700 transition hover:bg-rose-100 disabled:opacity-60" disabled={deletingId === meter.id}>
                              <Trash2 className="h-4 w-4" /> Xóa
                            </button>
                            {meter.status !== 1 && (
                              <button type="button" onClick={() => void changeStatus(meter, 1)} className="inline-flex h-9 items-center gap-2 rounded-2xl border border-[#3d2a18]/10 bg-[#d1fae5] px-3 text-xs font-black text-[#166534] transition hover:bg-[#bbf7d0]/70 disabled:opacity-60" disabled={statusChangingId === meter.id}>
                                <CheckCircle2 className="h-4 w-4" /> Kích hoạt
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>

          <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb]/85 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
            <p className="text-xs font-black text-[#6f6254]">
              Hiển thị <span className="tabular-nums text-[#24170d]">{paginationStart}</span>-<span className="tabular-nums text-[#24170d]">{paginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{paginationMeta?.total ?? totalMeters}</span> đồng hồ
            </p>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
              <div className="w-full sm:w-36">
                <AdminSelect value={perPage} options={[{ value: 5, label: '5 dòng', tone: 'default' as const }, { value: 10, label: '10 dòng', tone: 'default' as const }, { value: 20, label: '20 dòng', tone: 'default' as const }, { value: 50, label: '50 dòng', tone: 'default' as const }]} onChange={(nextValue) => setPerPage(Number(nextValue))} menuPlacement="top" />
              </div>
              <div className="flex items-center justify-end gap-1.5">
                <button type="button" disabled={safeCurrentPage <= 1} onClick={() => setCurrentPage(safeCurrentPage - 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang trước">
                  <ArrowLeft className="h-4 w-4" />
                </button>
                {visiblePages.map((page) => (
                  <button key={page} type="button" onClick={() => setCurrentPage(page)} className={cn('inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition', page === safeCurrentPage ? 'border-[#24170d] bg-[#24170d] text-[#fff4df] shadow-sm' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15')}>
                    {page}
                  </button>
                ))}
                <button type="button" disabled={safeCurrentPage >= totalPages} onClick={() => setCurrentPage(safeCurrentPage + 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang sau">
                  <ArrowLeft className="h-4 w-4 rotate-180" />
                </button>
              </div>
            </div>
          </div>
        </div>

        {isFormOpen && (
          <aside className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/95 p-5 shadow-sm shadow-[#6b3f1d]/5">
            <div className="flex flex-col gap-4">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <p className="text-sm font-semibold text-[#24170d]">{editingMeter ? 'Cập nhật đồng hồ' : 'Thông tin đồng hồ mới'}</p>
                  <p className="text-xs font-medium text-[#6f6254]">Điền thông tin bắt buộc và nhấn lưu.</p>
                </div>
                <div className="flex items-center gap-2">
                  <button type="button" onClick={resetForm} className="rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-3 py-2 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/15">
                    <RefreshCw className="inline-block h-4 w-4" /> Làm mới
                  </button>
                  <button type="button" onClick={() => { resetForm(); setIsFormOpen(false) }} className="rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-3 py-2 text-xs font-black text-[#8b5e34] transition hover:bg-rose-50 hover:text-rose-700">
                    <X className="inline-block h-4 w-4" /> Đóng
                  </button>
                </div>
              </div>

              {successMessage && <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-700">{successMessage}</div>}
              {errorMessage && <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700">{errorMessage}</div>}

            <div className="grid gap-4">
              <div>
                <label className={labelClass}>Phòng</label>
                <input type="text" className={cn(inputClass, errors.room_id && inputErrorClass)} value={form.room_id} onChange={(event) => updateForm('room_id', event.target.value)} placeholder="Ví dụ: BT201" />
                <FieldError message={errors.room_id} />
              </div>

              <div>
                <label className={labelClass}>Loại đồng hồ</label>
                <AdminSelect value={form.meter_type} options={meterTypeOptions} invalid={!!errors.meter_type} onChange={(nextValue) => updateForm('meter_type', Number(nextValue))} />
                <FieldError message={errors.meter_type} />
              </div>

              <div>
                <label className={labelClass}>Mã đồng hồ</label>
                <input
                  readOnly
                  className={cn(inputClass, 'bg-[#efe2cf]/40 text-[#3d2a18]/70 cursor-not-allowed', errors.meter_code && inputErrorClass)}
                  value={form.meter_code}
                  placeholder="Mã đồng hồ tự động"
                />
                <FieldError message={errors.meter_code} />
              </div>

              <div>
                <label className={labelClass}>Chỉ số khởi tạo</label>
                <input type="number" min={0} step="any" className={cn(inputClass, errors.initial_reading && inputErrorClass)} value={form.initial_reading} onChange={(event) => updateForm('initial_reading', event.target.value)} placeholder="0" />
                <FieldError message={errors.initial_reading} />
              </div>

              <div>
                <label className={labelClass}>Chỉ số cuối</label>
                <input type="number" min={0} step="any" className={cn(inputClass, errors.final_reading && inputErrorClass)} value={form.final_reading} onChange={(event) => updateForm('final_reading', event.target.value)} placeholder="Tùy chọn" />
                <FieldError message={errors.final_reading} />
              </div>

              <div>
                <label className={labelClass}>Ngày lắp</label>
                <input type="date" className={cn(inputClass, errors.installed_at && inputErrorClass)} value={form.installed_at} onChange={(event) => updateForm('installed_at', event.target.value)} />
                <FieldError message={errors.installed_at} />
              </div>

              <div>
                <label className={labelClass}>Trạng thái</label>
                <AdminSelect value={form.status} options={formStatusOptions} invalid={!!errors.status} onChange={(nextValue) => updateForm('status', Number(nextValue))} />
                <FieldError message={errors.status} />
              </div>

              <div>
                <label className={labelClass}>Đồng hồ thay thế</label>
                <AdminSelect value={form.replaced_by_meter_id} options={[{ value: '', label: 'Chọn đồng hồ thay thế', tone: 'default' }, ...replacementOptions]} invalid={!!errors.replaced_by_meter_id} onChange={(nextValue) => updateForm('replaced_by_meter_id', String(nextValue))} />
                <FieldError message={errors.replaced_by_meter_id} />
              </div>

              <div>
                <label className={labelClass}>Ghi chú</label>
                <textarea className={cn(inputClass, 'min-h-[120px]', errors.note && inputErrorClass)} value={form.note} onChange={(event) => updateForm('note', event.target.value)} placeholder="Ghi chú vận hành hoặc vị trí lắp" />
                <FieldError message={errors.note} />
              </div>

              <button type="button" onClick={submit} disabled={isSaving} className="mt-2 inline-flex h-12 w-full items-center justify-center rounded-2xl bg-[#24170d] px-5 text-sm font-black text-[#fff4df] transition hover:bg-[#3d2a18] disabled:cursor-not-allowed disabled:opacity-55">
                {isSaving ? 'Đang lưu...' : editingMeter ? 'Lưu cập nhật' : 'Lưu đồng hồ'}
              </button>
            </div>
          </div>
        </aside>
      )}
      </section>

      {isDetailOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-3xl rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] p-6 shadow-xl shadow-[#6b3f1d]/20">
            <div className="flex items-start justify-between gap-4">
              <div>
                <p className="text-lg font-black text-[#24170d]">Chi tiết đồng hồ</p>
                <p className="mt-1 text-sm text-[#6f6254]">Thông tin đầy đủ của đồng hồ và trạng thái hiện tại.</p>
              </div>
              <button type="button" onClick={closeDetail} className="rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3 text-[#8b5e34] transition hover:bg-[#f3c56b]/15">
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="mt-6 grid gap-4 sm:grid-cols-2">
              <DetailTile label="Phòng" value={detailMeter?.room_number || detailMeter?.room_id ? `Phòng ${detailMeter?.room_number || detailMeter?.room_id}` : 'Không có'} />
              <DetailTile label="Mã đồng hồ" value={detailMeter?.meter_code || '---'} />
              <DetailTile label="Loại" value={detailMeter?.meter_type_label || 'Không xác định'} />
              <DetailTile
                label="Trạng thái"
                value={
                  detailMeter ? (
                    <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-xs font-black uppercase tracking-[0.2em]', getStatusBadgeClass(detailMeter.status))}>
                      {detailMeter.status_label || 'Không xác định'}
                    </span>
                  ) : (
                    'Không xác định'
                  )
                }
              />
              <DetailTile
                label="Chỉ số"
                value={
                  detailMeter ? (
                    <span>
                      {detailMeter.initial_reading ?? '-'}
                      {detailMeter.final_reading !== null && detailMeter.final_reading !== undefined && detailMeter.final_reading !== '' ? ` → ${detailMeter.final_reading}` : ''}
                    </span>
                  ) : (
                    '---'
                  )
                }
              />
              <DetailTile label="Ngày lắp" value={detailMeter?.installed_at || '---'} />
              <DetailTile label="Đồng hồ thay thế" value={detailMeter?.replacement_meter_code || '---'} />
            </div>

            {detailErrorMessage && <div className="mt-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700">{detailErrorMessage}</div>}
          </div>
        </div>
      )}
    </div>
  )
}

function DetailTile({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="rounded-[1.75rem] border border-[#3d2a18]/10 bg-white p-4">
      <p className="text-[10px] font-black uppercase tracking-[0.35em] text-[#8b5e34]/65">{label}</p>
      <div className="mt-3 text-sm font-semibold text-[#24170d]">{value ?? '---'}</div>
    </div>
  )
}

function FieldError({ message }: { message?: string }) {
  if (!message) return null
  return <p className="mt-2 text-xs font-semibold text-rose-600">{message}</p>
}
