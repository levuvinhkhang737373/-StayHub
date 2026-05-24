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

function appendArray(formData: FormData, key: string, values: Array<string | number | boolean>) {
  values.forEach((value) => formData.append(`${key}[]`, String(value)))
}

function buildBuildingFormData(payload: AdminBuildingPayload) {
  const formData = new FormData()

  Object.entries(payload).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return

    if (key === 'images' && Array.isArray(value)) {
      value.forEach((file) => formData.append('images[]', file))
      return
    }

    if (key === 'image_metadata' && Array.isArray(value)) {
      value.forEach((metadata, index) => {
        Object.entries(metadata).forEach(([metadataKey, metadataValue]) => {
          if (metadataValue === undefined || metadataValue === null || metadataValue === '') return
          formData.append(`image_metadata[${index}][${metadataKey}]`, String(metadataValue))
        })
      })
      return
    }

    if (key === 'delete_image_ids' && Array.isArray(value)) {
      appendArray(formData, 'delete_image_ids', value)
      return
    }

    formData.append(key, String(value))
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
  return apiRequest<AdminManagerResource[]>({
    url: 'admin/admins?role=1&status=1&all=1',
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
