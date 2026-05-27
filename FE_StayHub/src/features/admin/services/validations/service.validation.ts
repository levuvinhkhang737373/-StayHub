export type ServiceFormValues = {
  service_code: string
  name: string
  service_type: string
  charge_method: number
  unit_name: string
  is_required: boolean
  is_active: boolean
}

export type ServiceFormErrors = Partial<Record<keyof ServiceFormValues, string>>

const serviceTypes = ['dien', 'nuoc', 'internet', 'rac', 'gui_xe', 've_sinh', 'khac']
const chargeMethods = [1, 2, 3, 4, 5]

export function validateServiceForm(form: ServiceFormValues): ServiceFormErrors {
  const errors: ServiceFormErrors = {}
  const serviceCode = form.service_code.trim()
  const name = form.name.trim()
  const unitName = form.unit_name.trim()

  if (!serviceCode) {
    errors.service_code = 'Vui lòng nhập mã dịch vụ.'
  } else if (serviceCode.length > 50) {
    errors.service_code = 'Mã dịch vụ tối đa 50 ký tự.'
  }

  if (!name) {
    errors.name = 'Vui lòng nhập tên dịch vụ.'
  } else if (name.length > 150) {
    errors.name = 'Tên dịch vụ tối đa 150 ký tự.'
  }

  if (!serviceTypes.includes(form.service_type)) {
    errors.service_type = 'Loại dịch vụ không hợp lệ.'
  }

  if (!chargeMethods.includes(Number(form.charge_method))) {
    errors.charge_method = 'Phương thức tính phí không hợp lệ.'
  }

  if (unitName.length > 50) {
    errors.unit_name = 'Đơn vị tính tối đa 50 ký tự.'
  }

  if (typeof form.is_required !== 'boolean') {
    errors.is_required = 'Trạng thái bắt buộc không hợp lệ.'
  }

  if (typeof form.is_active !== 'boolean') {
    errors.is_active = 'Trạng thái hoạt động không hợp lệ.'
  }

  return errors
}
