import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeft, Database, Edit3, Eye, Plus, Power, RefreshCw, Search, Trash2, X, Zap } from 'lucide-react'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { cn } from '../../../../shared/lib/utils/cn'
import {
  createAdminService,
  deleteAdminService,
  fetchAdminServiceDetail,
  fetchAdminServices,
  updateAdminService,
  updateAdminServiceStatus,
} from '../services/services.service'
import type { AdminServiceResource } from '../types/service-api.model'
import { validateServiceForm, type ServiceFormErrors, type ServiceFormValues } from '../validations/service.validation'

function getResourceList<T>(result: { data?: T[] } | T[] | null | undefined): T[] {
  if (!result) return []
  if (Array.isArray(result)) return result
  return result.data || []
}

const defaultForm: ServiceFormValues = {
  service_code: '',
  name: '',
  service_type: 'dien',
  charge_method: 1,
  unit_name: '',
  is_required: false,
  is_active: true,
}

const serviceTypeOptions = [
  { value: 'dien', label: 'Điện', tone: 'warning' as const },
  { value: 'nuoc', label: 'Nước', tone: 'success' as const },
  { value: 'internet', label: 'Internet', tone: 'default' as const },
  { value: 'rac', label: 'Rác', tone: 'default' as const },
  { value: 'gui_xe', label: 'Gửi xe', tone: 'default' as const },
  { value: 've_sinh', label: 'Vệ sinh', tone: 'default' as const },
  { value: 'khac', label: 'Khác', tone: 'default' as const },
]

const chargeMethodOptions = [
  { value: 1, label: 'Theo chỉ số', description: 'Điện, nước qua công tơ', tone: 'warning' as const },
  { value: 2, label: 'Theo người', description: 'Rác, nước sinh hoạt theo số người', tone: 'default' as const },
  { value: 3, label: 'Theo phòng', description: 'Internet, vệ sinh theo phòng', tone: 'success' as const },
  { value: 4, label: 'Theo xe', description: 'Gửi xe theo phương tiện', tone: 'default' as const },
  { value: 5, label: 'Cố định', description: 'Khoản thu cố định', tone: 'default' as const },
]

const requiredOptions = [
  { value: '', label: 'Tất cả bắt buộc', tone: 'default' as const },
  { value: '1', label: 'Bắt buộc', tone: 'warning' as const },
  { value: '0', label: 'Không bắt buộc', tone: 'default' as const },
]

const formRequiredOptions = [
  { value: 1, label: 'Bắt buộc', tone: 'warning' as const },
  { value: 0, label: 'Không bắt buộc', tone: 'default' as const },
]

const statusOptions = [
  { value: '', label: 'Tất cả trạng thái', tone: 'default' as const },
  { value: '1', label: 'Hoạt động', tone: 'success' as const },
  { value: '0', label: 'Ngừng hoạt động', tone: 'danger' as const },
]

const formStatusOptions = [
  { value: 1, label: 'Hoạt động', tone: 'success' as const },
  { value: 0, label: 'Ngừng hoạt động', tone: 'danger' as const },
]

const filterServiceTypeOptions = [{ value: '', label: 'Tất cả loại dịch vụ', tone: 'default' as const }, ...serviceTypeOptions]
const filterChargeMethodOptions = [{ value: '', label: 'Tất cả cách tính phí', tone: 'default' as const }, ...chargeMethodOptions]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'
const inputErrorClass = 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100'
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65'

const serviceTypeToneClassNames: Record<string, string> = {
  dien: 'border-amber-200 bg-amber-50 text-amber-700',
  nuoc: 'border-cyan-200 bg-cyan-50 text-cyan-700',
  internet: 'border-indigo-200 bg-indigo-50 text-indigo-700',
  rac: 'border-lime-200 bg-lime-50 text-lime-700',
  gui_xe: 'border-orange-200 bg-orange-50 text-orange-700',
  ve_sinh: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  khac: 'border-stone-200 bg-stone-50 text-stone-600',
}

export function ServicesScreen() {
  const { session } = useAdminSession()
  const isSuperAdmin = isSuperAdminRole(session?.admin.role)
  const [keyword, setKeyword] = useState('')
  const [selectedServiceType, setSelectedServiceType] = useState('')
  const [selectedChargeMethod, setSelectedChargeMethod] = useState('')
  const [selectedRequired, setSelectedRequired] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [services, setServices] = useState<AdminServiceResource[]>([])
  const [editingService, setEditingService] = useState<AdminServiceResource | null>(null)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [form, setForm] = useState<ServiceFormValues>(defaultForm)
  const [errors, setErrors] = useState<ServiceFormErrors>({})
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [detailService, setDetailService] = useState<AdminServiceResource | null>(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [statusChangingId, setStatusChangingId] = useState<number | null>(null)

  const loadServices = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)

    try {
      const response = await fetchAdminServices({
        keyword: keyword.trim() || undefined,
        service_type: selectedServiceType || undefined,
        charge_method: selectedChargeMethod ? Number(selectedChargeMethod) : undefined,
        is_required: selectedRequired === '' ? undefined : selectedRequired === '1',
        is_active: selectedStatus === '' ? undefined : selectedStatus === '1',
        per_page: 100,
      })

      setServices(getResourceList(response.result))
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể tải danh mục dịch vụ.')
    } finally {
      setIsLoading(false)
    }
  }, [keyword, selectedChargeMethod, selectedRequired, selectedServiceType, selectedStatus])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadServices()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [loadServices])

  const activeServices = useMemo(() => services.filter((item) => item.is_active).length, [services])
  const requiredServices = useMemo(() => services.filter((item) => item.is_required).length, [services])
  const relatedRecords = useMemo(() => services.reduce((sum, item) => sum + Number(item.prices_count || 0) + Number(item.meter_devices_count || 0) + Number(item.invoice_items_count || 0), 0), [services])
  const hasActiveFilters = Boolean(keyword.trim() || selectedServiceType || selectedChargeMethod || selectedRequired || selectedStatus)

  const updateForm = (key: keyof ServiceFormValues, value: string | number | boolean) => {
    setForm((current) => ({ ...current, [key]: value }))
    setErrors((current) => ({ ...current, [key]: undefined }))
    setSuccessMessage(null)
  }

  const openCreateForm = () => {
    setEditingService(null)
    setForm({ ...defaultForm })
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const resetForm = () => {
    setEditingService(null)
    setForm({ ...defaultForm })
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
  }

  const editService = (service: AdminServiceResource) => {
    setEditingService(service)
    setForm({
      service_code: service.service_code || '',
      name: service.name || '',
      service_type: service.service_type || 'dien',
      charge_method: Number(service.charge_method || 1),
      unit_name: service.unit_name || '',
      is_required: Boolean(service.is_required),
      is_active: Boolean(service.is_active),
    })
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const viewService = async (service: AdminServiceResource) => {
    setDetailService(service)
    setIsDetailOpen(true)
    setIsDetailLoading(true)
    setDetailErrorMessage(null)

    try {
      const response = await fetchAdminServiceDetail(service.id)
      setDetailService(response.result)
    } catch (error) {
      setDetailErrorMessage(error instanceof Error ? error.message : 'Không thể tải chi tiết dịch vụ.')
    } finally {
      setIsDetailLoading(false)
    }
  }

  const closeServiceDetail = () => {
    setIsDetailOpen(false)
    setDetailService(null)
    setDetailErrorMessage(null)
  }

  useEffect(() => {
    if (!isDetailOpen) return

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setIsDetailOpen(false)
        setDetailService(null)
        setDetailErrorMessage(null)
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [isDetailOpen])

  const submit = async () => {
    if (isSaving || !isSuperAdmin) return

    const nextErrors = validateServiceForm(form)
    setErrors(nextErrors)

    if (Object.keys(nextErrors).length > 0) {
      setErrorMessage('Vui lòng kiểm tra lại thông tin dịch vụ.')
      return
    }

    try {
      setIsSaving(true)
      setErrorMessage(null)
      setSuccessMessage(null)

      const payload = {
        service_code: form.service_code.trim(),
        name: form.name.trim(),
        service_type: form.service_type,
        charge_method: Number(form.charge_method),
        unit_name: form.unit_name.trim() || undefined,
        is_required: Boolean(form.is_required),
        is_active: Boolean(form.is_active),
      }

      if (editingService) {
        await updateAdminService(editingService.id, payload)
        setSuccessMessage('Cập nhật dịch vụ thành công.')
      } else {
        await createAdminService(payload)
        setSuccessMessage('Tạo dịch vụ thành công.')
      }

      resetForm()
      setIsFormOpen(false)
      await loadServices()
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể lưu dịch vụ.')
    } finally {
      setIsSaving(false)
    }
  }

  const toggleServiceStatus = async (service: AdminServiceResource) => {
    if (!isSuperAdmin) return

    const nextStatus = !service.is_active

    if (!nextStatus && !window.confirm(`Bạn có chắc muốn ngừng hoạt động dịch vụ ${service.name}?`)) return

    try {
      setStatusChangingId(service.id)
      setErrorMessage(null)
      setSuccessMessage(null)
      await updateAdminServiceStatus(service.id, nextStatus)
      setSuccessMessage(`${nextStatus ? 'Kích hoạt' : 'Ngừng hoạt động'} dịch vụ thành công.`)
      await loadServices()
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể đổi trạng thái dịch vụ.')
    } finally {
      setStatusChangingId(null)
    }
  }

  const removeService = async (service: AdminServiceResource) => {
    if (!isSuperAdmin) return
    if (!window.confirm(`Bạn có chắc chắn muốn xóa dịch vụ ${service.name}? Dịch vụ đã phát sinh dữ liệu sẽ không thể xóa.`)) return

    try {
      setErrorMessage(null)
      await deleteAdminService(service.id)
      setSuccessMessage('Xóa dịch vụ thành công.')
      await loadServices()
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể xóa dịch vụ.')
    }
  }

  const clearFilters = () => {
    setKeyword('')
    setSelectedServiceType('')
    setSelectedChargeMethod('')
    setSelectedRequired('')
    setSelectedStatus('')
  }

  return (
    <div className="relative min-w-0 overflow-hidden rounded-[2rem] bg-[#f7f0e5] text-[#24170d] shadow-inner shadow-white/80">
      <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,rgba(61,42,24,0.055)_1px,transparent_1px),linear-gradient(to_bottom,rgba(61,42,24,0.055)_1px,transparent_1px)] bg-[size:34px_34px]" />
      <div className="pointer-events-none absolute -left-24 top-24 h-72 w-72 rounded-full bg-[#f3c56b]/25 blur-3xl" />
      <div className="pointer-events-none absolute -right-28 -top-28 h-96 w-96 rounded-full bg-[#0f766e]/10 blur-3xl" />
      <div className="pointer-events-none absolute bottom-0 right-20 h-56 w-56 rounded-full bg-[#a65f16]/10 blur-3xl" />

      <div className="relative space-y-5 p-4 sm:space-y-6 sm:p-6">
        <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
          <div className="relative p-4 text-[#fff4df] sm:p-5 lg:p-6">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_14%,rgba(243,197,107,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(15,118,110,0.26),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
            <div className="pointer-events-none absolute inset-x-6 bottom-0 h-px bg-gradient-to-r from-transparent via-[#f3c56b]/40 to-transparent" />

            <div className="relative flex min-w-0 flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
              <div className="min-w-0">
                <Link to="/admin/dashboard" className="mb-2 inline-flex items-center gap-2 text-xs font-black text-[#f3c56b] transition hover:text-[#ffd56f]">
                  <ArrowLeft className="h-3.5 w-3.5" /> Về dashboard
                </Link>
                <div className="inline-flex items-center gap-2 rounded-full border border-[#f3c56b]/25 bg-[#f3c56b]/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.2em] text-[#f3c56b]">
                  <Zap className="h-3.5 w-3.5" /> Services
                </div>
                <h1 className="mt-3 max-w-3xl text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem]">Danh mục dịch vụ</h1>
                <p className="mt-2 max-w-2xl text-sm font-bold leading-6 text-[#f8e8c8]/75">Quản lý dịch vụ điện, nước, internet, rác, gửi xe và cấu hình cách tính phí cho hóa đơn.</p>
              </div>
              {isSuperAdmin && (
                <button type="button" onClick={openCreateForm} className="inline-flex h-9 items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition-all hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98]">
                  <Plus className="h-4 w-4 stroke-[2.8]" /> Thêm dịch vụ
                </button>
              )}
            </div>

            <div className="relative mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <MetricCard label="Tổng dịch vụ" value={services.length} tone="neutral" />
              <MetricCard label="Hoạt động" value={activeServices} tone="emerald" />
              <MetricCard label="Bắt buộc" value={requiredServices} tone="amber" />
              <MetricCard label="Liên kết dữ liệu" value={relatedRecords} tone="teal" />
            </div>
          </div>
        </section>

        {(errorMessage || successMessage) && (
          <div className={cn('rounded-3xl border px-4 py-3 text-sm font-black shadow-sm', errorMessage ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700')}>
            {errorMessage || successMessage}
          </div>
        )}

        <div className={cn('grid min-w-0 grid-cols-1 gap-4 xl:gap-6', isFormOpen && '2xl:grid-cols-[minmax(0,1fr)_390px]')}>
          <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
            <div className="border-b border-[#3d2a18]/10 bg-[#fff8eb]/85 p-4 sm:p-5">
              <div className="grid gap-3 xl:grid-cols-[minmax(18rem,1fr)_minmax(11rem,13rem)_minmax(12rem,14rem)_minmax(11rem,13rem)_minmax(11rem,13rem)]">
                <div className="relative min-w-0">
                  <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
                  <input type="text" value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="Tìm mã, tên hoặc đơn vị tính dịch vụ..." className={`${inputClass} pl-11 pr-28`} />
                  <button type="button" onClick={clearFilters} disabled={!hasActiveFilters} className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20 disabled:cursor-not-allowed disabled:opacity-45">
                    <X className="h-3.5 w-3.5" /> Xóa lọc
                  </button>
                </div>
                <AdminSelect value={selectedServiceType} options={filterServiceTypeOptions} onChange={(nextValue) => setSelectedServiceType(String(nextValue))} />
                <AdminSelect value={selectedChargeMethod} options={filterChargeMethodOptions} onChange={(nextValue) => setSelectedChargeMethod(String(nextValue))} />
                <AdminSelect value={selectedRequired} options={requiredOptions} onChange={(nextValue) => setSelectedRequired(String(nextValue))} />
                <AdminSelect value={selectedStatus} options={statusOptions} onChange={(nextValue) => setSelectedStatus(String(nextValue))} />
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full min-w-[1120px] text-left">
                <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
                  <tr>
                    <th className="px-5 py-4">Dịch vụ</th>
                    <th className="px-5 py-4">Loại</th>
                    <th className="px-5 py-4">Cách tính phí</th>
                    <th className="px-5 py-4">Đơn vị</th>
                    <th className="px-5 py-4 text-center">Bắt buộc</th>
                    <th className="px-5 py-4 text-center">Dữ liệu</th>
                    <th className="px-5 py-4">Trạng thái</th>
                    <th className="px-5 py-4 text-right">Thao tác</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[#3d2a18]/8 bg-[#fffaf1]/70">
                  {isLoading && Array.from({ length: 5 }).map((_, index) => (
                    <tr key={index}>
                      <td colSpan={8} className="px-5 py-4"><div className="h-14 animate-pulse rounded-2xl bg-stone-100" /></td>
                    </tr>
                  ))}

                  {!isLoading && services.map((service) => {
                    const usedCount = Number(service.prices_count || 0) + Number(service.meter_devices_count || 0) + Number(service.invoice_items_count || 0)

                    return (
                      <tr key={service.id} className="group transition hover:bg-[#f3c56b]/10">
                        <td className="px-5 py-4">
                          <div className="flex items-center gap-3">
                            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#a65f16] shadow-sm transition group-hover:-translate-y-0.5 group-hover:scale-105">
                              <Zap className="h-4 w-4" />
                            </div>
                            <div className="min-w-0">
                              <p className="truncate text-sm font-black tracking-tight text-[#24170d]">{service.name}</p>
                            </div>
                          </div>
                        </td>
                        <td className="px-5 py-4">
                          <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-xs font-black shadow-sm', serviceTypeToneClassNames[service.service_type || 'khac'] || serviceTypeToneClassNames.khac)}>
                            {service.service_type_label || service.service_type}
                          </span>
                        </td>
                        <td className="px-5 py-4">
                          <span className="text-sm font-black text-[#3d2a18]">{service.charge_method_label || service.charge_method}</span>
                        </td>
                        <td className="px-5 py-4">
                          <span className={cn('inline-flex items-center justify-center rounded-full border px-3 py-1 text-xs font-black', service.unit_name ? 'border-[#3d2a18]/10 bg-white/70 text-[#24170d]' : 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]')}>
                            {service.unit_name || 'Chưa khai báo'}
                          </span>
                        </td>
                        <td className="px-5 py-4 text-center">
                          <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-xs font-black shadow-sm', service.is_required ? 'border-[#f3c56b]/40 bg-[#f3c56b]/18 text-[#8a4f18]' : 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]')}>
                            {service.is_required_label || (service.is_required ? 'Bắt buộc' : 'Không bắt buộc')}
                          </span>
                        </td>
                        <td className="px-5 py-4 text-center">
                          <span className="inline-flex items-center justify-center gap-1.5 rounded-full border border-[#0f766e]/15 bg-[#0f766e]/10 px-3 py-1 text-xs font-black text-[#0f5f59] shadow-sm">
                            <Database className="h-3.5 w-3.5" /> <span className="tabular-nums">{usedCount}</span>
                          </span>
                        </td>
                        <td className="px-5 py-4">
                          <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-xs font-black shadow-sm', service.is_active ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]' : 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]')}>
                            {service.is_active_label || (service.is_active ? 'Hoạt động' : 'Ngừng hoạt động')}
                          </span>
                        </td>
                        <td className="px-5 py-4">
                          <div className="flex items-center justify-end gap-2">
                            <button type="button" onClick={() => void viewService(service)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95" title="Xem chi tiết" aria-label={`Xem chi tiết dịch vụ ${service.name}`}><Eye className="h-5 w-5" /></button>
                            {isSuperAdmin && (
                              <>
                                <button type="button" onClick={() => editService(service)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#3d2a18]/10 active:scale-95" title="Chỉnh sửa" aria-label={`Chỉnh sửa dịch vụ ${service.name}`}><Edit3 className="h-5 w-5" /></button>
                                <button type="button" disabled={statusChangingId === service.id} onClick={() => void toggleServiceStatus(service)} className={cn('inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition focus:outline-none focus:ring-4 active:scale-95 disabled:cursor-not-allowed disabled:opacity-55', service.is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 focus:ring-emerald-100' : 'border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 focus:ring-rose-100')} title={service.is_active ? 'Ngừng hoạt động' : 'Kích hoạt'} aria-label={`${service.is_active ? 'Ngừng hoạt động' : 'Kích hoạt'} dịch vụ ${service.name}`}><Power className="h-5 w-5" /></button>
                                <button type="button" onClick={() => void removeService(service)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-95" title="Xóa" aria-label={`Xóa dịch vụ ${service.name}`}><Trash2 className="h-5 w-5" /></button>
                              </>
                            )}
                          </div>
                        </td>
                      </tr>
                    )
                  })}

                  {!isLoading && services.length === 0 && (
                    <tr>
                      <td colSpan={8} className="px-5 py-20 text-center">
                        <div className="mx-auto flex max-w-sm flex-col items-center rounded-[2rem] border border-dashed border-[#3d2a18]/12 bg-white/55 px-6 py-8">
                          <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]"><Zap className="h-9 w-9" /></div>
                          <p className="text-lg font-black tracking-tight text-[#24170d]">Không tìm thấy dịch vụ</p>
                          <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">{hasActiveFilters ? 'Thử xóa bộ lọc hoặc đổi từ khóa tìm kiếm.' : 'Hãy tạo dịch vụ mới hoặc kiểm tra lại dữ liệu hiện tại.'}</p>
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

          {isFormOpen && isSuperAdmin && (
            <aside className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/95 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md 2xl:sticky 2xl:top-6 2xl:self-start">
              <div className="border-b border-[#3d2a18]/10 bg-[#fff8eb]/85 p-5">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <h2 className="mt-1 text-xl font-black tracking-tight text-[#24170d]">{editingService ? 'Cập nhật dịch vụ' : 'Thêm dịch vụ'}</h2>
                  </div>
                  <div className="flex items-center gap-2">
                    <button type="button" onClick={resetForm} className="rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] p-2 text-[#8b5e34] transition hover:bg-[#f3c56b]/15" title="Làm mới form" aria-label="Làm mới form dịch vụ">
                      <RefreshCw className="h-4 w-4" />
                    </button>
                    <button type="button" onClick={() => { resetForm(); setIsFormOpen(false) }} className="rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] p-2 text-[#8b5e34] transition hover:bg-rose-50 hover:text-rose-600" title="Đóng form" aria-label="Đóng form dịch vụ">
                      <X className="h-4 w-4" />
                    </button>
                  </div>
                </div>
              </div>

              <div className="space-y-4 p-5">
                <div>
                  <label className={labelClass}>Mã dịch vụ</label>
                  <input className={`${inputClass} ${errors.service_code ? inputErrorClass : ''}`} value={form.service_code} onChange={(event) => updateForm('service_code', event.target.value)} placeholder="Ví dụ: SV-DIEN" />
                  <FieldError message={errors.service_code} />
                </div>
                <div>
                  <label className={labelClass}>Tên dịch vụ</label>
                  <input className={`${inputClass} ${errors.name ? inputErrorClass : ''}`} value={form.name} onChange={(event) => updateForm('name', event.target.value)} placeholder="Ví dụ: Điện sinh hoạt" />
                  <FieldError message={errors.name} />
                </div>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-1">
                  <div>
                    <label className={labelClass}>Loại dịch vụ</label>
                    <AdminSelect value={form.service_type} options={serviceTypeOptions} invalid={!!errors.service_type} onChange={(nextValue) => updateForm('service_type', String(nextValue))} />
                    <FieldError message={errors.service_type} />
                  </div>
                  <div>
                    <label className={labelClass}>Cách tính phí</label>
                    <AdminSelect value={form.charge_method} options={chargeMethodOptions} invalid={!!errors.charge_method} onChange={(nextValue) => updateForm('charge_method', Number(nextValue))} />
                    <FieldError message={errors.charge_method} />
                  </div>
                </div>
                <div>
                  <label className={labelClass}>Đơn vị tính</label>
                  <input className={`${inputClass} ${errors.unit_name ? inputErrorClass : ''}`} value={form.unit_name} onChange={(event) => updateForm('unit_name', event.target.value)} placeholder="Ví dụ: kWh, m³, phòng, người" />
                  <FieldError message={errors.unit_name} />
                </div>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-1">
                  <div>
                    <label className={labelClass}>Bắt buộc</label>
                    <AdminSelect value={form.is_required ? 1 : 0} options={formRequiredOptions} invalid={!!errors.is_required} onChange={(nextValue) => updateForm('is_required', Number(nextValue) === 1)} />
                    <FieldError message={errors.is_required} />
                  </div>
                  <div>
                    <label className={labelClass}>Trạng thái</label>
                    <AdminSelect value={form.is_active ? 1 : 0} options={formStatusOptions} invalid={!!errors.is_active} onChange={(nextValue) => updateForm('is_active', Number(nextValue) === 1)} />
                    <FieldError message={errors.is_active} />
                  </div>
                </div>

                <div className="flex flex-col gap-3 pt-2 sm:flex-row 2xl:flex-col">
                  <button type="button" disabled={isSaving} onClick={() => void submit()} className="inline-flex min-h-14 flex-1 items-center justify-center gap-2 rounded-[1.25rem] bg-[#24170d] px-5 py-3.5 text-base font-black text-[#fff4df] shadow-lg shadow-[#24170d]/12 transition hover:bg-[#3d2a18] disabled:cursor-not-allowed disabled:opacity-60">
                    {isSaving ? 'Đang lưu...' : editingService ? 'Cập nhật' : 'Tạo dịch vụ'}
                  </button>
                </div>
              </div>
            </aside>
          )}
        </div>
      </div>

      {isDetailOpen && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="service-detail-title">
          <button type="button" aria-label="Đóng chi tiết dịch vụ" onClick={closeServiceDetail} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />
          <div className="relative z-10 w-full max-w-3xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-stone-950/30">
            <div className="bg-[#24170d] p-5 text-[#fff4df]">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-[10px] font-black uppercase tracking-[0.24em] text-[#f3c56b]">Service detail</p>
                  <h2 id="service-detail-title" className="mt-2 text-2xl font-black tracking-tight">{detailService?.name || 'Đang tải chi tiết...'}</h2>
                  <p className="mt-1 text-xs font-black uppercase tracking-[0.18em] text-[#f8e8c8]/72">{detailService?.service_code}</p>
                </div>
                <button type="button" onClick={closeServiceDetail} className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20" aria-label="Đóng chi tiết dịch vụ">
                  <X className="h-5 w-5" />
                </button>
              </div>
            </div>

            <div className="space-y-4 p-5">
              {isDetailLoading && <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-800">Đang tải chi tiết dịch vụ...</div>}
              {detailErrorMessage && <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{detailErrorMessage}</div>}

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <DetailTile label="Loại dịch vụ" value={detailService?.service_type_label || detailService?.service_type || '—'} />
                <DetailTile label="Cách tính" value={detailService?.charge_method_label || detailService?.charge_method || '—'} />
                <DetailTile label="Đơn vị" value={detailService?.unit_name || 'Chưa khai báo'} />
                <DetailTile label="Trạng thái" value={detailService?.is_active_label || (detailService?.is_active ? 'Hoạt động' : 'Ngừng hoạt động')} />
              </div>

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <DetailTile label={isSuperAdmin ? 'Bảng giá' : 'Bảng giá tòa quản lý'} value={detailService?.prices_count ?? 0} />
                <DetailTile label={isSuperAdmin ? 'Thiết bị đo' : 'Thiết bị đo tòa quản lý'} value={detailService?.meter_devices_count ?? 0} />
                <DetailTile label={isSuperAdmin ? 'Dòng hóa đơn' : 'Dòng hóa đơn tòa quản lý'} value={detailService?.invoice_items_count ?? 0} />
              </div>

              <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
                <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">{isSuperAdmin ? 'Bảng giá đã khai báo' : 'Bảng giá thuộc tòa nhà bạn quản lý'}</p>
                <div className="mt-3 max-h-60 space-y-2 overflow-y-auto pr-1">
                  {detailService?.prices?.map((price) => (
                    <div key={price.id} className="grid gap-2 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-3 py-2 text-sm font-bold text-[#24170d] sm:grid-cols-[1fr_auto_auto] sm:items-center">
                      <span>{price.building_name || 'Chưa gán tòa nhà'}</span>
                      <span className="tabular-nums text-[#0f5f59]">{formatCurrency(price.price)}</span>
                      <span className="text-xs text-[#8b5e34]">{price.status_label || 'Chưa rõ trạng thái'}</span>
                    </div>
                  ))}
                  {(!detailService?.prices || detailService.prices.length === 0) && <p className="text-sm font-semibold text-[#6f6254]">{isSuperAdmin ? 'Chưa có bảng giá nào cho dịch vụ này.' : 'Chưa có bảng giá nào thuộc tòa nhà bạn quản lý.'}</p>}
                </div>
              </section>
            </div>
          </div>
        </div>
      )}
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

function formatCurrency(value: string | null | undefined) {
  const [integerPart, decimalPart] = String(value || '0').split('.')
  const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  const decimalText = decimalPart && !/^0+$/.test(decimalPart) ? `,${decimalPart}` : ''

  return `${formattedInteger}${decimalText} ₫`
}
