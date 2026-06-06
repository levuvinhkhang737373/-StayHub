export type RoomTypeFormValues = {
  name: string
  description: string
  status: number
}

export type RoomTypeFormErrors = Partial<Record<keyof RoomTypeFormValues, string>>

export function validateRoomTypeForm(form: RoomTypeFormValues): RoomTypeFormErrors {
  const errors: RoomTypeFormErrors = {}
  const name = form.name.trim()
  const description = form.description.trim()

  if (!name) {
    errors.name = 'Vui lòng nhập tên loại phòng.'
  } else if (name.length > 150) {
    errors.name = 'Tên loại phòng tối đa 150 ký tự.'
  }


  if (![1, 2].includes(Number(form.status))) {
    errors.status = 'Trạng thái loại phòng không hợp lệ.'
  }

  if (description.length > 2000) {
    errors.description = 'Mô tả loại phòng tối đa 2000 ký tự.'
  }

  return errors
}
