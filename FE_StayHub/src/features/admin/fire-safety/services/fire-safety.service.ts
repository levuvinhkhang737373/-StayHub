import { apiRequest } from '../../../../shared/lib/api/api-client'
import type {
  AdminPaginator,
  FireSafetyAlertFilters,
  FireSafetyAlertResource,
  FireSafetyAnalysisResult,
  FireSafetyStreamTestResult,
  SecurityCameraMonitoringResult,
  SecurityCameraFilters,
  SecurityCameraPayload,
  SecurityCameraResource,
} from '../types/fire-safety-api.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchSecurityCameras(params: SecurityCameraFilters = {}) {
  return apiRequest<AdminPaginator<SecurityCameraResource>>({
    url: `admin/security-cameras${buildQuery({ per_page: 20, ...params })}`,
    method: 'GET',
  })
}

export async function createSecurityCamera(payload: SecurityCameraPayload) {
  return apiRequest<SecurityCameraResource>({
    url: 'admin/security-cameras',
    method: 'POST',
    data: payload,
  })
}

export async function updateSecurityCamera(cameraId: number, payload: SecurityCameraPayload) {
  return apiRequest<SecurityCameraResource>({
    url: `admin/security-cameras/${cameraId}`,
    method: 'PUT',
    data: payload,
  })
}

export async function deleteSecurityCamera(cameraId: number) {
  return apiRequest<null>({
    url: `admin/security-cameras/${cameraId}`,
    method: 'DELETE',
  })
}

export async function updateSecurityCameraMonitoring(cameraId: number, enabled: boolean) {
  return apiRequest<SecurityCameraResource>({
    url: `admin/security-cameras/${cameraId}/monitoring`,
    method: 'PATCH',
    data: { enabled },
  })
}

export async function bulkUpdateSecurityCameraMonitoring(params: Pick<SecurityCameraFilters, 'building_id' | 'keyword'>, enabled: boolean) {
  return apiRequest<SecurityCameraMonitoringResult>({
    url: 'admin/security-cameras/monitoring/bulk',
    method: 'PATCH',
    data: { ...params, enabled },
  })
}

export async function analyzeSecurityCamera(cameraId: number) {
  return apiRequest<FireSafetyAnalysisResult>({
    url: `admin/security-cameras/${cameraId}/analyze`,
    method: 'POST',
  })
}

export async function testSecurityCameraStream(cameraId: number) {
  return apiRequest<FireSafetyStreamTestResult>({
    url: `admin/security-cameras/${cameraId}/test-stream`,
    method: 'POST',
  })
}

export async function fetchFireSafetyAlerts(params: FireSafetyAlertFilters = {}) {
  return apiRequest<AdminPaginator<FireSafetyAlertResource>>({
    url: `admin/fire-safety-alerts${buildQuery({ per_page: 20, ...params })}`,
    method: 'GET',
  })
}

export async function fetchFireSafetyAlertDetail(alertId: number) {
  return apiRequest<FireSafetyAlertResource>({
    url: `admin/fire-safety-alerts/${alertId}`,
    method: 'GET',
  })
}

export async function acknowledgeFireSafetyAlert(alertId: number) {
  return apiRequest<FireSafetyAlertResource>({
    url: `admin/fire-safety-alerts/${alertId}/acknowledge`,
    method: 'PATCH',
  })
}

export async function resolveFireSafetyAlert(alertId: number) {
  return apiRequest<FireSafetyAlertResource>({
    url: `admin/fire-safety-alerts/${alertId}/resolve`,
    method: 'PATCH',
  })
}

export async function markFalseFireSafetyAlert(alertId: number) {
  return apiRequest<FireSafetyAlertResource>({
    url: `admin/fire-safety-alerts/${alertId}/false-alarm`,
    method: 'PATCH',
  })
}
