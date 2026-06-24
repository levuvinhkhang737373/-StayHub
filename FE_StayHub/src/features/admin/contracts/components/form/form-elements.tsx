import React from 'react'
import { Trash2 } from 'lucide-react'
import { AdminDateInput } from '../../../../../shared/components/AdminDateInput'
import { cn } from '../../../../../shared/lib/utils/cn'
import { AdminSelect } from '../../../shared/components/AdminSelect'
import type { ContractTenantFormRow, ContractVehicleFormRow } from '../../types/contract-api.model'
import { CHARGE_FREE, chargePolicyOptions } from '../../utils/contract.helpers'
import { FieldError } from '../ui/ui-elements'

export const inputClass =
  'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'
export const inputErrorClass = 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100'
export const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65'

export function Field({
  label,
  required,
  error,
  children,
}: {
  label: string
  required?: boolean
  error?: string
  children: React.ReactNode
}) {
  return (
    <div>
      <label className={labelClass}>
        {label} {required && <span className="text-rose-500">*</span>}
      </label>
      {children}
      <FieldError message={error} />
    </div>
  )
}

export function FormSection({
  title,
  icon,
  action,
  children,
}: {
  title: string
  icon: React.ReactNode
  action?: React.ReactNode
  children: React.ReactNode
}) {
  return (
    <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/95 p-4 shadow-xl shadow-[#6b3f1d]/8">
      <div className="mb-4 flex items-center justify-between gap-3">
        <h2 className="flex items-center gap-2 text-lg font-black text-[#24170d]">
          {icon}
          {title}
        </h2>
        {action}
      </div>
      {children}
    </section>
  )
}

export function RowHeader({ title, canRemove, onRemove }: { title: string; canRemove: boolean; onRemove: () => void }) {
  return (
    <div className="flex items-center justify-between gap-2">
      <p className="text-xs font-black text-[#24170d]">{title}</p>
      {canRemove && (
        <button type="button" onClick={onRemove} className="inline-flex items-center gap-1 text-xs font-black text-rose-600">
          <Trash2 className="h-3.5 w-3.5" /> Xóa
        </button>
      )}
    </div>
  )
}

export function TenantRow({
  index,
  row,
  options,
  error,
  canRemove,
  onChange,
  onRemove,
  isEditMode,
  isRenewMode,
}: {
  index: number
  row: ContractTenantFormRow
  options: Array<{ value: string | number; label: string; description?: string }>
  error?: string
  canRemove: boolean
  onChange: (patch: Partial<ContractTenantFormRow>) => void
  onRemove: () => void
  isEditMode: boolean
  isRenewMode?: boolean
}) {
  return (
    <div className="rounded-2xl border border-[#3d2a18]/10 bg-white/60 p-3">
      <RowHeader title={`Khách #${index + 1}`} canRemove={canRemove} onRemove={onRemove} />
      <div className="mt-3 space-y-3">
        <AdminSelect value={row.tenant_id} options={options} invalid={!!error} placeholder="Chọn khách thuê" onChange={(value: string | number) => onChange({ tenant_id: String(value) })} />
        <div className={cn('grid grid-cols-1 gap-3', isEditMode && !isRenewMode && 'sm:grid-cols-2')}>
          <AdminDateInput className={inputClass} value={row.join_date} onChange={(value: string) => onChange({ join_date: value, billing_start_date: value })} />
          {isEditMode && !isRenewMode && (
            <AdminDateInput className={inputClass} value={row.leave_date} onChange={(value: string) => onChange({ leave_date: value, is_staying: !value })} placeholder="Ngày rời đi" />
          )}
        </div>
        {isEditMode && !isRenewMode && (
          <div className="flex flex-wrap gap-3 text-xs font-black text-[#6f6254]">
            <label className="inline-flex items-center gap-2">
              <input type="checkbox" checked={row.is_staying} onChange={(event) => onChange({ is_staying: event.target.checked })} /> Đang ở
            </label>
          </div>
        )}
        <FieldError message={error} />
      </div>
    </div>
  )
}

export function VehicleRow({
  index,
  row,
  options,
  error,
  onChange,
  onRemove,
  isEditMode,
  isRenewMode,
}: {
  index: number
  row: ContractVehicleFormRow
  options: Array<{ value: string | number; label: string; description?: string }>
  error?: string
  onChange: (patch: Partial<ContractVehicleFormRow>) => void
  onRemove: () => void
  isEditMode: boolean
  isRenewMode?: boolean
}) {
  return (
    <div className="rounded-2xl border border-[#3d2a18]/10 bg-white/60 p-3">
      <RowHeader title={`Xe #${index + 1}`} canRemove onRemove={onRemove} />
      <div className="mt-3 space-y-3">
        <AdminSelect value={row.vehicle_id} options={options} invalid={!!error} placeholder="Chọn phương tiện" onChange={(value: string | number) => onChange({ vehicle_id: String(value) })} />
        <div className={cn('grid grid-cols-1 gap-3', isEditMode && !isRenewMode && 'sm:grid-cols-2')}>
          <AdminDateInput className={inputClass} value={row.started_at} disabled onChange={(value: string) => onChange({ started_at: value, billing_start_date: row.billing_start_date || value })} />
          {isEditMode && !isRenewMode && (
            <AdminDateInput className={inputClass} value={row.ended_at} onChange={(value: string) => onChange({ ended_at: value, is_active: !value })} placeholder="Ngày kết thúc" />
          )}
        </div>
        <AdminSelect
          value={row.charge_policy}
          options={chargePolicyOptions.map((opt) => ({ value: opt.value, label: opt.label }))}
          onChange={(value: string | number) => onChange({ charge_policy: Number(value), monthly_fee: Number(value) === CHARGE_FREE ? '0.00' : row.monthly_fee })}
        />
        <input
          className={cn(inputClass, error && inputErrorClass)}
          value={row.monthly_fee}
          disabled={Number(row.charge_policy) === CHARGE_FREE}
          onChange={(event) => onChange({ monthly_fee: event.target.value })}
          placeholder="Phí gửi xe"
        />
        {isEditMode && !isRenewMode && (
          <label className="inline-flex items-center gap-2 text-xs font-black text-[#6f6254]">
            <input type="checkbox" checked={row.is_active} onChange={(event) => onChange({ is_active: event.target.checked })} /> Còn tính phí
          </label>
        )}
        <FieldError message={error} />
      </div>
    </div>
  )
}
