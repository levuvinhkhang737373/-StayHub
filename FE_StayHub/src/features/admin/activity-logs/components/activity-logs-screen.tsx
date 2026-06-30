import { Fragment, useCallback, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { ChevronLeft, ChevronRight, Clock3, Eye, History, Network, Search, ShieldCheck, UserRound, X } from 'lucide-react'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatDateTime } from '../../../../shared/lib/utils/format'
import { AdminDateInput } from '../../../../shared/components/AdminDateInput'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { fetchAdminActivityLogDetail, fetchAdminActivityLogs } from '../services/activity-logs.service'
import type { AdminActivityLogChangeItem, AdminActivityLogDisplayField, AdminActivityLogPaginationMeta, AdminActivityLogResource } from '../types/activity-log-api.model'

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'

const perPageOptions = [
  { value: 10, label: '10 dòng', tone: 'default' as const },
  { value: 20, label: '20 dòng', tone: 'default' as const },
  { value: 50, label: '50 dòng', tone: 'default' as const },
  { value: 100, label: '100 dòng', tone: 'default' as const },
]

const actionLabels: Record<string, string> = {
  add_deposit_transaction: 'Thêm giao dịch tiền cọc',
  analyze_meter_image: 'Phân tích ảnh chỉ số điện nước',
  assign_maintenance_staff: 'Phân công nhân sự bảo trì',
  bulk_generate_invoice_queued: 'Xếp hàng tạo hóa đơn hàng loạt',
  cancel_expense: 'Hủy phiếu chi',
  cancel_invoice: 'Hủy hóa đơn',
  change_password: 'Đổi mật khẩu',
  confirm_invoice_payment: 'Xác nhận thanh toán hóa đơn',
  create_admin_account: 'Tạo tài khoản admin',
  create_asset_template: 'Tạo mẫu tài sản',
  create_building: 'Tạo tòa nhà',
  create_contract: 'Tạo hợp đồng',
  create_expense: 'Tạo phiếu chi',
  create_expense_category: 'Tạo danh mục chi phí',
  create_meter_device: 'Tạo đồng hồ điện nước',
  create_notification: 'Tạo thông báo',
  create_region: 'Tạo khu vực',
  create_room: 'Tạo phòng',
  create_room_type: 'Tạo loại phòng',
  create_service: 'Tạo dịch vụ',
  create_setting: 'Tạo cài đặt',
  create_tenant: 'Tạo khách thuê',
  create_vehicle: 'Tạo phương tiện',
  delete_admin_account: 'Xóa tài khoản admin',
  delete_asset_template: 'Xóa mẫu tài sản',
  delete_building: 'Xóa tòa nhà',
  delete_contract: 'Xóa hợp đồng',
  delete_expense_category: 'Xóa danh mục chi phí',
  delete_faceid: 'Xóa FaceID',
  delete_meter_device: 'Xóa đồng hồ điện nước',
  delete_notification: 'Xóa thông báo',
  delete_region: 'Xóa khu vực',
  delete_room: 'Xóa phòng',
  delete_room_type: 'Xóa loại phòng',
  delete_service: 'Xóa dịch vụ',
  delete_setting: 'Xóa cài đặt',
  delete_tenant: 'Xóa khách thuê',
  delete_vehicle: 'Xóa phương tiện',
  face_login_success: 'Đăng nhập bằng FaceID thành công',
  generate_and_issue_invoice: 'Tạo và phát hành hóa đơn',
  login_success: 'Đăng nhập thành công',
  logout: 'Đăng xuất',
  record_invoice_payment: 'Ghi nhận thanh toán hóa đơn',
  register_faceid: 'Đăng ký FaceID',
  renew_contract: 'Gia hạn hợp đồng',
  save_meter_reading: 'Lưu chỉ số điện nước',
  seed_create: 'Tạo dữ liệu mẫu',
  transfer_room: 'Chuyển phòng',
  update_admin_account: 'Cập nhật tài khoản admin',
  update_admin_account_status: 'Cập nhật trạng thái tài khoản admin',
  update_asset_template: 'Cập nhật mẫu tài sản',
  update_asset_template_status: 'Cập nhật trạng thái mẫu tài sản',
  update_building: 'Cập nhật tòa nhà',
  update_building_status: 'Cập nhật trạng thái tòa nhà',
  update_contract: 'Cập nhật hợp đồng',
  update_contract_status: 'Cập nhật trạng thái hợp đồng',
  update_expense: 'Cập nhật phiếu chi',
  update_expense_category: 'Cập nhật danh mục chi phí',
  update_expense_category_status: 'Cập nhật trạng thái danh mục chi phí',
  update_invoice: 'Cập nhật hóa đơn',
  update_maintenance_status: 'Cập nhật trạng thái bảo trì',
  update_meter_device: 'Cập nhật đồng hồ điện nước',
  update_meter_device_status: 'Cập nhật trạng thái đồng hồ điện nước',
  update_notification: 'Cập nhật thông báo',
  update_profile: 'Cập nhật hồ sơ',
  update_region: 'Cập nhật khu vực',
  update_region_status: 'Cập nhật trạng thái khu vực',
  update_room: 'Cập nhật phòng',
  update_room_status: 'Cập nhật trạng thái phòng',
  update_room_type: 'Cập nhật loại phòng',
  update_room_type_status: 'Cập nhật trạng thái loại phòng',
  update_service: 'Cập nhật dịch vụ',
  update_service_status: 'Cập nhật trạng thái dịch vụ',
  update_setting: 'Cập nhật cài đặt',
  update_setting_public: 'Cập nhật hiển thị cài đặt',
  update_tenant: 'Cập nhật khách thuê',
  update_tenant_status: 'Cập nhật trạng thái khách thuê',
  update_utility_prices: 'Cập nhật giá điện nước',
  update_vehicle: 'Cập nhật phương tiện',
  update_vehicle_status: 'Cập nhật trạng thái phương tiện',
}

const entityLabels: Record<string, string> = {
  Admin: 'Admin',
  AssetTemplate: 'Mẫu tài sản',
  Building: 'Tòa nhà',
  Contract: 'Hợp đồng',
  Expense: 'Phiếu chi',
  ExpenseCategory: 'Danh mục chi phí',
  Invoice: 'Hóa đơn',
  MaintenanceRequest: 'Bảo trì',
  MeterDevice: 'Đồng hồ',
  MeterReading: 'Chỉ số điện nước',
  Notification: 'Thông báo',
  Payment: 'Thanh toán',
  Region: 'Khu vực',
  Room: 'Phòng',
  RoomType: 'Loại phòng',
  Service: 'Dịch vụ',
  Setting: 'Cài đặt',
  Tenant: 'Khách thuê',
  Vehicle: 'Phương tiện',
}

export function ActivityLogsScreen() {
  const [keyword, setKeyword] = useState('')
  const [actionFilter, setActionFilter] = useState('')
  const [entityTypeFilter, setEntityTypeFilter] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [perPage, setPerPage] = useState(10)
  const [currentPage, setCurrentPage] = useState(1)
  const [paginationMeta, setPaginationMeta] = useState<AdminActivityLogPaginationMeta | null>(null)
  const [logs, setLogs] = useState<AdminActivityLogResource[]>([])
  const [selectedLog, setSelectedLog] = useState<AdminActivityLogResource | null>(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isLoading, setIsLoading] = useState(true)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null)

  const loadLogs = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)

    try {
      const response = await fetchAdminActivityLogs({
        keyword: keyword.trim() || undefined,
        action: actionFilter.trim() || undefined,
        entity_type: entityTypeFilter.trim() || undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
        page: currentPage,
        per_page: perPage,
      })

      setLogs(response.result?.data || [])
      setPaginationMeta(response.result?.meta || null)

      if (response.result?.meta?.last_page && currentPage > response.result.meta.last_page) {
        setCurrentPage(response.result.meta.last_page)
      }
    } catch (error) {
      setLogs([])
      setPaginationMeta(null)
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải nhật ký thao tác admin.'))
    } finally {
      setIsLoading(false)
    }
  }, [actionFilter, currentPage, dateFrom, dateTo, entityTypeFilter, keyword, perPage])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadLogs()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [loadLogs])

  useEffect(() => {
    if (!isDetailOpen) return

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') closeDetail()
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [isDetailOpen])

  const safeCurrentPage = Math.max(1, Math.min(currentPage, paginationMeta?.last_page ?? currentPage))
  const totalPages = Math.max(1, paginationMeta?.last_page ?? (logs.length >= perPage ? currentPage + 1 : currentPage))
  const paginationStart = paginationMeta?.from ?? (logs.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + 1)
  const paginationEnd = paginationMeta?.to ?? (logs.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + logs.length)
  const totalLogs = paginationMeta?.total ?? (safeCurrentPage - 1) * perPage + logs.length
  const visiblePages = useMemo(() => {
    const pages = new Set<number>([1, totalPages, safeCurrentPage - 1, safeCurrentPage, safeCurrentPage + 1])
    return Array.from(pages)
      .filter((page) => page >= 1 && page <= totalPages)
      .sort((a, b) => a - b)
  }, [safeCurrentPage, totalPages])
  const activeAdmins = useMemo(() => new Set(logs.map((log) => getAdminDisplayName(log)).filter((name) => name !== 'Admin không còn tồn tại')).size, [logs])
  const changedFieldCount = useMemo(() => logs.reduce((total, log) => total + (log.changed_fields?.length || 0), 0), [logs])
  const networkCount = useMemo(() => logs.filter((log) => Boolean(log.ip_address)).length, [logs])
  const hasActiveFilters = Boolean(keyword || actionFilter || entityTypeFilter || dateFrom || dateTo)

  function updateFilter(setter: (value: string) => void, value: string) {
    setter(value)
    setCurrentPage(1)
  }

  function clearFilters() {
    setKeyword('')
    setActionFilter('')
    setEntityTypeFilter('')
    setDateFrom('')
    setDateTo('')
    setCurrentPage(1)
  }

  function changePage(page: number) {
    setCurrentPage(Math.min(Math.max(1, page), totalPages))
  }

  function changePerPage(nextValue: string | number) {
    setPerPage(Number(nextValue))
    setCurrentPage(1)
  }

  async function openDetail(log: AdminActivityLogResource) {
    setSelectedLog(log)
    setIsDetailOpen(true)
    setIsDetailLoading(true)
    setDetailErrorMessage(null)

    try {
      const response = await fetchAdminActivityLogDetail(log.id)
      setSelectedLog(response.result)
    } catch (error) {
      setDetailErrorMessage(getVisibleErrorMessage(error, 'Không thể tải chi tiết nhật ký.'))
    } finally {
      setIsDetailLoading(false)
    }
  }

  function closeDetail() {
    setIsDetailOpen(false)
    setDetailErrorMessage(null)
  }

  return (
    <>
      <section className="space-y-5 text-[#24170d] sm:space-y-6">
        <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
          <div className="relative p-5 text-[#fff4df] sm:p-6 lg:p-7">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_16%_12%,rgba(243,197,107,0.30),transparent_31%),radial-gradient(circle_at_78%_10%,rgba(15,118,110,0.30),transparent_34%),linear-gradient(135deg,#24170d_0%,#4a2b14_48%,#0f3f3b_100%)]" />
            <div className="pointer-events-none absolute inset-x-6 bottom-0 h-px bg-gradient-to-r from-transparent via-[#f3c56b]/45 to-transparent" />

            <div className="relative flex min-w-0 flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
              <div className="min-w-0">
                <span className="block text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]/80">HỆ THỐNG</span>
                <h1 className="mt-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem] flex items-center gap-3">
                  <History className="h-8 w-8 text-[#f3c56b] shrink-0" />
                  Nhật ký admin
                </h1>
                <p className="mt-2.5 text-xs font-semibold text-[#f8e8c8]/70">Ghi vết lịch sử thao tác của các tài khoản quản trị viên trên hệ thống.</p>
              </div>

            </div>

            <div className="relative mt-6 grid grid-cols-[repeat(auto-fit,minmax(14rem,1fr))] gap-3">
              <MetricCard icon={<History className="h-4 w-4" />} label="Tổng log" value={totalLogs} tone="amber" />
              <MetricCard icon={<UserRound className="h-4 w-4" />} label="Admin/trang" value={activeAdmins} tone="neutral" />
              <MetricCard icon={<ShieldCheck className="h-4 w-4" />} label="Field đổi" value={changedFieldCount} tone="emerald" />
              <MetricCard icon={<Network className="h-4 w-4" />} label="Có IP" value={networkCount} tone="neutral" />
            </div>
          </div>
        </section>

        {errorMessage && <div className="rounded-3xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-black text-rose-700 shadow-sm">{errorMessage}</div>}

        <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
          <div className="border-b border-[#3d2a18]/10 bg-[#fff8eb]/85 p-4 sm:p-5">
            <div className="grid gap-3 lg:grid-cols-[minmax(18rem,1.15fr)_repeat(2,minmax(10rem,0.75fr))]">
              <div className="relative min-w-0">
                <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
                <input type="text" value={keyword} onChange={(event) => updateFilter(setKeyword, event.target.value)} className={`${inputClass} pl-11 pr-28`} placeholder="Tìm admin, đối tượng, hành động, IP..." />
                <button type="button" onClick={clearFilters} disabled={!hasActiveFilters} className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20 disabled:cursor-not-allowed disabled:opacity-45">
                  <X className="h-3.5 w-3.5" /> Xóa lọc
                </button>
              </div>
              <input value={actionFilter} onChange={(event) => updateFilter(setActionFilter, event.target.value)} className={inputClass} placeholder="Tên hành động" />
              <input value={entityTypeFilter} onChange={(event) => updateFilter(setEntityTypeFilter, event.target.value)} className={inputClass} placeholder="Loại đối tượng" />
            </div>
            <div className="mt-3 grid gap-3 lg:grid-cols-[minmax(10rem,14rem)_minmax(10rem,14rem)_1fr]">
              <AdminDateInput value={dateFrom} onChange={(value) => updateFilter(setDateFrom, value)} placeholder="Từ ngày" className={inputClass} />
              <AdminDateInput value={dateTo} onChange={(value) => updateFilter(setDateTo, value)} placeholder="Đến ngày" className={inputClass} />
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full min-w-[1080px] text-left text-sm">
              <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
                <tr>
                  <th className="px-4 py-3">Thời gian</th>
                  <th className="px-4 py-3">Admin</th>
                  <th className="px-4 py-3 text-center">Hành động</th>
                  <th className="px-4 py-3 text-center">Đối tượng</th>
                  <th className="px-4 py-3 text-center">IP</th>
                  <th className="px-4 py-3">Thiết bị</th>
                  <th className="px-4 py-3 w-[45px]"><div className="flex justify-end"><div className="w-[45px] text-center">Thao tác</div></div></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[#3d2a18]/8 bg-white/45">
                {isLoading && <tr><td colSpan={7} className="px-4 py-10 text-center text-sm font-black text-[#8b5e34]">Đang tải nhật ký thao tác admin...</td></tr>}
                {!isLoading && logs.length === 0 && <tr><td colSpan={7} className="px-4 py-10 text-center text-sm font-black text-[#8b5e34]">Chưa có nhật ký phù hợp.</td></tr>}
                {!isLoading && logs.map((log) => <ActivityLogRow key={log.id} log={log} onView={() => void openDetail(log)} />)}
              </tbody>
            </table>
          </div>

          <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb]/85 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
            <p className="text-xs font-black text-[#6f6254]">Hiển thị <span className="tabular-nums text-[#24170d]">{paginationStart}</span>-<span className="tabular-nums text-[#24170d]">{paginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{totalLogs}</span> nhật ký</p>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
              <div className="w-full sm:w-36">
                <AdminSelect value={perPage} options={perPageOptions} onChange={changePerPage} menuPlacement="top" />
              </div>
              <div className="flex items-center justify-end gap-1.5">
                <button type="button" disabled={safeCurrentPage <= 1 || isLoading} onClick={() => changePage(currentPage - 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang trước">
                  <ChevronLeft className="h-4 w-4" />
                </button>
                {visiblePages.map((page, index) => {
                  const previousPage = visiblePages[index - 1]
                  const hasGap = previousPage && page - previousPage > 1

                  return (
                    <Fragment key={page}>
                      {hasGap && <span className="px-1 text-xs font-black text-[#8b5e34]/60">...</span>}
                      <button type="button" onClick={() => changePage(page)} className={cn('inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition', page === safeCurrentPage ? 'border-[#24170d] bg-[#24170d] text-[#fff4df] shadow-sm' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15')}>
                        {page}
                      </button>
                    </Fragment>
                  )
                })}
                <button type="button" disabled={safeCurrentPage >= totalPages || isLoading} onClick={() => changePage(currentPage + 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang sau">
                  <ChevronRight className="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>
        </section>
      </section>

      {isDetailOpen && selectedLog && <DetailDrawer log={selectedLog} isLoading={isDetailLoading} errorMessage={detailErrorMessage} onClose={closeDetail} />}
    </>
  )
}

function ActivityLogRow({ log, onView }: { log: AdminActivityLogResource; onView: () => void }) {
  const entityLabel = log.entity_type_label || getEntityLabel(log.entity_type)
  const entityName = getEntityDisplayName(log)
  const adminName = getAdminDisplayName(log)
  const adminSubText = log.admin?.role_label || log.admin?.email || log.admin?.username || 'Không còn tài khoản'

  return (
    <tr className="align-middle transition hover:bg-[#fff8eb]">
      <td className="px-4 py-4 tabular-nums"><span className="inline-flex items-center gap-2 font-black text-[#24170d]"><Clock3 className="h-4 w-4 text-[#a65f16]" />{formatDateTime(log.created_at)}</span></td>
      <td className="px-4 py-4"><p className="font-black text-[#24170d]">{adminName}</p><p className="mt-1 text-xs font-bold text-[#8b5e34]">{adminSubText}</p></td>
      <td className="px-4 py-4 text-center"><ActionBadge action={log.action} /></td>
      <td className="px-4 py-4 text-center"><p className="font-black text-[#24170d]">{entityName}</p><p className="mt-1 text-xs font-bold text-[#8b5e34]">{entityLabel}</p></td>
      <td className="px-4 py-4 text-center font-black tabular-nums text-[#24170d]">{log.ip_address || '—'}</td>
      <td className="max-w-[18rem] truncate px-4 py-4 text-xs font-bold text-[#6f6254]" title={log.user_agent || undefined}>{log.user_agent || '—'}</td>
      <td className="px-4 py-4">
        <div className="flex justify-end gap-2">
          <IconButton title="Xem chi tiết" onClick={onView}><Eye className="h-4 w-4" /></IconButton>
        </div>
      </td>
    </tr>
  )
}

function DetailDrawer({ log, isLoading, errorMessage, onClose }: { log: AdminActivityLogResource; isLoading: boolean; errorMessage: string | null; onClose: () => void }) {
  const entityLabel = log.entity_type_label || getEntityLabel(log.entity_type)
  const entityName = getEntityDisplayName(log)
  const adminName = getAdminDisplayName(log)
  const adminRole = log.admin?.role_label || 'Không rõ vai trò'
  const oldFields = getDisplayFields(log.old_data_display, log.old_data)
  const newFields = getDisplayFields(log.new_data_display, log.new_data)
  const changeItems = getChangeItems(log)
  const changedFieldLabels = getChangedFieldLabels(log, changeItems)

  return (
    <div className="fixed inset-0 z-[120] flex items-center justify-center bg-[#24170d]/55 p-3 backdrop-blur-sm sm:p-6" role="dialog" aria-modal="true">
      <button type="button" className="absolute inset-0 cursor-default" aria-label="Đóng chi tiết nhật ký" onClick={onClose} />
      <aside className="relative flex max-h-[calc(100vh-1.5rem)] w-full max-w-5xl flex-col overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-[#24170d]/30 sm:max-h-[calc(100vh-3rem)]">
        <header className="relative overflow-hidden bg-[#24170d] p-5 text-[#fff4df]">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_0%,rgba(243,197,107,0.26),transparent_30%),radial-gradient(circle_at_86%_12%,rgba(15,118,110,0.24),transparent_34%)]" />
          <div className="relative flex items-start justify-between gap-4">
            <div>
              <p className="text-[10px] font-black uppercase tracking-[0.22em] text-[#f3c56b]">Chi tiết nhật ký</p>
              <h2 className="mt-2 text-2xl font-black tracking-[-0.04em]">{getActionLabel(log.action)}</h2>
              <p className="mt-1 text-sm font-semibold text-[#f8e8c8]/70">{formatDateTime(log.created_at)} · {adminName} · {entityName}</p>
            </div>
            <button type="button" onClick={onClose} className="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/10 text-[#fff4df] transition hover:bg-white/18 focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20" aria-label="Đóng chi tiết">
              <X className="h-5 w-5" />
            </button>
          </div>
        </header>

        <div className="custom-scrollbar flex-1 overflow-y-auto p-4 sm:p-5">
          {isLoading && <p className="rounded-2xl border border-[#3d2a18]/10 bg-white/70 px-4 py-3 text-sm font-black text-[#8b5e34]">Đang tải chi tiết...</p>}
          {errorMessage && <p className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-black text-rose-700">{errorMessage}</p>}
          <div className="grid gap-3 md:grid-cols-3">
            <DetailTile label="Admin thao tác" value={`${adminName} · ${adminRole}`} />
            <DetailTile label="IP" value={log.ip_address || '—'} />
            <DetailTile label="Đối tượng" value={`${entityName} · ${entityLabel}`} />
          </div>
          <div className="mt-4 rounded-3xl border border-[#3d2a18]/10 bg-white/65 p-4">
            <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Field thay đổi</p>
            <div className="mt-3 flex flex-wrap gap-2">
              {changedFieldLabels.length > 0 ? changedFieldLabels.map((field) => <span key={field} className="rounded-full border border-[#0f766e]/15 bg-[#0f766e]/10 px-3 py-1 text-xs font-black text-[#0f5f59]">{field}</span>) : <span className="text-sm font-bold text-[#8b5e34]">Không có trường thay đổi.</span>}
            </div>
          </div>
          <ChangeSummaryPanel items={changeItems} />
          <div className="mt-4 grid gap-4 lg:grid-cols-2">
            <ReadableDataBlock title="Dữ liệu trước" fields={oldFields} tone="old" />
            <ReadableDataBlock title="Dữ liệu sau" fields={newFields} tone="new" />
          </div>
          <div className="mt-4 rounded-3xl border border-[#3d2a18]/10 bg-[#fff8eb] p-4">
            <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">User agent</p>
            <p className="mt-2 break-words text-sm font-bold text-[#3d2a18]">{log.user_agent || '—'}</p>
          </div>
        </div>
      </aside>
    </div>
  )
}

function MetricCard({ icon, label, value, tone = 'neutral' }: { icon: ReactNode; label: string; value: ReactNode; tone?: 'neutral' | 'emerald' | 'rose' | 'amber' }) {
  const toneClasses = {
    neutral: 'bg-white/10 text-[#fff4df]',
    emerald: 'bg-emerald-300/12 text-emerald-100',
    rose: 'bg-rose-300/12 text-rose-100',
    amber: 'bg-[#f3c56b]/14 text-[#ffe2a3]',
  }

  return (
    <div className={cn('flex h-full min-h-[6.75rem] min-w-0 flex-col rounded-[1.45rem] border border-white/12 p-4 shadow-lg shadow-black/5 backdrop-blur-sm transition duration-200 hover:-translate-y-0.5 hover:bg-white/12', toneClasses[tone])}>
      <div className="flex min-w-0 items-center gap-3">
        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-white/12 text-[#f3c56b]">{icon}</div>
        <p className="min-w-0 whitespace-nowrap text-[10px] font-black uppercase leading-none tracking-[0.14em] opacity-75">{label}</p>
      </div>
      <p className="mt-5 min-w-0 whitespace-nowrap text-[clamp(1.35rem,1.5vw,1.6rem)] font-black leading-none tabular-nums tracking-[-0.04em]">{value}</p>
    </div>
  )
}

function IconButton({ children, onClick, title, disabled }: { children: ReactNode; onClick: () => void; title: string; disabled?: boolean }) {
  return (
    <button
      type="button"
      title={title}
      onClick={onClick}
      disabled={disabled}
      className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45"
    >
      {children}
    </button>
  )
}

function ActionBadge({ action }: { action: string }) {
  return <span className="inline-flex items-center rounded-full border border-[#f3c56b]/30 bg-[#f3c56b]/18 px-3 py-1 text-xs font-black text-[#8a4f18]">{getActionLabel(action)}</span>
}

function DetailTile({ label, value }: { label: string; value: string | number }) {
  return <div className="rounded-3xl border border-[#3d2a18]/10 bg-white/70 p-4"><p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">{label}</p><p className="mt-2 break-words text-sm font-black text-[#24170d]">{value}</p></div>
}

function ChangeSummaryPanel({ items }: { items: AdminActivityLogChangeItem[] }) {
  return (
    <section className="mt-4 overflow-hidden rounded-3xl border border-[#3d2a18]/10 bg-[#24170d] shadow-xl shadow-[#24170d]/12">
      <header className="relative border-b border-white/10 px-4 py-4 text-[#fff4df]">
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_16%_0%,rgba(243,197,107,0.22),transparent_32%),radial-gradient(circle_at_84%_0%,rgba(15,118,110,0.2),transparent_28%)]" />
        <div className="relative flex items-center justify-between gap-3">
          <div>
            <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#f3c56b]">Bản tóm tắt thay đổi</p>
          </div>
          <span className="rounded-full border border-[#f3c56b]/25 bg-[#f3c56b]/12 px-3 py-1 text-xs font-black text-[#f3c56b]">{items.length} mục</span>
        </div>
      </header>
      {items.length > 0 ? (
        <div className="grid gap-3 p-4 lg:grid-cols-2">
          {items.map((item) => <ChangeSummaryCard key={item.key} item={item} />)}
        </div>
      ) : (
        <div className="p-4 text-sm font-bold text-[#fff4df]/72">Không có dữ liệu thay đổi để hiển thị.</div>
      )}
    </section>
  )
}

function ChangeSummaryCard({ item }: { item: AdminActivityLogChangeItem }) {
  return (
    <article className="rounded-3xl border border-white/10 bg-[#fffaf1] p-4 text-[#24170d] shadow-lg shadow-black/10 transition hover:-translate-y-0.5 hover:shadow-xl">
      <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/65">{item.label}</p>
      <div className="mt-3 grid gap-3 sm:grid-cols-[1fr_auto_1fr] sm:items-stretch">
        <ValuePill label="Trước" value={item.old_value} tone="old" />
        <div className="hidden items-center justify-center text-[#8b5e34] sm:flex">→</div>
        <ValuePill label="Sau" value={item.new_value} tone="new" />
      </div>
    </article>
  )
}

function ValuePill({ label, value, tone }: { label: string; value: string; tone: 'old' | 'new' }) {
  const toneClassName = tone === 'old'
    ? 'border-rose-200/70 bg-rose-50 text-rose-900'
    : 'border-emerald-200/80 bg-emerald-50 text-emerald-900'

  return <div className={cn('rounded-2xl border px-3 py-3', toneClassName)}><p className="text-[9px] font-black uppercase tracking-[0.18em] opacity-60">{label}</p><p className="mt-1 break-words text-sm font-black leading-5">{value || 'Không có'}</p></div>
}

function ReadableDataBlock({ title, fields, tone }: { title: string; fields: AdminActivityLogDisplayField[]; tone: 'old' | 'new' }) {
  const headerClassName = tone === 'old' ? 'text-rose-900 bg-rose-50' : 'text-emerald-900 bg-emerald-50'

  return (
    <section className="overflow-hidden rounded-3xl border border-[#3d2a18]/10 bg-white/75">
      <header className={cn('border-b border-[#3d2a18]/10 px-4 py-3 text-[10px] font-black uppercase tracking-[0.2em]', headerClassName)}>{title}</header>
      {fields.length > 0 ? (
        <div className="custom-scrollbar grid max-h-96 gap-2 overflow-auto p-3">
          {fields.map((field) => <ReadableField key={field.key} field={field} />)}
        </div>
      ) : (
        <p className="p-4 text-sm font-bold text-[#8b5e34]">Không có dữ liệu.</p>
      )}
    </section>
  )
}

function ReadableField({ field }: { field: AdminActivityLogDisplayField }) {
  return <div className="rounded-2xl border border-[#3d2a18]/10 bg-[#fff8eb] px-3 py-2"><p className="text-[10px] font-black uppercase tracking-[0.16em] text-[#8b5e34]/60">{field.label}</p><p className="mt-1 break-words text-sm font-black text-[#24170d]">{field.value || 'Không có'}</p></div>
}

function getActionLabel(action: string) {
  return actionLabels[action] || action.split('_').filter(Boolean).join(' ')
}

function getEntityLabel(entityType: string) {
  const shortName = entityType.split('\\').pop() || entityType
  return entityLabels[shortName] || shortName
}

function getEntityDisplayName(log: AdminActivityLogResource) {
  if (log.entity_name) return log.entity_name

  const payloadName = getNameFromPayload(log.new_data) || getNameFromPayload(log.old_data)
  if (payloadName) return payloadName

  if ((log.entity_type.split('\\').pop() || log.entity_type) === 'Admin' && log.admin?.full_name) {
    return log.admin.full_name
  }

  return log.entity_type_label || getEntityLabel(log.entity_type)
}

function getAdminDisplayName(log: AdminActivityLogResource) {
  return log.admin_name || log.admin?.display_name || log.admin?.full_name || log.admin?.username || log.admin?.email || 'Admin không còn tồn tại'
}

function getDisplayFields(displayFields?: AdminActivityLogDisplayField[], rawData?: Record<string, unknown> | null) {
  if (displayFields?.length) return displayFields

  return displayFieldsFromRaw(rawData)
}

function getChangeItems(log: AdminActivityLogResource) {
  if (log.change_summary?.length) return log.change_summary

  const oldFields = getDisplayFields(log.old_data_display, log.old_data)
  const newFields = getDisplayFields(log.new_data_display, log.new_data)
  const oldMap = new Map(oldFields.map((field) => [field.key, field]))
  const newMap = new Map(newFields.map((field) => [field.key, field]))
  const keys = Array.from(new Set([...oldMap.keys(), ...newMap.keys()]))

  return keys
    .filter((key) => (oldMap.get(key)?.value || 'Không có') !== (newMap.get(key)?.value || 'Không có'))
    .map((key) => ({
      key,
      label: newMap.get(key)?.label || oldMap.get(key)?.label || 'Thông tin thay đổi',
      old_value: oldMap.get(key)?.value || 'Không có',
      new_value: newMap.get(key)?.value || 'Không có',
    }))
}

function getChangedFieldLabels(log: AdminActivityLogResource, changeItems: AdminActivityLogChangeItem[]) {
  if (log.changed_fields_display?.length) return log.changed_fields_display

  const labels = changeItems.map((item) => item.label)
  if (labels.length) return Array.from(new Set(labels))

  return (log.changed_fields || []).map((field) => fieldLabelFallback(field))
}

function displayFieldsFromRaw(rawData?: Record<string, unknown> | null, parentKey = ''): AdminActivityLogDisplayField[] {
  if (!rawData) return []

  return Object.entries(rawData).flatMap(([key, value]) => {
    const fieldKey = parentKey ? `${parentKey}.${key}` : key
    if (shouldHideRawField(key)) return []

    if (value && typeof value === 'object' && !Array.isArray(value)) {
      return displayFieldsFromRaw(value as Record<string, unknown>, fieldKey)
    }

    return [{ key: fieldKey, label: fieldLabelFallback(fieldKey), value: displayValueFallback(value) }]
  })
}

function shouldHideRawField(key: string) {
  return key === 'id' || key.endsWith('_id') || ['created_by', 'uploaded_by', 'assigned_to', 'collected_by'].includes(key)
}

function fieldLabelFallback(path: string) {
  const labels: Record<string, string> = {
    action: 'Hành động',
    address: 'Địa chỉ',
    amount: 'Số tiền',
    base_price: 'Giá cơ bản',
    billing_month: 'Tháng thanh toán',
    billing_year: 'Năm thanh toán',
    code: 'Mã',
    content: 'Nội dung',
    contract_code: 'Mã hợp đồng',
    current_occupants: 'Số người đang ở',
    description: 'Mô tả',
    email: 'Email',
    full_name: 'Họ tên',
    name: 'Tên',
    note: 'Ghi chú',
    password: 'Mật khẩu',
    phone: 'Số điện thoại',
    profile: 'Hồ sơ',
    reason: 'Lý do',
    role: 'Vai trò',
    room_number: 'Số phòng',
    slug: 'Đường dẫn',
    status: 'Trạng thái',
    title: 'Tiêu đề',
    token: 'Mã xác thực',
    username: 'Tên đăng nhập',
  }

  const parts = path.split('.').filter((part) => Number.isNaN(Number(part)))
  return parts.map((part) => labels[part] || 'Thông tin khác').join(' / ')
}

function displayValueFallback(value: unknown) {
  if (value === null || value === undefined || value === '') return 'Không có'
  if (value === '***') return 'Đã ẩn'
  if (typeof value === 'boolean') return value ? 'Có' : 'Không'
  if (Array.isArray(value)) return `${value.length} mục`

  return String(value)
}

function getNameFromPayload(payload?: Record<string, unknown> | null) {
  if (!payload) return null

  const keys = ['full_name', 'name', 'title', 'room_number', 'contract_code', 'invoice_code', 'expense_code', 'setting_label', 'license_plate', 'username', 'email', 'code', 'slug']

  for (const key of keys) {
    const value = payload[key]
    if ((typeof value === 'string' || typeof value === 'number') && String(value).trim()) {
      return String(value)
    }
  }

  return null
}

function getVisibleErrorMessage(error: unknown, fallback: string) {
  if (error instanceof ApiError && (!error.statusCode || error.statusCode >= 500)) {
    return fallback
  }

  if (error instanceof Error) {
    return error.message
  }

  return fallback
}
