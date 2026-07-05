export interface AdminNotificationResource {
  id: number
  title: string
  content: string
  action_url?: string | null
  notification_type: number
  notification_type_label?: string | null
  target_type: number
  target_type_label?: string | null
  building_id: number | null
  building_name?: string | null
  room_id: number | null
  room_number?: string | null
  tenant_id: number | null
  tenant_name?: string | null
  target_admin_id?: number | null
  published_at: string | null
  status: number
  status_label?: string | null
  created_by: number | null
  creator_name?: string | null
  created_at: string
  updated_at: string
}

export interface AdminNotificationFilters {
  status?: number
  target_type?: number
  building_id?: number
  page?: number
  per_page?: number
}

export interface AdminNotificationPayload {
  title: string
  content: string
  notification_type: number
  target_type: number
  building_id?: number | null
  room_id?: number | null
  tenant_id?: number | null
  status: number
}

export interface AdminNotificationPaginator {
  data: AdminNotificationResource[]
  pagination?: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}
