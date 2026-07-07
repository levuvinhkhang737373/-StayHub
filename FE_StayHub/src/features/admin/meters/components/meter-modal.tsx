import { useEffect, useState } from 'react'
import { AnimatePresence, motion } from 'motion/react'
import { Save, X, Zap } from 'lucide-react'
import { cn } from '../../../../shared/lib/utils/cn'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { getVisibleErrorMessage } from '../../shared/utils/error-message'
import { AdminDateInput } from '../../../../shared/components/AdminDateInput'
import { createAdminMeterDevice, updateAdminMeterDevice } from '../services/meters.service'
import { validateMeterForm } from '../validations/meter.validation'
import type { AdminMeterFormErrors, AdminMeterFormValues } from '../types/meter-api.model'

interface MeterModalProps {
  isOpen: boolean
  onClose: () => void
  editingMeterId: number | null
  form: AdminMeterFormValues
  setForm: React.Dispatch<React.SetStateAction<AdminMeterFormValues>>
  onCancel: () => void
  onSubmitSuccess: () => void
  buildings: any[]
  rooms: any[]
  rawServices: any[]
  replacementOptions: any[]
}

const meterTypeOptions = [
  { value: 1, label: 'Điện', tone: 'warning' as const },
  { value: 2, label: 'Nước', tone: 'success' as const },
]

const statusOptions = [
  { value: 1, label: 'Đang sử dụng', tone: 'success' as const },
  { value: 2, label: 'Ngừng sử dụng', tone: 'danger' as const },
  { value: 3, label: 'Đã thay thế', tone: 'warning' as const },
  { value: 4, label: 'Bị hỏng', tone: 'danger' as const },
]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'
const inputErrorClass = 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100'
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65'

function FieldError({ message }: { message?: string }) {
  if (!message) return null
  return <p className="mt-2 px-1 text-xs font-black text-rose-600" role="alert">{message}</p>
}

export function MeterModal({
  isOpen,
  onClose,
  editingMeterId,
  form,
  setForm,
  onCancel,
  onSubmitSuccess,
  buildings,
  rooms,
  rawServices,
  replacementOptions,
}: MeterModalProps) {
  const isEditing = editingMeterId !== null
  const [isSaving, setIsSaving] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [errors, setErrors] = useState<AdminMeterFormErrors>({})

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
  }, [isOpen, editingMeterId])

  const updateForm = (key: keyof AdminMeterFormValues, value: string | number) => {
    setForm((current) => {
      const next = { ...current, [key]: value }
      if (key === 'building_id') {
        next.room_id = ''
      }
      if (key === 'meter_type') {
        const targetTypeKey = Number(value) === 1 ? 'dien' : 'nuoc'
        const matchedService = rawServices.find((s) => s.slug?.includes(targetTypeKey))
        if (matchedService) {
          next.service_id = String(matchedService.id)
        }
      }
      return next
    })
    setErrors((current) => ({ ...current, [key]: undefined }))
  }

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

      const payload = {
        room_id: Number(form.room_id),
        service_id: Number(form.service_id),
        meter_type: form.meter_type,
        initial_reading: Number(form.initial_reading),
        installed_at: form.installed_at || undefined,
        final_reading: form.final_reading?.trim() ? Number(form.final_reading) : undefined,
        status: form.status,
        replaced_by_meter_id: form.replaced_by_meter_id.trim() ? Number(form.replaced_by_meter_id) : undefined,
        note: form.note.trim() || undefined,
      }

      if (isEditing && editingMeterId) {
        await updateAdminMeterDevice(editingMeterId, payload)
      } else {
        await createAdminMeterDevice(payload)
      }

      onSubmitSuccess()
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể lưu đồng hồ.'))
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <AnimatePresence>
      {isOpen && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="meter-modal-title">
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
                    <Zap className="h-3.5 w-3.5" /> Quản lý đồng hồ
                  </div>
                  <h2 id="meter-modal-title" className="mt-1 text-xl font-black tracking-tight text-[#fff4df]">
                    {isEditing ? 'Cập nhật đồng hồ' : 'Thêm đồng hồ'}
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

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <label className={labelClass}>Tòa nhà</label>
                  <AdminSelect
                    value={form.building_id}
                    options={buildings.map((b) => ({ value: b.id, label: b.name }))}
                    invalid={!!errors.building_id}
                    placeholder="Chọn tòa nhà"
                    disabled={isSaving}
                    onChange={(nextValue) => updateForm('building_id', String(nextValue))}
                  />
                  <FieldError message={errors.building_id} />
                </div>

                <div>
                  <label className={labelClass}>Phòng</label>
                  <AdminSelect
                    value={form.room_id}
                    options={rooms.map((r) => ({ value: r.id, label: r.room_number }))}
                    disabled={!form.building_id || isSaving}
                    invalid={!!errors.room_id}
                    placeholder={form.building_id ? 'Chọn phòng' : 'Vui lòng chọn tòa nhà trước'}
                    onChange={(nextValue) => updateForm('room_id', String(nextValue))}
                  />
                  <FieldError message={errors.room_id} />
                </div>
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <label className={labelClass}>Loại đồng hồ</label>
                  <AdminSelect
                    value={form.meter_type}
                    options={meterTypeOptions}
                    invalid={!!errors.meter_type}
                    disabled={isSaving}
                    onChange={(nextValue) => updateForm('meter_type', Number(nextValue))}
                  />
                  <FieldError message={errors.meter_type} />
                </div>

                <div>
                  <label className={labelClass}>Trạng thái</label>
                  <AdminSelect
                    value={form.status}
                    options={statusOptions}
                    invalid={!!errors.status}
                    disabled={isSaving}
                    onChange={(nextValue) => updateForm('status', Number(nextValue))}
                  />
                  <FieldError message={errors.status} />
                </div>
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <label className={labelClass}>Chỉ số khởi tạo</label>
                  <input
                    type="number"
                    min={0}
                    step="any"
                    className={cn(inputClass, errors.initial_reading && inputErrorClass)}
                    value={form.initial_reading}
                    onChange={(event) => updateForm('initial_reading', event.target.value)}
                    placeholder="0"
                    disabled={isSaving}
                  />
                  <FieldError message={errors.initial_reading} />
                </div>

                <div>
                  <label className={labelClass}>Chỉ số cuối</label>
                  <input
                    type="number"
                    min={0}
                    step="any"
                    className={cn(inputClass, errors.final_reading && inputErrorClass)}
                    value={form.final_reading || ''}
                    onChange={(event) => updateForm('final_reading', event.target.value)}
                    placeholder="Tùy chọn"
                    disabled={isSaving}
                  />
                  <FieldError message={errors.final_reading} />
                </div>
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                  <label className={labelClass}>Đồng hồ thay thế</label>
                  <AdminSelect
                    value={form.replaced_by_meter_id}
                    options={[{ value: '', label: 'Chọn đồng hồ thay thế', tone: 'default' }, ...replacementOptions]}
                    invalid={!!errors.replaced_by_meter_id}
                    disabled={isSaving}
                    onChange={(nextValue) => updateForm('replaced_by_meter_id', String(nextValue))}
                  />
                  <FieldError message={errors.replaced_by_meter_id} />
                </div>
              </div>

              <div>
                <label className={labelClass}>Ghi chú</label>
                <textarea
                  className={cn(inputClass, 'min-h-[100px] resize-y', errors.note && inputErrorClass)}
                  value={form.note}
                  onChange={(event) => updateForm('note', event.target.value)}
                  placeholder="Ghi chú vận hành hoặc vị trí lắp"
                  disabled={isSaving}
                />
                <FieldError message={errors.note} />
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
                <span>{isSaving ? 'Đang lưu...' : 'Lưu đồng hồ'}</span>
              </button>
            </div>
          </motion.div>
        </div>
      )}
    </AnimatePresence>
  )
}
