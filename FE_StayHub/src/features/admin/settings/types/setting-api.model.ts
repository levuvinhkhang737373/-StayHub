import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'

export interface AdminSettingResource {
  id: number
  building_id?: number | null
  building_name?: string | null
  building?: AdminBuildingResource | null
  setting_label: string
  setting_name: string
  setting_value?: string | null
  description?: string | null
  is_public: boolean
  is_public_label?: string | null
  created_by?: number | null
  creator_name?: string | null
  creator?: {
    id: number
    full_name?: string | null
  } | null
  created_at?: string | null
  updated_at?: string | null
}

export interface AdminSettingPayload {
  building_id?: number | null
  setting_label: string
  setting_name: string
  setting_value?: string
  description?: string
  is_public?: boolean
}
