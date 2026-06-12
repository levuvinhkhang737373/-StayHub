import type { AdminMeterFormErrors, AdminMeterFormValues } from '../types/meter-api.model'

export function validateMeterForm(form: AdminMeterFormValues) {
  const errors: AdminMeterFormErrors = {}

  if (!form.building_id || !form.building_id.trim()) {
    errors.building_id = 'Phải chọn tòa nhà.'
  }

  if (!form.room_id || !form.room_id.trim()) {
    errors.room_id = 'Phải chọn phòng.'
  }

  if (!form.service_id.trim()) {
    errors.service_id = 'Phải chọn dịch vụ.'
  } else if (Number(form.service_id) <= 0) {
    errors.service_id = 'Dịch vụ không hợp lệ.'
  }

  if (!form.meter_type) {
    errors.meter_type = 'Phải chọn loại đồng hồ.'
  }

  if (!form.initial_reading.trim()) {
    errors.initial_reading = 'Phải nhập chỉ số.'
  } else if (Number(form.initial_reading) < 0 || Number.isNaN(Number(form.initial_reading))) {
    errors.initial_reading = 'Chỉ số phải là số không âm.'
  }



  if (form.status && ![1, 2, 3, 4].includes(form.status)) {
    errors.status = 'Trạng thái đồng hồ không hợp lệ.'
  }

  if (Number(form.status) === 3 && !form.replaced_by_meter_id.trim()) {
    errors.replaced_by_meter_id = 'Phải chọn đồng hồ thay thế khi trạng thái là đã bị thay thế.'
  }

  if (form.replaced_by_meter_id.trim() && Number(form.replaced_by_meter_id) <= 0) {
    errors.replaced_by_meter_id = 'Đồng hồ thay thế không hợp lệ.'
  }

  return errors;
}
