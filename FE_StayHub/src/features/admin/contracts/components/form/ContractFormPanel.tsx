import { BadgeCheck, Car, FileText, RefreshCw, UserPlus, X } from 'lucide-react'
import { AdminDateInput } from '../../../../../shared/components/AdminDateInput'
import { cn } from '../../../../../shared/lib/utils/cn'
import { AdminSelect } from '../../../shared/components/AdminSelect'
import type { ContractFormErrors, ContractFormValues, ContractTenantFormRow, ContractVehicleFormRow } from '../../types/contract-api.model'
import { toDate, createStatusOptions } from '../../utils/contract.helpers'
import { FieldError } from '../ui/ui-elements'
import { inputClass, inputErrorClass, labelClass, TenantRow, VehicleRow } from './form-elements'

export function ContractFormPanel({
  editing,
  renewing,
  form,
  errors,
  isSaving,
  isSuperAdmin,
  buildingOptions,
  roomOptions,
  tenantOptions,
  vehicleOptions,
  onUpdate,
  onUpdateTenant,
  onUpdateVehicle,
  onAddTenant,
  onRemoveTenant,
  onAddVehicle,
  onRemoveVehicle,
  onSubmit,
  onReset,
  onClose,
}: {
  editing: boolean
  renewing: boolean
  form: ContractFormValues
  errors: ContractFormErrors
  isSaving: boolean
  isSuperAdmin: boolean
  buildingOptions: Array<{ value: string | number; label: string; tone?: 'default' | 'success' | 'warning' | 'danger'; description?: string }>
  roomOptions: Array<{ value: string | number; label: string; tone?: 'default' | 'success' | 'warning' | 'danger'; description?: string }>
  tenantOptions: Array<{ value: string | number; label: string; tone?: 'default' | 'success' | 'warning' | 'danger'; description?: string }>
  vehicleOptions: Array<{ value: string | number; label: string; tone?: 'default' | 'success' | 'warning' | 'danger'; description?: string }>
  onUpdate: <K extends keyof ContractFormValues>(key: K, value: ContractFormValues[K]) => void
  onUpdateTenant: (index: number, patch: Partial<ContractTenantFormRow>) => void
  onUpdateVehicle: (index: number, patch: Partial<ContractVehicleFormRow>) => void
  onAddTenant: () => void
  onRemoveTenant: (index: number) => void
  onAddVehicle: () => void
  onRemoveVehicle: (index: number) => void
  onSubmit: () => void
  onReset: () => void
  onClose: () => void
}) {
  return (
    <aside className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/95 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md 2xl:sticky 2xl:top-6 2xl:self-start">
      <div className="border-b border-[#3d2a18]/10 bg-[#fff8eb]/85 p-5">
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/60">Hồ sơ hợp đồng</p>
            <h2 className="mt-1 text-xl font-black tracking-tight text-[#24170d]">
              {editing ? 'Cập nhật hợp đồng' : renewing ? 'Gia hạn hợp đồng' : 'Thêm hợp đồng'}
            </h2>
          </div>
          <div className="flex gap-2">
            <button type="button" onClick={onReset} className="rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] p-2 text-[#8b5e34] transition hover:bg-[#f3c56b]/15">
              <RefreshCw className="h-4 w-4" />
            </button>
            <button type="button" onClick={onClose} className="rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] p-2 text-[#8b5e34] transition hover:bg-rose-50 hover:text-rose-600">
              <X className="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>

      <div className="max-h-[calc(100dvh-12rem)] space-y-4 overflow-y-auto p-5">
        {isSuperAdmin && (
          <div>
            <label className={labelClass}>
              Tòa nhà <span className="text-rose-500">*</span>
            </label>
            <AdminSelect
              value={form.building_id}
              options={buildingOptions}
              invalid={!!errors.building_id}
              placeholder="Chọn tòa nhà"
              onChange={(nextValue: string | number) => {
                onUpdate('building_id', String(nextValue))
                onUpdate('room_id', '')
              }}
            />
            <FieldError message={errors.building_id} />
          </div>
        )}

        <div>
          <label className={labelClass}>
            Phòng <span className="text-rose-500">*</span>
          </label>
          <AdminSelect
            value={form.room_id}
            options={roomOptions}
            disabled={!form.building_id && isSuperAdmin}
            invalid={!!errors.room_id}
            placeholder="Chọn phòng"
            onChange={(nextValue: string | number) => onUpdate('room_id', String(nextValue))}
          />
          <FieldError message={errors.room_id} />
        </div>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <label className={labelClass}>Ngày bắt đầu</label>
            <AdminDateInput className={cn(inputClass, errors.start_date && inputErrorClass)} value={form.start_date} onChange={(value: string) => onUpdate('start_date', value)} />
            <FieldError message={errors.start_date} />
          </div>
          <div>
            <label className={labelClass}>Ngày kết thúc</label>
            <AdminDateInput
              className={cn(inputClass, errors.end_date && inputErrorClass)}
              value={form.end_date}
              onChange={(value: string) => onUpdate('end_date', value)}
              minDate={toDate(form.start_date)}
            />
            <FieldError message={errors.end_date} />
          </div>
        </div>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {!editing && !renewing && (
            <div>
              <label className={labelClass}>Trạng thái tạo</label>
              <AdminSelect value={form.status} options={createStatusOptions} invalid={!!errors.status} onChange={(nextValue: string | number) => onUpdate('status', Number(nextValue))} />
              <FieldError message={errors.status} />
            </div>
          )}
        </div>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <label className={labelClass}>Giá phòng</label>
            <input
              className={cn(inputClass, errors.room_price && inputErrorClass)}
              value={form.room_price}
              onChange={(event) => onUpdate('room_price', event.target.value)}
              placeholder="3.500.000"
            />
            <FieldError message={errors.room_price} />
          </div>
          <div>
            <label className={labelClass}>Tiền cọc</label>
            <input
              className={cn(inputClass, errors.deposit_amount && inputErrorClass)}
              value={form.deposit_amount}
              onChange={(event) => onUpdate('deposit_amount', event.target.value)}
              placeholder="3.500.000"
            />
            <FieldError message={errors.deposit_amount} />
          </div>
        </div>

        {!editing && Number(form.deposit_amount) > 0 && (
          <div className="flex flex-col gap-2 p-3 rounded-2xl border border-[#3d2a18]/10 bg-white/40">
            <label className="inline-flex items-center gap-2 text-xs font-black text-[#6f6254]">
              <input type="checkbox" checked={form.is_deposit_paid} disabled onChange={(e) => onUpdate('is_deposit_paid', e.target.checked)} />
              Khách đóng tiền cọc khi ký hợp đồng
            </label>
            {form.is_deposit_paid && (
              <div className="w-full sm:w-1/2">
                <label className="mb-1.5 block px-1 text-[9px] font-black uppercase tracking-widest text-[#8b5e34]/65">Phương thức đóng cọc</label>
                <AdminSelect
                  value={form.deposit_payment_method}
                  options={[
                    { value: '1', label: 'Tiền mặt' },
                    { value: '2', label: 'Chuyển khoản QR' },
                  ]}
                  onChange={(value: string | number) => onUpdate('deposit_payment_method', String(value))}
                />
              </div>
            )}
          </div>
        )}

        <section className="rounded-2xl border border-[#3d2a18]/10 bg-white/55 p-3">
          <div className="mb-3 flex items-center justify-between">
            <p className={labelClass}>Khách thuê</p>
            <button type="button" onClick={onAddTenant} className="rounded-xl bg-[#24170d] px-3 py-2 text-xs font-black text-[#fff4df]">
              <UserPlus className="mr-1 inline h-3.5 w-3.5" />
              Thêm
            </button>
          </div>
          <FieldError message={errors.tenants} />
          <div className="space-y-3">
            {form.tenants.map((tenant, index) => (
              <TenantRow
                key={index}
                index={index}
                row={tenant}
                options={tenantOptions.filter(
                  (opt) =>
                    !form.tenants.some(
                      (t, idx) => idx !== index && String(t.tenant_id) === String(opt.value)
                    )
                )}
                error={errors[`tenants.${index}`]}
                canRemove={form.tenants.length > 1}
                onChange={(patch) => onUpdateTenant(index, patch)}
                onRemove={() => onRemoveTenant(index)}
                isEditMode={editing || renewing}
                isRenewMode={renewing}
              />
            ))}
          </div>
        </section>

        <section className="rounded-2xl border border-[#3d2a18]/10 bg-white/55 p-3">
          <div className="mb-3 flex items-center justify-between">
            <p className={labelClass}>Phương tiện</p>
            <button type="button" onClick={onAddVehicle} className="rounded-xl bg-[#24170d] px-3 py-2 text-xs font-black text-[#fff4df]">
              <Car className="mr-1 inline h-3.5 w-3.5" />
              Thêm
            </button>
          </div>
          <FieldError message={errors.vehicles} />
          {form.vehicles.length === 0 && <p className="text-xs font-bold text-[#8b5e34]/70">Chưa thêm phương tiện vào hợp đồng.</p>}
          <div className="space-y-3">
            {form.vehicles.map((vehicle, index) => (
              <VehicleRow
                key={index}
                index={index}
                row={vehicle}
                options={vehicleOptions.filter(
                  (opt) =>
                    !form.vehicles.some(
                      (v, idx) => idx !== index && String(v.vehicle_id) === String(opt.value)
                    )
                )}
                error={errors[`vehicles.${index}`]}
                onChange={(patch) => onUpdateVehicle(index, patch)}
                onRemove={() => onRemoveVehicle(index)}
                isEditMode={editing || renewing}
                isRenewMode={renewing}
              />
            ))}
          </div>
        </section>

        <div>
          <label className={labelClass}>File hợp đồng</label>
          <label className="flex min-h-14 cursor-pointer items-center justify-center gap-2 rounded-2xl border border-dashed border-[#3d2a18]/15 bg-white/55 px-4 py-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/15">
            <FileText className="h-4 w-4" /> Chọn PDF/ảnh hợp đồng
            <input
              type="file"
              className="hidden"
              multiple
              accept="application/pdf,image/jpeg,image/png,image/webp"
              onChange={(event) => onUpdate('contract_files', Array.from(event.target.files || []))}
            />
          </label>
          {form.contract_files.length > 0 && <p className="mt-2 text-xs font-bold text-[#6f6254]">{form.contract_files.length} file đã chọn.</p>}
          <FieldError message={errors.contract_files} />
        </div>

        <div>
          <label className={labelClass}>Ghi chú</label>
          <textarea
            className={cn(inputClass, 'min-h-24 resize-none', errors.note && inputErrorClass)}
            value={form.note}
            onChange={(event) => onUpdate('note', event.target.value)}
            placeholder="Ghi chú điều khoản hoặc tình trạng hợp đồng"
          />
          <FieldError message={errors.note} />
        </div>

        <button
          type="button"
          disabled={isSaving}
          onClick={onSubmit}
          className="inline-flex min-h-14 w-full items-center justify-center gap-2 rounded-[1.25rem] bg-[#24170d] px-5 py-3.5 text-base font-black text-[#fff4df] shadow-lg shadow-[#24170d]/12 transition hover:bg-[#3d2a18] disabled:opacity-60"
        >
          <BadgeCheck className="h-5 w-5" />{' '}
          {isSaving ? 'Đang lưu...' : editing ? 'Cập nhật hợp đồng' : renewing ? 'Gia hạn hợp đồng' : 'Tạo hợp đồng'}
        </button>
      </div>
    </aside>
  )
}
