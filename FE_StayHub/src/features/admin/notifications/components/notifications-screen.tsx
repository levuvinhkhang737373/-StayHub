import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { ConfirmModal } from '../../../../shared/components/ConfirmModal'
import { useConfirmModal } from '../../../../shared/lib/hooks/use-confirm-modal'
import { useNavigate, useSearchParams } from 'react-router-dom'
import {
  Bell,
  Plus,
  Trash2,
  Clock,
  Building,
  User,
  Edit3,
  CheckCheck,
  X,
} from 'lucide-react'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatDateTime } from '../../../../shared/lib/utils/format'
import { canManageContractsRole, isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import { fetchAdminTenants } from '../../tenants/services/tenants.service'
import type { AdminTenantResource } from '../../tenants/types/tenant-api.model'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { getVisibleErrorMessage, getVisibleFilterErrorMessage } from '../../shared/utils/error-message'
import { AdminPagination, type AdminPaginationMeta } from '../../shared/components/AdminPagination'
import {
  fetchAdminNotifications,
  fetchAdminNotificationDetail,
  deleteAdminNotification
} from '../services/notification.service'
import type {
  AdminNotificationResource
} from '../types/notification-api.model'
import { NotificationModal, type NotificationFormValues } from './notification-modal'
import { resolveNotificationActionPath } from '../utils/notification-link'
import { useAdminNotifications } from '../hooks/admin-notification-context'

function getResourceList<T>(result: { data?: T[] } | T[] | null | undefined): T[] {
  if (!result) return []
  if (Array.isArray(result)) return result
  return result.data || []
}

const notificationTypeLabels: Record<number, string> = {
  1: 'Sửa chữa',
  2: 'Hóa đơn',
  3: 'Hệ thống',
  4: 'Cảnh báo',
  5: 'Khác',
}

const targetTypeLabels: Record<number, string> = {
  1: 'Toàn hệ thống',
  2: 'Theo tòa nhà',
  3: 'Theo phòng',
  4: 'Theo khách thuê',
  5: 'Ban quản lý',
}

const statusLabels: Record<number, string> = {
  1: 'Nháp',
  2: 'Đã gửi',
  3: 'Đã hủy',
}

const filterStatusOptions = [
  { value: '', label: 'Tất cả trạng thái', tone: 'default' as const },
  { value: '1', label: 'Nháp', tone: 'warning' as const },
  { value: '2', label: 'Đã gửi', tone: 'success' as const },
  { value: '3', label: 'Đã hủy', tone: 'danger' as const },
]

const filterTargetTypeOptions = [
  { value: '', label: 'Tất cả', tone: 'default' as const },
  { value: '1', label: 'Toàn hệ thống', tone: 'default' as const },
  { value: '2', label: 'Theo tòa nhà', tone: 'default' as const },
  { value: '3', label: 'Theo phòng', tone: 'default' as const },
  { value: '4', label: 'Theo khách thuê', tone: 'default' as const },
]

const superAdminOnlyNotificationActionPrefixes = [
  '/admin/facilities',
  '/admin/asset-templates',
  '/admin/room-types',
  '/admin/services',
  '/admin/system-users',
  '/admin/activity-logs',
]

function matchesAdminPathPrefix(path: string, prefix: string) {
  return path === prefix || path.startsWith(`${prefix}/`) || path.startsWith(`${prefix}?`)
}

function canOpenNotificationActionPath(path: string, role?: string | number | null) {
  if (superAdminOnlyNotificationActionPrefixes.some((prefix) => matchesAdminPathPrefix(path, prefix))) {
    return isSuperAdminRole(role)
  }

  if (matchesAdminPathPrefix(path, '/admin/contracts')) {
    return canManageContractsRole(role)
  }

  return true
}


export function NotificationsScreen() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const notificationIdParam = searchParams.get('id')
  const lastOpenedNotificationIdRef = useRef<string | null>(null)
  const { session } = useAdminSession()
  const isSuperAdmin = isSuperAdminRole(session?.admin?.role)
  const { notifications: receivedNotifications, markAllAsRead, markAsRead } = useAdminNotifications()

  const [selectedStatus, setSelectedStatus] = useState('')
  const [selectedTargetType, setSelectedTargetType] = useState('')
  const [selectedBuildingId, setSelectedBuildingId] = useState('')
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [tenants, setTenants] = useState<AdminTenantResource[]>([])
  const [notifications, setNotifications] = useState<AdminNotificationResource[]>([])
  const [detailNotification, setDetailNotification] = useState<AdminNotificationResource | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const { confirmState, isConfirmLoading, setIsConfirmLoading, showConfirm, closeConfirm } = useConfirmModal()

  const [currentPage, setCurrentPage] = useState(1)
  const [perPage, setPerPage] = useState(20)
  const [paginationMeta, setPaginationMeta] = useState<AdminPaginationMeta | null>(null)
  const [stats, setStats] = useState<{ total: number; sent: number; draft: number; cancelled: number } | null>(null)

  useEffect(() => {
    setCurrentPage(1)
  }, [selectedStatus, selectedTargetType, selectedBuildingId])

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

  const defaultForm = useMemo<NotificationFormValues>(() => ({
    title: '',
    content: '',
    notification_type: 3,
    target_type: isSuperAdmin ? 1 : 2,
    building_id: '',
    room_id: '',
    tenant_id: '',
    status: 2,
  }), [isSuperAdmin])

  // Drawer / Form state
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [editingNotificationId, setEditingNotificationId] = useState<number | null>(null)
  const [form, setForm] = useState<NotificationFormValues>(defaultForm)

  const buildingOptions = useMemo(() => buildings.map((b) => ({ value: b.id, label: b.name, tone: 'default' as const })), [buildings])
  const filterBuildingOptions = useMemo(
    () => isSuperAdmin
      ? [{ value: '', label: 'Tất cả tòa nhà', tone: 'default' as const }, ...buildingOptions]
      : buildingOptions,
    [buildingOptions, isSuperAdmin]
  )

  // Metrics
  const metrics = useMemo(() => {
    if (stats) {
      return stats
    }
    const total = notifications.length
    const sent = notifications.filter((n) => Number(n.status) === 2).length
    const draft = notifications.filter((n) => Number(n.status) === 1).length
    const cancelled = notifications.filter((n) => Number(n.status) === 3).length
    return { total, sent, draft, cancelled }
  }, [notifications, stats])

  const loadResources = useCallback(async () => {
    try {
      const [buildingsRes, tenantsRes] = await Promise.all([
        fetchAdminBuildings({ per_page: 100 }),
        fetchAdminTenants({ per_page: 100, status: 1 }) // Load active tenants
      ])
      const list = getResourceList(buildingsRes.result)
      setBuildings(list)
      if (!isSuperAdmin && !selectedBuildingId && list[0]?.id) {
        setSelectedBuildingId(String(list[0].id))
      }

      // Parse tenants from envelope structure
      const tenantsEnvelope = tenantsRes as any
      const tenantsList = tenantsEnvelope.result?.data || tenantsEnvelope.data || []
      setTenants(tenantsList)
    } catch (e) {
      console.error('Không thể load danh sách tòa nhà/khách thuê', e)
    }
  }, [isSuperAdmin, selectedBuildingId])

  const loadNotifications = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)
    try {
      const response = await fetchAdminNotifications({
        status: selectedStatus ? Number(selectedStatus) : undefined,
        target_type: selectedTargetType ? Number(selectedTargetType) : undefined,
        building_id: selectedBuildingId ? Number(selectedBuildingId) : undefined,
        page: currentPage,
        per_page: perPage,
      })

      if (response.result) {
        const result = response.result as any
        if (Array.isArray(result)) {
          setNotifications(result)
          setPaginationMeta(null)
          setStats(null)
        } else {
          setNotifications(result.data || [])
          setPaginationMeta(result.pagination || null)
          setStats(result.stats || null)
        }
      } else {
        setNotifications([])
        setPaginationMeta(null)
        setStats(null)
      }
    } catch (error) {
      setErrorMessage(getVisibleFilterErrorMessage(error, 'Không thể tải danh sách thông báo.', Boolean(selectedStatus || selectedTargetType || selectedBuildingId)))
    } finally {
      setIsLoading(false)
    }
  }, [selectedStatus, selectedTargetType, selectedBuildingId, currentPage, perPage])

  useEffect(() => {
    void loadResources()
  }, [loadResources])

  useEffect(() => {
    void loadNotifications()
  }, [loadNotifications])

  useEffect(() => {
    const notificationId = Number(notificationIdParam)
    if (!Number.isFinite(notificationId) || notificationId <= 0) {
      lastOpenedNotificationIdRef.current = null
      return
    }

    if (lastOpenedNotificationIdRef.current === notificationIdParam) return

    lastOpenedNotificationIdRef.current = notificationIdParam
    const matchedNotification = notifications.find((item) => Number(item.id) === notificationId)
    void openDetail(matchedNotification || ({ id: notificationId } as AdminNotificationResource))
  }, [notificationIdParam, notifications])

  useEffect(() => {
    function handleRefresh() {
      console.log('WS Event: Refreshing admin notifications list')
      void loadNotifications()
    }
    window.addEventListener('maintenance-created', handleRefresh)
    window.addEventListener('invoice-refresh', handleRefresh)
    window.addEventListener('contract-refresh', handleRefresh)
    window.addEventListener('contract-deposit-paid', handleRefresh)
    window.addEventListener('notification-refresh', handleRefresh)
    return () => {
      window.removeEventListener('maintenance-created', handleRefresh)
      window.removeEventListener('invoice-refresh', handleRefresh)
      window.removeEventListener('contract-refresh', handleRefresh)
      window.removeEventListener('contract-deposit-paid', handleRefresh)
      window.removeEventListener('notification-refresh', handleRefresh)
    }
  }, [loadNotifications])

  const openCreateForm = () => {
    if (editingNotificationId !== null) {
      setForm(defaultForm)
    }
    setEditingNotificationId(null)
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const openEditForm = (notif: AdminNotificationResource) => {
    if (Number(notif.status) === 2) {
      setErrorMessage('Không thể chỉnh sửa thông báo đã gửi.')
      return
    }
    setEditingNotificationId(notif.id)
    setForm({
      title: notif.title || '',
      content: notif.content || '',
      notification_type: notif.notification_type,
      target_type: notif.target_type === 3 ? 2 : notif.target_type,
      building_id: notif.building_id ? String(notif.building_id) : '',
      room_id: notif.room_id ? String(notif.room_id) : '',
      tenant_id: notif.tenant_id ? String(notif.tenant_id) : '',
      status: notif.status,
    })
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const openDetail = async (notif: AdminNotificationResource) => {
    setIsDetailOpen(true)
    setIsDetailLoading(true)
    setDetailErrorMessage(null)
    setDetailNotification(notif)

    try {
      const response = await fetchAdminNotificationDetail(notif.id)
      setDetailNotification(response.result)
    } catch (error) {
      setDetailErrorMessage(getVisibleErrorMessage(error, 'Không thể tải chi tiết thông báo.'))
    } finally {
      setIsDetailLoading(false)
    }
  }

  const openNotificationAction = (notif: AdminNotificationResource) => {
    markAsRead(String(notif.id))

    const actionPath = resolveNotificationActionPath(notif) || '/admin/notifications'
    if (actionPath.startsWith('/admin/notifications') || !canOpenNotificationActionPath(actionPath, session?.admin?.role)) {
      void openDetail(notif)
      return
    }

    navigate(actionPath)
  }

  const closeDetail = () => {
    setIsDetailOpen(false)
    setDetailNotification(null)
    setDetailErrorMessage(null)
    if (notificationIdParam) {
      lastOpenedNotificationIdRef.current = null
      navigate('/admin/notifications', { replace: true })
    }
  }

  const handleCancelForm = () => {
    setIsFormOpen(false)
    setForm(defaultForm)
    setEditingNotificationId(null)
  }

  const handleCloseForm = () => {
    setIsFormOpen(false)
  }

  const handleSubmitSuccess = () => {
    setIsFormOpen(false)
    setForm(defaultForm)
    setEditingNotificationId(null)
    setSuccessMessage(editingNotificationId ? 'Cập nhật thông báo thành công.' : 'Tạo thông báo thành công.')
    void loadNotifications()
  }

  // Delete notification
  const handleDelete = (id: number) => {
    showConfirm({
      title: 'Xóa thông báo',
      message: 'Bạn có chắc chắn muốn xóa thông báo này?',
      confirmLabel: 'Xóa',
      onConfirm: async () => {
        try {
          setIsConfirmLoading(true)
          setErrorMessage(null)
          setSuccessMessage(null)
          await deleteAdminNotification(id)
          setSuccessMessage('Xóa thông báo thành công.')
          void loadNotifications()
        } catch (e) {
          setErrorMessage(getVisibleErrorMessage(e, 'Không thể xóa thông báo.'))
        } finally {
          setIsConfirmLoading(false)
          closeConfirm()
        }
      },
      variant: 'danger',
    })
  }

  // Delete all notifications
  const handleDeleteAll = () => {
    if (notifications.length === 0) return
    showConfirm({
      title: 'Xóa tất cả thông báo',
      message: `Bạn có chắc chắn muốn xóa tất cả ${notifications.length} thông báo hiện tại? Hành động này không thể hoàn tác.`,
      confirmLabel: 'Xóa tất cả',
      onConfirm: async () => {
        try {
          setIsConfirmLoading(true)
          setErrorMessage(null)
          setSuccessMessage(null)
          await Promise.all(notifications.map((n) => deleteAdminNotification(n.id)))
          setSuccessMessage('Xóa tất cả thông báo thành công.')
          void loadNotifications()
        } catch (e) {
          setErrorMessage(getVisibleErrorMessage(e, 'Không thể xóa thông báo.'))
        } finally {
          setIsConfirmLoading(false)
          closeConfirm()
        }
      },
      variant: 'danger',
    })
  }

  const changePerPage = (nextPerPage: number) => {
    setPerPage(nextPerPage)
    setCurrentPage(1)
  }

  const clearFilters = () => {
    setSelectedBuildingId(isSuperAdmin ? '' : (buildings[0]?.id ? String(buildings[0].id) : ''))
    setSelectedStatus('')
    setSelectedTargetType('')
  }

  return (
    <>
      <>
        <section className="space-y-6 text-[#24170d]">
          {/* Header and Summary Panel */}
          <div className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
            <div className="relative p-5 text-[#fff4df] sm:p-6 lg:p-7">
              <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(243,197,107,0.24),transparent_30%),radial-gradient(circle_at_82%_16%,rgba(15,118,110,0.22),transparent_32%),linear-gradient(135deg,#24170d_0%,#3d2a18_54%,#0f3f3b_100%)]" />
              <div className="relative flex min-w-0 flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                <div className="min-w-0">
                  <span className="block text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]/80">VẬN HÀNH</span>
                  <h1 className="mt-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem] flex items-center gap-3">
                    <Bell className="h-8 w-8 text-[#f3c56b] shrink-0" />
                    Thông báo
                  </h1>
                </div>
                <div className="flex flex-col sm:flex-row gap-2 shrink-0 w-full sm:w-auto">
                  <button
                    type="button"
                    onClick={markAllAsRead}
                    className="inline-flex w-full sm:w-fit justify-center h-9 items-center gap-2 rounded-xl border border-[#f3c56b]/35 bg-[#f3c56b]/10 px-4 text-sm font-black text-[#f3c56b] transition hover:bg-[#f3c56b]/20 focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20 active:scale-[0.98]"
                  >
                    <CheckCheck className="h-4 w-4" /> Đọc tất cả
                  </button>
                  <button
                    type="button"
                    onClick={handleDeleteAll}
                    className="inline-flex w-full sm:w-fit justify-center h-9 items-center gap-2 rounded-xl border border-rose-500/35 bg-rose-500/10 px-4 text-sm font-black text-rose-300 transition hover:bg-rose-500/20 focus:outline-none focus:ring-4 focus:ring-rose-500/20 active:scale-[0.98]"
                  >
                    <Trash2 className="h-4 w-4" /> Xóa tất cả
                  </button>
                  <button
                    type="button"
                    onClick={openCreateForm}
                    className="inline-flex w-full sm:w-fit justify-center h-9 items-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition-all hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98]"
                  >
                    <Plus className="h-4 w-4 stroke-[2.8]" /> Thêm thông báo
                  </button>
                </div>
              </div>

              {/* Metrics */}
              <div className="relative mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <MetricCard label="Tổng thông báo" value={metrics.total} tone="neutral" />
                <MetricCard label="Bản nháp" value={metrics.draft} tone="amber" />
                <MetricCard label="Đã gửi đi" value={metrics.sent} tone="emerald" />
                <MetricCard label="Đã hủy" value={metrics.cancelled} tone="neutral" />
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

          <div className="grid min-w-0 grid-cols-1 gap-4 xl:gap-6">
            {/* Main List */}
            <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/88 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
              <div className="border-b border-[#3d2a18]/10 bg-[#fff7e8]/72 p-4 sm:p-5">
                <div className="flex flex-wrap items-center gap-3">
                  <div className="min-w-[220px] flex-1">
                    <AdminSelect
                      value={selectedTargetType}
                      options={filterTargetTypeOptions}
                      onChange={(val) => setSelectedTargetType(String(val))}
                    />
                  </div>

                  <div className="min-w-[220px] flex-1">
                    <AdminSelect
                      value={selectedBuildingId}
                      options={filterBuildingOptions}
                      onChange={(val) => setSelectedBuildingId(String(val))}
                    />
                  </div>

                  <div className="min-w-[220px] flex-1">
                    <AdminSelect
                      value={selectedStatus}
                      options={filterStatusOptions}
                      onChange={(val) => setSelectedStatus(String(val))}
                    />
                  </div>

                  {(selectedStatus || selectedTargetType || selectedBuildingId) && (
                    <button
                      type="button"
                      onClick={clearFilters}
                      className="inline-flex h-10 items-center justify-center rounded-xl bg-rose-100 px-4 text-sm font-black text-rose-700 transition hover:bg-rose-200"
                    >
                      Xóa bộ lọc
                    </button>
                  )}
                </div>
              </div>

              {/* List */}
              <div className="p-4 sm:p-6 space-y-4">
                {isLoading ? (
                  <div className="space-y-4">
                    {Array.from({ length: 3 }).map((_, idx) => (
                      <div key={idx} className="h-28 animate-pulse rounded-2xl bg-stone-100" />
                    ))}
                  </div>
                ) : notifications.length === 0 ? (
                  <div className="py-20 text-center">
                    <div className="mx-auto flex max-w-sm flex-col items-center">
                      <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]">
                        <Bell className="h-9 w-9" />
                      </div>
                      <p className="text-lg font-black tracking-tight text-[#24170d]">Không có thông báo nào</p>
                      <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">Tạo chiến dịch thông báo để gửi tới các phòng/tòa nhà.</p>
                    </div>
                  </div>
                ) : (
                  <div className="grid grid-cols-1 gap-4">
                    {notifications.map((notif) => {
                      const isUnread = receivedNotifications.find((rn) => rn.id === String(notif.id))?.read === false
                      return (
                        <div
                          key={notif.id}
                          onClick={() => openNotificationAction(notif)}
                          className={cn(
                            "group flex flex-col justify-between gap-4 rounded-2xl border p-4 sm:p-5 cursor-pointer transition sm:flex-row sm:items-center",
                            isUnread
                              ? "border-[#f3c56b]/50 bg-[#f3c56b]/5 hover:bg-[#f3c56b]/10 shadow-sm"
                              : "border-[#3d2a18]/10 bg-white hover:border-[#f3c56b]/40 hover:bg-stone-50/50"
                          )}
                        >
                          <div className="min-w-0 flex-1 space-y-2">
                            <div className="flex flex-wrap items-center gap-1.5">
                              <span className="text-[9px] sm:text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-stone-100 text-stone-600">
                                {notificationTypeLabels[notif.notification_type] || 'Hệ thống'}
                              </span>
                              <span className="text-[9px] sm:text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-[#f3c56b]/15 text-[#8a4f18]">
                                Mục tiêu: {targetTypeLabels[notif.target_type] || 'Tất cả'}
                              </span>
                              <StatusBadge status={notif.status} label={notif.status_label || statusLabels[notif.status]} />
                            </div>

                            <div className="space-y-1">
                              <h3 className="text-sm sm:text-base font-black text-[#24170d] leading-snug">{notif.title}</h3>
                              <p className="text-xs text-[#6f6254] font-medium leading-relaxed whitespace-pre-wrap">{notif.content}</p>
                            </div>

                            <div className="border-t border-stone-100 pt-2 mt-2 sm:border-0 sm:pt-0 sm:mt-0">
                              {/* Desktop metadata */}
                              <div className="hidden sm:flex flex-wrap gap-x-4 gap-y-1 text-[10px] font-bold text-stone-500">
                                {notif.building_name && (
                                  <span className="flex items-center gap-1"><Building className="h-3 w-3" /> Tòa: {notif.building_name}</span>
                                )}
                                {notif.room_number && (
                                  <span className="flex items-center gap-1"><Building className="h-3 w-3" /> Phòng: {notif.room_number}</span>
                                )}
                                {notif.tenant_name && (
                                  <span className="flex items-center gap-1"><User className="h-3 w-3" /> Khách: {notif.tenant_name}</span>
                                )}
                                <span className="flex items-center gap-1"><Clock className="h-3 w-3" /> Ngày tạo: {formatDateTime(notif.created_at)}</span>
                              </div>

                              {/* Mobile metadata grid */}
                              <div className="block sm:hidden grid grid-cols-2 gap-2.5 text-[10px] font-semibold text-stone-500">
                                {notif.building_name && (
                                  <div className="flex flex-col gap-0.5">
                                    <span className="text-[9px] font-bold uppercase tracking-wider text-[#8b5e34]/70">Tòa nhà</span>
                                    <span className="font-bold text-[#24170d] truncate">{notif.building_name}</span>
                                  </div>
                                )}
                                {notif.room_number && (
                                  <div className="flex flex-col gap-0.5">
                                    <span className="text-[9px] font-bold uppercase tracking-wider text-[#8b5e34]/70">Phòng ở</span>
                                    <span className="font-bold text-[#24170d] truncate">Phòng {notif.room_number}</span>
                                  </div>
                                )}
                                {notif.tenant_name && (
                                  <div className="flex flex-col gap-0.5 col-span-2 border-t border-stone-100/60 pt-1.5 mt-0.5">
                                    <span className="text-[9px] font-bold uppercase tracking-wider text-[#8b5e34]/70">Khách thuê</span>
                                    <span className="font-bold text-[#24170d] truncate">{notif.tenant_name}</span>
                                  </div>
                                )}
                                <div className="flex flex-col gap-0.5 col-span-2 border-t border-stone-100/60 pt-1.5 mt-0.5">
                                  <span className="text-[9px] font-bold uppercase tracking-wider text-[#8b5e34]/70">Ngày gửi</span>
                                  <span className="font-bold text-[#24170d]">{formatDateTime(notif.created_at)}</span>
                                </div>
                              </div>
                            </div>
                          </div>

                          <div className="flex gap-2 self-end sm:self-center shrink-0 border-t border-stone-100 w-full pt-3 justify-end sm:border-0 sm:w-auto sm:pt-0">
                            {notif.status !== 2 && (
                              <button
                                type="button"
                                onClick={(e) => {
                                  e.stopPropagation();
                                  openEditForm(notif);
                                }}
                                className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#3d2a18]/10 active:scale-95"
                                title="Sửa bản nháp"
                                aria-label={`Sửa bản nháp thông báo ${notif.title}`}
                              >
                                <Edit3 className="h-4.5 w-4.5" />
                              </button>
                            )}
                            <button
                              type="button"
                              onClick={(e) => {
                                e.stopPropagation();
                                void handleDelete(notif.id);
                              }}
                              className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-95"
                              title="Xóa thông báo"
                              aria-label={`Xóa thông báo ${notif.title}`}
                            >
                              <Trash2 className="h-4.5 w-4.5" />
                            </button>
                          </div>
                        </div>
                      )
                    })}
                  </div>
                )}
              </div>

              <AdminPagination
                meta={paginationMeta}
                currentPage={currentPage}
                perPage={perPage}
                totalItems={notifications.length}
                itemLabel="thông báo"
                isLoading={isLoading}
                onPageChange={setCurrentPage}
                onPerPageChange={changePerPage}
              />
            </section>

            <NotificationModal
              isOpen={isFormOpen}
              onClose={handleCloseForm}
              editingNotificationId={editingNotificationId}
              form={form}
              setForm={setForm}
              onCancel={handleCancelForm}
              onSubmitSuccess={handleSubmitSuccess}
              buildings={buildings}
              tenants={tenants}
              isSuperAdmin={isSuperAdmin}
            />

            {isDetailOpen && (
              <NotificationDetailModal
                notification={detailNotification}
                isLoading={isDetailLoading}
                errorMessage={detailErrorMessage}
                onClose={closeDetail}
              />
            )}
          </div>
        </section>
      </>
      <ConfirmModal {...confirmState} onCancel={closeConfirm} isLoading={isConfirmLoading} />
    </>
  )
}

function NotificationDetailModal({ notification, isLoading, errorMessage, onClose }: { notification: AdminNotificationResource | null; isLoading: boolean; errorMessage: string | null; onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="notification-detail-title">
      <button type="button" aria-label="Đóng chi tiết thông báo" onClick={onClose} className="absolute inset-0 bg-stone-950/70 backdrop-blur-md" />
      <div className="relative z-10 flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] text-[#24170d] shadow-2xl shadow-black/40">
        <div className="flex items-start justify-between gap-4 border-b border-[#3d2a18]/10 bg-white/45 p-5">
          <div className="min-w-0">
            <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#0f766e]">Chi tiết thông báo</p>
            <h2 id="notification-detail-title" className="mt-1 text-xl font-black tracking-[-0.035em] text-[#24170d]">
              {notification?.title || 'Đang tải thông báo...'}
            </h2>
          </div>
          <button type="button" onClick={onClose} className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-white/70 text-[#8b5e34] transition hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-4 focus:ring-rose-100" aria-label="Đóng chi tiết thông báo">
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="overflow-y-auto p-5">
          {isLoading && <div className="h-32 animate-pulse rounded-2xl bg-stone-100" />}

          {!isLoading && errorMessage && (
            <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{errorMessage}</div>
          )}

          {!isLoading && notification && !errorMessage && (
            <div className="space-y-5">
              <div className="flex flex-wrap items-center gap-2">
                <Badge label={notification.notification_type_label || notificationTypeLabels[notification.notification_type] || 'Hệ thống'} />
                <Badge label={notification.target_type_label || targetTypeLabels[notification.target_type] || 'Tất cả'} />
                <StatusBadge status={notification.status} label={notification.status_label || statusLabels[notification.status]} />
              </div>

              <div className="rounded-3xl border border-[#3d2a18]/10 bg-white/70 p-4">
                <p className="text-xs font-black uppercase tracking-[0.14em] text-[#8b5e34]/70">Nội dung</p>
                <p className="mt-3 whitespace-pre-wrap text-sm font-semibold leading-7 text-[#3d2a18]">{notification.content || 'Không có nội dung.'}</p>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <DetailTile label="Tòa nhà" value={notification.building_name || (notification.building_id ? `#${notification.building_id}` : 'Không chỉ định')} />
                <DetailTile label="Phòng" value={notification.room_number || (notification.room_id ? `#${notification.room_id}` : 'Không chỉ định')} />
                <DetailTile label="Khách thuê" value={notification.tenant_name || (notification.tenant_id ? `#${notification.tenant_id}` : 'Không chỉ định')} />
                <DetailTile label="Ngày tạo" value={formatDateTime(notification.created_at)} />
                <DetailTile label="Ngày gửi" value={notification.published_at ? formatDateTime(notification.published_at) : 'Chưa gửi'} />
                <DetailTile label="Người tạo" value={notification.creator_name || (notification.created_by ? `#${notification.created_by}` : 'Hệ thống')} />
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

function DetailTile({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-2xl border border-[#3d2a18]/10 bg-white/60 p-3">
      <p className="text-[10px] font-black uppercase tracking-[0.14em] text-[#8b5e34]/65">{label}</p>
      <p className="mt-1 text-sm font-black text-[#24170d]">{value}</p>
    </div>
  )
}

function Badge({ label }: { label: string }) {
  return <span className="rounded-full border border-[#3d2a18]/10 bg-stone-100 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-stone-600">{label}</span>
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

function StatusBadge({ status, label }: { status: number; label: string }) {
  const tone = {
    1: 'border-amber-200 bg-amber-50 text-amber-700', // Draft
    2: 'border-emerald-200 bg-emerald-50 text-emerald-700', // Sent
    3: 'border-rose-200 bg-rose-50 text-rose-700', // Cancelled
  }[status] || 'border-stone-200 bg-stone-50 text-stone-600'

  return (
    <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-2 py-0.5 text-[9px] font-black shadow-sm', tone)}>
      {label}
    </span>
  )
}
