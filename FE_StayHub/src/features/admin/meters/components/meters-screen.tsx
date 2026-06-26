import { useCallback, useEffect, useMemo, useState, Fragment } from 'react'
import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeft, Droplet, Edit3, Eye, Plus, RefreshCw, Search, Trash2, X, Zap } from 'lucide-react'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { AdminDateInput } from '../../../../shared/components/AdminDateInput'
import { useAdminSession, isBuildingManagerRole } from '../../auth/hooks/use-admin-session'
import { fetchAdminBuildingDetail, fetchAdminBuildings } from '../../facilities/services/facilities.service'
import {
  createAdminMeterDevice,
  deleteAdminMeterDevice,
  fetchAdminMeterDeviceDetail,
  fetchAdminMeterDevices,
  fetchAdminServices,
  updateAdminMeterDevice,
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
  { value: 3, label: 'Đã bị thay thế', tone: 'warning' as const },
  { value: 4, label: 'Bị hỏng', tone: 'danger' as const },
]

const formStatusOptions = statusOptions.filter((item) => item.value !== '')

const filterMeterTypeOptions = [{ value: '', label: 'Tất cả loại đồng hồ', tone: 'default' as const }, ...meterTypeOptions]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'
const inputErrorClass = 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100'
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65'

const defaultForm: AdminMeterFormValues = {
  building_id: '',
  room_id: '',
  service_id: '',
  meter_code: '',
  meter_type: 1,
  initial_reading: '0',
  installed_at: new Date().toLocaleDateString('en-CA'),
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
  const { session } = useAdminSession()
  const isManager = isBuildingManagerRole(session?.admin.role)

  const [keyword, setKeyword] = useState('')
  const [selectedMeterType, setSelectedMeterType] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [perPage, setPerPage] = useState(10)
  const [currentPage, setCurrentPage] = useState(1)
  const [paginationMeta, setPaginationMeta] = useState<{ current_page?: number; from?: number | null; last_page?: number; per_page?: number; to?: number | null; total?: number } | null>(null)
  const [meterDevices, setMeterDevices] = useState<AdminMeterDeviceResource[]>([])
  const [allMeterDevices, setAllMeterDevices] = useState<AdminMeterDeviceResource[]>([])
  const [rawServices, setRawServices] = useState<any[]>([])
  const [buildings, setBuildings] = useState<any[]>([])
  const [rooms, setRooms] = useState<any[]>([])
  const [editingMeter, setEditingMeter] = useState<AdminMeterDeviceResource | null>(null)

  const loadBuildings = useCallback(async () => {
    try {
      const response = await fetchAdminBuildings({ per_page: 100 })
      let results = getResourceList(response.result)
      if (isManager && session?.admin.id) {
        results = results.filter((b: any) => Number(b.manager_admin_id) === Number(session.admin.id))
      }
      setBuildings(results)
    } catch (error) {
      console.error('Failed to load buildings:', error)
    }
  }, [isManager, session?.admin.id])

  const loadRoomsForBuilding = useCallback(async (buildingId: number) => {
    if (!buildingId) {
      setRooms([])
      return
    }
    try {
      const response = await fetchAdminBuildingDetail(buildingId)
      const buildingDetail = response.result
      setRooms(buildingDetail?.rooms || [])
    } catch (error) {
      console.error('Failed to load rooms:', error)
      setRooms([])
    }
  }, [])

  const [detailMeter, setDetailMeter] = useState<AdminMeterDeviceResource | null>(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [form, setForm] = useState<AdminMeterFormValues>(defaultForm)
  const [errors, setErrors] = useState<AdminMeterFormErrors>({})
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [activeMessage, setActiveMessage] = useState<string | null>(null)
  const [activeType, setActiveType] = useState<'success' | 'error' | null>(null)

  useEffect(() => {
    if (successMessage) {
      const timer = setTimeout(() => {
        setSuccessMessage(null)
      }, 5000)
      return () => clearTimeout(timer)
    }
  }, [successMessage])

  useEffect(() => {
    if (errorMessage) {
      const timer = setTimeout(() => {
        setErrorMessage(null)
      }, 5000)
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

  useEffect(() => {
    if (form.building_id) {
      void loadRoomsForBuilding(Number(form.building_id))
    } else {
      setRooms([])
    }
  }, [form.building_id, loadRoomsForBuilding])

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
    void loadBuildings()
  }, [loadServices, loadAllMeterDevices, loadBuildings])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadMeterDevices()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [loadMeterDevices])

  const totalMeters = useMemo(() => allMeterDevices.length, [allMeterDevices])

  const statsStatus = useMemo(() => {
    const electricity = allMeterDevices.filter((item) => item.meter_type === 1)
    const water = allMeterDevices.filter((item) => item.meter_type === 2)

    return {
      total: {
        all: allMeterDevices.length,
        elec: electricity.length,
        water: water.length,
      },
      active: {
        all: allMeterDevices.filter((item) => item.status === 1).length,
        elec: electricity.filter((item) => item.status === 1).length,
        water: water.filter((item) => item.status === 1).length,
      },
      inactive: {
        all: allMeterDevices.filter((item) => item.status === 2).length,
        elec: electricity.filter((item) => item.status === 2).length,
        water: water.filter((item) => item.status === 2).length,
      },
      replaced: {
        all: allMeterDevices.filter((item) => item.status === 3).length,
        elec: electricity.filter((item) => item.status === 3).length,
        water: water.filter((item) => item.status === 3).length,
      },
      broken: {
        all: allMeterDevices.filter((item) => item.status === 4).length,
        elec: electricity.filter((item) => item.status === 4).length,
        water: water.filter((item) => item.status === 4).length,
      },
    }
  }, [allMeterDevices])
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

  const findCurrentMeterForRoomAndType = useCallback((roomId: number, meterType: number, excludeId?: number) => {
    if (!roomId || !meterType) return null
    return allMeterDevices.find(
      (item) =>
        item.room_id === roomId &&
        item.meter_type === meterType &&
        item.id !== excludeId &&
        item.status === 1 // STATUS_ACTIVE
    ) || allMeterDevices.find(
      (item) =>
        item.room_id === roomId &&
        item.meter_type === meterType &&
        item.id !== excludeId &&
        item.status !== 3 // NOT STATUS_REPLACED
    )
  }, [allMeterDevices])

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

      if (key === 'room_id' || key === 'meter_type') {
        const roomId = Number(next.room_id)
        const meterType = Number(next.meter_type)
        const currentMeter = findCurrentMeterForRoomAndType(roomId, meterType, editingMeter?.id)
        if (currentMeter) {
          next.replaced_by_meter_id = String(currentMeter.id)
        } else {
          next.replaced_by_meter_id = ''
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
    if (isManager && buildings.length === 1) {
      initialForm.building_id = String(buildings[0].id)
    }
    setForm(initialForm)
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const resetForm = () => {
    if (editingMeter) {
      setForm({
        building_id: editingMeter.building_id ? String(editingMeter.building_id) : '',
        room_id: String(editingMeter.room_id),
        service_id: String(editingMeter.service_id),
        meter_code: editingMeter.meter_code || '',
        meter_type: editingMeter.meter_type || 1,
        initial_reading: editingMeter.initial_reading != null ? String(editingMeter.initial_reading) : '',
        installed_at: editingMeter.installed_at || '',
        status: editingMeter.status || 1,
        replaced_by_meter_id: editingMeter.replaced_by_meter_id ? String(editingMeter.replaced_by_meter_id) : '',
        note: editingMeter.note || '',
      })
    } else {
      const initialForm = { ...defaultForm }
      const matchedService = rawServices.find(s => s.slug?.includes('dien'))
      if (matchedService) {
        initialForm.service_id = String(matchedService.id)
      }
      if (isManager && buildings.length === 1) {
        initialForm.building_id = String(buildings[0].id)
      }
      setForm(initialForm)
    }
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
  }

  const editMeter = async (meter: AdminMeterDeviceResource) => {
    setEditingMeter(meter)
    const initialReplacementId = meter.replaced_by_meter_id
      ? String(meter.replaced_by_meter_id)
      : (findCurrentMeterForRoomAndType(meter.room_id, meter.meter_type, meter.id)?.id ? String(findCurrentMeterForRoomAndType(meter.room_id, meter.meter_type, meter.id)?.id) : '')

    setForm({
      building_id: meter.building_id ? String(meter.building_id) : '',
      room_id: String(meter.room_id),
      service_id: String(meter.service_id),
      meter_code: meter.meter_code || '',
      meter_type: meter.meter_type || 1,
      initial_reading: meter.initial_reading != null ? String(meter.initial_reading) : '',
      installed_at: meter.installed_at || '',
      status: meter.status || 1,
      replaced_by_meter_id: initialReplacementId,
      note: meter.note || '',
    })
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)

    try {
      const response = await fetchAdminMeterDeviceDetail(meter.id)
      const detail = response.result
      if (detail) {
        setEditingMeter(detail)
        const detailReplacementId = detail.replaced_by_meter_id
          ? String(detail.replaced_by_meter_id)
          : (findCurrentMeterForRoomAndType(detail.room_id, detail.meter_type, detail.id)?.id ? String(findCurrentMeterForRoomAndType(detail.room_id, detail.meter_type, detail.id)?.id) : '')

        setForm({
          building_id: detail.building_id ? String(detail.building_id) : '',
          room_id: String(detail.room_id),
          service_id: String(detail.service_id),
          meter_code: detail.meter_code || '',
          meter_type: detail.meter_type || 1,
          initial_reading: detail.initial_reading != null ? String(detail.initial_reading) : '',
          installed_at: detail.installed_at || '',
          status: detail.status || 1,
          replaced_by_meter_id: detailReplacementId,
          note: detail.note || '',
        })
      }
    } catch (error) {
      console.error('Failed to fetch meter detail in edit:', error)
    }
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
        room_id: Number(form.room_id),
        service_id: Number(form.service_id),
        meter_type: form.meter_type,
        initial_reading: Number(form.initial_reading),
        installed_at: form.installed_at || undefined,
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
      if (isManager && buildings.length === 1) {
        initialForm.building_id = String(buildings[0].id)
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
    <div className="space-y-5 sm:space-y-6 text-[#24170d]">
      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-4 text-[#fff4df] sm:p-5 lg:p-6">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_14%,rgba(243,197,107,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(15,118,110,0.26),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
          <div className="pointer-events-none absolute inset-x-6 bottom-0 h-px bg-gradient-to-r from-transparent via-[#f3c56b]/40 to-transparent" />

          <div className="relative flex min-w-0 flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div className="min-w-0">
              <Link to="/admin/dashboard" className="mb-2 inline-flex items-center gap-2 text-xs font-black text-[#f3c56b] transition hover:text-[#ffd56f]">
                <ArrowLeft className="h-3.5 w-3.5" /> Về dashboard
              </Link>

              <h1 className="mt-3 max-w-3xl text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem]">Quản lý đồng hồ</h1>
            </div>
            <div className="relative flex flex-wrap items-center gap-3">
              <button type="button" onClick={openCreateForm} className="inline-flex h-9 items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition-all hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98]">
                <Plus className="h-4 w-4 stroke-[2.8]" /> Thêm đồng hồ
              </button>
            </div>
          </div>

          <div className="relative mt-6 space-y-6">
            <div>
              <div className="mb-2.5 flex items-center gap-2 px-1">
                <div className="flex h-5 w-5 items-center justify-center rounded-lg bg-[#f3c56b]/20 text-[#f3c56b]">
                  <Zap className="h-3 w-3 stroke-[2.5]" />
                </div>
                <h2 className="text-[10px] font-black uppercase tracking-[0.2em] text-[#f3c56b]/90">Đồng hồ Điện</h2>
              </div>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                <MetricCard label="Tổng đồng hồ" value={statsStatus.total.elec} tone="neutral" />
                <MetricCard label="Đang sử dụng" value={statsStatus.active.elec} tone="emerald" />
                <MetricCard label="Ngừng sử dụng" value={statsStatus.inactive.elec} tone="teal" />
                <MetricCard label="Đã bị thay thế" value={statsStatus.replaced.elec} tone="amber" />
                <MetricCard label="Bị hỏng" value={statsStatus.broken.elec} tone="rose" />
              </div>
            </div>

            <div>
              <div className="mb-2.5 flex items-center gap-2 px-1">
                <div className="flex h-5 w-5 items-center justify-center rounded-lg bg-cyan-400/20 text-cyan-400">
                  <Droplet className="h-3 w-3 stroke-[2.5]" />
                </div>
                <h2 className="text-[10px] font-black uppercase tracking-[0.2em] text-cyan-400/90">Đồng hồ Nước</h2>
              </div>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                <MetricCard label="Tổng đồng hồ" value={statsStatus.total.water} tone="neutral" />
                <MetricCard label="Đang sử dụng" value={statsStatus.active.water} tone="emerald" />
                <MetricCard label="Ngừng sử dụng" value={statsStatus.inactive.water} tone="teal" />
                <MetricCard label="Đã bị thay thế" value={statsStatus.replaced.water} tone="amber" />
                <MetricCard label="Bị hỏng" value={statsStatus.broken.water} tone="rose" />
              </div>
            </div>
          </div>
        </div>
      </section>

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

      <div className="min-w-0">
        <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
          <div className="border-b border-[#3d2a18]/10 bg-[#fff8eb]/85 p-4 sm:p-5">
            <div className="grid gap-3 lg:grid-cols-[minmax(18rem,1fr)_200px_200px]">
              <div className="relative min-w-0">
                <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
                <input type="text" value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="Tìm phòng, ghi chú..." className={`${inputClass} pl-11 pr-24`} />
                <button type="button" onClick={() => setKeyword('')} disabled={!keyword} className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20 disabled:cursor-not-allowed disabled:opacity-45">
                  <X className="h-3.5 w-3.5" /> Xóa lọc
                </button>
              </div>
              <AdminSelect value={selectedMeterType} options={filterMeterTypeOptions} onChange={(nextValue) => setSelectedMeterType(String(nextValue))} />
              <AdminSelect value={selectedStatus} options={statusOptions} onChange={(nextValue) => setSelectedStatus(String(nextValue))} />
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full min-w-[780px] text-left">
              <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
                <tr>
                  <th className="px-5 py-4">Phòng</th>
                  <th className="px-5 py-4">Mã đồng hồ</th>
                  <th className="px-5 py-4">Loại</th>
                  <th className="px-5 py-4">Chỉ số</th>
                  <th className="px-5 py-4">Trạng thái</th>
                  <th className="px-5 py-4"><div className="flex justify-end"><div className="w-[136px] text-center">Thao tác</div></div></th>
                </tr>
              </thead>
              <tbody className={cn('divide-y divide-[#3d2a18]/8 bg-[#fffaf1]/70 transition-opacity duration-200', isLoading && 'opacity-50 pointer-events-none')}>
                {isLoading && meterDevices.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-5 py-20 text-center text-sm font-semibold text-[#6f6254]">
                      Đang tải danh sách đồng hồ...
                    </td>
                  </tr>
                ) : meterDevices.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-5 py-20 text-center">
                      <div className="mx-auto flex max-w-sm flex-col items-center rounded-[2rem] border border-dashed border-[#3d2a18]/12 bg-white/55 px-6 py-8">
                        <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]"><Zap className="h-9 w-9" /></div>
                        <p className="text-lg font-black tracking-tight text-[#24170d]">Không tìm thấy đồng hồ</p>
                        <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">{keyword.trim() || selectedMeterType || selectedStatus ? 'Thử xóa bộ lọc hoặc đổi từ khóa tìm kiếm.' : 'Hãy tạo đồng hồ mới hoặc kiểm tra lại dữ liệu hiện tại.'}</p>
                      </div>
                    </td>
                  </tr>
                ) : (
                  meterDevices.map((meter) => (
                    <tr key={meter.id} className="group transition hover:bg-[#f3c56b]/10">
                      <td className="px-5 py-4 text-sm font-black text-[#24170d] w-[12%]">
                        {meter.room_number || `Phòng #${meter.room_id}`}
                      </td>
                      <td className="px-5 py-4 text-sm text-[#24170d] w-[18%]">
                        <span className="font-mono text-xs bg-[#efe2cf]/50 px-2.5 py-1 rounded border border-[#3d2a18]/10 text-[#3d2a18]">
                          {meter.meter_code || '-'}
                        </span>
                      </td>
                      <td className="px-5 py-4 text-sm text-[#6f6254] w-[15%]">
                        <div className="flex items-center gap-1.5 font-bold">
                          {meter.meter_type === 1 ? (
                            <>
                              <Zap className="h-3.5 w-3.5 text-[#f3c56b] fill-[#f3c56b]/10" />
                              <span className="text-[#8a4f18]">Điện</span>
                            </>
                          ) : (
                            <>
                              <Droplet className="h-3.5 w-3.5 text-cyan-600 fill-cyan-500/10" />
                              <span className="text-cyan-700">Nước</span>
                            </>
                          )}
                        </div>
                      </td>
                      <td className="px-5 py-4 text-sm text-[#6f6254] w-[20%]">
                        {(() => {
                          const unit = meter.meter_type === 1 ? ' kWh' : ' m³'
                          return (
                            <>
                              <span className="font-semibold text-[#6f6254] tabular-nums">
                                {meter.initial_reading !== null && meter.initial_reading !== undefined && meter.initial_reading !== '' ? `${meter.initial_reading}${unit}` : '-'}
                              </span>
                            </>
                          )
                        })()}
                      </td>
                      <td className="px-5 py-4 w-[18%]">
                        <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-xs font-black shadow-sm', getStatusBadgeClass(meter.status))}>
                          {meter.status_label || 'Không xác định'}
                        </span>
                      </td>
                      <td className="px-5 py-4 w-[17%]">
                        <div className="flex justify-end">
                          <div className="w-[136px] flex items-center justify-end gap-2">
                            <button type="button" onClick={() => void viewMeter(meter)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95" title="Xem chi tiết"><Eye className="h-5 w-5" /></button>
                            <button type="button" onClick={() => editMeter(meter)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#3d2a18]/10 active:scale-95" title="Chỉnh sửa"><Edit3 className="h-5 w-5" /></button>
                            <button type="button" onClick={() => void removeMeter(meter)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-95" title="Xóa" disabled={deletingId === meter.id}><Trash2 className="h-5 w-5" /></button>
                          </div>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
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
                {visiblePages.map((page, index) => {
                  const previousPage = visiblePages[index - 1]
                  const hasGap = previousPage && page - previousPage > 1

                  return (
                    <Fragment key={page}>
                      {hasGap && <span className="px-1 text-xs font-black text-[#8b5e34]/60">...</span>}
                      <button type="button" onClick={() => setCurrentPage(page)} className={cn('inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition', page === safeCurrentPage ? 'border-[#24170d] bg-[#24170d] text-[#fff4df] shadow-sm' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15')}>
                        {page}
                      </button>
                    </Fragment>
                  )
                })}
                <button type="button" disabled={safeCurrentPage >= totalPages} onClick={() => setCurrentPage(safeCurrentPage + 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang sau">
                  <ArrowLeft className="h-4 w-4 rotate-180" />
                </button>
              </div>
            </div>
          </div>
        </section>
      </div>

      {isFormOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <button type="button" onClick={() => { resetForm(); setIsFormOpen(false) }} className="absolute inset-0 bg-stone-950/60 backdrop-blur-sm" />
          <div className="relative z-10 w-full max-w-2xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-stone-950/30">
            <div className="bg-[#24170d] p-5 text-[#fff4df]">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <h2 className="text-xl font-black tracking-tight">{editingMeter ? 'Cập nhật đồng hồ' : 'Thông tin đồng hồ mới'}</h2>
                  <p className="mt-1 text-xs font-semibold text-[#f8e8c8]/72">Điền thông tin bắt buộc và nhấn lưu.</p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                  <button type="button" onClick={resetForm} className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20" title="Làm mới form">
                    <RefreshCw className="h-4 w-4" />
                  </button>
                  <button type="button" onClick={() => { resetForm(); setIsFormOpen(false) }} className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20" title="Đóng form">
                    <X className="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>

            <div className="max-h-[70vh] overflow-y-auto p-6 space-y-4">
              {successMessage && <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-700">{successMessage}</div>}
              {errorMessage && <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700">{errorMessage}</div>}

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className={labelClass}>Tòa nhà</label>
                  <AdminSelect
                    value={form.building_id}
                    options={buildings.map((b) => ({ value: b.id, label: b.name }))}
                    invalid={!!errors.building_id}
                    placeholder="Chọn tòa nhà"
                    disabled={isManager && buildings.length === 1}
                    onChange={(nextValue) => {
                      updateForm('building_id', String(nextValue))
                      updateForm('room_id', '')
                    }}
                  />
                  <FieldError message={errors.building_id} />
                </div>

                <div>
                  <label className={labelClass}>Phòng</label>
                  <AdminSelect
                    value={form.room_id}
                    options={rooms.map((r) => ({ value: r.id, label: r.room_number }))}
                    disabled={!form.building_id}
                    invalid={!!errors.room_id}
                    placeholder={form.building_id ? "Chọn phòng" : "Vui lòng chọn tòa nhà trước"}
                    onChange={(nextValue) => updateForm('room_id', String(nextValue))}
                  />
                  <FieldError message={errors.room_id} />
                </div>

                <div>
                  <label className={labelClass}>Loại đồng hồ</label>
                  <AdminSelect value={form.meter_type} options={meterTypeOptions} invalid={!!errors.meter_type} onChange={(nextValue) => updateForm('meter_type', Number(nextValue))} />
                  <FieldError message={errors.meter_type} />
                </div>

                <div>
                  <label className={labelClass}>Chỉ số</label>
                  <input type="number" min={0} step="any" className={cn(inputClass, errors.initial_reading && inputErrorClass)} value={form.initial_reading} onChange={(event) => updateForm('initial_reading', event.target.value)} placeholder="0" />
                  <FieldError message={errors.initial_reading} />
                </div>

                <div>
                  <label className={labelClass}>Ngày lắp</label>
                  <AdminDateInput
                    className={cn(inputClass, errors.installed_at && inputErrorClass)}
                    value={form.installed_at}
                    onChange={(value) => updateForm('installed_at', value)}
                  />
                  <FieldError message={errors.installed_at} />
                </div>

                <div>
                  <label className={labelClass}>Trạng thái</label>
                  <AdminSelect value={form.status} options={formStatusOptions} invalid={!!errors.status} onChange={(nextValue) => updateForm('status', Number(nextValue))} />
                  <FieldError message={errors.status} />
                </div>

                {(!!findCurrentMeterForRoomAndType(Number(form.room_id), Number(form.meter_type), editingMeter?.id) || !!form.replaced_by_meter_id) && (
                  <div className="md:col-span-2">
                    <label className={labelClass}>Đồng hồ thay thế</label>
                    <AdminSelect value={form.replaced_by_meter_id} options={[{ value: '', label: 'Chọn đồng hồ thay thế', tone: 'default' }, ...replacementOptions]} invalid={!!errors.replaced_by_meter_id} disabled={true} onChange={(nextValue) => updateForm('replaced_by_meter_id', String(nextValue))} />
                    <FieldError message={errors.replaced_by_meter_id} />
                  </div>
                )}

                <div className="md:col-span-2">
                  <label className={labelClass}>Ghi chú</label>
                  <textarea className={cn(inputClass, 'min-h-[100px]', errors.note && inputErrorClass)} value={form.note} onChange={(event) => updateForm('note', event.target.value)} placeholder="Ghi chú vận hành hoặc vị trí lắp" />
                  <FieldError message={errors.note} />
                </div>
              </div>

              <div className="flex justify-end gap-3 pt-4 border-t border-[#3d2a18]/10">
                <button type="button" onClick={() => { resetForm(); setIsFormOpen(false) }} className="inline-flex h-12 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-5 text-sm font-black text-[#8b5e34] hover:bg-stone-100 transition">
                  Hủy
                </button>
                <button type="button" onClick={submit} disabled={isSaving} className="inline-flex h-12 min-w-32 items-center justify-center rounded-2xl bg-[#24170d] px-5 text-sm font-black text-[#fff4df] hover:bg-[#3d2a18] disabled:cursor-not-allowed disabled:opacity-55 transition">
                  {isSaving ? 'Đang lưu...' : editingMeter ? 'Lưu cập nhật' : 'Lưu đồng hồ'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

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

function MetricCard({ label, value, subValue, tone }: { label: string; value: number; subValue?: string; tone: 'neutral' | 'emerald' | 'amber' | 'teal' | 'rose' }) {
  const toneClassNames = {
    neutral: 'border-[#f8e8c8]/12 bg-[#f8e8c8]/10 text-[#fff4df]',
    emerald: 'border-[#0f766e]/35 bg-[#0f766e]/16 text-[#c8fff4]',
    amber: 'border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#fff4df]',
    teal: 'border-cyan-200/25 bg-cyan-100/10 text-cyan-50',
    rose: 'border-rose-400/25 bg-rose-500/10 text-rose-200',
  }[tone]

  return (
    <div className={cn('rounded-3xl border px-4 py-3 backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/10', toneClassNames)}>
      <p className="text-[10px] font-black uppercase tracking-[0.18em] opacity-65">{label}</p>
      <p className="mt-1 text-3xl font-black tracking-tight tabular-nums">{value}</p>
      {subValue && (
        <p className="mt-1 text-[10px] font-black opacity-75">{subValue}</p>
      )}
    </div>
  )
}
