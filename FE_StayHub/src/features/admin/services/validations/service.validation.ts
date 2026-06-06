export type ServiceFormValues = {
  name: string
  charge_method: number
  unit_name: string
  is_required: boolean
  is_active: boolean
}

export type ServiceFormErrors = Partial<Record<keyof ServiceFormValues, string>>

const chargeMethods = [1, 2, 3, 4, 5]

export function validateServiceForm(form: ServiceFormValues): ServiceFormErrors {
  const errors: ServiceFormErrors = {}
  const name = form.name.trim()
  const unitName = form.unit_name.trim()

  if (!name) {
    errors.name = 'Vui lòng nhập tên dịch vụ.'
  } else if (name.length > 150) {
    errors.name = 'Tên dịch vụ tối đa 150 ký tự.'
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
