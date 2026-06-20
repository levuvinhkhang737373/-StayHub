import { useState } from 'react'
import { AdminSelect } from '../../../shared/components/AdminSelect'
import { createAdminVehicle } from '../../services/contracts.service'
import type { AdminVehicleOptionResource } from '../../types/contract-api.model'
import { getVisibleErrorMessage } from '../../utils/contract.helpers'
import { inputClass, labelClass } from '../form/form-elements'

const vehicleTypeOptions = [
  { value: 1, label: 'Xe máy' },
  { value: 2, label: 'Xe đạp' },
  { value: 3, label: 'Ô tô' },
  { value: 4, label: 'Xe điện' },
]

export function CreateVehicleModal({
  tenantOptions,
  onClose,
  onCreated,
}: {
  tenantOptions: Array<{ value: number; label: string }>
  onClose: () => void
  onCreated: (vehicle: AdminVehicleOptionResource) => void
}) {
  const [vehicleForm, setVehicleForm] = useState({
    tenant_id: tenantOptions.length === 1 ? String(tenantOptions[0].value) : '',
    vehicle_type: '1',
    license_plate: '',
    brand: '',
    color: '',
  })
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async () => {
    if (!vehicleForm.tenant_id) {
      setError('Vui lòng chọn khách thuê sở hữu xe.')
      return
    }
    if (!vehicleForm.license_plate.trim()) {
      setError('Vui lòng nhập biển số xe.')
      return
    }

    try {
      setIsSaving(true)
      setError(null)
      const response = await createAdminVehicle({
        tenant_id: Number(vehicleForm.tenant_id),
        vehicle_type: Number(vehicleForm.vehicle_type),
        license_plate: vehicleForm.license_plate.trim(),
        brand: vehicleForm.brand.trim() || undefined,
        color: vehicleForm.color.trim() || undefined,
      })
      if (response.result) {
        onCreated(response.result)
      }
    } catch (err) {
      setError(getVisibleErrorMessage(err, 'Không thể tạo phương tiện.'))
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true">
      <button type="button" aria-label="Đóng" onClick={onClose} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />
      <div className="relative z-10 w-full max-w-md rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] p-5 shadow-2xl">
        <h2 className="text-lg font-black text-[#24170d]">Tạo phương tiện mới</h2>
        <p className="mt-1 text-xs font-bold text-[#8b5e34]/70">Xe sẽ được tạo và tự động thêm vào hợp đồng.</p>

        {error && <p className="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-black text-rose-700">{error}</p>}

        <div className="mt-4 space-y-3">
          <div>
            <label className={labelClass}>
              Khách thuê sở hữu <span className="text-rose-500">*</span>
            </label>
            <AdminSelect
              value={vehicleForm.tenant_id}
              options={tenantOptions.map((t) => ({ value: t.value, label: t.label, tone: 'default' as const }))}
              placeholder="Chọn khách thuê"
              onChange={(value: string | number) => setVehicleForm((f) => ({ ...f, tenant_id: String(value) }))}
            />
          </div>
          <div>
            <label className={labelClass}>
              Loại xe <span className="text-rose-500">*</span>
            </label>
            <AdminSelect
              value={Number(vehicleForm.vehicle_type)}
              options={vehicleTypeOptions.map((t) => ({ ...t, tone: 'default' as const }))}
              onChange={(value: string | number) => setVehicleForm((f) => ({ ...f, vehicle_type: String(value) }))}
            />
          </div>
          <div>
            <label className={labelClass}>
              Biển số xe <span className="text-rose-500">*</span>
            </label>
            <input
              className={inputClass}
              value={vehicleForm.license_plate}
              onChange={(e) => setVehicleForm((f) => ({ ...f, license_plate: e.target.value }))}
              placeholder="59F1-12345"
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className={labelClass}>Hiệu xe</label>
              <input className={inputClass} value={vehicleForm.brand} onChange={(e) => setVehicleForm((f) => ({ ...f, brand: e.target.value }))} placeholder="Honda" />
            </div>
            <div>
              <label className={labelClass}>Màu xe</label>
              <input className={inputClass} value={vehicleForm.color} onChange={(e) => setVehicleForm((f) => ({ ...f, color: e.target.value }))} placeholder="Đen" />
            </div>
          </div>
        </div>

        <div className="mt-5 flex gap-3">
          <button type="button" onClick={onClose} className="h-12 flex-1 rounded-xl border border-[#3d2a18]/10 bg-white text-sm font-black text-[#8b5e34]">
            Hủy
          </button>
          <button
            type="button"
            disabled={isSaving}
            onClick={handleSubmit}
            className="h-12 flex-1 rounded-xl bg-[#24170d] text-sm font-black text-[#fff4df] disabled:opacity-60"
          >
            {isSaving ? 'Đang tạo...' : 'Tạo xe'}
          </button>
        </div>
      </div>
    </div>
  )
}
