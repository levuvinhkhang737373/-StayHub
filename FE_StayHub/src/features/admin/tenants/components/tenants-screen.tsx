import { useCallback, useEffect, useMemo, useState, type ReactNode, type SyntheticEvent } from 'react'
import { createPortal } from 'react-dom'
import { Link, useNavigate } from 'react-router-dom'
import { Building2, ChevronLeft, ChevronRight, DoorOpen, Edit3, Eye, IdCard, Mail, Phone, Plus, Power, Search, Trash2, UserRound, X, ArrowLeft, ArrowRightLeft } from 'lucide-react'
import { formatDate, formatDateTime } from '../../../../shared/lib/utils/format'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { AdminSelect } from '../../shared/components/AdminSelect'
import {
  deleteAdminTenant,
  fetchAdminTenantDetail,
  fetchAdminTenants,
  updateAdminTenantStatus,
} from '../services/tenants.service'
import type { AdminPaginationMeta, AdminPaginator, AdminTenantResource } from '../types/tenant-api.model'
import { useAdminSession } from '../../auth/hooks/use-admin-session'

type AdminTenantsResult = AdminPaginator<AdminTenantResource> | AdminTenantResource[]
type AdminTenantsResponse = Omit<Awaited<ReturnType<typeof fetchAdminTenants>>, 'result'> & {
  result?: AdminTenantsResult | null
  data?: AdminTenantsResult | null
}

function normalizeAdminTenantsResponse(response: Awaited<ReturnType<typeof fetchAdminTenants>>) {
  const envelope = response as AdminTenantsResponse
  const result = envelope.result ?? envelope.data

  if (!result) {
    return { data: [] as AdminTenantResource[], meta: null as AdminPaginationMeta | null }
  }

  if (Array.isArray(result)) {
    return { data: result, meta: null }
  }

  return { data: result.data || [], meta: result.meta || null }
}

const STATUS_RENTING = 1
const STATUS_STOPPED_RENTING = 2
const DEFAULT_AVATAR_URL = '/images/avatar.jpg'
const GENDER_MALE = 1
const GENDER_FEMALE = 2
const IDENTITY_TYPE_CCCD = 1
const IDENTITY_TYPE_CMND = 2
const IDENTITY_TYPE_PASSPORT = 3

const statusOptions = [
  { value: '', label: 'Tất cả trạng thái', tone: 'default' as const },
  { value: STATUS_RENTING, label: 'Đang thuê', tone: 'success' as const },
  { value: STATUS_STOPPED_RENTING, label: 'Ngừng thuê', tone: 'danger' as const },
]

const genderOptions = [
  { value: '', label: 'Tất cả giới tính', tone: 'default' as const },
  { value: GENDER_MALE, label: 'Nam', tone: 'default' as const },
  { value: GENDER_FEMALE, label: 'Nữ', tone: 'default' as const },
]

const identityTypeOptions = [
  { value: '', label: 'Tất cả giấy tờ', tone: 'default' as const },
  { value: IDENTITY_TYPE_CCCD, label: 'CCCD', tone: 'success' as const },
  { value: IDENTITY_TYPE_PASSPORT, label: 'Hộ chiếu', tone: 'default' as const },
]

const perPageOptions = [
  { value: 5, label: '5 dòng', tone: 'default' as const },
  { value: 10, label: '10 dòng', tone: 'default' as const },
  { value: 20, label: '20 dòng', tone: 'default' as const },
  { value: 50, label: '50 dòng', tone: 'default' as const },
]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'

export function TenantsScreen() {
  const { session } = useAdminSession()
  const navigate = useNavigate()
  const defaultBuildingId = session?.admin?.managed_buildings?.[0]?.id

  const [keyword, setKeyword] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [selectedGender, setSelectedGender] = useState('')
  const [selectedIdentityType, setSelectedIdentityType] = useState('')
  const [perPage, setPerPage] = useState(10)
  const [currentPage, setCurrentPage] = useState(1)
  const [paginationMeta, setPaginationMeta] = useState<AdminPaginationMeta | null>(null)
  const [tenants, setTenants] = useState<AdminTenantResource[]>([])
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [detailTenant, setDetailTenant] = useState<AdminTenantResource | null>(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [statusChangingId, setStatusChangingId] = useState<number | null>(null)
  const [deletingId, setDeletingId] = useState<number | null>(null)

  const loadTenants = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)

    try {
      const response = await fetchAdminTenants({
        keyword: keyword.trim() || undefined,
        status: selectedStatus === '' ? undefined : Number(selectedStatus),
        gender: selectedGender === '' ? undefined : Number(selectedGender),
        identity_type: selectedIdentityType === '' ? undefined : Number(selectedIdentityType),
        building_id: defaultBuildingId,
        page: currentPage,
        per_page: perPage,
      })

      const { data, meta } = normalizeAdminTenantsResponse(response)
      setTenants(data)
      setPaginationMeta(meta)

      if (meta?.last_page && currentPage > meta.last_page) {
        setCurrentPage(meta.last_page)
      }
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải danh sách khách thuê.'))
    } finally {
      setIsLoading(false)
    }
  }, [currentPage, defaultBuildingId, keyword, perPage, selectedGender, selectedIdentityType, selectedStatus])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadTenants()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [loadTenants])

  const rentingTenants = useMemo(() => tenants.filter((item) => isTenantRenting(item)).length, [tenants])
  const stoppedTenants = useMemo(() => tenants.filter((item) => !isTenantRenting(item)).length, [tenants])
  const verifiedTenants = useMemo(() => tenants.filter((item) => item.identity_verified || Boolean(item.identity_number)).length, [tenants])
  const hasNextPageFallback = !paginationMeta && tenants.length >= perPage
  const totalPages = Math.max(1, paginationMeta?.last_page ?? (hasNextPageFallback ? currentPage + 1 : currentPage))
  const safeCurrentPage = Math.min(currentPage, totalPages)
  const totalTenants = paginationMeta?.total ?? (safeCurrentPage - 1) * perPage + tenants.length
  const paginationStart = paginationMeta?.from ?? (tenants.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + 1)
  const paginationEnd = paginationMeta?.to ?? (tenants.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + tenants.length)
  const visiblePages = useMemo(() => {
    const pages = new Set<number>([1, totalPages, safeCurrentPage - 1, safeCurrentPage, safeCurrentPage + 1])
    return Array.from(pages)
      .filter((page) => page >= 1 && page <= totalPages)
      .sort((a, b) => a - b)
  }, [safeCurrentPage, totalPages])
  const hasActiveFilters = Boolean(keyword.trim() || selectedStatus || selectedGender || selectedIdentityType)
  const openCreateForm = () => {
    navigate('/admin/tenants/create')
  }

  const editTenant = (tenant: AdminTenantResource) => {
    navigate(`/admin/tenants/${tenant.id}/edit`)
  }

  const transferTenantRoom = (tenant: AdminTenantResource) => {
    navigate(`/admin/transfer-room?tenantId=${tenant.id}`)
  }
  const viewTenant = async (tenant: AdminTenantResource) => {
    setDetailTenant(tenant)
    setIsDetailOpen(true)
    setIsDetailLoading(true)
    setDetailErrorMessage(null)

    try {
      const response = await fetchAdminTenantDetail(tenant.id)
      setDetailTenant(response.result)
    } catch (error) {
      setDetailErrorMessage(getVisibleErrorMessage(error, 'Không thể tải chi tiết khách thuê.'))
    } finally {
      setIsDetailLoading(false)
    }
  }

  const closeDetail = () => {
    setIsDetailOpen(false)
    setDetailTenant(null)
    setDetailErrorMessage(null)
  }

  useEffect(() => {
    if (!isDetailOpen) return

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        closeDetail()
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [isDetailOpen])

  const toggleTenantStatus = async (tenant: AdminTenantResource) => {
    const nextStatus = isTenantRenting(tenant) ? STATUS_STOPPED_RENTING : STATUS_RENTING

    if (nextStatus === STATUS_STOPPED_RENTING && !window.confirm(`Bạn có chắc muốn chuyển khách thuê ${tenant.full_name || tenant.username} sang ngừng thuê?`)) return

    try {
      setStatusChangingId(tenant.id)
      setErrorMessage(null)
      setSuccessMessage(null)
      await updateAdminTenantStatus(tenant.id, {
        status: nextStatus,
        reason: nextStatus === STATUS_STOPPED_RENTING ? 'Ngừng thuê từ màn quản lý khách thuê' : 'Kích hoạt thuê lại từ màn quản lý khách thuê',
      })
      setSuccessMessage(`${nextStatus === STATUS_RENTING ? 'Kích hoạt thuê lại' : 'Ngừng thuê'} khách thuê thành công.`)
      await loadTenants()
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể đổi trạng thái khách thuê.'))
    } finally {
      setStatusChangingId(null)
    }
  }

  const removeTenant = async (tenant: AdminTenantResource) => {
    if (!window.confirm(`Bạn có chắc chắn muốn xóa khách thuê ${tenant.full_name || tenant.username}? Chỉ khách thuê đã ngừng thuê và chưa phát sinh dữ liệu liên quan mới có thể xóa.`)) return

    try {
      setDeletingId(tenant.id)
      setErrorMessage(null)
      await deleteAdminTenant(tenant.id)
      setSuccessMessage('Xóa khách thuê thành công.')
      if (tenants.length === 1 && currentPage > 1) {
        setCurrentPage((page) => Math.max(1, page - 1))
      } else {
        await loadTenants()
      }
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể xóa khách thuê.'))
    } finally {
      setDeletingId(null)
    }
  }

  const clearFilters = () => {
    setKeyword('')
    setSelectedStatus('')
    setSelectedGender('')
    setSelectedIdentityType('')
    setCurrentPage(1)
  }

  const changePage = (page: number) => {
    setCurrentPage(Math.min(Math.max(1, page), totalPages))
  }

  const changePerPage = (nextValue: string | number) => {
    setPerPage(Number(nextValue))
    setCurrentPage(1)
  }

  return (
    <>
      <>
        <section className="space-y-5 sm:space-y-6 text-[#24170d]">
          <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
            <div className="relative p-4 text-[#fff4df] sm:p-5 lg:p-6">
              <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_14%,rgba(243,197,107,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(15,118,110,0.26),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
              <div className="pointer-events-none absolute inset-x-6 bottom-0 h-px bg-gradient-to-r from-transparent via-[#f3c56b]/40 to-transparent" />

              <div className="relative flex min-w-0 flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div className="min-w-0">
                  <Link to="/admin/dashboard" className="mb-2 inline-flex items-center gap-2 text-xs font-black text-[#f3c56b] transition hover:text-[#ffd56f]">
                    <ArrowLeft className="h-3.5 w-3.5" /> Về dashboard
                  </Link>

                  <h1 className="mt-3 max-w-3xl text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem]">Quản lý khách thuê</h1>
                </div>
                <button type="button" onClick={openCreateForm} className="inline-flex h-9 w-fit self-end lg:self-auto items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition-all hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98] sm:mb-1 shrink-0">
                  <Plus className="h-4 w-4 stroke-[2.8]" /> Thêm khách thuê
                </button>
              </div>

              <div className="relative mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <MetricCard label="Tổng khách thuê" value={totalTenants} tone="neutral" />
                <MetricCard label="Đang thuê/trang" value={rentingTenants} tone="emerald" />
                <MetricCard label="Ngừng thuê/trang" value={stoppedTenants} tone="amber" />
                <MetricCard label="Đủ giấy tờ/trang" value={verifiedTenants} tone="teal" />
              </div>
            </div>
          </section>

          {(errorMessage || successMessage) && (
            <div className={cn('rounded-3xl border px-4 py-3 text-sm font-black shadow-sm', errorMessage ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700')}>
              {errorMessage || successMessage}
            </div>
          )}

          <div className="grid min-w-0 grid-cols-1 gap-4 lg:gap-6">
            <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
              <div className="border-b border-[#3d2a18]/10 bg-[#fff8eb]/85 p-4 sm:p-5">
                <div className="grid gap-3 lg:grid-cols-[minmax(12rem,1fr)_minmax(100px,1fr)_minmax(100px,1fr)_minmax(100px,1fr)]">
                  <div className="relative min-w-0">
                    <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
                    <input type="text" value={keyword} onChange={(event) => { setKeyword(event.target.value); setCurrentPage(1) }} placeholder="Tìm tên, username, email, SĐT hoặc số giấy tờ..." className={`${inputClass} pl-11 pr-28`} />
                    <button type="button" onClick={clearFilters} disabled={!hasActiveFilters} className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20 disabled:cursor-not-allowed disabled:opacity-45">
                      <X className="h-3.5 w-3.5" /> Xóa lọc
                    </button>
                  </div>
                  <AdminSelect value={selectedStatus} options={statusOptions} onChange={(nextValue) => setSelectedStatus(String(nextValue))} />
                  <AdminSelect value={selectedGender} options={genderOptions} onChange={(nextValue) => setSelectedGender(String(nextValue))} />
                  <AdminSelect value={selectedIdentityType} options={identityTypeOptions} onChange={(nextValue) => setSelectedIdentityType(String(nextValue))} />
                </div>
              </div>

              {/* Desktop View Table */}
              <div className="hidden lg:block w-full max-w-full overflow-x-auto custom-scrollbar">
                <table className="w-full text-left">
                  <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
                    <tr>
                      <th className="px-5 py-4">Khách thuê</th>
                      <th className="px-5 py-4">Liên hệ</th>
                      <th className="px-5 py-4 text-center">Giấy tờ</th>
                      <th className="px-5 py-4 text-center">Trạng thái</th>
                      <th className="px-5 py-4 text-center">Tòa nhà</th>
                      <th className="whitespace-nowrap px-5 py-4 text-center">Phòng</th>
                      <th className="px-5 py-4 text-center">Phương tiện</th>
                      <th className="px-5 py-4"><div className="flex justify-end"><div className="w-[184px] text-center">Thao tác</div></div></th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[#3d2a18]/8 bg-[#fffaf1]/70">
                    {isLoading && Array.from({ length: 5 }).map((_, index) => (
                      <tr key={index}>
                        <td colSpan={8} className="px-5 py-4"><div className="h-14 animate-pulse rounded-2xl bg-stone-100" /></td>
                      </tr>
                    ))}

                    {!isLoading && tenants.map((tenant) => {
                      const renting = isTenantRenting(tenant)

                      return (
                        <tr key={tenant.id} className="group transition hover:bg-[#f3c56b]/10">
                          <td className="px-5 py-4">
                            <div className="flex items-center gap-3">
                              <TenantAvatar tenant={tenant} />
                              <div className="min-w-0">
                                <p className="truncate text-sm font-black tracking-tight text-[#24170d]">{tenant.full_name || tenant.username}</p>
                                <p className="mt-0.5 text-xs font-bold text-[#8b5e34]/70">@{tenant.username}</p>
                              </div>
                            </div>
                          </td>
                          <td className="px-5 py-4">
                            <div className="space-y-1 text-xs font-bold text-[#6f6254]">
                              <p className="flex items-center gap-1.5"><Mail className="h-3.5 w-3.5 text-[#a65f16]" /> {tenant.email || 'Chưa có email'}</p>
                              <p className="flex items-center gap-1.5"><Phone className="h-3.5 w-3.5 text-[#0f766e]" /> {tenant.phone || 'Chưa có số điện thoại'}</p>
                            </div>
                          </td>
                          <td className="px-5 py-4 text-center">
                            <div className="inline-block text-left space-y-1.5">
                              <div className="flex justify-center">
                                <span className="inline-flex items-center gap-1.5 rounded-full border border-[#3d2a18]/10 bg-[#efe2cf]/65 px-3 py-1 text-xs font-black text-[#6f6254] shadow-sm"><IdCard className="h-3.5 w-3.5" /> {tenant.identity_type_label || getIdentityTypeLabel(tenant.identity_type)}</span>
                              </div>
                              <p className="text-center text-xs font-bold text-[#8b5e34]/75">{tenant.identity_number || 'Chưa nhập số giấy tờ'}</p>
                            </div>
                          </td>
                          <td className="px-5 py-4 text-center">
                            <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-xs font-black shadow-sm', renting ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]' : 'border-rose-200 bg-rose-50 text-rose-700')}>
                              {tenant.status_label || (renting ? 'Đang thuê' : 'Ngừng thuê')}
                            </span>
                          </td>
                          <td className="px-5 py-4 text-center">
                            <div className="inline-flex items-center gap-2 text-xs font-black text-[#0f5f59]"><Building2 className="h-4 w-4" /> {getTenantBuildingName(tenant)}</div>
                          </td>
                          <td className="px-5 py-4 text-center">
                            <div className="inline-flex items-center gap-2 whitespace-nowrap text-xs font-black text-[#8a4f18]"><DoorOpen className="h-4 w-4 shrink-0" /> {getTenantRoomNumber(tenant)}</div>
                          </td>
                          <td className="px-5 py-4 text-center"><CountBadge value={tenant.vehicles_count ?? 0} /></td>
                          <td className="px-5 py-4">
                            <div className="flex items-center justify-end gap-2">
                              <button type="button" onClick={() => void viewTenant(tenant)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95" title="Xem chi tiết" aria-label={`Xem chi tiết khách thuê ${tenant.username}`}><Eye className="h-5 w-5" /></button>
                              <button type="button" onClick={() => editTenant(tenant)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#3d2a18]/10 active:scale-95" title="Chỉnh sửa" aria-label={`Chỉnh sửa khách thuê ${tenant.username}`}><Edit3 className="h-5 w-5" /></button>
                              <button type="button" disabled={statusChangingId === tenant.id} onClick={() => void toggleTenantStatus(tenant)} className={cn('inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition focus:outline-none focus:ring-4 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45', renting ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 focus:ring-emerald-100' : 'border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 focus:ring-rose-100')} title={renting ? 'Ngừng thuê' : 'Kích hoạt thuê lại'} aria-label={`${renting ? 'Ngừng thuê' : 'Kích hoạt thuê lại'} khách thuê ${tenant.username}`}><Power className="h-5 w-5" /></button>
                              <button type="button" disabled={deletingId === tenant.id} onClick={() => void removeTenant(tenant)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45" title="Xóa" aria-label={`Xóa khách thuê ${tenant.username}`}><Trash2 className="h-5 w-5" /></button>
                            </div>
                          </td>
                        </tr>
                      )
                    })}

                    {!isLoading && tenants.length === 0 && (
                      <tr>
                        <td colSpan={8} className="px-5 py-20 text-center">
                          <div className="mx-auto flex max-w-sm flex-col items-center rounded-[2rem] border border-dashed border-[#3d2a18]/12 bg-white/55 px-6 py-8">
                            <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]"><UserRound className="h-9 w-9" /></div>
                            <p className="text-lg font-black tracking-tight text-[#24170d]">Không tìm thấy khách thuê</p>
                            <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">{hasActiveFilters ? 'Thử xóa bộ lọc hoặc đổi từ khóa tìm kiếm.' : 'Hãy tạo hồ sơ khách thuê đầu tiên cho hệ thống.'}</p>
                            {hasActiveFilters && (
                              <button type="button" onClick={clearFilters} className="mt-4 inline-flex h-10 items-center justify-center rounded-2xl bg-[#24170d] px-4 text-xs font-black text-[#fff4df] transition hover:bg-[#3d2a18]">
                                Xóa bộ lọc
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

              {/* Mobile/Tablet Card View */}
              <div className="block lg:hidden divide-y divide-[#3d2a18]/8 bg-[#fffaf1]/70">
                {isLoading && Array.from({ length: 3 }).map((_, index) => (
                  <div key={index} className="p-4">
                    <div className="h-32 animate-pulse rounded-2xl bg-stone-100" />
                  </div>
                ))}

                {!isLoading && tenants.map((tenant) => {
                  const renting = isTenantRenting(tenant)

                  return (
                    <div key={tenant.id} className="p-4 space-y-3 transition hover:bg-[#f3c56b]/10">
                      <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                          <TenantAvatar tenant={tenant} />
                          <div className="min-w-0">
                            <p className="truncate text-sm font-black tracking-tight text-[#24170d]">{tenant.full_name || tenant.username}</p>
                            <p className="text-xs font-bold text-[#8b5e34]/70">@{tenant.username}</p>
                          </div>
                        </div>
                        <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-black shadow-sm', renting ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]' : 'border-rose-200 bg-rose-50 text-rose-700')}>
                          {tenant.status_label || (renting ? 'Đang thuê' : 'Ngừng thuê')}
                        </span>
                      </div>

                      <div className="grid grid-cols-2 gap-3 text-xs">
                        <div className="space-y-1 font-bold text-[#6f6254]">
                          <p className="text-[10px] font-black uppercase tracking-wider text-[#8b5e34]/60">Liên hệ</p>
                          <p className="flex items-center gap-1.5 truncate"><Mail className="h-3.5 w-3.5 shrink-0 text-[#a65f16]" /> {tenant.email || '—'}</p>
                          <p className="flex items-center gap-1.5"><Phone className="h-3.5 w-3.5 shrink-0 text-[#0f766e]" /> {tenant.phone || '—'}</p>
                        </div>
                        <div className="space-y-1 font-bold text-[#6f6254]">
                          <p className="text-[10px] font-black uppercase tracking-wider text-[#8b5e34]/60">Giấy tờ</p>
                          <p className="flex items-center gap-1.5 truncate"><IdCard className="h-3.5 w-3.5 shrink-0" /> {tenant.identity_type_label || getIdentityTypeLabel(tenant.identity_type)}</p>
                          <p className="pl-5 truncate text-[#8b5e34]/75">{tenant.identity_number || '—'}</p>
                        </div>
                      </div>

                      <div className="grid grid-cols-2 gap-3 text-xs border-t border-[#3d2a18]/6 pt-2">
                        <div className="space-y-1 font-black text-[#6f6254]">
                          <p className="text-[10px] font-black uppercase tracking-wider text-[#8b5e34]/60">Phòng ở</p>
                          <div className="flex items-center gap-2 text-[#0f5f59]"><Building2 className="h-3.5 w-3.5 shrink-0" /> {getTenantBuildingName(tenant)}</div>
                          <div className="flex items-center gap-2 text-[#8a4f18]"><DoorOpen className="h-3.5 w-3.5 shrink-0" /> {getTenantRoomNumber(tenant)}</div>
                        </div>
                        <div className="space-y-1 font-bold text-[#6f6254]">
                          <p className="text-[10px] font-black uppercase tracking-wider text-[#8b5e34]/60">Phương tiện</p>
                          <div className="pt-0.5"><CountBadge value={tenant.vehicles_count ?? 0} /></div>
                        </div>
                      </div>

                      <div className="flex items-center justify-end gap-2 border-t border-[#3d2a18]/6 pt-2">
                        <button type="button" onClick={() => void viewTenant(tenant)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59]" title="Xem chi tiết"><Eye className="h-4.5 w-4.5" /></button>
                        <button type="button" onClick={() => editTenant(tenant)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d]" title="Chỉnh sửa"><Edit3 className="h-4.5 w-4.5" /></button>
                        <button type="button" disabled={statusChangingId === tenant.id} onClick={() => void toggleTenantStatus(tenant)} className={cn('inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition disabled:cursor-not-allowed disabled:opacity-45', renting ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100')} title={renting ? 'Ngừng thuê' : 'Kích hoạt thuê lại'}><Power className="h-4.5 w-4.5" /></button>
                        <button type="button" disabled={deletingId === tenant.id} onClick={() => void removeTenant(tenant)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600" title="Xóa"><Trash2 className="h-4.5 w-4.5" /></button>
                        <button type="button" onClick={() => transferTenantRoom(tenant)} disabled={!renting} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f5f59]/25 hover:bg-[#0f5f59]/10 hover:text-[#0f5f59] disabled:cursor-not-allowed disabled:opacity-45" title="Chuyển phòng"><ArrowRightLeft className="h-4.5 w-4.5" /></button>
                      </div>
                    </div>
                  )
                })}

                {!isLoading && tenants.length === 0 && (
                  <div className="mx-auto flex max-w-sm flex-col items-center px-6 py-12 text-center">
                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]">
                      <UserRound className="h-7 w-7" />
                    </div>
                    <p className="text-base font-black text-[#24170d]">Không tìm thấy khách thuê</p>
                    <p className="mt-1 text-xs font-semibold text-[#6f6254]">Thử xóa bộ lọc hoặc đổi từ khóa tìm kiếm.</p>
                  </div>
                )}
              </div>

              <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb]/85 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
                <p className="text-xs font-black text-[#6f6254]">
                  Hiển thị <span className="tabular-nums text-[#24170d]">{paginationStart}</span>-<span className="tabular-nums text-[#24170d]">{paginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{totalTenants}</span> khách thuê
                </p>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                  <div className="w-full sm:w-36">
                    <AdminSelect value={perPage} options={perPageOptions} onChange={changePerPage} menuPlacement="top" />
                  </div>
                  <div className="flex items-center justify-center sm:justify-end gap-1.5">
                    <button type="button" disabled={safeCurrentPage <= 1} onClick={() => changePage(safeCurrentPage - 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang trước">
                      <ChevronLeft className="h-4 w-4" />
                    </button>
                    {visiblePages.map((page, index) => {
                      const previousPage = visiblePages[index - 1]
                      const hasGap = previousPage && page - previousPage > 1

                      return (
                        <div key={page} className="flex items-center gap-1.5">
                          {hasGap && <span className="px-1 text-xs font-black text-[#8b5e34]/60">...</span>}
                          <button type="button" onClick={() => changePage(page)} className={cn('inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition', page === safeCurrentPage ? 'border-[#24170d] bg-[#24170d] text-[#fff4df] shadow-sm' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15')} aria-current={page === safeCurrentPage ? 'page' : undefined}>
                            {page}
                          </button>
                        </div>
                      )
                    })}
                    <button type="button" disabled={safeCurrentPage >= totalPages} onClick={() => changePage(safeCurrentPage + 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang sau">
                      <ChevronRight className="h-4 w-4" />
                    </button>
                  </div>
                </div>
              </div>
            </section>

          </div >
        </section >

        {isDetailOpen && (
          <div className="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="tenant-detail-title">
            <button type="button" aria-label="Đóng chi tiết khách thuê" onClick={closeDetail} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />
            <div className="relative z-10 w-full max-w-5xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-stone-950/30">
              <div className="bg-[#24170d] p-5 text-[#fff4df]">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <h2 id="tenant-detail-title" className="mt-2 text-2xl font-black tracking-tight">{detailTenant?.full_name || detailTenant?.username || 'Đang tải chi tiết...'}</h2>
                  </div>
                  <button type="button" onClick={closeDetail} className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20" aria-label="Đóng chi tiết khách thuê">
                    <X className="h-5 w-5" />
                  </button>
                </div>
              </div>

              <div className="max-h-[calc(100dvh-12rem)] space-y-4 overflow-y-auto p-5">
                {isDetailLoading && <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-800">Đang tải chi tiết khách thuê...</div>}
                {detailErrorMessage && <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{detailErrorMessage}</div>}

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
                  <DetailTile label="Trạng thái" value={getTenantStatusLabel(detailTenant)} />
                  <DetailTile label="Giới tính" value={detailTenant?.gender_label || getGenderLabel(detailTenant?.gender)} />
                  <DetailTile label="Giấy tờ" value={`${detailTenant?.identity_type_label || getIdentityTypeLabel(detailTenant?.identity_type)} · ${detailTenant?.identity_number || '—'}`} />
                  <DetailTile label="Người tạo" value={detailTenant?.creator?.full_name || detailTenant?.creator?.username || '—'} />
                </div>

                <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
                  <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Thông tin liên hệ</p>
                  <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <DetailTile label="Username" value={`@${detailTenant?.username || '—'}`} />
                    <DetailTile label="Email" value={detailTenant?.email || '—'} />
                    <DetailTile label="Số điện thoại" value={detailTenant?.phone || '—'} />
                    <DetailTile label="Ngày sinh" value={formatDate(detailTenant?.date_of_birth)} />
                    <DetailTile label="Địa chỉ thường trú" value={detailTenant?.permanent_address || '—'} />
                    <DetailTile label="Địa chỉ hiện tại" value={detailTenant?.current_address || '—'} />
                  </div>
                </section>

                <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
                  <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Ảnh giấy tờ</p>
                  <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <ImageTile label="Mặt trước" url={detailTenant?.front_image_url} />
                    <ImageTile label="Mặt sau" url={detailTenant?.back_image_url} />
                  </div>
                </section>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
                  <DetailTile label="Tòa nhà đang ở" value={getTenantBuildingName(detailTenant)} />
                  <DetailTile label="Phòng đang ở" value={getTenantRoomNumber(detailTenant)} />
                  <DetailTile label="Phương tiện" value={detailTenant?.vehicles_count ?? 0} />
                  <DetailTile label="Thông báo đã đọc" value={detailTenant?.notification_reads_count ?? 0} />
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <DetailTile label="Ngày ngừng thuê" value={formatDate(detailTenant?.leave_date)} />
                  <DetailTile label="Ngày tạo" value={formatDateTime(detailTenant?.created_at)} />
                  <DetailTile
                    label="Lịch sử phòng"
                    value={
                      detailTenant?.id ? (
                        <Link to={`/admin/room-movements?tenant_id=${detailTenant.id}`} className="inline-flex items-center rounded-full border border-[#0f766e]/15 bg-[#0f766e]/10 px-3 py-1 text-xs font-black text-[#0f5f59] transition hover:bg-[#0f766e]/15">
                          Xem phòng & cọc
                        </Link>
                      ) : '—'
                    }
                  />
                </div>
              </div>
            </div>
          </div>
        )
        }
      </>
    </>
  )
}

function MetricCard({ label, value, tone }: { label: string; value: number; tone: 'neutral' | 'emerald' | 'amber' | 'teal' }) {
  const toneClassNames = {
    neutral: 'border-[#f8e8c8]/12 bg-[#f8e8c8]/10 text-[#fff4df]',
    emerald: 'border-[#0f766e]/35 bg-[#0f766e]/16 text-[#c8fff4]',
    amber: 'border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#fff4df]',
    teal: 'border-cyan-200/25 bg-cyan-100/10 text-cyan-50',
  }[tone]

  return (
    <div className={cn('rounded-3xl border px-4 py-3 backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/10', toneClassNames)}>
      <p className="text-[10px] font-black uppercase tracking-[0.18em] opacity-65">{label}</p>
      <p className="mt-1 text-3xl font-black tracking-tight tabular-nums">{value}</p>
    </div>
  )
}

function CountBadge({ value }: { value: number }) {
  return <span className="inline-flex min-w-10 items-center justify-center rounded-full border border-[#3d2a18]/10 bg-[#efe2cf]/65 px-3 py-1 text-xs font-black text-[#6f6254] shadow-sm tabular-nums">{value}</span>
}

function TenantAvatar({ tenant }: { tenant: AdminTenantResource }) {
  const avatarUrl = tenant.avatar_url?.trim()
  const [loadedAvatarUrl, setLoadedAvatarUrl] = useState<string | null>(null)
  const [failedAvatarUrl, setFailedAvatarUrl] = useState<string | null>(null)
  const [isZoomed, setIsZoomed] = useState(false)

  useEffect(() => {
    if (!isZoomed) return

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setIsZoomed(false)
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [isZoomed])

  const hasRemoteAvatar = Boolean(avatarUrl && avatarUrl !== DEFAULT_AVATAR_URL && avatarUrl !== failedAvatarUrl)
  const avatarAlt = tenant.full_name || tenant.username
  const visibleUrl = hasRemoteAvatar && loadedAvatarUrl === avatarUrl ? avatarUrl : DEFAULT_AVATAR_URL

  return (
    <>
      <span
        className="relative block h-11 w-11 shrink-0 overflow-hidden rounded-2xl border border-[#f3c56b]/35 bg-[#efe2cf] shadow-sm transition group-hover:-translate-y-0.5 group-hover:scale-105 cursor-zoom-in"
        onClick={() => setIsZoomed(true)}
      >
        <img src={DEFAULT_AVATAR_URL} alt={avatarAlt} width={44} height={44} decoding="async" className="h-full w-full object-cover" />
        {hasRemoteAvatar && (
          <img src={avatarUrl} alt="" aria-hidden="true" width={44} height={44} loading="lazy" decoding="async" onLoad={() => setLoadedAvatarUrl(avatarUrl || null)} onError={() => setFailedAvatarUrl(avatarUrl || null)} className={cn('absolute inset-0 h-full w-full object-cover transition-opacity duration-200', loadedAvatarUrl === avatarUrl ? 'opacity-100' : 'opacity-0')} />
        )}
      </span>

      {isZoomed && createPortal(
        <div
          className="fixed inset-0 z-[100] flex items-center justify-center bg-stone-950/80 p-4 backdrop-blur-sm cursor-zoom-out"
          onClick={() => setIsZoomed(false)}
        >
          <button
            type="button"
            className="absolute right-6 top-6 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur-md transition hover:bg-white/20"
            onClick={(e) => { e.stopPropagation(); setIsZoomed(false) }}
            aria-label="Đóng ảnh"
          >
            <X className="h-6 w-6" />
          </button>
          <img
            src={visibleUrl}
            alt={avatarAlt}
            className="max-h-[75vh] max-w-[90vw] lg:max-w-[70vw] rounded-lg object-contain shadow-2xl cursor-default"
            onClick={(e) => e.stopPropagation()}
          />
        </div>,
        document.body
      )}
    </>
  )
}

function handleImageFallback(event: SyntheticEvent<HTMLImageElement>) {
  const image = event.currentTarget

  if (image.src.endsWith(DEFAULT_AVATAR_URL)) return

  image.onerror = null
  image.src = DEFAULT_AVATAR_URL
}

function ImageTile({ label, url }: { label: string; url?: string | null }) {
  const isAvatar = label === 'Ảnh đại diện'
  const visibleUrl = url || (isAvatar ? DEFAULT_AVATAR_URL : null)
  const [isZoomed, setIsZoomed] = useState(false)

  useEffect(() => {
    if (!isZoomed) return

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setIsZoomed(false)
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [isZoomed])

  return (
    <>
      <div className="rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3">
        <p className="mb-2 text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/60">{label}</p>
        {visibleUrl ? (
          <img
            src={visibleUrl}
            alt={label}
            onError={isAvatar ? handleImageFallback : undefined}
            className="h-44 w-full rounded-2xl object-cover cursor-zoom-in transition hover:opacity-90"
            onClick={() => setIsZoomed(true)}
          />
        ) : (
          <div className="flex h-44 items-center justify-center rounded-2xl border border-dashed border-[#3d2a18]/12 bg-[#efe2cf]/45 text-sm font-black text-[#8b5e34]">Chưa có ảnh</div>
        )}
      </div>

      {isZoomed && visibleUrl && createPortal(
        <div
          className="fixed inset-0 z-[100] flex items-center justify-center bg-stone-950/80 p-4 backdrop-blur-sm cursor-zoom-out"
          onClick={() => setIsZoomed(false)}
        >
          <button
            type="button"
            className="absolute right-6 top-6 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur-md transition hover:bg-white/20"
            onClick={(e) => { e.stopPropagation(); setIsZoomed(false) }}
            aria-label="Đóng ảnh"
          >
            <X className="h-6 w-6" />
          </button>
          <img
            src={visibleUrl}
            alt={label}
            className="max-h-[75vh] max-w-[90vw] lg:max-w-[70vw] rounded-lg object-contain shadow-2xl cursor-default"
            onClick={(e) => e.stopPropagation()}
          />
        </div>,
        document.body
      )}
    </>
  )
}

function DetailTile({ label, value }: { label: string; value?: ReactNode }) {
  return (
    <div className="min-w-0 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3 shadow-sm">
      <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/60">{label}</p>
      <p className="mt-1 break-words text-sm font-black text-[#24170d]">{value ?? '—'}</p>
    </div>
  )
}


function isTenantRenting(tenant: AdminTenantResource) {
  return Number(tenant.status) === STATUS_RENTING
}

function getTenantStatusLabel(tenant?: AdminTenantResource | null) {
  if (!tenant) return '—'
  return tenant.status_label || (isTenantRenting(tenant) ? 'Đang thuê' : 'Ngừng thuê')
}

function getTenantBuildingName(tenant?: AdminTenantResource | null) {
  if (!tenant) return '—'
  return tenant.building_name || tenant.current_room?.building_name || 'Chưa gán tòa nhà'
}

function getTenantRoomNumber(tenant?: AdminTenantResource | null) {
  if (!tenant) return '—'
  const roomNumber = tenant.room_number || tenant.current_room?.room_number
  return roomNumber ? `Phòng ${roomNumber}` : 'Chưa gán phòng'
}

function getGenderLabel(gender?: number | null) {
  if (Number(gender) === GENDER_MALE) return 'Nam'
  if (Number(gender) === GENDER_FEMALE) return 'Nữ'
  return '—'
}

function getIdentityTypeLabel(identityType?: number | null) {
  if (Number(identityType) === IDENTITY_TYPE_CCCD) return 'CCCD'
  if (Number(identityType) === IDENTITY_TYPE_CMND) return 'CMND'
  if (Number(identityType) === IDENTITY_TYPE_PASSPORT) return 'Hộ chiếu'
  return '—'
}



function getVisibleErrorMessage(error: unknown, fallback: string) {
  if (error instanceof ApiError) {
    return error.message || fallback
  }

  if (error instanceof Error) {
    return error.message
  }

  return fallback
}
