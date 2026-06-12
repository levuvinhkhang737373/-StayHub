import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { Link } from 'react-router-dom'
import {
  ArrowLeft,
  BadgeCheck,
  Building2,
  CalendarDays,
  CalendarPlus,
  Car,
  ChevronLeft,
  ChevronRight,
  Edit3,
  Eye,
  FileText,
  Plus,
  Power,
  RefreshCw,
  Search,
  Trash2,
  UserPlus,
  Users,
  X,
} from 'lucide-react'
import { AdminDateInput } from '../../../../shared/components/AdminDateInput'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatCurrency, formatDate, formatDateTime } from '../../../../shared/lib/utils/format'
import { canManageContractsRole, isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { fetchAdminTenants } from '../../tenants/services/tenants.service'
import type { AdminTenantResource } from '../../tenants/types/tenant-api.model'
import {
  createAdminContract,
  createAdminContractDepositTransaction,
  deleteAdminContract,
  fetchAdminContractDetail,
  fetchAdminContracts,
  fetchAvailableRooms,
  fetchAdminContractVehicles,
  renewContract,
  updateAdminContract,
  updateAdminContractStatus,
} from '../services/contracts.service'
import type {
  AdminContractPayload,
  AdminContractResource,
  AdminPaginationMeta,
  AdminVehicleOptionResource,
  ContractFormErrors,
  ContractFormValues,
  ContractTenantFormRow,
  ContractVehicleFormRow,
} from '../types/contract-api.model'
import { validateContractForm } from '../validations/contract.validation'

type ContractRoomOption = {
  id: number
  building_id: number
  room_number?: string | null
  status?: number | null
  base_price?: string | number | null
  max_occupants?: number | null
  current_occupants?: number | null
}

type ContractsResult = { data?: AdminContractResource[]; meta?: AdminPaginationMeta | null } | AdminContractResource[] | null | undefined

const STATUS_ACTIVE = 1
const STATUS_EXPIRED = 2
const STATUS_LIQUIDATED = 3
const STATUS_CANCELLED = 4
const CHARGE_MONTHLY = 1
const CHARGE_DAILY = 2
const CHARGE_FREE = 3

const defaultTenantRow: ContractTenantFormRow = {
  tenant_id: '',
  join_date: '',
  leave_date: '',
  billing_start_date: '',
  billing_end_date: '',
  is_staying: true,
}



const defaultForm: ContractFormValues = {
  contract_code: '',
  building_id: '',
  room_id: '',
  start_date: '',
  end_date: '',
  actual_end_date: '',
  billing_cycle_day: '1',
  room_price: '',
  deposit_amount: '0.00',
  status: STATUS_ACTIVE,
  contract_files: [],
  delete_contract_files: [],
  note: '',
  parent_contract_id: '',
  renew_from_contract_id: '',
  tenants: [{ ...defaultTenantRow }],
  vehicles: [],
  deposit_transactions: [],
  is_deposit_paid: true,
  deposit_payment_method: '2',
}

const statusOptions = [
  { value: '', label: 'Tất cả trạng thái', tone: 'default' as const },
  { value: STATUS_ACTIVE, label: 'Đang hiệu lực', tone: 'success' as const },
  { value: STATUS_EXPIRED, label: 'Hết hạn', tone: 'warning' as const },
  { value: STATUS_LIQUIDATED, label: 'Đã thanh lý', tone: 'success' as const },
  { value: STATUS_CANCELLED, label: 'Đã hủy', tone: 'danger' as const },
]

const createStatusOptions = statusOptions.filter((option) => [STATUS_ACTIVE].includes(Number(option.value)))
const statusChangeOptions = statusOptions.filter((option) => option.value !== '')

const chargePolicyOptions = [
  { value: CHARGE_MONTHLY, label: 'Tính theo tháng', tone: 'default' as const },
  { value: CHARGE_DAILY, label: 'Tính theo ngày', tone: 'warning' as const },
  { value: CHARGE_FREE, label: 'Miễn phí', tone: 'success' as const },
]


const perPageOptions = [
  { value: 5, label: '5 dòng', tone: 'default' as const },
  { value: 10, label: '10 dòng', tone: 'default' as const },
  { value: 20, label: '20 dòng', tone: 'default' as const },
  { value: 50, label: '50 dòng', tone: 'default' as const },
]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'
const inputErrorClass = 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100'
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65'

export function ContractsScreen() {
  const { session } = useAdminSession()
  const adminRole = session?.admin?.role
  const isSuperAdmin = useMemo(() => isSuperAdminRole(adminRole), [adminRole])
  const canManageContracts = useMemo(() => canManageContractsRole(adminRole), [adminRole])
  const managedBuildingId = session?.admin?.managed_buildings?.[0]?.id

  const [keyword, setKeyword] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [selectedBuildingId, setSelectedBuildingId] = useState(isSuperAdmin ? '' : managedBuildingId ? String(managedBuildingId) : '')
  const [selectedRoomId, setSelectedRoomId] = useState('')
  const [perPage, setPerPage] = useState(10)
  const [currentPage, setCurrentPage] = useState(1)
  const [paginationMeta, setPaginationMeta] = useState<AdminPaginationMeta | null>(null)
  const [contracts, setContracts] = useState<AdminContractResource[]>([])
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [rooms, setRooms] = useState<ContractRoomOption[]>([])
  const [tenants, setTenants] = useState<AdminTenantResource[]>([])
  const [vehicles, setVehicles] = useState<AdminVehicleOptionResource[]>([])
  const [editingContract, setEditingContract] = useState<AdminContractResource | null>(null)
  const [renewingContract, setRenewingContract] = useState<AdminContractResource | null>(null)
  const [detailContract, setDetailContract] = useState<AdminContractResource | null>(null)
  const [statusContract, setStatusContract] = useState<AdminContractResource | null>(null)
  const [statusForm, setStatusForm] = useState({ status: STATUS_ACTIVE, actual_end_date: '', note: '' })
  const [form, setForm] = useState<ContractFormValues>(defaultForm)
  const [errors, setErrors] = useState<ContractFormErrors>({})
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [isStatusSaving, setIsStatusSaving] = useState(false)
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [qrModalContract, setQrModalContract] = useState<AdminContractResource | null>(null)
  const [isConfirmingDeposit, setIsConfirmingDeposit] = useState(false)
  const [payingDepositContract, setPayingDepositContract] = useState<AdminContractResource | null>(null)

  const buildingOptions = useMemo(() => buildings.map((building) => ({ value: building.id, label: building.name, tone: 'default' as const })), [buildings])
  const filterBuildingOptions = useMemo(() => [{ value: '', label: isSuperAdmin ? 'Tất cả tòa nhà' : 'Tòa nhà được phân quyền', tone: 'default' as const }, ...buildingOptions], [buildingOptions, isSuperAdmin])
  const roomOptions = useMemo(() => rooms.map((room) => ({ value: room.id, label: `Phòng ${room.room_number || room.id}`, description: `Đang ở ${room.current_occupants ?? 0}/${room.max_occupants ?? '—'} người`, tone: room.status === 1 ? 'success' as const : 'warning' as const })), [rooms])
  const filterRoomOptions = useMemo(() => [{ value: '', label: 'Tất cả phòng', tone: 'default' as const }, ...roomOptions], [roomOptions])
  const tenantOptions = useMemo(() => tenants.map((tenant) => ({ value: tenant.id, label: tenant.full_name || tenant.username, description: tenant.phone || tenant.email || tenant.identity_number || undefined, tone: 'default' as const })), [tenants])
  const vehicleOptions = useMemo(() => vehicles.map((vehicle) => ({ value: vehicle.id, label: `${vehicle.license_plate || vehicle.vehicle_type_label || 'Phương tiện'}`, description: vehicle.tenant_name || undefined, tone: vehicle.is_active ? 'success' as const : 'warning' as const })), [vehicles])

  const metrics = useMemo(() => ({
    active: contracts.filter((contract) => Number(contract.status) === STATUS_ACTIVE).length,
    expired: contracts.filter((contract) => Number(contract.status) === STATUS_EXPIRED).length,
    liquidated: contracts.filter((contract) => Number(contract.status) === STATUS_LIQUIDATED).length,
  }), [contracts])

  const safeCurrentPage = Math.max(1, Math.min(currentPage, paginationMeta?.last_page ?? currentPage))
  const totalPages = Math.max(1, paginationMeta?.last_page ?? (contracts.length >= perPage ? currentPage + 1 : currentPage))
  const totalContracts = paginationMeta?.total ?? (safeCurrentPage - 1) * perPage + contracts.length
  const paginationStart = paginationMeta?.from ?? (contracts.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + 1)
  const paginationEnd = paginationMeta?.to ?? (contracts.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + contracts.length)
  const visiblePages = useMemo(() => {
    const pages = new Set<number>([1, totalPages, safeCurrentPage - 1, safeCurrentPage, safeCurrentPage + 1])
    return Array.from(pages).filter((page) => page >= 1 && page <= totalPages).sort((a, b) => a - b)
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
        setForm((current) => ({ ...current, building_id: String(list[0].id) }))
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

  const loadTenants = useCallback(async (buildingId: string) => {
    try {
      const response = await fetchAdminTenants({ building_id: buildingId ? Number(buildingId) : undefined, status: 1, without_active_contract: true, per_page: 100 })
      setTenants(getResourceList(response.result))
    } catch (error) {
      setTenants([])
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải danh sách khách thuê.'))
    }
  }, [])

  const loadVehiclesForTenants = useCallback(async (tenantIds: number[]) => {
    if (tenantIds.length === 0) {
      setVehicles([])
      return
    }

    try {
      const responses = await Promise.all(tenantIds.map((tenantId) => fetchAdminContractVehicles({ tenant_id: tenantId, is_active: true, without_active_contract: true, per_page: 100 })))
      const nextVehicles = responses.flatMap((response) => getResourceList(response.result))
      const uniqueVehicles = Array.from(new Map(nextVehicles.map((vehicle) => [vehicle.id, vehicle])).values())
      setVehicles(uniqueVehicles)
    } catch (error) {
      setVehicles([])
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải danh sách phương tiện.'))
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
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void loadBuildings()
  }, [loadBuildings])

  useEffect(() => {
    const activeBuildingId = form.building_id || selectedBuildingId
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void loadRoomsForBuilding(activeBuildingId)
    void loadTenants(activeBuildingId)
  }, [form.building_id, loadRoomsForBuilding, loadTenants, selectedBuildingId])

  useEffect(() => {
    const tenantIds = form.tenants.map((tenant) => Number(tenant.tenant_id)).filter((id) => id > 0)
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void loadVehiclesForTenants(tenantIds)
  }, [form.tenants, loadVehiclesForTenants])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadContracts()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [loadContracts])

  const updateForm = <K extends keyof ContractFormValues>(key: K, value: ContractFormValues[K]) => {
    setForm((current) => {
      const next = { ...current, [key]: value }
      if (key === 'start_date' && typeof value === 'string') {
        next.vehicles = current.vehicles.map((v) => ({
          ...v,
          started_at: value,
          billing_start_date: value
        }))
      }
      return next
    })
    setErrors((current) => ({ ...current, [key]: undefined }))
    setSuccessMessage(null)
  }

  const updateTenantRow = (index: number, patch: Partial<ContractTenantFormRow>) => {
    setForm((current) => {
      const tenants = current.tenants.map((tenant, tenantIndex) => {
        return tenantIndex === index ? { ...tenant, ...patch } : tenant
      })

      return {
        ...current,
        tenants,
      }
    })
    setErrors((current) => ({ ...current, tenants: undefined, [`tenants.${index}`]: undefined }))
  }

  const updateVehicleRow = (index: number, patch: Partial<ContractVehicleFormRow>) => {
    setForm((current) => ({
      ...current,
      vehicles: current.vehicles.map((vehicle, vehicleIndex) => vehicleIndex === index ? { ...vehicle, ...patch } : vehicle),
    }))
    setErrors((current) => ({ ...current, vehicles: undefined, [`vehicles.${index}`]: undefined }))
  }


  const openCreateForm = () => {
    const buildingId = isSuperAdmin ? selectedBuildingId : selectedBuildingId || (managedBuildingId ? String(managedBuildingId) : '')
    const today = new Date().toISOString().slice(0, 10)
    setEditingContract(null)
    setRenewingContract(null)
    setForm({
      ...defaultForm,
      building_id: buildingId,
      start_date: today,
      tenants: [{ ...defaultTenantRow, join_date: today, billing_start_date: today }],
      deposit_transactions: [],
    })
    setErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const editContract = async (contract: AdminContractResource) => {
    setEditingContract(contract)
    setRenewingContract(null)
    setIsFormOpen(true)
    setErrorMessage(null)
    setSuccessMessage(null)

    try {
      const response = await fetchAdminContractDetail(contract.id)
      const detail = response.result
      if (!detail) return
      setEditingContract(detail)
      setForm(contractToForm(detail))
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải chi tiết hợp đồng để chỉnh sửa.'))
    }
  }

  const startRenewal = async (contract: AdminContractResource) => {
    setRenewingContract(contract)
    setEditingContract(null)
    setIsFormOpen(true)
    setErrorMessage(null)
    setSuccessMessage(null)

    try {
      const response = await fetchAdminContractDetail(contract.id)
      const detail = response.result
      if (!detail) return
      setRenewingContract(detail)

      let nextStartDate = ''
      if (detail.end_date) {
        const endDateObj = new Date(detail.end_date)
        endDateObj.setDate(endDateObj.getDate() + 1)
        nextStartDate = endDateObj.toISOString().split('T')[0]
      }

      const formValues = contractToForm(detail)

      setForm({
        ...formValues,
        contract_code: '',
        start_date: nextStartDate,
        end_date: '',
        actual_end_date: '',
        parent_contract_id: String(detail.parent_contract_id || detail.id),
        renew_from_contract_id: String(detail.id),
        status: STATUS_ACTIVE,
        vehicles: formValues.vehicles.map((v) => ({
          ...v,
          started_at: nextStartDate,
          ended_at: '',
          billing_start_date: nextStartDate,
          billing_end_date: '',
        })),
        deposit_transactions: [],
      })
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải chi tiết hợp đồng để gia hạn.'))
    }
  }

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

  const submit = async () => {
    if (isSaving) return

    const selectedRoom = rooms.find((room) => String(room.id) === form.room_id)
    const roomMaxOccupants = selectedRoom ? selectedRoom.max_occupants : null
    const nextErrors = validateContractForm(form, roomMaxOccupants, isSuperAdmin)
    setErrors(nextErrors)

    if (Object.keys(nextErrors).length > 0) {
      setErrorMessage('Vui lòng kiểm tra lại thông tin hợp đồng.')
      return
    }

    try {
      setIsSaving(true)
      setErrorMessage(null)
      setSuccessMessage(null)

      const payload = buildPayload(form, !editingContract && !renewingContract)

      let qrContract: AdminContractResource | null = null

      if (editingContract) {
        const response = await updateAdminContract(editingContract.id, payload)
        setSuccessMessage(response.message || 'Cập nhật hợp đồng thành công.')
      } else if (renewingContract) {
        const response = await renewContract(renewingContract.id, payload)
        setSuccessMessage(response.message || 'Gia hạn hợp đồng thành công.')
        const createdContract = response.result
        if (createdContract && form.is_deposit_paid && form.deposit_payment_method === '2' && Number(createdContract.deposit_amount) > 0) {
          qrContract = createdContract
        }
      } else {
        const response = await createAdminContract(payload)
        setSuccessMessage(response.message || 'Tạo hợp đồng thành công.')
        const createdContract = response.result
        if (createdContract && form.is_deposit_paid && form.deposit_payment_method === '2' && Number(createdContract.deposit_amount) > 0) {
          qrContract = createdContract
        }
      }

      setEditingContract(null)
      setRenewingContract(null)
      setForm(defaultForm)
      setErrors({})
      setIsFormOpen(false)

      if (qrContract) {
        setQrModalContract(qrContract)
        setSuccessMessage('Tạo hợp đồng thành công. Vui lòng quét mã QR để đóng cọc.')
      } else {
        await loadContracts()
      }
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, editingContract ? 'Không thể cập nhật hợp đồng.' : renewingContract ? 'Không thể gia hạn hợp đồng.' : 'Không thể tạo hợp đồng.'))
    } finally {
      setIsSaving(false)
    }
  }

  const openStatusModal = (contract: AdminContractResource) => {
    const nextStatus = Number(contract.status) === STATUS_ACTIVE ? STATUS_LIQUIDATED : STATUS_LIQUIDATED
    setStatusContract(contract)
    setStatusForm({ status: nextStatus, actual_end_date: '', note: '' })
  }

  const submitStatus = async () => {
    if (!statusContract || isStatusSaving) return

    if ([STATUS_LIQUIDATED, STATUS_CANCELLED].includes(Number(statusForm.status)) && !statusForm.actual_end_date) {
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

  const removeContract = async (contract: AdminContractResource) => {
    if (!window.confirm(`Bạn có chắc chắn muốn xóa hợp đồng ${contract.contract_code}? Chỉ hợp đồng nháp hoặc đã hủy và chưa phát sinh dữ liệu liên quan mới có thể xóa.`)) return

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
          <div className="relative flex min-w-0 flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div className="min-w-0">
              <Link to="/admin/dashboard" className="mb-2 inline-flex items-center gap-2 text-xs font-black text-[#f3c56b] transition hover:text-[#ffd56f]">
                <ArrowLeft className="h-3.5 w-3.5" /> Về dashboard
              </Link>
              <h1 className="mt-3 flex items-center gap-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem]">
                <FileText className="h-9 w-9 text-[#f3c56b]" /> Quản lý hợp đồng
              </h1>

            </div>
            <button type="button" onClick={openCreateForm} className="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition hover:bg-[#ffd56f] active:scale-[0.98]">
              <Plus className="h-4 w-4" /> Thêm hợp đồng
            </button>
          </div>

          <div className="relative mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Tổng hợp đồng" value={totalContracts} tone="neutral" />
            <MetricCard label="Đang hiệu lực/trang" value={metrics.active} tone="emerald" />
            <MetricCard label="Hết hạn/trang" value={metrics.expired} tone="amber" />
            <MetricCard label="Đã thanh lý/trang" value={metrics.liquidated} tone="teal" />
          </div>
        </div>
      </section>

      {(errorMessage || successMessage) && (
        <div className={cn('rounded-3xl border px-4 py-3 text-sm font-black shadow-sm', errorMessage ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700')}>
          {errorMessage || successMessage}
        </div>
      )}

      <div className={cn('grid min-w-0 grid-cols-1 gap-4 xl:gap-6', isFormOpen && '2xl:grid-cols-[minmax(0,1fr)_460px]')}>
        <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
          <div className="border-b border-[#3d2a18]/10 bg-[#fff8eb]/85 p-4 sm:p-5">
            <div className="grid gap-3 xl:grid-cols-[minmax(18rem,1fr)_minmax(10rem,13rem)_minmax(10rem,13rem)_minmax(10rem,13rem)]">
              <div className="relative min-w-0">
                <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
                <input type="text" value={keyword} onChange={(event) => { setKeyword(event.target.value); setCurrentPage(1) }} placeholder="Tìm mã HĐ, phòng, tòa nhà, khách đại diện, SĐT..." className={`${inputClass} pl-11 pr-28`} />
                <button type="button" onClick={clearFilters} disabled={!hasActiveFilters} className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] disabled:opacity-45">
                  <X className="h-3.5 w-3.5" /> Xóa lọc
                </button>
              </div>
              <AdminSelect value={selectedStatus} options={statusOptions} onChange={(nextValue) => { setSelectedStatus(String(nextValue)); setCurrentPage(1) }} />
              <AdminSelect value={selectedBuildingId} options={filterBuildingOptions} disabled={!isSuperAdmin && buildingOptions.length <= 1} onChange={(nextValue) => { setSelectedBuildingId(String(nextValue)); setSelectedRoomId(''); setCurrentPage(1) }} />
              <AdminSelect value={selectedRoomId} options={filterRoomOptions} disabled={!selectedBuildingId} onChange={(nextValue) => { setSelectedRoomId(String(nextValue)); setCurrentPage(1) }} />
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full min-w-[1180px] text-left">
              <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
                <tr>
                  <th className="px-5 py-4">Hợp đồng</th>
                  <th className="px-5 py-4">Phòng / Tòa nhà</th>
                  <th className="px-5 py-4">Khách thuê</th>
                  <th className="px-5 py-4">Thời hạn</th>
                  <th className="px-5 py-4">Giá / Cọc</th>
                  <th className="px-5 py-4 text-center">Dữ liệu</th>
                  <th className="px-5 py-4">Trạng thái</th>
                  <th className="px-5 py-4 text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[#3d2a18]/8 bg-[#fffaf1]/70">
                {isLoading && Array.from({ length: 5 }).map((_, index) => (
                  <tr key={index}>
                    <td colSpan={8} className="px-5 py-4"><div className="h-14 animate-pulse rounded-2xl bg-stone-100" /></td>
                  </tr>
                ))}

                {!isLoading && contracts.map((contract) => (
                  <tr key={contract.id} className="transition hover:bg-[#f3c56b]/10">
                    <td className="px-5 py-4">
                      <p className="text-sm font-black text-[#24170d]">{contract.contract_code}</p>
                      <p className="mt-1 text-xs font-bold text-[#8b5e34]/70">tạo {formatDate(contract.created_at)}</p>
                    </td>
                    <td className="px-5 py-4">
                      <div className="space-y-1 text-xs font-black text-[#6f6254]">
                        <p className="flex items-center gap-1.5 text-[#8a4f18]"><Building2 className="h-4 w-4" /> {contract.building_name || 'Chưa rõ tòa nhà'}</p>
                        <p className="text-[#24170d]">Phòng {contract.room_number || contract.room_code || contract.room_id}</p>
                      </div>
                    </td>
                    <td className="px-5 py-4 max-w-[200px] truncate" title={contract.contract_tenants?.map((ct) => ct.tenant?.full_name).filter(Boolean).join(', ') || 'Chưa có'}>
                      <p className="text-sm font-black text-[#24170d]">{contract.contract_tenants?.map((ct) => ct.tenant?.full_name).filter(Boolean).join(', ') || 'Chưa có'}</p>
                    </td>
                    <td className="px-5 py-4">
                      <p className="flex items-center gap-1.5 text-xs font-black text-[#0f5f59]"><CalendarDays className="h-4 w-4" /> {formatDate(contract.start_date)} → {formatDate(contract.end_date)}</p>
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
                    <td className="px-5 py-4"><StatusBadge status={contract.status} label={contract.status_label || getStatusLabel(contract.status)} /></td>
                    <td className="px-5 py-4">
                      <div className="flex items-center justify-end gap-2">
                        <IconButton title="Xem chi tiết" onClick={() => void viewContract(contract)}><Eye className="h-5 w-5" /></IconButton>
                        {([STATUS_ACTIVE, STATUS_EXPIRED].includes(Number(contract.status))) && (
                          <IconButton title="Gia hạn" onClick={() => void startRenewal(contract)}><CalendarPlus className="h-5 w-5" /></IconButton>
                        )}
                        <IconButton title="Chỉnh sửa" onClick={() => void editContract(contract)}><Edit3 className="h-5 w-5" /></IconButton>
                        <IconButton title="Đổi trạng thái" onClick={() => openStatusModal(contract)}><Power className="h-5 w-5" /></IconButton>
                        <IconButton title="Xóa" disabled={deletingId === contract.id} danger onClick={() => void removeContract(contract)}><Trash2 className="h-5 w-5" /></IconButton>
                      </div>
                    </td>
                  </tr>
                ))}

                {!isLoading && contracts.length === 0 && (
                  <tr>
                    <td colSpan={8} className="px-5 py-20 text-center">
                      <div className="mx-auto flex max-w-sm flex-col items-center rounded-[2rem] border border-dashed border-[#3d2a18]/12 bg-white/55 px-6 py-8">
                        <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]"><FileText className="h-9 w-9" /></div>
                        <p className="text-lg font-black tracking-tight text-[#24170d]">Không tìm thấy hợp đồng</p>
                        <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">{hasActiveFilters ? 'Thử xóa bộ lọc hoặc đổi từ khóa tìm kiếm.' : 'Hãy tạo hợp đồng đầu tiên cho phòng đang quản lý.'}</p>
                      </div>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb]/85 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
            <p className="text-xs font-black text-[#6f6254]">Hiển thị <span className="tabular-nums text-[#24170d]">{paginationStart}</span>-<span className="tabular-nums text-[#24170d]">{paginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{totalContracts}</span> hợp đồng</p>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
              <div className="w-full sm:w-36"><AdminSelect value={perPage} options={perPageOptions} onChange={(nextValue) => { setPerPage(Number(nextValue)); setCurrentPage(1) }} menuPlacement="top" /></div>
              <div className="flex items-center justify-end gap-1.5">
                <button type="button" disabled={safeCurrentPage <= 1} onClick={() => setCurrentPage(safeCurrentPage - 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:opacity-45"><ChevronLeft className="h-4 w-4" /></button>
                {visiblePages.map((page) => <button key={page} type="button" onClick={() => setCurrentPage(page)} className={cn('inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition', page === safeCurrentPage ? 'border-[#24170d] bg-[#24170d] text-[#fff4df]' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15')}>{page}</button>)}
                <button type="button" disabled={safeCurrentPage >= totalPages} onClick={() => setCurrentPage(safeCurrentPage + 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:opacity-45"><ChevronRight className="h-4 w-4" /></button>
              </div>
            </div>
          </div>
        </section>

        {isFormOpen && (
          <ContractFormPanel
            editing={Boolean(editingContract)}
            renewing={Boolean(renewingContract)}
            form={form}
            errors={errors}
            isSaving={isSaving}
            isSuperAdmin={isSuperAdmin}
            buildingOptions={buildingOptions}
            roomOptions={roomOptions}
            tenantOptions={tenantOptions}
            vehicleOptions={vehicleOptions}
            onUpdate={updateForm}
            onUpdateTenant={updateTenantRow}
            onUpdateVehicle={updateVehicleRow}
            onAddTenant={() => updateForm('tenants', [...form.tenants, { ...defaultTenantRow, join_date: form.start_date, billing_start_date: form.start_date }])}
            onRemoveTenant={(index) => updateForm('tenants', form.tenants.filter((_, rowIndex) => rowIndex !== index))}
            onAddVehicle={() => updateForm('vehicles', [...form.vehicles, { vehicle_id: '', started_at: form.start_date, ended_at: '', billing_start_date: form.start_date, billing_end_date: '', monthly_fee: '0.00', charge_policy: CHARGE_MONTHLY, is_active: true }])}
            onRemoveVehicle={(index) => updateForm('vehicles', form.vehicles.filter((_, rowIndex) => rowIndex !== index))}
            onSubmit={() => void submit()}
            onReset={openCreateForm}
            onClose={() => { setIsFormOpen(false); setEditingContract(null); setRenewingContract(null); setErrors({}) }}
          />
        )}
      </div>

      {detailContract && (
        <ContractDetailModal
          contract={detailContract}
          isLoading={isDetailLoading}
          errorMessage={detailErrorMessage}
          onClose={() => { setDetailContract(null); setDetailErrorMessage(null) }}
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
                note: method === 1 ? 'Thu cọc bằng tiền mặt' : 'Xác nhận thu cọc chuyển khoản QR tại chỗ'
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
          isSaving={isConfirmingDeposit}
          onClose={() => {
            setQrModalContract(null)
            void loadContracts()
          }}
          onConfirm={async () => {
            try {
              setIsConfirmingDeposit(true)
              await createAdminContractDepositTransaction(qrModalContract.id, {
                transaction_type: 1, // COLLECT
                amount: qrModalContract.deposit_amount || '0.00',
                transaction_date: new Date().toISOString().slice(0, 10),
                payment_method: 2, // QR
                note: 'Xác nhận thu cọc chuyển khoản QR tại chỗ khi ký hợp đồng'
              })
              setQrModalContract(null)
              void loadContracts()
            } catch (error) {
              alert(getVisibleErrorMessage(error, 'Không thể ghi nhận giao dịch cọc.'))
            } finally {
              setIsConfirmingDeposit(false)
            }
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
    </section>
  )
}

function ContractFormPanel({
  editing,
  renewing,
  form,
  errors,
  isSaving,
  isSuperAdmin,
  buildingOptions,
  roomOptions,
  tenantOptions,
  vehicleOptions,
  onUpdate,
  onUpdateTenant,
  onUpdateVehicle,
  onAddTenant,
  onRemoveTenant,
  onAddVehicle,
  onRemoveVehicle,
  onSubmit,
  onReset,
  onClose,
}: {
  editing: boolean
  renewing: boolean
  form: ContractFormValues
  errors: ContractFormErrors
  isSaving: boolean
  isSuperAdmin: boolean
  buildingOptions: Array<{ value: string | number; label: string; tone?: 'default' | 'success' | 'warning' | 'danger'; description?: string }>
  roomOptions: Array<{ value: string | number; label: string; tone?: 'default' | 'success' | 'warning' | 'danger'; description?: string }>
  tenantOptions: Array<{ value: string | number; label: string; tone?: 'default' | 'success' | 'warning' | 'danger'; description?: string }>
  vehicleOptions: Array<{ value: string | number; label: string; tone?: 'default' | 'success' | 'warning' | 'danger'; description?: string }>
  onUpdate: <K extends keyof ContractFormValues>(key: K, value: ContractFormValues[K]) => void
  onUpdateTenant: (index: number, patch: Partial<ContractTenantFormRow>) => void
  onUpdateVehicle: (index: number, patch: Partial<ContractVehicleFormRow>) => void
  onAddTenant: () => void
  onRemoveTenant: (index: number) => void
  onAddVehicle: () => void
  onRemoveVehicle: (index: number) => void
  onSubmit: () => void
  onReset: () => void
  onClose: () => void
}) {
  return (
    <aside className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/95 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md 2xl:sticky 2xl:top-6 2xl:self-start">
      <div className="border-b border-[#3d2a18]/10 bg-[#fff8eb]/85 p-5">
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/60">Hồ sơ hợp đồng</p>
            <h2 className="mt-1 text-xl font-black tracking-tight text-[#24170d]">{editing ? 'Cập nhật hợp đồng' : renewing ? 'Gia hạn hợp đồng' : 'Thêm hợp đồng'}</h2>
          </div>
          <div className="flex gap-2">
            <button type="button" onClick={onReset} className="rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] p-2 text-[#8b5e34] transition hover:bg-[#f3c56b]/15"><RefreshCw className="h-4 w-4" /></button>
            <button type="button" onClick={onClose} className="rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] p-2 text-[#8b5e34] transition hover:bg-rose-50 hover:text-rose-600"><X className="h-4 w-4" /></button>
          </div>
        </div>
      </div>

      <div className="max-h-[calc(100dvh-12rem)] space-y-4 overflow-y-auto p-5">
        {isSuperAdmin && (
          <div>
            <label className={labelClass}>Tòa nhà <span className="text-rose-500">*</span></label>
            <AdminSelect value={form.building_id} options={buildingOptions} invalid={!!errors.building_id} placeholder="Chọn tòa nhà" onChange={(nextValue) => { onUpdate('building_id', String(nextValue)); onUpdate('room_id', '') }} />
            <FieldError message={errors.building_id} />
          </div>
        )}

        <div>
          <label className={labelClass}>Phòng <span className="text-rose-500">*</span></label>
          <AdminSelect value={form.room_id} options={roomOptions} disabled={!form.building_id && isSuperAdmin} invalid={!!errors.room_id} placeholder="Chọn phòng" onChange={(nextValue) => onUpdate('room_id', String(nextValue))} />
          <FieldError message={errors.room_id} />
        </div>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <label className={labelClass}>Ngày bắt đầu</label>
            <AdminDateInput className={cn(inputClass, errors.start_date && inputErrorClass)} value={form.start_date} onChange={(value) => onUpdate('start_date', value)} />
            <FieldError message={errors.start_date} />
          </div>
          <div>
            <label className={labelClass}>Ngày kết thúc</label>
            <AdminDateInput className={cn(inputClass, errors.end_date && inputErrorClass)} value={form.end_date} onChange={(value) => onUpdate('end_date', value)} minDate={toDate(form.start_date)} />
            <FieldError message={errors.end_date} />
          </div>
        </div>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <label className={labelClass}>Ngày chốt tiền</label>
            <input className={cn(inputClass, errors.billing_cycle_day && inputErrorClass)} value={form.billing_cycle_day} onChange={(event) => onUpdate('billing_cycle_day', event.target.value)} type="number" min={1} max={28} />
            <FieldError message={errors.billing_cycle_day} />
          </div>
          {!editing && !renewing && (
            <div>
              <label className={labelClass}>Trạng thái tạo</label>
              <AdminSelect value={form.status} options={createStatusOptions} invalid={!!errors.status} onChange={(nextValue) => onUpdate('status', Number(nextValue))} />
              <FieldError message={errors.status} />
            </div>
          )}
        </div>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <label className={labelClass}>Giá phòng</label>
            <input className={cn(inputClass, errors.room_price && inputErrorClass)} value={form.room_price} onChange={(event) => onUpdate('room_price', event.target.value)} placeholder="3500000.00" />
            <FieldError message={errors.room_price} />
          </div>
          <div>
            <label className={labelClass}>Tiền cọc</label>
            <input className={cn(inputClass, errors.deposit_amount && inputErrorClass)} value={form.deposit_amount} onChange={(event) => onUpdate('deposit_amount', event.target.value)} placeholder="3500000.00" />
            <FieldError message={errors.deposit_amount} />
          </div>
        </div>

        {Number(form.deposit_amount) > 0 && (
          <div className="flex flex-col gap-2 p-3 rounded-2xl border border-[#3d2a18]/10 bg-white/40">
            <label className="inline-flex items-center gap-2 text-xs font-black text-[#6f6254]">
              <input type="checkbox" checked={form.is_deposit_paid} onChange={(e) => onUpdate('is_deposit_paid', e.target.checked)} />
              Khách đã đóng tiền cọc khi ký hợp đồng
            </label>
            {form.is_deposit_paid && (
              <div className="w-full sm:w-1/2">
                <label className="mb-1.5 block px-1 text-[9px] font-black uppercase tracking-widest text-[#8b5e34]/65">Phương thức đóng cọc</label>
                <AdminSelect
                  value={form.deposit_payment_method}
                  options={[
                    { value: '1', label: 'Tiền mặt' },
                    { value: '2', label: 'Chuyển khoản QR' }
                  ]}
                  onChange={(value) => onUpdate('deposit_payment_method', String(value))}
                />
              </div>
            )}
          </div>
        )}

        <section className="rounded-2xl border border-[#3d2a18]/10 bg-white/55 p-3">
          <div className="mb-3 flex items-center justify-between">
            <p className={labelClass}>Khách thuê</p>
            <button type="button" onClick={onAddTenant} className="rounded-xl bg-[#24170d] px-3 py-2 text-xs font-black text-[#fff4df]"><UserPlus className="mr-1 inline h-3.5 w-3.5" />Thêm</button>
          </div>
          <FieldError message={errors.tenants} />
          <div className="space-y-3">
            {form.tenants.map((tenant, index) => (
              <div key={index} className="rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3">
                <div className="flex items-center justify-between gap-2">
                  <p className="text-xs font-black text-[#24170d]">Khách #{index + 1}</p>
                  {form.tenants.length > 1 && <button type="button" onClick={() => onRemoveTenant(index)} className="text-xs font-black text-rose-600">Xóa</button>}
                </div>
                <div className="mt-3 space-y-3">
                  <AdminSelect value={tenant.tenant_id} options={tenantOptions} invalid={!!errors[`tenants.${index}`]} placeholder="Chọn khách thuê" onChange={(value) => onUpdateTenant(index, { tenant_id: String(value) })} />
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <AdminDateInput className={inputClass} value={tenant.join_date} onChange={(value) => onUpdateTenant(index, { join_date: value, billing_start_date: tenant.billing_start_date || value })} />
                    <AdminDateInput className={inputClass} value={tenant.leave_date} onChange={(value) => onUpdateTenant(index, { leave_date: value, is_staying: !value })} placeholder="Ngày rời đi" />
                  </div>
                  <div className="flex flex-wrap gap-3 text-xs font-black text-[#6f6254]">
                    <label className="inline-flex items-center gap-2"><input type="checkbox" checked={tenant.is_staying} onChange={(event) => onUpdateTenant(index, { is_staying: event.target.checked })} /> Đang ở</label>
                  </div>
                  <FieldError message={errors[`tenants.${index}`]} />
                </div>
              </div>
            ))}
          </div>
        </section>

        <section className="rounded-2xl border border-[#3d2a18]/10 bg-white/55 p-3">
          <div className="mb-3 flex items-center justify-between">
            <p className={labelClass}>Phương tiện</p>
            <button type="button" onClick={onAddVehicle} className="rounded-xl bg-[#24170d] px-3 py-2 text-xs font-black text-[#fff4df]"><Car className="mr-1 inline h-3.5 w-3.5" />Thêm</button>
          </div>
          <FieldError message={errors.vehicles} />
          {form.vehicles.length === 0 && <p className="text-xs font-bold text-[#8b5e34]/70">Chưa thêm phương tiện vào hợp đồng.</p>}
          <div className="space-y-3">
            {form.vehicles.map((vehicle, index) => (
              <div key={index} className="rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3">
                <div className="flex items-center justify-between gap-2">
                  <p className="text-xs font-black text-[#24170d]">Xe #{index + 1}</p>
                  <button type="button" onClick={() => onRemoveVehicle(index)} className="text-xs font-black text-rose-600">Xóa</button>
                </div>
                <div className="mt-3 space-y-3">
                  <AdminSelect value={vehicle.vehicle_id} options={vehicleOptions} invalid={!!errors[`vehicles.${index}`]} placeholder="Chọn phương tiện" onChange={(value) => onUpdateVehicle(index, { vehicle_id: String(value) })} />
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <AdminDateInput className={inputClass} value={vehicle.started_at} disabled onChange={(value) => onUpdateVehicle(index, { started_at: value, billing_start_date: vehicle.billing_start_date || value })} />
                    <AdminDateInput className={inputClass} value={vehicle.ended_at} onChange={(value) => onUpdateVehicle(index, { ended_at: value, is_active: !value })} placeholder="Ngày kết thúc" />
                  </div>
                  <AdminSelect value={vehicle.charge_policy} options={chargePolicyOptions} onChange={(value) => onUpdateVehicle(index, { charge_policy: Number(value), monthly_fee: Number(value) === CHARGE_FREE ? '0.00' : vehicle.monthly_fee })} />
                  <input className={cn(inputClass, errors[`vehicles.${index}`] && inputErrorClass)} value={vehicle.monthly_fee} onChange={(event) => onUpdateVehicle(index, { monthly_fee: event.target.value })} placeholder="Phí gửi xe" disabled={Number(vehicle.charge_policy) === CHARGE_FREE} />
                  <label className="inline-flex items-center gap-2 text-xs font-black text-[#6f6254]"><input type="checkbox" checked={vehicle.is_active} onChange={(event) => onUpdateVehicle(index, { is_active: event.target.checked })} /> Còn tính phí</label>
                  <FieldError message={errors[`vehicles.${index}`]} />
                </div>
              </div>
            ))}
          </div>
        </section>



        <div>
          <label className={labelClass}>File hợp đồng</label>
          <label className="flex min-h-14 cursor-pointer items-center justify-center gap-2 rounded-2xl border border-dashed border-[#3d2a18]/15 bg-white/55 px-4 py-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/15">
            <FileText className="h-4 w-4" /> Chọn PDF/ảnh hợp đồng
            <input type="file" className="hidden" multiple accept="application/pdf,image/jpeg,image/png,image/webp" onChange={(event) => onUpdate('contract_files', Array.from(event.target.files || []))} />
          </label>
          {form.contract_files.length > 0 && <p className="mt-2 text-xs font-bold text-[#6f6254]">{form.contract_files.length} file đã chọn.</p>}
          <FieldError message={errors.contract_files} />
        </div>

        <div>
          <label className={labelClass}>Ghi chú</label>
          <textarea className={cn(inputClass, 'min-h-24 resize-none', errors.note && inputErrorClass)} value={form.note} onChange={(event) => onUpdate('note', event.target.value)} placeholder="Ghi chú điều khoản hoặc tình trạng hợp đồng" />
          <FieldError message={errors.note} />
        </div>

        <button type="button" disabled={isSaving} onClick={onSubmit} className="inline-flex min-h-14 items-center justify-center gap-2 rounded-[1.25rem] bg-[#24170d] px-5 py-3.5 text-base font-black text-[#fff4df] shadow-lg shadow-[#24170d]/12 transition hover:bg-[#3d2a18] disabled:opacity-60">
          <BadgeCheck className="h-5 w-5" /> {isSaving ? 'Đang lưu...' : editing ? 'Cập nhật hợp đồng' : renewing ? 'Gia hạn hợp đồng' : 'Tạo hợp đồng'}
        </button>
      </div>
    </aside>
  )
}

function ContractDetailModal({
  contract,
  isLoading,
  errorMessage,
  onClose,
  onPayDeposit
}: {
  contract: AdminContractResource
  isLoading: boolean
  errorMessage: string | null
  onClose: () => void
  onPayDeposit: (contract: AdminContractResource) => void
}) {
  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true">
      <button type="button" aria-label="Đóng chi tiết hợp đồng" onClick={onClose} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />
      <div className="relative z-10 w-full max-w-6xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-stone-950/30">
        <div className="bg-[#24170d] p-5 text-[#fff4df]">
          <div className="flex items-start justify-between gap-4">
            <div>
              <p className="text-[10px] font-black uppercase tracking-[0.24em] text-[#f3c56b]">Chi tiết hợp đồng</p>
              <h2 className="mt-2 text-2xl font-black tracking-tight">{contract.contract_code}</h2>
            </div>
            <button type="button" onClick={onClose} className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20"><X className="h-5 w-5" /></button>
          </div>
        </div>

        <div className="max-h-[calc(100dvh-12rem)] space-y-4 overflow-y-auto p-5">
          {isLoading && <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-800">Đang tải chi tiết hợp đồng...</div>}
          {errorMessage && <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{errorMessage}</div>}

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-5">
            <DetailTile label="Trạng thái" value={contract.status_label || getStatusLabel(contract.status)} />
            <DetailTile
              label="Trạng thái cọc"
              value={
                <span className={
                  contract.payment_status === 2 // SUCCESS
                    ? "text-emerald-600 font-bold"
                    : contract.payment_status === 3 // CANCELLED
                    ? "text-rose-600 font-bold"
                    : contract.payment_status === 4 // EXPIRED
                    ? "text-red-600 font-bold"
                    : "text-amber-600 font-bold" // PENDING / others
                }>
                  {contract.payment_status_label || (contract.is_deposit_paid ? "Đã đóng cọc" : "Chưa đóng cọc")}
                </span>
              }
            />
            <DetailTile label="Phòng" value={`Phòng ${contract.room?.room_number || contract.room_number || contract.room_id}`} />
            <DetailTile label="Tòa nhà" value={contract.room?.building_name || contract.building_name || '—'} />
            <DetailTile label="Người tạo" value={contract.creator_name || '—'} />
          </div>

          <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
            <p className={labelClass}>Thông tin tài chính & thời hạn</p>
            <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-4">
              <DetailTile label="Thời hạn" value={`${formatDate(contract.start_date)} → ${formatDate(contract.end_date)}`} />
              <DetailTile label="Ngày kết thúc thực tế" value={formatDate(contract.actual_end_date)} />
              <DetailTile label="Giá phòng" value={formatCurrency(contract.room_price)} />
              <DetailTile label="Tiền cọc" value={formatCurrency(contract.deposit_amount)} />
            </div>
          </section>

          <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
            <p className={labelClass}>Khách thuê trong hợp đồng</p>
            <div className="mt-3 overflow-x-auto">
              <table className="w-full min-w-[720px] text-left text-sm">
                <thead className="text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/70"><tr><th className="py-2">Khách thuê</th><th>Ngày ở</th><th>Tính tiền</th><th>Trạng thái</th></tr></thead>
                <tbody className="divide-y divide-[#3d2a18]/10">
                  {(contract.contract_tenants || []).map((tenant) => <tr key={tenant.id || tenant.tenant_id}><td className="py-3 font-black">{tenant.tenant?.full_name || tenant.tenant_id}</td><td>{formatDate(tenant.join_date)} → {formatDate(tenant.leave_date)}</td><td>{formatDate(tenant.billing_start_date)} → {formatDate(tenant.billing_end_date)}</td><td>{tenant.is_staying ? 'Đang ở' : 'Đã rời'}</td></tr>)}
                </tbody>
              </table>
              {(contract.contract_tenants || []).length === 0 && <p className="py-4 text-sm font-bold text-[#8b5e34]/70">Chưa có dữ liệu khách thuê.</p>}
            </div>
          </section>

          <section className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
              <p className={labelClass}>Phương tiện</p>
              <div className="mt-3 space-y-2">
                {(contract.contract_vehicles || []).map((vehicle) => <div key={vehicle.id || vehicle.vehicle_id} className="rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3 text-sm font-bold"><p className="font-black text-[#24170d]">{vehicle.vehicle?.license_plate || vehicle.vehicle_id}</p><p className="text-xs text-[#6f6254]">{vehicle.charge_policy_label} · {formatCurrency(vehicle.monthly_fee)} · {vehicle.is_active ? 'Còn tính phí' : 'Hết tính phí'}</p></div>)}
                {(contract.contract_vehicles || []).length === 0 && <p className="text-sm font-bold text-[#8b5e34]/70">Chưa có phương tiện.</p>}
              </div>
            </div>
            <div className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
              <div className="flex items-center justify-between">
                <p className={labelClass}>Giao dịch cọc</p>
                {!contract.is_deposit_paid && Number(contract.deposit_amount) > 0 && (
                  <button
                    type="button"
                    onClick={() => onPayDeposit(contract)}
                    className="rounded-xl bg-[#24170d] px-3 py-1.5 text-xs font-black text-[#fff4df] transition hover:bg-[#3d2a18] active:scale-95"
                  >
                    Đóng cọc
                  </button>
                )}
              </div>
              <div className="mt-3 space-y-2">
                {(contract.deposit_transactions || []).map((transaction) => <div key={transaction.id} className="rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3 text-sm font-bold"><p className="font-black text-[#24170d]">{transaction.transaction_type_label} · {formatCurrency(transaction.amount)}</p><p className="text-xs text-[#6f6254]">{formatDate(transaction.transaction_date)} · {transaction.payment_method_label} · {transaction.creator_name || '—'}</p></div>)}
                {(contract.deposit_transactions || []).length === 0 && <p className="text-sm font-bold text-[#8b5e34]/70">Chưa có giao dịch cọc.</p>}
              </div>
            </div>
          </section>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <DetailTile label="Chuyển phòng" value={contract.room_movements_count ?? 0} />
            <DetailTile label="Cập nhật" value={formatDateTime(contract.updated_at)} />
          </div>

          {contract.note && <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4"><p className={labelClass}>Ghi chú</p><p className="whitespace-pre-wrap text-sm font-bold text-[#3d2a18]">{contract.note}</p></section>}
        </div>
      </div>
    </div>
  )
}

function StatusModal({ contract, form, isSaving, onChange, onClose, onSubmit }: { contract: AdminContractResource; form: { status: number; actual_end_date: string; note: string }; isSaving: boolean; onChange: (value: { status: number; actual_end_date: string; note: string }) => void; onClose: () => void; onSubmit: () => void }) {
  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center p-4">
      <button type="button" className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" onClick={onClose} aria-label="Đóng cập nhật trạng thái" />
      <div className="relative z-10 w-full max-w-md rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] p-5 shadow-2xl">
        <h2 className="text-lg font-black text-[#24170d]">Cập nhật trạng thái hợp đồng</h2>
        <p className="mt-1 text-xs font-bold text-[#8b5e34]/70">{contract.contract_code} · trạng thái hiện tại: {contract.status_label || getStatusLabel(contract.status)}</p>
        <div className="mt-5 space-y-4">
          <div><label className={labelClass}>Trạng thái mới</label><AdminSelect value={form.status} options={statusChangeOptions} onChange={(value) => onChange({ ...form, status: Number(value) })} /></div>
          <div><label className={labelClass}>Ngày kết thúc thực tế</label><AdminDateInput className={inputClass} value={form.actual_end_date} onChange={(value) => onChange({ ...form, actual_end_date: value })} /></div>
          <div><label className={labelClass}>Ghi chú</label><textarea className={cn(inputClass, 'min-h-24')} value={form.note} onChange={(event) => onChange({ ...form, note: event.target.value })} placeholder="Lý do đổi trạng thái" /></div>
          <div className="flex gap-3"><button type="button" onClick={onClose} className="h-12 flex-1 rounded-xl border border-[#3d2a18]/10 bg-white text-sm font-black text-[#8b5e34]">Hủy</button><button type="button" disabled={isSaving} onClick={onSubmit} className="h-12 flex-1 rounded-xl bg-[#24170d] text-sm font-black text-[#fff4df] disabled:opacity-60">{isSaving ? 'Đang lưu...' : 'Cập nhật'}</button></div>
        </div>
      </div>
    </div>
  )
}

function MetricCard({ label, value, tone }: { label: string; value: number; tone: 'neutral' | 'emerald' | 'amber' | 'teal' }) {
  const toneClassNames = {
    neutral: 'border-[#f8e8c8]/12 bg-[#f8e8c8]/10 text-[#fff4df]',
    emerald: 'border-[#0f766e]/35 bg-[#0f766e]/16 text-[#c8fff4]',
    amber: 'border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#fff4df]',
    teal: 'border-cyan-200/25 bg-cyan-100/10 text-cyan-50',
  }[tone]

  return <div className={cn('rounded-3xl border px-4 py-3 backdrop-blur', toneClassNames)}><p className="text-[10px] font-black uppercase tracking-[0.18em] opacity-65">{label}</p><p className="mt-1 text-3xl font-black tracking-tight tabular-nums">{value}</p></div>
}

function IconButton({ title, disabled, danger, onClick, children }: { title: string; disabled?: boolean; danger?: boolean; onClick: () => void; children: ReactNode }) {
  return <button type="button" disabled={disabled} onClick={onClick} className={cn('inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition focus:outline-none focus:ring-4 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45', danger ? 'hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus:ring-rose-100' : 'hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:ring-[#3d2a18]/10')} title={title} aria-label={title}>{children}</button>
}

function DetailTile({ label, value }: { label: string; value?: React.ReactNode }) {
  return <div className="min-w-0 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3 shadow-sm"><p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/60">{label}</p><div className="mt-1 break-words text-sm font-black text-[#24170d]">{value ?? '—'}</div></div>
}

function StatusBadge({ status, label }: { status: number; label: string }) {
  const className = Number(status) === STATUS_ACTIVE
    ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]'
    : Number(status) === STATUS_CANCELLED
      ? 'border-rose-200 bg-rose-50 text-rose-700'
      : 'border-[#f3c56b]/45 bg-[#f3c56b]/18 text-[#8a4f18]'

  return <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-xs font-black shadow-sm', className)}>{label}</span>
}

function FieldError({ message }: { message?: string }) {
  if (!message) return null
  return <p className="mt-1.5 px-1 text-xs font-bold text-rose-600">{message}</p>
}

function normalizeContracts(result: ContractsResult) {
  if (!result) return { data: [] as AdminContractResource[], meta: null as AdminPaginationMeta | null }
  if (Array.isArray(result)) return { data: result, meta: null }
  return { data: result.data || [], meta: result.meta || null }
}

function getResourceList<T>(result: { data?: T[] } | T[] | null | undefined): T[] {
  if (!result) return []
  if (Array.isArray(result)) return result
  return result.data || []
}

function getVisibleErrorMessage(error: unknown, fallback: string) {
  if (error instanceof ApiError) return error.message || fallback
  if (error instanceof Error) return error.message
  return fallback
}

function getStatusLabel(status?: number | null) {
  if (Number(status) === STATUS_ACTIVE) return 'Đang hiệu lực'
  if (Number(status) === STATUS_EXPIRED) return 'Hết hạn'
  if (Number(status) === STATUS_LIQUIDATED) return 'Đã thanh lý'
  if (Number(status) === STATUS_CANCELLED) return 'Đã hủy'
  return 'Không xác định'
}

function toDate(value: string) {
  if (!value) return undefined
  const [year, month, day] = value.split('-').map(Number)
  return new Date(year, month - 1, day)
}

function contractToForm(contract: AdminContractResource): ContractFormValues {
  const tenants = (contract.contract_tenants || []).map((tenant) => ({
    tenant_id: String(tenant.tenant_id),
    join_date: tenant.join_date || '',
    leave_date: tenant.leave_date || '',
    billing_start_date: tenant.billing_start_date || '',
    billing_end_date: tenant.billing_end_date || '',
    is_staying: tenant.is_staying !== false,
  }))

  return {
    contract_code: contract.contract_code || '',
    building_id: String(contract.room?.building_id || contract.building_id || ''),
    room_id: String(contract.room_id || ''),
    start_date: contract.start_date || '',
    end_date: contract.end_date || '',
    actual_end_date: contract.actual_end_date || '',
    billing_cycle_day: contract.billing_cycle_day ? String(contract.billing_cycle_day) : '1',
    room_price: contract.room_price || '',
    deposit_amount: contract.deposit_amount || '0.00',
    status: Number(contract.status || STATUS_ACTIVE),
    contract_files: [],
    delete_contract_files: [],
    note: contract.note || '',
    parent_contract_id: contract.parent_contract_id ? String(contract.parent_contract_id) : '',
    renew_from_contract_id: contract.renew_from_contract_id ? String(contract.renew_from_contract_id) : '',
    tenants: tenants.length > 0 ? tenants : [{ ...defaultTenantRow }],
    vehicles: (contract.contract_vehicles || []).map((vehicle) => ({
      vehicle_id: String(vehicle.vehicle_id),
      started_at: vehicle.started_at || '',
      ended_at: vehicle.ended_at || '',
      billing_start_date: vehicle.billing_start_date || '',
      billing_end_date: vehicle.billing_end_date || '',
      monthly_fee: vehicle.monthly_fee || '0.00',
      charge_policy: Number(vehicle.charge_policy || CHARGE_MONTHLY),
      is_active: vehicle.is_active !== false,
    })),
    deposit_transactions: [],
    is_deposit_paid: contract.is_deposit_paid !== false,
    deposit_payment_method: String(contract.deposit_transactions?.[0]?.payment_method || '2'),
  }
}

function buildPayload(form: ContractFormValues, includeStatus: boolean): AdminContractPayload {
  const isQr = form.is_deposit_paid && form.deposit_payment_method === '2'
  const payload: AdminContractPayload = {
    contract_code: form.contract_code.trim(),
    room_id: Number(form.room_id),
    start_date: form.start_date,
    end_date: form.end_date,
    actual_end_date: form.actual_end_date || null,
    billing_cycle_day: Number(form.billing_cycle_day),
    room_price: form.room_price.trim(),
    deposit_amount: form.deposit_amount.trim(),
    contract_files: form.contract_files,
    delete_contract_files: form.delete_contract_files,
    note: form.note.trim() || null,
    tenants: form.tenants.map((tenant) => ({
      tenant_id: Number(tenant.tenant_id),
      join_date: tenant.join_date,
      leave_date: tenant.leave_date || null,
      billing_start_date: tenant.billing_start_date || tenant.join_date,
      billing_end_date: tenant.billing_end_date || tenant.leave_date || null,
      is_staying: tenant.is_staying,
    })),
    vehicles: form.vehicles.map((vehicle) => ({
      vehicle_id: Number(vehicle.vehicle_id),
      started_at: vehicle.started_at,
      ended_at: vehicle.ended_at || null,
      billing_start_date: vehicle.billing_start_date || vehicle.started_at,
      billing_end_date: vehicle.billing_end_date || vehicle.ended_at || null,
      monthly_fee: Number(vehicle.charge_policy) === CHARGE_FREE ? '0.00' : vehicle.monthly_fee.trim(),
      charge_policy: Number(vehicle.charge_policy),
      is_active: vehicle.is_active,
    })),
    deposit_transactions: form.deposit_transactions.map((transaction) => ({
      transaction_type: Number(transaction.transaction_type),
      amount: transaction.amount.trim(),
      transaction_date: transaction.transaction_date,
      payment_method: Number(transaction.payment_method),
      note: transaction.note.trim() || null,
    })),
    is_deposit_paid: isQr ? false : form.is_deposit_paid,
    deposit_payment_method: isQr ? null : (form.is_deposit_paid ? Number(form.deposit_payment_method) : null),
  }

  if (includeStatus) payload.status = Number(form.status)

  return payload
}

function PayDepositModal({
  contract,
  isSaving,
  onClose,
  onConfirm
}: {
  contract: AdminContractResource
  isSaving: boolean
  onClose: () => void
  onConfirm: (method: number) => Promise<void>
}) {
  const [method, setMethod] = useState<number | null>(null) // null, 1: Cash, 2: QR
  const [timeLeft, setTimeLeft] = useState(1800) // 30 minutes in seconds

  useEffect(() => {
    if (method !== 2 || timeLeft <= 0) return
    const timer = setInterval(() => {
      setTimeLeft((prev) => prev - 1)
    }, 1000)
    return () => clearInterval(timer)
  }, [method, timeLeft])

  const formatTime = (seconds: number) => {
    const mins = Math.floor(seconds / 60)
    const secs = seconds % 60
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
  }

  const isExpired = timeLeft <= 0

  const handleConfirm = () => {
    if (method) {
      void onConfirm(method)
    }
  }

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center p-4">
      <button type="button" className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" onClick={onClose} aria-label="Đóng QR" />
      <div className="relative z-10 w-full max-w-md overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] p-5 shadow-2xl">
        <div className="flex items-center justify-between gap-3 border-b border-[#3d2a18]/10 pb-3">
          <h2 className="text-lg font-black text-[#24170d]">Ghi nhận đóng tiền cọc</h2>
          <button type="button" onClick={onClose} className="rounded-xl p-1.5 text-[#8b5e34] transition hover:bg-rose-50 hover:text-rose-600"><X className="h-4 w-4" /></button>
        </div>

        {method === null ? (
          <div className="mt-5 space-y-4">
            <p className="text-sm font-bold text-[#6f6254]">Vui lòng chọn phương thức thu cọc cho hợp đồng <span className="font-black text-[#24170d]">{contract.contract_code}</span>:</p>
            <div className="grid grid-cols-2 gap-4">
              <button
                type="button"
                onClick={() => setMethod(1)}
                className="flex flex-col items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-white p-4 transition hover:bg-[#f3c56b]/15 active:scale-95"
              >
                <span className="text-2xl">💵</span>
                <span className="mt-2 text-sm font-black text-[#24170d]">Tiền mặt</span>
              </button>
              <button
                type="button"
                onClick={() => setMethod(2)}
                className="flex flex-col items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-white p-4 transition hover:bg-[#f3c56b]/15 active:scale-95"
              >
                <span className="text-2xl">📱</span>
                <span className="mt-2 text-sm font-black text-[#24170d]">Chuyển khoản QR</span>
              </button>
            </div>
          </div>
        ) : method === 1 ? (
          <div className="mt-5 space-y-4">
            <div className="rounded-2xl border border-[#3d2a18]/10 bg-white/60 p-4 text-center">
              <p className="text-sm font-bold text-[#6f6254]">Xác nhận ghi nhận thu cọc bằng tiền mặt:</p>
              <p className="mt-2 text-2xl font-black text-[#24170d]">{formatCurrency(contract.deposit_amount)}</p>
            </div>
            <div className="flex gap-3">
              <button
                type="button"
                onClick={() => setMethod(null)}
                className="h-12 flex-1 rounded-xl border border-[#3d2a18]/10 bg-white text-sm font-black text-[#8b5e34]"
              >
                Quay lại
              </button>
              <button
                type="button"
                disabled={isSaving}
                onClick={handleConfirm}
                className="h-12 flex-1 rounded-xl bg-[#24170d] text-sm font-black text-[#fff4df] hover:bg-[#3d2a18] disabled:opacity-60"
              >
                {isSaving ? 'Đang xác nhận...' : 'Xác nhận'}
              </button>
            </div>
          </div>
        ) : (
          <div className="mt-4 flex flex-col items-center">
            <div className="rounded-3xl border border-[#3d2a18]/10 bg-white p-4 shadow-sm">
              {isExpired ? (
                <div className="flex h-[240px] w-[240px] flex-col items-center justify-center text-center p-4 bg-stone-50 rounded-2xl">
                  <p className="text-sm font-bold text-rose-500">Mã QR đã hết hạn (30 phút)</p>
                  <p className="mt-2 text-xs text-stone-500">Vui lòng đóng modal và mở lại để tạo mã mới.</p>
                </div>
              ) : (
                contract.deposit_qr_url ? (
                  <img
                    src={contract.deposit_qr_url}
                    alt="VietQR Deposit Code"
                    className="h-[240px] w-[240px] rounded-2xl object-contain"
                  />
                ) : (
                  <div className="flex h-[240px] w-[240px] items-center justify-center bg-stone-50 text-xs font-bold text-stone-500">Không tìm thấy mã QR</div>
                )
              )}
            </div>

            <div className="mt-3 w-full text-center">
              <p className="text-xs font-bold text-[#8b5e34]/70">Mã QR hết hạn trong:</p>
              <p className={cn("text-base font-black mt-0.5", isExpired ? "text-rose-500" : "text-[#a65f16] animate-pulse")}>
                {formatTime(timeLeft)}
              </p>
            </div>

            <div className="mt-3 w-full space-y-2 rounded-2xl border border-[#3d2a18]/10 bg-white/60 p-3 text-xs font-bold text-[#6f6254]">
              <div className="flex justify-between">
                <span>Số tiền cọc:</span>
                <span className="font-black text-[#24170d]">{formatCurrency(contract.deposit_amount)}</span>
              </div>
              <div className="flex justify-between">
                <span>Nội dung CK:</span>
                <span className="font-black text-[#a65f16]">{`COC ${contract.contract_code}`}</span>
              </div>
            </div>

            <div className="mt-4 flex w-full gap-3">
              <button
                type="button"
                onClick={() => setMethod(null)}
                className="h-12 flex-1 rounded-xl border border-[#3d2a18]/10 bg-white text-sm font-black text-[#8b5e34]"
              >
                Quay lại
              </button>
              <button
                type="button"
                disabled={isSaving || isExpired}
                onClick={handleConfirm}
                className="h-12 flex-1 rounded-xl bg-[#24170d] text-sm font-black text-[#fff4df] shadow-md transition hover:bg-[#3d2a18] disabled:opacity-60"
              >
                {isSaving ? 'Đang xác nhận...' : 'Xác nhận đã nhận'}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}

function DepositQRModal({
  contract,
  isSaving,
  onClose,
  onConfirm
}: {
  contract: AdminContractResource
  isSaving: boolean
  onClose: () => void
  onConfirm: () => void
}) {
  const [timeLeft, setTimeLeft] = useState(1800) // 30 minutes in seconds

  useEffect(() => {
    if (timeLeft <= 0) return
    const timer = setInterval(() => {
      setTimeLeft((prev) => prev - 1)
    }, 1000)
    return () => clearInterval(timer)
  }, [timeLeft])

  const formatTime = (seconds: number) => {
    const mins = Math.floor(seconds / 60)
    const secs = seconds % 60
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
  }

  const isExpired = timeLeft <= 0

  return (
    <div className="fixed inset-0 z-[75] flex items-center justify-center p-4">
      <button type="button" className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" onClick={onClose} aria-label="Đóng QR" />
      <div className="relative z-10 w-full max-w-md overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] p-5 shadow-2xl">
        <div className="flex items-center justify-between gap-3 border-b border-[#3d2a18]/10 pb-3">
          <h2 className="text-lg font-black text-[#24170d]">Thanh toán cọc VietQR</h2>
          <button type="button" onClick={onClose} className="rounded-xl p-1.5 text-[#8b5e34] transition hover:bg-rose-50 hover:text-rose-600"><X className="h-4 w-4" /></button>
        </div>

        <div className="mt-4 flex flex-col items-center">
          <div className="rounded-3xl border border-[#3d2a18]/10 bg-white p-4 shadow-sm">
            {isExpired ? (
              <div className="flex h-[280px] w-[280px] flex-col items-center justify-center text-center p-4 bg-stone-50 rounded-2xl">
                <p className="text-sm font-bold text-rose-500">Mã QR đã hết hạn (30 phút)</p>
                <p className="mt-2 text-xs text-stone-500">Vui lòng đóng modal và tải lại để lấy mã mới.</p>
              </div>
            ) : (
              contract.deposit_qr_url ? (
                <img
                  src={contract.deposit_qr_url}
                  alt="VietQR Deposit Code"
                  className="h-[280px] w-[280px] rounded-2xl object-contain"
                />
              ) : (
                <div className="flex h-[280px] w-[280px] items-center justify-center bg-stone-50 text-xs font-bold text-stone-500">Không tìm thấy mã QR</div>
              )
            )}
          </div>

          <div className="mt-4 w-full text-center">
            <p className="text-xs font-bold text-[#8b5e34]/70">Mã QR hết hạn trong:</p>
            <p className={cn("text-lg font-black mt-1", isExpired ? "text-rose-500" : "text-[#a65f16] animate-pulse")}>
              {formatTime(timeLeft)}
            </p>
          </div>

          <div className="mt-4 w-full space-y-2.5 rounded-2xl border border-[#3d2a18]/10 bg-white/60 p-3 text-xs font-bold text-[#6f6254]">
            <div className="flex justify-between">
              <span>Mã hợp đồng:</span>
              <span className="font-black text-[#24170d]">{contract.contract_code}</span>
            </div>
            <div className="flex justify-between">
              <span>Số tiền cọc:</span>
              <span className="font-black text-[#24170d]">{formatCurrency(contract.deposit_amount)}</span>
            </div>
            <div className="flex justify-between">
              <span>Nội dung chuyển khoản:</span>
              <span className="font-black text-[#a65f16]">{`COC ${contract.contract_code}`}</span>
            </div>
          </div>
        </div>

        <div className="mt-5 flex gap-3">
          <button
            type="button"
            onClick={onClose}
            className="h-12 flex-1 rounded-xl border border-[#3d2a18]/10 bg-white text-sm font-black text-[#8b5e34]"
          >
            Đóng / Thu sau
          </button>
          <button
            type="button"
            disabled={isSaving || isExpired}
            onClick={onConfirm}
            className="h-12 flex-1 rounded-xl bg-[#24170d] text-sm font-black text-[#fff4df] shadow-md transition hover:bg-[#3d2a18] disabled:opacity-60"
          >
            {isSaving ? 'Đang xác nhận...' : 'Xác nhận đã nhận'}
          </button>
        </div>
      </div>
    </div>
  )
}
