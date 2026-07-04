import { useCallback, useEffect, useMemo, useState } from 'react'
import { ConfirmModal } from '../../../../shared/components/ConfirmModal'
import { useConfirmModal } from '../../../../shared/lib/hooks/use-confirm-modal'
import { useNavigate } from 'react-router-dom'
import { 
  Bell, 
  Plus, 
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
  deleteAdminNotification
} from '../services/notification.service'
import type { 
  AdminNotificationResource
} from '../types/notification-api.model'
import { NotificationModal, type NotificationFormValues } from './notification-modal'

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


export function NotificationsScreen() {
  const navigate = useNavigate()
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
  const { confirmState, isConfirmLoading, setIsConfirmLoading, showConfirm, closeConfirm } = useConfirmModal()

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
    status: 1,
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
      setErrorMessage('Không thể chỉnh sửa thông báo đã gửi.')
      return
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
          setErrorMessage(e instanceof Error ? e.message : 'Không thể xóa thông báo.')
        } finally {
          setIsConfirmLoading(false)
          closeConfirm()
        }
      },
      variant: 'danger',
    })
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
              <button 
                type="button" 
                onClick={openCreateForm} 
                className="inline-flex w-fit self-end xl:self-auto h-9 items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition-all hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98]"
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
                      onClick={() => {
                        const scMatch = (notif.content || '').match(/(SC-\d{6})/i)
                        const invMatch = (notif.content || '').match(/(INV-[A-Z0-9-]+)/i)
                        const hdMatch = (notif.content || '').match(/(HD-[A-Z0-9-]+)/i)

                        let link = '/admin/contracts'
                        if (notif.notification_type === 1) {
                          link = scMatch ? `/admin/maintenance?request_code=${scMatch[1]}` : '/admin/maintenance'
                        } else if (notif.notification_type === 2) {
                          link = invMatch ? `/admin/invoices?invoice_code=${invMatch[1]}` : '/admin/invoices'
                        } else {
                          link = hdMatch ? `/admin/contracts?contract_code=${hdMatch[1]}` : '/admin/contracts'
                        }
                        navigate(link);
                      }}
                      className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl border border-[#3d2a18]/10 bg-white hover:border-[#f3c56b]/40 cursor-pointer hover:bg-stone-50/50 transition"
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
                            onClick={(e) => {
                              e.stopPropagation();
                              openEditForm(notif);
                            }}
                            className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#3d2a18]/10 active:scale-95"
                            title="Sửa bản nháp"
                            aria-label={`Sửa bản nháp thông báo ${notif.title}`}
                          >
                            <Edit3 className="h-5 w-5" />
                          </button>
                        )}
                        <button 
                          type="button" 
                          onClick={(e) => {
                            e.stopPropagation();
                            void handleDelete(notif.id);
                          }}
                          className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-95"
                          title="Xóa thông báo"
                          aria-label={`Xóa thông báo ${notif.title}`}
                        >
                          <Trash2 className="h-5 w-5" />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
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
        </div>
      </section>
    </>
    <ConfirmModal {...confirmState} onCancel={closeConfirm} isLoading={isConfirmLoading} />
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
