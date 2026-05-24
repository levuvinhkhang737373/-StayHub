export type AdminLoginFormValues = {
  username: string
  password: string
}

export type AdminLoginFormErrors = Partial<Record<keyof AdminLoginFormValues, string>>

export function validateAdminLoginForm(form: AdminLoginFormValues): AdminLoginFormErrors {
  const errors: AdminLoginFormErrors = {}

  if (!form.username.trim()) {
    errors.username = 'Vui lòng nhập tên đăng nhập.'
  }

  if (!form.password.trim()) {
    errors.password = 'Vui lòng nhập mật khẩu.'
  } else if (form.password.trim().length < 6) {
    errors.password = 'Mật khẩu tối thiểu 6 ký tự.'
  }

  return errors
}

export function hasAdminLoginErrors(errors: AdminLoginFormErrors): boolean {
  return Object.keys(errors).length > 0
}
