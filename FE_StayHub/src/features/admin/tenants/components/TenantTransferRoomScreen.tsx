import { useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import {
  ArrowLeft,
  ArrowRightLeft,
  CalendarDays,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Clock3,
  FileSignature,
  Loader2,
  Plus,
  ReceiptText,
  Search,
  ShieldAlert,
  UserRound,
  Users,
  WalletCards,
} from 'lucide-react'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { AdminSelect, type AdminSelectOption } from '../../shared/components/AdminSelect'
import { buildingAllowsTenantGender } from '../../shared/config/gender-policy'
import { fetchAdminTenantDetail, fetchAdminTenants } from '../services/tenants.service'
import { transferTenantRoom } from '../services/TranferRoom'
import { fetchAdminRooms, fetchBuilding } from '../../rooms/services/rooms.service'
import type { AdminPaginationMeta, AdminPaginator, AdminTenantResource } from '../types/tenant-api.model'
import type { AdminRoomResource, BuildingResource } from '../../rooms/types/rooms.model'
import type { AdminContractResource } from '../../contracts/types/contract-api.model'
import type { TransferRoomResultResource, TransferTenantPayload } from '../types/TranferModel'
import { fetchAdminContractDetail } from '../../contracts/services/contracts.service'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { formatMoneyInput } from '../../../../shared/lib/utils/format'

const ROOM_STATUS_ACTIVE = 1
const STATUS_RENTING = 1
const TENANT_PICKER_PAGE_SIZE = 10

const inputClass =
  'w-full rounded-2xl border border-[#372515]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#2b1b10] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#d9a441] focus:ring-4 focus:ring-[#d9a441]/18'

interface TenantCardState {
  tenantId: number
  fullName: string
  phone?: string | null
  email?: string | null
  gender?: number | null
  isStaying?: boolean | null
}

interface TenantPickerProps {
  keyword: string
  buildingFilter: string
  buildingOptions: AdminSelectOption[]
  isBuildingFilterDisabled: boolean
  tenants: AdminTenantResource[]
  isLoading: boolean
  errorMessage: string | null
  currentPage: number
  totalPages: number
  totalTenants: number
  paginationStart: number
  paginationEnd: number
  visiblePages: number[]
  perPage: number
  onKeywordChange: (value: string) => void
  onBuildingChange: (value: string) => void
  onPageChange: (page: number) => void
  onPerPageChange: (value: string | number) => void
  onSelectTenant: (tenant: AdminTenantResource) => void
}

export function TenantTransferRoomScreen() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { session } = useAdminSession()
  const isSuperAdmin = isSuperAdminRole(session?.admin.role)
  const managedBuildingId = session?.admin?.managed_buildings?.[0]?.id

  const selectedTenantIdParam = searchParams.get('tenantId') || searchParams.get('tenant_id') || ''
  const parsedTenantId = Number(selectedTenantIdParam)
  const hasSelectedTenant = Number.isFinite(parsedTenantId) && parsedTenantId > 0

  const [tenantKeyword, setTenantKeyword] = useState('')
  const [tenantBuildingFilter, setTenantBuildingFilter] = useState('')
  const [tenantOptions, setTenantOptions] = useState<AdminTenantResource[]>([])
  const [tenantOptionsMeta, setTenantOptionsMeta] = useState<AdminPaginationMeta | null>(null)
  const [tenantCurrentPage, setTenantCurrentPage] = useState(1)
  const [tenantPerPage, setTenantPerPage] = useState(TENANT_PICKER_PAGE_SIZE)
  const [isTenantOptionsLoading, setIsTenantOptionsLoading] = useState(false)
  const [tenantOptionsError, setTenantOptionsError] = useState<string | null>(null)

  const [tenant, setTenant] = useState<AdminTenantResource | null>(null)
  const [currentContract, setCurrentContract] = useState<AdminContractResource | null>(null)
  const [isTenantLoading, setIsTenantLoading] = useState(false)
  const [isContractLoading, setIsContractLoading] = useState(false)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [buildings, setBuildings] = useState<BuildingResource[]>([])
  const [selectedBuildingId, setSelectedBuildingId] = useState('')
  const [rooms, setRooms] = useState<AdminRoomResource[]>([])
  const [isRoomsLoading, setIsRoomsLoading] = useState(false)
  const [roomKeyword, setRoomKeyword] = useState('')
  const [selectedRoom, setSelectedRoom] = useState<AdminRoomResource | null>(null)

  const [selectedTenantIds, setSelectedTenantIds] = useState<number[]>(hasSelectedTenant ? [parsedTenantId] : [])
  const [depositDeductionAmount, setDepositDeductionAmount] = useState('0')
  const [transferFee, setTransferFee] = useState('0')
  const [newDepositAmount, setNewDepositAmount] = useState('')
  const [note, setNote] = useState('')

  const [isSubmitting, setIsSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [transferResult, setTransferResult] = useState<TransferRoomResultResource | null>(null)

  const currentRoomId = tenant?.current_room?.room_id ?? tenant?.room_id ?? currentContract?.room_id ?? null
  const currentRoomNumber = tenant?.current_room?.room_number ?? tenant?.room_number ?? currentContract?.room_number ?? null
  const currentBuildingName = tenant?.current_room?.building_name ?? tenant?.building_name ?? currentContract?.building_name ?? null
  const currentContractCode = currentContract?.contract_code ?? tenant?.current_contract?.contract_code ?? null

  useEffect(() => {
    if (isSuperAdmin || !managedBuildingId) return

    queueMicrotask(() => {
      setTenantBuildingFilter((current) => current || String(managedBuildingId))
      setSelectedBuildingId((current) => current || String(managedBuildingId))
    })
  }, [isSuperAdmin, managedBuildingId])

  useEffect(() => {
    queueMicrotask(() => {
      if (!hasSelectedTenant) {
        setTenant(null)
        setCurrentContract(null)
        setSelectedRoom(null)
        setSelectedTenantIds([])
        setTransferResult(null)
        setLoadError(null)
        setSubmitError(null)
        setFieldErrors({})
        return
      }

      setSelectedTenantIds([parsedTenantId])
      setTransferResult(null)
    })
  }, [hasSelectedTenant, parsedTenantId])

  useEffect(() => {
    if (hasSelectedTenant) return

    if (!isSuperAdmin && !tenantBuildingFilter && managedBuildingId) {
      queueMicrotask(() => setTenantBuildingFilter(String(managedBuildingId)))
      return
    }

    let isMounted = true
    const timer = window.setTimeout(() => {
      setIsTenantOptionsLoading(true)
      setTenantOptionsError(null)

      fetchAdminTenants({
        keyword: tenantKeyword.trim() || undefined,
        status: STATUS_RENTING,
        building_id: tenantBuildingFilter ? Number(tenantBuildingFilter) : undefined,
        page: tenantCurrentPage,
        per_page: tenantPerPage,
      })
        .then((response) => {
          if (!isMounted) return
          const normalized = normalizeAdminTenantsResponse(response)
          setTenantOptions(normalized.data)
          setTenantOptionsMeta(normalized.meta)

          if (normalized.meta?.last_page && tenantCurrentPage > normalized.meta.last_page) {
            setTenantCurrentPage(normalized.meta.last_page)
          }
        })
        .catch((error) => {
          if (!isMounted) return
          setTenantOptions([])
          setTenantOptionsMeta(null)
          setTenantOptionsError(getVisibleErrorMessage(error, 'Không thể tải danh sách khách thuê.'))
        })
        .finally(() => {
          if (!isMounted) return
          setIsTenantOptionsLoading(false)
        })
    }, 250)

    return () => {
      isMounted = false
      window.clearTimeout(timer)
    }
  }, [hasSelectedTenant, managedBuildingId, tenantBuildingFilter, tenantKeyword, tenantCurrentPage, tenantPerPage, isSuperAdmin])

  useEffect(() => {
    if (!hasSelectedTenant) return

    let isMounted = true

    queueMicrotask(() => {
      if (!isMounted) return
      setIsTenantLoading(true)
      setLoadError(null)
      setTenant(null)
      setCurrentContract(null)
      setSelectedRoom(null)
      setSubmitError(null)
      setFieldErrors({})
    })

    fetchAdminTenantDetail(parsedTenantId)
      .then((response) => {
        if (!isMounted) return

        const tenantResult = response.result ?? null
        setTenant(tenantResult)

        const currentContractId = tenantResult?.current_contract?.id
        if (!currentContractId) {
          setLoadError('Khách thuê này chưa có hợp đồng đang hiệu lực để chuyển phòng.')
          return
        }

        setIsContractLoading(true)
        return fetchAdminContractDetail(currentContractId)
          .then((contractResponse) => {
            if (!isMounted) return
            setCurrentContract(contractResponse.result ?? null)
            setSelectedTenantIds((current) => (current.length > 0 ? current : [parsedTenantId]))
          })
          .catch((error) => {
            if (!isMounted) return
            setLoadError(getVisibleErrorMessage(error, 'Không thể tải chi tiết hợp đồng hiện tại.'))
          })
          .finally(() => {
            if (!isMounted) return
            setIsContractLoading(false)
          })
      })
      .catch((error) => {
        if (!isMounted) return
        setLoadError(getVisibleErrorMessage(error, 'Không thể tải thông tin khách thuê.'))
      })
      .finally(() => {
        if (!isMounted) return
        setIsTenantLoading(false)
      })

    return () => {
      isMounted = false
    }
  }, [hasSelectedTenant, parsedTenantId])

  useEffect(() => {
    let isMounted = true

    queueMicrotask(() => {
      if (!isMounted) return
      setIsRoomsLoading(true)
    })
    fetchBuilding()
      .then((response) => {
        if (!isMounted) return
        setBuildings(normalizeBuildingResponse(response))
      })
      .catch(() => {
        if (!isMounted) return
        setBuildings([])
      })

    fetchAdminRooms({ status: ROOM_STATUS_ACTIVE, per_page: 1000 })
      .then((response) => {
        if (!isMounted) return
        setRooms(response.result || [])
      })
      .catch(() => {
        if (!isMounted) return
        setRooms([])
      })
      .finally(() => {
        if (!isMounted) return
        setIsRoomsLoading(false)
      })

    return () => {
      isMounted = false
    }
  }, [])

  const contractTenants = useMemo(() => {
    const rows = currentContract?.contract_tenants ?? []

    return rows
      .filter((row) => row.is_staying !== false)
      .map((row): TenantCardState => ({
        tenantId: row.tenant_id,
        fullName: row.tenant?.full_name?.trim() || `Khách thuê #${row.tenant_id}`,
        phone: row.tenant?.phone ?? null,
        email: row.tenant?.email ?? null,
        gender: row.tenant?.gender ?? null,
        isStaying: row.is_staying,
      }))
  }, [currentContract])

  const selectedTenantCards = useMemo(() => {
    if (contractTenants.length > 0) {
      return contractTenants.filter((tenantCard) => selectedTenantIds.includes(tenantCard.tenantId))
    }

    if (!tenant) return []

    return [
      {
        tenantId: tenant.id,
        fullName: tenant.full_name || tenant.username || `Khách thuê #${tenant.id}`,
        phone: tenant.phone,
        email: tenant.email,
        gender: tenant.gender,
        isStaying: true,
      },
    ]
  }, [contractTenants, selectedTenantIds, tenant])

  const selectedTenantsInfo = useMemo(() => {
    if (selectedTenantCards.length > 0) return selectedTenantCards

    return selectedTenantIds.length > 0
      ? selectedTenantIds.map((tenantId) => ({
        tenantId,
        fullName: `Khách thuê #${tenantId}`,
        gender: tenant?.gender ?? null,
        isStaying: true,
      }))
      : []
  }, [selectedTenantCards, selectedTenantIds, tenant?.gender])

  const oldDepositBalance = moneyNumber(currentContract?.deposit_balance ?? tenant?.current_contract?.deposit_balance)
  const damageAmount = moneyNumber(depositDeductionAmount)
  const transferFeeAmount = moneyNumber(transferFee)
  const availableAfterCosts = Math.max(0, oldDepositBalance - damageAmount - transferFeeAmount)
  const extraChargeAmount = Math.max(0, damageAmount + transferFeeAmount - oldDepositBalance)
  const destinationRoomHasContract = Boolean(selectedRoom && selectedRoom.current_occupants > 0)
  const requiredNewDeposit = !selectedRoom || destinationRoomHasContract ? 0 : moneyNumber(newDepositAmount || selectedRoom.base_price)
  const depositAppliedToNewRoom = !selectedRoom || destinationRoomHasContract ? 0 : Math.min(availableAfterCosts, requiredNewDeposit)
  const manualRefundAmount = !selectedRoom
    ? 0
    : destinationRoomHasContract
      ? availableAfterCosts
      : Math.max(availableAfterCosts - requiredNewDeposit, 0)
  const depositDueAmount = !selectedRoom || destinationRoomHasContract
    ? 0
    : Math.max(requiredNewDeposit - availableAfterCosts, 0)
  const settlementDueAmount = selectedRoom ? depositDueAmount + extraChargeAmount : 0
  const effectiveMovementDate = nextMonthStartDateString()
  const destinationRoomCapacity = selectedRoom ? Math.max(0, (selectedRoom.max_occupants || 0) - (selectedRoom.current_occupants || 0)) : 0

  const tenantBuildingOptions = useMemo(
    () => buildBuildingOptions(mergeBuildingResources(buildings, tenantsToBuildingResources(tenantOptions), roomsToBuildingResources(rooms)), 'Tất cả tòa nhà'),
    [buildings, rooms, tenantOptions],
  )

  const destinationBuildingOptions = useMemo(
    () => buildBuildingOptions(mergeBuildingResources(buildings, roomsToBuildingResources(rooms)), 'Tất cả tòa nhà'),
    [buildings, rooms],
  )

  const roomCandidates = useMemo(() => {
    const keyword = roomKeyword.trim().toLowerCase()

    return rooms
      .filter((room) => room.status === ROOM_STATUS_ACTIVE)
      .filter((room) => !currentRoomId || room.id !== currentRoomId)
      .filter((room) => !selectedBuildingId || Number(room.building_id) === Number(selectedBuildingId))
      .filter((room) => !keyword || [room.room_number, room.slug, room.building_name, room.building?.name, room.floor?.toString()].some((value) => String(value ?? '').toLowerCase().includes(keyword)))
      .filter((room) => {
        if (selectedTenantIds.length === 0) return true
        if ((room.max_occupants || 0) > 0 && room.current_occupants + selectedTenantIds.length > room.max_occupants) return false

        return selectedTenantsInfo.every((tenantInfo) => tenantInfo.gender == null || buildingAllowsTenantGender(room.building?.gender_policy ?? null, tenantInfo.gender))
      })
      .sort((left, right) => {
        const leftGap = Math.max(0, (left.max_occupants || 0) - (left.current_occupants || 0))
        const rightGap = Math.max(0, (right.max_occupants || 0) - (right.current_occupants || 0))
        if (leftGap !== rightGap) return rightGap - leftGap
        return String(left.room_number || '').localeCompare(String(right.room_number || ''), 'vi')
      })
  }, [currentRoomId, roomKeyword, rooms, selectedBuildingId, selectedTenantIds.length, selectedTenantsInfo])

  useEffect(() => {
    if (!selectedRoom) return

    const stillVisible = roomCandidates.some((room) => room.id === selectedRoom.id)
    if (!stillVisible) {
      queueMicrotask(() => {
        setSelectedRoom(null)
        setNewDepositAmount('')
      })
    }
  }, [roomCandidates, selectedRoom])

  function selectTenantForTransfer(nextTenant: AdminTenantResource) {
    navigate(`/admin/transfer-room?tenantId=${nextTenant.id}`)
  }

  function changeTenant() {
    setTransferResult(null)
    navigate('/admin/transfer-room')
  }

  function toggleTenantSelection(tenantId: number) {
    setSelectedTenantIds((current) => {
      if (current.includes(tenantId)) {
        if (current.length === 1) return current
        return current.filter((value) => value !== tenantId)
      }

      return [...current, tenantId]
    })
  }

  function selectAllContractTenants() {
    if (contractTenants.length === 0) return
    setSelectedTenantIds(contractTenants.map((row) => row.tenantId))
  }

  function clearOtherContractTenants() {
    if (!hasSelectedTenant) return
    setSelectedTenantIds([parsedTenantId])
  }

  function pickRoom(room: AdminRoomResource) {
    setSelectedRoom(room)
    setFieldErrors({})
    setSubmitError(null)
    if (room.current_occupants > 0) {
      setNewDepositAmount('0')
    } else {
      setNewDepositAmount(formatMoneyInput(String(Math.max(0, Number(room.base_price ?? 0)))))
    }
  }

  function updateFilter(setter: (value: string) => void, value: string) {
    setter(value)
    setTenantCurrentPage(1)
  }

  function updateSelectFilter(setter: (value: string) => void, value: string) {
    setter(value)
    setTenantCurrentPage(1)
  }

  function changePage(page: number) {
    setTenantCurrentPage(page)
  }

  function changePerPage(nextValue: string | number) {
    setTenantPerPage(Number(nextValue))
    setTenantCurrentPage(1)
  }

  const safeTenantCurrentPage = Math.max(1, Math.min(tenantCurrentPage, tenantOptionsMeta?.last_page ?? tenantCurrentPage))
  const tenantTotalPages = Math.max(1, tenantOptionsMeta?.last_page ?? (tenantOptions.length >= tenantPerPage ? tenantCurrentPage + 1 : tenantCurrentPage))
  const tenantPaginationStart = tenantOptionsMeta?.from ?? (tenantOptions.length === 0 ? 0 : (safeTenantCurrentPage - 1) * tenantPerPage + 1)
  const tenantPaginationEnd = tenantOptionsMeta?.to ?? (tenantOptions.length === 0 ? 0 : (safeTenantCurrentPage - 1) * tenantPerPage + tenantOptions.length)
  const totalTenantOptions = tenantOptionsMeta?.total ?? (safeTenantCurrentPage - 1) * tenantPerPage + tenantOptions.length
  const visibleTenantPages = useMemo(() => {
    const pages = new Set<number>([1, tenantTotalPages, safeTenantCurrentPage - 1, safeTenantCurrentPage, safeTenantCurrentPage + 1])
    return Array.from(pages)
      .filter((page) => page >= 1 && page <= tenantTotalPages)
      .sort((left, right) => left - right)
  }, [safeTenantCurrentPage, tenantTotalPages])

  const hasSelectedTenantError = loadError || !tenant
  const canSubmit = Boolean(selectedRoom && selectedTenantIds.length > 0 && !isSubmitting && !isContractLoading)

  async function handleSubmit() {
    if (!selectedRoom || selectedTenantIds.length === 0) return

    setSubmitError(null)
    setFieldErrors({})

    const payload: TransferTenantPayload = {
      tenant_ids: selectedTenantIds,
      to_room_id: selectedRoom.id,
      movement_date: effectiveMovementDate,
      note: note.trim() || undefined,
      deposit_deduction_amount: damageAmount,
      transfer_fee: transferFeeAmount,
      new_deposit_amount: destinationRoomHasContract ? 0 : requiredNewDeposit,
    }

    try {
      setIsSubmitting(true)
      const response = await transferTenantRoom(payload)
      setTransferResult(response.result ?? null)
    } catch (error) {
      if (error instanceof ApiError) {
        const errors = error.validationErrors
        if (errors) setFieldErrors(errors)
        setSubmitError(error.message || 'Lên lịch chuyển phòng thất bại.')
      } else {
        setSubmitError('Lên lịch chuyển phòng thất bại.')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  if (!hasSelectedTenant) {
    return (
      <TenantPicker
        keyword={tenantKeyword}
        buildingFilter={tenantBuildingFilter}
        buildingOptions={tenantBuildingOptions}
        isBuildingFilterDisabled={!isSuperAdmin && tenantBuildingOptions.length <= 1}
        tenants={tenantOptions}
        isLoading={isTenantOptionsLoading}
        errorMessage={tenantOptionsError}
        currentPage={safeTenantCurrentPage}
        totalPages={tenantTotalPages}
        totalTenants={totalTenantOptions}
        paginationStart={tenantPaginationStart}
        paginationEnd={tenantPaginationEnd}
        visiblePages={visibleTenantPages}
        perPage={tenantPerPage}
        onKeywordChange={(value) => updateFilter(setTenantKeyword, value)}
        onBuildingChange={(value) => updateSelectFilter(setTenantBuildingFilter, value)}
        onPageChange={changePage}
        onPerPageChange={changePerPage}
        onSelectTenant={selectTenantForTransfer}
      />
    )
  }

  if (isTenantLoading || isContractLoading) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center rounded-[2rem] border border-[#372515]/10 bg-[#fffaf1] text-[#8b5e34] shadow-xl shadow-[#6b3f1d]/10">
        <div className="flex items-center gap-3 rounded-full border border-[#d9a441]/20 bg-white px-4 py-3 text-sm font-black text-[#2b1b10]">
          <Loader2 className="h-4 w-4 animate-spin text-[#a65f16]" />
          <span>Đang tải hồ sơ chuyển phòng...</span>
        </div>
      </div>
    )
  }

  if (hasSelectedTenantError) {
    return (
      <section className="space-y-4 text-[#24170d]">
        <div className="rounded-3xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-black text-rose-700">
          {loadError || 'Không tìm thấy khách thuê.'}
        </div>
        <button type="button" onClick={changeTenant} className="inline-flex h-11 items-center justify-center rounded-2xl bg-[#24170d] px-5 text-sm font-black text-[#fff4df] transition hover:bg-[#3d2a18]">
          Chọn khách thuê khác
        </button>
      </section>
    )
  }

  if (transferResult) {
    return (
      <TransferResultPanel
        tenant={tenant}
        contract={currentContract}
        result={transferResult}
        selectedTenantCards={selectedTenantCards}
        selectedRoom={selectedRoom}
        manualRefundAmount={manualRefundAmount}
        settlementDueAmount={settlementDueAmount}
        extraChargeAmount={extraChargeAmount}
        depositDueAmount={depositDueAmount}
        onNewTransfer={changeTenant}
        onViewMovement={(transferCode) => navigate(`/admin/room-movements?keyword=${encodeURIComponent(transferCode)}`)}
      />
    )
  }

  return (
    <section className="space-y-6 text-[#24170d] sm:space-y-8">
      <section className="overflow-hidden rounded-[2.15rem] border border-[#372515]/10 bg-[#1f150f] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative overflow-hidden p-5 text-[#fff4df] sm:p-6 lg:p-7">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_14%,rgba(217,164,65,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(15,118,110,0.28),transparent_34%),linear-gradient(135deg,#1f150f_0%,#3b2918_50%,#0f3f3b_100%)]" />
          <div className="pointer-events-none absolute inset-x-8 bottom-0 h-px bg-gradient-to-r from-transparent via-[#d9a441]/45 to-transparent" />
          <div className="relative grid gap-6 xl:grid-cols-[1.1fr_0.9fr] xl:items-end">
            <div className="space-y-4">
              <Link to="/admin/room-movements" className="inline-flex items-center gap-2 text-xs font-black uppercase tracking-[0.18em] text-[#d9a441] transition hover:text-[#f6cd73]">
                <ArrowLeft className="h-3.5 w-3.5" /> Lịch sử phòng & cọc
              </Link>
              <div className="flex flex-wrap items-center gap-3">
                <span className="inline-flex h-14 w-14 items-center justify-center rounded-[1.25rem] border border-[#d9a441]/35 bg-[#d9a441]/15 text-[#d9a441] shadow-xl shadow-black/15">
                  <ArrowRightLeft className="h-6 w-6" />
                </span>
                <div>
                  <p className="text-[10px] font-black uppercase tracking-[0.22em] text-[#d9a441]">Luồng chuyển phòng đầu tháng</p>
                  <h1 className="mt-1 text-3xl font-black tracking-[-0.055em] sm:text-4xl">Lên lịch chuyển phòng</h1>
                </div>
              </div>
              <p className="max-w-2xl text-sm font-semibold leading-6 text-[#f8e8c8]/78">
                {tenant?.full_name || tenant?.username || `Khách thuê #${tenant?.id ?? parsedTenantId}`} ·
                {' '}
                chuyển từ {currentRoomNumber ? `phòng ${currentRoomNumber}` : 'phòng hiện tại'}
                {currentBuildingName ? ` · ${currentBuildingName}` : ''}.
                {' '}
                Mọi chuyển phòng chỉ được chốt vào ngày 01 của tháng kế tiếp.
              </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-3 xl:justify-self-end">
              <MetricCard label="Khách chuyển" value={selectedTenantIds.length} icon={<Users className="h-4 w-4" />} />
              <MetricCard label="Cọc còn lại" value={oldDepositBalance} currency icon={<WalletCards className="h-4 w-4" />} />
              <MetricCard label="Ngày chốt" value={1} suffix="/tháng" icon={<CalendarDays className="h-4 w-4" />} />
            </div>
          </div>
        </div>
      </section>

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div className="space-y-6">
          <section className="rounded-[2rem] border border-[#372515]/10 bg-[#fffaf1]/92 p-4 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur sm:p-5 lg:p-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
              <div>
                <p className="text-[10px] font-black uppercase tracking-[0.22em] text-[#8b5e34]/65">Bước 1</p>
                <h2 className="mt-1 text-xl font-black tracking-[-0.04em] sm:text-2xl">Chọn khách thuê tham gia chuyển phòng</h2>
                <p className="mt-2 max-w-2xl text-sm font-semibold leading-6 text-[#6f6254]">
                  Có thể chuyển một người, người đại diện hoặc toàn bộ khách trong cùng hợp đồng. Chỉ những người đang ở lại hợp đồng cũ mới được chọn.
                </p>
              </div>
              <div className="flex flex-wrap gap-2">
                <button type="button" onClick={selectAllContractTenants} className="inline-flex h-10 items-center gap-2 rounded-2xl border border-[#372515]/10 bg-white px-4 text-xs font-black uppercase tracking-[0.16em] text-[#2b1b10] transition hover:border-[#0f766e]/20 hover:bg-[#0f766e]/8">
                  <CheckCircle2 className="h-4 w-4 text-[#0f5f59]" /> Chọn tất cả
                </button>
                <button type="button" onClick={clearOtherContractTenants} className="inline-flex h-10 items-center gap-2 rounded-2xl border border-[#372515]/10 bg-white px-4 text-xs font-black uppercase tracking-[0.16em] text-[#2b1b10] transition hover:border-[#d97706]/20 hover:bg-[#d97706]/8">
                  <ShieldAlert className="h-4 w-4 text-[#a65f16]" /> Giữ người đang chọn
                </button>
              </div>
            </div>

            <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
              {contractTenants.length > 0 ? contractTenants.map((contractTenant) => {
                const checked = selectedTenantIds.includes(contractTenant.tenantId)

                return (
                  <button
                    key={contractTenant.tenantId}
                    type="button"
                    onClick={() => toggleTenantSelection(contractTenant.tenantId)}
                    className={cn(
                      'group rounded-[1.4rem] border p-4 text-left transition focus:outline-none focus:ring-4 focus:ring-[#d9a441]/15',
                      checked
                        ? 'border-[#0f766e]/20 bg-[#0f766e]/8 shadow-lg shadow-[#0f766e]/6'
                        : 'border-[#372515]/10 bg-white/70 hover:border-[#d9a441]/25 hover:bg-white',
                    )}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div className="flex items-start gap-3">
                        <span className={cn('flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border text-sm font-black', checked ? 'border-[#0f766e]/20 bg-[#0f766e]/12 text-[#0f5f59]' : 'border-[#372515]/10 bg-[#fffaf1] text-[#8b5e34]')}>
                          <UserRound className="h-4 w-4" />
                        </span>
                        <div className="space-y-1">
                          <p className="text-sm font-black text-[#24170d]">{contractTenant.fullName}</p>
                          <p className="text-xs font-semibold text-[#6f6254]">{contractTenant.phone || contractTenant.email || `Khách thuê #${contractTenant.tenantId}`}</p>
                        </div>
                      </div>
                      <span className={cn('inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em]', checked ? 'bg-[#0f766e]/12 text-[#0f5f59]' : 'bg-[#efe2cf]/75 text-[#8b5e34]')}>
                        {checked ? 'Đã chọn' : 'Chọn'}
                      </span>
                    </div>
                  </button>
                )
              }) : (
                <div className="rounded-[1.4rem] border border-dashed border-[#372515]/12 bg-white/70 px-5 py-6 text-sm font-semibold text-[#6f6254]">
                  Hợp đồng này chưa có danh sách khách thuê chi tiết. Hệ thống sẽ chuyển theo khách thuê hiện tại.
                </div>
              )}
            </div>

            <div className="mt-4 rounded-[1.4rem] border border-dashed border-[#372515]/12 bg-white/60 p-4">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Cách chốt</p>
                  <p className="mt-1 text-sm font-semibold text-[#6f6254]">
                    Ngày chuyển sẽ cố định là <span className="font-black text-[#24170d]">{effectiveMovementDate}</span>. Hệ thống chỉ ghi nhận lịch chờ và chạy execute vào ngày 01.
                  </p>
                </div>
                <span className="inline-flex items-center gap-2 rounded-full border border-[#0f766e]/20 bg-[#0f766e]/8 px-3 py-1.5 text-xs font-black text-[#0f5f59]">
                  <Clock3 className="h-3.5 w-3.5" /> Chờ ký & chờ thực thi
                </span>
              </div>
            </div>
          </section>

          <section className="rounded-[2rem] border border-[#372515]/10 bg-[#fffaf1]/92 p-4 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur sm:p-5 lg:p-6">
            <div className="flex flex-wrap items-end justify-between gap-4">
              <div>
                <p className="text-[10px] font-black uppercase tracking-[0.22em] text-[#8b5e34]/65">Bước 2</p>
                <h2 className="mt-1 text-xl font-black tracking-[-0.04em] sm:text-2xl">Chọn phòng đích</h2>
                <p className="mt-2 max-w-2xl text-sm font-semibold leading-6 text-[#6f6254]">
                  Phòng trống sẽ tạo hợp đồng pending-sign. Phòng đang có người ở sẽ ghép vào hợp đồng active nếu còn sức chứa.
                </p>
              </div>
              <div className="grid w-full gap-3 sm:max-w-xl sm:grid-cols-[minmax(0,1fr)_minmax(12rem,14rem)]">
                <label className="relative block">
                  <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8b5e34]/60" />
                  <input
                    type="text"
                    value={roomKeyword}
                    onChange={(event) => setRoomKeyword(event.target.value)}
                    placeholder="Tìm số phòng, mã phòng..."
                    className={cn(inputClass, 'pl-11')}
                  />
                </label>
                <AdminSelect
                  value={selectedBuildingId}
                  options={destinationBuildingOptions}
                  onChange={(value) => setSelectedBuildingId(String(value))}
                  disabled={!isSuperAdmin && destinationBuildingOptions.length <= 1}
                />
              </div>
            </div>

            <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 2xl:grid-cols-3">
              {isRoomsLoading && Array.from({ length: 6 }).map((_, index) => (
                <div key={index} className="h-32 animate-pulse rounded-[1.4rem] bg-stone-100" />
              ))}

              {!isRoomsLoading && roomCandidates.length === 0 && (
                <div className="col-span-full rounded-[1.4rem] border border-dashed border-[#372515]/12 bg-white/65 px-6 py-10 text-center text-sm font-semibold text-[#6f6254]">
                  Không có phòng phù hợp với số lượng khách, giới tính hoặc sức chứa hiện tại.
                </div>
              )}

              {!isRoomsLoading && roomCandidates.map((room) => {
                const checked = selectedRoom?.id === room.id
                const isOccupied = room.current_occupants > 0
                const remainingCapacity = Math.max(0, (room.max_occupants || 0) - (room.current_occupants || 0))

                return (
                  <button
                    key={room.id}
                    type="button"
                    onClick={() => pickRoom(room)}
                    className={cn(
                      'group rounded-[1.5rem] border p-4 text-left transition focus:outline-none focus:ring-4 focus:ring-[#d9a441]/15',
                      checked
                        ? 'border-[#0f766e]/25 bg-[#0f766e]/8 shadow-lg shadow-[#0f766e]/8'
                        : 'border-[#372515]/10 bg-white/75 hover:border-[#d9a441]/25 hover:bg-white',
                    )}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">
                          {isOccupied ? 'Ghép vào hợp đồng active' : 'Tạo hợp đồng pending-sign'}
                        </p>
                        <h3 className="mt-1 text-lg font-black tracking-[-0.03em] text-[#24170d]">Phòng {room.room_number}</h3>
                        <p className="mt-1 text-sm font-semibold text-[#6f6254]">
                          {room.building?.name || room.building_name || `Tòa nhà #${room.building_id}`}
                        </p>
                      </div>
                      <span className={cn('inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.16em]', checked ? 'bg-[#0f766e]/12 text-[#0f5f59]' : 'bg-[#efe2cf]/75 text-[#8b5e34]')}>
                        {checked ? 'Đang chọn' : 'Chọn'}
                      </span>
                    </div>

                    <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
                      <div className="rounded-2xl border border-[#372515]/10 bg-[#fffaf1] p-3">
                        <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/60">Sức chứa</p>
                        <p className="mt-1 text-base font-black text-[#24170d]">{room.current_occupants}/{room.max_occupants || '∞'}</p>
                      </div>
                      <div className="rounded-2xl border border-[#372515]/10 bg-[#fffaf1] p-3">
                        <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/60">Còn trống</p>
                        <p className="mt-1 text-base font-black text-[#24170d]">{remainingCapacity}</p>
                      </div>
                      <div className="col-span-2 rounded-2xl border border-[#372515]/10 bg-[#fffaf1] p-3">
                        <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/60">Cọc yêu cầu</p>
                        <p className="mt-1 text-base font-black tabular-nums text-[#0f5f59]">{formatCurrency(room.base_price)}</p>
                      </div>
                    </div>
                  </button>
                )
              })}
            </div>
          </section>

          <section className="rounded-[2rem] border border-[#372515]/10 bg-[#fffaf1]/92 p-4 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur sm:p-5 lg:p-6">
            <div className="flex items-center gap-3">
              <span className="flex h-11 w-11 items-center justify-center rounded-2xl border border-[#372515]/10 bg-white/80 text-[#8b5e34]"><FileSignature className="h-5 w-5" /></span>
              <div>
                <h2 className="text-xl font-black tracking-[-0.04em] sm:text-2xl">Điều chỉnh cọc và phí</h2>
                <p className="mt-1 text-sm font-semibold text-[#6f6254]">Nhập số khấu trừ hư hao, phí chuyển phòng và cọc phòng mới nếu có.</p>
              </div>
            </div>

            <div className="mt-5 grid grid-cols-1 gap-3 lg:grid-cols-3">
              <Field label="Khấu trừ hư hao / đồ dùng" error={fieldErrors.deposit_deduction_amount}>
                <input
                  type="text"
                  value={depositDeductionAmount}
                  onChange={(event) => setDepositDeductionAmount(formatMoneyInput(event.target.value))}
                  placeholder="0"
                  className={inputClass}
                />
              </Field>
              <Field label="Phí chuyển phòng" error={fieldErrors.transfer_fee}>
                <input
                  type="text"
                  value={transferFee}
                  onChange={(event) => setTransferFee(formatMoneyInput(event.target.value))}
                  placeholder="0"
                  className={inputClass}
                />
              </Field>
              <Field label="Cọc yêu cầu phòng đích" error={fieldErrors.new_deposit_amount}>
                <input
                  type="text"
                  value={newDepositAmount}
                  onChange={(event) => setNewDepositAmount(formatMoneyInput(event.target.value))}
                  placeholder="0"
                  className={inputClass}
                  disabled={destinationRoomHasContract}
                />
              </Field>
            </div>

            <div className="mt-5 rounded-[1.6rem] border border-dashed border-[#372515]/12 bg-white/65 p-4">
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <LedgerTile label="Cọc gốc" value={oldDepositBalance} tone="neutral" />
                <LedgerTile label="Khấu trừ + phí" value={damageAmount + transferFeeAmount} tone="danger" />
                <LedgerTile label="Khả dụng sau khấu trừ" value={availableAfterCosts} tone="success" />
                <LedgerTile label="Hoàn tay / cần thu" value={destinationRoomHasContract ? manualRefundAmount : depositDueAmount + extraChargeAmount} tone="warning" />
              </div>

              <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <SummaryStat label="Cọc chuyển sang phòng mới" value={depositAppliedToNewRoom} tone="success" />
                <SummaryStat label="Còn thiếu phải thu QR" value={settlementDueAmount} tone="warning" />
                <SummaryStat label="Hoàn cọc thủ công" value={manualRefundAmount} tone="neutral" />
              </div>

              <div className="mt-4 rounded-2xl border border-[#0f766e]/15 bg-[#0f766e]/8 px-4 py-3 text-sm font-semibold leading-6 text-[#0f5f59]">
                Nếu phòng đích đã có người ở, hệ thống không đổi cọc phòng mới. Nếu cọc còn dư sẽ để admin tự tạo phiếu chi; nếu thiếu sẽ sinh QR theo <span className="font-black">transfer_code</span> sau khi execute.
              </div>
            </div>
          </section>

          <section className="rounded-[2rem] border border-[#372515]/10 bg-[#fffaf1]/92 p-4 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur sm:p-5 lg:p-6">
            <div className="flex items-center gap-3">
              <span className="flex h-11 w-11 items-center justify-center rounded-2xl border border-[#372515]/10 bg-white/80 text-[#8b5e34]"><ReceiptText className="h-5 w-5" /></span>
              <div>
                <h2 className="text-xl font-black tracking-[-0.04em] sm:text-2xl">Ghi chú & lịch chốt</h2>
                <p className="mt-1 text-sm font-semibold text-[#6f6254]">Hệ thống chỉ nhận chuyển phòng vào đúng ngày 01 của tháng kế tiếp.</p>
              </div>
            </div>

            <div className="mt-5 grid grid-cols-1 gap-3 lg:grid-cols-[1fr_18rem]">
              <Field label="Ngày chuyển" error={fieldErrors.movement_date}>
                <input
                  type="date"
                  value={effectiveMovementDate}
                  readOnly
                  disabled
                  className={cn(inputClass, 'cursor-not-allowed bg-[#f4eadc]')}
                />
              </Field>
              <div className="rounded-2xl border border-dashed border-[#372515]/12 bg-white/65 px-4 py-3 text-sm font-semibold leading-6 text-[#6f6254]">
                <p className="font-black text-[#24170d]">Lịch thực thi</p>
                <p className="mt-2">Command backend sẽ chạy tự động vào ngày 01 lúc 00:10. Nếu còn hóa đơn nợ cũ, chuyển phòng sẽ bị chặn cho đến khi thanh toán xong.</p>
              </div>
            </div>

            <Field label="Ghi chú" error={fieldErrors.note}>
              <textarea
                value={note}
                onChange={(event) => setNote(event.target.value)}
                placeholder="Nhập ghi chú vận hành, lý do chuyển hoặc phân bổ nội bộ..."
                rows={4}
                className={cn(inputClass, 'resize-none')}
              />
            </Field>

            {submitError && (
              <div className="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-black text-rose-700">
                {submitError}
              </div>
            )}

            <div className="mt-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <p className="text-xs font-semibold leading-6 text-[#6f6254]">
                {selectedRoom
                  ? `${destinationRoomHasContract ? 'Phòng đích đang có hợp đồng active.' : 'Phòng đích trống, sẽ tạo hợp đồng chờ ký.'} ${destinationRoomCapacity > 0 ? `Còn ${destinationRoomCapacity} chỗ trống.` : ''}`
                  : 'Chọn phòng đích để hệ thống tính lại cọc và settlement.'}
              </p>
              <button
                type="button"
                onClick={() => void handleSubmit()}
                disabled={!canSubmit}
                className="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl border border-[#d9a441]/25 bg-[#24170d] px-5 text-sm font-black uppercase tracking-[0.16em] text-[#fff4df] shadow-lg shadow-[#24170d]/10 transition hover:bg-[#3b2918] disabled:cursor-not-allowed disabled:opacity-50"
              >
                {isSubmitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <ArrowRightLeft className="h-4 w-4" />}
                {isSubmitting ? 'Đang lên lịch...' : 'Lên lịch chuyển phòng'}
              </button>
            </div>
          </section>
        </div>

        <aside className="space-y-6 xl:sticky xl:top-6 xl:self-start">
          <section className="rounded-[2rem] border border-[#372515]/10 bg-[#1f150f] p-5 text-[#fff4df] shadow-2xl shadow-[#6b3f1d]/16">
            <p className="text-[10px] font-black uppercase tracking-[0.22em] text-[#d9a441]">Tóm tắt chuyển phòng</p>
            <h3 className="mt-2 text-2xl font-black tracking-[-0.04em]">{selectedRoom ? `Phòng ${selectedRoom.room_number}` : 'Chưa chọn phòng đích'}</h3>
            <p className="mt-2 text-sm font-semibold leading-6 text-[#f8e8c8]/78">
              {selectedRoom
                ? `${selectedRoom.building?.name || selectedRoom.building_name || `Tòa nhà #${selectedRoom.building_id}`} · ${selectedRoom.current_occupants > 0 ? 'Ghép vào hợp đồng hiện tại' : 'Tạo hợp đồng mới chờ ký'}`
                : 'Chọn một phòng trong danh sách để tính settlement chính xác.'}
            </p>

            <div className="mt-5 grid gap-3">
              <SummaryLine label="Khách được chuyển" value={`${selectedTenantIds.length} người`} />
              <SummaryLine label="Ngày thực thi" value={effectiveMovementDate} />
              <SummaryLine label="Cọc khả dụng" value={formatCurrency(availableAfterCosts)} accent />
              <SummaryLine label="Cọc cho phòng mới" value={formatCurrency(depositAppliedToNewRoom)} accent />
              <SummaryLine label="Hoàn cọc thủ công" value={formatCurrency(manualRefundAmount)} />
              <SummaryLine label="Thu QR còn thiếu" value={formatCurrency(settlementDueAmount)} />
            </div>

            <div className="mt-5 rounded-[1.5rem] border border-white/10 bg-white/6 p-4">
              <p className="text-[10px] font-black uppercase tracking-[0.22em] text-[#d9a441]">Trạng thái phòng đích</p>
              <div className="mt-3 flex flex-wrap gap-2">
                <StatusPill tone={selectedRoom ? (destinationRoomHasContract ? 'success' : 'warning') : 'neutral'}>
                  {selectedRoom ? (destinationRoomHasContract ? 'Đã có hợp đồng active' : 'Phòng trống') : 'Chưa chọn phòng'}
                </StatusPill>
                {selectedRoom && (
                  <StatusPill tone="neutral">{selectedRoom.current_occupants}/{selectedRoom.max_occupants || '∞'} chỗ</StatusPill>
                )}
              </div>
            </div>

            <div className="mt-5 rounded-[1.5rem] border border-[#d9a441]/20 bg-[#d9a441]/10 p-4 text-sm font-semibold leading-6 text-[#f8e8c8]/85">
              Nếu admin muốn hoàn cọc, hãy tạo phiếu chi thủ công sau khi hệ thống trả `manual_refund_amount`. Hệ thống không tự sinh expense hoàn tiền.
            </div>
          </section>

          <section className="rounded-[2rem] border border-[#372515]/10 bg-[#fffaf1]/92 p-5 shadow-xl shadow-[#6b3f1d]/8">
            <p className="text-[10px] font-black uppercase tracking-[0.22em] text-[#8b5e34]/60">Hợp đồng hiện tại</p>
            <div className="mt-3 space-y-3">
              <DetailRow label="Mã hợp đồng" value={currentContractCode || '—'} />
              <DetailRow label="Phòng hiện tại" value={currentRoomNumber ? `Phòng ${currentRoomNumber}` : '—'} />
              <DetailRow label="Tòa nhà" value={currentBuildingName || '—'} />
              <DetailRow label="Đại diện" value={currentContract?.representative_tenant?.full_name || tenant?.full_name || '—'} />
            </div>
          </section>

          <section className="rounded-[2rem] border border-[#372515]/10 bg-[#fffaf1]/92 p-5 shadow-xl shadow-[#6b3f1d]/8">
            <p className="text-[10px] font-black uppercase tracking-[0.22em] text-[#8b5e34]/60">Khách trong hợp đồng</p>
            <div className="mt-3 space-y-2">
              {selectedTenantCards.length > 0 ? selectedTenantCards.map((card) => (
                <div key={card.tenantId} className="rounded-2xl border border-[#372515]/10 bg-white/75 px-4 py-3">
                  <p className="text-sm font-black text-[#24170d]">{card.fullName}</p>
                  <p className="mt-1 text-xs font-semibold text-[#6f6254]">{card.phone || card.email || `Tenant #${card.tenantId}`}</p>
                </div>
              )) : (
                <div className="rounded-2xl border border-dashed border-[#372515]/12 bg-white/75 px-4 py-3 text-sm font-semibold text-[#6f6254]">Chưa có khách nào được chọn.</div>
              )}
            </div>
          </section>
        </aside>
      </div>
    </section>
  )
}

function TenantPicker({ keyword, buildingFilter, buildingOptions, isBuildingFilterDisabled, tenants, isLoading, errorMessage, currentPage, totalPages, totalTenants, paginationStart, paginationEnd, visiblePages, perPage, onKeywordChange, onBuildingChange, onPageChange, onPerPageChange, onSelectTenant }: TenantPickerProps) {
  return (
    <section className="space-y-6 text-[#24170d] sm:space-y-8">
      <section className="overflow-hidden rounded-[2.15rem] border border-[#372515]/10 bg-[#1f150f] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-5 text-[#fff4df] sm:p-6 lg:p-7">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_16%,rgba(217,164,65,0.28),transparent_30%),radial-gradient(circle_at_86%_6%,rgba(15,118,110,0.32),transparent_30%),linear-gradient(135deg,#1f150f_0%,#3b2918_52%,#0f3f3b_100%)]" />
          <div className="relative flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
            <div className="max-w-3xl space-y-4">
              <Link to="/admin/room-movements" className="inline-flex items-center gap-2 text-xs font-black uppercase tracking-[0.18em] text-[#d9a441] transition hover:text-[#f6cd73]">
                <ArrowLeft className="h-3.5 w-3.5" /> Lịch sử phòng & cọc
              </Link>
              <div className="flex flex-wrap items-center gap-3">
                <span className="inline-flex h-14 w-14 items-center justify-center rounded-[1.25rem] border border-[#d9a441]/35 bg-[#d9a441]/15 text-[#d9a441] shadow-xl shadow-black/15">
                  <Users className="h-6 w-6" />
                </span>
                <div>
                  <p className="text-[10px] font-black uppercase tracking-[0.22em] text-[#d9a441]">Chọn khách thuê</p>
                  <h1 className="mt-1 text-3xl font-black tracking-[-0.055em] sm:text-4xl">Bắt đầu lịch chuyển phòng</h1>
                </div>
              </div>

            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:min-w-[28rem] xl:grid-cols-2">
              <MetricCard label="Tổng khách" value={totalTenants} icon={<Users className="h-4 w-4" />} />
              <MetricCard label="Trang hiện tại" value={currentPage} suffix={`/${totalPages}`} icon={<ReceiptText className="h-4 w-4" />} />
            </div>
          </div>
        </div>
      </section>

      <section className="rounded-[2rem] border border-[#372515]/10 bg-[#fffaf1]/92 p-4 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur lg:p-5">
        <div className="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1fr)_16rem]">
          <label className="relative block">
            <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8b5e34]/60" />
            <input value={keyword} onChange={(event) => onKeywordChange(event.target.value)} placeholder="Tìm theo tên, số điện thoại, email, mã hợp đồng..." className={cn(inputClass, 'pl-11')} />
          </label>
          <AdminSelect value={buildingFilter} options={buildingOptions} onChange={(value) => onBuildingChange(String(value))} disabled={isBuildingFilterDisabled} />
        </div>

        <div className="mt-3 flex flex-wrap items-center gap-2 text-xs font-black text-[#0f5f59]">
          {keyword && <span className="rounded-full border border-[#0f766e]/20 bg-[#0f766e]/10 px-3 py-1">Từ khóa: {keyword}</span>}
          {buildingFilter && (
            <span className="rounded-full border border-[#0f766e]/20 bg-[#0f766e]/10 px-3 py-1">
              Tòa nhà: {buildingOptions.find((option) => String(option.value) === String(buildingFilter))?.label || `#${buildingFilter}`}
            </span>
          )}
        </div>
      </section>

      <section className="rounded-[2rem] border border-[#372515]/10 bg-white/82 p-4 shadow-2xl shadow-[#6b3f1d]/10 backdrop-blur sm:p-5">
        {errorMessage && <div className="mb-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{errorMessage}</div>}

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {isLoading && Array.from({ length: 9 }).map((_, index) => (
            <div key={index} className="h-28 animate-pulse rounded-[1.4rem] bg-stone-100" />
          ))}

          {!isLoading && tenants.map((tenant) => (
            <button
              key={tenant.id}
              type="button"
              onClick={() => onSelectTenant(tenant)}
              className="group rounded-[1.4rem] border border-[#372515]/10 bg-[#fffaf1] p-4 text-left transition hover:-translate-y-0.5 hover:border-[#d9a441]/30 hover:bg-white hover:shadow-lg hover:shadow-[#6b3f1d]/8 focus:outline-none focus:ring-4 focus:ring-[#d9a441]/15"
            >
              <div className="flex items-start justify-between gap-3">
                <div className="flex items-start gap-3">
                  <span className="flex h-11 w-11 items-center justify-center rounded-2xl border border-[#372515]/10 bg-white text-[#8b5e34]"><UserRound className="h-5 w-5" /></span>
                  <div className="space-y-1">
                    <p className="text-sm font-black text-[#24170d]">{tenant.full_name || tenant.username}</p>
                    <p className="text-xs font-semibold text-[#6f6254]">{tenant.phone || tenant.email || `Tenant #${tenant.id}`}</p>
                  </div>
                </div>
                <span className="inline-flex rounded-full bg-[#efe2cf]/75 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-[#8b5e34]">Chọn</span>
              </div>

              <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
                <DetailRow label="Phòng hiện tại" value={getTenantRoomText(tenant)} compact />
                <DetailRow label="Hợp đồng" value={tenant.current_contract?.contract_code || '—'} compact />
              </div>
            </button>
          ))}

          {!isLoading && tenants.length === 0 && (
            <div className="col-span-full rounded-[1.4rem] border border-dashed border-[#372515]/12 bg-[#fffaf1]/70 px-6 py-10 text-center text-sm font-semibold text-[#6f6254]">
              Không tìm thấy khách thuê đang ở phù hợp với bộ lọc.
            </div>
          )}
        </div>

        <div className="mt-5 flex flex-col gap-3 border-t border-[#372515]/10 bg-[#fff8eb]/85 px-1 pt-4 lg:flex-row lg:items-center lg:justify-between">
          <p className="text-xs font-black text-[#6f6254]">
            Hiển thị <span className="tabular-nums text-[#24170d]">{paginationStart}</span>-<span className="tabular-nums text-[#24170d]">{paginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{totalTenants}</span> khách thuê
          </p>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div className="w-full sm:w-36">
              <AdminSelect value={perPage} options={tenantPickerPerPageOptions} onChange={onPerPageChange} menuPlacement="top" />
            </div>
            <div className="flex items-center justify-end gap-1.5">
              <button type="button" disabled={currentPage <= 1} onClick={() => onPageChange(currentPage - 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#372515]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#d9a441]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang trước">
                <ChevronLeft className="h-4 w-4" />
              </button>
              {visiblePages.map((page, index) => {
                const previousPage = visiblePages[index - 1]
                const hasGap = previousPage && page - previousPage > 1

                return (
                  <div key={page} className="flex items-center gap-1.5">
                    {hasGap && <span className="px-1 text-xs font-black text-[#8b5e34]/60">...</span>}
                    <button type="button" onClick={() => onPageChange(page)} className={cn('inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition', page === currentPage ? 'border-[#24170d] bg-[#24170d] text-[#fff4df] shadow-sm' : 'border-[#372515]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#d9a441]/15')} aria-current={page === currentPage ? 'page' : undefined}>
                      {page}
                    </button>
                  </div>
                )
              })}
              <button type="button" disabled={currentPage >= totalPages} onClick={() => onPageChange(currentPage + 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#372515]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#d9a441]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang sau">
                <ChevronRight className="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      </section>
    </section>
  )
}

function TransferResultPanel({ tenant, contract, result, selectedTenantCards, selectedRoom, manualRefundAmount, settlementDueAmount, extraChargeAmount, depositDueAmount, onNewTransfer, onViewMovement }: { tenant: AdminTenantResource; contract: AdminContractResource | null; result: TransferRoomResultResource; selectedTenantCards: TenantCardState[]; selectedRoom: AdminRoomResource | null; manualRefundAmount: number; settlementDueAmount: number; extraChargeAmount: number; depositDueAmount: number; onNewTransfer: () => void; onViewMovement: (transferCode: string) => void }) {
  const transferCode = result.transfer_code || result.movement.transfer_code || result.movements?.[0]?.transfer_code || ''
  const scheduledDate = result.movement.movement_date || String(result.scheduled_payload?.movement_date ?? '—')
  const movementStatus = result.status_label || result.movement.status_label || 'Chờ xử lý'
  const movementCount = result.movements?.length || 1

  return (
    <section className="space-y-6 text-[#24170d] sm:space-y-8">
      <section className="overflow-hidden rounded-[2.15rem] border border-[#372515]/10 bg-[#1f150f] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-5 text-[#fff4df] sm:p-6 lg:p-7">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_16%,rgba(217,164,65,0.28),transparent_30%),radial-gradient(circle_at_86%_6%,rgba(15,118,110,0.32),transparent_30%),linear-gradient(135deg,#1f150f_0%,#3b2918_52%,#0f3f3b_100%)]" />
          <div className="relative grid gap-6 xl:grid-cols-[1fr_auto] xl:items-end">
            <div>
              <p className="text-[10px] font-black uppercase tracking-[0.22em] text-[#d9a441]">Đã lên lịch chuyển phòng</p>
              <h1 className="mt-2 text-3xl font-black tracking-[-0.055em] sm:text-4xl">{transferCode || 'TRF-...'}</h1>
              <p className="mt-3 max-w-2xl text-sm font-semibold leading-6 text-[#f8e8c8]/78">
                {tenant.full_name || tenant.username} đã được gắn lịch chuyển phòng. Mã chuyển này sẽ được dùng cho QR settlement và lịch sử room movement.
              </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:min-w-[24rem] xl:grid-cols-2">
              <MetricCard label="Trạng thái" value={0} textValue={movementStatus} icon={<CheckCircle2 className="h-4 w-4" />} />
              <MetricCard label="Dòng movement" value={movementCount} icon={<ReceiptText className="h-4 w-4" />} />
            </div>
          </div>
        </div>
      </section>

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div className="space-y-6">
          <section className="rounded-[2rem] border border-[#372515]/10 bg-[#fffaf1]/92 p-4 shadow-xl shadow-[#6b3f1d]/8 sm:p-5 lg:p-6">
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
              <ResultCard icon={<ArrowRightLeft className="h-4 w-4" />} title="Luồng chuyển">
                <div className="space-y-2 text-sm font-semibold leading-6 text-[#6f6254]">
                  <p>Ngày chuyển: <span className="font-black text-[#24170d]">{scheduledDate}</span></p>
                  <p>Phòng đích: <span className="font-black text-[#24170d]">{selectedRoom ? `Phòng ${selectedRoom.room_number}` : '—'}</span></p>
                  <p>Hợp đồng mới: <span className="font-black text-[#24170d]">{selectedRoom?.current_occupants ? 'Ghép active contract' : 'Pending-sign'}</span></p>
                </div>
              </ResultCard>

              <ResultCard icon={<WalletCards className="h-4 w-4" />} title="Settlement">
                <div className="space-y-2 text-sm font-semibold leading-6 text-[#6f6254]">
                  <p>Hoàn tay: <span className="font-black text-[#24170d]">{formatCurrency(manualRefundAmount)}</span></p>
                  <p>Còn thiếu QR: <span className="font-black text-[#24170d]">{formatCurrency(settlementDueAmount)}</span></p>
                  <p>Thiếu do cọc: <span className="font-black text-[#24170d]">{formatCurrency(depositDueAmount)}</span></p>
                  <p>Thiếu do khấu trừ vượt: <span className="font-black text-[#24170d]">{formatCurrency(extraChargeAmount)}</span></p>
                </div>
              </ResultCard>

              <ResultCard icon={<Users className="h-4 w-4" />} title="Khách chuyển">
                <div className="space-y-2 text-sm font-semibold leading-6 text-[#6f6254]">
                  {selectedTenantCards.map((card) => (
                    <p key={card.tenantId} className="truncate font-black text-[#24170d]">{card.fullName}</p>
                  ))}
                </div>
              </ResultCard>
            </div>
          </section>

          <section className="rounded-[2rem] border border-[#372515]/10 bg-[#fffaf1]/92 p-4 shadow-xl shadow-[#6b3f1d]/8 sm:p-5 lg:p-6">
            <div className="flex items-center gap-3">
              <span className="flex h-11 w-11 items-center justify-center rounded-2xl border border-[#372515]/10 bg-white/80 text-[#8b5e34]"><ReceiptText className="h-5 w-5" /></span>
              <div>
                <h2 className="text-xl font-black tracking-[-0.04em] sm:text-2xl">Chi tiết lịch đã tạo</h2>
                <p className="mt-1 text-sm font-semibold text-[#6f6254]">Mã chuyển phòng được dùng cho lịch sử, QR settlement và broadcast realtime.</p>
              </div>
            </div>

            <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
              <DetailRow label="Mã chuyển" value={transferCode || '—'} />
              <DetailRow label="Trạng thái" value={movementStatus} />
              <DetailRow label="Ngày chuyển" value={scheduledDate} />
              <DetailRow label="Hợp đồng cũ" value={contract?.contract_code || tenant.current_contract?.contract_code || '—'} />
              <DetailRow label="Phòng đích" value={selectedRoom ? `Phòng ${selectedRoom.room_number}` : '—'} />
              <DetailRow label="Số khách" value={`${selectedTenantCards.length} người`} />
            </div>
          </section>
        </div>

        <aside className="space-y-6 xl:sticky xl:top-6 xl:self-start">
          <section className="rounded-[2rem] border border-[#372515]/10 bg-[#1f150f] p-5 text-[#fff4df] shadow-2xl shadow-[#6b3f1d]/16">
            <p className="text-[10px] font-black uppercase tracking-[0.22em] text-[#d9a441]">Thanh toán thủ công</p>
            <h3 className="mt-2 text-2xl font-black tracking-[-0.04em]">Admin tự xử lý phiếu chi</h3>
            <div className="mt-4 space-y-3">
              <SummaryLine label="Mã chuyển" value={transferCode || '—'} />
              <SummaryLine label="Hoàn tay" value={formatCurrency(manualRefundAmount)} accent />
              <SummaryLine label="Settlement QR" value={formatCurrency(settlementDueAmount)} accent />
            </div>
            <div className="mt-5 rounded-[1.5rem] border border-[#d9a441]/20 bg-[#d9a441]/10 p-4 text-sm font-semibold leading-6 text-[#f8e8c8]/85">
              Không có phiếu chi auto. Admin tự tạo phiếu chi cho phần hoàn cọc nếu cần.
            </div>
          </section>

          <section className="rounded-[2rem] border border-[#372515]/10 bg-[#fffaf1]/92 p-5 shadow-xl shadow-[#6b3f1d]/8">
            <p className="text-[10px] font-black uppercase tracking-[0.22em] text-[#8b5e34]/60">Hành động</p>
            <div className="mt-4 space-y-3">
              <button type="button" onClick={onNewTransfer} className="inline-flex w-full min-h-12 items-center justify-center gap-2 rounded-2xl border border-[#d9a441]/25 bg-[#24170d] px-5 text-sm font-black uppercase tracking-[0.16em] text-[#fff4df] shadow-lg shadow-[#24170d]/10 transition hover:bg-[#3b2918]">
                <Plus className="h-4 w-4" /> Lên lịch mới
              </button>
              {transferCode && (
                <button type="button" onClick={() => onViewMovement(transferCode)} className="inline-flex w-full min-h-12 items-center justify-center gap-2 rounded-2xl border border-[#372515]/10 bg-white px-5 text-sm font-black uppercase tracking-[0.16em] text-[#24170d] transition hover:border-[#0f766e]/20 hover:bg-[#0f766e]/8 hover:text-[#0f5f59]">
                  <ArrowRightLeft className="h-4 w-4" /> Xem lịch sử
                </button>
              )}
            </div>
          </section>
        </aside>
      </div>
    </section>
  )
}

function MetricCard({ label, value, icon, currency = false, suffix, textValue }: { label: string; value: number; icon: ReactNode; currency?: boolean; suffix?: string; textValue?: string }) {
  return (
    <div className="rounded-3xl border border-[#f8e8c8]/12 bg-[#f8e8c8]/10 px-4 py-3 text-[#fff4df] backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/10">
      <div className="flex items-center justify-between gap-3 text-[#d9a441]">
        {icon}
        <span className="text-[10px] font-black uppercase tracking-[0.18em] opacity-75">{label}</span>
      </div>
      <p className="mt-2 text-3xl font-black tracking-tight tabular-nums">
        {textValue ?? (currency ? formatCurrency(value) : value)}
        {suffix ? <span className="ml-2 text-sm font-black uppercase tracking-[0.16em] text-[#f8e8c8]/72">{suffix}</span> : null}
      </p>
    </div>
  )
}

function SummaryLine({ label, value, accent = false }: { label: string; value: string; accent?: boolean }) {
  return (
    <div className="flex items-center justify-between gap-3 rounded-2xl border border-white/8 bg-white/6 px-4 py-3 text-sm">
      <span className="font-semibold text-[#f8e8c8]/72">{label}</span>
      <span className={cn('font-black tabular-nums text-right', accent ? 'text-[#d9a441]' : 'text-[#fff4df]')}>{value}</span>
    </div>
  )
}

function ResultCard({ icon, title, children }: { icon: ReactNode; title: string; children: ReactNode }) {
  return (
    <section className="rounded-[1.6rem] border border-[#372515]/10 bg-white/70 p-4 shadow-sm">
      <div className="flex items-center gap-3 text-[#8b5e34]">
        <span className="flex h-9 w-9 items-center justify-center rounded-2xl border border-[#372515]/10 bg-[#fffaf1]">{icon}</span>
        <p className="text-[10px] font-black uppercase tracking-[0.18em]">{title}</p>
      </div>
      <div className="mt-3">{children}</div>
    </section>
  )
}

function StatusPill({ tone, children }: { tone: 'neutral' | 'warning' | 'success'; children: ReactNode }) {
  const className = {
    neutral: 'border-[#372515]/10 bg-white/10 text-[#fff4df]',
    warning: 'border-[#d9a441]/20 bg-[#d9a441]/12 text-[#f6cd73]',
    success: 'border-[#0f766e]/20 bg-[#0f766e]/12 text-[#9be4db]',
  }[tone]

  return <span className={cn('inline-flex rounded-full border px-3 py-1.5 text-xs font-black uppercase tracking-[0.16em]', className)}>{children}</span>
}

function SummaryStat({ label, value, tone }: { label: string; value: number; tone: 'neutral' | 'warning' | 'success' }) {
  const toneClass = {
    neutral: 'text-[#24170d]',
    warning: 'text-[#8a4f18]',
    success: 'text-[#0f5f59]',
  }[tone]

  return (
    <div className="rounded-2xl border border-[#372515]/10 bg-[#fffaf1] p-3 shadow-sm">
      <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/60">{label}</p>
      <p className={cn('mt-1 text-lg font-black tabular-nums', toneClass)}>{formatCurrency(value)}</p>
    </div>
  )
}

function LedgerTile({ label, value, tone = 'neutral' }: { label: string; value: number; tone?: 'neutral' | 'success' | 'danger' | 'warning' }) {
  const toneClass = {
    neutral: 'text-[#24170d]',
    success: 'text-[#0f5f59]',
    danger: 'text-rose-700',
    warning: 'text-[#8a4f18]',
  }[tone]

  return (
    <div className="rounded-2xl border border-[#372515]/10 bg-white/80 p-3 shadow-sm">
      <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/60">{label}</p>
      <p className={cn('mt-1 text-lg font-black tabular-nums', toneClass)}>{formatCurrency(value)}</p>
    </div>
  )
}

function DetailRow({ label, value, compact = false }: { label: string; value?: string | null; compact?: boolean }) {
  return (
    <div className={cn('rounded-2xl border border-[#372515]/10 bg-[#fffaf1] p-3', compact ? 'shadow-none' : 'shadow-sm')}>
      <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/60">{label}</p>
      <p className="mt-1 break-words text-sm font-black text-[#24170d]">{value || '—'}</p>
    </div>
  )
}

function Field({ label, error, children }: { label: string; error?: string[]; children: ReactNode }) {
  return (
    <label className="block space-y-2">
      <span className="text-xs font-black uppercase tracking-[0.18em] text-[#8b5e34]/65">{label}</span>
      {children}
      {error?.length ? <span className="block text-xs font-semibold text-rose-700">{error[0]}</span> : null}
    </label>
  )
}

function tenantPickerPerPageOptionsFactory(): AdminSelectOption[] {
  return [
    { value: 5, label: '5 dòng', tone: 'default' as const },
    { value: 10, label: '10 dòng', tone: 'default' as const },
    { value: 20, label: '20 dòng', tone: 'default' as const },
    { value: 50, label: '50 dòng', tone: 'default' as const },
  ]
}

const tenantPickerPerPageOptions = tenantPickerPerPageOptionsFactory()

function mergeBuildingResources(...groups: Array<Array<Partial<BuildingResource> | null | undefined>>): BuildingResource[] {
  const byId = new Map<number, BuildingResource>()

  groups.flat().forEach((building) => {
    const id = Number(building?.id)
    const name = String(building?.name ?? '').trim()

    if (!Number.isFinite(id) || id <= 0 || !name) return

    const existing = byId.get(id)
    byId.set(id, {
      id,
      name,
      gender_policy: building?.gender_policy ?? existing?.gender_policy ?? null,
    })
  })

  return Array.from(byId.values()).sort((left, right) => left.name.localeCompare(right.name, 'vi'))
}

function tenantsToBuildingResources(tenants: AdminTenantResource[]): BuildingResource[] {
  return mergeBuildingResources(tenants.map((tenant) => {
    const id = tenant.current_room?.building_id ?? tenant.building_id
    const name = tenant.current_room?.building_name ?? tenant.building_name
    return id && name ? { id, name } : null
  }))
}

function roomsToBuildingResources(rooms: AdminRoomResource[]): BuildingResource[] {
  return mergeBuildingResources(rooms.map((room) => ({
    id: room.building_id,
    name: room.building?.name ?? room.building_name,
    gender_policy: room.building?.gender_policy ?? null,
  })))
}

function buildBuildingOptions(buildings: BuildingResource[], allLabel?: string): AdminSelectOption[] {
  const options = buildings.map((building) => ({ value: String(building.id), label: building.name, tone: 'default' as const }))

  return allLabel ? [{ value: '', label: allLabel, tone: 'default' as const }, ...options] : options
}

function normalizeBuildingResponse(response: unknown): BuildingResource[] {
  const envelope = response as { result?: unknown; data?: unknown }
  const result = envelope.result ?? envelope.data

  if (Array.isArray(result)) return result as BuildingResource[]

  if (result && typeof result === 'object') {
    const paginated = result as { data?: unknown; result?: unknown; buildings?: unknown }
    if (Array.isArray(paginated.data)) return paginated.data as BuildingResource[]
    if (Array.isArray(paginated.result)) return paginated.result as BuildingResource[]
    if (Array.isArray(paginated.buildings)) return paginated.buildings as BuildingResource[]

    if (paginated.data && typeof paginated.data === 'object') {
      const nested = paginated.data as { data?: unknown; buildings?: unknown }
      if (Array.isArray(nested.data)) return nested.data as BuildingResource[]
      if (Array.isArray(nested.buildings)) return nested.buildings as BuildingResource[]
    }
  }

  return []
}

function normalizeAdminTenantsResponse(response: Awaited<ReturnType<typeof fetchAdminTenants>>) {
  const envelope = response as { result?: unknown; data?: unknown }
  const result = envelope.result ?? envelope.data

  if (!result) {
    return { data: [] as AdminTenantResource[], meta: null as AdminPaginationMeta | null }
  }

  if (Array.isArray(result)) {
    return { data: result as AdminTenantResource[], meta: null }
  }

  const maybePaginated = result as AdminPaginator<AdminTenantResource> & { pagination?: AdminPaginationMeta | null; result?: AdminTenantResource[] | null }

  if (Array.isArray(maybePaginated.data)) {
    return { data: maybePaginated.data, meta: maybePaginated.meta || maybePaginated.pagination || null }
  }

  if (Array.isArray(maybePaginated.result)) {
    return { data: maybePaginated.result, meta: null }
  }

  return { data: [], meta: maybePaginated.meta || maybePaginated.pagination || null }
}

function moneyNumber(value: string | number | null | undefined): number {
  if (value === null || value === undefined) return 0
  if (typeof value === 'number') return value

  const valStr = String(value).trim()
  if (/^\d+\.\d{1,2}$/.test(valStr)) {
    return Math.max(Math.round(Number(valStr)), 0)
  }

  const parsed = Number(valStr.replace(/\./g, '').replace(/,/g, '').trim() || '0')
  return Number.isFinite(parsed) ? Math.max(parsed, 0) : 0
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(Math.round(value))
}

function nextMonthStartDateString(reference = new Date()) {
  const next = new Date(reference.getFullYear(), reference.getMonth() + 1, 1)
  const year = next.getFullYear()
  const month = String(next.getMonth() + 1).padStart(2, '0')
  const day = String(next.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function getTenantRoomText(tenant: AdminTenantResource) {
  const roomNumber = tenant.current_room?.room_number ?? tenant.room_number
  const buildingName = tenant.current_room?.building_name ?? tenant.building_name

  if (!roomNumber) return 'Chưa rõ phòng hiện tại'
  return `Phòng ${roomNumber}${buildingName ? ` · ${buildingName}` : ''}`
}

function getVisibleErrorMessage(error: unknown, fallback: string) {
  if (error instanceof ApiError) return error.message || fallback
  if (error instanceof Error) return error.message
  return fallback
}
