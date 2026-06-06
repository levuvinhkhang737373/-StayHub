import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { 
  ArrowLeft, 
  Bell, 
  Plus, 
  X, 
  Trash2, 
  Clock, 
  Building, 
  User,
  Edit3
} from 'lucide-react'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatDateTime } from '../../../../shared/lib/utils/format'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import { fetchAdminTenants } from '../../tenants/services/tenants.service'
import type { AdminTenantResource } from '../../tenants/types/tenant-api.model'
import { AdminSelect } from '../../shared/components/AdminSelect'
import {
  fetchAdminNotifications,
  createAdminNotification,
  updateAdminNotification,
  deleteAdminNotification
} from '../services/notification.service'
import type { 
  AdminNotificationResource,
  AdminNotificationPayload 
} from '../types/notification-api.model'

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
  1: 'Tất cả',
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
  { value: '', label: 'Tất cả đối tượng', tone: 'default' as const },
  { value: '1', label: 'Tất cả', tone: 'default' as const },
  { value: '2', label: 'Theo tòa nhà', tone: 'default' as const },
  { value: '3', label: 'Theo phòng', tone: 'default' as const },
  { value: '4', label: 'Theo khách thuê', tone: 'default' as const },
]

const typeOptions = [
  { value: 1, label: 'Sửa chữa' },
  { value: 2, label: 'Hóa đơn' },
  { value: 3, label: 'Hệ thống' },
  { value: 4, label: 'Cảnh báo' },
  { value: 5, label: 'Khác' },
]

const statusOptions = [
  { value: 1, label: 'Bản nháp' },
  { value: 2, label: 'Gửi ngay' },
  { value: 3, label: 'Hủy' },
]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65'

export function NotificationsScreen() {
  const { session } = useAdminSession()
  const isSuperAdmin = isSuperAdminRole(session?.admin?.role)

  const [selectedStatus, setSelectedStatus] = useState('')
  const [selectedTargetType, setSelectedTargetType] = useState('')
  const [selectedBuildingId, setSelectedBuildingId] = useState('')
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [tenants, setTenants] = useState<AdminTenantResource[]>([])
  const [notifications, setNotifications] = useState<AdminNotificationResource[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
 
  // Drawer / Form state
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [editingNotification, setEditingNotification] = useState<AdminNotificationResource | null>(null)
  const [isSaving, setIsSaving] = useState(false)
  
  // Form fields
  const [title, setTitle] = useState('')
  const [content, setContent] = useState('')
  const [notifType, setNotifType] = useState<number>(3) // Default to System
  const [targetType, setTargetType] = useState<number>(1) // Default to All
  const [targetBuildingId, setTargetBuildingId] = useState<string>('')
  const [targetRoomId, setTargetRoomId] = useState<string>('')
  const [targetTenantId, setTargetTenantId] = useState<string>('')
  const [status, setStatus] = useState<number>(1) // Default to Draft

  const buildingOptions = useMemo(() => buildings.map((b) => ({ value: b.id, label: b.name, tone: 'default' as const })), [buildings])
  const filterBuildingOptions = useMemo(() => [{ value: '', label: 'Tất cả tòa nhà', tone: 'default' as const }, ...buildingOptions], [buildingOptions])

  // Map tenants to select options
  const tenantSelectOptions = useMemo(() => {
    return tenants.map((t) => ({
      value: t.id,
      label: `${t.full_name || t.username} (Phòng ${t.room_number || 'N/A'} - ${t.building_name || 'N/A'})`,
      tone: 'default' as const
    }))
  }, [tenants])

  const allowedTargetOptions = useMemo(() => {
    const options = [
      { value: 2, label: 'Theo tòa nhà / phòng' },
      { value: 4, label: 'Theo khách thuê' },
    ]
    if (isSuperAdmin) {
      options.unshift({ value: 1, label: 'Tất cả (Toàn hệ thống)' })
    }
    return options
  }, [isSuperAdmin])

  const handleBuildingChange = (val: string | number) => {
    setTargetBuildingId(String(val))
    setTargetRoomId('')
  }

  // Filter rooms belonging to targetBuildingId
  const filteredRoomSelectOptions = useMemo(() => {
    if (!targetBuildingId) return []
    const seenRoomIds = new Set<number>()
    const options: Array<{ value: string; label: string; tone: 'default' }> = [
      { value: '', label: 'Tất cả phòng (Mặc định gửi cả tòa nhà)', tone: 'default' as const }
    ]

    tenants.forEach((t) => {
      if (t.room_id && String(t.building_id) === targetBuildingId && !seenRoomIds.has(t.room_id)) {
        seenRoomIds.add(t.room_id)
        options.push({
          value: String(t.room_id),
          label: `Phòng ${t.room_number || 'N/A'}`,
          tone: 'default' as const
        })
      }
    })

    return options
  }, [tenants, targetBuildingId])

  // Metrics
  const metrics = useMemo(() => {
    const total = notifications.length
    const sent = notifications.filter((n) => Number(n.status) === 2).length
    const draft = notifications.filter((n) => Number(n.status) === 1).length
    const cancelled = notifications.filter((n) => Number(n.status) === 3).length
    return { total, sent, draft, cancelled }
  }, [notifications])

  const loadResources = useCallback(async () => {
    try {
      const [buildingsRes, tenantsRes] = await Promise.all([
        fetchAdminBuildings({ per_page: 100 }),
        fetchAdminTenants({ per_page: 100, status: 1 }) // Load active tenants
      ])
      setBuildings(getResourceList(buildingsRes.result))
      
      // Parse tenants from envelope structure
      const tenantsEnvelope = tenantsRes as any
      const tenantsList = tenantsEnvelope.result?.data || tenantsEnvelope.data || []
      setTenants(tenantsList)
    } catch (e) {
      console.error('Không thể load danh sách tòa nhà/khách thuê', e)
    }
  }, [])

  const loadNotifications = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)
    try {
      const response = await fetchAdminNotifications({
        status: selectedStatus ? Number(selectedStatus) : undefined,
        target_type: selectedTargetType ? Number(selectedTargetType) : undefined,
        building_id: selectedBuildingId ? Number(selectedBuildingId) : undefined,
      })

      if (response.result && Array.isArray(response.result)) {
        setNotifications(response.result)
      } else if (response.result?.data) {
        setNotifications(response.result.data)
      } else {
        setNotifications([])
      }
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể tải danh sách thông báo.')
    } finally {
      setIsLoading(false)
    }
  }, [selectedStatus, selectedTargetType, selectedBuildingId])

  useEffect(() => {
    void loadResources()
  }, [loadResources])

  useEffect(() => {
    void loadNotifications()
  }, [loadNotifications])

  useEffect(() => {
    if (buildings.length === 1 && !targetBuildingId && isFormOpen) {
      setTargetBuildingId(String(buildings[0].id))
    }
  }, [buildings, targetBuildingId, isFormOpen])

  useEffect(() => {
    function handleMaintenanceCreated() {
      console.log('WS Event: Refreshing admin notifications list')
      void loadNotifications()
    }
    window.addEventListener('maintenance-created', handleMaintenanceCreated)
    return () => {
      window.removeEventListener('maintenance-created', handleMaintenanceCreated)
    }
  }, [loadNotifications])

  // Open Form for Creating
  const openCreateForm = () => {
    setEditingNotification(null)
    setTitle('')
    setContent('')
    setNotifType(3)
    setTargetType(isSuperAdmin ? 1 : 2)
    setTargetBuildingId('')
    setTargetRoomId('')
    setTargetTenantId('')
    setStatus(1)
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  // Open Form for Editing
  const openEditForm = (notif: AdminNotificationResource) => {
    if (notif.status === 2) {
      alert('Không thể chỉnh sửa thông báo đã gửi.')
      return
    }
    setEditingNotification(notif)
    setTitle(notif.title)
    setContent(notif.content)
    setNotifType(notif.notification_type)
    setTargetType(notif.target_type === 3 ? 2 : notif.target_type)
    setTargetBuildingId(notif.building_id ? String(notif.building_id) : '')
    setTargetRoomId(notif.room_id ? String(notif.room_id) : '')
    setTargetTenantId(notif.tenant_id ? String(notif.tenant_id) : '')
    setStatus(notif.status)
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  // Submit Form
  const handleSubmit = async () => {
    if (!title.trim() || !content.trim()) {
      alert('Vui lòng điền đầy đủ tiêu đề và nội dung.')
      return
    }

    if (targetType === 2 && !targetBuildingId) {
      alert('Vui lòng chọn tòa nhà mục tiêu.')
      return
    }

    if (targetType === 4 && !targetTenantId) {
      alert('Vui lòng chọn khách thuê mục tiêu.')
      return
    }

    setIsSaving(true)
    setErrorMessage(null)
    setSuccessMessage(null)

    const payload: AdminNotificationPayload = {
      title: title.trim(),
      content: content.trim(),
      notification_type: notifType,
      target_type: (targetType === 2 && targetRoomId) ? 3 : targetType,
      building_id: targetType === 2 ? Number(targetBuildingId) : null,
      room_id: (targetType === 2 && targetRoomId) ? Number(targetRoomId) : null,
      tenant_id: targetType === 4 ? Number(targetTenantId) : null,
      status: status
    }

    try {
      if (editingNotification) {
        await updateAdminNotification(editingNotification.id, payload)
        setSuccessMessage('Cập nhật thông báo thành công.')
      } else {
        await createAdminNotification(payload)
        setSuccessMessage('Tạo thông báo thành công.')
      }
      setIsFormOpen(false)
      void loadNotifications()
    } catch (e) {
      setErrorMessage(e instanceof Error ? e.message : 'Có lỗi xảy ra khi lưu thông báo.')
    } finally {
      setIsSaving(false)
    }
  }

  // Delete notification
  const handleDelete = async (id: number) => {
    if (!window.confirm('Bạn có chắc chắn muốn xóa thông báo này?')) return
    setErrorMessage(null)
    setSuccessMessage(null)
    try {
      await deleteAdminNotification(id)
      setSuccessMessage('Xóa thông báo thành công.')
      void loadNotifications()
    } catch (e) {
      setErrorMessage(e instanceof Error ? e.message : 'Không thể xóa thông báo.')
    }
  }

  const clearFilters = () => {
    setSelectedBuildingId('')
    setSelectedStatus('')
    setSelectedTargetType('')
  }

  return (
    <div className="relative min-w-0 overflow-hidden rounded-[2rem] bg-[#f4efe6] text-[#24170d] shadow-inner shadow-white/70">
      <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,rgba(77,51,25,0.08)_1px,transparent_1px),linear-gradient(to_bottom,rgba(77,51,25,0.08)_1px,transparent_1px)] bg-[size:36px_36px]" />
      <div className="pointer-events-none absolute -right-28 -top-32 h-80 w-80 rounded-full bg-[#f3c56b]/28 blur-3xl" />
      <div className="pointer-events-none absolute bottom-20 left-10 h-64 w-64 rounded-full bg-[#0f766e]/10 blur-3xl" />

      <div className="relative space-y-5 p-4 sm:space-y-6 sm:p-6">
        {/* Header and Summary Panel */}
        <div className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
          <div className="relative p-3 text-[#fff4df] sm:p-4">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(243,197,107,0.24),transparent_30%),radial-gradient(circle_at_82%_16%,rgba(15,118,110,0.22),transparent_32%),linear-gradient(135deg,#24170d_0%,#3d2a18_54%,#0f3f3b_100%)]" />
            <div className="relative flex min-w-0 flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
              <div className="min-w-0">
                <Link to="/admin/dashboard" className="mb-1 inline-flex items-center gap-2 text-xs font-black text-[#f3c56b] transition hover:text-[#ffd56f]">
                  <ArrowLeft className="h-3.5 w-3.5" /> Về dashboard
                </Link>
                <h1 className="max-w-3xl text-2xl font-black tracking-[-0.04em] text-[#fff4df] sm:text-[1.7rem] lg:text-3xl flex items-center gap-2.5">
                  <Bell className="h-7 w-7 text-[#f3c56b]" />
                  Thông báo
                </h1>
              </div>
              <button 
                type="button" 
                onClick={openCreateForm} 
                className="inline-flex h-9 items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition-all hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98]"
              >
                <Plus className="h-4 w-4 stroke-[2.8]" /> Thêm thông báo
              </button>
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
        {(errorMessage || successMessage) && (
          <div className={cn('rounded-3xl border p-4 text-sm font-black flex items-center justify-between', errorMessage ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700')}>
            <span>{errorMessage || successMessage}</span>
            <button onClick={() => { setErrorMessage(null); setSuccessMessage(null) }} className="hover:opacity-70 text-xs font-bold px-2">Đóng</button>
          </div>
        )}

        <div className={cn('grid min-w-0 grid-cols-1 gap-4 xl:gap-6', isFormOpen && '2xl:grid-cols-[minmax(0,1fr)_400px]')}>
          {/* Main List */}
          <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/88 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
            <div className="border-b border-[#3d2a18]/10 bg-[#fff7e8]/72 p-4 sm:p-5">
              <div className="grid gap-3 sm:grid-cols-3">
                <AdminSelect value={selectedTargetType} options={filterTargetTypeOptions} onChange={(val) => setSelectedTargetType(String(val))} />
                <AdminSelect value={selectedBuildingId} options={filterBuildingOptions} onChange={(val) => setSelectedBuildingId(String(val))} />
                <div className="relative min-w-0">
                  <AdminSelect value={selectedStatus} options={filterStatusOptions} onChange={(val) => setSelectedStatus(String(val))} />
                  {(selectedStatus || selectedTargetType || selectedBuildingId) && (
                    <button 
                      type="button" 
                      onClick={clearFilters} 
                      className="absolute -right-2 -top-1 inline-flex h-6 items-center justify-center gap-1 rounded-full bg-rose-100 px-2 text-[10px] font-black text-rose-700 hover:bg-rose-200"
                    >
                      Xóa bộ lọc
                    </button>
                  )}
                </div>
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
                  {notifications.map((notif) => (
                    <div 
                      key={notif.id} 
                      className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl border border-[#3d2a18]/10 bg-white hover:border-[#f3c56b]/40 transition"
                    >
                      <div className="min-w-0 space-y-1.5">
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-stone-100 text-stone-600">
                            {notificationTypeLabels[notif.notification_type] || 'Hệ thống'}
                          </span>
                          <span className="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-[#f3c56b]/15 text-[#8a4f18]">
                            Mục tiêu: {targetTypeLabels[notif.target_type] || 'Tất cả'}
                          </span>
                          <StatusBadge status={notif.status} label={notif.status_label || statusLabels[notif.status]} />
                        </div>

                        <h3 className="text-base font-black text-[#24170d]">{notif.title}</h3>
                        <p className="text-xs text-[#6f6254] font-medium whitespace-pre-wrap">{notif.content}</p>

                        <div className="flex flex-wrap gap-x-4 gap-y-1 pt-1.5 text-[10px] font-bold text-stone-500">
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
                      </div>

                      <div className="flex gap-2 self-end sm:self-center">
                        {notif.status !== 2 && (
                          <button 
                            type="button" 
                            onClick={() => openEditForm(notif)} 
                            className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15"
                            title="Sửa bản nháp"
                          >
                            <Edit3 className="h-4.5 w-4.5" />
                          </button>
                        )}
                        <button 
                          type="button" 
                          onClick={() => void handleDelete(notif.id)} 
                          className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-white text-rose-600 hover:bg-rose-50"
                          title="Xóa thông báo"
                        >
                          <Trash2 className="h-4.5 w-4.5" />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </section>

          {/* Form sidebar drawer */}
          {isFormOpen && (
            <aside className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/95 p-5 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md sticky top-6 self-start">
              <div className="mb-5 flex items-start justify-between gap-3">
                <div>
                  <h2 className="text-lg font-black tracking-tight text-[#24170d]">
                    {editingNotification ? 'Cập nhật thông báo' : 'Thêm thông báo'}
                  </h2>
                  <p className="text-xs font-bold text-[#8b5e34]/60">Gửi các bản tin/cảnh báo tới khách thuê.</p>
                </div>
                <button 
                  type="button" 
                  onClick={() => setIsFormOpen(false)} 
                  className="rounded-xl border border-[#3d2a18]/10 p-2 text-[#8b5e34] transition hover:bg-rose-50 hover:text-rose-600"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>

              <div className="space-y-4">
                <div>
                  <label className={labelClass}>Tiêu đề thông báo</label>
                  <input 
                    type="text" 
                    value={title} 
                    onChange={(e) => setTitle(e.target.value)} 
                    placeholder="Ví dụ: Lịch bảo trì điện ngày 15/06" 
                    className={inputClass} 
                  />
                </div>

                <div>
                  <label className={labelClass}>Nội dung chi tiết</label>
                  <textarea 
                    value={content} 
                    onChange={(e) => setContent(e.target.value)} 
                    placeholder="Nhập nội dung thông báo đầy đủ..." 
                    className={`${inputClass} min-h-24`} 
                  />
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className={labelClass}>Loại thông báo</label>
                    <AdminSelect 
                      value={notifType} 
                      options={typeOptions.map((o) => ({ value: o.value, label: o.label, tone: 'default' as const }))} 
                      onChange={(val) => setNotifType(Number(val))} 
                    />
                  </div>
                  <div>
                    <label className={labelClass}>Trạng thái</label>
                    <AdminSelect 
                      value={status} 
                      options={statusOptions.map((o) => ({ value: o.value, label: o.label, tone: 'default' as const }))} 
                      onChange={(val) => setStatus(Number(val))} 
                    />
                  </div>
                </div>

                <div>
                  <label className={labelClass}>Đối tượng mục tiêu</label>
                  <AdminSelect 
                    value={targetType} 
                    options={allowedTargetOptions.map((o) => ({ value: o.value, label: o.label, tone: 'default' as const }))} 
                    onChange={(val) => { setTargetType(Number(val)); setTargetBuildingId(''); setTargetRoomId(''); setTargetTenantId('') }} 
                  />
                </div>

                {/* Conditional Fields */}
                {targetType === 2 && (
                  <div className="space-y-4">
                    <div>
                      <label className={labelClass}>Chọn tòa nhà</label>
                      <AdminSelect 
                        value={targetBuildingId} 
                        options={[{ value: '', label: 'Chọn tòa nhà mục tiêu', tone: 'default' as const }, ...buildings.map((b) => ({ value: b.id, label: b.name, tone: 'default' as const }))]} 
                        onChange={(val) => handleBuildingChange(String(val))} 
                      />
                    </div>

                    {targetBuildingId && (
                      <div>
                        <label className={labelClass}>Chọn phòng nhận tin (Không bắt buộc)</label>
                        <AdminSelect 
                          value={targetRoomId} 
                          options={filteredRoomSelectOptions} 
                          onChange={(val) => setTargetRoomId(String(val))} 
                          placeholder="Mặc định gửi cả tòa nhà"
                        />
                      </div>
                    )}
                  </div>
                )}

                {targetType === 4 && (
                  <div>
                    <label className={labelClass}>Chọn khách thuê nhận tin</label>
                    <AdminSelect 
                      value={targetTenantId} 
                      options={tenantSelectOptions} 
                      onChange={(val) => setTargetTenantId(String(val))} 
                      placeholder="Chọn khách thuê mục tiêu"
                    />
                  </div>
                )}

                <div className="pt-2">
                  <button 
                    type="button" 
                    disabled={isSaving} 
                    onClick={() => void handleSubmit()} 
                    className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-[1.25rem] bg-[#24170d] px-5 py-3.5 text-base font-black text-[#fff4df] transition hover:bg-[#3d2a18] disabled:opacity-60"
                  >
                    {isSaving ? 'Đang gửi...' : editingNotification ? 'Cập nhật' : 'Gửi thông báo'}
                  </button>
                </div>
              </div>
            </aside>
          )}
        </div>
      </div>
    </div>
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
