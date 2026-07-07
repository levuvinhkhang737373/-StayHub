import { useEffect, useState } from 'react'
import { AnimatePresence, motion } from 'motion/react'
import { Save, X, Zap } from 'lucide-react'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { getVisibleErrorMessage } from '../../shared/utils/error-message'
import { createAdminService, updateAdminService } from '../services/services.service'
import { validateServiceForm, type ServiceFormErrors, type ServiceFormValues } from '../validations/service.validation'

interface ServiceModalProps {
  isOpen: boolean
  onClose: () => void
  editingServiceId: number | null
  form: ServiceFormValues
  setForm: React.Dispatch<React.SetStateAction<ServiceFormValues>>
  onCancel: () => void
  onSubmitSuccess: () => void
}

const chargeMethodOptions = [
  { value: 1, label: 'Theo chỉ số', description: 'Điện, nước qua công tơ', tone: 'warning' as const },
  { value: 2, label: 'Theo người', description: 'Rác, nước sinh hoạt theo số người', tone: 'default' as const },
  { value: 3, label: 'Theo phòng', description: 'Internet, vệ sinh theo phòng', tone: 'success' as const },
  { value: 4, label: 'Theo xe', description: 'Gửi xe theo phương tiện', tone: 'default' as const },
  { value: 5, label: 'Cố định', description: 'Khoản thu cố định', tone: 'default' as const },
]

const formRequiredOptions = [
  { value: 1, label: 'Bắt buộc', tone: 'warning' as const },
  { value: 0, label: 'Không bắt buộc', tone: 'default' as const },
]

const formStatusOptions = [
  { value: 1, label: 'Hoạt động', tone: 'success' as const },
  { value: 0, label: 'Ngừng hoạt động', tone: 'danger' as const },
]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'
const inputErrorClass = 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100'
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65'

function FieldError({ message }: { message?: string }) {
  if (!message) return null
  return <p className="mt-2 px-1 text-xs font-black text-rose-600" role="alert">{message}</p>
}

export function ServiceModal({
  isOpen,
  onClose,
  editingServiceId,
  form,
  setForm,
  onCancel,
  onSubmitSuccess,
}: ServiceModalProps) {
  const isEditing = editingServiceId !== null
  const [isSaving, setIsSaving] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [errors, setErrors] = useState<ServiceFormErrors>({})

  useEffect(() => {
    if (!isOpen) return

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') onClose()
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [isOpen, onClose])

  useEffect(() => {
    setErrors({})
    setErrorMessage(null)
  }, [isOpen, editingServiceId])

  const updateForm = (key: keyof ServiceFormValues, value: string | number | boolean) => {
    setForm((current) => ({ ...current, [key]: value }))
    setErrors((current) => ({ ...current, [key]: undefined }))
  }

  const submit = async () => {
    if (isSaving) return

    const nextErrors = validateServiceForm(form)
    setErrors(nextErrors)

    if (Object.keys(nextErrors).length > 0) {
      setErrorMessage('Vui lòng kiểm tra lại thông tin dịch vụ.')
      return
    }

    try {
      setIsSaving(true)
      setErrorMessage(null)

      const payload = {
        name: form.name.trim(),
        charge_method: Number(form.charge_method),
        unit_name: form.unit_name.trim() || undefined,
        is_required: Boolean(form.is_required),
        is_active: Boolean(form.is_active),
      }

      if (isEditing && editingServiceId) {
        await updateAdminService(editingServiceId, payload)
      } else {
        await createAdminService(payload)
      }

      onSubmitSuccess()
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể lưu dịch vụ.'))
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <AnimatePresence>
      {isOpen && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="service-modal-title">
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="absolute inset-0 bg-stone-950/70 backdrop-blur-md"
          />

          <motion.div
            initial={{ y: 40, opacity: 0, scale: 0.98 }}
            animate={{ y: 0, opacity: 1, scale: 1 }}
            exit={{ y: 40, opacity: 0, scale: 0.98 }}
            className="relative z-10 flex max-h-[92vh] w-full max-w-xl flex-col overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-black/40 text-[#24170d]"
          >
            {/* Header */}
            <div className="relative overflow-hidden bg-[#24170d] p-5 text-[#fff4df]">
              <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(243,197,107,0.2),transparent_30%),linear-gradient(135deg,#24170d_0%,#3d2a18_100%)]" />
              <div className="relative flex items-center justify-between">
                <div>
                  <div className="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-[0.2em] text-[#f3c56b]">
                    <Zap className="h-3.5 w-3.5" /> Danh mục dịch vụ
                  </div>
                  <h2 id="service-modal-title" className="mt-1 text-xl font-black tracking-tight text-[#fff4df]">
                    {isEditing ? 'Cập nhật dịch vụ' : 'Thêm dịch vụ'}
                  </h2>
                </div>
                <button
                  type="button"
                  onClick={onClose}
                  className="flex h-9 w-9 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white backdrop-blur-md transition-all hover:bg-white/20 focus:outline-none focus:ring-4"
                  aria-label="Đóng modal"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>
            </div>

            {/* Form Body */}
            <div className="flex-1 overflow-y-auto p-5 space-y-4">
              {errorMessage && (
                <div role="alert" className="rounded-2xl border border-rose-200 bg-rose-50/95 p-4 text-sm font-black text-rose-700 shadow-sm">
                  {errorMessage}
                </div>
              )}

              <div>
                <label className={labelClass}>Tên dịch vụ</label>
                <input
                  className={`${inputClass} ${errors.name ? inputErrorClass : ''}`}
                  value={form.name}
                  onChange={(event) => updateForm('name', event.target.value)}
                  placeholder="Ví dụ: Điện sinh hoạt"
                  disabled={isSaving}
                />
                <FieldError message={errors.name} />
              </div>

              <div>
                <label className={labelClass}>Cách tính phí</label>
                <AdminSelect
                  value={form.charge_method}
                  options={chargeMethodOptions}
                  invalid={!!errors.charge_method}
                  disabled={isSaving}
                  onChange={(nextValue) => updateForm('charge_method', Number(nextValue))}
                />
                <FieldError message={errors.charge_method} />
              </div>

              <div>
                <label className={labelClass}>Đơn vị tính</label>
                <input
                  className={`${inputClass} ${errors.unit_name ? inputErrorClass : ''}`}
                  value={form.unit_name}
                  onChange={(event) => updateForm('unit_name', event.target.value)}
                  placeholder="Ví dụ: kWh, m³, phòng, người"
                  disabled={isSaving}
                />
                <FieldError message={errors.unit_name} />
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <label className={labelClass}>Bắt buộc</label>
                  <AdminSelect
                    value={form.is_required ? 1 : 0}
                    options={formRequiredOptions}
                    invalid={!!errors.is_required}
                    disabled={isSaving}
                    onChange={(nextValue) => updateForm('is_required', Number(nextValue) === 1)}
                  />
                  <FieldError message={errors.is_required} />
                </div>
                <div>
                  <label className={labelClass}>Trạng thái</label>
                  <AdminSelect
                    value={form.is_active ? 1 : 0}
                    options={formStatusOptions}
                    invalid={!!errors.is_active}
                    disabled={isSaving}
                    onChange={(nextValue) => updateForm('is_active', Number(nextValue) === 1)}
                  />
                  <FieldError message={errors.is_active} />
                </div>
              </div>
            </div>

            {/* Footer Actions */}
            <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff7e8]/70 p-5 sm:flex-row sm:items-center sm:justify-end">
              <button
                type="button"
                onClick={onCancel}
                disabled={isSaving}
                className="inline-flex min-h-12 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-6 text-sm font-black uppercase tracking-widest text-[#6f6254] transition hover:border-[#f3c56b]/45 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 disabled:opacity-50"
              >
                Hủy
              </button>
              <button
                type="button"
                onClick={submit}
                disabled={isSaving}
                className="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-[#24170d] px-6 text-sm font-black uppercase tracking-widest text-[#fff4df] shadow-xl shadow-[#6b3f1d]/18 transition hover:bg-[#3d2a18] focus:outline-none focus:ring-4 disabled:opacity-50"
              >
                <Save className="h-4 w-4 text-[#f3c56b] stroke-[2.8]" />
                <span>{isSaving ? 'Đang lưu...' : 'Lưu dịch vụ'}</span>
              </button>
            </div>
          </motion.div>
        </div>
      )}
    </AnimatePresence>
  )
}
