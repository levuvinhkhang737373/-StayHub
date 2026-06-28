import { useCallback, useEffect, useMemo, useState, Fragment } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import {
  ArrowLeft,
  Building2,
  CalendarDays,
  CalendarPlus,
  Car,
  ChevronLeft,
  ChevronRight,
  ClipboardCheck,
  Edit3,
  Eye,
  FileText,
  Plus,
  Power,
  Trash2,
  Users,
  X,
} from 'lucide-react'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatCurrency, formatDate, parseMoneyInput } from '../../../../shared/lib/utils/format'
import { canManageContractsRole, isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import { AdminSelect } from '../../shared/components/AdminSelect'
import {
  createAdminContractDepositTransaction,
  deleteAdminContract,
  fetchAdminContractDetail,
  fetchAdminContracts,
  fetchAvailableRooms,
  terminateAdminContract,
  updateAdminContractStatus,
} from '../services/contracts.service'
import type {
  AdminContractResource,
  AdminPaginationMeta,
} from '../types/contract-api.model'

import {
  STATUS_PENDING_SIGN,
  STATUS_ACTIVE,
  STATUS_EXPIRED,
  STATUS_LIQUIDATED,
  STATUS_CANCELLED,
  statusOptions,
  perPageOptions,
  normalizeContracts,
  getResourceList,
  getVisibleErrorMessage,
  getStatusLabel,
  todayStr,
} from '../utils/contract.helpers'

import { MetricCard, IconButton, StatusBadge } from './ui/ui-elements'
import { inputClass } from './form/form-elements'
import { ContractDetailModal } from './modals/ContractDetailModal'
import { PayDepositModal } from './modals/PayDepositModal'
import { DepositQRModal } from './modals/DepositQRModal'
import { StatusModal } from './modals/StatusModal'
import { TerminateContractModal, type TerminateContractForm } from './modals/TerminateContractModal'

type ContractRoomOption = {
  id: number
  building_id: number
  room_number?: string | null
  status?: number | null
  base_price?: string | number | null
  max_occupants?: number | null
  current_occupants?: number | null
}

export function ContractsScreen() {
  const navigate = useNavigate()
  const { session } = useAdminSession()
  const adminRole = session?.admin?.role
  const isSuperAdmin = useMemo(() => isSuperAdminRole(adminRole), [adminRole])
  const canManageContracts = useMemo(() => canManageContractsRole(adminRole), [adminRole])
  const managedBuildingId = session?.admin?.managed_buildings?.[0]?.id

  const [searchParams] = useSearchParams()
  const contractIdParam = searchParams.get('id')
  const contractCodeParam = searchParams.get('contract_code')

  const [keyword, setKeyword] = useState(contractCodeParam || '')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [selectedBuildingId, setSelectedBuildingId] = useState(isSuperAdmin ? '' : managedBuildingId ? String(managedBuildingId) : '')
  const [selectedRoomId, setSelectedRoomId] = useState('')
  const [perPage, setPerPage] = useState(10)
  const [currentPage, setCurrentPage] = useState(1)
  const [paginationMeta, setPaginationMeta] = useState<AdminPaginationMeta | null>(null)
  const [contracts, setContracts] = useState<AdminContractResource[]>([])
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [rooms, setRooms] = useState<ContractRoomOption[]>([])
  const [detailContract, setDetailContract] = useState<AdminContractResource | null>(null)
  const [statusContract, setStatusContract] = useState<AdminContractResource | null>(null)
  const [statusForm, setStatusForm] = useState({ status: STATUS_ACTIVE, actual_end_date: '', note: '' })
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [isStatusSaving, setIsStatusSaving] = useState(false)
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [qrModalContract, setQrModalContract] = useState<AdminContractResource | null>(null)
  const [isConfirmingDeposit, setIsConfirmingDeposit] = useState(false)
  const [payingDepositContract, setPayingDepositContract] = useState<AdminContractResource | null>(null)
  const [terminatingContract, setTerminatingContract] = useState<AdminContractResource | null>(null)
  const [isTerminating, setIsTerminating] = useState(false)
  const [terminateForm, setTerminateForm] = useState<TerminateContractForm>({
    actual_end_date: todayStr,
    deduction_amount: '0',
    payment_method: 2,
    note: '',
  })

  const buildingOptions = useMemo(() => buildings.map((building) => ({ value: building.id, label: building.name, tone: 'default' as const })), [buildings])
  const filterBuildingOptions = useMemo(
    () => [{ value: '', label: isSuperAdmin ? 'Tất cả tòa nhà' : 'Tòa nhà được phân quyền', tone: 'default' as const }, ...buildingOptions],
    [buildingOptions, isSuperAdmin]
  )
  const roomOptions = useMemo(
    () =>
      rooms.map((room) => ({
        value: room.id,
        label: `Phòng ${room.room_number || room.id}`,
        description: `Đang ở ${room.current_occupants ?? 0}/${room.max_occupants ?? '—'} người`,
        tone: room.status === 1 ? ('success' as const) : ('warning' as const),
      })),
    [rooms]
  )
  const filterRoomOptions = useMemo(() => [{ value: '', label: 'Tất cả phòng', tone: 'default' as const }, ...roomOptions], [roomOptions])

  const metrics = useMemo(
    () => ({
      active: contracts.filter((contract) => Number(contract.status) === STATUS_ACTIVE).length,
      expired: contracts.filter((contract) => Number(contract.status) === STATUS_EXPIRED).length,
      liquidated: contracts.filter((contract) => Number(contract.status) === STATUS_LIQUIDATED).length,
    }),
    [contracts]
  )

  const safeCurrentPage = Math.max(1, Math.min(currentPage, paginationMeta?.last_page ?? currentPage))
  const totalPages = Math.max(1, paginationMeta?.last_page ?? (contracts.length >= perPage ? currentPage + 1 : currentPage))
  const totalContracts = paginationMeta?.total ?? (safeCurrentPage - 1) * perPage + contracts.length
  const paginationStart = paginationMeta?.from ?? (contracts.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + 1)
  const paginationEnd = paginationMeta?.to ?? (contracts.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + contracts.length)
  const visiblePages = useMemo(() => {
    const pages = new Set<number>([1, totalPages, safeCurrentPage - 1, safeCurrentPage, safeCurrentPage + 1])
    return Array.from(pages)
      .filter((page) => page >= 1 && page <= totalPages)
      .sort((a, b) => a - b)
  }, [safeCurrentPage, totalPages])
  const hasActiveFilters = Boolean(keyword.trim() || selectedStatus || selectedBuildingId || selectedRoomId)

  const loadBuildings = useCallback(async () => {
    if (!canManageContracts) return

    try {
      const response = await fetchAdminBuildings({ per_page: 100 })
      const list = getResourceList(response.result)
      setBuildings(list)

      if (!isSuperAdmin && !selectedBuildingId && list[0]?.id) {
        setSelectedBuildingId(String(list[0].id))
      }
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải danh sách tòa nhà.'))
    }
  }, [canManageContracts, isSuperAdmin, selectedBuildingId])

  const loadRoomsForBuilding = useCallback(async (buildingId: string) => {
    if (!buildingId) {
      setRooms([])
      return
    }

    try {
      const response = await fetchAvailableRooms({ building_id: Number(buildingId) })
      const list = Array.isArray(response.result) ? response.result : []
      setRooms(list)
    } catch (error) {
      setRooms([])
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải danh sách phòng của tòa nhà.'))
    }
  }, [])

  const loadContracts = useCallback(async () => {
    if (!canManageContracts) return

    setIsLoading(true)
    setErrorMessage(null)

    try {
      const response = await fetchAdminContracts({
        keyword: keyword.trim() || undefined,
        status: selectedStatus ? Number(selectedStatus) : undefined,
        building_id: selectedBuildingId ? Number(selectedBuildingId) : undefined,
        room_id: selectedRoomId ? Number(selectedRoomId) : undefined,
        page: currentPage,
        per_page: perPage,
      })
      const { data, meta } = normalizeContracts(response.result)
      setContracts(data)
      setPaginationMeta(meta)

      if (meta?.last_page && currentPage > meta.last_page) {
        setCurrentPage(meta.last_page)
      }
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải danh sách hợp đồng.'))
    } finally {
      setIsLoading(false)
    }
  }, [canManageContracts, currentPage, keyword, perPage, selectedBuildingId, selectedRoomId, selectedStatus])

  useEffect(() => {
    void loadBuildings()
  }, [loadBuildings])

  useEffect(() => {
    void loadRoomsForBuilding(selectedBuildingId)
  }, [selectedBuildingId, loadRoomsForBuilding])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadContracts()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [loadContracts])

  useEffect(() => {
    const handleRefresh = () => {
      void loadContracts()
    }

    window.addEventListener('contract-refresh', handleRefresh)
    window.addEventListener('contract-deposit-paid', handleRefresh)

    return () => {
      window.removeEventListener('contract-refresh', handleRefresh)
      window.removeEventListener('contract-deposit-paid', handleRefresh)
    }
  }, [loadContracts])

  useEffect(() => {
    if (contractIdParam) {
      void viewContract({ id: Number(contractIdParam) } as any)
    }
  }, [contractIdParam])

  useEffect(() => {
    if (!isLoading && contractCodeParam && contracts.length > 0) {
      const found = contracts.find((c) => c.contract_code === contractCodeParam)
      if (found) {
        void viewContract(found)
      }
    }
  }, [isLoading, contractCodeParam, contracts])

  const viewContract = async (contract: AdminContractResource) => {
    setDetailContract(contract)
    setIsDetailLoading(true)
    setDetailErrorMessage(null)

    try {
      const response = await fetchAdminContractDetail(contract.id)
      setDetailContract(response.result)
    } catch (error) {
      setDetailErrorMessage(getVisibleErrorMessage(error, 'Không thể tải chi tiết hợp đồng.'))
    } finally {
      setIsDetailLoading(false)
    }
  }

  const openStatusModal = (contract: AdminContractResource) => {
    const currentStatus = Number(contract.status)
    const nextStatus = currentStatus === STATUS_PENDING_SIGN ? STATUS_CANCELLED : STATUS_LIQUIDATED
    setStatusContract(contract)
    setStatusForm({ status: nextStatus, actual_end_date: '', note: '' })
  }

  const submitStatus = async () => {
    if (!statusContract || isStatusSaving) return

    const currentStatus = Number(statusContract.status)
    if (
      currentStatus !== STATUS_PENDING_SIGN &&
      [STATUS_LIQUIDATED, STATUS_CANCELLED].includes(Number(statusForm.status)) &&
      !statusForm.actual_end_date
    ) {
      setErrorMessage('Vui lòng nhập ngày kết thúc thực tế khi thanh lý hoặc hủy hợp đồng.')
      return
    }

    try {
      setIsStatusSaving(true)
      setErrorMessage(null)
      setSuccessMessage(null)
      await updateAdminContractStatus(statusContract.id, {
        status: Number(statusForm.status),
        actual_end_date: statusForm.actual_end_date || undefined,
        note: statusForm.note.trim() || undefined,
      })
      setSuccessMessage('Cập nhật trạng thái hợp đồng thành công.')
      setStatusContract(null)
      await loadContracts()
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể cập nhật trạng thái hợp đồng.'))
    } finally {
      setIsStatusSaving(false)
    }
  }

  const openTerminateModal = (contract: AdminContractResource) => {
    setTerminatingContract(contract)
    setTerminateForm({
      actual_end_date: contract.actual_end_date || todayStr,
      deduction_amount: '0',
      payment_method: 2,
      note: '',
    })
  }

  const submitTerminate = async () => {
    if (!terminatingContract || isTerminating) return

    const depositBalance = Number(terminatingContract.deposit_balance || '0')
    const deductionAmount = Number(terminateForm.deduction_amount || '0')

    if (!terminateForm.actual_end_date) {
      setErrorMessage('Vui lòng nhập ngày thanh lý hợp đồng.')
      return
    }

    if (Number.isNaN(deductionAmount) || deductionAmount < 0) {
      setErrorMessage('Số tiền cấn trừ cọc không hợp lệ.')
      return
    }

    if (deductionAmount > depositBalance) {
      setErrorMessage('Số tiền cấn trừ không được vượt quá số dư cọc hiện tại.')
      return
    }

    try {
      setIsTerminating(true)
      setErrorMessage(null)
      setSuccessMessage(null)

      const response = await terminateAdminContract(terminatingContract.id, {
        actual_end_date: terminateForm.actual_end_date,
        deduction_amount: parseMoneyInput(terminateForm.deduction_amount) || '0',
        payment_method: Number(terminateForm.payment_method),
        note: terminateForm.note.trim() || undefined,
      })

      const settlement = response.result?.settlement
      setSuccessMessage(
        settlement
          ? `Thanh lý hợp đồng thành công. Hoàn cọc ${formatCurrency(settlement.refund_amount)}, cấn trừ ${formatCurrency(settlement.deduction_amount)}.`
          : 'Thanh lý hợp đồng thành công.'
      )
      setTerminatingContract(null)

      if (detailContract?.id === terminatingContract.id && response.result?.contract) {
        setDetailContract(response.result.contract)
      }

      await loadContracts()
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể thanh lý hợp đồng.'))
    } finally {
      setIsTerminating(false)
    }
  }

  const removeContract = async (contract: AdminContractResource) => {
    if (
      !window.confirm(
        `Bạn có chắc chắn muốn xóa hợp đồng ${contract.contract_code}? Chỉ hợp đồng nháp hoặc đã hủy và chưa phát sinh dữ liệu liên quan mới có thể xóa.`
      )
    )
      return

    try {
      setDeletingId(contract.id)
      setErrorMessage(null)
      await deleteAdminContract(contract.id)
      setSuccessMessage('Xóa hợp đồng thành công.')
      if (contracts.length === 1 && currentPage > 1) {
        setCurrentPage((page) => Math.max(1, page - 1))
      } else {
        await loadContracts()
      }
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể xóa hợp đồng.'))
    } finally {
      setDeletingId(null)
    }
  }

  const clearFilters = () => {
    setKeyword('')
    setSelectedStatus('')
    setSelectedBuildingId(isSuperAdmin ? '' : managedBuildingId ? String(managedBuildingId) : '')
    setSelectedRoomId('')
    setCurrentPage(1)
  }

  if (!canManageContracts) {
    return (
      <section className="rounded-[2rem] border border-rose-200 bg-rose-50 p-6 text-rose-700">
        <h1 className="text-xl font-black">Bạn không có quyền quản lý hợp đồng</h1>
        <p className="mt-2 text-sm font-bold">Chức năng này chỉ dành cho quản trị tổng và quản lý tòa nhà.</p>
      </section>
    )
  }

  return (
    <section className="space-y-5 sm:space-y-6 text-[#24170d]">
      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-4 text-[#fff4df] sm:p-5 lg:p-6">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_14%,rgba(243,197,107,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(15,118,110,0.26),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
          <div className="relative flex min-w-0 flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div className="min-w-0">
              <Link to="/admin/dashboard" className="mb-2 inline-flex items-center gap-2 text-xs font-black text-[#f3c56b] transition hover:text-[#ffd56f]">
                <ArrowLeft className="h-3.5 w-3.5" /> Về dashboard
              </Link>
              <h1 className="mt-3 flex items-center gap-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem]">
                <FileText className="h-9 w-9 text-[#f3c56b]" /> Quản lý hợp đồng
              </h1>
            </div>
            <button
              type="button"
              onClick={() => navigate('/admin/contracts/create')}
              className="inline-flex h-10 w-fit self-end lg:self-auto items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition hover:bg-[#ffd56f] active:scale-[0.98]"
            >
              <Plus className="h-4 w-4" /> Thêm hợp đồng
            </button>
          </div>

          <div className="relative mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <MetricCard label="Tổng hợp đồng" value={totalContracts} tone="neutral" />
            <MetricCard label="Đang hiệu lực/trang" value={metrics.active} tone="emerald" />
            <MetricCard label="Hết hạn/trang" value={metrics.expired} tone="amber" />
            <MetricCard label="Đã thanh lý/trang" value={metrics.liquidated} tone="teal" />
          </div>
        </div>
      </section>

      {(errorMessage || successMessage) && (
        <div
          className={cn(
            'rounded-3xl border px-4 py-3 text-sm font-black shadow-sm',
            errorMessage ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'
          )}
        >
          {errorMessage || successMessage}
        </div>
      )}

      <div className="grid min-w-0 grid-cols-1 gap-4 lg:gap-6">
        <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
          <div className="border-b border-[#3d2a18]/10 bg-[#fff8eb]/85 p-4 sm:p-5">
            <div className="grid gap-3 lg:grid-cols-[minmax(12rem,1fr)_minmax(100px,1fr)_minmax(100px,1fr)_minmax(100px,1fr)]">
              <div className="relative min-w-0">
                <SearchIcon className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
                <input
                  type="text"
                  value={keyword}
                  onChange={(event) => {
                    setKeyword(event.target.value)
                    setCurrentPage(1)
                  }}
                  placeholder="Tìm mã HĐ, phòng, tòa nhà, khách đại diện, SĐT..."
                  className={`${inputClass} pl-11 pr-28`}
                />
                <button
                  type="button"
                  onClick={clearFilters}
                  disabled={!hasActiveFilters}
                  className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] disabled:opacity-45"
                >
                  <X className="h-3.5 w-3.5" /> Xóa lọc
                </button>
              </div>
              <AdminSelect
                value={selectedStatus}
                options={statusOptions}
                onChange={(nextValue) => {
                  setSelectedStatus(String(nextValue))
                  setCurrentPage(1)
                }}
              />
              <AdminSelect
                value={selectedBuildingId}
                options={filterBuildingOptions}
                disabled={!isSuperAdmin && buildingOptions.length <= 1}
                onChange={(nextValue) => {
                  setSelectedBuildingId(String(nextValue))
                  setSelectedRoomId('')
                  setCurrentPage(1)
                }}
              />
              <AdminSelect
                value={selectedRoomId}
                options={filterRoomOptions}
                disabled={!selectedBuildingId}
                onChange={(nextValue) => {
                  setSelectedRoomId(String(nextValue))
                  setCurrentPage(1)
                }}
              />
            </div>
          </div>

          <div className="overflow-x-auto custom-scrollbar">
            <table className="w-full text-left">
              <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
                <tr>
                  <th className="px-5 py-4">Hợp đồng</th>
                  <th className="px-5 py-4">Phòng / Tòa nhà</th>
                  <th className="px-5 py-4">Thời hạn</th>
                  <th className="px-5 py-4">Giá / Cọc</th>
                  <th className="px-5 py-4 text-center">Dữ liệu</th>
                  <th className="px-5 py-4 text-center">Trạng thái</th>
                  <th className="px-5 py-4"><div className="flex justify-end"><div className="w-[232px] text-center">Thao tác</div></div></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[#3d2a18]/8 bg-[#fffaf1]/70">
                {isLoading &&
                  Array.from({ length: 5 }).map((_, index) => (
                    <tr key={index}>
                      <td colSpan={7} className="px-5 py-4">
                        <div className="h-14 animate-pulse rounded-2xl bg-stone-100" />
                      </td>
                    </tr>
                  ))}

                {!isLoading &&
                  contracts.map((contract) => (
                    <tr key={contract.id} className="transition hover:bg-[#f3c56b]/10">
                      <td className="px-5 py-4">
                        <p className="text-sm font-black text-[#24170d]">{contract.contract_code}</p>
                        <p className="mt-1 text-xs font-bold text-[#8b5e34]/70">tạo {formatDate(contract.created_at)}</p>
                      </td>
                      <td className="px-5 py-4">
                        <div className="space-y-1 text-xs font-black text-[#6f6254]">
                          <p className="flex items-center gap-1.5 text-[#8a4f18]">
                            <Building2 className="h-4 w-4" /> {contract.building_name || 'Chưa rõ tòa nhà'}
                          </p>
                          <p className="text-[#24170d]">Phòng {contract.room_number || contract.room_code || contract.room_id}</p>
                        </div>
                      </td>
                      <td className="px-5 py-4">
                        <p className="flex items-center gap-1.5 text-xs font-black text-[#0f5f59]">
                          <CalendarDays className="h-4 w-4" /> {formatDate(contract.start_date)} → {formatDate(contract.end_date)}
                        </p>
                        {contract.actual_end_date && <p className="mt-1 text-xs font-bold text-rose-600">KT thực tế: {formatDate(contract.actual_end_date)}</p>}
                      </td>
                      <td className="px-5 py-4">
                        <p className="text-xs font-black text-[#24170d]">{formatCurrency(contract.room_price)}</p>
                        <p className="mt-1 text-xs font-bold text-[#8b5e34]/70">Cọc {formatCurrency(contract.deposit_amount)}</p>
                      </td>
                      <td className="px-5 py-4 text-center">
                        <div className="inline-flex items-center gap-1.5 rounded-full border border-[#3d2a18]/10 bg-[#efe2cf]/65 px-3 py-1 text-xs font-black text-[#6f6254]">
                          <Users className="h-3.5 w-3.5" /> {contract.tenants_count ?? contract.contract_tenants_count ?? 0}
                          <Car className="ml-1 h-3.5 w-3.5" /> {contract.vehicles_count ?? 0}
                        </div>
                      </td>
                      <td className="px-5 py-4 text-center">
                        <StatusBadge status={contract.status} label={contract.status_label || getStatusLabel(contract.status)} />
                      </td>
                      <td className="px-5 py-4">
                        <div className="flex items-center justify-end gap-2">
                          <IconButton title="Xem chi tiết" onClick={() => void viewContract(contract)}>
                            <Eye className="h-5 w-5" />
                          </IconButton>
                          {Number(contract.status) === STATUS_EXPIRED && (
                            <IconButton title="Gia hạn" onClick={() => navigate(`/admin/contracts/${contract.id}/renew`)}>
                              <CalendarPlus className="h-5 w-5" />
                            </IconButton>
                          )}
                          {[STATUS_ACTIVE, STATUS_EXPIRED].includes(Number(contract.status)) && (
                            <IconButton title="Thanh lý hợp đồng" onClick={() => openTerminateModal(contract)}>
                              <ClipboardCheck className="h-5 w-5" />
                            </IconButton>
                          )}
                          <IconButton title="Chỉnh sửa" onClick={() => navigate(`/admin/contracts/${contract.id}/edit`)}>
                            <Edit3 className="h-5 w-5" />
                          </IconButton>
                          {![STATUS_EXPIRED, STATUS_LIQUIDATED, STATUS_CANCELLED].includes(Number(contract.status)) && (
                            <IconButton title="Đổi trạng thái" onClick={() => openStatusModal(contract)}>
                              <Power className="h-5 w-5" />
                            </IconButton>
                          )}
                          <IconButton title="Xóa" disabled={deletingId === contract.id} danger onClick={() => void removeContract(contract)}>
                            <Trash2 className="h-5 w-5" />
                          </IconButton>
                        </div>
                      </td>
                    </tr>
                  ))}

                {!isLoading && contracts.length === 0 && (
                  <tr>
                    <td colSpan={7} className="px-5 py-20 text-center">
                      <div className="mx-auto flex max-w-sm flex-col items-center rounded-[2rem] border border-dashed border-[#3d2a18]/12 bg-white/55 px-6 py-8">
                        <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]">
                          <FileText className="h-9 w-9" />
                        </div>
                        <p className="text-lg font-black tracking-tight text-[#24170d]">Không tìm thấy hợp đồng</p>
                        <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">
                          {hasActiveFilters ? 'Thử xóa bộ lọc hoặc đổi từ khóa tìm kiếm.' : 'Hãy tạo hợp đồng đầu tiên cho phòng đang quản lý.'}
                        </p>
                      </div>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb]/85 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
            <p className="text-xs font-black text-[#6f6254]">
              Hiển thị <span className="tabular-nums text-[#24170d]">{paginationStart}</span>-
              <span className="tabular-nums text-[#24170d]">{paginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{totalContracts}</span> hợp
              đồng
            </p>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
              <div className="w-full sm:w-36">
                <AdminSelect
                  value={perPage}
                  options={perPageOptions}
                  onChange={(nextValue) => {
                    setPerPage(Number(nextValue))
                    setCurrentPage(1)
                  }}
                  menuPlacement="top"
                />
              </div>
              <div className="flex items-center justify-end gap-1.5">
                <button
                  type="button"
                  disabled={safeCurrentPage <= 1}
                  onClick={() => setCurrentPage(safeCurrentPage - 1)}
                  className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:opacity-45"
                >
                  <ChevronLeft className="h-4 w-4" />
                </button>
                {visiblePages.map((page, index) => {
                  const previousPage = visiblePages[index - 1]
                  const hasGap = previousPage && page - previousPage > 1

                  return (
                    <Fragment key={page}>
                      {hasGap && <span className="px-1 text-xs font-black text-[#8b5e34]/60">...</span>}
                      <button
                        type="button"
                        onClick={() => setCurrentPage(page)}
                        className={cn(
                          'inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition',
                          page === safeCurrentPage ? 'border-[#24170d] bg-[#24170d] text-[#fff4df]' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15'
                        )}
                      >
                        {page}
                      </button>
                    </Fragment>
                  )
                })}
                <button
                  type="button"
                  disabled={safeCurrentPage >= totalPages}
                  onClick={() => setCurrentPage(safeCurrentPage + 1)}
                  className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:opacity-45"
                >
                  <ChevronRight className="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>
        </section>
      </div>

      {detailContract && (
        <ContractDetailModal
          contract={detailContract}
          isLoading={isDetailLoading}
          errorMessage={detailErrorMessage}
          onClose={() => {
            setDetailContract(null)
            setDetailErrorMessage(null)
          }}
          onPayDeposit={(contract) => {
            setPayingDepositContract(contract)
          }}
        />
      )}

      {payingDepositContract && (
        <PayDepositModal
          contract={payingDepositContract}
          isSaving={isConfirmingDeposit}
          onClose={() => setPayingDepositContract(null)}
          onConfirm={async (method) => {
            try {
              setIsConfirmingDeposit(true)
              const response = await createAdminContractDepositTransaction(payingDepositContract.id, {
                transaction_type: 1, // COLLECT
                amount: payingDepositContract.deposit_amount || '0.00',
                transaction_date: new Date().toISOString().slice(0, 10),
                payment_method: method, // 1: Cash, 2: QR
                note: method === 1 ? 'Thu cọc bằng tiền mặt' : 'Xác nhận thu cọc chuyển khoản QR tại chỗ',
              })
              setPayingDepositContract(null)
              if (detailContract && detailContract.id === payingDepositContract.id && response.result) {
                setDetailContract(response.result)
              }
              await loadContracts()
            } catch (error) {
              alert(getVisibleErrorMessage(error, 'Không thể ghi nhận giao dịch cọc.'))
            } finally {
              setIsConfirmingDeposit(false)
            }
          }}
        />
      )}

      {qrModalContract && (
        <DepositQRModal
          contract={qrModalContract}
          onClose={() => {
            setQrModalContract(null)
            void loadContracts()
          }}
        />
      )}

      {statusContract && (
        <StatusModal
          contract={statusContract}
          form={statusForm}
          isSaving={isStatusSaving}
          onChange={setStatusForm}
          onClose={() => setStatusContract(null)}
          onSubmit={() => void submitStatus()}
        />
      )}

      {terminatingContract && (
        <TerminateContractModal
          contract={terminatingContract}
          form={terminateForm}
          isSaving={isTerminating}
          onChange={setTerminateForm}
          onClose={() => setTerminatingContract(null)}
          onSubmit={() => void submitTerminate()}
        />
      )}
    </section>
  )
}

function SearchIcon(props: React.SVGProps<SVGSVGElement>) {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      width="24"
      height="24"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      {...props}
    >
      <circle cx="11" cy="11" r="8" />
      <path d="m21 21-4.3-4.3" />
    </svg>
  )
}
