export type SettingFormValues = {
  building_id: string
  setting_label: string
  setting_name: string
  setting_value: string
  description: string
  is_public: boolean
}

export type SettingFormErrors = Partial<Record<keyof SettingFormValues, string>>

interface SettingValidationOptions {
  allowedBuildingIds: number[]
  requireBuilding: boolean
}

const settingNamePattern = /^[A-Za-z0-9_.-]+$/

export function validateSettingForm(form: SettingFormValues, options: SettingValidationOptions): SettingFormErrors {
  const errors: SettingFormErrors = {}
  const buildingId = form.building_id ? Number(form.building_id) : undefined
  const settingLabel = form.setting_label.trim()
  const settingName = form.setting_name.trim()
  const settingValue = form.setting_value.trim()
  const description = form.description.trim()

  if (options.requireBuilding && !buildingId) {
    errors.building_id = 'Vui lòng chọn tòa nhà áp dụng.'
  } else if (buildingId && !options.allowedBuildingIds.includes(buildingId)) {
    errors.building_id = 'Tòa nhà áp dụng không hợp lệ.'
  }

  if (!settingLabel) {
    errors.setting_label = 'Vui lòng nhập tên hiển thị cài đặt.'
  } else if (settingLabel.length > 150) {
    errors.setting_label = 'Tên hiển thị cài đặt tối đa 150 ký tự.'
  }

  if (!settingName) {
    errors.setting_name = 'Vui lòng nhập khóa cài đặt.'
  } else if (settingName.length > 255) {
    errors.setting_name = 'Khóa cài đặt tối đa 255 ký tự.'
  } else if (!settingNamePattern.test(settingName)) {
    errors.setting_name = 'Khóa cài đặt chỉ được chứa chữ, số, dấu gạch dưới, gạch ngang hoặc dấu chấm.'
  }

  if (settingValue.length > 500) {
    errors.setting_value = 'Giá trị cài đặt tối đa 500 ký tự.'
  }

  if (description.length > 500) {
    errors.description = 'Mô tả cài đặt tối đa 500 ký tự.'
  }

  return errors
}
