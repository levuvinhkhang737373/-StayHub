
export interface AdminMaintenanceLogResource {
  id: number
  maintenance_request_id: number
  old_status: number | null
  old_status_label: string | null
  new_status: number
  new_status_label: string
  note: string | null
  created_by: number | null
  creator_name: string | null
  created_at: string
}

export interface AdminMaintenanceFeedbackResource {
  id: number
  maintenance_request_id: number
  tenant_id: number
  rating: number
  images: string[]
  comment: string | null
  created_at: string
}

export interface AdminMaintenanceRequestResource {
  id: number
  request_code: string
  tenant_id: number
  tenant_name?: string | null
  tenant_phone?: string | null
  room_id: number
  room_number?: string | null
  building_id?: number | null
  building_name?: string | null
  title: string
  description: string
  status: number
  status_label?: string | null
  images: string[]
  assigned_to: number | null
  assignee_name?: string | null
  received_at?: string | null
  completed_at?: string | null
  logs?: AdminMaintenanceLogResource[]
  feedbacks?: AdminMaintenanceFeedbackResource[]
  feedbacks_count?: number
  created_at: string
  updated_at: string
}

export interface AdminMaintenanceFilters {
  keyword?: string
  status?: number
  building_id?: number
  room_number?: string
  page?: number
  per_page?: number
}

export interface AdminMaintenanceAssignPayload {
  assigned_to: number
}

export interface AdminMaintenanceStatusPayload {
  status: number
  note?: string
  after_image?: File | null
}

export interface AdminMaintenancePaginator {
  data: AdminMaintenanceRequestResource[]
  pagination?: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}
