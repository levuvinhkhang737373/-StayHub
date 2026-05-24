export type AssetTemplateBuildingOption = {
  id: number
}

export type AssetTemplateFormValues = {
  building_id: string
  name: string
  default_unit_name: number
  description: string
  status: number
}

export type AssetTemplateFormErrors = Partial<Record<keyof AssetTemplateFormValues, string>>

export function validateAssetTemplateForm(
  form: AssetTemplateFormValues,
  buildings: AssetTemplateBuildingOption[],
  options: { requireBuilding: boolean },
): AssetTemplateFormErrors {
  const errors: AssetTemplateFormErrors = {}
  const name = form.name.trim()
  const description = form.description.trim()
  const buildingId = form.building_id ? Number(form.building_id) : undefined

  if (!name) {
    errors.name = 'Vui lòng nhập tên mẫu tài sản.'
  } else if (name.length > 150) {
    errors.name = 'Tên mẫu tài sản tối đa 150 ký tự.'
  }

  if (options.requireBuilding && !buildingId) {
    errors.building_id = 'Vui lòng chọn tòa nhà áp dụng.'
  } else if (buildingId && !buildings.some((building) => building.id === buildingId)) {
    errors.building_id = 'Tòa nhà áp dụng không hợp lệ.'
  }

  if (![1, 2, 3].includes(Number(form.default_unit_name))) {
    errors.default_unit_name = 'Đơn vị mặc định không hợp lệ.'
  }

  if (![1, 2].includes(Number(form.status))) {
    errors.status = 'Trạng thái mẫu tài sản không hợp lệ.'
  }

  if (description.length > 2000) {
    errors.description = 'Mô tả mẫu tài sản tối đa 2000 ký tự.'
  }

  return errors
}
