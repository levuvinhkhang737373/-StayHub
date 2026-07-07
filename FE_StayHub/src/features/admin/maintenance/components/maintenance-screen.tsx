import { useCallback, useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  Wrench,
  Eye,
  Settings,
  RefreshCw,
  Search,
  X,
  CheckCircle2,
  Clock,
  AlertTriangle,
  User,
  Image as ImageIcon,
  Building
} from 'lucide-react'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatDateTime } from '../../../../shared/lib/utils/format'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { getVisibleErrorMessage, getVisibleFilterErrorMessage } from '../../shared/utils/error-message'
import { AdminPagination, type AdminPaginationMeta } from '../../shared/components/AdminPagination'
import {
  fetchAdminMaintenanceRequests,
  fetchAdminMaintenanceDetail,
  updateMaintenanceStatus
} from '../services/maintenance.service'
import type {
  AdminMaintenanceRequestResource
} from '../types/maintenance-api.model'

function getResourceList<T>(result: { data?: T[] } | T[] | null | undefined): T[] {
  if (!result) return []
  if (Array.isArray(result)) return result
  return result.data || []
}

const statusLabels: Record<number, string> = {
  1: 'Mới tạo',
  3: 'Đang xử lý',
  4: 'Đã hoàn thành',
  5: 'Đã hủy',
}

const statusOptions = [
  { value: '', label: 'Tất cả trạng thái', tone: 'default' as const },
  { value: '1', label: 'Mới tạo', tone: 'danger' as const },
  { value: '3', label: 'Đang xử lý', tone: 'warning' as const },
  { value: '4', label: 'Đã hoàn thành', tone: 'success' as const },
  { value: '5', label: 'Đã hủy', tone: 'default' as const },
]

const statusChangeOptions = [
  { value: 1, label: 'Mới tạo' },
  { value: 3, label: 'Đang xử lý' },
  { value: 4, label: 'Đã hoàn thành' },
  { value: 5, label: 'Đã hủy' },
]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65'

export function MaintenanceScreen() {
  const { session } = useAdminSession()
  const isSuperAdmin = isSuperAdminRole(session?.admin.role)

  const [searchParams] = useSearchParams()
  const maintenanceIdParam = searchParams.get('id')
  const requestCodeParam = searchParams.get('request_code')

  const [keyword, setKeyword] = useState(requestCodeParam || '')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [selectedBuildingId, setSelectedBuildingId] = useState('')
  const [roomNumber, setRoomNumber] = useState('')
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [requests, setRequests] = useState<AdminMaintenanceRequestResource[]>([])
  const [perPage, setPerPage] = useState(10)
  const [currentPage, setCurrentPage] = useState(1)
  const [paginationMeta, setPaginationMeta] = useState<AdminPaginationMeta | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)

  const [activeMessage, setActiveMessage] = useState<string | null>(null)
  const [activeType, setActiveType] = useState<'success' | 'error' | null>(null)

  useEffect(() => {
    if (successMessage) {
      const timer = setTimeout(() => {
        setSuccessMessage(null)
      }, 3000)
      return () => clearTimeout(timer)
    }
  }, [successMessage])

  useEffect(() => {
    if (errorMessage) {
      const timer = setTimeout(() => {
        setErrorMessage(null)
      }, 3000)
      return () => clearTimeout(timer)
    }
  }, [errorMessage])

  useEffect(() => {
    if (successMessage) {
      setActiveMessage(successMessage)
      setActiveType('success')
    } else if (errorMessage) {
      setActiveMessage(errorMessage)
      setActiveType('error')
    } else {
      const timer = setTimeout(() => {
        setActiveMessage(null)
        setActiveType(null)
      }, 500)
      return () => clearTimeout(timer)
    }
  }, [successMessage, errorMessage])

  // Modals state
  const [detailRequest, setDetailRequest] = useState<AdminMaintenanceRequestResource | null>(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isDetailLoading, setIsDetailLoading] = useState(false)


  const [updatingRequest, setUpdatingRequest] = useState<AdminMaintenanceRequestResource | null>(null)
  const [newStatus, setNewStatus] = useState<number>(1)
  const [updateNote, setUpdateNote] = useState<string>('')
  const [afterImageFile, setAfterImageFile] = useState<File | null>(null)
  const [isUpdatingStatus, setIsUpdatingStatus] = useState(false)

  const [lightboxImage, setLightboxImage] = useState<string | null>(null)

  const buildingOptions = useMemo(() => buildings.map((b) => ({ value: b.id, label: b.name, tone: 'default' as const })), [buildings])
  const filterBuildingOptions = useMemo(
    () => isSuperAdmin
      ? [{ value: '', label: 'Tất cả tòa nhà', tone: 'default' as const }, ...buildingOptions]
      : buildingOptions,
    [buildingOptions, isSuperAdmin]
  )

  // Metrics
  const metrics = useMemo(() => {
    const total = paginationMeta?.total ?? requests.length
    const created = requests.filter((r) => Number(r.status) === 1).length
    const processing = requests.filter((r) => Number(r.status) === 3).length
    const completed = requests.filter((r) => Number(r.status) === 4).length
    return { total, created, processing, completed }
  }, [paginationMeta?.total, requests])

  const loadBuildingsAndStaff = useCallback(async () => {
    try {
      const buildingsRes = await fetchAdminBuildings({ per_page: 100 })
      const list = getResourceList(buildingsRes.result)
      setBuildings(list)
      if (!isSuperAdmin && !selectedBuildingId && list[0]?.id) {
        setSelectedBuildingId(String(list[0].id))
      }
    } catch (e) {
      console.error('Không thể load tòa nhà', e)
    }
  }, [isSuperAdmin, selectedBuildingId])

  const loadRequests = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)
    try {
      const response = await fetchAdminMaintenanceRequests({
        keyword: keyword.trim() || undefined,
        status: selectedStatus ? Number(selectedStatus) : undefined,
        building_id: selectedBuildingId ? Number(selectedBuildingId) : undefined,
        room_number: roomNumber.trim() || undefined,
        per_page: perPage,
        page: currentPage,
      })

      // Parse requests from paginated result and coerce legacy status 2 to 3
      if (response.result && Array.isArray(response.result)) {
        const coerced = response.result.map(r => ({ ...r, status: Number(r.status) === 2 ? 3 : Number(r.status) }))
        setRequests(coerced)
        setPaginationMeta(null)
      } else if (response.result?.data) {
        const coerced = response.result.data.map(r => ({ ...r, status: Number(r.status) === 2 ? 3 : Number(r.status) }))
        setRequests(coerced)
        const meta = response.result.meta ?? response.result.pagination ?? null
        setPaginationMeta(meta)
        if (meta?.last_page && currentPage > meta.last_page) {
          setCurrentPage(meta.last_page)
        }
      } else {
        setRequests([])
        setPaginationMeta(null)
      }
    } catch (error) {
      setErrorMessage(getVisibleFilterErrorMessage(error, 'Không thể tải danh sách phiếu sửa chữa.', Boolean(keyword.trim() || roomNumber || selectedBuildingId || selectedStatus)))
    } finally {
      setIsLoading(false)
    }
  }, [currentPage, keyword, perPage, roomNumber, selectedBuildingId, selectedStatus])

  useEffect(() => {
    window.scrollTo(0, 0)
  }, [])

  useEffect(() => {
    void loadBuildingsAndStaff()
  }, [loadBuildingsAndStaff])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadRequests()
    }, 300)
    return () => window.clearTimeout(timer)
  }, [loadRequests])

  useEffect(() => {
    function handleMaintenanceCreated() {
      console.log('WS Event: Refreshing maintenance requests list')
      void loadRequests()
    }
    window.addEventListener('maintenance-created', handleMaintenanceCreated)
    return () => {
      window.removeEventListener('maintenance-created', handleMaintenanceCreated)
    }
  }, [loadRequests])

  useEffect(() => {
    if (maintenanceIdParam) {
      void openDetail({ id: Number(maintenanceIdParam) } as any)
    }
  }, [maintenanceIdParam])

  useEffect(() => {
    if (!isLoading && requestCodeParam && requests.length > 0) {
      const found = requests.find((r) => r.request_code === requestCodeParam)
      if (found) {
        void openDetail(found)
      }
    }
  }, [isLoading, requestCodeParam, requests])

  // View details
  const openDetail = async (req: AdminMaintenanceRequestResource) => {
    setIsDetailOpen(true)
    setIsDetailLoading(true)
    const coercedReq = { ...req, status: Number(req.status) === 2 ? 3 : Number(req.status) }
    setDetailRequest(coercedReq)
    try {
      const res = await fetchAdminMaintenanceDetail(req.id)
      if (res.result) {
        const coercedRes = { ...res.result, status: Number(res.result.status) === 2 ? 3 : Number(res.result.status) }
        setDetailRequest(coercedRes)
      }
    } catch (e) {
      console.error('Không thể load chi tiết phiếu', e)
    } finally {
      setIsDetailLoading(false)
    }
  }

  // // Assign staff


  // Update status
  const handleStatusSubmit = async () => {
    const req = updatingRequest
    if (!req) return
    if (newStatus === 4 && !afterImageFile) {
      setErrorMessage('Vui lòng tải lên ảnh minh chứng sau khi hoàn thành sửa chữa.')
      return
    }

    setIsUpdatingStatus(true)
    setErrorMessage(null)
    setSuccessMessage(null)
    try {
      await updateMaintenanceStatus(
        req.id,
        newStatus,
        updateNote.trim() || undefined,
        afterImageFile
      )
      setSuccessMessage(`Cập nhật trạng thái phiếu ${req.request_code} thành công.`)
      setUpdatingRequest(null)
      setUpdateNote('')
      setAfterImageFile(null)
      void loadRequests()
      if (detailRequest && detailRequest.id === req.id) {
        void openDetail(req)
      }
    } catch (error: any) {
      setErrorMessage(getVisibleErrorMessage(error, 'Có lỗi xảy ra khi cập nhật trạng thái.'))
    } finally {
      setIsUpdatingStatus(false)
    }
  }

  const clearFilters = () => {
    setKeyword('')
    setSelectedBuildingId(isSuperAdmin ? '' : (buildings[0]?.id ? String(buildings[0].id) : ''))
    setSelectedStatus('')
    setRoomNumber('')
    setCurrentPage(1)
  }

  const updateKeywordFilter = (value: string) => {
    setKeyword(value)
    setCurrentPage(1)
  }

  const updateRoomNumberFilter = (value: string) => {
    setRoomNumber(value)
    setCurrentPage(1)
  }

  const updateBuildingFilter = (value: string) => {
    setSelectedBuildingId(value)
    setCurrentPage(1)
  }

  const updateStatusFilter = (value: string) => {
    setSelectedStatus(value)
    setCurrentPage(1)
  }

  const changePerPage = (nextPerPage: number) => {
    setPerPage(nextPerPage)
    setCurrentPage(1)
  }

  return (
    <>
      <section className="space-y-5 sm:space-y-6 text-[#24170d]">
        {/* Header and Summary Panel */}
        <div className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
          <div className="relative p-5 text-[#fff4df] sm:p-6 lg:p-7">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(243,197,107,0.24),transparent_30%),radial-gradient(circle_at_82%_16%,rgba(15,118,110,0.22),transparent_32%),linear-gradient(135deg,#24170d_0%,#3d2a18_54%,#0f3f3b_100%)]" />
            <div className="relative flex min-w-0 flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
              <div className="min-w-0">
                <span className="block text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]/80">VẬN HÀNH</span>
                <h1 className="mt-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem] flex items-center gap-3">
                  <Wrench className="h-8 w-8 text-[#f3c56b] shrink-0" />
                  Quản lý bảo trì
                </h1>
              </div>
              <button
                type="button"
                onClick={() => void loadRequests()}
                className="inline-flex h-9 items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition-all hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98]"
              >
                <RefreshCw className="h-4 w-4 stroke-[2.8]" /> Làm mới
              </button>
            </div>

            {/* Metrics */}
            <div className="relative mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
              <MetricCard label="Tổng phiếu" value={metrics.total} tone="neutral" />
              <MetricCard label="Chờ tiếp nhận" value={metrics.created} tone="rose" />
              <MetricCard label="Đang sửa chữa" value={metrics.processing} tone="amber" />
              <MetricCard label="Đã hoàn thành" value={metrics.completed} tone="emerald" />
            </div>
          </div>
        </div>

        {/* Notifications and Alerts */}
        <div
          className={cn(
            'rounded-3xl border px-4 text-sm font-black shadow-sm transition-all duration-500 ease-in-out transform overflow-hidden',
            (successMessage || errorMessage)
              ? 'opacity-100 max-h-20 py-3 translate-y-0 scale-100'
              : 'opacity-0 max-h-0 py-0 -translate-y-2 scale-95 pointer-events-none border-transparent',
            (errorMessage || activeType === 'error')
              ? 'border-rose-200 bg-rose-50 text-rose-700'
              : 'border-emerald-200 bg-emerald-50 text-emerald-700'
          )}
        >
          {activeMessage || errorMessage || successMessage}
        </div>

        {/* Filters Panel */}
        <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/88 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
          <div className="border-b border-[#3d2a18]/10 bg-[#fff7e8]/72 p-4 sm:p-5">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
              <div className="relative min-w-0 flex-1">
                <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
                <input
                  type="text"
                  value={keyword}
                  onChange={(event) => updateKeywordFilter(event.target.value)}
                  placeholder="Tìm theo mã sự cố, tên phòng, mô tả hoặc tên khách..."
                  className={`${inputClass} pl-11 pr-28`}
                />
                <button
                  type="button"
                  onClick={clearFilters}
                  className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20"
                >
                  <X className="h-3.5 w-3.5" /> Xóa lọc
                </button>
              </div>
              <div className={cn(
                "grid w-full gap-3 lg:ml-auto",
                isSuperAdmin ? "sm:grid-cols-3 lg:w-2xl" : "sm:grid-cols-2 lg:w-xl"
              )}>
                <input
                  type="text"
                  value={roomNumber}
                  onChange={(e) => updateRoomNumberFilter(e.target.value)}
                  placeholder="Số phòng..."
                  className={inputClass}
                />
                {isSuperAdmin && (
                  <AdminSelect value={selectedBuildingId} options={filterBuildingOptions} onChange={(val) => updateBuildingFilter(String(val))} />
                )}
                <AdminSelect value={selectedStatus} options={statusOptions} onChange={(val) => updateStatusFilter(String(val))} />
              </div>
            </div>
          </div>

          {/* List of Requests */}
          <div className="p-4 sm:p-6">
            {isLoading ? (
              <div className="space-y-4">
                {Array.from({ length: 3 }).map((_, index) => (
                  <div key={index} className="h-36 animate-pulse rounded-3xl bg-stone-100" />
                ))}
              </div>
            ) : requests.length === 0 ? (
              <div className="py-20 text-center">
                <div className="mx-auto flex max-w-sm flex-col items-center">
                  <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]">
                    <Wrench className="h-9 w-9" />
                  </div>
                  <p className="text-lg font-black tracking-tight text-[#24170d]">Không tìm thấy phiếu yêu cầu</p>
                  <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">Chưa có yêu cầu nào khớp với bộ lọc của bạn.</p>
                </div>
              </div>
            ) : (
              <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                {requests.map((req) => (
                  <div
                    key={req.id}
                    className="group relative flex flex-col justify-between overflow-hidden rounded-[1.75rem] border border-[#3d2a18]/10 bg-white p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md hover:border-[#f3c56b]/40"
                  >
                    {/* Top Section */}
                    <div>
                      <div className="flex items-center justify-between gap-2 mb-3">
                        <span className="text-xs font-black text-[#a65f16] bg-[#f3c56b]/12 px-2.5 py-1 rounded-lg tabular-nums">
                          {req.request_code}
                        </span>
                        <StatusBadge status={req.status} label={req.status_label || statusLabels[req.status]} />
                      </div>

                      <h3 className="text-base font-black text-[#24170d] line-clamp-1 mb-1">{req.title}</h3>
                      <p className="text-xs text-[#6f6254] line-clamp-2 mb-4 font-medium">{req.description}</p>

                      <div className="space-y-1.5 border-t border-[#3d2a18]/5 pt-3 mb-4">
                        <div className="flex items-center gap-2 text-xs font-bold text-[#6f6254]">
                          <Building className="h-3.5 w-3.5 text-[#8b5e34]" />
                          <span>Phòng {req.room_number} — {req.building_name || 'Dùng chung'}</span>
                        </div>
                        <div className="flex items-center gap-2 text-xs font-bold text-[#6f6254]">
                          <User className="h-3.5 w-3.5 text-[#8b5e34]" />
                          <span>Khách: {req.tenant_name || 'Không rõ'}</span>
                        </div>
                        {req.assignee_name ? (
                          <div className="flex items-center gap-2 text-xs font-black text-[#0f766e]">
                            <CheckCircle2 className="h-3.5 w-3.5 text-[#0f766e]" />
                            <span>Giao cho: {req.assignee_name}</span>
                          </div>
                        ) : (
                          <div className="flex items-center gap-2 text-xs font-black text-rose-600">
                            <AlertTriangle className="h-3.5 w-3.5 text-rose-500" />
                            <span>Chưa phân công nhân sự</span>
                          </div>
                        )}
                      </div>
                    </div>

                    {/* Image Thumbnails */}
                    {req.images && req.images.length > 0 && (
                      <div className="flex gap-2 overflow-x-auto pb-3 mb-4">
                        {req.images.map((img, idx) => (
                          <MaintenanceThumbnail
                            key={idx}
                            src={img}
                            alt={`evidence-${idx}`}
                            onClick={() => setLightboxImage(img)}
                          />
                        ))}
                      </div>
                    )}

                    {/* Action buttons */}
                    <div className="flex gap-2 border-t border-[#3d2a18]/5 pt-3">
                      <button
                        type="button"
                        onClick={() => void openDetail(req)}
                        className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95"
                        title="Xem chi tiết"
                      >
                        <Eye className="h-5 w-5" />
                      </button>

                      <button
                        type="button"
                        onClick={() => { setUpdatingRequest(req); setNewStatus(Number(req.status) === 2 ? 3 : Number(req.status)) }}
                        className="ml-auto inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-[#24170d] px-3.5 text-xs font-black text-[#fff4df] shadow-sm transition hover:bg-[#3d2a18] focus:outline-none focus:ring-4 focus:ring-[#24170d]/20 active:scale-95"
                      >
                        <Settings className="h-4 w-4" /> Trạng thái
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          <AdminPagination
            meta={paginationMeta}
            currentPage={currentPage}
            perPage={perPage}
            totalItems={requests.length}
            itemLabel="phiếu bảo trì"
            isLoading={isLoading}
            onPageChange={setCurrentPage}
            onPerPageChange={changePerPage}
          />
        </section>
      </section>

      {/* LIGHTBOX FOR IMAGES */}
      {lightboxImage && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4">
          <button
            type="button"
            onClick={() => setLightboxImage(null)}
            className="absolute right-6 top-6 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition"
          >
            <X className="h-6 w-6" />
          </button>
          <img src={lightboxImage} alt="Fullscreen evidence" className="max-h-[90vh] max-w-[90vw] object-contain rounded-2xl" />
        </div>
      )}

      {/* DETAIL MODAL */}
      {isDetailOpen && detailRequest && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-stone-950/60 backdrop-blur-sm" onClick={() => setIsDetailOpen(false)} />
          <div className="relative z-10 w-full max-w-3xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl max-h-[90vh] flex flex-col">
            <div className="bg-[#24170d] p-5 text-[#fff4df] flex items-start justify-between">
              <div>
                <p className="text-[10px] font-black uppercase tracking-[0.24em] text-[#f3c56b]">Chi tiết sự cố</p>
                <h2 className="mt-1.5 text-xl font-black tracking-tight flex items-center gap-2">
                  <span>{detailRequest.title}</span>
                  <span className="text-xs font-black bg-[#f3c56b]/20 px-2 py-0.5 rounded text-[#f3c56b] tabular-nums">{detailRequest.request_code}</span>
                </h2>
              </div>
              <button
                type="button"
                onClick={() => setIsDetailOpen(false)}
                className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-5 sm:p-6 space-y-6">
              {isDetailLoading ? (
                <div className="h-40 animate-pulse bg-stone-100 rounded-2xl" />
              ) : (
                <>
                  {/* General Info */}
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <DetailTile label="Tòa nhà & Phòng" value={`Phòng ${detailRequest.room_number || 'N/A'} — ${detailRequest.building_name || 'Dùng chung'}`} />
                    <DetailTile label="Trạng thái" value={detailRequest.status_label || statusLabels[detailRequest.status]} />
                    <DetailTile label="Khách thuê" value={`${detailRequest.tenant_name || 'N/A'} (${detailRequest.tenant_phone || 'Không có số'})`} />
                    <DetailTile label="Nhân sự xử lý" value={detailRequest.assignee_name || 'Chưa phân công'} />
                  </div>

                  {/* Description */}
                  <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
                    <p className={labelClass}>Mô tả sự cố của khách</p>
                    <p className="text-sm font-semibold leading-relaxed text-[#3d2a18] whitespace-pre-wrap">{detailRequest.description}</p>
                  </section>

                  {/* Image Grid Before/After */}
                  {detailRequest.images && detailRequest.images.length > 0 && (
                    <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
                      <p className={labelClass}>Hình ảnh đính kèm (Ảnh trước / sau)</p>
                      <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-3">
                        {detailRequest.images.map((img, idx) => (
                          <div key={idx} className="relative group cursor-zoom-in" onClick={() => setLightboxImage(img)}>
                            <MaintenanceDetailImage
                              src={img}
                              alt={`evidence-${idx}`}
                              label={idx === 0 ? 'Ảnh trước' : 'Ảnh sau'}
                            />
                          </div>
                        ))}
                      </div>
                    </section>
                  )}

                  {/* Feedback Section */}
                  {detailRequest.feedbacks && detailRequest.feedbacks.length > 0 && (
                    <section className="rounded-[1.5rem] border border-[#3d2a18]/12 bg-emerald-50/50 p-4 border-emerald-200">
                      <p className="text-[10px] font-black uppercase tracking-widest text-emerald-800">Phản hồi từ khách thuê</p>
                      {detailRequest.feedbacks.map((f) => (
                        <div key={f.id} className="mt-2">
                          <p className="text-sm italic text-stone-700 bg-white/80 p-3 rounded-xl border border-stone-200">
                            "{f.comment || 'gửi phản hồi (không có nội dung)'}"
                          </p>
                        </div>
                      ))}
                    </section>
                  )}

                  {/* Logs/Timeline */}
                  {detailRequest.logs && detailRequest.logs.length > 0 && (
                    <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
                      <p className={labelClass}>Lịch sử xử lý</p>
                      <div className="mt-4 space-y-4 relative before:absolute before:left-3.5 before:top-2 before:bottom-2 before:w-[2px] before:bg-[#3d2a18]/10">
                        {detailRequest.logs.map((log) => (
                          <div key={log.id} className="flex gap-4 relative">
                            <div className="flex h-7 w-7 items-center justify-center rounded-full bg-[#f3c56b] border-2 border-[#fffaf1] z-10 shadow-sm text-white">
                              <Clock className="h-3.5 w-3.5 text-[#24170d]" />
                            </div>
                            <div className="flex-1 bg-white/40 border border-[#3d2a18]/5 rounded-xl p-3 text-xs">
                              <div className="flex items-center justify-between gap-2 mb-1">
                                <span className="font-black text-[#24170d]">
                                  {log.new_status_label}
                                </span>
                                <span className="text-[10px] text-stone-500 font-bold tabular-nums">
                                  {formatDateTime(log.created_at)}
                                </span>
                              </div>
                              <p className="text-stone-600 font-medium">{log.note}</p>
                              {log.creator_name && (
                                <p className="mt-1 text-[9px] text-[#0f766e] font-black uppercase">Thực hiện bởi: {log.creator_name}</p>
                              )}
                            </div>
                          </div>
                        ))}
                      </div>
                    </section>
                  )}
                </>
              )}
            </div>

            <div className="p-4 bg-stone-100 border-t border-[#3d2a18]/10 flex gap-2 justify-end">

              <button
                type="button"
                onClick={() => { setUpdatingRequest(detailRequest); setNewStatus(detailRequest.status) }}
                className="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-[#24170d] px-4 text-sm font-black text-[#fff4df]"
              >
                <Settings className="h-4.5 w-4.5" /> Cập nhật trạng thái
              </button>
            </div>
          </div>
        </div>
      )}



      {/* UPDATE STATUS MODAL */}
      {updatingRequest && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-stone-950/60 backdrop-blur-sm" onClick={() => setUpdatingRequest(null)} />
          <div className="relative z-10 w-full max-w-md overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl p-5 sm:p-6">
            <h2 className="text-lg font-black text-[#24170d] mb-2 flex items-center gap-2">
              <Settings className="h-5 w-5 text-[#f3c56b]" />
              Cập nhật trạng thái
            </h2>
            <p className="text-xs font-bold text-[#8b5e34]/60 mb-4">
              Điều chỉnh trạng thái tiến trình xử lý sự cố sự cố phòng {updatingRequest.room_number}.
            </p>

            <div className="space-y-4">
              <div>
                <label className={labelClass}>Chọn trạng thái mới</label>
                <AdminSelect
                  value={newStatus}
                  options={statusChangeOptions.map((o) => ({ value: o.value, label: o.label, tone: 'default' as const }))}
                  onChange={(val) => setNewStatus(Number(val))}
                />
              </div>

              <div>
                <label className={labelClass}>Ghi chú xử lý</label>
                <textarea
                  value={updateNote}
                  onChange={(e) => setUpdateNote(e.target.value)}
                  placeholder="Ghi chú chi tiết công việc hoặc lý do cập nhật trạng thái..."
                  className={`${inputClass} min-h-20`}
                />
              </div>

              {/* Upload after image if Completed (4) */}
              {newStatus === 4 && (
                <div>
                  <label className={labelClass}>Ảnh minh chứng sau khi hoàn thành sửa chữa</label>
                  <div className="relative flex min-h-12 w-full items-center justify-between rounded-2xl border border-dashed border-[#3d2a18]/20 bg-white px-4 py-2 text-stone-700">
                    <input
                      type="file"
                      accept="image/*"
                      onChange={(e) => {
                        if (e.target.files && e.target.files.length > 0) {
                          setAfterImageFile(e.target.files[0])
                        }
                      }}
                      className="absolute inset-0 cursor-pointer opacity-0"
                    />
                    <div className="flex items-center gap-2">
                      <ImageIcon className="h-5 w-5 text-[#a65f16]" />
                      <span className="text-xs font-bold truncate max-w-56">
                        {afterImageFile ? afterImageFile.name : 'Chọn ảnh sau khi sửa...'}
                      </span>
                    </div>
                    {afterImageFile && (
                      <button
                        type="button"
                        onClick={(e) => { e.stopPropagation(); setAfterImageFile(null) }}
                        className="h-6 w-6 rounded bg-rose-50 text-rose-600 flex items-center justify-center font-black text-xs hover:bg-rose-100 z-10"
                      >
                        ✕
                      </button>
                    )}
                  </div>
                </div>
              )}

              <div className="flex gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => setUpdatingRequest(null)}
                  className="flex-1 h-12 rounded-xl border border-[#3d2a18]/10 text-sm font-black text-[#8b5e34] bg-white transition hover:bg-stone-50"
                >
                  HỦY
                </button>
                <button
                  type="button"
                  disabled={isUpdatingStatus || (newStatus === 4 && !afterImageFile)}
                  onClick={() => void handleStatusSubmit()}
                  className="flex-1 h-12 rounded-xl bg-[#24170d] text-sm font-black text-[#fff4df] shadow-lg transition hover:bg-[#3d2a18] disabled:opacity-60"
                >
                  {isUpdatingStatus ? 'Đang cập nhật...' : 'CẬP NHẬT'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </>
  )
}

function MetricCard({ label, value, tone }: { label: string; value: number; tone: 'neutral' | 'rose' | 'amber' | 'emerald' }) {
  const toneClassNames = {
    neutral: 'border-[#f8e8c8]/12 bg-[#f8e8c8]/10 text-[#fff4df]',
    rose: 'border-rose-400/25 bg-rose-500/10 text-rose-200',
    amber: 'border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#fff4df]',
    emerald: 'border-[#0f766e]/35 bg-[#0f766e]/16 text-[#c8fff4]',
  }[tone]

  return (
    <div className={cn('rounded-2xl border px-3 py-2.5 backdrop-blur', toneClassNames)}>
      <p className="text-[10px] font-black uppercase tracking-[0.18em] opacity-60">{label}</p>
      <p className="mt-0.5 text-2xl font-black tracking-tight tabular-nums">{value}</p>
    </div>
  )
}

function DetailTile({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-2xl border border-[#3d2a18]/10 bg-white/70 p-3 sm:p-4">
      <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">{label}</p>
      <p className="mt-1 text-sm sm:text-base font-black text-[#24170d] tabular-nums leading-snug">{value}</p>
    </div>
  )
}

function StatusBadge({ status, label }: { status: number; label: string }) {
  const tone = {
    1: 'border-rose-200 bg-rose-50 text-rose-700', // Mới tạo
    3: 'border-amber-200 bg-amber-50 text-amber-700', // Đang xử lý
    4: 'border-emerald-200 bg-emerald-50 text-emerald-700', // Đã hoàn thành
    5: 'border-stone-200 bg-stone-100 text-stone-600', // Đã hủy
  }[status] || 'border-stone-200 bg-stone-50 text-stone-600'

  return (
    <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-[10px] font-black shadow-sm', tone)}>
      {label}
    </span>
  )
}

function MaintenanceThumbnail({ src, alt, onClick }: { src: string; alt: string; onClick: () => void; key?: any }) {
  const [hasError, setHasError] = useState(src.includes('/storage/demo/'))

  if (hasError) {
    return (
      <div 
        className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#3d2a18]/5 text-[#8b5e34]/60"
        title="Không thể tải ảnh"
      >
        <ImageIcon className="h-5 w-5" />
      </div>
    )
  }

  return (
    <img
      src={src}
      alt={alt}
      onError={() => setHasError(true)}
      onClick={onClick}
      className="h-12 w-12 rounded-xl object-cover border border-[#3d2a18]/10 cursor-zoom-in transition duration-200"
    />
  )
}

function MaintenanceDetailImage({ src, alt, label }: { src: string; alt: string; label: string; key?: any }) {
  const [hasError, setHasError] = useState(src.includes('/storage/demo/'))

  if (hasError) {
    return (
      <div className="relative flex h-28 w-full items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#3d2a18]/5 text-[#8b5e34]/60">
        <div className="flex flex-col items-center gap-1">
          <ImageIcon className="h-6 w-6" />
          <span className="text-[10px] font-black text-[#8b5e34]/80">Không có ảnh</span>
        </div>
        <span className="absolute bottom-1 right-1 bg-black/60 text-[9px] text-white px-2 py-0.5 rounded font-black uppercase">
          {label}
        </span>
      </div>
    )
  }

  return (
    <>
      <img src={src} alt={alt} onError={() => setHasError(true)} className="h-28 w-full object-cover rounded-xl border border-[#3d2a18]/10 transition duration-200" />
      <span className="absolute bottom-1 right-1 bg-black/60 text-[9px] text-white px-2 py-0.5 rounded font-black uppercase">
        {label}
      </span>
    </>
  )
}
