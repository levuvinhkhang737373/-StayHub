import { useCallback, useEffect, useMemo, useState } from 'react'
import { ConfirmModal } from '../../../../shared/components/ConfirmModal'
import { useConfirmModal } from '../../../../shared/lib/hooks/use-confirm-modal'
import { useNavigate } from 'react-router-dom'
import { Building2, ChevronLeft, ChevronRight, Edit3, Eye, Mail, Phone, Plus, Power, Search, ShieldCheck, Trash2, UserCog, X } from 'lucide-react'
import { formatDateTime } from '../../../../shared/lib/utils/format'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { getVisibleErrorMessage, getVisibleFilterErrorMessage } from '../../shared/utils/error-message'
import { cn } from '../../../../shared/lib/utils/cn'
import {
  deleteAdminAccount,
  fetchAdminAccountDetail,
  fetchAdminAccounts,
  updateAdminAccountStatus,
} from '../services/admin-accounts.service'
import type { AdminAccountResource, AdminPaginationMeta, AdminPaginator } from '../types/admin-account-api.model'

type AdminAccountsResult = AdminPaginator<AdminAccountResource> | AdminAccountResource[]
type AdminAccountsResponse = Omit<Awaited<ReturnType<typeof fetchAdminAccounts>>, 'result'> & {
  result?: AdminAccountsResult | null
  data?: AdminAccountsResult | null
}

function normalizeAdminAccountsResponse(response: Awaited<ReturnType<typeof fetchAdminAccounts>>) {
  const envelope = response as AdminAccountsResponse
  const result = envelope.result ?? envelope.data

  if (!result) {
    return { data: [] as AdminAccountResource[], meta: null as AdminPaginationMeta | null }
  }

  if (Array.isArray(result)) {
    return { data: result, meta: null }
  }

  return { data: result.data || [], meta: result.meta || null }
}

const STATUS_ACTIVE = 1
const STATUS_INACTIVE = 2
const ROLE_BUILDING_MANAGER = 1
const ROLE_SUPER_ADMIN = 2

const roleOptions = [
  { value: '', label: 'Tất cả vai trò', tone: 'default' as const },
  { value: ROLE_BUILDING_MANAGER, label: 'Quản lý tòa nhà', tone: 'success' as const },
  { value: ROLE_SUPER_ADMIN, label: 'Quản trị tổng', tone: 'warning' as const },
]

const statusOptions = [
  { value: '', label: 'Tất cả trạng thái', tone: 'default' as const },
  { value: STATUS_ACTIVE, label: 'Hoạt động', tone: 'success' as const },
  { value: STATUS_INACTIVE, label: 'Ngừng hoạt động', tone: 'danger' as const },
]

const perPageOptions = [
  { value: 5, label: '5 dòng', tone: 'default' as const },
  { value: 10, label: '10 dòng', tone: 'default' as const },
  { value: 20, label: '20 dòng', tone: 'default' as const },
  { value: 50, label: '50 dòng', tone: 'default' as const },
]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'

export function SystemUsersScreen() {
  const navigate = useNavigate()
  const { session } = useAdminSession()
  const isSuperAdmin = isSuperAdminRole(session?.admin.role)
  const currentAdminId = Number(session?.admin.id || 0)
  const [keyword, setKeyword] = useState('')
  const [selectedRole, setSelectedRole] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [perPage, setPerPage] = useState(10)
  const [currentPage, setCurrentPage] = useState(1)
  const [paginationMeta, setPaginationMeta] = useState<AdminPaginationMeta | null>(null)
  const [accounts, setAccounts] = useState<AdminAccountResource[]>([])
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

  const [detailAccount, setDetailAccount] = useState<AdminAccountResource | null>(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [statusChangingId, setStatusChangingId] = useState<number | null>(null)
  const [deletingId, setDeletingId] = useState<number | null>(null)

  const loadAccounts = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)

    try {
      const response = await fetchAdminAccounts({
        keyword: keyword.trim() || undefined,
        role: selectedRole === '' ? undefined : Number(selectedRole),
        status: selectedStatus === '' ? undefined : Number(selectedStatus),
        page: currentPage,
        per_page: perPage,
      })

      const { data, meta } = normalizeAdminAccountsResponse(response)
      setAccounts(data)
      setPaginationMeta(meta)

      if (meta?.last_page && currentPage > meta.last_page) {
        setCurrentPage(meta.last_page)
      }
    } catch (error) {
      setErrorMessage(getVisibleFilterErrorMessage(error, 'Không thể tải tài khoản admin.', Boolean(keyword.trim() || selectedRole || selectedStatus)))
    } finally {
      setIsLoading(false)
    }
  }, [currentPage, keyword, perPage, selectedRole, selectedStatus])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadAccounts()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [loadAccounts])

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setCurrentPage(1)
  }, [keyword, selectedRole, selectedStatus, perPage])

  const activeAccounts = useMemo(() => accounts.filter((item) => isAccountActive(item)).length, [accounts])
  const inactiveAccounts = useMemo(() => accounts.filter((item) => !isAccountActive(item)).length, [accounts])
  const faceIdAccounts = useMemo(() => accounts.filter((item) => item.has_faceid).length, [accounts])
  const hasNextPageFallback = !paginationMeta && accounts.length >= perPage
  const totalPages = Math.max(1, paginationMeta?.last_page ?? (hasNextPageFallback ? currentPage + 1 : currentPage))
  const safeCurrentPage = Math.min(currentPage, totalPages)
  const totalAccounts = paginationMeta?.total ?? (safeCurrentPage - 1) * perPage + accounts.length
  const paginationStart = paginationMeta?.from ?? (accounts.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + 1)
  const paginationEnd = paginationMeta?.to ?? (accounts.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + accounts.length)
  const visiblePages = useMemo(() => {
    const pages = new Set<number>([1, totalPages, safeCurrentPage - 1, safeCurrentPage, safeCurrentPage + 1])
    return Array.from(pages)
      .filter((page) => page >= 1 && page <= totalPages)
      .sort((a, b) => a - b)
  }, [safeCurrentPage, totalPages])
  const hasActiveFilters = Boolean(keyword.trim() || selectedRole || selectedStatus)

  const viewAccount = async (account: AdminAccountResource) => {
    setDetailAccount(account)
    setIsDetailOpen(true)
    setIsDetailLoading(true)
    setDetailErrorMessage(null)

    try {
      const response = await fetchAdminAccountDetail(account.id)
      setDetailAccount(response.result)
    } catch (error) {
      setDetailErrorMessage(getVisibleErrorMessage(error, 'Không thể tải chi tiết tài khoản admin.'))
    } finally {
      setIsDetailLoading(false)
    }
  }

  const closeDetail = () => {
    setIsDetailOpen(false)
    setDetailAccount(null)
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

  const toggleAccountStatus = (account: AdminAccountResource) => {
    if (!isSuperAdmin || account.id === currentAdminId) return

    const nextStatus = isAccountActive(account) ? STATUS_INACTIVE : STATUS_ACTIVE

    const doToggle = async () => {
      try {
        setIsConfirmLoading(true)
        setStatusChangingId(account.id)
        setErrorMessage(null)
        setSuccessMessage(null)
        await updateAdminAccountStatus(account.id, {
          status: nextStatus,
          reason: nextStatus === STATUS_INACTIVE ? 'Ngừng hoạt động từ màn quản lý tài khoản admin' : 'Kích hoạt lại từ màn quản lý tài khoản admin',
        })
        setSuccessMessage(`${nextStatus === STATUS_ACTIVE ? 'Kích hoạt' : 'Ngừng hoạt động'} tài khoản admin thành công.`)
        await loadAccounts()
      } catch (error) {
        setErrorMessage(getVisibleErrorMessage(error, 'Không thể đổi trạng thái tài khoản admin.'))
      } finally {
        setIsConfirmLoading(false)
        setStatusChangingId(null)
        closeConfirm()
      }
    }

    if (nextStatus === STATUS_INACTIVE) {
      showConfirm({
        title: 'Ngừng hoạt động tài khoản',
        message: `Bạn có chắc muốn ngừng hoạt động tài khoản ${account.full_name || account.username}?.`,
        confirmLabel: 'Ngừng hoạt động',
        onConfirm: doToggle,
        variant: 'warning',
      })
    } else {
      void doToggle()
    }
  }

  const removeAccount = (account: AdminAccountResource) => {
    if (!isSuperAdmin || account.id === currentAdminId) return
    showConfirm({
      title: 'Xóa tài khoản admin',
      message: `Bạn có chắc chắn muốn xóa tài khoản ${account.full_name || account.username}? Chỉ tài khoản đã ngừng hoạt động và chưa phát sinh dữ liệu mới có thể xóa.`,
      confirmLabel: 'Xóa',
      onConfirm: async () => {
        try {
          setIsConfirmLoading(true)
          setDeletingId(account.id)
          setErrorMessage(null)
          await deleteAdminAccount(account.id)
          setSuccessMessage('Xóa tài khoản admin thành công.')
          if (accounts.length === 1 && currentPage > 1) {
            setCurrentPage((page) => Math.max(1, page - 1))
          } else {
            await loadAccounts()
          }
        } catch (error) {
          setErrorMessage(getVisibleErrorMessage(error, 'Không thể xóa tài khoản admin.'))
        } finally {
          setIsConfirmLoading(false)
          setDeletingId(null)
          closeConfirm()
        }
      },
      variant: 'danger',
    })
  }

  const clearFilters = () => {
    setKeyword('')
    setSelectedRole('')
    setSelectedStatus('')
  }

  const changePage = (page: number) => {
    setCurrentPage(Math.min(Math.max(1, page), totalPages))
  }

  const changePerPage = (nextValue: string | number) => {
    setPerPage(Number(nextValue))
  }

  return (
    <>
      <section className="space-y-5 sm:space-y-6 text-[#24170d]">
        <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
          <div className="relative p-5 text-[#fff4df] sm:p-6 lg:p-7">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_14%,rgba(243,197,107,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(15,118,110,0.26),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
            <div className="pointer-events-none absolute inset-x-6 bottom-0 h-px bg-gradient-to-r from-transparent via-[#f3c56b]/40 to-transparent" />

            <div className="relative flex min-w-0 flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
              <div className="min-w-0">
                <span className="block text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]/80">HỆ THỐNG</span>
                  <h1 className="mt-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem] flex items-center gap-3">
                    <ShieldCheck className="h-8 w-8 text-[#f3c56b] shrink-0" />
                    Tài khoản admin
                  </h1>
              </div>
              {isSuperAdmin && (
                <button type="button" onClick={() => navigate('/admin/system-users/create')} className="inline-flex h-9 w-fit self-end lg:self-auto items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition-all hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98]">
                  <Plus className="h-4 w-4 stroke-[2.8]" /> Thêm tài khoản
                </button>
              )}
            </div>

            <div className="relative mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <MetricCard label="Tổng tài khoản" value={totalAccounts} tone="neutral" />
              <MetricCard label="Hoạt động/trang" value={activeAccounts} tone="emerald" />
              <MetricCard label="Ngừng/trang" value={inactiveAccounts} tone="amber" />
              <MetricCard label="FaceID/trang" value={faceIdAccounts} tone="teal" />
            </div>
          </div>
        </section>

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

        {!isSuperAdmin && (
          <div className="rounded-3xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-black text-amber-800 shadow-sm">
            Chỉ quản trị tổng được quản lý tài khoản admin.
          </div>
        )}

        <div className="grid min-w-0 grid-cols-1 gap-4 lg:gap-6">
          <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
            <div className="border-b border-[#3d2a18]/10 bg-[#fff8eb]/85 p-4 sm:p-5">
              <div className="grid gap-3 lg:grid-cols-[minmax(18rem,1fr)_minmax(12rem,14rem)_minmax(12rem,14rem)]">
                <div className="relative min-w-0">
                  <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
                  <input type="text" value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="Tìm username, họ tên, email hoặc số điện thoại..." className={`${inputClass} pl-11 pr-28`} />
                  <button type="button" onClick={clearFilters} disabled={!hasActiveFilters} className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20 disabled:cursor-not-allowed disabled:opacity-45">
                    <X className="h-3.5 w-3.5" /> Xóa lọc
                  </button>
                </div>
                <AdminSelect value={selectedRole} options={roleOptions} onChange={(nextValue) => setSelectedRole(String(nextValue))} />
                <AdminSelect value={selectedStatus} options={statusOptions} onChange={(nextValue) => setSelectedStatus(String(nextValue))} />
              </div>
            </div>

            <div className="overflow-x-auto custom-scrollbar">
              <table className="w-full min-w-[1180px] text-left">
                <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8] whitespace-nowrap">
                  <tr>
                    <th className="px-5 py-4">Tài khoản</th>
                    <th className="px-5 py-4">Liên hệ</th>
                    <th className="px-5 py-4 text-center">Vai trò</th>
                    <th className="px-5 py-4 text-center">Trạng thái</th>
                    <th className="px-5 py-4 text-center">FaceID</th>
                    <th className="px-5 py-4 text-center">Tòa nhà</th>
                    <th className="px-5 py-4 text-center">Cập nhật</th>
                    <th className="px-5 py-4 min-w-[180px]"><div className="flex justify-end"><div className="w-[180px] text-center">Thao tác</div></div></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[#3d2a18]/8 bg-[#fffaf1]/70">
                  {isLoading && Array.from({ length: 5 }).map((_, index) => (
                    <tr key={index}>
                      <td colSpan={8} className="px-5 py-4"><div className="h-14 animate-pulse rounded-2xl bg-stone-100" /></td>
                    </tr>
                  ))}

                  {!isLoading && accounts.map((account) => {
                    const isSelf = account.id === currentAdminId
                    const active = isAccountActive(account)

                    return (
                      <tr key={account.id} className="group transition hover:bg-[#f3c56b]/10">
                        <td className="px-5 py-4">
                          <div className="flex items-center gap-3">
                            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#a65f16] shadow-sm transition group-hover:-translate-y-0.5 group-hover:scale-105">
                              <UserCog className="h-4 w-4" />
                            </div>
                            <div className="min-w-0">
                              <p className="truncate text-sm font-black tracking-tight text-[#24170d]">{account.full_name || account.username}</p>
                              <p className="mt-0.5 text-xs font-bold text-[#8b5e34]/70">@{account.username}{isSelf ? ' · Bạn' : ''}</p>
                            </div>
                          </div>
                        </td>
                        <td className="px-5 py-4">
                          <div className="space-y-1 text-xs font-bold text-[#6f6254]">
                            <p className="flex items-center gap-1.5"><Mail className="h-3.5 w-3.5 text-[#a65f16]" /> {account.email || 'Chưa có email'}</p>
                            <p className="flex items-center gap-1.5"><Phone className="h-3.5 w-3.5 text-[#0f766e]" /> {account.phone || 'Chưa có số điện thoại'}</p>
                          </div>
                        </td>
                        <td className="px-5 py-4 text-center">
                          <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-xs font-black shadow-sm', getRoleBadgeClass(account.role))}>
                            {account.role_label || getRoleLabel(account.role)}
                          </span>
                        </td>
                        <td className="px-5 py-4 text-center">
                          <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-xs font-black shadow-sm', active ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]' : 'border-rose-200 bg-rose-50 text-rose-700')}>
                            {account.status_label || (active ? 'Hoạt động' : 'Ngừng hoạt động')}
                          </span>
                        </td>
                        <td className="px-5 py-4 text-center">
                          <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-xs font-black shadow-sm', account.has_faceid ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]' : 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]')}>
                            {account.has_faceid ? 'Đã đăng ký' : 'Chưa có'}
                          </span>
                        </td>
                        <td className="px-5 py-4 text-center">
                          <div className="inline-block text-left">
                            <ManagedBuildingsBadges account={account} />
                          </div>
                        </td>
                        <td className="px-5 py-4 text-center">
                          <span className="text-xs font-bold text-[#8b5e34]/75">{formatDateTime(account.updated_at)}</span>
                        </td>
                        <td className="px-5 py-4 min-w-[180px]">
                          <div className="flex items-center justify-end gap-2">
                            <button type="button" onClick={() => void viewAccount(account)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95" title="Xem chi tiết" aria-label={`Xem chi tiết tài khoản ${account.username}`}><Eye className="h-5 w-5" /></button>
                            {isSuperAdmin && (
                              <>
                                <button type="button" onClick={() => navigate(`/admin/system-users/update/${account.id}`)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#3d2a18]/10 active:scale-95" title="Chỉnh sửa" aria-label={`Chỉnh sửa tài khoản ${account.username}`}><Edit3 className="h-5 w-5" /></button>
                                <button type="button" disabled={statusChangingId === account.id || isSelf} onClick={() => void toggleAccountStatus(account)} className={cn('inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition focus:outline-none focus:ring-4 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45', active ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 focus:ring-emerald-100' : 'border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 focus:ring-rose-100')} title={active ? 'Ngừng hoạt động' : 'Kích hoạt'} aria-label={`${active ? 'Ngừng hoạt động' : 'Kích hoạt'} tài khoản ${account.username}`}><Power className="h-5 w-5" /></button>
                                <button type="button" disabled={deletingId === account.id || isSelf} onClick={() => void removeAccount(account)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45" title="Xóa" aria-label={`Xóa tài khoản ${account.username}`}><Trash2 className="h-5 w-5" /></button>
                              </>
                            )}
                          </div>
                        </td>
                      </tr>
                    )
                  })}

                  {!isLoading && accounts.length === 0 && (
                    <tr>
                      <td colSpan={8} className="px-5 py-20 text-center">
                        <div className="mx-auto flex max-w-sm flex-col items-center rounded-[2rem] border border-dashed border-[#3d2a18]/12 bg-white/55 px-6 py-8">
                          <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]"><ShieldCheck className="h-9 w-9" /></div>
                          <p className="text-lg font-black tracking-tight text-[#24170d]">Không tìm thấy tài khoản admin</p>
                          <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">{hasActiveFilters ? 'Thử xóa bộ lọc hoặc đổi từ khóa tìm kiếm.' : 'Hãy tạo tài khoản admin đầu tiên cho hệ thống.'}</p>
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

            <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb]/85 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
              <p className="text-xs font-black text-[#6f6254]">
                Hiển thị <span className="tabular-nums text-[#24170d]">{paginationStart}</span>-<span className="tabular-nums text-[#24170d]">{paginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{totalAccounts}</span> tài khoản
              </p>
              <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="w-full sm:w-36">
                  <AdminSelect value={perPage} options={perPageOptions} onChange={changePerPage} menuPlacement="top" />
                </div>
                <div className="flex items-center justify-end gap-1.5">
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
        </div>
      </section>

      {isDetailOpen && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="admin-account-detail-title">
          <button type="button" aria-label="Đóng chi tiết tài khoản admin" onClick={closeDetail} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />
          <div className="relative z-10 w-full max-w-4xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-stone-950/30">
            <div className="bg-[#24170d] p-5 text-[#fff4df]">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-[10px] font-black uppercase tracking-[0.24em] text-[#f3c56b]">Admin account detail</p>
                  <h2 id="admin-account-detail-title" className="mt-2 text-2xl font-black tracking-tight">{detailAccount?.full_name || detailAccount?.username || 'Đang tải chi tiết...'}</h2>
                  <p className="mt-1 text-xs font-black uppercase tracking-[0.18em] text-[#f8e8c8]/72">{getAccountRoleLabel(detailAccount)} · {getAccountStatusLabel(detailAccount)}</p>
                </div>
                <button type="button" onClick={closeDetail} className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20" aria-label="Đóng chi tiết tài khoản admin">
                  <X className="h-5 w-5" />
                </button>
              </div>
            </div>

            <div className="max-h-[calc(100dvh-12rem)] space-y-4 overflow-y-auto p-5">
              {isDetailLoading && <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-800">Đang tải chi tiết tài khoản admin...</div>}
              {detailErrorMessage && <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{detailErrorMessage}</div>}

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <DetailTile label="Vai trò" value={getAccountRoleLabel(detailAccount)} />
                <DetailTile label="Trạng thái" value={getAccountStatusLabel(detailAccount)} />
                <DetailTile label="FaceID" value={detailAccount?.has_faceid ? 'Đã đăng ký' : 'Chưa có'} />
                <DetailTile label="Tòa nhà quản lý" value={getManagedBuildingSummary(detailAccount)} />
              </div>

              <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
                <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Danh sách tòa nhà quản lý</p>
                <div className="mt-3">
                  <ManagedBuildingsBadges account={detailAccount} expanded />
                </div>
              </section>

              <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
                <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Thông tin liên hệ</p>
                <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <DetailTile label="Username" value={`@${detailAccount?.username || '—'}`} />
                  <DetailTile label="Email" value={detailAccount?.email || '—'} />
                  <DetailTile label="Số điện thoại" value={detailAccount?.phone || '—'} />
                  <DetailTile label="Giới tính" value={detailAccount?.gender_label || '—'} />
                  <DetailTile label="Ngày sinh" value={detailAccount?.date_of_birth ? formatDateTime(detailAccount.date_of_birth).split(' ')[0] : '—'} />
                  <DetailTile label="Địa Chỉ" value={detailAccount?.address || '—'} />
                </div>
              </section>

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <DetailTile label="Ngày tạo" value={formatDateTime(detailAccount?.created_at)} />
                <DetailTile label="Ngày cập nhật" value={formatDateTime(detailAccount?.updated_at)} />
              </div>
            </div>
          </div>
        </div>
      )}
      <ConfirmModal {...confirmState} onCancel={closeConfirm} isLoading={isConfirmLoading} />
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

function ManagedBuildingsBadges({ account, expanded = false }: { account: AdminAccountResource | null; expanded?: boolean }) {
  if (!account) {
    return <span className="inline-flex items-center rounded-full border border-[#3d2a18]/10 bg-[#efe2cf]/65 px-3 py-1 text-xs font-black text-[#6f6254]">—</span>
  }

  const names = getManagedBuildingNames(account)

  if (names.length === 0) {
    const label = Number(account.role) === ROLE_SUPER_ADMIN
      ? 'Toàn hệ thống'
      : Number(account.managed_buildings_count || 0) > 0
        ? `${account.managed_buildings_count} tòa đã phân công`
        : Number(account.role) === ROLE_BUILDING_MANAGER
          ? 'Chưa phân công tòa nhà'
          : 'Không phụ trách tòa nhà'

    return (
      <span className="inline-flex items-center justify-center gap-1.5 rounded-full border border-[#3d2a18]/10 bg-[#efe2cf]/65 px-3 py-1 text-xs font-black text-[#6f6254] shadow-sm">
        <Building2 className="h-3.5 w-3.5" /> {label}
      </span>
    )
  }

  const visibleNames = expanded ? names : names.slice(0, 2)
  const hiddenCount = names.length - visibleNames.length

  return (
    <div className="flex flex-wrap gap-1.5">
      {visibleNames.map((name) => (
        <span key={name} className="inline-flex max-w-[13rem] items-center justify-center gap-1.5 rounded-full border border-[#0f766e]/15 bg-[#0f766e]/10 px-3 py-1 text-xs font-black text-[#0f5f59] shadow-sm">
          <Building2 className="h-3.5 w-3.5 shrink-0" /> <span className="truncate">{name}</span>
        </span>
      ))}
      {hiddenCount > 0 && (
        <span className="inline-flex items-center justify-center rounded-full border border-[#f3c56b]/35 bg-[#f3c56b]/18 px-3 py-1 text-xs font-black text-[#8a4f18] shadow-sm">
          +{hiddenCount} tòa
        </span>
      )}
    </div>
  )
}

function DetailTile({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-2xl border border-[#3d2a18]/10 bg-white/70 p-4">
      <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">{label}</p>
      <p className="mt-2 text-lg font-black text-[#24170d] tabular-nums">{value}</p>
    </div>
  )
}


function isAccountActive(account: AdminAccountResource) {
  return Number(account.status) === STATUS_ACTIVE
}

function getRoleLabel(role?: string | number | null) {
  if (Number(role) === ROLE_SUPER_ADMIN) return 'Quản trị tổng'
  if (Number(role) === ROLE_BUILDING_MANAGER) return 'Quản lý tòa nhà'
  return 'Không xác định'
}

function getAccountRoleLabel(account: AdminAccountResource | null) {
  return account ? account.role_label || getRoleLabel(account.role) : '—'
}

function getAccountStatusLabel(account: AdminAccountResource | null) {
  if (!account) return '—'
  return account.status_label || (isAccountActive(account) ? 'Hoạt động' : 'Ngừng hoạt động')
}

function getManagedBuildingNames(account: AdminAccountResource) {
  const buildingNames = account.managed_buildings?.map((building) => building.name).filter((name): name is string => Boolean(name)) ?? []
  const listedNames = account.managed_building_names?.filter((name): name is string => Boolean(name)) ?? []

  return Array.from(new Set([...buildingNames, ...listedNames]))
}

function getManagedBuildingSummary(account: AdminAccountResource | null) {
  if (!account) return '—'

  const names = getManagedBuildingNames(account)

  if (names.length > 0) return `${names.length} tòa`
  if (Number(account.role) === ROLE_SUPER_ADMIN) return 'Toàn hệ thống'
  if (Number(account.managed_buildings_count || 0) > 0) return `${account.managed_buildings_count} tòa đã phân công`
  if (Number(account.role) === ROLE_BUILDING_MANAGER) return 'Chưa phân công'

  return 'Không phụ trách'
}

function getRoleBadgeClass(role?: string | number | null) {
  if (Number(role) === ROLE_SUPER_ADMIN) return 'border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#8a4f18]'
  return 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]'
}
