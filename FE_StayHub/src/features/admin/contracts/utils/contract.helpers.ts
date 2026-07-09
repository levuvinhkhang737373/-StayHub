import { getVisibleErrorMessage as getSharedVisibleErrorMessage } from '../../shared/utils/error-message'
import { formatMoneyInput, parseMoneyInput } from '../../../../shared/lib/utils/format'
import type {
  AdminContractPayload,
  AdminContractResource,
  AdminPaginationMeta,
  ContractFormValues,
  ContractTenantFormRow,
} from '../types/contract-api.model'

export const STATUS_PENDING_SIGN = 0
export const STATUS_ACTIVE = 1
export const STATUS_EXPIRED = 2
export const STATUS_LIQUIDATED = 3
export const STATUS_CANCELLED = 4
export const CHARGE_MONTHLY = 1
export const CHARGE_DAILY = 2
export const CHARGE_FREE = 3

export const todayStr = new Date().toISOString().slice(0, 10)
export const oneYearLaterStr = (() => {
  const date = new Date()
  date.setFullYear(date.getFullYear() + 1)
  date.setDate(date.getDate() - 1)
  return date.toISOString().slice(0, 10)
})()

export const defaultTenantRow: ContractTenantFormRow = {
  tenant_id: '',
  join_date: todayStr,
  leave_date: '',
  billing_start_date: todayStr,
  billing_end_date: '',
  is_staying: true,
}

export const defaultForm: ContractFormValues = {
  contract_code: '',
  building_id: '',
  room_id: '',
  start_date: todayStr,
  end_date: oneYearLaterStr,
  actual_end_date: '',
  room_price: '',
  deposit_amount: '',
  status: STATUS_PENDING_SIGN,
  contract_files: [],
  delete_contract_files: [],
  note: '',
  parent_contract_id: '',
  renew_from_contract_id: '',
  tenants: [{ ...defaultTenantRow }],
  vehicles: [],
  deposit_transactions: [],
  services: [],
  is_deposit_paid: true,
  deposit_payment_method: '2',
}

export const statusOptions = [
  { value: '', label: 'Tất cả trạng thái', tone: 'default' as const },
  { value: STATUS_PENDING_SIGN, label: 'Chờ ký', tone: 'warning' as const },
  { value: STATUS_ACTIVE, label: 'Đang hiệu lực', tone: 'success' as const },
  { value: STATUS_EXPIRED, label: 'Hết hạn', tone: 'warning' as const },
  { value: STATUS_LIQUIDATED, label: 'Đã thanh lý', tone: 'success' as const },
  { value: STATUS_CANCELLED, label: 'Đã hủy', tone: 'danger' as const },
]

export const createStatusOptions = [
  { value: STATUS_PENDING_SIGN, label: 'Chờ ký', tone: 'warning' as const },
  { value: STATUS_ACTIVE, label: 'Đang hiệu lực', tone: 'success' as const },
]

export const chargePolicyOptions = [
  { value: CHARGE_MONTHLY, label: 'Tính theo tháng', tone: 'default' as const },
  { value: CHARGE_DAILY, label: 'Tính theo ngày', tone: 'warning' as const },
  { value: CHARGE_FREE, label: 'Miễn phí', tone: 'success' as const },
]

export const perPageOptions = [
  { value: 5, label: '5 dòng', tone: 'default' as const },
  { value: 10, label: '10 dòng', tone: 'default' as const },
  { value: 20, label: '20 dòng', tone: 'default' as const },
  { value: 50, label: '50 dòng', tone: 'default' as const },
]

export type ContractsResult = { data?: AdminContractResource[]; meta?: AdminPaginationMeta | null } | AdminContractResource[] | null | undefined

export function normalizeContracts(result: ContractsResult) {
  if (!result) return { data: [] as AdminContractResource[], meta: null as AdminPaginationMeta | null }
  if (Array.isArray(result)) return { data: result, meta: null }
  return { data: result.data || [], meta: result.meta || null }
}

export function getResourceList<T>(result: { data?: T[] } | T[] | null | undefined): T[] {
  if (!result) return []
  if (Array.isArray(result)) return result
  return result.data || []
}

export function getVisibleErrorMessage(error: unknown, fallback: string) {
  return getSharedVisibleErrorMessage(error, fallback)
}


export function getStatusLabel(status?: number | null) {
  if (Number(status) === STATUS_PENDING_SIGN) return 'Chờ ký'
  if (Number(status) === STATUS_ACTIVE) return 'Đang hiệu lực'
  if (Number(status) === STATUS_EXPIRED) return 'Hết hạn'
  if (Number(status) === STATUS_LIQUIDATED) return 'Đã thanh lý'
  if (Number(status) === STATUS_CANCELLED) return 'Đã hủy'
  return 'Không xác định'
}

export function toDate(value: string) {
  if (!value) return undefined
  const [year, month, day] = value.split('-').map(Number)
  return new Date(year, month - 1, day)
}

export function contractToForm(contract: AdminContractResource, isRenew = false): ContractFormValues {
  const tenants = (contract.contract_tenants || []).map((tenant) => ({
    tenant_id: String(tenant.tenant_id),
    join_date: tenant.join_date || '',
    leave_date: tenant.leave_date || '',
    billing_start_date: tenant.billing_start_date || '',
    billing_end_date: tenant.billing_end_date || '',
    is_staying: tenant.is_staying !== false,
  }))

  const contractVehicles = contract.contract_vehicles || []
  const filteredVehicles = isRenew
    ? contractVehicles.filter((vehicle) => !vehicle.ended_at || vehicle.ended_at === contract.end_date || vehicle.ended_at === contract.actual_end_date)
    : contractVehicles.filter((vehicle) => vehicle.is_active !== false)

  return {
    contract_code: contract.contract_code || '',
    building_id: String(contract.room?.building_id || contract.building_id || ''),
    room_id: String(contract.room_id || ''),
    start_date: contract.start_date || '',
    end_date: contract.end_date || '',
    actual_end_date: contract.actual_end_date || '',
    room_price: formatMoneyInput(contract.room_price || ''),
    deposit_amount: formatMoneyInput(contract.deposit_amount || '0'),
    status: Number(contract.status || STATUS_ACTIVE),
    contract_files: [],
    delete_contract_files: [],
    note: contract.note || '',
    parent_contract_id: contract.parent_contract_id ? String(contract.parent_contract_id) : '',
    renew_from_contract_id: contract.renew_from_contract_id ? String(contract.renew_from_contract_id) : '',
    tenants: tenants.length > 0 ? tenants : [{ ...defaultTenantRow }],
    vehicles: filteredVehicles.map((vehicle) => ({
      vehicle_id: String(vehicle.vehicle_id),
      started_at: vehicle.started_at || '',
      ended_at: vehicle.ended_at || '',
      billing_start_date: vehicle.billing_start_date || '',
      billing_end_date: vehicle.billing_end_date || '',
      monthly_fee: formatMoneyInput(vehicle.monthly_fee || '0'),
      charge_policy: Number(vehicle.charge_policy || CHARGE_MONTHLY),
      is_active: vehicle.is_active !== false,
    })),
    deposit_transactions: [],
    services: (contract.room_services || []).map((service) => ({
      service_id: String(service.id),
      name: service.name || '',
      slug: (service as any).slug || '',
      charge_method_label: service.charge_method_label || '',
      unit_name: service.unit_name || '',
      price: formatMoneyInput(service.price || '0'),
    })),
    is_deposit_paid: contract.is_deposit_paid !== false,
    deposit_payment_method: String(contract.deposit_transactions?.[0]?.payment_method || '2'),
  }
}

export function buildPayload(form: ContractFormValues, includeStatus: boolean, includeActualEndDate = true): AdminContractPayload {
  const isQr = form.is_deposit_paid && form.deposit_payment_method === '2'
  const payload: AdminContractPayload = {
    contract_code: form.contract_code.trim(),
    room_id: Number(form.room_id),
    start_date: form.start_date,
    end_date: form.end_date,
    room_price: parseMoneyInput(form.room_price.trim()),
    deposit_amount: parseMoneyInput(form.deposit_amount.trim()),
    contract_files: form.contract_files,
    delete_contract_files: form.delete_contract_files,
    note: form.note.trim() || null,
    tenants: form.tenants.map((tenant) => ({
      tenant_id: Number(tenant.tenant_id),
      join_date: tenant.join_date,
      leave_date: tenant.leave_date || null,
      billing_start_date: tenant.billing_start_date || tenant.join_date,
      billing_end_date: tenant.billing_end_date || tenant.leave_date || null,
      is_staying: tenant.is_staying,
    })),
    vehicles: form.vehicles.map((vehicle) => ({
      vehicle_id: Number(vehicle.vehicle_id),
      started_at: vehicle.started_at,
      ended_at: vehicle.ended_at || null,
      billing_start_date: vehicle.billing_start_date || vehicle.started_at,
      billing_end_date: vehicle.billing_end_date || vehicle.ended_at || null,
      monthly_fee: Number(vehicle.charge_policy) === CHARGE_FREE ? '0' : parseMoneyInput(vehicle.monthly_fee.trim()),
      charge_policy: Number(vehicle.charge_policy),
      is_active: vehicle.is_active,
    })),
    deposit_transactions: form.deposit_transactions.map((transaction) => ({
      transaction_type: Number(transaction.transaction_type),
      amount: transaction.amount.trim(),
      transaction_date: transaction.transaction_date,
      payment_method: Number(transaction.payment_method),
      note: transaction.note.trim() || null,
    })),
    services: form.services.map((service) => ({
      service_id: Number(service.service_id),
      price: parseMoneyInput(service.price.trim()),
    })),
    is_deposit_paid: isQr ? false : form.is_deposit_paid,
    deposit_payment_method: isQr ? null : (form.is_deposit_paid ? Number(form.deposit_payment_method) : null),
  }

  if (includeActualEndDate) payload.actual_end_date = form.actual_end_date || null
  if (includeStatus) payload.status = Number(form.status)

  return payload
}

export function getStatusChangeOptions(currentStatus: number) {
  if (Number(currentStatus) === STATUS_PENDING_SIGN) {
    return [
      { value: STATUS_ACTIVE, label: 'Kích hoạt (Đang hiệu lực)', tone: 'success' as const },
      { value: STATUS_CANCELLED, label: 'Hủy hợp đồng', tone: 'danger' as const },
    ]
  }

  return []
}
