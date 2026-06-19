import { ArrowLeft, Save, UserCog } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { AdminDateInput } from '../../../../shared/components/AdminDateInput'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import {
  createAdminAccount,
  fetchAdminAccountDetail,
  updateAdminAccount,
} from '../services/admin-accounts.service'
import type { AdminAccountPayload } from '../types/admin-account-api.model'
import { validateAdminAccountForm, type AdminAccountFormErrors, type AdminAccountFormValues } from '../validations/admin-account.validation'

const STATUS_ACTIVE = 1
const STATUS_INACTIVE = 2
const ROLE_BUILDING_MANAGER = 1
const ROLE_SUPER_ADMIN = 2
const ROLE_TECHNICIAN = 3

const defaultForm: AdminAccountFormValues = {
  username: '',
  full_name: '',
  email: '',
  phone: '',
  password: '',
  role: ROLE_BUILDING_MANAGER,
  status: STATUS_ACTIVE,
  gender: null,
  date_of_birth: '',
  address: '',
  avatar_url: '',
}

const roleOptions = [
  { value: ROLE_BUILDING_MANAGER, label: 'Quản lí tòa nhà', tone: 'success' as const },
  { value: ROLE_SUPER_ADMIN, label: 'Quản trị tổng', tone: 'warning' as const },
  { value: ROLE_TECHNICIAN, label: 'Kỹ thuật', tone: 'default' as const },
]

const statusOptions = [
  { value: STATUS_ACTIVE, label: 'Hoạt động', tone: 'success' as const },
  { value: STATUS_INACTIVE, label: 'Ngừng hoạt động', tone: 'danger' as const },
]

const genderOptions = [
  { value: '', label: 'Chưa chọn', tone: 'default' as const },
  { value: 1, label: 'Nam', tone: 'default' as const },
  { value: 2, label: 'Nữ', tone: 'default' as const },
]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20 disabled:opacity-50'
const inputErrorClass = 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100'
const readOnlyInputClass = 'cursor-not-allowed border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254] focus:border-[#3d2a18]/10 focus:ring-0'
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65'

export function CreateSystemUserScreen() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const isEditMode = Boolean(id)

  const [form, setForm] = useState<AdminAccountFormValues>(defaultForm)
  const [errors, setErrors] = useState<AdminAccountFormErrors>({})
  const [isLoading, setIsLoading] = useState(isEditMode)
  const [isSaving, setIsSaving] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  useEffect(() => {
    if (!isEditMode) return

    const loadAccountDetail = async () => {
      try {
        setIsLoading(true)
        const response = await fetchAdminAccountDetail(Number(id))
        const account = response.result
        if (account) {
          setForm({
            username: account.username || '',
            full_name: account.full_name || '',
            email: account.email || '',
            phone: account.phone || '',
            password: '',
            role: Number(account.role || ROLE_BUILDING_MANAGER),
            status: Number(account.status || STATUS_ACTIVE),
            gender: account.gender ? Number(account.gender) : null,
            date_of_birth: account.date_of_birth || '',
            address: account.address || '',
            avatar_url: account.avatar_url || '',
          })
        }
      } catch (error) {
        console.error(error)
        setErrorMessage('Không thể tải thông tin tài khoản.')
      } finally {
        setIsLoading(false)
      }
    }

    void loadAccountDetail()
  }, [id, isEditMode])

  const updateForm = (key: keyof AdminAccountFormValues, value: string | number | null) => {
    setForm((current) => ({ ...current, [key]: value }))
    setErrors((current) => ({ ...current, [key]: undefined }))
    setErrorMessage(null)
  }

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (isSaving) return

    const nextErrors = validateAdminAccountForm(form, isEditMode)
    setErrors(nextErrors)

    if (Object.keys(nextErrors).length > 0) {
      setErrorMessage('Vui lòng kiểm tra lại thông tin biểu mẫu.')
      return
    }

    try {
      setIsSaving(true)
      setErrorMessage(null)

      const payload: AdminAccountPayload = {
        full_name: form.full_name.trim(),
        email: form.email.trim(),
        phone: form.phone.trim(),
        role: Number(form.role),
        gender: form.gender === null ? undefined : Number(form.gender),
        date_of_birth: form.date_of_birth || null,
        address: form.address.trim() || null,
        avatar_url: form.avatar_url.trim() || null,
      }

      if (!isEditMode) {
        payload.username = form.username.trim()
      }

      if (isEditMode && form.password.trim()) {
        payload.password = form.password.trim()
      }

      if (isEditMode) {
        await updateAdminAccount(Number(id), payload)
        alert('Cập nhật tài khoản admin thành công!')
      } else {
        await createAdminAccount({ ...payload, status: Number(form.status) })
        alert('Tạo tài khoản admin thành công! Mật khẩu đã được gửi qua email.')
      }

      navigate('/admin/system-users')
    } catch (error: any) {
      console.error(error)
      if (error instanceof ApiError && error.validationErrors) {
        const nextErrors: AdminAccountFormErrors = {}
        const formErrorKeys: Array<keyof AdminAccountFormValues> = ['username', 'full_name', 'email', 'phone', 'password', 'role', 'status', 'gender', 'date_of_birth', 'address', 'avatar_url']
        formErrorKeys.forEach((key) => {
          const messages = error.validationErrors?.[key]
          if (messages?.[0]) {
            nextErrors[key] = messages[0]
          }
        })
        setErrors(nextErrors)
      } else {
        setErrorMessage(error?.response?.data?.message || error?.message || 'Có lỗi xảy ra.')
      }
    } finally {
      setIsSaving(false)
    }
  }

  if (isLoading) {
    return (
      <div className="flex h-64 items-center justify-center text-[#8b5e34]">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-[#8b5e34] border-t-transparent"></div>
        <span className="ml-2 font-black text-sm">Đang tải dữ liệu tài khoản...</span>
      </div>
    )
  }

  return (
    <form onSubmit={submit} className="space-y-6 text-[#24170d]">
      {/* Premium Header */}
      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-5 text-[#fff4df] lg:p-6">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_14%,rgba(243,197,107,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(15,118,110,0.26),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
          <div className="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <button
                type="button"
                onClick={() => navigate('/admin/system-users')}
                className="inline-flex items-center gap-2 text-xs font-black text-[#f3c56b] transition hover:text-[#ffd56f]"
              >
                <ArrowLeft className="h-3.5 w-3.5" /> Quay lại danh sách
              </button>
              <h1 className="mt-4 flex items-center gap-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl">
                <UserCog className="h-9 w-9 text-[#f3c56b]" /> {isEditMode ? 'Cập nhật tài khoản' : 'Thêm tài khoản'}
              </h1>
              <p className="mt-2 max-w-3xl text-sm font-semibold text-[#f8e8c8]/75">
                {isEditMode ? `Chỉnh sửa thông tin tài khoản admin @${form.username}` : 'Điền đầy đủ thông tin để tạo tài khoản admin mới.'}
              </p>
            </div>
            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => navigate('/admin/system-users')}
                className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/10 px-4 text-sm font-black text-[#fff4df] transition hover:bg-white/20"
              >
                Hủy
              </button>
              <button
                type="submit"
                disabled={isSaving}
                className="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] transition hover:bg-[#ffd56f] disabled:opacity-60"
              >
                <Save className="h-4 w-4" /> {isSaving ? 'Đang lưu...' : isEditMode ? 'Cập nhật' : 'Lưu lại'}
              </button>
            </div>
          </div>
        </div>
      </section>

      {errorMessage && (
        <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-700">
          {errorMessage}
        </div>
      )}

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Left columns - Account Information */}
        <div className="lg:col-span-2 space-y-6">
          <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-6 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
            <div className="mb-6 flex items-center gap-3 border-b border-[#3d2a18]/10 pb-4">
              <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#24170d] text-[#fff4df]">
                <UserCog className="h-5 w-5 text-[#f3c56b]" />
              </div>
              <div>
                <h2 className="font-black text-[#24170d]">Thông tin tài khoản</h2>
                <p className="mt-0.5 text-xs font-semibold text-[#8b5e34]/70">Cấu hình các trường thông tin cơ bản cho tài khoản admin.</p>
              </div>
            </div>

            <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
              <div>
                <label className={labelClass}>Tên đăng nhập *</label>
                <input
                  className={cn(inputClass, errors.username && inputErrorClass, isEditMode && readOnlyInputClass)}
                  value={form.username}
                  disabled={isEditMode}
                  onChange={(event) => updateForm('username', event.target.value)}
                  placeholder="Ví dụ: manager_a"
                  autoComplete="off"
                />
                {errors.username && <p className="mt-1 text-xs font-bold text-rose-600">{errors.username}</p>}
              </div>

              <div>
                <label className={labelClass}>Họ tên *</label>
                <input
                  className={cn(inputClass, errors.full_name && inputErrorClass)}
                  value={form.full_name}
                  onChange={(event) => updateForm('full_name', event.target.value)}
                  placeholder="Nhập họ tên admin"
                />
                {errors.full_name && <p className="mt-1 text-xs font-bold text-rose-600">{errors.full_name}</p>}
              </div>

              <div>
                <label className={labelClass}>Email *</label>
                <input
                  className={cn(inputClass, errors.email && inputErrorClass)}
                  value={form.email}
                  onChange={(event) => updateForm('email', event.target.value)}
                  placeholder="admin@stayhub.vn"
                  type="email"
                />
                {errors.email && <p className="mt-1 text-xs font-bold text-rose-600">{errors.email}</p>}
              </div>

              <div>
                <label className={labelClass}>Số điện thoại *</label>
                <input
                  className={cn(inputClass, errors.phone && inputErrorClass)}
                  value={form.phone}
                  onChange={(event) => updateForm('phone', event.target.value.replace(/\D/g, ''))}
                  placeholder="Nhập số điện thoại"
                  maxLength={10}
                />
                {errors.phone && <p className="mt-1 text-xs font-bold text-rose-600">{errors.phone}</p>}
              </div>

              {isEditMode && (
                <div>
                  <label className={labelClass}>Mật khẩu mới</label>
                  <input
                    className={cn(inputClass, errors.password && inputErrorClass)}
                    value={form.password}
                    onChange={(event) => updateForm('password', event.target.value)}
                    placeholder="Bỏ trống nếu không đổi mật khẩu"
                    type="password"
                    autoComplete="new-password"
                  />
                  {errors.password && <p className="mt-1 text-xs font-bold text-rose-600">{errors.password}</p>}
                </div>
              )}

              <div>
                <label className={labelClass}>Vai trò *</label>
                <AdminSelect
                  value={form.role}
                  options={roleOptions}
                  onChange={(nextValue) => updateForm('role', Number(nextValue))}
                />
                {errors.role && <p className="mt-1 text-xs font-bold text-rose-600">{errors.role}</p>}
              </div>

              {!isEditMode && (
                <div>
                  <label className={labelClass}>Trạng thái *</label>
                  <AdminSelect
                    value={form.status}
                    options={statusOptions}
                    onChange={(nextValue) => updateForm('status', Number(nextValue))}
                  />
                  {errors.status && <p className="mt-1 text-xs font-bold text-rose-600">{errors.status}</p>}
                </div>
              )}
            </div>
          </section>
        </div>

        {/* Right column - Personal details */}
        <div className="space-y-6">
          <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-6 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
            <div className="mb-6 flex items-center gap-3 border-b border-[#3d2a18]/10 pb-4">
              <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#24170d] text-[#fff4df]">
                <UserCog className="h-5 w-5 text-[#f3c56b]" />
              </div>
              <div>
                <h2 className="font-black text-[#24170d]">Thông tin cá nhân</h2>
                <p className="mt-0.5 text-xs font-semibold text-[#8b5e34]/70">Các thông tin cá nhân mở rộng.</p>
              </div>
            </div>

            <div className="space-y-4">
              <div>
                <label className={labelClass}>Giới tính</label>
                <AdminSelect
                  value={form.gender ?? ''}
                  options={genderOptions}
                  onChange={(nextValue) => updateForm('gender', nextValue === '' ? null : Number(nextValue))}
                />
                {errors.gender && <p className="mt-1 text-xs font-bold text-rose-600">{errors.gender}</p>}
              </div>

              <div>
                <label className={labelClass}>Ngày sinh</label>
                <AdminDateInput
                  value={form.date_of_birth}
                  onChange={(val) => updateForm('date_of_birth', val)}
                  placeholder="dd/mm/yyyy"
                  className={cn(inputClass, errors.date_of_birth && inputErrorClass)}
                  maxDate={new Date()}
                />
                {errors.date_of_birth && <p className="mt-1 text-xs font-bold text-rose-600">{errors.date_of_birth}</p>}
              </div>

              <div>
                <label className={labelClass}>Địa chỉ</label>
                <textarea
                  className={cn(inputClass, 'min-h-24 resize-none', errors.address && inputErrorClass)}
                  value={form.address}
                  onChange={(event) => updateForm('address', event.target.value)}
                  placeholder="Nhập địa chỉ liên hệ"
                />
                {errors.address && <p className="mt-1 text-xs font-bold text-rose-600">{errors.address}</p>}
              </div>
            </div>
          </section>
        </div>
      </div>
    </form>
  )
}
