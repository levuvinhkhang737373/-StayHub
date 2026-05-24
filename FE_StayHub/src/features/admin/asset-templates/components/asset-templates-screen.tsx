import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeft, Boxes, Edit3, Eye, Plus, Power, RefreshCw, Search, Trash2, X } from 'lucide-react'
import { cn } from '../../../../shared/lib/utils/cn'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { AdminSelect } from '../../shared/components/AdminSelect'
import {
  createAdminAssetTemplate,
  deleteAdminAssetTemplate,
  fetchAdminAssetTemplateDetail,
  fetchAdminAssetTemplates,
  updateAdminAssetTemplate,
  updateAdminAssetTemplateStatus,
} from '../services/asset-templates.service'
import type { AdminAssetTemplateResource } from '../types/asset-template-api.model'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import { validateAssetTemplateForm, type AssetTemplateFormErrors, type AssetTemplateFormValues } from '../validations/asset-template.validation'

function getResourceList<T>(result: { data?: T[] } | T[] | null | undefined): T[] {
  if (!result) return []
  if (Array.isArray(result)) return result
  return result.data || []
}

const defaultForm: AssetTemplateFormValues = {
  building_id: '',
  name: '',
  default_unit_name: 1,
  description: '',
  status: 1,
}

const unitLabels: Record<number, string> = {
  1: 'cái',
  2: 'bộ',
  3: 'chiếc',
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

const unitOptions = [
  { value: 1, label: 'cái', tone: 'default' as const },
  { value: 2, label: 'bộ', tone: 'default' as const },
  { value: 3, label: 'chiếc', tone: 'default' as const },
]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'
const inputErrorClass = 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100'
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65'

export function AssetTemplatesScreen() {
  const { session } = useAdminSession()
  const isSuperAdmin = isSuperAdminRole(session?.admin.role)
  const [keyword, setKeyword] = useState('')
  const [selectedBuildingId, setSelectedBuildingId] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [assetTemplates, setAssetTemplates] = useState<AdminAssetTemplateResource[]>([])
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [editingAssetTemplate, setEditingAssetTemplate] = useState<AdminAssetTemplateResource | null>(null)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [form, setForm] = useState<AssetTemplateFormValues>(defaultForm)
  const [errors, setErrors] = useState<AssetTemplateFormErrors>({})
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [detailAssetTemplate, setDetailAssetTemplate] = useState<AdminAssetTemplateResource | null>(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [statusChangingId, setStatusChangingId] = useState<number | null>(null)

  const loadAssetTemplates = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)

    try {
      const [assetTemplateResponse, buildingResponse] = await Promise.all([
        fetchAdminAssetTemplates({
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
      const allowedBuildingIds = new Set(visibleBuildings.map((building) => Number(building.id)))
      const nextAssetTemplates = getResourceList(assetTemplateResponse.result).filter((assetTemplate) => {
        if (isSuperAdmin || !assetTemplate.building_id) return true
        return allowedBuildingIds.has(Number(assetTemplate.building_id))
      })

      setAssetTemplates(nextAssetTemplates)
      setBuildings(visibleBuildings)

      if (!isSuperAdmin && buildingList.length === 1) {
        setForm((current) => ({ ...current, building_id: current.building_id || String(buildingList[0].id) }))
      }
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể tải danh sách mẫu tài sản.')
    } finally {
      setIsLoading(false)
    }
  }, [isSuperAdmin, keyword, selectedBuildingId, selectedStatus])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadAssetTemplates()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [loadAssetTemplates])

  const activeAssetTemplates = useMemo(() => assetTemplates.filter((item) => Number(item.status) === 1).length, [assetTemplates])
  const globalAssetTemplates = useMemo(() => assetTemplates.filter((item) => !item.building_id).length, [assetTemplates])
  const managedAssetTemplates = useMemo(() => assetTemplates.filter((item) => item.building_id).length, [assetTemplates])
  const buildingItems = useMemo(() => buildings.map((building) => ({ value: building.id, label: building.name, tone: 'default' as const })), [buildings])
  const buildingOptions = useMemo(() => [
    { value: '', label: 'Tất cả tòa nhà', tone: 'default' as const },
    ...buildingItems,
  ], [buildingItems])
  const formBuildingOptions = useMemo(() => isSuperAdmin ? [
    { value: '', label: 'Dùng chung toàn hệ thống', tone: 'warning' as const },
    ...buildingItems,
  ] : buildingItems, [buildingItems, isSuperAdmin])

  const updateForm = (key: keyof AssetTemplateFormValues, value: string | number) => {
    setForm((current) => ({ ...current, [key]: value }))
    setErrors((current) => ({ ...current, [key]: undefined }))
    setSuccessMessage(null)
  }

  const openCreateForm = () => {
    setEditingAssetTemplate(null)
    setForm({ ...defaultForm, building_id: !isSuperAdmin && buildings.length === 1 ? String(buildings[0].id) : selectedBuildingId })
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const resetForm = () => {
    setEditingAssetTemplate(null)
    setForm({ ...defaultForm, building_id: !isSuperAdmin && buildings.length === 1 ? String(buildings[0].id) : '' })
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
  }

  const editAssetTemplate = (assetTemplate: AdminAssetTemplateResource) => {
    setEditingAssetTemplate(assetTemplate)
    setForm({
      building_id: assetTemplate.building_id ? String(assetTemplate.building_id) : '',
      name: assetTemplate.name || '',
      default_unit_name: Number(assetTemplate.default_unit_name || 1),
      description: assetTemplate.description || '',
      status: Number(assetTemplate.status || 1),
    })
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const viewAssetTemplate = async (assetTemplate: AdminAssetTemplateResource) => {
    setDetailAssetTemplate(assetTemplate)
    setIsDetailOpen(true)
    setIsDetailLoading(true)
    setDetailErrorMessage(null)

    try {
      const response = await fetchAdminAssetTemplateDetail(assetTemplate.id)
      setDetailAssetTemplate(response.result)
    } catch (error) {
      setDetailErrorMessage(error instanceof Error ? error.message : 'Không thể tải chi tiết mẫu tài sản.')
    } finally {
      setIsDetailLoading(false)
    }
  }

  const closeAssetTemplateDetail = () => {
    setIsDetailOpen(false)
    setDetailAssetTemplate(null)
    setDetailErrorMessage(null)
  }

  const submit = async () => {
    if (isSaving) return

    const nextErrors = validateAssetTemplateForm(form, buildings, { requireBuilding: !isSuperAdmin })
    setErrors(nextErrors)

    if (Object.keys(nextErrors).length > 0) {
      setErrorMessage('Vui lòng kiểm tra lại thông tin mẫu tài sản.')
      return
    }

    try {
      setIsSaving(true)
      setErrorMessage(null)
      setSuccessMessage(null)

      const payload = {
        building_id: form.building_id ? Number(form.building_id) : undefined,
        name: form.name.trim(),
        default_unit_name: Number(form.default_unit_name),
        description: form.description.trim() || undefined,
        status: Number(form.status),
      }

      if (editingAssetTemplate) {
        await updateAdminAssetTemplate(editingAssetTemplate.id, payload)
        setSuccessMessage('Cập nhật mẫu tài sản thành công.')
      } else {
        await createAdminAssetTemplate(payload)
        setSuccessMessage('Tạo mẫu tài sản thành công.')
      }

      resetForm()
      setIsFormOpen(false)
      await loadAssetTemplates()
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể lưu mẫu tài sản.')
    } finally {
      setIsSaving(false)
    }
  }

  const toggleAssetTemplateStatus = async (assetTemplate: AdminAssetTemplateResource) => {
    const nextStatus = Number(assetTemplate.status) === 1 ? 2 : 1

    if (nextStatus === 2 && !window.confirm(`Bạn có chắc muốn tắt hoạt động mẫu tài sản ${assetTemplate.name}?`)) return

    try {
      setStatusChangingId(assetTemplate.id)
      setErrorMessage(null)
      setSuccessMessage(null)
      await updateAdminAssetTemplateStatus(assetTemplate.id, nextStatus)
      setSuccessMessage(`${nextStatus === 1 ? 'Kích hoạt' : 'Ngừng hoạt động'} mẫu tài sản thành công.`)
      await loadAssetTemplates()
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể đổi trạng thái mẫu tài sản.')
    } finally {
      setStatusChangingId(null)
    }
  }

  const removeAssetTemplate = async (assetTemplate: AdminAssetTemplateResource) => {
    if (!window.confirm(`Bạn có chắc chắn muốn xóa mẫu tài sản ${assetTemplate.name}?`)) return

    try {
      setErrorMessage(null)
      await deleteAdminAssetTemplate(assetTemplate.id)
      setSuccessMessage('Xóa mẫu tài sản thành công.')
      await loadAssetTemplates()
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể xóa mẫu tài sản.')
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
                <h1 className="max-w-3xl text-2xl font-black tracking-[-0.04em] text-[#fff4df] sm:text-[1.7rem] lg:text-3xl">Mẫu tài sản</h1>
              </div>
              <button type="button" onClick={openCreateForm} className="inline-flex h-9 items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition-all hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98]">
                <Plus className="h-4 w-4 stroke-[2.8]" /> Thêm mẫu mới
              </button>
            </div>

            <div className="relative mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
              <MetricCard label="Tổng mẫu" value={assetTemplates.length} tone="neutral" />
              <MetricCard label="Hoạt động" value={activeAssetTemplates} tone="emerald" />
              <MetricCard label={isSuperAdmin ? 'Dùng chung' : 'Thuộc tòa nhà'} value={isSuperAdmin ? globalAssetTemplates : managedAssetTemplates} tone="amber" />
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
                  <input type="text" value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="Tìm tên hoặc mô tả mẫu tài sản..." className={`${inputClass} pl-11 pr-28`} />
                  <button type="button" onClick={clearFilters} className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20">
                    <X className="h-3.5 w-3.5" /> Xóa lọc
                  </button>
                </div>
                <div className="ml-auto grid w-full grid-cols-1 gap-3 sm:grid-cols-2 lg:w-auto">
                  <AdminSelect value={selectedBuildingId} options={buildingOptions} className="lg:w-64" onChange={(nextValue) => setSelectedBuildingId(String(nextValue))} />
                  <AdminSelect value={selectedStatus} options={statusOptions} className="lg:w-64" onChange={(nextValue) => setSelectedStatus(String(nextValue))} />
                </div>
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="min-w-[880px] w-full text-left">
                <thead className="bg-[#24170d] text-[11px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
                  <tr>
                    <th className="px-5 py-4">Mẫu tài sản</th>
                    <th className="px-5 py-4">Tòa nhà</th>
                    <th className="px-5 py-4">Đơn vị</th>
                    <th className="px-5 py-4 text-center">Đang gán</th>
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

                  {!isLoading && assetTemplates.map((assetTemplate) => {
                    const canMutateAssetTemplate = isSuperAdmin || Boolean(assetTemplate.building_id)

                    return (
                    <tr key={assetTemplate.id} className="group transition hover:bg-[#f3c56b]/12">
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2.5">
                          <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#a65f16] shadow-sm transition group-hover:scale-105">
                            <Boxes className="h-4 w-4" />
                          </div>
                          <div className="min-w-0">
                            <p className="truncate text-[13px] font-black tracking-tight text-[#24170d]">{assetTemplate.name}</p>
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-[13px] font-bold text-[#6f6254]">{assetTemplate.building_name || 'Dùng chung'}</td>
                      <td className="px-4 py-3 text-[13px] font-black text-[#24170d]">{assetTemplate.default_unit_label || unitLabels[Number(assetTemplate.default_unit_name || 1)]}</td>
                      <td className="px-4 py-3 text-center text-[13px] font-black text-[#24170d] tabular-nums">{assetTemplate.room_assets_count ?? 0}</td>
                      <td className="px-4 py-3">
                        <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-[11px] font-black shadow-sm', Number(assetTemplate.status) === 1 ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]' : 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]')}>
                          {assetTemplate.status_label || statusLabels[Number(assetTemplate.status)]}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center justify-end gap-2.5">
                          <button type="button" onClick={() => void viewAssetTemplate(assetTemplate)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95" title="Xem chi tiết"><Eye className="h-5 w-5" /></button>
                          <button type="button" disabled={!canMutateAssetTemplate} onClick={() => editAssetTemplate(assetTemplate)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#3d2a18]/10 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45" title={canMutateAssetTemplate ? 'Chỉnh sửa' : 'Chỉ superadmin được sửa mẫu dùng chung'}><Edit3 className="h-5 w-5" /></button>
                          <button type="button" disabled={statusChangingId === assetTemplate.id || !canMutateAssetTemplate} onClick={() => void toggleAssetTemplateStatus(assetTemplate)} className={cn('inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition focus:outline-none focus:ring-4 active:scale-95 disabled:cursor-not-allowed disabled:opacity-55', Number(assetTemplate.status) === 1 ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 focus:ring-emerald-100' : 'border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 focus:ring-rose-100')} title={canMutateAssetTemplate ? (Number(assetTemplate.status) === 1 ? 'Ngừng hoạt động' : 'Kích hoạt') : 'Chỉ superadmin được đổi trạng thái mẫu dùng chung'}><Power className="h-5 w-5" /></button>
                          <button type="button" disabled={!canMutateAssetTemplate} onClick={() => void removeAssetTemplate(assetTemplate)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45" title={canMutateAssetTemplate ? 'Xóa' : 'Chỉ superadmin được xóa mẫu dùng chung'}><Trash2 className="h-5 w-5" /></button>
                        </div>
                      </td>
                    </tr>
                    )
                  })}

                  {!isLoading && assetTemplates.length === 0 && (
                    <tr>
                      <td colSpan={6} className="px-5 py-20 text-center">
                        <div className="mx-auto flex max-w-sm flex-col items-center">
                          <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]"><Boxes className="h-9 w-9" /></div>
                          <p className="text-lg font-black tracking-tight text-[#24170d]">Không tìm thấy mẫu tài sản</p>
                          <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">Hãy tạo mẫu mới hoặc đổi bộ lọc hiện tại.</p>
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
                <h2 className="text-lg font-black tracking-tight text-[#24170d]">{editingAssetTemplate ? 'Cập nhật mẫu tài sản' : 'Thêm mẫu tài sản'}</h2>
                <p className="text-xs font-bold text-[#8b5e34]/60">Khai báo tên, đơn vị và phạm vi áp dụng.</p>
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
                <label className={labelClass}>Tên mẫu tài sản</label>
                <input className={`${inputClass} ${errors.name ? inputErrorClass : ''}`} value={form.name} onChange={(event) => updateForm('name', event.target.value)} placeholder="Ví dụ: Máy lạnh" />
                <FieldError message={errors.name} />
              </div>
              <div>
                <label className={labelClass}>Tòa nhà áp dụng</label>
                <AdminSelect value={form.building_id} options={formBuildingOptions} invalid={!!errors.building_id} onChange={(nextValue) => updateForm('building_id', String(nextValue))} />
                <FieldError message={errors.building_id} />
              </div>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 2xl:grid-cols-1">
                <div>
                  <label className={labelClass}>Đơn vị</label>
                  <AdminSelect value={form.default_unit_name} options={unitOptions} invalid={!!errors.default_unit_name} onChange={(nextValue) => updateForm('default_unit_name', Number(nextValue))} />
                  <FieldError message={errors.default_unit_name} />
                </div>
                <div>
                  <label className={labelClass}>Trạng thái</label>
                  <AdminSelect value={form.status} options={formStatusOptions} invalid={!!errors.status} onChange={(nextValue) => updateForm('status', Number(nextValue))} />
                  <FieldError message={errors.status} />
                </div>
              </div>
              <div>
                <label className={labelClass}>Mô tả</label>
                <textarea className={`${inputClass} min-h-24 ${errors.description ? inputErrorClass : ''}`} value={form.description} onChange={(event) => updateForm('description', event.target.value)} placeholder="Ghi chú cách dùng hoặc tiêu chuẩn tài sản..." />
                <FieldError message={errors.description} />
              </div>

              <div className="flex flex-col gap-3 pt-2 sm:flex-row 2xl:flex-col">
                <button type="button" disabled={isSaving} onClick={() => void submit()} className="inline-flex min-h-14 flex-1 items-center justify-center gap-2 rounded-[1.25rem] bg-[#24170d] px-5 py-3.5 text-base font-black text-[#fff4df] shadow-lg shadow-[#24170d]/12 transition hover:bg-[#3d2a18] disabled:cursor-not-allowed disabled:opacity-60">
                  {isSaving ? 'Đang lưu...' : editingAssetTemplate ? 'Cập nhật' : 'Tạo mẫu'}
                </button>
                {editingAssetTemplate && (
                  <button type="button" onClick={() => { resetForm(); setIsFormOpen(false) }} className="inline-flex min-h-14 flex-1 items-center justify-center rounded-[1.25rem] border border-[#3d2a18]/10 bg-[#fffaf1] px-5 py-3.5 text-base font-black text-[#8b5e34] transition hover:border-[#f3c56b]/45 hover:bg-[#f3c56b]/15 hover:text-[#24170d]">
                    Hủy sửa
                  </button>
                )}
              </div>
            </div>
            </aside>
          )}
        </div>
      </div>

      {isDetailOpen && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
          <button type="button" aria-label="Đóng chi tiết mẫu tài sản" onClick={closeAssetTemplateDetail} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />
          <div className="relative z-10 w-full max-w-2xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-stone-950/30">
            <div className="bg-[#24170d] p-5 text-[#fff4df]">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-[10px] font-black uppercase tracking-[0.24em] text-[#f3c56b]">Asset template detail</p>
                  <h2 className="mt-2 text-2xl font-black tracking-tight">{detailAssetTemplate?.name || 'Đang tải chi tiết...'}</h2>
                </div>
                <button type="button" onClick={closeAssetTemplateDetail} className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20">
                  <X className="h-5 w-5" />
                </button>
              </div>
            </div>

            <div className="space-y-4 p-5">
              {isDetailLoading && <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-800">Đang tải chi tiết mẫu tài sản...</div>}
              {detailErrorMessage && <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{detailErrorMessage}</div>}

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <DetailTile label="Tòa nhà" value={detailAssetTemplate?.building_name || 'Dùng chung'} />
                <DetailTile label="Đơn vị" value={detailAssetTemplate?.default_unit_label || unitLabels[Number(detailAssetTemplate?.default_unit_name || 1)]} />
                <DetailTile label="Đang gán" value={detailAssetTemplate?.room_assets_count ?? 0} />
              </div>

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <DetailTile label="Trạng thái" value={detailAssetTemplate?.status_label || statusLabels[Number(detailAssetTemplate?.status || 1)]} />
                <DetailTile label="Người tạo" value={detailAssetTemplate?.creator_name || 'Chưa cập nhật'} />
              </div>

              <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
                <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Mô tả</p>
                <p className="mt-2 text-sm font-semibold leading-6 text-[#3d2a18]">{detailAssetTemplate?.description || 'Chưa có mô tả.'}</p>
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
