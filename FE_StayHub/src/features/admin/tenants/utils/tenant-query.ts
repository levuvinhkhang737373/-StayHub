import { isBuildingManagerRole } from '../../auth/hooks/use-admin-session'
import type { AdminTenantFilters } from '../types/tenant-api.model'

export interface TenantListQueryInput {
  role?: string | number | null
  managedBuildingId?: number | null
  keyword?: string
  status?: string
  gender?: string
  identityType?: string
  page: number
  perPage: number
}

export function buildTenantListQuery(input: TenantListQueryInput): AdminTenantFilters {
  const query: AdminTenantFilters = {
    keyword: input.keyword?.trim() || undefined,
    status: input.status === '' || input.status === undefined ? undefined : Number(input.status),
    gender: input.gender === '' || input.gender === undefined ? undefined : Number(input.gender),
    identity_type: input.identityType === '' || input.identityType === undefined ? undefined : Number(input.identityType),
    page: input.page,
    per_page: input.perPage,
  }

  if (isBuildingManagerRole(input.role) && input.managedBuildingId) {
    query.building_id = input.managedBuildingId
  }

  return query
}
