export interface AdminPaginationMeta {
  current_page?: number
  per_page?: number
  total?: number
  last_page?: number
}

export interface AdminPaginator<T> {
  data: T[]
  pagination?: AdminPaginationMeta | null
}

export interface SecurityCameraResource {
  id: number
  building_id: number
  building_name?: string | null
  manager_admin_id?: number | null
  manager_name?: string | null
  name: string
  location?: string | null
  source_type: number
  source_type_label?: string | null
  stream_url?: string | null
  username?: string | null
  has_password?: boolean
  is_ai_enabled: boolean
  is_monitoring_active?: boolean
  frame_interval_seconds: number
  frames_per_batch: number
  alert_cooldown_seconds: number
  status: number
  status_label?: string | null
  monitoring_started_at?: string | null
  monitoring_stopped_at?: string | null
  last_scanned_at?: string | null
  next_scan_at?: string | null
  last_scan_status?: 'safe' | 'alert' | 'error' | string | null
  last_scan_message?: string | null
  monitoring_error_count?: number
  alerts_count?: number
  latest_alert?: FireSafetyAlertResource | null
  created_at?: string | null
  updated_at?: string | null
}

export interface SecurityCameraPayload {
  building_id: number
  name: string
  location?: string
  source_type: number
  stream_url: string
  username?: string
  password?: string
  is_ai_enabled?: boolean
  frame_interval_seconds?: number
  frames_per_batch?: number
  alert_cooldown_seconds?: number
  status?: number
}

export interface FireSafetyAlertResource {
  id: number
  security_camera_id?: number | null
  camera_name?: string | null
  camera_location?: string | null
  building_id: number
  building_name?: string | null
  source_label?: string | null
  risk_level: number
  risk_level_label?: string | null
  detected_fire: boolean
  detected_smoke: boolean
  detected_smoking: boolean
  confidence: number
  snapshot_path?: string | null
  snapshot_url?: string | null
  ai_summary?: string | null
  raw_ai_payload?: Record<string, unknown> | null
  status: number
  status_label?: string | null
  acknowledged_at?: string | null
  resolved_at?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export interface FireSafetyAnalysisResult {
  camera: SecurityCameraResource
  analysis: {
    success?: boolean
    risk_level?: string
    risk_level_code?: number
    detected_fire?: boolean
    detected_smoke?: boolean
    detected_smoking?: boolean
    confidence?: number
    summary?: string
    recommended_action?: string
    evidence?: string[]
    frame_count?: number
    model?: string
  }
  alert?: FireSafetyAlertResource | null
}

export interface FireSafetyStreamTestResult {
  camera: SecurityCameraResource
  stream: {
    success?: boolean
    message?: string
    width?: number
    height?: number
    resolved_stream_url?: string
    snapshot_base64?: string
  }
}

export interface SecurityCameraMonitoringResult {
  updated_count: number
  skipped_count: number
}

export interface SecurityCameraFilters {
  building_id?: number
  source_type?: number
  status?: number
  is_ai_enabled?: boolean
  keyword?: string
  page?: number
  per_page?: number
}

export interface FireSafetyAlertFilters {
  building_id?: number
  security_camera_id?: number
  risk_level?: number
  status?: number
  page?: number
  per_page?: number
}
