export interface ChangePasswordForm {
  currentPassword: string
  newPassword: string
  newPasswordConfirmation: string
}

export type ChangePasswordErrors = Partial<Record<keyof ChangePasswordForm, string>>

export interface ProfileForm {
  fullName: string
  email: string
  phone: string
}

export type ProfileFormErrors = Partial<Record<keyof ProfileForm, string>>

const phoneRegex = /^(0[3|5|7|8|9])+([0-9]{8})$/
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

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

export function validateProfileForm(form: ProfileForm): ProfileFormErrors {
  const errors: ProfileFormErrors = {}
  const fullName = form.fullName.trim()
  const email = form.email.trim()
  const phone = form.phone.trim()

  if (!fullName) {
    errors.fullName = 'Họ và tên không được để trống.'
  } else if (fullName.length > 150) {
    errors.fullName = 'Họ và tên không được vượt quá 150 ký tự.'
  }

  if (!email) {
    errors.email = 'Email không được để trống.'
  } else if (!emailRegex.test(email)) {
    errors.email = 'Email không đúng định dạng.'
  } else if (email.length > 150) {
    errors.email = 'Email không được vượt quá 150 ký tự.'
  }

  if (phone && !phoneRegex.test(phone)) {
    errors.phone = 'Số điện thoại không đúng định dạng Việt Nam (phải gồm 10 chữ số và bắt đầu bằng 03, 05, 07, 08, 09).'
  }

  return errors
}
