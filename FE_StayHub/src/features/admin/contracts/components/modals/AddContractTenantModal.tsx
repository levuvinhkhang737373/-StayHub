import { useMemo, useState } from 'react'
import { AlertTriangle, BadgeCheck, Building2, CalendarDays, Loader2, Search, UserPlus, Users, X } from 'lucide-react'
import { AdminDateInput } from '../../../../../shared/components/AdminDateInput'
import { cn } from '../../../../../shared/lib/utils/cn'
import { formatDate } from '../../../../../shared/lib/utils/format'
import type { AdminContractResource, AdminContractTenantOptionResource } from '../../types/contract-api.model'
import { toDate, todayStr } from '../../utils/contract.helpers'
import { FieldError } from '../ui/ui-elements'
import { inputClass, labelClass } from '../form/form-elements'

type AddContractTenantForm = {
  tenant_id: string
  join_date: string
  billing_start_date: string
}

type AddContractTenantModalProps = {
  contract: AdminContractResource
  tenants: AdminContractTenantOptionResource[]
  form: AddContractTenantForm
  keyword: string
  errorMessage?: string | null
  isLoading: boolean
  isSaving: boolean
  onKeywordChange: (keyword: string) => void
  onChange: (form: AddContractTenantForm) => void
  onCancel: () => void
  onSubmit: () => void
}

export function createDefaultAddContractTenantForm(): AddContractTenantForm {
  return {
    tenant_id: '',
    join_date: todayStr,
    billing_start_date: todayStr,
  }
}

export function AddContractTenantModal({
  contract,
  tenants,
  form,
  keyword,
  errorMessage,
  isLoading,
  isSaving,
  onKeywordChange,
  onChange,
  onCancel,
  onSubmit,
}: AddContractTenantModalProps) {
  const [touched, setTouched] = useState(false)
  const selectedTenant = useMemo(() => tenants.find((tenant) => String(tenant.id) === form.tenant_id) || null, [tenants, form.tenant_id])
  const selectedTenantLabel = selectedTenant?.full_name || selectedTenant?.username || (form.tenant_id ? `Khách thuê #${form.tenant_id}` : 'Chưa chọn khách thuê')
  const selectedTenantDescription = selectedTenant
    ? [selectedTenant.phone, selectedTenant.email].filter(Boolean).join(' · ') || 'Chưa có liên hệ'
    : form.tenant_id
      ? 'Đã chọn, đang giữ dữ liệu dù danh sách hiện tại bị lọc.'
      : 'Chọn từ danh sách bên trái để tiếp tục.'
  const canSubmit = Boolean(form.tenant_id && form.join_date && form.billing_start_date) && !isSaving
  const minStayDate = contract.start_date ? toDate(contract.start_date) : undefined
  const maxStayDate = contract.actual_end_date || contract.end_date ? toDate(contract.actual_end_date || contract.end_date || '') : undefined

  const handleJoinDateChange = (value: string) => {
    onChange({ ...form, join_date: value, billing_start_date: !form.billing_start_date || form.billing_start_date < value ? value : form.billing_start_date })
  }

  return (
    <div className="fixed inset-0 z-[80] flex items-center justify-center p-3 sm:p-5" role="dialog" aria-modal="true" aria-labelledby="add-contract-tenant-title">
      <div className="absolute inset-0 bg-[#140b06]/72 backdrop-blur-sm" aria-hidden="true" />
      <div className="relative z-10 flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-[2rem] border border-[#f3c56b]/25 bg-[#fff8ec] shadow-2xl shadow-[#24170d]/35">
        <div className="relative overflow-hidden border-b border-[#3d2a18]/10 bg-[#24170d] px-5 py-5 text-[#fff4df] sm:px-6">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_14%_8%,rgba(243,197,107,0.34),transparent_32%),radial-gradient(circle_at_88%_10%,rgba(15,118,110,0.28),transparent_36%),linear-gradient(135deg,#24170d_0%,#4a2b16_52%,#0f3f3b_100%)]" />
          <div className="relative flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-[#f3c56b]/25 bg-[#f3c56b]/14 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-[#ffe7a8]">
                <UserPlus className="h-3.5 w-3.5" /> Thêm người vào hợp đồng
              </div>
              <h2 id="add-contract-tenant-title" className="text-2xl font-black tracking-tight sm:text-3xl">
                Chọn khách thuê cho {contract.contract_code}
              </h2>
              <p className="mt-2 flex flex-wrap items-center gap-2 text-xs font-bold text-[#f8e8c8]/78">
                <span className="inline-flex items-center gap-1.5"><Building2 className="h-4 w-4 text-[#f3c56b]" /> {contract.building_name || contract.room?.building_name || 'Chưa rõ tòa nhà'}</span>
                <span className="h-1 w-1 rounded-full bg-[#f3c56b]/70" />
                <span>Phòng {contract.room_number || contract.room?.room_number || contract.room_id}</span>
                <span className="h-1 w-1 rounded-full bg-[#f3c56b]/70" />
                <span>{formatDate(contract.start_date)} → {formatDate(contract.end_date)}</span>
              </p>
            </div>
            <button
              type="button"
              onClick={onCancel}
              className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-[#fff4df]/15 bg-[#fff4df]/10 text-[#fff4df] transition hover:bg-[#fff4df]/18 focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20"
              aria-label="Hủy thêm khách thuê"
            >
              <X className="h-5 w-5" />
            </button>
          </div>
        </div>

        <div className="grid min-h-0 flex-1 gap-0 overflow-hidden lg:grid-cols-[1.25fr_0.75fr]">
          <section className="min-h-0 overflow-y-auto p-5 sm:p-6">
            <div className="mb-4 rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/70 p-3 shadow-inner shadow-[#6b3f1d]/5">
              <label className="mb-2 block px-1 text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/65">Tìm khách thuê trong tòa nhà</label>
              <div className="relative">
                <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8b5e34]/55" />
                <input
                  value={keyword}
                  onChange={(event) => onKeywordChange(event.target.value)}
                  className={cn(inputClass, 'pl-11')}
                  placeholder="Nhập tên, SĐT, email hoặc CCCD..."
                />
              </div>
            </div>

            {errorMessage && (
              <div className="mb-4 flex items-start gap-2 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">
                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" /> {errorMessage}
              </div>
            )}

            <div className="space-y-3">
              {isLoading && (
                <div className="flex min-h-56 items-center justify-center rounded-[1.5rem] border border-dashed border-[#3d2a18]/12 bg-white/55 text-sm font-black text-[#8b5e34]">
                  <Loader2 className="mr-2 h-5 w-5 animate-spin" /> Đang tải khách thuê theo tòa nhà...
                </div>
              )}

              {!isLoading && tenants.length === 0 && (
                <div className="flex min-h-56 flex-col items-center justify-center rounded-[1.5rem] border border-dashed border-[#3d2a18]/12 bg-white/55 px-6 text-center">
                  <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f3c56b]/18 text-[#a65f16]">
                    <Users className="h-8 w-8" />
                  </div>
                  <p className="text-base font-black text-[#24170d]">Không còn khách thuê phù hợp</p>

                </div>
              )}

              {!isLoading && tenants.map((tenant) => {
                const checked = String(tenant.id) === form.tenant_id
                const description = [tenant.phone, tenant.email, tenant.identity_number].filter(Boolean).join(' · ')

                return (
                  <button
                    key={tenant.id}
                    type="button"
                    onClick={() => {
                      setTouched(true)
                      onChange({ ...form, tenant_id: String(tenant.id) })
                    }}
                    className={cn(
                      'group w-full rounded-[1.35rem] border p-4 text-left shadow-sm transition focus:outline-none focus:ring-4',
                      checked
                        ? 'border-[#0f766e]/35 bg-[#0f766e]/10 shadow-[#0f766e]/10 focus:ring-[#0f766e]/12'
                        : 'border-[#3d2a18]/10 bg-white/72 hover:-translate-y-0.5 hover:border-[#f3c56b]/55 hover:bg-[#fffaf1] focus:ring-[#f3c56b]/18'
                    )}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <p className="truncate text-sm font-black text-[#24170d]">{tenant.full_name || tenant.username || `Khách thuê #${tenant.id}`}</p>
                        <p className="mt-1 truncate text-xs font-bold text-[#8b5e34]/72">{description || 'Chưa có thông tin liên hệ'}</p>
                        <div className="mt-3 flex flex-wrap gap-2">
                          <span className="rounded-full bg-[#efe2cf] px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-[#8b5e34]">{tenant.gender_label || 'Chưa rõ giới tính'}</span>
                          <span className="rounded-full bg-[#0f766e]/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-[#0f5f59]">{tenant.status_label || 'Đang thuê'}</span>
                        </div>
                      </div>
                      <span className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border transition', checked ? 'border-[#0f766e]/25 bg-[#0f766e] text-white' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34]/45 group-hover:text-[#a65f16]')}>
                        {checked ? <BadgeCheck className="h-5 w-5" /> : <UserPlus className="h-4 w-4" />}
                      </span>
                    </div>
                  </button>
                )
              })}
            </div>
            {touched && !form.tenant_id && <FieldError message="Vui lòng chọn khách thuê cần thêm." />}
          </section>

          <aside className="border-t border-[#3d2a18]/10 bg-[#f7ecd8] p-5 sm:p-6 lg:border-l lg:border-t-0">
            <div className="sticky top-0 space-y-4">
              <div className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/70 p-4 shadow-sm">
                <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/65">Khách thuê đã chọn</p>
                <p className="mt-2 text-lg font-black text-[#24170d]">{selectedTenantLabel}</p>
                <p className="mt-1 text-xs font-bold text-[#6f6254]">{selectedTenantDescription}</p>
              </div>

              <div className="grid gap-4">
                <div>
                  <label className={labelClass}>Ngày vào ở</label>
                  <div className="relative">
                    <CalendarDays className="pointer-events-none absolute left-4 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-[#8b5e34]/55" />
                    <AdminDateInput className={cn(inputClass, 'pl-11')} value={form.join_date} minDate={minStayDate} maxDate={maxStayDate} onChange={handleJoinDateChange} />
                  </div>
                  {!form.join_date && <FieldError message="Ngày vào ở là bắt buộc." />}
                </div>

                <div>
                  <label className={labelClass}>Ngày bắt đầu tính tiền</label>
                  <div className="relative">
                    <CalendarDays className="pointer-events-none absolute left-4 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-[#8b5e34]/55" />
                    <AdminDateInput className={cn(inputClass, 'pl-11')} value={form.billing_start_date} minDate={form.join_date ? toDate(form.join_date) : minStayDate} maxDate={maxStayDate} onChange={(value) => onChange({ ...form, billing_start_date: value })} />
                  </div>
                  {!form.billing_start_date && <FieldError message="Ngày bắt đầu tính tiền là bắt buộc." />}
                </div>
              </div>



              <div className="flex flex-col-reverse gap-3 sm:flex-row lg:flex-col-reverse">
                <button type="button" onClick={onCancel} className="h-12 rounded-2xl border border-[#3d2a18]/10 bg-white px-5 text-sm font-black text-[#8b5e34] transition hover:bg-[#fffaf1]">
                  Hủy
                </button>
                <button
                  type="button"
                  disabled={!canSubmit}
                  onClick={() => {
                    setTouched(true)
                    onSubmit()
                  }}
                  className="h-12 rounded-2xl bg-[#24170d] px-6 text-sm font-black text-[#fff4df] shadow-xl shadow-[#24170d]/18 transition hover:-translate-y-0.5 hover:bg-[#3a2616] disabled:cursor-not-allowed disabled:opacity-55 disabled:hover:translate-y-0"
                >
                  {isSaving ? 'Đang thêm...' : 'Thêm vào hợp đồng'}
                </button>
              </div>
            </div>
          </aside>
        </div>
      </div>
    </div>
  )
}
