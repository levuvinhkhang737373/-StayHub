import { apiRequest } from '../../../../shared/lib/api/api-client'
import type { AdminAssetTemplatePayload, AdminAssetTemplateResource, AdminPaginator } from '../types/asset-template-api.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchAdminAssetTemplates(
  params: {
    keyword?: string
    building_id?: number
    only_global?: boolean
    default_unit_name?: number
    status?: number
    per_page?: number
    page?: number
  } = {},
) {
  return apiRequest<AdminPaginator<AdminAssetTemplateResource>>({
    url: `admin/asset-templates${buildQuery({ per_page: 10, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminAssetTemplateDetail(assetTemplateId: number) {
  return apiRequest<AdminAssetTemplateResource>({
    url: `admin/asset-templates/${assetTemplateId}`,
    method: 'GET',
  })
}

export async function createAdminAssetTemplate(payload: AdminAssetTemplatePayload) {
  return apiRequest<AdminAssetTemplateResource>({
    url: 'admin/asset-templates',
    method: 'POST',
    data: payload,
  })
}

export async function updateAdminAssetTemplate(assetTemplateId: number, payload: AdminAssetTemplatePayload) {
  return apiRequest<AdminAssetTemplateResource>({
    url: `admin/asset-templates/${assetTemplateId}`,
    method: 'PUT',
    data: payload,
  })
}

export async function updateAdminAssetTemplateStatus(assetTemplateId: number, status: number) {
  return apiRequest<AdminAssetTemplateResource>({
    url: `admin/asset-templates/${assetTemplateId}/status`,
    method: 'PATCH',
    data: { status },
  })
}

export async function deleteAdminAssetTemplate(assetTemplateId: number) {
  return apiRequest<null>({
    url: `admin/asset-templates/${assetTemplateId}`,
    method: 'DELETE',
  })
}
