import { useEffect, useMemo, useState } from 'react'
import { AnimatePresence, motion } from 'motion/react'
import { Bell, Save, X } from 'lucide-react'
import { cn } from '../../../../shared/lib/utils/cn'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { createAdminNotification, updateAdminNotification } from '../services/notification.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import type { AdminTenantResource } from '../../tenants/types/tenant-api.model'
import type { AdminNotificationPayload } from '../types/notification-api.model'

export interface NotificationFormValues {
  title: string
  content: string
  notification_type: number
  target_type: number
  building_id: string
  room_id: string
  tenant_id: string
  status: number
}

interface NotificationModalProps {
  isOpen: boolean
  onClose: () => void
  editingNotificationId: number | null
  form: NotificationFormValues
  setForm: React.Dispatch<React.SetStateAction<NotificationFormValues>>
  onCancel: () => void
  onSubmitSuccess: () => void
  buildings: AdminBuildingResource[]
  tenants: AdminTenantResource[]
  isSuperAdmin: boolean
}

const typeOptions = [
  { value: 1, label: 'Sửa chữa' },
  { value: 2, label: 'Hóa đơn' },
  { value: 3, label: 'Hệ thống' },
  { value: 4, label: 'Cảnh báo' },
  { value: 5, label: 'Khác' },
]

const statusOptions = [
  { value: 1, label: 'Bản nháp' },
  { value: 2, label: 'Gửi ngay' },
]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'
const inputErrorClass = 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100'
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65'

function FieldError({ message }: { message?: string }) {
  if (!message) return null
  return <p className="mt-1.5 px-1 text-xs font-bold text-rose-600" role="alert">{message}</p>
}

export function NotificationModal({
  isOpen,
  onClose,
  editingNotificationId,
  form,
  setForm,
  onCancel,
  onSubmitSuccess,
  buildings,
  tenants,
  isSuperAdmin,
}: NotificationModalProps) {
  const isEditing = editingNotificationId !== null
  const [isSaving, setIsSaving] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [errors, setErrors] = useState<Record<string, string>>({})

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
  }, [isOpen, editingNotificationId])

  useEffect(() => {
    if (isOpen && form.target_type === 2 && buildings.length === 1 && !form.building_id) {
      setForm((current) => ({ ...current, building_id: String(buildings[0].id) }))
    }
  }, [isOpen, form.target_type, buildings, form.building_id])

  const buildingOptions = useMemo(() => buildings.map((b) => ({ value: b.id, label: b.name, tone: 'default' as const })), [buildings])

  const tenantSelectOptions = useMemo(() => {
    return tenants.map((t) => ({
      value: t.id,
      label: `${t.full_name || t.username} (Phòng ${t.room_number || 'N/A'} - ${t.building_name || 'N/A'})`,
      tone: 'default' as const
    }))
  }, [tenants])

  const allowedTargetOptions = useMemo(() => {
    const options = [
      { value: 2, label: 'Theo tòa nhà / phòng' },
      { value: 4, label: 'Theo khách thuê' },
    ]
    if (isSuperAdmin) {
      options.unshift({ value: 1, label: 'Tất cả (Toàn hệ thống)' })
    }
    return options
  }, [isSuperAdmin])

  const handleBuildingChange = (val: string | number) => {
    setForm((current) => ({
      ...current,
      building_id: String(val),
      room_id: ''
    }))
    setErrors((current) => ({ ...current, building_id: '', room_id: '' }))
  }

  const filteredRoomSelectOptions = useMemo(() => {
    if (!form.building_id) return []
    const seenRoomIds = new Set<number>()
    const options: Array<{ value: string; label: string; tone: 'default' }> = [
      { value: '', label: 'Tất cả phòng (Mặc định gửi cả tòa nhà)', tone: 'default' as const }
    ]

    tenants.forEach((t) => {
      if (t.room_id && String(t.building_id) === form.building_id && !seenRoomIds.has(t.room_id)) {
        seenRoomIds.add(t.room_id)
        options.push({
          value: String(t.room_id),
          label: `Phòng ${t.room_number || 'N/A'}`,
          tone: 'default' as const
        })
      }
    })

    return options
  }, [tenants, form.building_id])

  const updateForm = (key: keyof NotificationFormValues, value: any) => {
    setForm((current) => ({ ...current, [key]: value }))
    setErrors((current) => ({ ...current, [key]: '' }))
  }

  const validate = () => {
    const nextErrors: Record<string, string> = {}
    if (!form.title.trim()) nextErrors.title = 'Vui lòng nhập tiêu đề thông báo.'
    if (!form.content.trim()) nextErrors.content = 'Vui lòng nhập nội dung chi tiết.'
    
    if (form.target_type === 2 && !form.building_id) {
      nextErrors.building_id = 'Vui lòng chọn tòa nhà mục tiêu.'
    }
    
    if (form.target_type === 4 && !form.tenant_id) {
      nextErrors.tenant_id = 'Vui lòng chọn khách thuê mục tiêu.'
    }

    setErrors(nextErrors)
    return Object.keys(nextErrors).length === 0
  }

  const submit = async () => {
    if (isSaving) return
    if (!validate()) {
      setErrorMessage('Vui lòng kiểm tra lại thông tin thông báo.')
      return
    }

    setIsSaving(true)
    setErrorMessage(null)

    const payload: AdminNotificationPayload = {
      title: form.title.trim(),
      content: form.content.trim(),
      notification_type: form.notification_type,
      target_type: (form.target_type === 2 && form.room_id) ? 3 : form.target_type,
      building_id: form.target_type === 2 ? Number(form.building_id) : null,
      room_id: (form.target_type === 2 && form.room_id) ? Number(form.room_id) : null,
      tenant_id: form.target_type === 4 ? Number(form.tenant_id) : null,
      status: form.status
    }

    try {
      if (isEditing && editingNotificationId) {
        await updateAdminNotification(editingNotificationId, payload)
      } else {
        await createAdminNotification(payload)
      }
      onSubmitSuccess()
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể lưu thông báo.')
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <AnimatePresence>
      {isOpen && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="notification-modal-title">
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
                    <Bell className="h-3.5 w-3.5" /> Quản lý thông báo
                  </div>
                  <h2 id="notification-modal-title" className="mt-1 text-xl font-black tracking-tight text-[#fff4df]">
                    {isEditing ? 'Cập nhật thông báo' : 'Thêm thông báo'}
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
                <label className={labelClass}>Tiêu đề thông báo <span className="text-rose-500">*</span></label>
                <input
                  type="text"
                  className={cn(inputClass, errors.title && inputErrorClass)}
                  value={form.title}
                  onChange={(e) => updateForm('title', e.target.value)}
                  placeholder="Ví dụ: Lịch bảo trì điện ngày 15/06"
                  disabled={isSaving}
                />
                <FieldError message={errors.title} />
              </div>

              <div>
                <label className={labelClass}>Nội dung chi tiết <span className="text-rose-500">*</span></label>
                <textarea
                  className={cn(inputClass, 'min-h-[100px] resize-y', errors.content && inputErrorClass)}
                  value={form.content}
                  onChange={(e) => updateForm('content', e.target.value)}
                  placeholder="Nhập nội dung thông báo đầy đủ..."
                  disabled={isSaving}
                />
                <FieldError message={errors.content} />
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <label className={labelClass}>Loại thông báo</label>
                  <AdminSelect
                    value={form.notification_type}
                    options={typeOptions.map((o) => ({ value: o.value, label: o.label, tone: 'default' as const }))}
                    disabled={isSaving}
                    onChange={(val) => updateForm('notification_type', Number(val))}
                  />
                </div>
                <div>
                  <label className={labelClass}>Trạng thái</label>
                  <AdminSelect
                    value={form.status}
                    options={statusOptions.map((o) => ({ value: o.value, label: o.label, tone: 'default' as const }))}
                    disabled={isSaving}
                    onChange={(val) => updateForm('status', Number(val))}
                  />
                </div>
              </div>

              <div>
                <label className={labelClass}>Đối tượng mục tiêu</label>
                <AdminSelect
                  value={form.target_type}
                  options={allowedTargetOptions.map((o) => ({ value: o.value, label: o.label, tone: 'default' as const }))}
                  disabled={isSaving}
                  onChange={(val) => {
                    setForm((current) => ({
                      ...current,
                      target_type: Number(val),
                      building_id: '',
                      room_id: '',
                      tenant_id: ''
                    }))
                    setErrors((current) => ({
                      ...current,
                      building_id: '',
                      room_id: '',
                      tenant_id: ''
                    }))
                  }}
                />
              </div>

              {/* Conditional Fields */}
              {form.target_type === 2 && (
                <div className="space-y-4 rounded-2xl border border-[#3d2a18]/10 bg-white/50 p-4">
                  <div>
                    <label className={labelClass}>Chọn tòa nhà <span className="text-rose-500">*</span></label>
                    <AdminSelect
                      value={form.building_id}
                      options={[{ value: '', label: 'Chọn tòa nhà mục tiêu', tone: 'default' as const }, ...buildingOptions]}
                      disabled={isSaving}
                      invalid={!!errors.building_id}
                      onChange={(val) => handleBuildingChange(val)}
                    />
                    <FieldError message={errors.building_id} />
                  </div>

                  {form.building_id && (
                    <div>
                      <label className={labelClass}>Chọn phòng nhận tin (Không bắt buộc)</label>
                      <AdminSelect
                        value={form.room_id}
                        options={filteredRoomSelectOptions}
                        disabled={isSaving}
                        onChange={(val) => updateForm('room_id', String(val))}
                        placeholder="Mặc định gửi cả tòa nhà"
                      />
                    </div>
                  )}
                </div>
              )}

              {form.target_type === 4 && (
                <div className="rounded-2xl border border-[#3d2a18]/10 bg-white/50 p-4">
                  <label className={labelClass}>Chọn khách thuê nhận tin <span className="text-rose-500">*</span></label>
                  <AdminSelect
                    value={form.tenant_id}
                    options={[{ value: '', label: 'Chọn khách thuê mục tiêu', tone: 'default' as const }, ...tenantSelectOptions]}
                    disabled={isSaving}
                    invalid={!!errors.tenant_id}
                    onChange={(val) => updateForm('tenant_id', String(val))}
                  />
                  <FieldError message={errors.tenant_id} />
                </div>
              )}
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
                <span>{isSaving ? 'Đang gửi...' : 'Lưu thông báo'}</span>
              </button>
            </div>
          </motion.div>
        </div>
      )}
    </AnimatePresence>
  )
}
