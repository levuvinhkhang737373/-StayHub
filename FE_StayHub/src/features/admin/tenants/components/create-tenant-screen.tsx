import { useEffect, useMemo, useState } from 'react'
import { createPortal } from 'react-dom'
import { useNavigate, useParams, Navigate } from 'react-router-dom'
import { ArrowLeft, Camera, IdCard, Save, UploadCloud, UserRound, X } from 'lucide-react'
import { ApiError, type ApiValidationErrors } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { getVisibleErrorMessage } from '../../shared/utils/error-message'
import { AdminDateInput } from '../../../../shared/components/AdminDateInput'
import {
  createAdminTenant,
  fetchAdminTenantDetail,
  updateAdminTenant,
} from '../services/tenants.service'
import type { AdminTenantPayload } from '../types/tenant-api.model'
import { validateTenantForm, type TenantFormErrors, type TenantFormValues } from '../validations/tenant.validation'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import { GENDER_FEMALE, GENDER_MALE } from '../../shared/config/gender-policy'

const STATUS_RENTING = 1
const STATUS_STOPPED_RENTING = 2
const IDENTITY_TYPE_CCCD = 1
const IDENTITY_TYPE_PASSPORT = 3

const defaultForm: TenantFormValues = {
  building_id: '',
  username: '',
  full_name: '',
  email: '',
  phone: '',
  date_of_birth: '',
  gender: GENDER_MALE,
  status: STATUS_RENTING,
  identity_type: IDENTITY_TYPE_CCCD,
  identity_number: '',
  permanent_address: '',
  current_address: '',
  front_image: null,
  back_image: null,
  delete_front_image: false,
  delete_back_image: false,
}

const formStatusOptions = [
  { value: STATUS_RENTING, label: 'Đang thuê', tone: 'success' as const },
  { value: STATUS_STOPPED_RENTING, label: 'Ngừng thuê', tone: 'danger' as const },
]

const formGenderOptions = [
  { value: GENDER_MALE, label: 'Nam', tone: 'default' as const },
  { value: GENDER_FEMALE, label: 'Nữ', tone: 'default' as const },
]

const formIdentityTypeOptions = [
  { value: IDENTITY_TYPE_CCCD, label: 'CCCD', tone: 'success' as const },
  { value: IDENTITY_TYPE_PASSPORT, label: 'Hộ chiếu', tone: 'default' as const },
]

const formErrorKeys: Array<keyof TenantFormValues> = [
  'building_id',
  'username',
  'full_name',
  'email',
  'phone',
  'date_of_birth',
  'gender',
  'status',
  'identity_type',
  'identity_number',
  'permanent_address',
  'current_address',
  'front_image',
  'back_image',
  'delete_front_image',
  'delete_back_image',
]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'
const inputErrorClass = 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100'
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65'

function FieldError({ message }: { message?: string }) {
  if (!message) return null
  return <p className="mt-1.5 px-1 text-xs font-bold text-rose-600" role="alert">{message}</p>
}

export function CreateTenantScreen() {
  const { session } = useAdminSession()
  const navigate = useNavigate()
  const { tenantId } = useParams()
  const isEditMode = tenantId !== undefined

  const isSuperAdmin = useMemo(() => isSuperAdminRole(session?.admin?.role), [session])
  const defaultBuildingId = session?.admin?.managed_buildings?.[0]?.id

  const [form, setForm] = useState<TenantFormValues>(defaultForm)
  const [errors, setErrors] = useState<TenantFormErrors>({})
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [isSaving, setIsSaving] = useState(false)
  const [isLoading, setIsLoading] = useState(isEditMode)
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [buildingOptions, setBuildingOptions] = useState<{ value: string | number; label: string; tone: 'default' }[]>([])
  const [existingTenant, setExistingTenant] = useState<any | null>(null)

  const selectedBuildingGenderPolicy = useMemo(() => {
    const selectedBuildingId = Number(isSuperAdmin ? form.building_id : defaultBuildingId)
    if (!selectedBuildingId) return null

    const loadedBuilding = buildings.find((building) => building.id === selectedBuildingId)
    const managedBuilding = session?.admin?.managed_buildings?.find((building) => building.id === selectedBuildingId)

    return loadedBuilding?.gender_policy ?? managedBuilding?.gender_policy ?? null
  }, [buildings, defaultBuildingId, form.building_id, isSuperAdmin, session?.admin?.managed_buildings])

  useEffect(() => {
    fetchAdminBuildings().then((response) => {
      const result = response.result as AdminBuildingResource[] | { data?: AdminBuildingResource[] } | null | undefined
      const list = Array.isArray(result) ? result : result?.data || []
      setBuildings(list)
      setBuildingOptions([
        { value: '', label: 'Chọn tòa nhà', tone: 'default' },
        ...list.map(b => ({ value: b.id, label: b.name, tone: 'default' as const }))
      ])
    }).catch(console.error)
  }, [])

  useEffect(() => {
    if (isEditMode) return

    const selectedBuildingId = Number(isSuperAdmin ? form.building_id : defaultBuildingId)
    if (!selectedBuildingId) return

    const building = buildings.find(b => b.id === selectedBuildingId) ||
                     session?.admin?.managed_buildings?.find(b => b.id === selectedBuildingId)

    if (building?.address) {
      setForm(current => ({
        ...current,
        current_address: building.address || ''
      }))
    }
  }, [form.building_id, buildings, isEditMode, isSuperAdmin, defaultBuildingId, session?.admin?.managed_buildings])

  useEffect(() => {
    if (isEditMode && tenantId) {
      setIsLoading(true)
      fetchAdminTenantDetail(Number(tenantId))
        .then((response) => {
          const tenant = response.result
          if (tenant) {
            setExistingTenant(tenant)
            setForm({
              building_id: tenant.building_id || '',
              username: tenant.username || '',
              full_name: tenant.full_name || '',
              email: tenant.email || '',
              phone: tenant.phone || '',
              date_of_birth: tenant.date_of_birth || '',
              gender: Number(tenant.gender || GENDER_MALE),
              status: Number(tenant.status || STATUS_RENTING),
              identity_type: Number(tenant.identity_type || IDENTITY_TYPE_CCCD),
              identity_number: tenant.identity_number || '',
              permanent_address: tenant.permanent_address || '',
              current_address: tenant.current_address || '',
              front_image: null,
              back_image: null,
              delete_front_image: false,
              delete_back_image: false,
            })
          }
        })
        .catch((error) => {
          setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải thông tin khách thuê.'))
        })
        .finally(() => {
          setIsLoading(false)
        })
    }
  }, [isEditMode, tenantId])

  const updateForm = (key: keyof TenantFormValues, value: string | number | boolean | File | null) => {
    setForm((current) => ({ ...current, [key]: value }))
    setErrors((current) => ({ ...current, [key]: undefined }))
  }

  const applyApiValidationErrors = (validationErrors: ApiValidationErrors) => {
    const nextErrors: TenantFormErrors = {}
    formErrorKeys.forEach((key) => {
      const messages = validationErrors[key]
      if (messages?.[0]) {
        nextErrors[key] = messages[0]
      }
    })
    setErrors(nextErrors)
  }

  const submit = async () => {
    if (isSaving) return

    const nextErrors = validateTenantForm(form, isSuperAdmin, selectedBuildingGenderPolicy)
    setErrors(nextErrors)

    if (Object.keys(nextErrors).length > 0) {
      setErrorMessage('Vui lòng kiểm tra lại thông tin khách thuê.')
      return
    }

    try {
      setIsSaving(true)
      setErrorMessage(null)

      const payload: AdminTenantPayload = {
        building_id: isSuperAdmin ? (form.building_id ? Number(form.building_id) : undefined) : defaultBuildingId,
        username: form.username.trim(),
        full_name: form.full_name.trim(),
        email: form.email.trim(),
        phone: form.phone.trim(),
        date_of_birth: form.date_of_birth,
        gender: Number(form.gender),
        status: Number(form.status),
        identity_type: Number(form.identity_type),
        identity_number: form.identity_number.trim(),
        permanent_address: form.permanent_address.trim() || null,
        current_address: form.current_address.trim() || null,
        front_image: form.front_image,
        back_image: form.back_image,
        delete_front_image: form.delete_front_image,
        delete_back_image: form.delete_back_image,
      }

      if (isEditMode && tenantId) {
        await updateAdminTenant(Number(tenantId), payload)
      } else {
        await createAdminTenant(payload)
      }

      navigate('/admin/tenants', {
        state: { success: isEditMode ? 'Cập nhật khách thuê thành công.' : 'Tạo khách thuê thành công. Mật khẩu đã được gửi qua email.' }
      })
    } catch (error) {
      if (error instanceof ApiError && error.validationErrors) {
        applyApiValidationErrors(error.validationErrors)
      }
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể lưu khách thuê.'))
    } finally {
      setIsSaving(false)
    }
  }

  if (!session?.admin) return <Navigate to="/admin/login" replace />

  return (
    <div className="space-y-6 text-[#24170d]">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <button onClick={() => navigate(-1)} className="mb-3 inline-flex items-center gap-2 text-sm font-bold text-[#8b5e34] hover:text-[#24170d] transition">
            <ArrowLeft className="h-4 w-4" /> Quay lại danh sách
          </button>
          <h1 className="text-3xl font-black tracking-tight text-[#24170d]">
            {isEditMode ? 'Chỉnh sửa khách thuê' : 'Thêm khách thuê'}
          </h1>
        </div>
        <div className="flex gap-2">
          <button onClick={() => navigate(-1)} className="inline-flex items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-5 py-3 text-xs font-black uppercase tracking-widest text-[#6f6254] transition hover:bg-[#efe2cf]">
            Hủy
          </button>
          <button onClick={() => void submit()} disabled={isSaving || isLoading} className="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#24170d] px-5 py-3 text-xs font-black uppercase tracking-widest text-[#fff4df] shadow-xl shadow-[#24170d]/10 transition hover:bg-[#3d2a18] disabled:opacity-50">
            <Save className="h-4 w-4 text-[#f3c56b] stroke-[2.8]" />
            {isSaving ? 'Đang lưu...' : isEditMode ? 'Cập nhật' : 'Lưu khách thuê'}
          </button>
        </div>
      </div>

      {errorMessage && (
        <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-700">
          {errorMessage}
        </div>
      )}

      {isLoading ? (
        <div className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-10 text-center font-black text-[#8b5e34]/70 shadow-xl shadow-[#6b3f1d]/8">
          Đang tải thông tin khách thuê...
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          {/* Left / Main Form Column */}
          <div className="space-y-6 lg:col-span-2">
            <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-6 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
              <div className="mb-6 flex items-center gap-3 border-b border-[#3d2a18]/10 pb-4">
                <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#24170d] text-[#fff4df]">
                  <UserRound className="h-5 w-5 text-[#f3c56b]" />
                </div>
                <div>
                  <h2 className="font-black text-[#24170d]">Thông tin tài khoản và cá nhân</h2>
                  <p className="mt-0.5 text-xs font-semibold text-[#8b5e34]/70">Họ tên, ngày sinh, giới tính và thông tin đăng nhập.</p>
                </div>
              </div>

              <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                {isSuperAdmin && (
                  <div className="md:col-span-2">
                    <label className={labelClass}>Tòa nhà <span className="text-rose-500">*</span></label>
                    <AdminSelect value={form.building_id || ''} options={buildingOptions} invalid={!!errors.building_id} onChange={(nextValue) => updateForm('building_id', nextValue)} />
                    <FieldError message={errors.building_id} />
                  </div>
                )}
                <div>
                  <label className={labelClass}>Tên đăng nhập <span className="text-rose-500">*</span></label>
                  <input className={cn(inputClass, errors.username && inputErrorClass)} value={form.username} onChange={(event) => updateForm('username', event.target.value)} placeholder="Ví dụ: tenant_nguyenvana" autoComplete="off" disabled={isEditMode} />
                  <FieldError message={errors.username} />
                </div>
                <div>
                  <label className={labelClass}>Họ tên <span className="text-rose-500">*</span></label>
                  <input className={cn(inputClass, errors.full_name && inputErrorClass)} value={form.full_name} onChange={(event) => updateForm('full_name', event.target.value)} placeholder="Nhập họ tên khách thuê" />
                  <FieldError message={errors.full_name} />
                </div>
                <div>
                  <label className={labelClass}>Email <span className="text-rose-500">*</span></label>
                  <input className={cn(inputClass, errors.email && inputErrorClass)} value={form.email} onChange={(event) => updateForm('email', event.target.value)} placeholder="tenant@stayhub.vn" type="email" />
                  <FieldError message={errors.email} />
                </div>
                <div>
                  <label className={labelClass}>Số điện thoại <span className="text-rose-500">*</span></label>
                  <input className={cn(inputClass, errors.phone && inputErrorClass)} value={form.phone} onChange={(event) => updateForm('phone', event.target.value.replace(/\D/g, ''))} placeholder="Nhập số điện thoại" maxLength={10} />
                  <FieldError message={errors.phone} />
                </div>
                <div>
                  <label className={labelClass}>Ngày sinh <span className="text-rose-500">*</span></label>
                  <AdminDateInput
                    className={cn(inputClass, errors.date_of_birth && inputErrorClass)}
                    value={form.date_of_birth}
                    onChange={(value) => updateForm('date_of_birth', value)}
                    maxDate={new Date()}
                  />
                  <FieldError message={errors.date_of_birth} />
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className={labelClass}>Giới tính</label>
                    <AdminSelect value={form.gender} options={formGenderOptions} invalid={!!errors.gender} onChange={(nextValue) => updateForm('gender', Number(nextValue))} />
                    <FieldError message={errors.gender} />
                  </div>
                  <div>
                    <label className={labelClass}>Trạng thái</label>
                    <AdminSelect value={form.status} options={formStatusOptions} invalid={!!errors.status} onChange={(nextValue) => updateForm('status', Number(nextValue))} />
                    <FieldError message={errors.status} />
                  </div>
                </div>
              </div>
            </section>

            <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-6 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
              <div className="mb-6 flex items-center gap-3 border-b border-[#3d2a18]/10 pb-4">
                <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#24170d] text-[#fff4df]">
                  <IdCard className="h-5 w-5 text-[#f3c56b]" />
                </div>
                <div>
                  <h2 className="font-black text-[#24170d]">Giấy tờ tùy thân</h2>
                  <p className="mt-0.5 text-xs font-semibold text-[#8b5e34]/70">Thông tin giấy tờ CCCD hoặc Hộ chiếu.</p>
                </div>
              </div>

              <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                  <label className={labelClass}>Loại giấy tờ</label>
                  <AdminSelect
                    value={form.identity_type}
                    options={formIdentityTypeOptions}
                    invalid={!!errors.identity_type}
                    onChange={(nextValue) => {
                      const nextType = Number(nextValue)
                      updateForm('identity_type', nextType)
                      let val = form.identity_number
                      if (nextType === 1) {
                        val = val.replace(/\D/g, '').slice(0, 12)
                      } else if (nextType === 3) {
                        val = val.replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 9)
                      }
                      updateForm('identity_number', val)
                    }}
                  />
                  <FieldError message={errors.identity_type} />
                </div>
                <div>
                  <label className={labelClass}>Số giấy tờ <span className="text-rose-500">*</span></label>
                  <input
                    className={cn(inputClass, errors.identity_number && inputErrorClass)}
                    value={form.identity_number}
                    onChange={(event) => {
                      let val = event.target.value
                      const type = Number(form.identity_type)
                      if (type === 1) {
                        val = val.replace(/\D/g, '')
                      } else if (type === 3) {
                        val = val.replace(/[^A-Za-z0-9]/g, '').toUpperCase()
                      }
                      updateForm('identity_number', val)
                    }}
                    placeholder={Number(form.identity_type) === 1 ? "Nhập 12 số CCCD" : "Nhập 9 ký tự hộ chiếu"}
                    maxLength={Number(form.identity_type) === 1 ? 12 : Number(form.identity_type) === 3 ? 9 : 30}
                  />
                  <FieldError message={errors.identity_number} />
                </div>
                <div className="md:col-span-2">
                  <label className={labelClass}>Địa chỉ thường trú</label>
                  <textarea className={cn(inputClass, 'min-h-20 resize-none', errors.permanent_address && inputErrorClass)} value={form.permanent_address} onChange={(event) => updateForm('permanent_address', event.target.value)} placeholder="Nhập địa chỉ thường trú" />
                  <FieldError message={errors.permanent_address} />
                </div>
                <div className="md:col-span-2">
                  <label className={labelClass}>Địa chỉ hiện tại</label>
                  <textarea className={cn(inputClass, 'min-h-20 resize-none', errors.current_address && inputErrorClass)} value={form.current_address} onChange={(event) => updateForm('current_address', event.target.value)} placeholder="Nhập địa chỉ hiện tại" />
                  <FieldError message={errors.current_address} />
                </div>
              </div>
            </section>
          </div>

          {/* Right Column (Files) */}
          <div className="space-y-6">
            <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-6 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
              <div className="mb-6 flex items-center gap-3 border-b border-[#3d2a18]/10 pb-4">
                <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#24170d] text-[#fff4df]">
                  <UploadCloud className="h-5 w-5 text-[#f3c56b]" />
                </div>
                <div>
                  <h2 className="font-black text-[#24170d]">Tài liệu ảnh đính kèm</h2>
                  <p className="mt-0.5 text-xs font-semibold text-[#8b5e34]/70">Đăng tải ảnh mặt trước/sau giấy tờ CCCD.</p>
                </div>
              </div>

              <div className="space-y-5">
                <FileInputField
                  label="Ảnh mặt trước CCCD"
                  file={form.front_image}
                  currentUrl={existingTenant?.front_image_url}
                  deleteChecked={form.delete_front_image}
                  error={errors.front_image}
                  onFileChange={(file) => updateForm('front_image', file)}
                  onDeleteChange={(checked) => updateForm('delete_front_image', checked)}
                />
                <FileInputField
                  label="Ảnh mặt sau CCCD"
                  file={form.back_image}
                  currentUrl={existingTenant?.back_image_url}
                  deleteChecked={form.delete_back_image}
                  error={errors.back_image}
                  onFileChange={(file) => updateForm('back_image', file)}
                  onDeleteChange={(checked) => updateForm('delete_back_image', checked)}
                />
              </div>
            </section>
          </div>
        </div>
      )}
    </div>
  )
}

function FileInputField({
  label,
  file,
  currentUrl,
  deleteChecked,
  error,
  onFileChange,
  onDeleteChange,
}: {
  label: string
  file: File | null
  currentUrl?: string | null
  deleteChecked: boolean
  error?: string
  onFileChange: (file: File | null) => void
  onDeleteChange: (checked: boolean) => void
}) {
  const previewUrl = useMemo(() => (file ? URL.createObjectURL(file) : null), [file])
  const [isZoomed, setIsZoomed] = useState(false)

  useEffect(() => {
    return () => {
      if (previewUrl) URL.revokeObjectURL(previewUrl)
    }
  }, [previewUrl])

  useEffect(() => {
    if (!isZoomed) return

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setIsZoomed(false)
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [isZoomed])

  const visibleUrl = previewUrl || (!deleteChecked ? currentUrl : null)

  const handleRemove = () => {
    if (previewUrl) {
      onFileChange(null)
    } else if (currentUrl) {
      onDeleteChange(true)
    }
  }

  const handleUndoDelete = () => {
    onDeleteChange(false)
  }

  return (
    <>
      <div className="rounded-2xl border border-[#3d2a18]/10 bg-white/55 p-3">
        <div className="mb-1.5 flex items-center justify-between">
          <label className={labelClass}>{label}</label>
          {deleteChecked && !previewUrl && currentUrl && (
            <button type="button" onClick={handleUndoDelete} className="text-[10px] font-black text-[#a65f16] hover:text-[#8b5e34] hover:underline">
              Hoàn tác xóa
            </button>
          )}
        </div>
        {visibleUrl ? (
          <div className="relative mb-3 group">
            <img src={visibleUrl} alt={label} onClick={() => setIsZoomed(true)} className="h-32 w-full rounded-2xl border border-[#3d2a18]/10 object-cover cursor-zoom-in transition hover:opacity-90" />
            <button type="button" onClick={handleRemove} className="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-rose-500/90 text-white shadow-sm backdrop-blur-sm transition hover:bg-rose-600 hover:scale-105 focus:outline-none focus:ring-2 focus:ring-rose-400 sm:opacity-0 sm:group-hover:opacity-100" title="Xóa ảnh">
              <X className="h-4 w-4" />
            </button>
          </div>
        ) : (
          <div className="mb-3 flex h-24 w-full items-center justify-center rounded-2xl border border-dashed border-[#3d2a18]/12 bg-[#efe2cf]/45 text-[#8b5e34]">
            <Camera className="h-6 w-6" />
          </div>
        )}
        <label className="flex cursor-pointer items-center justify-center gap-2 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/15">
          <UploadCloud className="h-4 w-4" /> {file ? 'Chọn ảnh khác' : 'Chọn ảnh'}
          <input type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={(event) => {
            onFileChange(event.target.files?.[0] ?? null)
            if (deleteChecked) onDeleteChange(false)
          }} />
        </label>
        {file && <p className="mt-2 truncate text-xs font-bold text-[#6f6254]">{file.name}</p>}
        <FieldError message={error} />
      </div>

      {isZoomed && visibleUrl && createPortal(
        <div
          className="fixed inset-0 z-[100] flex items-center justify-center bg-stone-950/80 p-4 backdrop-blur-sm cursor-zoom-out"
          onClick={() => setIsZoomed(false)}
        >
          <button
            type="button"
            className="absolute right-6 top-6 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur-md transition hover:bg-white/20"
            onClick={(e) => { e.stopPropagation(); setIsZoomed(false) }}
            aria-label="Đóng ảnh"
          >
            <X className="h-6 w-6" />
          </button>
          <img
            src={visibleUrl}
            alt={label}
            className="max-h-[75vh] max-w-[90vw] lg:max-w-[70vw] rounded-lg object-contain shadow-2xl cursor-default"
            onClick={(e) => e.stopPropagation()}
          />
        </div>,
        document.body
      )}
    </>
  )
}
