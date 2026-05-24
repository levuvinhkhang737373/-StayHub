export type RoomTypeFormValues = {
  name: string
  building_id: string
  default_price: string
  description: string
  status: number
}

export type RoomTypeFormErrors = Partial<Record<keyof RoomTypeFormValues, string>>

interface RoomTypeValidationOptions {
  allowedBuildingIds: number[]
  requireBuilding: boolean
}

export function validateRoomTypeForm(form: RoomTypeFormValues, options: RoomTypeValidationOptions): RoomTypeFormErrors {
  const errors: RoomTypeFormErrors = {}
  const name = form.name.trim()
  const price = Number(form.default_price)
  const description = form.description.trim()
  const buildingId = form.building_id ? Number(form.building_id) : undefined

  if (!name) {
    errors.name = 'Vui lòng nhập tên loại phòng.'
  } else if (name.length > 150) {
    errors.name = 'Tên loại phòng tối đa 150 ký tự.'
  }

  if (options.requireBuilding && !buildingId) {
    errors.building_id = 'Vui lòng chọn tòa nhà cho loại phòng.'
  } else if (buildingId && !options.allowedBuildingIds.includes(buildingId)) {
    errors.building_id = 'Tòa nhà của loại phòng không hợp lệ.'
  }

  if (!form.default_price.trim()) {
    errors.default_price = 'Vui lòng nhập giá mặc định.'
  } else if (!Number.isFinite(price) || price < 0) {
    errors.default_price = 'Giá mặc định phải là số lớn hơn hoặc bằng 0.'
  }

  if (![1, 2].includes(Number(form.status))) {
    errors.status = 'Trạng thái loại phòng không hợp lệ.'
  }

  if (description.length > 2000) {
    errors.description = 'Mô tả loại phòng tối đa 2000 ký tự.'
  }

  return errors
}
