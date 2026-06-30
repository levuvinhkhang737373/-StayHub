import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { Car, Bike, Fuel, Zap, Edit3, Eye, Plus, RefreshCw, Search, Trash2, X } from 'lucide-react'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { formatDateTime } from '../../../../shared/lib/utils/format'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { cn } from '../../../../shared/lib/utils/cn'
import {
  fetchAdminVehicles,
  fetchAdminVehicleDetail,
  createAdminVehicle,
  updateAdminVehicle,
  deleteAdminVehicle,
} from '../services/vehicles.service'
import { fetchAdminTenants } from '../../tenants/services/tenants.service'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import type { AdminVehicleResource, AdminVehicleFormValues, AdminVehicleFormErrors } from '../types/vehicle.model'
import type { AdminTenantResource } from '../../tenants/types/tenant-api.model'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import { validateVehicleForm } from '../validations/vehicle.validation'

function getVisibleErrorMessage(error: unknown, fallback: string) {
  if (error instanceof ApiError) return error.message || fallback
  if (error instanceof Error) return error.message
  return fallback
}

const defaultForm: AdminVehicleFormValues = {
  building_id: '',
  tenant_id: '',
  vehicle_type: 1,
  license_plate: '',
  brand: '',
  color: '',
  is_active: true,
}

const vehicleTypeOptions = [
  { value: 1, label: 'Xe máy', tone: 'warning' as const },
  { value: 2, label: 'Xe đạp', tone: 'success' as const },
  { value: 3, label: 'Ô tô', tone: 'danger' as const },
  { value: 4, label: 'Xe điện', tone: 'success' as const },
]

const filterVehicleTypeOptions = [
  { value: '', label: 'Tất cả loại xe', tone: 'default' as const },
  ...vehicleTypeOptions,
]

const statusOptions = [
  { value: '', label: 'Tất cả trạng thái', tone: 'default' as const },
  { value: '1', label: 'Còn sử dụng', tone: 'success' as const },
  { value: '0', label: 'Hết sử dụng', tone: 'danger' as const },
]

const formStatusOptions = [
  { value: 1, label: 'Còn sử dụng', tone: 'success' as const },
  { value: 0, label: 'Hết sử dụng', tone: 'danger' as const },
]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'
const inputErrorClass = 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100'
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65'

export function VehiclesScreen() {
  const [vehicles, setVehicles] = useState<AdminVehicleResource[]>([])
  const [tenants, setTenants] = useState<AdminTenantResource[]>([])
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])

  const [keyword, setKeyword] = useState('')
  const [selectedType, setSelectedType] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [activeMessage, setActiveMessage] = useState<string | null>(null)
  const [activeType, setActiveType] = useState<'success' | 'error' | null>(null)

  // Drawer / Form State
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [editingVehicle, setEditingVehicle] = useState<AdminVehicleResource | null>(null)
  const [form, setForm] = useState<AdminVehicleFormValues>(defaultForm)
  const [errors, setErrors] = useState<AdminVehicleFormErrors>({})

  // Detail Modal State
  const [detailVehicle, setDetailVehicle] = useState<AdminVehicleResource | null>(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null)

  const loadTenants = useCallback(async () => {
    try {
      const response = await fetchAdminTenants({ per_page: 100, status: 1 })
      const tenantsEnvelope = response as any
      const list = tenantsEnvelope.result?.data || tenantsEnvelope.data || []
      setTenants(list)
    } catch (e) {
      console.error('Không thể load danh sách khách thuê', e)
    }
  }, [])

  const loadBuildings = useCallback(async () => {
    try {
      const response = await fetchAdminBuildings({ per_page: 100 })
      const list = response.result || []
      setBuildings(Array.isArray(list) ? list : [])
    } catch (e) {
      console.error('Không thể load danh sách tòa nhà', e)
    }
  }, [])

  const loadVehicles = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)
    try {
      const response = await fetchAdminVehicles({
        keyword: keyword.trim() || undefined,
        vehicle_type: selectedType ? Number(selectedType) : undefined,
        is_active: selectedStatus === '' ? undefined : selectedStatus === '1',
      })
      const list = Array.isArray(response.result) ? response.result : (response.result?.data || [])
      setVehicles(list)
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải danh sách phương tiện.'))
    } finally {
      setIsLoading(false)
    }
  }, [keyword, selectedType, selectedStatus])

  useEffect(() => {
    void loadTenants()
    void loadBuildings()
  }, [loadTenants, loadBuildings])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadVehicles()
    }, 250)
    return () => window.clearTimeout(timer)
  }, [loadVehicles])

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

  const metrics = useMemo(() => {
    const total = vehicles.length
    const motorbikes = vehicles.filter((v) => Number(v.vehicle_type) === 1).length
    const bicycles = vehicles.filter((v) => Number(v.vehicle_type) === 2).length
    const cars = vehicles.filter((v) => Number(v.vehicle_type) === 3).length
    const electrics = vehicles.filter((v) => Number(v.vehicle_type) === 4).length
    return { total, motorbikes, bicycles, cars, electrics }
  }, [vehicles])

  const tenantSelectOptions = useMemo(() => {
    if (!form.building_id) return []
    const buildingIdNum = Number(form.building_id)
    return tenants
      .filter((t) => t.building_id === buildingIdNum || t.current_room?.building_id === buildingIdNum)
      .map((t) => {
        const roomLabel = t.room_number || t.current_room?.room_number ? `Phòng ${t.room_number || t.current_room?.room_number}` : 'Chưa có phòng'
        return {
          value: t.id,
          label: `${t.full_name || t.username} (${roomLabel} - ${t.phone || 'Không có sđt'})`,
          tone: 'default' as const,
        }
      })
  }, [tenants, form.building_id])

  useEffect(() => {
    if (form.building_id && tenantSelectOptions.length === 1) {
      const singleTenantId = String(tenantSelectOptions[0].value)
      if (form.tenant_id !== singleTenantId) {
        updateForm('tenant_id', singleTenantId)
      }
    }
  }, [form.building_id, tenantSelectOptions, form.tenant_id])

  const hasActiveFilters = Boolean(keyword.trim() || selectedType || selectedStatus)

  const clearFilters = () => {
    setKeyword('')
    setSelectedType('')
    setSelectedStatus('')
  }

  const updateForm = (key: keyof AdminVehicleFormValues, value: string | number | boolean) => {
    setForm((current) => ({ ...current, [key]: value }))
    setErrors((current) => ({ ...current, [key]: undefined }))
    setSuccessMessage(null)
  }

  const openCreateForm = () => {
    setEditingVehicle(null)
    setForm({ ...defaultForm })
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const resetForm = () => {
    if (editingVehicle) {
      const t = tenants.find((t) => t.id === editingVehicle.tenant_id)
      const bId = t ? String(t.building_id || '') : ''
      setForm({
        building_id: bId,
        tenant_id: String(editingVehicle.tenant_id),
        vehicle_type: editingVehicle.vehicle_type,
        license_plate: editingVehicle.license_plate || '',
        brand: editingVehicle.brand || '',
        color: editingVehicle.color || '',
        is_active: editingVehicle.is_active,
      })
    } else {
      setForm({ ...defaultForm })
    }
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
  }

  const openEditForm = (vehicle: AdminVehicleResource) => {
    const t = tenants.find((t) => t.id === vehicle.tenant_id)
    const bId = t ? String(t.building_id || '') : ''
    setEditingVehicle(vehicle)
    setForm({
      building_id: bId,
      tenant_id: String(vehicle.tenant_id),
      vehicle_type: vehicle.vehicle_type,
      license_plate: vehicle.license_plate || '',
      brand: vehicle.brand || '',
      color: vehicle.color || '',
      is_active: vehicle.is_active,
    })
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const viewVehicleDetail = async (vehicle: AdminVehicleResource) => {
    setDetailVehicle(vehicle)
    setIsDetailOpen(true)
    setIsDetailLoading(true)
    setDetailErrorMessage(null)
    try {
      const response = await fetchAdminVehicleDetail(vehicle.id)
      const data = response.result
      setDetailVehicle(data)
    } catch (error) {
      setDetailErrorMessage(getVisibleErrorMessage(error, 'Không thể tải chi tiết phương tiện.'))
    } finally {
      setIsDetailLoading(false)
    }
  }

  const closeVehicleDetail = () => {
    setIsDetailOpen(false)
    setDetailVehicle(null)
    setDetailErrorMessage(null)
  }

  useEffect(() => {
    if (!isDetailOpen) return
    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        closeVehicleDetail()
      }
    }
    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [isDetailOpen])

  const handleSubmit = async () => {
    if (isSaving) return

    const nextErrors = validateVehicleForm(form)
    setErrors(nextErrors)

    if (Object.keys(nextErrors).length > 0) {
      setErrorMessage('Vui lòng kiểm tra lại thông tin phương tiện.')
      return
    }

    try {
      setIsSaving(true)
      setErrorMessage(null)
      setSuccessMessage(null)

      const isPlateRequired = Number(form.vehicle_type) !== 2 && Number(form.vehicle_type) !== 4
      const payload = {
        tenant_id: Number(form.tenant_id),
        vehicle_type: Number(form.vehicle_type),
        license_plate: isPlateRequired ? (form.license_plate.trim() || null) : null,
        brand: form.brand.trim() || undefined,
        color: form.color.trim() || undefined,
        is_active: form.is_active,
      }

      if (editingVehicle) {
        await updateAdminVehicle(editingVehicle.id, payload)
        setSuccessMessage('Cập nhật phương tiện thành công.')
      } else {
        await createAdminVehicle(payload)
        setSuccessMessage('Tạo phương tiện thành công.')
      }

      resetForm()
      setIsFormOpen(false)
      await loadVehicles()
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể lưu thông tin phương tiện.'))
    } finally {
      setIsSaving(false)
    }
  }


  const handleDelete = async (vehicle: AdminVehicleResource) => {
    if (!window.confirm(`Bạn có chắc chắn muốn xóa phương tiện ${vehicle.license_plate}? Phương tiện đã liên kết với hợp đồng thuê sẽ không thể xóa.`)) return

    try {
      setErrorMessage(null)
      setSuccessMessage(null)
      await deleteAdminVehicle(vehicle.id)
      setSuccessMessage('Xóa phương tiện thành công.')
      await loadVehicles()
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể xóa phương tiện.'))
    }
  }

  const getVehicleIcon = (type: number) => {
    switch (type) {
      case 1:
        return <Fuel className="h-4 w-4" />
      case 2:
        return <Bike className="h-4 w-4" />
      case 3:
        return <Car className="h-4 w-4" />
      case 4:
        return <Zap className="h-4 w-4" />
      default:
        return <Car className="h-4 w-4" />
    }
  }

  return (
    <>
      <section className="space-y-5 sm:space-y-6 text-[#24170d]">
        <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
          <div className="relative p-5 text-[#fff4df] sm:p-6 lg:p-7">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_14%,rgba(243,197,107,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(15,118,110,0.26),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
            <div className="pointer-events-none absolute inset-x-6 bottom-0 h-px bg-gradient-to-r from-transparent via-[#f3c56b]/40 to-transparent" />

            <div className="relative flex min-w-0 flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
              <div className="min-w-0">
                <span className="block text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]/80">VẬN HÀNH</span>
                  <h1 className="mt-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem] flex items-center gap-3">
                    <Car className="h-8 w-8 text-[#f3c56b] shrink-0" />
                    Bãi xe & Phương tiện
                  </h1>
                <p className="mt-2.5 text-xs font-semibold text-[#f8e8c8]/70">Quản lý thông tin gửi xe, vé xe và phương tiện của khách thuê.</p>
              </div>
              <button type="button" onClick={openCreateForm} className="inline-flex h-9 w-fit self-end lg:self-auto items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition-all hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98]">
                <Plus className="h-4 w-4 stroke-[2.8]" /> Thêm phương tiện
              </button>
            </div>

            <div className="relative mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
              <MetricCard label="Tổng phương tiện" value={metrics.total} tone="neutral" />
              <MetricCard label="Xe máy" value={metrics.motorbikes} tone="amber" />
              <MetricCard label="Xe đạp" value={metrics.bicycles} tone="emerald" />
              <MetricCard label="Ô tô" value={metrics.cars} tone="rose" />
              <MetricCard label="Xe điện" value={metrics.electrics} tone="teal" />
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
              <div className="grid gap-3 sm:grid-cols-3">
                <div className="relative min-w-0">
                  <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
                  <input type="text" value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="Tìm biển số, khách thuê, hãng..." className={`${inputClass} pl-11 pr-24`} />
                  {hasActiveFilters && (
                    <button type="button" onClick={clearFilters} className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20">
                      Xóa lọc
                    </button>
                  )}
                </div>
                <AdminSelect value={selectedType} options={filterVehicleTypeOptions} onChange={(nextValue) => setSelectedType(String(nextValue))} />
                <AdminSelect value={selectedStatus} options={statusOptions} onChange={(nextValue) => setSelectedStatus(String(nextValue))} />
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full min-w-[920px] text-left">
                <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
                  <tr>
                    <th className="px-5 py-4">Hãng & Loại</th>
                    <th className="px-5 py-4 text-center">Biển số</th>
                    <th className="px-5 py-4 text-center">Khách thuê</th>
                    <th className="px-5 py-4 text-center">Trạng thái</th>
                    <th className="px-5 py-4 text-center">Ngày đăng ký</th>
                    <th className="px-5 py-4 w-[130px]"><div className="flex justify-end"><div className="w-[130px] text-center">Thao tác</div></div></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[#3d2a18]/8 bg-[#fffaf1]/70">
                  {isLoading && Array.from({ length: 5 }).map((_, index) => (
                    <tr key={index}>
                      <td colSpan={6} className="px-5 py-4"><div className="h-14 animate-pulse rounded-2xl bg-stone-100" /></td>
                    </tr>
                  ))}

                  {!isLoading && vehicles.map((vehicle) => (
                    <tr key={vehicle.id} className="group transition hover:bg-[#f3c56b]/10">
                      <td className="px-5 py-4">
                        <div className="flex items-center gap-3">
                          <div className={cn(
                            "flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border shadow-sm transition group-hover:-translate-y-0.5 group-hover:scale-105",
                            vehicle.vehicle_type === 1 && "border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#a65f16]",
                            vehicle.vehicle_type === 2 && "border-[#0f766e]/35 bg-[#0f766e]/16 text-[#0f5f59]",
                            vehicle.vehicle_type === 3 && "border-rose-200 bg-rose-50 text-rose-700",
                            vehicle.vehicle_type === 4 && "border-cyan-200 bg-cyan-50 text-cyan-700"
                          )}>
                            {getVehicleIcon(vehicle.vehicle_type)}
                          </div>
                          <div className="min-w-0">
                            <p className="truncate text-sm font-black tracking-tight text-[#24170d]">{vehicle.brand || 'Không rõ hãng'}</p>
                            <p className="mt-0.5 text-xs font-bold text-[#8b5e34]/70">
                              Loại: {vehicle.vehicle_type_label} {vehicle.color ? `• Màu: ${vehicle.color}` : ''}
                            </p>
                          </div>
                        </div>
                      </td>
                      <td className="px-5 py-4 text-center">
                        <span className="font-mono text-sm font-black tracking-wider text-[#24170d] bg-stone-100 border border-stone-200/60 px-2.5 py-1 rounded-lg shadow-sm">
                          {vehicle.license_plate}
                        </span>
                      </td>
                      <td className="px-5 py-4 text-center">
                        <p className="text-sm font-black text-[#3d2a18]">{vehicle.tenant_name || 'Hệ thống'}</p>
                      </td>
                      <td className="px-5 py-4 text-center">
                        <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-xs font-black shadow-sm', vehicle.is_active ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]' : 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]')}>
                          {vehicle.is_active_label || (vehicle.is_active ? 'Còn sử dụng' : 'Hết sử dụng')}
                        </span>
                      </td>
                      <td className="px-5 py-4 text-center">
                        <span className="text-xs font-bold text-[#6f6254]">{formatDateTime(vehicle.created_at)}</span>
                      </td>
                      <td className="px-5 py-4">
                        <div className="flex items-center justify-end gap-2">
                          <button type="button" onClick={() => void viewVehicleDetail(vehicle)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95" title="Xem chi tiết" aria-label={`Xem chi tiết xe ${vehicle.license_plate}`}><Eye className="h-5 w-5" /></button>
                          <button type="button" onClick={() => openEditForm(vehicle)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#3d2a18]/10 active:scale-95" title="Chỉnh sửa" aria-label={`Chỉnh sửa xe ${vehicle.license_plate}`}><Edit3 className="h-5 w-5" /></button>
                          <button type="button" onClick={() => void handleDelete(vehicle)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-95" title="Xóa" aria-label={`Xóa xe ${vehicle.license_plate}`}><Trash2 className="h-5 w-5" /></button>
                        </div>
                      </td>
                    </tr>
                  ))}

                  {!isLoading && vehicles.length === 0 && (
                    <tr>
                      <td colSpan={6} className="px-5 py-20 text-center">
                        <div className="mx-auto flex max-w-sm flex-col items-center rounded-[2rem] border border-dashed border-[#3d2a18]/12 bg-white/55 px-6 py-8">
                          <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]"><Car className="h-9 w-9" /></div>
                          <p className="text-lg font-black tracking-tight text-[#24170d]">Không tìm thấy phương tiện</p>
                          <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">{hasActiveFilters ? 'Thử xóa bộ lọc hoặc đổi từ khóa tìm kiếm.' : 'Hãy đăng ký phương tiện mới hoặc kiểm tra lại dữ liệu hiện tại.'}</p>
                          {hasActiveFilters && (
                            <button type="button" onClick={clearFilters} className="mt-4 inline-flex h-10 items-center justify-center rounded-2xl bg-[#24170d] px-4 text-xs font-black text-[#fff4df] transition hover:bg-[#3d2a18]">
                              Xóa bộ lọc
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </section>

        </div>

        {isFormOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <button type="button" onClick={() => { resetForm(); setIsFormOpen(false) }} className="absolute inset-0 bg-stone-950/60 backdrop-blur-sm" />
            <div className="relative z-10 w-full max-w-2xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-stone-950/30">
              <div className="bg-[#24170d] p-5 text-[#fff4df]">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <h2 className="mt-1 text-xl font-black tracking-tight">{editingVehicle ? 'Cập nhật xe' : 'Đăng ký xe'}</h2>
                  </div>
                  <div className="flex items-center gap-2">
                    <button type="button" onClick={resetForm} className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20" title="Làm mới form">
                      <RefreshCw className="h-4 w-4" />
                    </button>
                    <button type="button" onClick={() => { resetForm(); setIsFormOpen(false) }} className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20" title="Đóng form">
                      <X className="h-4 w-4" />
                    </button>
                  </div>
                </div>
              </div>

              <div className="max-h-[70vh] overflow-y-auto p-5 space-y-4">
                {successMessage && <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-700">{successMessage}</div>}
                {errorMessage && <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700">{errorMessage}</div>}

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className={labelClass}>Tòa nhà</label>
                    <AdminSelect
                      value={form.building_id}
                      options={[{ value: '', label: 'Chọn tòa nhà', tone: 'default' }, ...buildings.map((b) => ({ value: b.id, label: b.name }))]}
                      invalid={!!errors.building_id}
                      onChange={(nextValue) => {
                        updateForm('building_id', String(nextValue))
                        updateForm('tenant_id', '')
                      }}
                    />
                    <FieldError message={errors.building_id} />
                  </div>

                  <div>
                    <label className={labelClass}>Khách thuê sở hữu</label>
                    <AdminSelect
                      value={form.tenant_id}
                      options={[{ value: '', label: 'Chọn khách thuê', tone: 'default' }, ...tenantSelectOptions]}
                      disabled={!form.building_id}
                      invalid={!!errors.tenant_id}
                      placeholder={form.building_id ? (tenantSelectOptions.length > 0 ? "Chọn khách thuê" : "Không có khách thuê nào") : "Vui lòng chọn tòa nhà trước"}
                      onChange={(nextValue) => updateForm('tenant_id', String(nextValue))}
                    />
                    <FieldError message={errors.tenant_id} />
                  </div>
                  <div>
                    <label className={labelClass}>Loại xe</label>
                    <AdminSelect
                      value={form.vehicle_type}
                      options={vehicleTypeOptions.map((o) => ({ value: o.value, label: o.label, tone: 'default' as const }))}
                      invalid={!!errors.vehicle_type}
                      onChange={(nextValue) => updateForm('vehicle_type', Number(nextValue))}
                    />
                    <FieldError message={errors.vehicle_type} />
                  </div>
                  <div>
                    <label className={labelClass}>
                      Biển số xe {(Number(form.vehicle_type) !== 2 && Number(form.vehicle_type) !== 4) && <span className="text-rose-500">*</span>}
                    </label>
                    <input className={cn(inputClass, errors.license_plate && inputErrorClass)} value={form.license_plate} onChange={(event) => updateForm('license_plate', event.target.value)} placeholder="Ví dụ: 59A-123.45" />
                    <FieldError message={errors.license_plate} />
                  </div>
                  <div>
                    <label className={labelClass}>Hãng xe / Thương hiệu</label>
                    <input className={cn(inputClass, errors.brand && inputErrorClass)} value={form.brand} onChange={(event) => updateForm('brand', event.target.value)} placeholder="Ví dụ: Honda, Yamaha, Toyota" />
                    <FieldError message={errors.brand} />
                  </div>
                  <div>
                    <label className={labelClass}>Màu xe</label>
                    <input className={cn(inputClass, errors.color && inputErrorClass)} value={form.color} onChange={(event) => updateForm('color', event.target.value)} placeholder="Ví dụ: Đỏ đen, Trắng, Xanh lá" />
                    <FieldError message={errors.color} />
                  </div>
                  <div className="md:col-span-2">
                    <label className={labelClass}>Trạng thái hoạt động</label>
                    <AdminSelect
                      value={form.is_active ? 1 : 0}
                      options={formStatusOptions}
                      invalid={!!errors.is_active}
                      onChange={(nextValue) => updateForm('is_active', Number(nextValue) === 1)}
                    />
                    <FieldError message={errors.is_active} />
                  </div>
                </div>

                <div className="flex justify-end gap-3 pt-4 border-t border-[#3d2a18]/10">
                  <button type="button" onClick={() => { resetForm(); setIsFormOpen(false) }} className="inline-flex h-12 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-5 text-sm font-black text-[#8b5e34] hover:bg-stone-100 transition">
                    Hủy
                  </button>
                  <button type="button" disabled={isSaving} onClick={() => void handleSubmit()} className="inline-flex h-12 min-w-32 items-center justify-center rounded-[1.25rem] bg-[#24170d] px-5 text-sm font-black text-[#fff4df] shadow-lg shadow-[#24170d]/12 transition hover:bg-[#3d2a18]">
                    {isSaving ? 'Đang lưu...' : editingVehicle ? 'Cập nhật' : 'Đăng ký xe'}
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}
      </section>

      {isDetailOpen && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="vehicle-detail-title">
          <button type="button" onClick={closeVehicleDetail} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />
          <div className="relative z-10 w-full max-w-3xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-stone-950/30">
            <div className="bg-[#24170d] p-5 text-[#fff4df]">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-[10px] font-black uppercase tracking-[0.24em] text-[#f3c56b]">Vehicle Details</p>
                  <h2 id="vehicle-detail-title" className="mt-2 text-2xl font-black tracking-tight">{detailVehicle?.license_plate || 'Đang tải chi tiết...'}</h2>
                  <p className="mt-1 text-xs font-black uppercase tracking-[0.18em] text-[#f8e8c8]/72">
                    Hãng: {detailVehicle?.brand || 'Chưa cập nhật'} • Loại: {detailVehicle?.vehicle_type_label}
                  </p>
                </div>
                <button type="button" onClick={closeVehicleDetail} className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20">
                  <X className="h-5 w-5" />
                </button>
              </div>
            </div>

            <div className="space-y-4 p-5">
              {isDetailLoading && <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-800">Đang tải chi tiết phương tiện...</div>}
              {detailErrorMessage && <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{detailErrorMessage}</div>}

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <DetailTile label="Trạng thái" value={detailVehicle?.is_active_label || (detailVehicle?.is_active ? 'Còn sử dụng' : 'Hết sử dụng')} />
                <DetailTile label="Biển số" value={detailVehicle?.license_plate || 'Không có'} />
                <DetailTile label="Màu xe" value={detailVehicle?.color || 'Chưa cập nhật'} />
              </div>

              <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4 space-y-3">
                <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Chủ sở hữu (Khách thuê)</p>
                <div className="space-y-1">
                  <p className="text-base font-black text-[#24170d]">{detailVehicle?.tenant?.full_name || detailVehicle?.tenant_name || 'Không có thông tin'}</p>
                  {detailVehicle?.tenant && (
                    <div className="text-sm font-semibold text-[#6f6254] space-y-0.5">
                      <p>SĐT: {detailVehicle.tenant.phone || 'Chưa cập nhật'}</p>
                      <p>Email: {detailVehicle.tenant.email || 'Chưa cập nhật'}</p>
                    </div>
                  )}
                </div>
              </section>

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <DetailTile label="Ngày tạo" value={formatDateTime(detailVehicle?.created_at)} />
                <DetailTile label="Ngày cập nhật" value={formatDateTime(detailVehicle?.updated_at)} />
              </div>
            </div>
          </div>
        </div>
      )}
    </>
  )
}

function MetricCard({ label, value, tone }: { label: string; value: number; tone: 'neutral' | 'emerald' | 'amber' | 'teal' | 'rose' }) {
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
    </div>
  )
}

function DetailTile({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-2xl border border-[#3d2a18]/10 bg-white/70 p-4">
      <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">{label}</p>
      <p className="mt-2 text-lg font-black text-[#24170d] tabular-nums">{value}</p>
    </div>
  )
}

function FieldError({ message }: { message?: string }) {
  if (!message) return null
  return <p className="mt-2 px-1 text-xs font-black text-rose-600" role="alert">{message}</p>
}
