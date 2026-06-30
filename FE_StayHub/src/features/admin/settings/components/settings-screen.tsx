import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { Edit3, Eye, Plus, RefreshCw, Search, Settings, Trash2, X, Power } from 'lucide-react'
import { formatDate } from '../../../../shared/lib/utils/format'
import { cn } from '../../../../shared/lib/utils/cn'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import { AdminSelect } from '../../shared/components/AdminSelect'
import {
  createAdminSetting,
  deleteAdminSetting,
  fetchAdminSettingDetail,
  fetchAdminSettings,
  updateAdminSetting,
  updateAdminSettingPublic,
} from '../services/settings.service'
import type { AdminSettingResource } from '../types/setting-api.model'
import { validateSettingForm, type SettingFormErrors, type SettingFormValues } from '../validations/setting.validation'

function getResourceList<T>(result: { data?: T[] } | T[] | null | undefined): T[] {
  if (!result) return []
  if (Array.isArray(result)) return result
  return result.data || []
}

function getSafeSettingsErrorMessage(error: unknown, fallback: string) {
  if (!(error instanceof Error)) return fallback

  const message = error.message.trim()
  if (!message) return fallback

  const normalizedMessage = message.toLowerCase()
  const isSystemError = normalizedMessage.startsWith('server error')
    || normalizedMessage.includes('sqlstate')
    || normalizedMessage.includes('exception')
    || normalizedMessage.includes('stack trace')
    || normalizedMessage.includes('undefined property')

  return isSystemError ? fallback : message
}

const defaultForm: SettingFormValues = {
  building_id: '',
  setting_label: '',
  setting_value: '',
  description: '',
  is_public: true,
}

const publicOptions = [
  { value: '', label: 'Tất cả hiển thị', tone: 'default' as const },
  { value: '1', label: 'Công khai', tone: 'success' as const },
  { value: '0', label: 'Không công khai', tone: 'warning' as const },
]

const formPublicOptions = [
  { value: 1, label: 'Công khai', tone: 'success' as const },
  { value: 0, label: 'Không công khai', tone: 'warning' as const },
]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'
const inputErrorClass = 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100'
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65'

export function SettingsScreen() {
  const { session } = useAdminSession()
  const isSuperAdmin = isSuperAdminRole(session?.admin.role)
  const [keyword, setKeyword] = useState('')
  const [selectedBuildingId, setSelectedBuildingId] = useState('')
  const [selectedPublic, setSelectedPublic] = useState('')
  const [settings, setSettings] = useState<AdminSettingResource[]>([])
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [editingSetting, setEditingSetting] = useState<AdminSettingResource | null>(null)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [form, setForm] = useState<SettingFormValues>(defaultForm)
  const [errors, setErrors] = useState<SettingFormErrors>({})
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [detailSetting, setDetailSetting] = useState<AdminSettingResource | null>(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [deletingId, setDeletingId] = useState<number | null>(null)

  const buildingItems = useMemo(() => buildings.map((building) => ({ value: building.id, label: building.name, tone: 'default' as const })), [buildings])
  const filterBuildingOptions = useMemo(() => [{ value: '', label: 'Tất cả tòa nhà', tone: 'default' as const }, ...buildingItems], [buildingItems])
  const formBuildingOptions = useMemo(() => isSuperAdmin ? [{ value: '', label: 'Dùng chung toàn hệ thống', tone: 'warning' as const }, ...buildingItems] : buildingItems, [buildingItems, isSuperAdmin])
  const allowedBuildingIds = useMemo(() => buildings.map((building) => Number(building.id)), [buildings])
  const hasManagedBuildings = isSuperAdmin || buildings.length > 0

  const loadSettings = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)

    try {
      const [settingResponse, buildingResponse] = await Promise.all([
        fetchAdminSettings({
          keyword: keyword.trim() || undefined,
          building_id: selectedBuildingId ? Number(selectedBuildingId) : undefined,
          is_public: selectedPublic === '' ? undefined : selectedPublic === '1',
          per_page: 100,
        }),
        fetchAdminBuildings({ per_page: 100 }),
      ])

      const buildingList = getResourceList(buildingResponse.result)
      const visibleBuildings = isSuperAdmin
        ? buildingList
        : buildingList.filter((building) => Number(building.manager_admin_id) === Number(session?.admin.id))
      const nextAllowedBuildingIds = new Set(visibleBuildings.map((building) => Number(building.id)))
      const nextSettings = getResourceList(settingResponse.result).filter((setting) => {
        if (isSuperAdmin) return true
        if (!setting.building_id) return false
        return nextAllowedBuildingIds.has(Number(setting.building_id))
      })

      setSettings(nextSettings)
      setBuildings(visibleBuildings)

      if (!isSuperAdmin && visibleBuildings.length === 1) {
        setForm((current) => ({ ...current, building_id: current.building_id || String(visibleBuildings[0].id) }))
      }
    } catch (error) {
      setErrorMessage(getSafeSettingsErrorMessage(error, 'Không thể tải danh sách cài đặt tòa nhà.'))
    } finally {
      setIsLoading(false)
    }
  }, [isSuperAdmin, keyword, selectedBuildingId, selectedPublic, session?.admin.id])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadSettings()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [loadSettings])

  const publicSettings = useMemo(() => settings.filter((item) => item.is_public).length, [settings])
  const globalSettings = useMemo(() => settings.filter((item) => !item.building_id).length, [settings])
  const buildingSettings = useMemo(() => settings.filter((item) => item.building_id).length, [settings])

  const updateForm = (key: keyof SettingFormValues, value: string | boolean) => {
    setForm((current) => ({ ...current, [key]: value }))
    setErrors((current) => ({ ...current, [key]: undefined }))
    setSuccessMessage(null)
  }

  const openCreateForm = () => {
    if (!hasManagedBuildings) return

    setEditingSetting(null)
    setForm({
      ...defaultForm,
      building_id: !isSuperAdmin && buildings.length === 1 ? String(buildings[0].id) : selectedBuildingId,
    })
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const resetForm = () => {
    setEditingSetting(null)
    setForm({ ...defaultForm, building_id: !isSuperAdmin && buildings.length === 1 ? String(buildings[0].id) : '' })
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
  }

  const editSetting = (setting: AdminSettingResource) => {
    setEditingSetting(setting)
    setForm({
      building_id: setting.building_id ? String(setting.building_id) : '',
      setting_label: setting.setting_label || '',
      setting_value: setting.setting_value || '',
      description: setting.description || '',
      is_public: Boolean(setting.is_public),
    })
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const viewSetting = async (setting: AdminSettingResource) => {
    setDetailSetting(setting)
    setIsDetailOpen(true)
    setIsDetailLoading(true)
    setDetailErrorMessage(null)

    try {
      const response = await fetchAdminSettingDetail(setting.id)
      setDetailSetting(response.result)
    } catch (error) {
      setDetailErrorMessage(getSafeSettingsErrorMessage(error, 'Không thể tải chi tiết cài đặt.'))
    } finally {
      setIsDetailLoading(false)
    }
  }

  const closeSettingDetail = () => {
    setIsDetailOpen(false)
    setDetailSetting(null)
    setDetailErrorMessage(null)
  }

  const canMutateSetting = (setting: AdminSettingResource) => {
    if (isSuperAdmin) return true
    return Boolean(setting.building_id && allowedBuildingIds.includes(Number(setting.building_id)))
  }

  const submit = async () => {
    if (isSaving) return

    const nextErrors = validateSettingForm(form, { allowedBuildingIds, requireBuilding: !isSuperAdmin })
    setErrors(nextErrors)

    if (Object.keys(nextErrors).length > 0) {
      setErrorMessage('Vui lòng kiểm tra lại thông tin cài đặt.')
      return
    }

    try {
      setIsSaving(true)
      setErrorMessage(null)
      setSuccessMessage(null)

      const payload = {
        building_id: form.building_id ? Number(form.building_id) : null,
        setting_label: form.setting_label.trim(),
        setting_value: form.setting_value.trim(),
        description: form.description.trim(),
        is_public: form.is_public,
      }

      if (editingSetting) {
        await updateAdminSetting(editingSetting.id, payload)
        setSuccessMessage('Cập nhật cài đặt thành công.')
      } else {
        await createAdminSetting(payload)
        setSuccessMessage('Tạo cài đặt thành công.')
      }

      resetForm()
      setIsFormOpen(false)
      await loadSettings()
    } catch (error) {
      setErrorMessage(getSafeSettingsErrorMessage(error, 'Không thể lưu cài đặt.'))
    } finally {
      setIsSaving(false)
    }
  }

  const togglePublicSetting = async (setting: AdminSettingResource) => {
    if (isSaving || !canMutateSetting(setting)) return

    try {
      setIsSaving(true)
      setErrorMessage(null)
      setSuccessMessage(null)

      await updateAdminSettingPublic(setting.id)
      setSuccessMessage(setting.is_public ? 'Đã tắt hiển thị công khai.' : 'Đã bật hiển thị công khai.')
      await loadSettings()
    } catch (error) {
      setErrorMessage(getSafeSettingsErrorMessage(error, 'Không thể thay đổi trạng thái hiển thị.'))
    } finally {
      setIsSaving(false)
    }
  }

  const removeSetting = async (setting: AdminSettingResource) => {
    if (!window.confirm(`Bạn có chắc chắn muốn xóa cài đặt ${setting.setting_label}?`)) return

    try {
      setDeletingId(setting.id)
      setErrorMessage(null)
      setSuccessMessage(null)
      await deleteAdminSetting(setting.id)
      setSuccessMessage('Xóa cài đặt thành công.')
      await loadSettings()
    } catch (error) {
      setErrorMessage(getSafeSettingsErrorMessage(error, 'Không thể xóa cài đặt.'))
    } finally {
      setDeletingId(null)
    }
  }

  const clearFilters = () => {
    setKeyword('')
    setSelectedBuildingId('')
    setSelectedPublic('')
  }

  return (
    <>
      <>
      <section className="space-y-5 sm:space-y-6 text-[#24170d]">
        <div className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
          <div className="relative p-5 text-[#fff4df] sm:p-6 lg:p-7">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(243,197,107,0.24),transparent_30%),radial-gradient(circle_at_82%_16%,rgba(15,118,110,0.22),transparent_32%),linear-gradient(135deg,#24170d_0%,#3d2a18_54%,#0f3f3b_100%)]" />
            <div className="relative flex min-w-0 flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
              <div className="min-w-0">
                <span className="block text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]/80">HỆ THỐNG</span>
                <h1 className="mt-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem] flex items-center gap-3">
                  <Settings className="h-8 w-8 text-[#f3c56b] shrink-0" />
                  Cài đặt tòa nhà
                </h1>
                <p className="mt-2.5 text-xs font-semibold text-[#f8e8c8]/70">Thiết lập thông tin chung, quy định và cấu hình vận hành tòa nhà.</p>
              </div>
              <button type="button" disabled={!hasManagedBuildings} onClick={openCreateForm} className="inline-flex w-fit self-end xl:self-auto h-9 items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition-all hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-55">
                <Plus className="h-4 w-4 stroke-[2.8]" /> Thêm cài đặt
              </button>
            </div>

            <div className="relative mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
              <MetricCard label="Tổng cài đặt" value={settings.length} tone="neutral" />
              <MetricCard label="Công khai" value={publicSettings} tone="emerald" />
              <MetricCard label={isSuperAdmin ? 'Dùng chung' : 'Thuộc tòa'} value={isSuperAdmin ? globalSettings : buildingSettings} tone="amber" />
            </div>
          </div>
        </div>

        {!isSuperAdmin && !isLoading && buildings.length === 0 && (
          <div className="rounded-3xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-800">
            Tài khoản của bạn chưa được gán tòa nhà quản lý. Vui lòng liên hệ quản trị tổng để được phân quyền.
          </div>
        )}

        {(errorMessage || successMessage) && (
          <div className={cn('rounded-3xl border p-4 text-sm font-black', errorMessage ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700')}>
            {errorMessage || successMessage}
          </div>
        )}

        <div className={cn('grid min-w-0 grid-cols-1 gap-4 xl:gap-6', isFormOpen && '2xl:grid-cols-[minmax(0,1fr)_380px]')}>
          <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/88 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
            <div className="border-b border-[#3d2a18]/10 bg-[#fff7e8]/72 p-4 sm:p-5">
              <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div className="relative min-w-0 flex-1">
                  <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
                  <input type="text" value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="Tìm tên, khóa, giá trị hoặc mô tả..." className={`${inputClass} pl-11 pr-28`} />
                  <button type="button" onClick={clearFilters} className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20">
                    <X className="h-3.5 w-3.5" /> Xóa lọc
                  </button>
                </div>
                <div className="grid w-full gap-3 sm:grid-cols-2 lg:ml-auto lg:w-lg">
                  <AdminSelect value={selectedBuildingId} options={filterBuildingOptions} onChange={(nextValue) => setSelectedBuildingId(String(nextValue))} />
                  <AdminSelect value={selectedPublic} options={publicOptions} onChange={(nextValue) => setSelectedPublic(String(nextValue))} />
                </div>
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="min-w-[940px] w-full text-left">
                <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
                  <tr>
                    <th className="px-5 py-4">Cài đặt</th>
                    <th className="px-5 py-4">Tòa nhà</th>
                    <th className="px-5 py-4">Giá trị</th>
                    <th className="px-5 py-4">Hiển thị</th>
                    <th className="px-5 py-4 w-[190px]"><div className="flex justify-end"><div className="w-[190px] text-center">Thao tác</div></div></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[#3d2a18]/8">
                  {isLoading && Array.from({ length: 5 }).map((_, index) => (
                    <tr key={index}>
                      <td colSpan={5} className="px-5 py-4"><div className="h-12 animate-pulse rounded-2xl bg-stone-100" /></td>
                    </tr>
                  ))}

                  {!isLoading && settings.map((setting) => {
                    const canMutate = canMutateSetting(setting)

                    return (
<tr key={setting.id} className="group transition hover:bg-[#f3c56b]/12">
                        <td className="px-4 py-3">
                          <div className="flex items-center gap-2.5">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#a65f16] shadow-sm transition group-hover:scale-105">
                              <Settings className="h-4 w-4" />
                            </div>
                            <div className="min-w-0">
                              <p className="truncate text-[13px] font-black tracking-tight text-[#24170d]">{setting.setting_label}</p>
                            </div>
                          </div>
                        </td>
                        <td className="px-4 py-3 text-[13px] font-bold text-[#6f6254]">{setting.building_name || 'Dùng chung'}</td>
                        <td className="max-w-[240px] px-4 py-3 text-[13px] font-bold text-[#24170d]">
                          <span className="line-clamp-2">{setting.setting_value || 'Chưa cấu hình'}</span>
                        </td>
                        <td className="px-4 py-3">
                          <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-[11px] font-black shadow-sm', setting.is_public ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]' : 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]')}>
                            {setting.is_public_label || (setting.is_public ? 'Công khai' : 'Không công khai')}
                          </span>
                        </td>
                        <td className="px-4 py-3">
                          <div className="flex items-center justify-end gap-2.5">
                            <button type="button" aria-label={`Xem chi tiết ${setting.setting_label}`} onClick={() => void viewSetting(setting)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95" title="Xem chi tiết"><Eye className="h-5 w-5" /></button>
                            <button type="button" aria-label={`Chỉnh sửa ${setting.setting_label}`} disabled={!canMutate} onClick={() => editSetting(setting)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#3d2a18]/10 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45" title={canMutate ? 'Chỉnh sửa' : 'Bạn không có quyền sửa cài đặt này'}><Edit3 className="h-5 w-5" /></button>
                            <button type="button" aria-label={`Đổi trạng thái hiển thị ${setting.setting_label}`} disabled={!canMutate || deletingId === setting.id} onClick={() => void togglePublicSetting(setting)} className={cn("inline-flex h-10 w-10 items-center justify-center rounded-xl border shadow-sm transition focus:outline-none focus:ring-4 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45", setting.is_public ? "border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59] hover:border-[#0f766e]/30 hover:bg-[#0f766e]/20 focus:ring-[#0f766e]/10" : "border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254] hover:bg-[#efe2cf] hover:text-[#3d2a18] focus:ring-[#3d2a18]/10")} title={canMutate ? 'Đổi trạng thái hiển thị' : 'Bạn không có quyền sửa cài đặt này'}><Power className="h-5 w-5" /></button>
                            <button type="button" aria-label={`Xóa ${setting.setting_label}`} disabled={!canMutate || deletingId === setting.id} onClick={() => void removeSetting(setting)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45" title={canMutate ? 'Xóa' : 'Bạn không có quyền xóa cài đặt này'}><Trash2 className="h-5 w-5" /></button>
                          </div>
                        </td>
                      </tr>
  )
                  })}

                  {!isLoading && settings.length === 0 && (
                    <tr>
                      <td colSpan={5} className="px-5 py-20 text-center">
                        <div className="mx-auto flex max-w-sm flex-col items-center">
                          <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]"><Settings className="h-9 w-9" /></div>
                          <p className="text-lg font-black tracking-tight text-[#24170d]">Không tìm thấy cài đặt</p>
                          <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">Hãy tạo cài đặt mới hoặc đổi bộ lọc hiện tại.</p>
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
                  <h2 className="text-lg font-black tracking-tight text-[#24170d]">{editingSetting ? 'Cập nhật cài đặt' : 'Thêm cài đặt'}</h2>
                  <p className="text-xs font-bold text-[#8b5e34]/60">Khai báo khóa cấu hình cho hệ thống hoặc từng tòa.</p>
                </div>
                <div className="flex items-center gap-2">
                  <button type="button" onClick={resetForm} className="rounded-xl border border-[#3d2a18]/10 p-2 text-[#8b5e34] transition hover:bg-[#f3c56b]/15" title="Làm mới form" aria-label="Làm mới form cài đặt">
                    <RefreshCw className="h-4 w-4" />
                  </button>
                  <button type="button" onClick={() => { resetForm(); setIsFormOpen(false) }} className="rounded-xl border border-[#3d2a18]/10 p-2 text-[#8b5e34] transition hover:bg-rose-50 hover:text-rose-600" title="Đóng form" aria-label="Đóng form cài đặt">
                    <X className="h-4 w-4" />
                  </button>
                </div>
              </div>

              <div className="space-y-4">
                <div>
                  <label className={labelClass}>Tên hiển thị</label>
                  <input className={`${inputClass} ${errors.setting_label ? inputErrorClass : ''}`} value={form.setting_label} onChange={(event) => updateForm('setting_label', event.target.value)} placeholder="Ví dụ: Giờ đóng cổng" />
                  <FieldError message={errors.setting_label} />
                </div>

                <div>
                  <label className={labelClass}>Tòa nhà áp dụng</label>
                  <AdminSelect value={form.building_id} options={formBuildingOptions} invalid={!!errors.building_id} disabled={!isSuperAdmin && buildings.length === 0} onChange={(nextValue) => updateForm('building_id', String(nextValue))} />
                  <FieldError message={errors.building_id} />
                </div>
                <div>
                  <label className={labelClass}>Giá trị</label>
                  <input className={`${inputClass} ${errors.setting_value ? inputErrorClass : ''}`} value={form.setting_value} onChange={(event) => updateForm('setting_value', event.target.value)} placeholder="Ví dụ: 23:00" />
                  <FieldError message={errors.setting_value} />
                </div>
                <div>
                  <label className={labelClass}>Hiển thị</label>
                  <AdminSelect value={form.is_public ? 1 : 0} options={formPublicOptions} onChange={(nextValue) => updateForm('is_public', Number(nextValue) === 1)} />
                </div>
                <div>
                  <label className={labelClass}>Mô tả</label>
                  <textarea className={`${inputClass} min-h-24 ${errors.description ? inputErrorClass : ''}`} value={form.description} onChange={(event) => updateForm('description', event.target.value)} placeholder="Ghi chú mục đích hoặc cách dùng cài đặt..." />
                  <FieldError message={errors.description} />
                </div>

                <div className="flex flex-col gap-3 pt-2 sm:flex-row 2xl:flex-col">
                  <button type="button" disabled={isSaving} onClick={() => void submit()} className="inline-flex min-h-14 flex-1 items-center justify-center gap-2 rounded-[1.25rem] bg-[#24170d] px-5 py-3.5 text-base font-black text-[#fff4df] shadow-lg shadow-[#24170d]/12 transition hover:bg-[#3d2a18] disabled:cursor-not-allowed disabled:opacity-60">
                    {isSaving ? 'Đang lưu...' : editingSetting ? 'Cập nhật' : 'Tạo cài đặt'}
                  </button>
                </div>
              </div>
            </aside>
          )}
        </div>
      </section>

      {isDetailOpen && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
          <button type="button" aria-label="Đóng chi tiết cài đặt" onClick={closeSettingDetail} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />
          <div className="relative z-10 w-full max-w-2xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-stone-950/30">
            <div className="bg-[#24170d] p-5 text-[#fff4df]">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-[10px] font-black uppercase tracking-[0.24em] text-[#f3c56b]">Setting detail</p>
                  <h2 className="mt-2 text-2xl font-black tracking-tight">{detailSetting?.setting_label || 'Đang tải chi tiết...'}</h2>
                </div>
                <button type="button" onClick={closeSettingDetail} className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20" aria-label="Đóng chi tiết cài đặt">
                  <X className="h-5 w-5" />
                </button>
              </div>
            </div>

            <div className="space-y-4 p-5">
              {isDetailLoading && <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-800">Đang tải chi tiết cài đặt...</div>}
              {detailErrorMessage && <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{detailErrorMessage}</div>}

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <DetailTile label="Tòa nhà" value={detailSetting?.building_name || 'Dùng chung'} />
                <DetailTile label="Hiển thị" value={detailSetting?.is_public_label || (detailSetting?.is_public ? 'Công khai' : 'Không công khai')} />
              </div>

              <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
                <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Giá trị</p>
                <p className="mt-2 break-words text-sm font-semibold leading-6 text-[#3d2a18]">{detailSetting?.setting_value || 'Chưa cấu hình.'}</p>
              </section>

              <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
                <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Mô tả</p>
                <p className="mt-2 text-sm font-semibold leading-6 text-[#3d2a18]">{detailSetting?.description || 'Chưa có mô tả.'}</p>
              </section>

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <DetailTile label="Người tạo" value={detailSetting?.creator_name || detailSetting?.creator?.full_name || 'Không rõ'} />
                <DetailTile label="Cập nhật" value={formatDate(detailSetting?.updated_at)} />
              </div>
            </div>
          </div>
        </div>
      )}
    </>
    </>
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

function DetailTile({ label, value }: { label: string; value?: string | number | null }) {
  return (
    <div className="rounded-2xl border border-[#3d2a18]/10 bg-white/70 p-4">
      <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">{label}</p>
      <p className="mt-2 text-lg font-black text-[#24170d] tabular-nums">{value ?? '—'}</p>
    </div>
  )
}

function FieldError({ message }: { message?: string }) {
  if (!message) return null
  return <p className="mt-2 px-1 text-xs font-black text-rose-600" role="alert">{message}</p>
}
