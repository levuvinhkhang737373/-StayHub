export type AdminAccountFormValues = {
  username: string
  full_name: string
  email: string
  phone: string
  password: string
  role: number
  status: number
  gender: number | null
  date_of_birth: string
  address: string
  avatar_url: string
}

export type AdminAccountFormErrors = Partial<Record<keyof AdminAccountFormValues, string>>

export function validateAdminAccountForm(form: AdminAccountFormValues, isEdit: boolean): AdminAccountFormErrors {
  const errors: AdminAccountFormErrors = {}
  const username = form.username.trim()
  const fullName = form.full_name.trim()
  const email = form.email.trim()
  const phone = form.phone.trim()
  const password = form.password.trim()
  const address = form.address.trim()
  const avatarUrl = form.avatar_url.trim()

  if (!isEdit) {
    if (!username) {
      errors.username = 'Vui lòng nhập tên đăng nhập.'
    } else if (username.length > 255) {
      errors.username = 'Tên đăng nhập tối đa 255 ký tự.'
    }
  }

  if (!fullName) {
    errors.full_name = 'Vui lòng nhập họ tên admin.'
  } else if (fullName.length > 255) {
    errors.full_name = 'Họ tên admin tối đa 255 ký tự.'
  }

  if (!email) {
    errors.email = 'Vui lòng nhập email admin.'
  } else if (!/^\S+@\S+\.\S+$/.test(email)) {
    errors.email = 'Email admin không hợp lệ.'
  } else if (email.length > 255) {
    errors.email = 'Email admin tối đa 255 ký tự.'
  }

  if (!phone) {
    errors.phone = 'Vui lòng nhập số điện thoại admin.'
  } else if (phone.length > 20) {
    errors.phone = 'Số điện thoại admin tối đa 20 ký tự.'
  }

  if (isEdit && password && password.length < 6) {
    errors.password = 'Mật khẩu admin tối thiểu 6 ký tự.'
  } else if (isEdit && password.length > 255) {
    errors.password = 'Mật khẩu admin tối đa 255 ký tự.'
  }

  if (![1, 2, 3].includes(Number(form.role))) {
    errors.role = 'Vai trò admin không hợp lệ.'
  }

  if (![1, 2].includes(Number(form.status))) {
    errors.status = 'Trạng thái admin không hợp lệ.'
  }

  if (form.gender !== null && ![1, 2].includes(Number(form.gender))) {
    errors.gender = 'Giới tính admin không hợp lệ.'
  }

  if (address.length > 500) {
    errors.address = 'Địa chỉ admin tối đa 500 ký tự.'
  }

  if (avatarUrl.length > 2048) {
    errors.avatar_url = 'Đường dẫn ảnh đại diện tối đa 2048 ký tự.'
  }

  return errors
}
