export type ExpenseCategoryFormValues = {
  name: string
  description: string
  is_active: boolean
}

export type ExpenseCategoryFormErrors = Partial<Record<keyof ExpenseCategoryFormValues, string>>

export function validateExpenseCategoryForm(form: ExpenseCategoryFormValues): ExpenseCategoryFormErrors {
  const errors: ExpenseCategoryFormErrors = {}
  const name = form.name.trim()
  const description = form.description.trim()

  if (!name) {
    errors.name = 'Vui lòng nhập tên danh mục chi phí.'
  } else if (name.length > 150) {
    errors.name = 'Tên danh mục chi phí tối đa 150 ký tự.'
  }

  if (description.length > 2000) {
    errors.description = 'Mô tả danh mục chi phí tối đa 2000 ký tự.'
  }

  if (typeof form.is_active !== 'boolean') {
    errors.is_active = 'Trạng thái danh mục chi phí không hợp lệ.'
  }

  return errors
}
