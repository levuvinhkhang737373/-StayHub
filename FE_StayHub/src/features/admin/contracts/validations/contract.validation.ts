import type { ContractFormErrors, ContractFormValues } from '../types/contract-api.model'

const MAX_FILE_SIZE = 20 * 1024 * 1024
const ALLOWED_FILE_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp']
const MONEY_REGEX = /^\d{1,13}(\.\d{1,2})?$/
const CONTRACT_CODE_REGEX = /^[A-Za-z0-9_.-]+$/

export function validateContractForm(form: ContractFormValues, roomMaxOccupants?: number | null, isSuperAdmin = false): ContractFormErrors {
  const errors: ContractFormErrors = {}
  const contractCode = form.contract_code.trim()
  const roomPrice = form.room_price.trim()
  const depositAmount = form.deposit_amount.trim()

  if (contractCode) {
    if (contractCode.length > 100) {
      errors.contract_code = 'Mã hợp đồng tối đa 100 ký tự.'
    } else if (!CONTRACT_CODE_REGEX.test(contractCode)) {
      errors.contract_code = 'Mã hợp đồng chỉ được chứa chữ, số, dấu gạch ngang, gạch dưới hoặc dấu chấm.'
    }
  }

  if (isSuperAdmin && !form.building_id) {
    errors.building_id = 'Vui lòng chọn tòa nhà.'
  }

  if (!form.room_id) {
    errors.room_id = 'Vui lòng chọn phòng ký hợp đồng.'
  }

  if (!form.start_date) {
    errors.start_date = 'Vui lòng chọn ngày bắt đầu hợp đồng.'
  }

  if (!form.end_date) {
    errors.end_date = 'Vui lòng chọn ngày kết thúc hợp đồng.'
  }

  if (form.start_date && form.end_date && form.end_date < form.start_date) {
    errors.end_date = 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.'
  }

  if (form.actual_end_date && form.start_date && form.actual_end_date < form.start_date) {
    errors.actual_end_date = 'Ngày kết thúc thực tế phải lớn hơn hoặc bằng ngày bắt đầu.'
  }

  const billingDay = Number(form.billing_cycle_day)
  if (!form.billing_cycle_day) {
    errors.billing_cycle_day = 'Vui lòng nhập ngày chốt tiền hằng tháng.'
  } else if (!Number.isInteger(billingDay) || billingDay < 1 || billingDay > 28) {
    errors.billing_cycle_day = 'Ngày chốt tiền phải là số nguyên từ 1 đến 28.'
  }

  if (!roomPrice) {
    errors.room_price = 'Vui lòng nhập giá phòng.'
  } else if (!MONEY_REGEX.test(roomPrice)) {
    errors.room_price = 'Giá phòng phải là số tiền không âm và tối đa 2 chữ số thập phân.'
  }

  if (!depositAmount) {
    errors.deposit_amount = 'Vui lòng nhập tiền cọc.'
  } else if (!MONEY_REGEX.test(depositAmount)) {
    errors.deposit_amount = 'Tiền cọc phải là số tiền không âm và tối đa 2 chữ số thập phân.'
  } else if (Number(depositAmount) <= 0) {
    errors.deposit_amount = 'Tiền cọc trong hợp đồng phải lớn hơn 0.'
  }

  if (![1, 2, 3, 4].includes(Number(form.status))) {
    errors.status = 'Trạng thái hợp đồng không hợp lệ.'
  }

  if (form.note.trim().length > 2000) {
    errors.note = 'Ghi chú hợp đồng tối đa 2000 ký tự.'
  }

  validateFiles(form.contract_files, errors)
  validateTenants(form, errors, roomMaxOccupants)
  validateVehicles(form, errors)

  return errors
}

function validateFiles(files: File[], errors: ContractFormErrors) {
  if (files.length > 10) {
    errors.contract_files = 'Mỗi hợp đồng chỉ được tải tối đa 10 file.'
    return
  }

  const invalidFile = files.find((file) => !ALLOWED_FILE_TYPES.includes(file.type) || file.size > MAX_FILE_SIZE)
  if (invalidFile) {
    errors.contract_files = 'File hợp đồng chỉ hỗ trợ PDF/JPG/PNG/WEBP và mỗi file không vượt quá 20MB.'
  }
}

function validateTenants(form: ContractFormValues, errors: ContractFormErrors, roomMaxOccupants?: number | null) {
  if (form.tenants.length < 1) {
    errors.tenants = 'Hợp đồng phải có ít nhất 1 khách thuê.'
    return
  }

  const stayingCount = form.tenants.filter((tenant) => tenant.is_staying).length
  if (stayingCount < 1) {
    errors.tenants = 'Hợp đồng phải có ít nhất 1 khách thuê đang ở.'
    return
  }

  if (roomMaxOccupants && roomMaxOccupants > 0 && stayingCount > roomMaxOccupants) {
    errors.tenants = `Số khách thuê đang ở vượt quá sức chứa tối đa của phòng (tối đa ${roomMaxOccupants} người).`
    return
  }

  const tenantIds = form.tenants.map((tenant) => tenant.tenant_id).filter(Boolean)
  if (new Set(tenantIds).size !== tenantIds.length) {
    errors.tenants = 'Không được chọn trùng khách thuê trong cùng hợp đồng.'
    return
  }

  form.tenants.forEach((tenant, index) => {
    if (!tenant.tenant_id) {
      errors[`tenants.${index}`] = 'Vui lòng chọn khách thuê.'
      return
    }

    if (!tenant.join_date) {
      errors[`tenants.${index}`] = 'Vui lòng chọn ngày vào ở của khách thuê.'
      return
    }

    if (tenant.leave_date && tenant.leave_date < tenant.join_date) {
      errors[`tenants.${index}`] = 'Ngày rời đi phải lớn hơn hoặc bằng ngày vào ở.'
      return
    }

    if (tenant.billing_start_date && tenant.billing_start_date < form.start_date) {
      errors[`tenants.${index}`] = 'Ngày bắt đầu tính tiền không được nhỏ hơn ngày bắt đầu hợp đồng.'
      return
    }

    if (tenant.billing_end_date && tenant.billing_start_date && tenant.billing_end_date < tenant.billing_start_date) {
      errors[`tenants.${index}`] = 'Ngày kết thúc tính tiền phải lớn hơn hoặc bằng ngày bắt đầu tính tiền.'
    }
  })
}

function validateVehicles(form: ContractFormValues, errors: ContractFormErrors) {
  const vehicleIds = form.vehicles.map((vehicle) => vehicle.vehicle_id).filter(Boolean)
  if (new Set(vehicleIds).size !== vehicleIds.length) {
    errors.vehicles = 'Không được chọn trùng phương tiện trong cùng hợp đồng.'
    return
  }

  form.vehicles.forEach((vehicle, index) => {
    if (!vehicle.vehicle_id) {
      errors[`vehicles.${index}`] = 'Vui lòng chọn phương tiện.'
      return
    }

    if (!vehicle.started_at) {
      errors[`vehicles.${index}`] = 'Vui lòng chọn ngày bắt đầu gửi xe.'
      return
    }

    if (vehicle.ended_at && vehicle.ended_at < vehicle.started_at) {
      errors[`vehicles.${index}`] = 'Ngày kết thúc gửi xe phải lớn hơn hoặc bằng ngày bắt đầu.'
      return
    }

    if (![1, 2, 3].includes(Number(vehicle.charge_policy))) {
      errors[`vehicles.${index}`] = 'Chính sách tính phí xe không hợp lệ.'
      return
    }

    if (Number(vehicle.charge_policy) !== 3 && (!vehicle.monthly_fee.trim() || !MONEY_REGEX.test(vehicle.monthly_fee.trim()))) {
      errors[`vehicles.${index}`] = 'Phí gửi xe phải là số tiền không âm và tối đa 2 chữ số thập phân.'
      return
    }

    if (vehicle.billing_end_date && vehicle.billing_start_date && vehicle.billing_end_date < vehicle.billing_start_date) {
      errors[`vehicles.${index}`] = 'Ngày kết thúc tính phí xe phải lớn hơn hoặc bằng ngày bắt đầu tính phí.'
    }
  })
}
