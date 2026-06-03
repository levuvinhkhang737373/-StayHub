import type { AdminMeterFormErrors, AdminMeterFormValues } from '../types/meter-api.model'

export function validateMeterForm(form: AdminMeterFormValues) {
  const errors: AdminMeterFormErrors = {}

  if (!form.room_id.trim()) {
    errors.room_id = 'Phải nhập số phòng (Ví dụ: BT201).'
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
    errors.initial_reading = 'Phải nhập chỉ số khởi tạo.'
  } else if (Number(form.initial_reading) < 0 || Number.isNaN(Number(form.initial_reading))) {
    errors.initial_reading = 'Chỉ số khởi tạo phải là số không âm.'
  }

  if (form.final_reading.trim()) {
    const finalVal = Number(form.final_reading)
    const initialVal = Number(form.initial_reading)
    if (Number.isNaN(finalVal) || finalVal < 0) {
      errors.final_reading = 'Chỉ số cuối cùng phải là số không âm.'
    } else if (!Number.isNaN(initialVal) && finalVal <= initialVal) {
      errors.final_reading = 'Chỉ số cuối phải lớn hơn chỉ số khởi tạo.'
    }
  }

  if (form.status && ![1, 2, 3, 4].includes(form.status)) {
    errors.status = 'Trạng thái đồng hồ không hợp lệ.'
  }

  if (Number(form.status) === 3 && !form.replaced_by_meter_id.trim()) {
    errors.replaced_by_meter_id = 'Phải chọn đồng hồ thay thế khi trạng thái là đã thay thế.'
  }

  if (form.replaced_by_meter_id.trim() && Number(form.replaced_by_meter_id) <= 0) {
    errors.replaced_by_meter_id = 'Đồng hồ thay thế không hợp lệ.'
  }

  return errors;
}
