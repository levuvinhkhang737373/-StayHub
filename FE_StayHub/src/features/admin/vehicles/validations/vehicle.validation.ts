import type { AdminVehicleFormErrors, AdminVehicleFormValues } from '../types/vehicle.model'

export function validateVehicleForm(form: AdminVehicleFormValues) {
  const errors: AdminVehicleFormErrors = {}

  if (!form.building_id || !form.building_id.trim()) {
    errors.building_id = 'Phải chọn tòa nhà.'
  }

  if (!form.tenant_id || !form.tenant_id.trim()) {
    errors.tenant_id = 'Phải chọn khách thuê.'
  }

  if (!form.vehicle_type) {
    errors.vehicle_type = 'Phải chọn loại phương tiện.'
  } else if (![1, 2, 3, 4].includes(Number(form.vehicle_type))) {
    errors.vehicle_type = 'Loại phương tiện không hợp lệ.'
  }

  const isLicensePlateRequired = Number(form.vehicle_type) !== 2 && Number(form.vehicle_type) !== 4

  if (isLicensePlateRequired) {
    if (!form.license_plate || !form.license_plate.trim()) {
      errors.license_plate = 'Phải nhập biển số xe.'
    } else if (form.license_plate.trim().length > 30) {
      errors.license_plate = 'Biển số xe không được vượt quá 30 ký tự.'
    }
  } else {
    if (form.license_plate && form.license_plate.trim().length > 30) {
      errors.license_plate = 'Biển số xe không được vượt quá 30 ký tự.'
    }
  }

  return errors
}
