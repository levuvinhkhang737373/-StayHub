import type { Dispatch, FormEvent, ReactNode, SetStateAction } from 'react'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  AlertTriangle,
  Building2,
  Camera,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Flame,
  Loader2,
  Plus,
  RadioTower,
  RefreshCw,
  Search,
  ShieldAlert,
  Trash2,
  XCircle,
} from 'lucide-react'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { getVisibleErrorMessage, getVisibleFilterErrorMessage } from '../../shared/utils/error-message'
import { ImageViewerModal } from '../../../../shared/components/ImageViewerModal'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import {
  acknowledgeFireSafetyAlert,
  analyzeSecurityCamera,
  bulkUpdateSecurityCameraMonitoring,
  createSecurityCamera,
  deleteSecurityCamera,
  fetchFireSafetyAlertDetail,
  fetchFireSafetyAlerts,
  fetchSecurityCameras,
  markFalseFireSafetyAlert,
  resolveFireSafetyAlert,
  testSecurityCameraStream,
  updateSecurityCamera,
  updateSecurityCameraMonitoring,
} from '../services/fire-safety.service'
import type { AdminPaginationMeta, FireSafetyAlertResource, SecurityCameraPayload, SecurityCameraResource } from '../types/fire-safety-api.model'

const DEFAULT_FORM = {
  building_id: '',
  name: '',
  location: '',
  source_type: 2,
  stream_url: '',
  username: '',
  password: '',
  is_ai_enabled: false,
  frame_interval_seconds: 2,
  frames_per_batch: 3,
  alert_cooldown_seconds: 60,
  status: 1,
}

type CameraForm = typeof DEFAULT_FORM

type AlertAction = 'ack' | 'resolve' | 'false'
type ActivePanel = 'cameras' | 'alerts'

const perPageOptions = [
  { value: 5, label: '5 dòng', tone: 'default' as const },
  { value: 10, label: '10 dòng', tone: 'default' as const },
  { value: 20, label: '20 dòng', tone: 'default' as const },
  { value: 50, label: '50 dòng', tone: 'default' as const },
]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20 disabled:cursor-not-allowed disabled:bg-[#efe2cf]/55 disabled:text-[#8b5e34]'
const labelClass = 'mb-1.5 block text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/75'

function resourceList<T>(result: { data?: T[] } | T[] | null | undefined): T[] {
  if (!result) return []
  if (Array.isArray(result)) return result
  return result.data || []
}

function riskTone(riskLevel?: number) {
  if (!riskLevel || riskLevel <= 1) return 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]'
  if (riskLevel === 2) return 'border-[#f3c56b]/45 bg-[#f3c56b]/18 text-[#8a4f18]'
  if (riskLevel === 3) return 'border-orange-200 bg-orange-50 text-orange-700'
  return 'border-rose-200 bg-rose-50 text-rose-700'
}

function alertTypes(alert: FireSafetyAlertResource) {
  return [
    alert.detected_fire ? 'Lửa' : null,
    alert.detected_smoke ? 'Khói' : null,
    alert.detected_smoking ? 'Hút thuốc' : null,
  ].filter(Boolean).join(', ') || 'Không rõ'
}

function normalizeCameraUrl(value: string) {
  const cleaned = value.trim()
  if (!cleaned) return cleaned
  return /^(https?|rtsp):\/\//i.test(cleaned) ? cleaned : `http://${cleaned.replace(/^\/+/, '')}`
}

function inferSourceType(streamUrl: string) {
  const normalized = normalizeCameraUrl(streamUrl).toLowerCase()

  if (normalized.startsWith('rtsp://')) return 3
  if (/\.(jpe?g|png|webp)(\?.*)?$/i.test(normalized) || /\/(photo|snapshot|shot|image|jpg)(\?.*)?$/i.test(normalized)) return 1

  return 2
}

function toPayload(form: CameraForm): SecurityCameraPayload {
  const streamUrl = normalizeCameraUrl(form.stream_url)

  return {
    building_id: Number(form.building_id),
    name: form.name.trim(),
    location: form.location.trim() || undefined,
    source_type: inferSourceType(streamUrl),
    stream_url: streamUrl,
    username: form.username.trim() || undefined,
    password: form.password.trim() || undefined,
    is_ai_enabled: form.is_ai_enabled,
    frame_interval_seconds: Number(form.frame_interval_seconds),
    frames_per_batch: Number(form.frames_per_batch),
    alert_cooldown_seconds: Number(form.alert_cooldown_seconds),
    status: Number(form.status),
  }
}

export function FireSafetyScreen() {
  const { session } = useAdminSession()
  const [searchParams] = useSearchParams()
  const panelParam = searchParams.get('panel')
  const alertIdParam = searchParams.get('alert_id')
  const isSuperAdmin = isSuperAdminRole(session?.admin?.role)
  const managedBuildings = useMemo(() => session?.admin?.managed_buildings || [], [session?.admin?.managed_buildings])
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [cameras, setCameras] = useState<SecurityCameraResource[]>([])
  const [cameraPaginationMeta, setCameraPaginationMeta] = useState<AdminPaginationMeta | null>(null)
  const [cameraCurrentPage, setCameraCurrentPage] = useState(1)
  const [cameraPerPage, setCameraPerPage] = useState(5)
  const [alerts, setAlerts] = useState<FireSafetyAlertResource[]>([])
  const [alertPaginationMeta, setAlertPaginationMeta] = useState<AdminPaginationMeta | null>(null)
  const [alertCurrentPage, setAlertCurrentPage] = useState(1)
  const [alertPerPage, setAlertPerPage] = useState(5)
  const [activePanel, setActivePanel] = useState<ActivePanel>(panelParam === 'alerts' || alertIdParam ? 'alerts' : 'cameras')
  const [selectedBuildingId, setSelectedBuildingId] = useState(isSuperAdmin ? '' : managedBuildings?.[0]?.id ? String(managedBuildings[0].id) : '')
  const [keyword, setKeyword] = useState('')
  const [form, setForm] = useState<CameraForm>(DEFAULT_FORM)
  const [editingCameraId, setEditingCameraId] = useState<number | null>(null)
  const [isCameraModalOpen, setIsCameraModalOpen] = useState(false)
  const [hasCameraAuth, setHasCameraAuth] = useState(false)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [monitoringId, setMonitoringId] = useState<number | null>(null)
  const [isBulkMonitoring, setIsBulkMonitoring] = useState(false)
  const [analyzingId, setAnalyzingId] = useState<number | null>(null)
  const [testingId, setTestingId] = useState<number | null>(null)
  const [streamSnapshots, setStreamSnapshots] = useState<Record<number, string>>({})
  const [scanStatuses, setScanStatuses] = useState<Record<number, { status: 'ok' | 'safe' | 'error'; message: string; at: string }>>({})
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [viewingImageSrc, setViewingImageSrc] = useState<string | null>(null)

  const buildingOptions = useMemo(() => {
    if (isSuperAdmin) return buildings
    return managedBuildings as AdminBuildingResource[]
  }, [buildings, isSuperAdmin, managedBuildings])

  const metrics = useMemo(() => {
    const activeCameras = cameras.filter((camera) => Number(camera.status) === 1).length
    const monitoringEnabled = cameras.filter((camera) => camera.is_ai_enabled).length
    const openAlerts = alerts.filter((alert) => Number(alert.status) === 1).length
    const criticalAlerts = alerts.filter((alert) => Number(alert.risk_level) >= 4 && Number(alert.status) === 1).length
    return { activeCameras, monitoringEnabled, openAlerts, criticalAlerts }
  }, [alerts, cameras])

  const loadBuildings = useCallback(async () => {
    if (!isSuperAdmin) return
    try {
      const response = await fetchAdminBuildings({ per_page: 100 })
      setBuildings(resourceList(response.result))
    } catch {
      setBuildings([])
    }
  }, [isSuperAdmin])

  const loadData = useCallback(async () => {
    setIsLoading(true)
    setError(null)
    try {
      const buildingId = selectedBuildingId ? Number(selectedBuildingId) : undefined
      const [cameraResponse, alertResponse] = await Promise.all([
        fetchSecurityCameras({ building_id: buildingId, keyword: keyword.trim() || undefined, page: cameraCurrentPage, per_page: cameraPerPage }),
        fetchFireSafetyAlerts({ building_id: buildingId, page: alertCurrentPage, per_page: alertPerPage }),
      ])
      setCameras(cameraResponse.result?.data || [])
      setCameraPaginationMeta(cameraResponse.result?.pagination || null)
      setAlerts(alertResponse.result?.data || [])
      setAlertPaginationMeta(alertResponse.result?.pagination || null)

      const cameraLastPage = cameraResponse.result?.pagination?.last_page
      if (cameraLastPage && cameraCurrentPage > cameraLastPage) {
        setCameraCurrentPage(cameraLastPage)
      }

      const lastPage = alertResponse.result?.pagination?.last_page
      if (lastPage && alertCurrentPage > lastPage) {
        setAlertCurrentPage(lastPage)
      }
    } catch (err) {
      setError(getVisibleFilterErrorMessage(err, 'Không thể tải dữ liệu AI camera.', Boolean(selectedBuildingId || keyword.trim())))
    } finally {
      setIsLoading(false)
    }
  }, [alertCurrentPage, alertPerPage, cameraCurrentPage, cameraPerPage, keyword, selectedBuildingId])

  useEffect(() => {
    void Promise.resolve().then(loadBuildings)
  }, [loadBuildings])

  useEffect(() => {
    void Promise.resolve().then(loadData)
  }, [loadData])

  useEffect(() => {
    if (panelParam === 'alerts' || alertIdParam) {
      queueMicrotask(() => setActivePanel('alerts'))
    }
  }, [alertIdParam, panelParam])

  useEffect(() => {
    const alertId = Number(alertIdParam)
    if (!Number.isFinite(alertId) || alertId <= 0) return

    let isMounted = true

    async function openAlertSnapshot() {
      try {
        const response = await fetchFireSafetyAlertDetail(alertId)
        if (!isMounted) return
        const alert = response.result
        if (alert?.snapshot_url) {
          setViewingImageSrc(alert.snapshot_url)
        }
      } catch (err) {
        if (isMounted) {
          setError(getVisibleErrorMessage(err, 'Không thể tải chi tiết cảnh báo AI camera.'))
        }
      }
    }

    void openAlertSnapshot()

    return () => {
      isMounted = false
    }
  }, [alertIdParam])

  useEffect(() => {
    const refresh = () => loadData()
    window.addEventListener('fire-safety-refresh', refresh)
    window.addEventListener('notification-refresh', refresh)
    return () => {
      window.removeEventListener('fire-safety-refresh', refresh)
      window.removeEventListener('notification-refresh', refresh)
    }
  }, [loadData])

  const resetForm = () => {
    setEditingCameraId(null)
    setHasCameraAuth(false)
    setForm({ ...DEFAULT_FORM, building_id: buildingOptions[0]?.id ? String(buildingOptions[0].id) : '' })
  }

  const openCreateCameraModal = () => {
    resetForm()
    setIsCameraModalOpen(true)
  }

  const closeCameraModal = () => {
    resetForm()
    setIsCameraModalOpen(false)
  }

  const editCamera = (camera: SecurityCameraResource) => {
    setEditingCameraId(camera.id)
    setHasCameraAuth(Boolean(camera.username || camera.has_password))
    setForm({
      building_id: String(camera.building_id),
      name: camera.name || '',
      location: camera.location || '',
      source_type: Number(camera.source_type) || 2,
      stream_url: camera.stream_url || '',
      username: camera.username || '',
      password: '',
      is_ai_enabled: Boolean(camera.is_ai_enabled),
      frame_interval_seconds: Number(camera.frame_interval_seconds) || 2,
      frames_per_batch: Number(camera.frames_per_batch) || 3,
      alert_cooldown_seconds: Number(camera.alert_cooldown_seconds) || 60,
      status: Number(camera.status) || 1,
    })
    setIsCameraModalOpen(true)
  }

  const saveCamera = async (event: FormEvent) => {
    event.preventDefault()
    if (!isSuperAdmin) return
    setIsSaving(true)
    setError(null)
    setMessage(null)
    try {
      if (!form.building_id) throw new Error('Vui lòng chọn tòa nhà.')
      if (!form.name.trim()) throw new Error('Vui lòng nhập tên camera.')
      if (!form.stream_url.trim()) throw new Error('Vui lòng nhập URL camera từ iPhone hoặc camera thật.')
      const payload = toPayload(hasCameraAuth ? form : { ...form, username: '', password: '' })
      if (editingCameraId) {
        await updateSecurityCamera(editingCameraId, payload)
        setMessage('Đã cập nhật camera.')
      } else {
        await createSecurityCamera(payload)
        setMessage('Đã thêm camera mới.')
      }
      resetForm()
      setIsCameraModalOpen(false)
      await loadData()
    } catch (err) {
      setError(getVisibleErrorMessage(err, 'Không thể lưu camera.'))
    } finally {
      setIsSaving(false)
    }
  }

  const runAnalyze = async (cameraId: number) => {
    setAnalyzingId(cameraId)
    setError(null)
    setMessage(null)
    try {
      const response = await analyzeSecurityCamera(cameraId)
      const result = response.result
      const risk = result?.analysis?.risk_level || 'safe'
      setScanStatuses((current) => ({
        ...current,
        [cameraId]: {
          status: result?.alert ? 'ok' : 'safe',
          message: result?.alert ? `Đã gửi cảnh báo ${risk}` : `An toàn (${risk})`,
          at: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
        },
      }))
      setMessage(result?.alert ? `AI phát hiện nguy cơ ${risk} và đã gửi cảnh báo.` : `AI phân tích xong: ${risk}. Chưa tạo cảnh báo.`)
      await loadData()
    } catch (err) {
      const detail = err instanceof ApiError ? err.message : 'Không thể phân tích camera. Hãy kiểm tra iPhone/laptop cùng mạng và URL stream.'
      setScanStatuses((current) => ({
        ...current,
        [cameraId]: {
          status: 'error',
          message: detail,
          at: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
        },
      }))
      setError(detail)
    } finally {
      setAnalyzingId(null)
    }
  }

  const testStream = async (cameraId: number) => {
    setTestingId(cameraId)
    setError(null)
    setMessage(null)

    try {
      const response = await testSecurityCameraStream(cameraId)
      const stream = response.result?.stream
      if (stream?.snapshot_base64) {
        setStreamSnapshots((current) => ({ ...current, [cameraId]: stream.snapshot_base64 || '' }))
      }
      setMessage(`Camera lấy frame OK${stream?.width && stream?.height ? ` (${stream.width}x${stream.height})` : ''}${stream?.resolved_stream_url ? ` qua ${stream.resolved_stream_url}` : ''}. Có thể bấm Quét ngay.`)
    } catch (err) {
      const detail = err instanceof ApiError ? err.message : 'Không lấy được frame từ camera iPhone.'
      if (/basic auth|username|password|401|mật khẩu|xác thực/i.test(detail)) {
        setHasCameraAuth(true)
      }
      setError(detail)
    } finally {
      setTestingId(null)
    }
  }

  const removeCamera = async (cameraId: number) => {
    if (!confirm('Xóa camera này? Camera đã có cảnh báo sẽ không thể xóa.')) return
    setError(null)
    setMessage(null)
    try {
      await deleteSecurityCamera(cameraId)
      setMessage('Đã xóa camera.')
      await loadData()
    } catch (err) {
      setError(getVisibleErrorMessage(err, 'Không thể xóa camera.'))
    }
  }

  const toggleCameraMonitoring = async (camera: SecurityCameraResource, enabled: boolean) => {
    if (!isSuperAdmin) return
    if (enabled && Number(camera.status) !== 1) {
      setError('Camera tạm tắt, không thể bật giám sát 24/24.')
      return
    }

    setMonitoringId(camera.id)
    setError(null)
    setMessage(null)
    try {
      await updateSecurityCameraMonitoring(camera.id, enabled)
      setMessage(enabled ? 'Đã bật giám sát AI 24/24 cho camera.' : 'Đã tắt giám sát AI 24/24 cho camera.')
      await loadData()
    } catch (err) {
      setError(getVisibleErrorMessage(err, 'Không thể cập nhật giám sát 24/24.'))
    } finally {
      setMonitoringId(null)
    }
  }

  const bulkToggleMonitoring = async (enabled: boolean) => {
    if (!isSuperAdmin) return
    const scope = selectedBuildingId || keyword.trim() ? 'các camera khớp bộ lọc hiện tại' : 'tất cả camera'
    if (!confirm(`${enabled ? 'Bật' : 'Tắt'} giám sát AI 24/24 cho ${scope}?`)) return

    setIsBulkMonitoring(true)
    setError(null)
    setMessage(null)
    try {
      const response = await bulkUpdateSecurityCameraMonitoring({
        building_id: selectedBuildingId ? Number(selectedBuildingId) : undefined,
        keyword: keyword.trim() || undefined,
      }, enabled)
      const result = response.result
      setMessage(`${enabled ? 'Đã bật' : 'Đã tắt'} giám sát 24/24 cho ${result?.updated_count ?? 0} camera${result?.skipped_count ? `, bỏ qua ${result.skipped_count} camera tạm tắt` : ''}.`)
      await loadData()
    } catch (err) {
      setError(getVisibleErrorMessage(err, 'Không thể cập nhật giám sát hàng loạt.'))
    } finally {
      setIsBulkMonitoring(false)
    }
  }

  const updateAlertStatus = async (alertId: number, action: AlertAction) => {
    setError(null)
    setMessage(null)
    try {
      if (action === 'ack') await acknowledgeFireSafetyAlert(alertId)
      if (action === 'resolve') await resolveFireSafetyAlert(alertId)
      if (action === 'false') await markFalseFireSafetyAlert(alertId)
      setMessage('Đã cập nhật trạng thái cảnh báo.')
      await loadData()
    } catch (err) {
      setError(getVisibleErrorMessage(err, 'Không thể cập nhật cảnh báo.'))
    }
  }

  const hasActiveFilters = Boolean(selectedBuildingId || keyword.trim())

  const cameraSafeCurrentPage = Math.max(1, Math.min(cameraCurrentPage, cameraPaginationMeta?.last_page ?? cameraCurrentPage))
  const cameraTotalPages = Math.max(1, cameraPaginationMeta?.last_page ?? (cameras.length >= cameraPerPage ? cameraCurrentPage + 1 : cameraCurrentPage))
  const cameraPaginationStart = cameras.length === 0 ? 0 : (cameraSafeCurrentPage - 1) * cameraPerPage + 1
  const cameraPaginationEnd = cameras.length === 0 ? 0 : cameraPaginationStart + cameras.length - 1
  const cameraTotalItems = cameraPaginationMeta?.total ?? (cameraSafeCurrentPage - 1) * cameraPerPage + cameras.length

  const cameraVisiblePages = useMemo(() => {
    const pages = new Set<number>([1, cameraTotalPages, cameraSafeCurrentPage - 1, cameraSafeCurrentPage, cameraSafeCurrentPage + 1])
    return Array.from(pages)
      .filter((page) => page >= 1 && page <= cameraTotalPages)
      .sort((left, right) => left - right)
  }, [cameraSafeCurrentPage, cameraTotalPages])

  const changeCameraPage = (page: number) => {
    setCameraCurrentPage(Math.min(Math.max(1, page), cameraTotalPages))
  }

  const changeCameraPerPage = (nextValue: string | number) => {
    setCameraPerPage(Number(nextValue))
    setCameraCurrentPage(1)
  }

  const alertSafeCurrentPage = Math.max(1, Math.min(alertCurrentPage, alertPaginationMeta?.last_page ?? alertCurrentPage))
  const alertTotalPages = Math.max(1, alertPaginationMeta?.last_page ?? (alerts.length >= alertPerPage ? alertCurrentPage + 1 : alertCurrentPage))
  const alertPaginationStart = alerts.length === 0 ? 0 : (alertSafeCurrentPage - 1) * alertPerPage + 1
  const alertPaginationEnd = alerts.length === 0 ? 0 : alertPaginationStart + alerts.length - 1
  const alertTotalItems = alertPaginationMeta?.total ?? (alertSafeCurrentPage - 1) * alertPerPage + alerts.length

  const alertVisiblePages = useMemo(() => {
    const pages = new Set<number>([1, alertTotalPages, alertSafeCurrentPage - 1, alertSafeCurrentPage, alertSafeCurrentPage + 1])
    return Array.from(pages)
      .filter((page) => page >= 1 && page <= alertTotalPages)
      .sort((left, right) => left - right)
  }, [alertSafeCurrentPage, alertTotalPages])

  const changeAlertPage = (page: number) => {
    setAlertCurrentPage(Math.min(Math.max(1, page), alertTotalPages))
  }

  const changeAlertPerPage = (nextValue: string | number) => {
    setAlertPerPage(Number(nextValue))
    setAlertCurrentPage(1)
  }

  const clearFilters = () => {
    setSelectedBuildingId(isSuperAdmin ? '' : (buildingOptions[0]?.id ? String(buildingOptions[0].id) : ''))
    setKeyword('')
    setCameraCurrentPage(1)
    setAlertCurrentPage(1)
  }

  const formTitle = editingCameraId ? 'Cập nhật camera' : 'Thêm camera'

  return (
    <section className="space-y-5 text-[#24170d] sm:space-y-6">
      <div className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-5 text-[#fff4df] sm:p-6 lg:p-7">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_16%_18%,rgba(243,197,107,0.24),transparent_30%),radial-gradient(circle_at_85%_20%,rgba(15,118,110,0.22),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
          <div className="relative flex min-w-0 flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div className="min-w-0">
              <span className="block text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]/80">VẬN HÀNH</span>
              <h1 className="mt-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem] flex items-center gap-3">
                <Camera className="h-8 w-8 text-[#f3c56b] shrink-0" />
                AI Camera
              </h1>
            </div>
            <div className="flex shrink-0 flex-wrap gap-2">
              {isSuperAdmin && (
                <button type="button" onClick={openCreateCameraModal} className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98]">
                  <Plus className="h-4 w-4" /> Thêm camera
                </button>
              )}
              {isSuperAdmin && (
                <button type="button" onClick={() => bulkToggleMonitoring(true)} disabled={isBulkMonitoring} className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-[#0f766e]/25 bg-[#0f766e]/18 px-4 text-sm font-black text-[#d7fffb] transition hover:bg-[#0f766e]/25 focus:outline-none focus:ring-4 focus:ring-[#0f766e]/15 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-55">
                  {isBulkMonitoring ? <Loader2 className="h-4 w-4 animate-spin" /> : <RadioTower className="h-4 w-4" />} Bật 24/24
                </button>
              )}
              {isSuperAdmin && (
                <button type="button" onClick={() => bulkToggleMonitoring(false)} disabled={isBulkMonitoring} className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-rose-200/25 bg-rose-500/16 px-4 text-sm font-black text-rose-50 transition hover:bg-rose-500/25 focus:outline-none focus:ring-4 focus:ring-rose-200/15 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-55">
                  Tắt 24/24
                </button>
              )}
              <button type="button" onClick={loadData} className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-[#fff4df]/15 bg-white/10 px-4 text-sm font-black text-[#fff4df] transition hover:bg-white/15 focus:outline-none focus:ring-4 focus:ring-white/10">
                <RefreshCw className={cn('h-4 w-4', isLoading && 'animate-spin')} /> Làm mới
              </button>
            </div>
          </div>

          <div className="relative mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <MetricCard icon={<Camera className="h-5 w-5" />} label="Camera hoạt động" value={metrics.activeCameras} tone="amber" />
            <MetricCard icon={<RadioTower className="h-5 w-5" />} label="Giám sát 24/24" value={metrics.monitoringEnabled} tone="teal" />
            <MetricCard icon={<AlertTriangle className="h-5 w-5" />} label="Cảnh báo mở" value={metrics.openAlerts} tone="orange" />
            <MetricCard icon={<Flame className="h-5 w-5" />} label="Báo động đỏ" value={metrics.criticalAlerts} tone="rose" />
          </div>
        </div>
      </div>

      {(error || message) && (
        <div className={cn('rounded-3xl border px-4 py-3 text-sm font-black shadow-sm', error ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700')}>
          {error || message}
        </div>
      )}

      <div className="space-y-5">
        <div className="flex flex-col gap-3 rounded-[1.75rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-2 shadow-lg shadow-[#6b3f1d]/8 sm:flex-row">
          <PanelButton active={activePanel === 'cameras'} onClick={() => setActivePanel('cameras')} icon={<Camera className="h-4 w-4" />} label="Danh sách camera" count={cameraTotalItems} />
          <PanelButton active={activePanel === 'alerts'} onClick={() => setActivePanel('alerts')} icon={<ShieldAlert className="h-4 w-4" />} label="Lịch sử cảnh báo" count={alertTotalItems} />
        </div>

        {activePanel === 'cameras' && (
          <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
            <div className="border-b border-[#3d2a18]/10 bg-[#fff8eb]/85 p-4 sm:p-5">
              <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                  <h2 className="text-lg font-black tracking-tight text-[#24170d]">Danh sách camera</h2>
                </div>
                <div className="grid gap-2 sm:grid-cols-[minmax(11rem,13rem)_minmax(12rem,1fr)_auto]">
                  <select value={selectedBuildingId} onChange={(event) => { setSelectedBuildingId(event.target.value); setCameraCurrentPage(1); setAlertCurrentPage(1) }} className={inputClass}>
                    {isSuperAdmin && <option value="">Tất cả tòa nhà</option>}
                    {buildingOptions.map((building) => <option key={building.id} value={building.id}>{building.name}</option>)}
                  </select>
                  <div className="relative">
                    <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8b5e34]/60" />
                    <input value={keyword} onChange={(event) => { setKeyword(event.target.value); setCameraCurrentPage(1); setAlertCurrentPage(1) }} className={`${inputClass} pl-11`} placeholder="Tìm camera/vị trí" />
                  </div>
                  <button type="button" onClick={clearFilters} disabled={!hasActiveFilters} className="inline-flex h-12 items-center justify-center rounded-2xl px-4 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] disabled:cursor-not-allowed disabled:opacity-45">
                    Xóa lọc
                  </button>
                </div>
              </div>
            </div>

            <div className="p-4 sm:p-5">
              {isLoading ? (
                <div className="rounded-3xl border border-[#3d2a18]/10 bg-white/60 p-8 text-center text-sm font-black text-[#8b5e34]">Đang tải camera...</div>
              ) : cameras.length === 0 ? (
                <div className="rounded-3xl border border-dashed border-[#3d2a18]/15 bg-white/50 p-8 text-center text-sm font-bold text-[#8b5e34]">
                  Chưa có camera nào.
                </div>
              ) : (
                <div className="overflow-hidden rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60">
                  <div className="hidden grid-cols-[1.15fr_1.2fr_0.95fr_1.1fr] gap-4 bg-[#24170d] px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8] lg:grid">
                    <span>Camera</span>
                    <span className="text-center">Tòa nhà</span>
                    <span>Thông số</span>
                    <span className="text-center">Thao tác</span>
                  </div>
                  <div className="divide-y divide-[#3d2a18]/8">
                    {cameras.map((camera) => (
                      <article key={camera.id} className="grid gap-4 bg-[#fffaf1]/75 p-4 transition hover:bg-[#f3c56b]/8 lg:grid-cols-[1.15fr_1.2fr_0.95fr_1.1fr] lg:items-center">
                        <div className="min-w-0">
                          <div className="flex flex-wrap items-center gap-2">
                            <h3 className="truncate text-base font-black text-[#24170d]">{camera.name}</h3>
                            <StatusBadge active={Number(camera.status) === 1} label={camera.status_label || (Number(camera.status) === 1 ? 'Đang hoạt động' : 'Tạm tắt')} />
                            <span className={cn('rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em]', camera.is_ai_enabled ? 'border-[#0f766e]/15 bg-[#0f766e]/10 text-[#0f5f59]' : 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]')}>
                              {camera.is_ai_enabled ? '24/24 bật' : 'Chưa giám sát'}
                            </span>
                          </div>
                          <p className="mt-1 text-sm font-bold text-[#6f6254]">{camera.location || 'Chưa nhập vị trí'}</p>
                          <p className="mt-1 truncate text-xs font-semibold text-[#8b5e34]/75">{camera.stream_url}</p>
                          {scanStatuses[camera.id] && (
                            <div className={cn('mt-2 rounded-2xl border px-3 py-2 text-xs font-black', scanStatuses[camera.id].status === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : scanStatuses[camera.id].status === 'ok' ? 'border-[#f3c56b]/45 bg-[#f3c56b]/18 text-[#8a4f18]' : 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]')}>
                              {scanStatuses[camera.id].message} • {scanStatuses[camera.id].at}
                            </div>
                          )}
                          {(camera.last_scan_message || camera.last_scanned_at || camera.next_scan_at) && (
                            <div className={cn('mt-2 rounded-2xl border px-3 py-2 text-xs font-bold', camera.last_scan_status === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : camera.last_scan_status === 'alert' ? 'border-[#f3c56b]/45 bg-[#f3c56b]/18 text-[#8a4f18]' : 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]')}>
                              <div className="font-black">{camera.last_scan_message || 'Đang chờ job giám sát 24/24.'}</div>
                              <div className="mt-1 opacity-80">Quét cuối: {camera.last_scanned_at || 'chưa có'} • Lượt kế: {camera.next_scan_at || 'chưa lên lịch'}</div>
                              {Boolean(camera.monitoring_error_count) && <div className="mt-1 text-rose-700">Lỗi liên tiếp: {camera.monitoring_error_count}</div>}
                            </div>
                          )}
                          {streamSnapshots[camera.id] && (
                            <div className="mt-3 overflow-hidden rounded-2xl border border-[#0f766e]/15 bg-[#0f766e]/8 sm:max-w-xs">
                              <img src={`data:image/jpeg;base64,${streamSnapshots[camera.id]}`} alt="Frame test camera" className="h-28 w-full object-cover" />
                              <div className="px-3 py-2 text-xs font-black text-[#0f5f59]">Frame test OK từ iPhone/camera</div>
                            </div>
                          )}
                        </div>

                        <div className="min-w-0 text-sm font-bold text-[#3d2a18] lg:text-center">
                          <div className="flex items-center gap-2 lg:justify-center"><Building2 className="h-4 w-4 text-[#a65f16]" /> {camera.building_name || `Tòa #${camera.building_id}`}</div>
                          <p className="mt-1 text-xs font-semibold text-[#8b5e34]">Manager: {camera.manager_name || 'Chưa gán'}</p>
                        </div>

                        <div className="space-y-1.5 text-xs font-bold text-[#6f6254]">
                          <div>Loại luồng: <span className="font-extrabold text-[#24170d]">{camera.source_type_label}</span></div>
                          <div>Tần suất quét: <span className="font-extrabold text-[#24170d]">{camera.frames_per_batch} khung hình / {camera.frame_interval_seconds}s</span></div>
                          <div>Thời gian chờ: <span className="font-extrabold text-[#24170d]">{camera.alert_cooldown_seconds}s</span></div>
                          <div>Số cảnh báo: <span className="font-extrabold text-[#24170d]">{camera.alerts_count || 0} lần</span></div>
                          {camera.latest_alert && (
                            <div className={cn('mt-2 inline-flex rounded-full border px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.18em]', riskTone(camera.latest_alert.risk_level))}>
                              Gần nhất: {camera.latest_alert.risk_level_label}
                            </div>
                          )}
                        </div>

                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                          {isSuperAdmin && (
                            <button
                              type="button"
                              onClick={() => toggleCameraMonitoring(camera, !camera.is_ai_enabled)}
                              disabled={monitoringId === camera.id || (Number(camera.status) !== 1 && !camera.is_ai_enabled)}
                              title={Number(camera.status) !== 1 ? 'Camera tạm tắt, không thể bật giám sát' : undefined}
                              className={cn(
                                'inline-flex h-10 items-center justify-center gap-2 rounded-xl border px-3 text-xs font-black transition focus:outline-none focus:ring-4 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60',
                                camera.is_ai_enabled ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 focus:ring-rose-100' : 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59] hover:bg-[#0f766e]/16 focus:ring-[#0f766e]/10',
                              )}
                            >
                              {monitoringId === camera.id ? <Loader2 className="h-4 w-4 animate-spin" /> : <RadioTower className="h-4 w-4" />} {camera.is_ai_enabled ? 'Tắt 24/24' : 'Bật 24/24'}
                            </button>
                          )}
                          <button type="button" onClick={() => testStream(camera.id)} disabled={testingId === camera.id || analyzingId === camera.id} className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#0f766e]/20 bg-[#0f766e]/10 px-3 text-xs font-black text-[#0f5f59] transition hover:bg-[#0f766e]/16 focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60">
                            {testingId === camera.id ? <Loader2 className="h-4 w-4 animate-spin" /> : <Camera className="h-4 w-4" />} Test cam
                          </button>
                          <button type="button" onClick={() => runAnalyze(camera.id)} disabled={analyzingId === camera.id} className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 text-xs font-black text-rose-700 transition hover:bg-rose-100 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60">
                            {analyzingId === camera.id ? <Loader2 className="h-4 w-4 animate-spin" /> : <ShieldAlert className="h-4 w-4" />} Quét ngay
                          </button>
                          {isSuperAdmin && <button type="button" onClick={() => editCamera(camera)} className="inline-flex h-10 items-center justify-center rounded-xl bg-[#f3c56b]/18 px-3 text-xs font-black text-[#8a4f18] transition hover:bg-[#f3c56b]/28 focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20 active:scale-95">Sửa</button>}
                          {isSuperAdmin && <button type="button" onClick={() => removeCamera(camera.id)} className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-3 text-xs font-black text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-95"><Trash2 className="h-4 w-4" /> Xóa</button>}
                        </div>
                      </article>
                    ))}
                  </div>
                </div>
              )}
            </div>
            <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb]/85 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
              <p className="text-xs font-black text-[#6f6254]">
                Hiển thị <span className="tabular-nums text-[#24170d]">{cameraPaginationStart}</span>-<span className="tabular-nums text-[#24170d]">{cameraPaginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{cameraTotalItems}</span> camera
              </p>
              <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="w-full sm:w-36">
                  <AdminSelect value={cameraPerPage} options={perPageOptions} onChange={changeCameraPerPage} menuPlacement="top" />
                </div>
                <div className="flex items-center justify-end gap-1.5">
                  <button type="button" disabled={cameraSafeCurrentPage <= 1} onClick={() => changeCameraPage(cameraSafeCurrentPage - 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang trước">
                    <ChevronLeft className="h-4 w-4" />
                  </button>
                  {cameraVisiblePages.map((page, index) => {
                    const previousPage = cameraVisiblePages[index - 1]
                    const hasGap = previousPage && page - previousPage > 1

                    return (
                      <div key={page} className="flex items-center gap-1.5">
                        {hasGap && <span className="px-1 text-xs font-black text-[#8b5e34]/60">...</span>}
                        <button type="button" onClick={() => changeCameraPage(page)} className={cn('inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition', page === cameraSafeCurrentPage ? 'border-[#24170d] bg-[#24170d] text-[#fff4df] shadow-sm' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15')} aria-current={page === cameraSafeCurrentPage ? 'page' : undefined}>
                          {page}
                        </button>
                      </div>
                    )
                  })}
                  <button type="button" disabled={cameraSafeCurrentPage >= cameraTotalPages} onClick={() => changeCameraPage(cameraSafeCurrentPage + 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang sau">
                    <ChevronRight className="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
          </section>
        )}

        {activePanel === 'alerts' && (
          <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
            <div className="border-b border-[#3d2a18]/10 bg-[#fff8eb]/85 p-4 sm:p-5">
              <h2 className="text-lg font-black tracking-tight text-[#24170d]">Lịch sử cảnh báo</h2>
            </div>
            <div className="space-y-3 p-4 sm:p-5">
              {alerts.length === 0 ? (
                <div className="rounded-3xl border border-dashed border-[#3d2a18]/15 bg-white/50 p-8 text-center text-sm font-bold text-[#8b5e34]">Chưa có cảnh báo AI camera.</div>
              ) : alerts.map((alert) => (
                <article key={alert.id} className="grid gap-4 rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4 md:grid-cols-[8rem_minmax(0,1fr)_auto]">
                  <div className="overflow-hidden rounded-2xl border border-[#3d2a18]/10 bg-[#efe2cf]/45">
                    {alert.snapshot_url ? (
                      <img
                        src={alert.snapshot_url}
                        alt="Snapshot cảnh báo"
                        className="h-28 w-full object-cover md:h-full cursor-pointer transition-opacity hover:opacity-90"
                        onClick={() => setViewingImageSrc(alert.snapshot_url ?? null)}
                      />
                    ) : (
                      <div className="flex h-28 items-center justify-center text-[#8b5e34]/45">
                        <Camera className="h-8 w-8" />
                      </div>
                    )}
                  </div>
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className={cn('rounded-full border px-2.5 py-1 text-xs font-black', riskTone(alert.risk_level))}>{alert.risk_level_label}</span>
                      <span className="rounded-full border border-[#3d2a18]/10 bg-[#fffaf1] px-2.5 py-1 text-xs font-black text-[#6f6254]">{alert.status_label}</span>
                      <span className="text-xs font-bold text-[#8b5e34]/70">{alert.created_at}</span>
                    </div>
                    <h3 className="mt-2 text-base font-black text-[#24170d]">{alert.building_name || `Tòa #${alert.building_id}`} • {alert.source_label || alert.camera_name || 'Camera'}</h3>
                    <p className="mt-1 text-sm font-semibold leading-6 text-[#6f6254]">{alert.ai_summary || 'AI phát hiện nguy cơ bất thường.'}</p>
                    <p className="mt-1 text-xs font-black uppercase tracking-[0.18em] text-rose-700">{alertTypes(alert)} • {Math.round((alert.confidence || 0) * 100)}%</p>
                  </div>
                  <div className="flex flex-col gap-2 md:min-w-36">
                    {Number(alert.status) === 1 && <AlertButton onClick={() => updateAlertStatus(alert.id, 'ack')}><CheckCircle2 className="h-4 w-4" /> Xác nhận</AlertButton>}
                    <AlertButton onClick={() => updateAlertStatus(alert.id, 'resolve')}><CheckCircle2 className="h-4 w-4" /> Đã xử lý</AlertButton>
                    <AlertButton danger onClick={() => updateAlertStatus(alert.id, 'false')}><XCircle className="h-4 w-4" /> Báo giả</AlertButton>
                  </div>
                </article>
              ))}
            </div>
            <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb]/85 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
              <p className="text-xs font-black text-[#6f6254]">
                Hiển thị <span className="tabular-nums text-[#24170d]">{alertPaginationStart}</span>-<span className="tabular-nums text-[#24170d]">{alertPaginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{alertTotalItems}</span> cảnh báo
              </p>
              <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="w-full sm:w-36">
                  <AdminSelect value={alertPerPage} options={perPageOptions} onChange={changeAlertPerPage} menuPlacement="top" />
                </div>
                <div className="flex items-center justify-end gap-1.5">
                  <button type="button" disabled={alertSafeCurrentPage <= 1} onClick={() => changeAlertPage(alertSafeCurrentPage - 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang trước">
                    <ChevronLeft className="h-4 w-4" />
                  </button>
                  {alertVisiblePages.map((page, index) => {
                    const previousPage = alertVisiblePages[index - 1]
                    const hasGap = previousPage && page - previousPage > 1

                    return (
                      <div key={page} className="flex items-center gap-1.5">
                        {hasGap && <span className="px-1 text-xs font-black text-[#8b5e34]/60">...</span>}
                        <button type="button" onClick={() => changeAlertPage(page)} className={cn('inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition', page === alertSafeCurrentPage ? 'border-[#24170d] bg-[#24170d] text-[#fff4df] shadow-sm' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15')} aria-current={page === alertSafeCurrentPage ? 'page' : undefined}>
                          {page}
                        </button>
                      </div>
                    )
                  })}
                  <button type="button" disabled={alertSafeCurrentPage >= alertTotalPages} onClick={() => changeAlertPage(alertSafeCurrentPage + 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang sau">
                    <ChevronRight className="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
          </section>
        )}
      </div>
      {isCameraModalOpen && (
        <CameraFormModal
          formTitle={formTitle}
          form={form}
          buildingOptions={buildingOptions}
          hasCameraAuth={hasCameraAuth}
          isSaving={isSaving}
          editingCameraId={editingCameraId}
          onClose={closeCameraModal}
          onSubmit={saveCamera}
          onFormChange={setForm}
          onAuthChange={setHasCameraAuth}
        />
      )}
      <ImageViewerModal
        isOpen={!!viewingImageSrc}
        src={viewingImageSrc}
        onClose={() => setViewingImageSrc(null)}
      />
    </section>
  )
}

function MetricCard({ icon, label, value, tone }: { icon: ReactNode; label: string; value: number; tone: 'amber' | 'teal' | 'orange' | 'rose' }) {
  const toneClassNames = {
    amber: 'border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#fff4df]',
    teal: 'border-[#0f766e]/35 bg-[#0f766e]/16 text-[#c8fff4]',
    orange: 'border-orange-200/30 bg-orange-100/12 text-orange-50',
    rose: 'border-rose-200/30 bg-rose-100/12 text-rose-50',
  }[tone]

  return (
    <div className={cn('flex h-full min-h-[6.75rem] min-w-0 flex-col rounded-[1.45rem] border p-4 shadow-lg shadow-black/5 backdrop-blur-sm transition duration-200 hover:-translate-y-0.5 hover:bg-white/12', toneClassNames)}>
      <div className="flex min-w-0 items-center gap-3">
        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-white/12 text-[#f3c56b]">{icon}</div>
        <p className="min-w-0 whitespace-nowrap text-[10px] font-black uppercase leading-none tracking-[0.14em] opacity-75">{label}</p>
      </div>
      <p className="mt-5 min-w-0 whitespace-nowrap text-[clamp(1.35rem,1.5vw,1.6rem)] font-black leading-none tracking-[-0.04em] tabular-nums">{value}</p>
    </div>
  )
}

function PanelButton({ active, icon, label, count, onClick }: { active: boolean; icon: ReactNode; label: string; count: number; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'flex min-h-14 flex-1 items-center justify-between gap-3 rounded-[1.35rem] border px-4 text-left transition focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20',
        active
          ? 'border-[#24170d] bg-[#24170d] text-[#fff4df] shadow-lg shadow-[#24170d]/15'
          : 'border-[#3d2a18]/10 bg-white/60 text-[#6f6254] hover:bg-[#f3c56b]/14 hover:text-[#24170d]',
      )}
    >
      <span className="flex items-center gap-3 text-sm font-black">
        <span className={cn('flex h-9 w-9 items-center justify-center rounded-2xl', active ? 'bg-[#f3c56b] text-[#24170d]' : 'bg-[#fff8eb] text-[#8b5e34]')}>{icon}</span>
        {label}
      </span>
      <span className={cn('rounded-full px-2.5 py-1 text-xs font-black tabular-nums', active ? 'bg-white/12 text-[#fff4df]' : 'bg-[#fff8eb] text-[#8b5e34]')}>{count}</span>
    </button>
  )
}

function CameraFormModal({
  formTitle,
  form,
  buildingOptions,
  hasCameraAuth,
  isSaving,
  editingCameraId,
  onClose,
  onSubmit,
  onFormChange,
  onAuthChange,
}: {
  formTitle: string
  form: CameraForm
  buildingOptions: AdminBuildingResource[]
  hasCameraAuth: boolean
  isSaving: boolean
  editingCameraId: number | null
  onClose: () => void
  onSubmit: (event: FormEvent) => void
  onFormChange: Dispatch<SetStateAction<CameraForm>>
  onAuthChange: Dispatch<SetStateAction<boolean>>
}) {
  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="camera-dialog-title">
      <button type="button" onClick={onClose} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" aria-label="Đóng popup" />
      <form onSubmit={onSubmit} className="relative max-h-[92vh] w-full max-w-4xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-stone-950/30">
        <div className="flex items-start justify-between gap-4 border-b border-[#3d2a18]/10 bg-[#fff8eb] p-5">
          <div>
            <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/70">AI Camera</p>
            <h2 id="camera-dialog-title" className="mt-1 text-xl font-black tracking-tight text-[#24170d]">{formTitle}</h2>
          </div>
          <button type="button" onClick={onClose} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-white text-[#8b5e34] transition hover:bg-rose-50 hover:text-rose-600">
            <XCircle className="h-5 w-5" />
          </button>
        </div>

        <div className="max-h-[calc(92vh-9rem)] space-y-4 overflow-y-auto p-5">
          <Field label="Tòa nhà">
            <select value={form.building_id} onChange={(event) => onFormChange((prev) => ({ ...prev, building_id: event.target.value }))} className={inputClass}>
              <option value="">Chọn tòa nhà</option>
              {buildingOptions.map((building) => <option key={building.id} value={building.id}>{building.name}</option>)}
            </select>
          </Field>

          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Tên camera">
              <input value={form.name} onChange={(event) => onFormChange((prev) => ({ ...prev, name: event.target.value }))} className={inputClass} />
            </Field>
            <Field label="Vị trí lắp">
              <input value={form.location} onChange={(event) => onFormChange((prev) => ({ ...prev, location: event.target.value }))} className={inputClass} />
            </Field>
          </div>

          <Field label="URL camera">
            <input value={form.stream_url} onChange={(event) => onFormChange((prev) => ({ ...prev, stream_url: event.target.value }))} className={inputClass} placeholder="http://192.168.1.5:8081" />
          </Field>

          <div className="rounded-2xl border border-[#3d2a18]/10 bg-white/55 p-3">
            <label className="flex items-start gap-3 text-sm font-black text-[#24170d]">
              <input
                type="checkbox"
                checked={hasCameraAuth}
                onChange={(event) => {
                  onAuthChange(event.target.checked)
                  if (!event.target.checked) onFormChange((prev) => ({ ...prev, username: '', password: '' }))
                }}
                className="mt-1 h-4 w-4 rounded border-[#3d2a18]/20 text-[#0f766e] focus:ring-[#0f766e]/20"
              />
              <span>Camera có mật khẩu</span>
            </label>

            {hasCameraAuth && (
              <div className="mt-3 grid gap-3 sm:grid-cols-2">
                <Field label="User">
                  <input value={form.username} onChange={(event) => onFormChange((prev) => ({ ...prev, username: event.target.value }))} className={inputClass} placeholder="Để trống nếu app không yêu cầu" />
                </Field>
                <Field label="Password">
                  <input type="password" value={form.password} onChange={(event) => onFormChange((prev) => ({ ...prev, password: event.target.value }))} className={inputClass} placeholder="Để trống nếu app không yêu cầu" />
                </Field>
              </div>
            )}
          </div>

          <div className="grid gap-3 sm:grid-cols-3">
            <Field label="Giây/lần">
              <input type="number" min={1} max={60} value={form.frame_interval_seconds} onChange={(event) => onFormChange((prev) => ({ ...prev, frame_interval_seconds: Number(event.target.value) }))} className={inputClass} />
            </Field>
            <Field label="Frame/lần">
              <input type="number" min={1} max={6} value={form.frames_per_batch} onChange={(event) => onFormChange((prev) => ({ ...prev, frames_per_batch: Number(event.target.value) }))} className={inputClass} />
            </Field>
            <Field label="Cooldown">
              <input type="number" min={10} max={3600} value={form.alert_cooldown_seconds} onChange={(event) => onFormChange((prev) => ({ ...prev, alert_cooldown_seconds: Number(event.target.value) }))} className={inputClass} />
            </Field>
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Giám sát 24/24">
              <select value={form.is_ai_enabled ? '1' : '0'} disabled={Number(form.status) !== 1} onChange={(event) => onFormChange((prev) => ({ ...prev, is_ai_enabled: event.target.value === '1' }))} className={inputClass}>
                <option value="0">Tắt giám sát</option>
                <option value="1">Bật giám sát 24/24</option>
              </select>
            </Field>
            <Field label="Trạng thái">
              <select value={form.status} onChange={(event) => onFormChange((prev) => ({ ...prev, status: Number(event.target.value), is_ai_enabled: Number(event.target.value) === 1 ? prev.is_ai_enabled : false }))} className={inputClass}>
                <option value={1}>Đang hoạt động</option>
                <option value={2}>Tạm tắt</option>
              </select>
            </Field>
          </div>
        </div>

        <div className="flex flex-col-reverse gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb] p-5 sm:flex-row sm:justify-end">
          <button type="button" onClick={onClose} className="inline-flex h-11 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-white px-5 text-sm font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/12">
            Hủy
          </button>
          <button type="submit" disabled={isSaving} className="inline-flex h-11 items-center justify-center gap-2 rounded-2xl bg-[#f3c56b] px-5 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition hover:bg-[#ffd56f] disabled:cursor-not-allowed disabled:opacity-60">
            {isSaving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />} {editingCameraId ? 'Lưu camera' : 'Thêm camera'}
          </button>
        </div>
      </form>
    </div>
  )
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return <label className="block"><span className={labelClass}>{label}</span>{children}</label>
}

function StatusBadge({ active, label }: { active: boolean; label: string }) {
  return (
    <span className={cn('rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em]', active ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]' : 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]')}>
      {label}
    </span>
  )
}

function AlertButton({ children, danger = false, onClick }: { children: ReactNode; danger?: boolean; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border px-3 text-xs font-black transition focus:outline-none focus:ring-4',
        danger
          ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 focus:ring-rose-100'
          : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#6f6254] hover:bg-[#f3c56b]/14 hover:text-[#24170d] focus:ring-[#f3c56b]/20',
      )}
    >
      {children}
    </button>
  )
}
