import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeft, BedDouble, Edit3, Eye, Plus, Power, RefreshCw, Search, Trash2, X } from 'lucide-react'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { cn } from '../../../../shared/lib/utils/cn'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import { AdminSelect } from '../../shared/components/AdminSelect'
import {
  createAdminRoomType,
  deleteAdminRoomType,
  fetchAdminRoomTypeDetail,
  fetchAdminRoomTypes,
  updateAdminRoomType,
  updateAdminRoomTypeStatus,
} from '../services/room-types.service'
import type { AdminRoomTypeResource } from '../types/room-type-api.model'
import { validateRoomTypeForm, type RoomTypeFormErrors, type RoomTypeFormValues } from '../validations/room-type.validation'

function getResourceList<T>(result: { data?: T[] } | T[] | null | undefined): T[] {
  if (!result) return []
  if (Array.isArray(result)) return result
  return result.data || []
}

const defaultForm: RoomTypeFormValues = {
  name: '',
  building_id: '',
  default_price: '',
  description: '',
  status: 1,
}

const statusLabels: Record<number, string> = {
  1: 'Hoạt động',
  2: 'Ngừng hoạt động',
}

const statusOptions = [
  { value: '', label: 'Tất cả trạng thái', tone: 'default' as const },
  { value: '1', label: 'Hoạt động', tone: 'success' as const },
  { value: '2', label: 'Ngừng hoạt động', tone: 'danger' as const },
]

const formStatusOptions = [
  { value: 1, label: 'Hoạt động', tone: 'success' as const },
  { value: 2, label: 'Ngừng hoạt động', tone: 'danger' as const },
]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'
const inputErrorClass = 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100'
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65'

export function RoomTypesScreen() {
  const { session } = useAdminSession()
  const isSuperAdmin = isSuperAdminRole(session?.admin.role)
  const [keyword, setKeyword] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [selectedBuildingId, setSelectedBuildingId] = useState('')
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [roomTypes, setRoomTypes] = useState<AdminRoomTypeResource[]>([])
  const [editingRoomType, setEditingRoomType] = useState<AdminRoomTypeResource | null>(null)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [form, setForm] = useState<RoomTypeFormValues>(defaultForm)
  const [errors, setErrors] = useState<RoomTypeFormErrors>({})
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [detailRoomType, setDetailRoomType] = useState<AdminRoomTypeResource | null>(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [statusChangingId, setStatusChangingId] = useState<number | null>(null)

  const buildingOptions = useMemo(() => buildings.map((building) => ({ value: building.id, label: building.name, tone: 'default' as const })), [buildings])
  const filterBuildingOptions = useMemo(() => [{ value: '', label: 'Tất cả tòa nhà', tone: 'default' as const }, ...buildingOptions], [buildingOptions])
  const formBuildingOptions = useMemo(() => isSuperAdmin ? [{ value: '', label: 'Dùng chung toàn hệ thống', tone: 'warning' as const }, ...buildingOptions] : buildingOptions, [buildingOptions, isSuperAdmin])
  const allowedBuildingIds = useMemo(() => buildings.map((building) => building.id), [buildings])

  const loadRoomTypes = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)

    try {
      const [roomTypeResponse, buildingResponse] = await Promise.all([
        fetchAdminRoomTypes({
          keyword: keyword.trim() || undefined,
          building_id: selectedBuildingId ? Number(selectedBuildingId) : undefined,
          status: selectedStatus ? Number(selectedStatus) : undefined,
          per_page: 100,
        }),
        fetchAdminBuildings({ per_page: 100 }),
      ])

      const buildingList = getResourceList(buildingResponse.result)
      const visibleBuildings = isSuperAdmin
        ? buildingList
        : buildingList.filter((building) => Number(building.manager_admin_id) === Number(session?.admin.id))
      const nextAllowedBuildingIds = new Set(visibleBuildings.map((building) => Number(building.id)))
      const nextRoomTypes = getResourceList(roomTypeResponse.result).filter((roomType) => {
        if (isSuperAdmin || !roomType.building_id) return true
        return nextAllowedBuildingIds.has(Number(roomType.building_id))
      })

      setRoomTypes(nextRoomTypes)
      setBuildings(visibleBuildings)

      if (!isSuperAdmin && visibleBuildings.length === 1) {
        setForm((current) => ({ ...current, building_id: current.building_id || String(visibleBuildings[0].id) }))
      }
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể tải danh sách loại phòng.')
    } finally {
      setIsLoading(false)
    }
  }, [isSuperAdmin, keyword, selectedBuildingId, selectedStatus, session?.admin.id])


  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadRoomTypes()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [loadRoomTypes])

  const activeRoomTypes = useMemo(() => roomTypes.filter((item) => Number(item.status) === 1).length, [roomTypes])
  const totalRooms = useMemo(() => roomTypes.reduce((sum, item) => sum + Number(item.rooms_count || 0), 0), [roomTypes])
  const updateForm = (key: keyof RoomTypeFormValues, value: string | number) => {
    setForm((current) => ({ ...current, [key]: value }))
    setErrors((current) => ({ ...current, [key]: undefined }))
    setSuccessMessage(null)
  }

  const openCreateForm = () => {
    setEditingRoomType(null)
    setForm({ ...defaultForm, building_id: !isSuperAdmin && buildings.length === 1 ? String(buildings[0].id) : selectedBuildingId })
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const resetForm = () => {
    setEditingRoomType(null)
    setForm({ ...defaultForm, building_id: !isSuperAdmin && buildings.length === 1 ? String(buildings[0].id) : '' })
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
  }

  const editRoomType = (roomType: AdminRoomTypeResource) => {
    setEditingRoomType(roomType)
    setForm({
      name: roomType.name || '',
      building_id: roomType.building_id ? String(roomType.building_id) : '',
      default_price: String(Number(roomType.default_price || 0)),
      description: roomType.description || '',
      status: Number(roomType.status || 1),
    })
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const viewRoomType = async (roomType: AdminRoomTypeResource) => {
    setDetailRoomType(roomType)
    setIsDetailOpen(true)
    setIsDetailLoading(true)
    setDetailErrorMessage(null)

    try {
      const response = await fetchAdminRoomTypeDetail(roomType.id)
      setDetailRoomType(response.result)
    } catch (error) {
      setDetailErrorMessage(error instanceof Error ? error.message : 'Không thể tải chi tiết loại phòng.')
    } finally {
      setIsDetailLoading(false)
    }
  }

  const closeRoomTypeDetail = () => {
    setIsDetailOpen(false)
    setDetailRoomType(null)
    setDetailErrorMessage(null)
  }

  const submit = async () => {
    if (isSaving) return

    const nextErrors = validateRoomTypeForm(form, { allowedBuildingIds, requireBuilding: !isSuperAdmin })
    setErrors(nextErrors)

    if (Object.keys(nextErrors).length > 0) {
      setErrorMessage('Vui lòng kiểm tra lại thông tin loại phòng.')
      return
    }

    try {
      setIsSaving(true)
      setErrorMessage(null)
      setSuccessMessage(null)

      const payload = {
        name: form.name.trim(),
        building_id: form.building_id ? Number(form.building_id) : undefined,
        default_price: Number(form.default_price),
        description: form.description.trim() || undefined,
        status: Number(form.status),
      }

      if (editingRoomType) {
        await updateAdminRoomType(editingRoomType.id, payload)
        setSuccessMessage('Cập nhật loại phòng thành công.')
      } else {
        await createAdminRoomType(payload)
        setSuccessMessage('Tạo loại phòng thành công.')
      }

      resetForm()
      setIsFormOpen(false)
      await loadRoomTypes()
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể lưu loại phòng.')
    } finally {
      setIsSaving(false)
    }
  }

  const toggleRoomTypeStatus = async (roomType: AdminRoomTypeResource) => {
    const nextStatus = Number(roomType.status) === 1 ? 2 : 1

    if (nextStatus === 2 && !window.confirm(`Bạn có chắc muốn tắt hoạt động loại phòng ${roomType.name}?`)) return

    try {
      setStatusChangingId(roomType.id)
      setErrorMessage(null)
      setSuccessMessage(null)
      await updateAdminRoomTypeStatus(roomType.id, nextStatus)
      setSuccessMessage(`${nextStatus === 1 ? 'Kích hoạt' : 'Ngừng hoạt động'} loại phòng thành công.`)
      await loadRoomTypes()
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể đổi trạng thái loại phòng.')
    } finally {
      setStatusChangingId(null)
    }
  }

  const removeRoomType = async (roomType: AdminRoomTypeResource) => {
    if (!window.confirm(`Bạn có chắc chắn muốn xóa loại phòng ${roomType.name}?`)) return

    try {
      setErrorMessage(null)
      await deleteAdminRoomType(roomType.id)
      setSuccessMessage('Xóa loại phòng thành công.')
      await loadRoomTypes()
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể xóa loại phòng.')
    }
  }

  const clearFilters = () => {
    setKeyword('')
    setSelectedBuildingId('')
    setSelectedStatus('')
  }

  return (
    <div className="relative min-w-0 overflow-hidden rounded-[2rem] bg-[#f4efe6] text-[#24170d] shadow-inner shadow-white/70">
      <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,rgba(77,51,25,0.08)_1px,transparent_1px),linear-gradient(to_bottom,rgba(77,51,25,0.08)_1px,transparent_1px)] bg-[size:36px_36px]" />
      <div className="pointer-events-none absolute -right-28 -top-32 h-80 w-80 rounded-full bg-[#f3c56b]/28 blur-3xl" />
      <div className="pointer-events-none absolute bottom-20 left-10 h-64 w-64 rounded-full bg-[#0f766e]/10 blur-3xl" />

      <div className="relative space-y-5 p-4 sm:space-y-6 sm:p-6">
        <div className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
          <div className="relative p-3 text-[#fff4df] sm:p-4">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(243,197,107,0.24),transparent_30%),radial-gradient(circle_at_82%_16%,rgba(15,118,110,0.22),transparent_32%),linear-gradient(135deg,#24170d_0%,#3d2a18_54%,#0f3f3b_100%)]" />
            <div className="relative flex min-w-0 flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
              <div className="min-w-0">
                <Link to="/admin/dashboard" className="mb-1 inline-flex items-center gap-2 text-xs font-black text-[#f3c56b] transition hover:text-[#ffd56f]">
                  <ArrowLeft className="h-3.5 w-3.5" /> Về dashboard
                </Link>
                <div className="flex min-w-0 flex-wrap items-center gap-2 text-[11px] font-black uppercase tracking-[0.24em] text-[#f3c56b]">
                </div>
                <h1 className="max-w-3xl text-2xl font-black tracking-[-0.04em] text-[#fff4df] sm:text-[1.7rem] lg:text-3xl">Loại phòng</h1>
              </div>
              <button type="button" onClick={openCreateForm} className="inline-flex h-9 items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition-all hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98]">
                <Plus className="h-4 w-4 stroke-[2.8]" /> Thêm loại phòng
              </button>
            </div>

            <div className="relative mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
              <MetricCard label="Tổng loại" value={roomTypes.length} tone="neutral" />
              <MetricCard label="Hoạt động" value={activeRoomTypes} tone="emerald" />
              <MetricCard label="Số lượng phòng đã áp dụng" value={totalRooms} tone="amber" />
            </div>
          </div>
        </div>

        {(errorMessage || successMessage) && (
          <div className={cn('rounded-3xl border p-4 text-sm font-black', errorMessage ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700')}>
            {errorMessage || successMessage}
          </div>
        )}

        <div className={cn('grid min-w-0 grid-cols-1 gap-4 xl:gap-6', isFormOpen && '2xl:grid-cols-[minmax(0,1fr)_360px]')}>
          <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/88 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
            <div className="border-b border-[#3d2a18]/10 bg-[#fff7e8]/72 p-4 sm:p-5">
              <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div className="relative min-w-0 flex-1">
                  <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
                  <input type="text" value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="Tìm tên hoặc mô tả loại phòng..." className={`${inputClass} pl-11 pr-28`} />
                  <button type="button" onClick={clearFilters} className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20">
                    <X className="h-3.5 w-3.5" /> Xóa lọc
                  </button>
                </div>
                <div className="grid w-full gap-3 sm:grid-cols-2 lg:ml-auto lg:w-lg">
                  <AdminSelect value={selectedBuildingId} options={filterBuildingOptions} onChange={(nextValue) => setSelectedBuildingId(String(nextValue))} />
                  <AdminSelect value={selectedStatus} options={statusOptions} onChange={(nextValue) => setSelectedStatus(String(nextValue))} />
                </div>
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="min-w-[880px] w-full text-left">
                <thead className="bg-[#24170d] text-[11px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
                  <tr>
                    <th className="px-5 py-4">Loại phòng</th>
                    <th className="px-5 py-4">Tòa nhà</th>
                    <th className="px-5 py-4">Giá mặc định</th>
                    <th className="px-5 py-4 text-center">Số lượng phòng đang áp dụng</th>
                    <th className="px-5 py-4">Trạng thái</th>
                    <th className="px-5 py-4"><span className="flex justify-end"><span className="w-47.5 text-center">Thao tác</span></span></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[#3d2a18]/8">
                  {isLoading && Array.from({ length: 5 }).map((_, index) => (
                    <tr key={index}>
                      <td colSpan={6} className="px-5 py-4"><div className="h-12 animate-pulse rounded-2xl bg-stone-100" /></td>
                    </tr>
                  ))}

                  {!isLoading && roomTypes.map((roomType) => {
                    const canMutateRoomType = isSuperAdmin || Boolean(roomType.building_id)

                    return (
                    <tr key={roomType.id} className="group transition hover:bg-[#f3c56b]/12">
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2.5">
                          <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#a65f16] shadow-sm transition group-hover:scale-105">
                            <BedDouble className="h-4 w-4" />
                          </div>
                          <div className="min-w-0">
                            <p className="truncate text-[13px] font-black tracking-tight text-[#24170d]">{roomType.name}</p>
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-[13px] font-bold text-[#6f6254]">{roomType.building_name || 'Dùng chung'}</td>
                      <td className="px-4 py-3 text-[13px] font-black text-[#24170d] tabular-nums">{formatCurrency(roomType.default_price)}</td>
                      <td className="px-4 py-3 text-center text-[13px] font-black text-[#24170d] tabular-nums">{roomType.rooms_count ?? 0}</td>
                      <td className="px-4 py-3">
                        <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-[11px] font-black shadow-sm', Number(roomType.status) === 1 ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]' : 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]')}>
                          {roomType.status_label || statusLabels[Number(roomType.status)]}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center justify-end gap-2.5">
                          <button type="button" onClick={() => void viewRoomType(roomType)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95" title="Xem chi tiết"><Eye className="h-5 w-5" /></button>
                          <button type="button" disabled={!canMutateRoomType} onClick={() => editRoomType(roomType)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#3d2a18]/10 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45" title={canMutateRoomType ? 'Chỉnh sửa' : 'Chỉ superadmin được sửa loại phòng dùng chung'}><Edit3 className="h-5 w-5" /></button>
                          <button type="button" disabled={statusChangingId === roomType.id || !canMutateRoomType} onClick={() => void toggleRoomTypeStatus(roomType)} className={cn('inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition focus:outline-none focus:ring-4 active:scale-95 disabled:cursor-not-allowed disabled:opacity-55', Number(roomType.status) === 1 ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 focus:ring-emerald-100' : 'border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 focus:ring-rose-100')} title={canMutateRoomType ? (Number(roomType.status) === 1 ? 'Ngừng hoạt động' : 'Kích hoạt') : 'Chỉ superadmin được đổi trạng thái loại phòng dùng chung'}><Power className="h-5 w-5" /></button>
                          <button type="button" disabled={!canMutateRoomType} onClick={() => void removeRoomType(roomType)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45" title={canMutateRoomType ? 'Xóa' : 'Chỉ superadmin được xóa loại phòng dùng chung'}><Trash2 className="h-5 w-5" /></button>
                        </div>
                      </td>
                    </tr>
                    )
                  })}

                  {!isLoading && roomTypes.length === 0 && (
                    <tr>
                      <td colSpan={6} className="px-5 py-20 text-center">
                        <div className="mx-auto flex max-w-sm flex-col items-center">
                          <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]"><BedDouble className="h-9 w-9" /></div>
                          <p className="text-lg font-black tracking-tight text-[#24170d]">Không tìm thấy loại phòng</p>
                          <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">Hãy tạo loại phòng mới hoặc đổi bộ lọc hiện tại.</p>
                        </div>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </section>

          {isFormOpen && (
            <aside className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/88 p-5 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
            <div className="mb-5 flex items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-black tracking-tight text-[#24170d]">{editingRoomType ? 'Cập nhật loại phòng' : 'Thêm loại phòng'}</h2>
                <p className="text-xs font-bold text-[#8b5e34]/60">Khai báo giá mặc định cho loại phòng.</p>
              </div>
              <div className="flex items-center gap-2">
                <button type="button" onClick={resetForm} className="rounded-xl border border-[#3d2a18]/10 p-2 text-[#8b5e34] transition hover:bg-[#f3c56b]/15" title="Làm mới form">
                  <RefreshCw className="h-4 w-4" />
                </button>
                <button type="button" onClick={() => { resetForm(); setIsFormOpen(false) }} className="rounded-xl border border-[#3d2a18]/10 p-2 text-[#8b5e34] transition hover:bg-rose-50 hover:text-rose-600" title="Đóng form">
                  <X className="h-4 w-4" />
                </button>
              </div>
            </div>

            <div className="space-y-4">
              <div>
                <label className={labelClass}>Tên loại phòng</label>
                <input className={`${inputClass} ${errors.name ? inputErrorClass : ''}`} value={form.name} onChange={(event) => updateForm('name', event.target.value)} placeholder="Ví dụ: Studio cao cấp" />
                <FieldError message={errors.name} />
              </div>
              <div>
                <label className={labelClass}>Tòa nhà</label>
                <AdminSelect value={form.building_id} options={formBuildingOptions} invalid={!!errors.building_id} onChange={(nextValue) => updateForm('building_id', String(nextValue))} />
                <FieldError message={errors.building_id} />
              </div>
              <div>
                <label className={labelClass}>Giá mặc định</label>
                <input className={`${inputClass} ${errors.default_price ? inputErrorClass : ''}`} value={form.default_price} onChange={(event) => updateForm('default_price', event.target.value)} inputMode="numeric" placeholder="Ví dụ: 4500000" />
                <FieldError message={errors.default_price} />
              </div>
              <div>
                <label className={labelClass}>Trạng thái</label>
                <AdminSelect value={form.status} options={formStatusOptions} invalid={!!errors.status} onChange={(nextValue) => updateForm('status', Number(nextValue))} />
                <FieldError message={errors.status} />
              </div>
              <div>
                <label className={labelClass}>Mô tả</label>
                <textarea className={`${inputClass} min-h-24 ${errors.description ? inputErrorClass : ''}`} value={form.description} onChange={(event) => updateForm('description', event.target.value)} placeholder="Ghi chú tiêu chuẩn diện tích, nội thất hoặc đối tượng phù hợp..." />
                <FieldError message={errors.description} />
              </div>

            

              <div className="flex flex-col gap-3 pt-2 sm:flex-row 2xl:flex-col">
                <button type="button" disabled={isSaving} onClick={() => void submit()} className="inline-flex min-h-14 flex-1 items-center justify-center gap-2 rounded-[1.25rem] bg-[#24170d] px-5 py-3.5 text-base font-black text-[#fff4df] shadow-lg shadow-[#24170d]/12 transition hover:bg-[#3d2a18] disabled:cursor-not-allowed disabled:opacity-60">
                  {isSaving ? 'Đang lưu...' : editingRoomType ? 'Cập nhật' : 'Tạo loại phòng'}
                </button>
              
              </div>
            </div>
            </aside>
          )}
        </div>
      </div>

      {isDetailOpen && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
          <button type="button" aria-label="Đóng chi tiết loại phòng" onClick={closeRoomTypeDetail} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />
          <div className="relative z-10 w-full max-w-2xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-stone-950/30">
            <div className="bg-[#24170d] p-5 text-[#fff4df]">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-[10px] font-black uppercase tracking-[0.24em] text-[#f3c56b]">Room type detail</p>
                  <h2 className="mt-2 text-2xl font-black tracking-tight">{detailRoomType?.name || 'Đang tải chi tiết...'}</h2>
                </div>
                <button type="button" onClick={closeRoomTypeDetail} className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20">
                  <X className="h-5 w-5" />
                </button>
              </div>
            </div>

            <div className="space-y-4 p-5">
              {isDetailLoading && <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-800">Đang tải chi tiết loại phòng...</div>}
              {detailErrorMessage && <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{detailErrorMessage}</div>}

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <DetailTile label="Tòa nhà" value={detailRoomType?.building_name || 'Dùng chung'} />
                <DetailTile label="Giá mặc định" value={formatCurrency(detailRoomType?.default_price)} />
                <DetailTile label="Số phòng" value={detailRoomType?.rooms_count ?? 0} />
              </div>

              <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
                <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Mô tả</p>
                <p className="mt-2 text-sm font-semibold leading-6 text-[#3d2a18]">{detailRoomType?.description || 'Chưa có mô tả.'}</p>
              </section>

              <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
                <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Phòng đang dùng</p>
                <div className="mt-3 max-h-52 space-y-2 overflow-y-auto pr-1">
                  {detailRoomType?.rooms?.map((room) => (
                    <div key={room.id} className="flex items-center justify-between gap-3 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-3 py-2 text-sm font-bold text-[#24170d]">
                      <span>{room.room_number}</span>
                      <span className="text-xs text-[#8b5e34]">{room.building_name || 'Chưa có tòa nhà'}</span>
                    </div>
                  ))}
                  {(!detailRoomType?.rooms || detailRoomType.rooms.length === 0) && <p className="text-sm font-semibold text-[#6f6254]">Chưa có phòng nào dùng loại phòng này.</p>}
                </div>
              </section>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

function MetricCard({ label, value, tone }: { label: string; value: number; tone: 'neutral' | 'emerald' | 'amber' }) {
  const toneClassNames = {
    neutral: 'border-[#f8e8c8]/12 bg-[#f8e8c8]/10 text-[#fff4df]',
    emerald: 'border-[#0f766e]/35 bg-[#0f766e]/16 text-[#c8fff4]',
    amber: 'border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#fff4df]',
  }[tone]

  return (
    <div className={cn('rounded-2xl border px-3 py-2.5 backdrop-blur', toneClassNames)}>
      <p className="text-[10px] font-black uppercase tracking-[0.18em] opacity-60">{label}</p>
      <p className="mt-0.5 text-2xl font-black tracking-tight tabular-nums">{value}</p>
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

function formatCurrency(value: number | string | null | undefined) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value || 0))
}
