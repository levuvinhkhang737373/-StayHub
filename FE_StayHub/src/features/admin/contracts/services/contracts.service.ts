import { apiRequest } from '../../../../shared/lib/api/api-client'
import type {
  AdminContractAddTenantPayload,
  AdminContractFilters,
  AdminContractPayload,
  AdminContractResource,
  AdminContractStatusPayload,
  AdminContractTenantOptionResource,
  AdminContractTerminatePayload,
  AdminContractTerminationResult,
  AdminPaginator,
  AdminVehicleOptionResource,
} from '../types/contract-api.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

function normalizeFormValue(value: string | number | boolean) {
  if (typeof value === 'boolean') return value ? '1' : '0'
  return String(value)
}

function appendObjectArray(formData: FormData, key: string, values: Array<Record<string, unknown>>) {
  values.forEach((item, index) => {
    Object.entries(item).forEach(([itemKey, itemValue]) => {
      if (itemValue === undefined || itemValue === null || itemValue === '') return
      if (!['string', 'number', 'boolean'].includes(typeof itemValue)) return
      formData.append(`${key}[${index}][${itemKey}]`, normalizeFormValue(itemValue as string | number | boolean))
    })
  })
}

function toContractFormData(payload: AdminContractPayload, method?: 'PUT') {
  const formData = new FormData()

  if (method) {
    formData.append('_method', method)
  }

  Object.entries(payload).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return

    if (key === 'contract_files' && Array.isArray(value)) {
      value.forEach((file) => formData.append('contract_files[]', file))
      return
    }

    if (key === 'delete_contract_files' && Array.isArray(value)) {
      value.forEach((path) => formData.append('delete_contract_files[]', String(path)))
      return
    }

    if (['tenants', 'vehicles', 'deposit_transactions'].includes(key) && Array.isArray(value)) {
      appendObjectArray(formData, key, value as Array<Record<string, unknown>>)
      return
    }

    if (!['string', 'number', 'boolean'].includes(typeof value)) return
    formData.append(key, normalizeFormValue(value as string | number | boolean))
  })

  return formData
}

function requiresFormData(payload: AdminContractPayload) {
  return Boolean(payload.contract_files?.length)
}

export async function fetchAdminContracts(params: AdminContractFilters = {}) {
  return apiRequest<AdminPaginator<AdminContractResource>>({
    url: `admin/contracts${buildQuery({ per_page: 10, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminContractDetail(contractId: number) {
  return apiRequest<AdminContractResource>({
    url: `admin/contracts/${contractId}`,
    method: 'GET',
  })
}

export async function fetchAdminContractVehicles(params: { tenant_id?: number; is_active?: boolean; without_active_contract?: boolean; per_page?: number } = {}) {
  return apiRequest<AdminPaginator<AdminVehicleOptionResource>>({
    url: `admin/vehicles${buildQuery({ per_page: 100, ...params })}`,
    method: 'GET',
  })
}

export async function fetchContractAvailableTenants(contractId: number, params: { keyword?: string; page?: number; per_page?: number } = {}) {
  return apiRequest<AdminPaginator<AdminContractTenantOptionResource>>({
    url: `admin/contracts/${contractId}/available-tenants${buildQuery({ per_page: 20, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAvailableRooms(params: { building_id: number }) {
  return apiRequest<Array<{ id: number; building_id: number; room_number?: string | null; status?: number | null; base_price?: string | number | null; max_occupants?: number | null; current_occupants?: number | null }>>({
    url: `admin/contracts/available-rooms${buildQuery(params)}`,
    method: 'GET',
  })
}

export async function createAdminContract(payload: AdminContractPayload) {
  return apiRequest<AdminContractResource>({
    url: 'admin/contracts',
    method: 'POST',
    data: toContractFormData(payload),
  })
}

export async function renewContract(contractId: number, payload: AdminContractPayload) {
  return apiRequest<AdminContractResource>({
    url: `admin/contracts/${contractId}/renew`,
    method: 'POST',
    data: toContractFormData(payload),
  })
}

export async function updateAdminContract(contractId: number, payload: AdminContractPayload) {
  if (requiresFormData(payload)) {
    return apiRequest<AdminContractResource>({
      url: `admin/contracts/${contractId}`,
      method: 'POST',
      data: toContractFormData(payload, 'PUT'),
    })
  }

  return apiRequest<AdminContractResource>({
    url: `admin/contracts/${contractId}`,
    method: 'PUT',
    data: payload,
  })
}

export async function updateAdminContractStatus(contractId: number, payload: AdminContractStatusPayload) {
  return apiRequest<AdminContractResource>({
    url: `admin/contracts/${contractId}/status`,
    method: 'PATCH',
    data: payload,
  })
}

export async function terminateAdminContract(contractId: number, payload: AdminContractTerminatePayload) {
  return apiRequest<AdminContractTerminationResult>({
    url: `admin/contracts/${contractId}/terminate`,
    method: 'POST',
    data: payload,
  })
}

export async function addTenantToContract(contractId: number, payload: AdminContractAddTenantPayload) {
  return apiRequest<AdminContractResource>({
    url: `admin/contracts/${contractId}/tenants`,
    method: 'POST',
    data: payload,
  })
}

export async function deleteAdminContract(contractId: number) {
  return apiRequest<null>({
    url: `admin/contracts/${contractId}`,
    method: 'DELETE',
  })
}

export async function createAdminContractDepositTransaction(
  contractId: number,
  payload: {
    transaction_type: number
    amount: string
    transaction_date: string
    payment_method: number
    note?: string | null
  }
) {
  return apiRequest<AdminContractResource>({
    url: `admin/contracts/${contractId}/deposit-transactions`,
    method: 'POST',
    data: payload,
  })
}

export async function createAdminVehicle(payload: {
  tenant_id: number
  vehicle_type: number
  license_plate: string | null
  brand?: string
  color?: string
}) {
  return apiRequest<AdminVehicleOptionResource>({
    url: 'admin/vehicles',
    method: 'POST',
    data: payload,
  })
}
