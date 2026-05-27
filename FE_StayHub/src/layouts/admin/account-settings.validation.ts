export interface ChangePasswordForm {
  currentPassword: string
  newPassword: string
  newPasswordConfirmation: string
}

export type ChangePasswordErrors = Partial<Record<keyof ChangePasswordForm, string>>

export function validateDeleteFaceIdPassword(password: string): string | null {
  if (password.trim().length < 6) {
    return 'Vui lòng nhập mật khẩu hiện tại để xóa FaceID.'
  }

  return null
}

export function validateChangePasswordForm(form: ChangePasswordForm): ChangePasswordErrors {
  const errors: ChangePasswordErrors = {}
  const currentPassword = form.currentPassword.trim()

  if (currentPassword.length < 6) {
    errors.currentPassword = 'Vui lòng nhập mật khẩu hiện tại tối thiểu 6 ký tự.'
  }

  if (form.newPassword.length < 6) {
    errors.newPassword = 'Vui lòng nhập mật khẩu mới tối thiểu 6 ký tự.'
  }

  if (!form.newPasswordConfirmation) {
    errors.newPasswordConfirmation = 'Vui lòng xác nhận mật khẩu mới.'
  } else if (form.newPasswordConfirmation.length < 6) {
    errors.newPasswordConfirmation = 'Xác nhận mật khẩu mới tối thiểu 6 ký tự.'
  } else if (form.newPassword !== form.newPasswordConfirmation) {
    errors.newPasswordConfirmation = 'Xác nhận mật khẩu mới không khớp.'
  }

  if (currentPassword && form.newPassword && currentPassword === form.newPassword) {
    errors.newPassword = 'Mật khẩu mới không được trùng mật khẩu hiện tại.'
  }

  return errors
}
