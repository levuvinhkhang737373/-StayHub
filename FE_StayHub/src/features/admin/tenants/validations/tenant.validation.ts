import { buildingAllowsTenantGender, GENDER_POLICY_ERROR_MESSAGE } from '../../shared/config/gender-policy'

export type TenantFormValues = {
  building_id?: number | ''
  username: string
  full_name: string
  email: string
  phone: string
  date_of_birth: string
  gender: number
  status: number
  identity_type: number
  identity_number: string
  permanent_address: string
  current_address: string
  front_image: File | null
  back_image: File | null
  delete_front_image: boolean
  delete_back_image: boolean
}

export type TenantFormErrors = Partial<Record<keyof TenantFormValues, string>>

const MAX_IMAGE_SIZE = 10 * 1024 * 1024
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp']

export function validateTenantForm(form: TenantFormValues, isSuperAdmin = false, buildingGenderPolicy?: number | null): TenantFormErrors {
  const errors: TenantFormErrors = {}
  
  if (isSuperAdmin && !form.building_id) {
    errors.building_id = 'Vui lòng chọn tòa nhà.'
  }

  const username = form.username.trim()
  const fullName = form.full_name.trim()
  const email = form.email.trim()
  const phone = form.phone.trim()
  const identityNumber = form.identity_number.trim()
  const permanentAddress = form.permanent_address.trim()
  const currentAddress = form.current_address.trim()

  if (!username) {
    errors.username = 'Vui lòng nhập tên đăng nhập khách thuê.'
  } else if (username.length > 255) {
    errors.username = 'Tên đăng nhập khách thuê tối đa 255 ký tự.'
  } else if (!/^[A-Za-z0-9_.-]+$/.test(username)) {
    errors.username = 'Tên đăng nhập chỉ được chứa chữ, số, dấu gạch ngang, gạch dưới hoặc dấu chấm.'
  }

  if (!fullName) {
    errors.full_name = 'Vui lòng nhập họ tên khách thuê.'
  } else if (fullName.length > 150) {
    errors.full_name = 'Họ tên khách thuê tối đa 150 ký tự.'
  }

  if (!email) {
    errors.email = 'Vui lòng nhập email để hệ thống gửi mật khẩu.'
  } else if (!/^\S+@\S+\.\S+$/.test(email)) {
    errors.email = 'Email khách thuê không hợp lệ.'
  } else if (email.length > 150) {
    errors.email = 'Email khách thuê tối đa 150 ký tự.'
  }

  const VIETNAMESE_PHONE_REGEX = /^(032|033|034|035|036|037|038|039|086|096|097|098|081|082|083|084|085|088|091|094|070|076|077|078|079|089|090|093|056|058|092|059|099|087)\d{7}$/

  if (!phone) {
    errors.phone = 'Vui lòng nhập số điện thoại khách thuê.'
  } else if (!VIETNAMESE_PHONE_REGEX.test(phone)) {
    errors.phone = 'Số điện thoại phải gồm 10 số và thuộc nhà mạng Việt Nam hợp lệ.'
  }

  if (!form.date_of_birth) {
    errors.date_of_birth = 'Vui lòng nhập ngày sinh khách thuê.'
  } else if (new Date(form.date_of_birth) > new Date()) {
    errors.date_of_birth = 'Ngày sinh khách thuê không được lớn hơn ngày hiện tại.'
  }

  if (![1, 2].includes(Number(form.gender))) {
    errors.gender = 'Giới tính khách thuê không hợp lệ.'
  } else if (buildingGenderPolicy !== null && buildingGenderPolicy !== undefined && !buildingAllowsTenantGender(buildingGenderPolicy, form.gender)) {
    errors.gender = GENDER_POLICY_ERROR_MESSAGE
  }

  if (![1, 2].includes(Number(form.status))) {
    errors.status = 'Trạng thái khách thuê không hợp lệ.'
  }

  if (![1, 2, 3].includes(Number(form.identity_type))) {
    errors.identity_type = 'Loại giấy tờ khách thuê không hợp lệ.'
  }

  if (!identityNumber) {
    errors.identity_number = 'Vui lòng nhập số giấy tờ khách thuê.'
  } else {
    const idType = Number(form.identity_type)
    if (idType === 1) { // CCCD
      if (!/^\d{12}$/.test(identityNumber)) {
        errors.identity_number = 'Số CCCD phải gồm đúng 12 chữ số.'
      }
    } else if (idType === 3) { // Hộ chiếu
      if (!/^[A-Za-z0-9]{9}$/.test(identityNumber)) {
        errors.identity_number = 'Số hộ chiếu phải gồm đúng 9 ký tự (chữ và số).'
      }
    } else if (identityNumber.length > 30) {
      errors.identity_number = 'Số giấy tờ khách thuê tối đa 30 ký tự.'
    }
  }

  if (permanentAddress.length > 500) {
    errors.permanent_address = 'Địa chỉ thường trú tối đa 500 ký tự.'
  }

  if (currentAddress.length > 500) {
    errors.current_address = 'Địa chỉ hiện tại tối đa 500 ký tự.'
  }

  validateImage(form.front_image, 'front_image', 'Ảnh mặt trước giấy tờ', errors)
  validateImage(form.back_image, 'back_image', 'Ảnh mặt sau giấy tờ', errors)

  return errors
}

function validateImage(file: File | null, key: 'front_image' | 'back_image', label: string, errors: TenantFormErrors) {
  if (!file) return

  if (!ALLOWED_IMAGE_TYPES.includes(file.type)) {
    errors[key] = `${label} chỉ hỗ trợ jpg, jpeg, png hoặc webp.`
    return
  }

  if (file.size > MAX_IMAGE_SIZE) {
    errors[key] = `${label} không được vượt quá 10MB.`
  }
}
