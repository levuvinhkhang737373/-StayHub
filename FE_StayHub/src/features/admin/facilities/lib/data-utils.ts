import type { AdminBuildingDetailResource, AdminBuildingResource } from '../types/facility-api.model'
import { mapApiStatusToUiStatus } from '../types/facility-api.model'
import type { Building } from '../types/building.model'

export function mapBuildingResourceToBuilding(building: AdminBuildingResource | AdminBuildingDetailResource): Building {
  const detail = building as AdminBuildingDetailResource
  const primaryImage = building.primary_image || detail.images?.find((image) => image.is_primary) || detail.images?.[0] || null

  return {
    id: building.id,
    name: building.name,
    slug: building.slug,
    address: building.address,
    region_id: building.region_id,
    region_name: building.region_name || detail.region?.name || null,
    manager_admin_id: building.manager_admin_id,
    status: mapApiStatusToUiStatus(building.status),
    status_value: Number(building.status),
    gender_policy: building.gender_policy === null || building.gender_policy === undefined ? 1 : Number(building.gender_policy),
    images: detail.images || [],
    room_types: detail.room_types || [],
    asset_templates: detail.asset_templates || [],
    service_prices: detail.service_prices || [],
    settings: detail.settings || [],
    image_urls: detail.images?.map((image) => image.image_url) || (primaryImage ? [primaryImage.image_url] : []),
    primary_image: primaryImage,
    total_floors: building.total_floors ?? null,
    description: detail.description || null,
    manager_name: building.manager_name || detail.manager?.full_name || null,
    manager_phone: detail.manager?.phone || null,
    created_at: building.created_at || null,
    updated_at: building.updated_at || null,
    images_count: building.images_count,
    rooms_count: building.rooms_count,
    room_types_count: building.room_types_count,
    asset_templates_count: building.asset_templates_count,
    service_prices_count: building.service_prices_count,
    settings_count: building.settings_count,
    notifications_count: building.notifications_count,
    expenses_count: building.expenses_count,
  }
}
