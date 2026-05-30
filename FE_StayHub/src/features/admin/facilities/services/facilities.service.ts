import { apiRequest } from '../../../../shared/lib/api/api-client'
import type {
  AdminBuildingDetailResource,
  AdminBuildingPayload,
  AdminBuildingResource,
  AdminManagerResource,
  AdminRegionPayload,
  AdminRegionResource,
} from '../types/facility-api.model'

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

function appendArray(formData: FormData, key: string, values: Array<string | number | boolean>) {
  values.forEach((value) => formData.append(`${key}[]`, normalizeFormValue(value)))
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

function buildBuildingFormData(payload: AdminBuildingPayload) {
  const formData = new FormData()
  const nestedObjectKeys = new Set(['image_metadata', 'room_types', 'asset_templates', 'service_prices', 'settings'])
  const idArrayKeys = new Set(['delete_image_ids', 'room_type_ids', 'delete_room_type_ids', 'asset_template_ids', 'delete_asset_template_ids', 'delete_service_price_ids', 'setting_ids', 'delete_setting_ids'])

  Object.entries(payload).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return

    if (key === 'images' && Array.isArray(value)) {
      value.forEach((file) => formData.append('images[]', file))
      return
    }

    if (nestedObjectKeys.has(key) && Array.isArray(value)) {
      appendObjectArray(formData, key, value as Array<Record<string, unknown>>)
      return
    }

    if (idArrayKeys.has(key) && Array.isArray(value)) {
      appendArray(formData, key, value)
      return
    }

    if (!['string', 'number', 'boolean'].includes(typeof value)) return
    formData.append(key, normalizeFormValue(value as string | number | boolean))
  })

  return formData
}

export async function fetchAdminRegions(params: { keyword?: string; status?: boolean; per_page?: number } = {}) {
  return apiRequest<AdminRegionResource[]>({
    url: `admin/regions${buildQuery({ per_page: 100, ...params })}`,
    method: 'GET',
  })
}

export async function createAdminRegion(payload: AdminRegionPayload) {
  return apiRequest<AdminRegionResource>({
    url: 'admin/regions',
    method: 'POST',
    data: payload,
  })
}

export async function fetchAdminRegionDetail(regionId: number) {
  return apiRequest<AdminRegionResource>({
    url: `admin/regions/${regionId}`,
    method: 'GET',
  })
}

export async function updateAdminRegion(regionId: number, payload: AdminRegionPayload) {
  return apiRequest<AdminRegionResource>({
    url: `admin/regions/${regionId}`,
    method: 'PUT',
    data: payload,
  })
}

export async function updateAdminRegionStatus(regionId: number, status: boolean) {
  return apiRequest<AdminRegionResource>({
    url: `admin/regions/${regionId}/status`,
    method: 'PATCH',
    data: { status },
  })
}

export async function deleteAdminRegion(regionId: number) {
  return apiRequest<null>({
    url: `admin/regions/${regionId}`,
    method: 'DELETE',
  })
}

export async function fetchAdminBuildings(
  params: {
    keyword?: string
    status?: number
    region_id?: number
    manager_admin_id?: number
    gender_policy?: number
    per_page?: number
  } = {},
) {
  return apiRequest<AdminBuildingResource[]>({
    url: `admin/buildings${buildQuery({ per_page: 100, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminBuildingDetail(buildingId: number) {
  return apiRequest<AdminBuildingDetailResource>({
    url: `admin/buildings/${buildingId}`,
    method: 'GET',
  })
}

export async function deleteAdminBuilding(buildingId: number) {
  return apiRequest<null>({
    url: `admin/buildings/${buildingId}`,
    method: 'DELETE',
  })
}

export async function fetchAdminManagers() {
  return apiRequest<{ data: AdminManagerResource[] }>({
    url: 'admin/accounts?role=1&status=1&per_page=100',
    method: 'GET',
  })
}

export async function createAdminBuilding(payload: AdminBuildingPayload) {
  return apiRequest<AdminBuildingDetailResource>({
    url: 'admin/buildings',
    method: 'POST',
    data: buildBuildingFormData(payload),
  })
}

export async function updateAdminBuilding(buildingId: number, payload: AdminBuildingPayload) {
  const formData = buildBuildingFormData(payload)
  formData.append('_method', 'PUT')

  return apiRequest<AdminBuildingDetailResource>({
    url: `admin/buildings/${buildingId}`,
    method: 'POST',
    data: formData,
  })
}

export async function updateAdminBuildingStatus(buildingId: number, status: number) {
  return apiRequest<AdminBuildingDetailResource>({
    url: `admin/buildings/${buildingId}/status`,
    method: 'PATCH',
    data: { status },
  })
}
