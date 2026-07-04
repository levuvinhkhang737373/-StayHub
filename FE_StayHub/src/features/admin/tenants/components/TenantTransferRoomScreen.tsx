import { useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import {
  ArrowRightLeft,
  CalendarDays,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Loader2,
  Maximize2,
  Plus,
  ReceiptText,
  Search,
  ShieldAlert,
  UserRound,
  Users,
  WalletCards,
  Wifi,
  Wind,
  Bed,
} from 'lucide-react'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { AdminDateInput } from '../../../../shared/components/AdminDateInput'
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
const TENANT_PICKER_PAGE_SIZE = 6

const inputClass =
  'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#24170d] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/18'

interface TenantCardState {
  tenantId: number
  fullName: string
  phone?: string | null
  email?: string | null
  gender?: number | null
  isStaying?: boolean | null
}

interface VehicleBillingPreview {
  vehicleCount: number
  oldAmount: number
  newAmount: number
  oldRange: string
  newRange: string
}

interface VehicleWindowPreview {
  amount: number
  range: string
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
  const [selectedFloor, setSelectedFloor] = useState('')
  const [selectedRoomTypeId, setSelectedRoomTypeId] = useState('')
  const [roomPage, setRoomPage] = useState(1)
  const roomPerPage = 6

  const floors = useMemo(() => {
    const uniqueFloors = Array.from(new Set(rooms.map((room) => room.floor).filter((f) => f !== undefined && f !== null)))
    uniqueFloors.sort((a, b) => Number(a) - Number(b))
    return uniqueFloors
  }, [rooms])

  const roomTypes = useMemo(() => {
    const typeMap = new Map<number, string>()
    rooms.forEach((room) => {
      if (room.room_type_id && room.room_type_name) {
        typeMap.set(room.room_type_id, room.room_type_name)
      }
    })
    return Array.from(typeMap.entries()).map(([id, name]) => ({ id, name }))
  }, [rooms])

  const [selectedTenantIds, setSelectedTenantIds] = useState<number[]>(hasSelectedTenant ? [parsedTenantId] : [])
  const [depositDeductionAmount, setDepositDeductionAmount] = useState('0')
  const [transferFee, setTransferFee] = useState('0')
  const [newDepositAmount, setNewDepositAmount] = useState('')
  const [note, setNote] = useState('')
  const [movementDate, setMovementDate] = useState(() => todayDateString())

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
        setMovementDate(todayDateString())
        setSelectedFloor('')
        setSelectedRoomTypeId('')
        setRoomPage(1)
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
  const transferChargesAmount = damageAmount + transferFeeAmount
  const destinationRoomHasContract = Boolean(selectedRoom && selectedRoom.current_occupants > 0)
  const movingAllTenants = contractTenants.length > 0 && selectedTenantIds.length >= contractTenants.length
  const usesOldDepositSettlement = Boolean(selectedRoom && movingAllTenants)
  const availableAfterCosts = usesOldDepositSettlement ? Math.max(0, oldDepositBalance - transferChargesAmount) : oldDepositBalance
  const extraChargeAmount = usesOldDepositSettlement ? Math.max(0, transferChargesAmount - oldDepositBalance) : transferChargesAmount
  const requiredNewDeposit = !selectedRoom || destinationRoomHasContract ? 0 : moneyNumber(newDepositAmount || selectedRoom.base_price)
  const depositAppliedToNewRoom = !selectedRoom || destinationRoomHasContract || !usesOldDepositSettlement ? 0 : Math.min(availableAfterCosts, requiredNewDeposit)
  const manualRefundAmount = !selectedRoom
    ? 0
    : !usesOldDepositSettlement
      ? 0
      : destinationRoomHasContract
      ? availableAfterCosts
      : Math.max(availableAfterCosts - requiredNewDeposit, 0)
  const depositDueAmount = !selectedRoom || destinationRoomHasContract
    ? 0
    : usesOldDepositSettlement
      ? Math.max(requiredNewDeposit - availableAfterCosts, 0)
      : requiredNewDeposit
  const settlementDueAmount = selectedRoom ? depositDueAmount + extraChargeAmount : 0
  const effectiveMovementDate = movementDate
  const today = useMemo(() => todayDateString(), [])
  const minimumMovementDate = useMemo(() => parseDateInput(today), [today])
  const vehicleBillingPreview = useMemo(
    () => buildVehicleBillingPreview(currentContract, selectedTenantIds, effectiveMovementDate),
    [currentContract, selectedTenantIds, effectiveMovementDate],
  )

  const showRepresentativeWarning = useMemo(() => {
    const repId = currentContract?.representative_tenant_id
    if (!repId) return false
    const isRepSelected = selectedTenantIds.includes(repId)
    const areSomeTenantsLeft = contractTenants.some((t) => !selectedTenantIds.includes(t.tenantId))
    return isRepSelected && areSomeTenantsLeft
  }, [currentContract?.representative_tenant_id, selectedTenantIds, contractTenants])

  const tenantBuildingOptions = useMemo(
    () => buildBuildingOptions(mergeBuildingResources(buildings, tenantsToBuildingResources(tenantOptions), roomsToBuildingResources(rooms)), isSuperAdmin ? 'Tất cả tòa nhà' : undefined),
    [buildings, rooms, tenantOptions, isSuperAdmin],
  )

  const destinationBuildingOptions = useMemo(
    () => buildBuildingOptions(mergeBuildingResources(buildings, roomsToBuildingResources(rooms)), isSuperAdmin ? 'Tất cả tòa nhà' : undefined),
    [buildings, rooms, isSuperAdmin],
  )

  const floorOptions = useMemo(() => {
    return [
      { value: '', label: 'Tất cả các tầng', tone: 'default' as const },
      ...floors.map((f) => ({ value: String(f), label: `Tầng ${f}`, tone: 'default' as const })),
    ]
  }, [floors])

  const roomTypeOptions = useMemo(() => {
    return [
      { value: '', label: 'Tất cả loại phòng', tone: 'default' as const },
      ...roomTypes.map((t) => ({ value: String(t.id), label: t.name, tone: 'default' as const })),
    ]
  }, [roomTypes])
  const roomCandidates = useMemo(() => {
    const keyword = roomKeyword.trim().toLowerCase()

    return rooms
      .filter((room) => room.status === ROOM_STATUS_ACTIVE)
      .filter((room) => !currentRoomId || room.id !== currentRoomId)
      .filter((room) => !selectedBuildingId || Number(room.building_id) === Number(selectedBuildingId))
      .filter((room) => !selectedFloor || Number(room.floor) === Number(selectedFloor))
      .filter((room) => !selectedRoomTypeId || Number(room.room_type_id) === Number(selectedRoomTypeId))
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
  }, [currentRoomId, roomKeyword, rooms, selectedBuildingId, selectedFloor, selectedRoomTypeId, selectedTenantIds.length, selectedTenantsInfo])

  useEffect(() => {
    setRoomPage(1)
  }, [roomKeyword, selectedBuildingId, selectedFloor, selectedRoomTypeId])

  const totalRoomsCount = roomCandidates.length
  const totalRoomPages = Math.max(1, Math.ceil(totalRoomsCount / roomPerPage))
  const roomPaginationStart = totalRoomsCount === 0 ? 0 : (roomPage - 1) * roomPerPage + 1
  const roomPaginationEnd = Math.min(roomPage * roomPerPage, totalRoomsCount)

  const paginatedRoomCandidates = useMemo(() => {
    const startIndex = (roomPage - 1) * roomPerPage
    return roomCandidates.slice(startIndex, startIndex + roomPerPage)
  }, [roomCandidates, roomPage])

  const visibleRoomPages = useMemo(() => {
    const pages: number[] = []
    const range = 2
    for (let i = 1; i <= totalRoomPages; i++) {
      if (i === 1 || i === totalRoomPages || Math.abs(i - roomPage) <= range) {
        pages.push(i)
      }
    }
    return pages
  }, [totalRoomPages, roomPage])

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
      <div className="flex min-h-[50vh] items-center justify-center rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-xl shadow-[#6b3f1d]/10">
        <div className="flex items-center gap-3 rounded-full border border-[#f3c56b]/20 bg-white px-4 py-3 text-sm font-black text-[#24170d]">
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
      {/* Premium dark header banner */}
      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative overflow-hidden p-5 text-[#fff4df] sm:p-6 lg:p-7">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_14%,rgba(243,197,107,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(15,118,110,0.28),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_50%,#0f3f3b_100%)]" />
          <div className="pointer-events-none absolute inset-x-8 bottom-0 h-px bg-gradient-to-r from-transparent via-[#f3c56b]/45 to-transparent" />
          <div className="relative flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
            <div className="space-y-4">
              <span className="block text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]/80">KHÁCH THUÊ & HỢP ĐỒNG</span>
              <h1 className="mt-3 text-3xl font-black tracking-[-0.055em] sm:text-4xl lg:text-[2.65rem] text-[#fff4df] flex items-center gap-3">
                <ArrowRightLeft className="h-8 w-8 text-[#f3c56b] shrink-0" />
                Lên lịch chuyển phòng
              </h1>
            </div>

            <div className="grid gap-3 sm:grid-cols-3 xl:min-w-[28rem] xl:grid-cols-3 items-end">
              <MetricCard label="Khách chuyển" value={selectedTenantIds.length} icon={<Users className="h-4 w-4" />} />
              <MetricCard label="Cọc còn lại" value={oldDepositBalance} currency icon={<WalletCards className="h-4 w-4" />} />
              <button
                type="button"
                onClick={changeTenant}
                className="inline-flex min-h-12 items-center justify-center gap-2 rounded-3xl border border-[#f3c56b]/25 bg-[#fffaf1]/10 px-4 text-xs font-black uppercase tracking-[0.16em] text-[#f3c56b] hover:bg-[#fffaf1]/18 hover:text-[#ffd56f] transition"
              >
                Đổi khách thuê
              </button>
            </div>
          </div>
        </div>
      </section>

      {/* Main 2-column layout */}
      <div className="grid gap-6 lg:grid-cols-[1fr_24rem] xl:grid-cols-[1fr_26rem]">
        {/* Left Column (Main forms) */}
        <div className="space-y-6">
          {/* Card A: Current Tenant & Contract Info + Roommates Checklist */}
          <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-white p-5 shadow-xl shadow-[#6b3f1d]/6">
            <div className="flex flex-col gap-4">
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <span className="text-[10px] font-black uppercase tracking-[0.22em] text-[#8b5e34]/65">Thông tin hiện tại</span>
                  <h2 className="mt-1 text-xl font-black tracking-[-0.04em] text-[#24170d]">
                    {tenant?.full_name || tenant?.username || `Khách thuê #${tenant?.id}`}
                  </h2>
                  <p className="mt-1 text-sm font-semibold text-[#6f6254]">
                    {currentBuildingName ? `${currentBuildingName} · ` : ''}
                    {currentRoomNumber ? `Phòng ${currentRoomNumber}` : 'Chưa rõ phòng'}
                    {currentContractCode ? ` · Hợp đồng: ${currentContractCode}` : ''}
                  </p>
                </div>

                <div className="flex flex-col gap-1 items-end text-right">
                  <span className="text-[10px] font-black uppercase tracking-[0.22em] text-[#8b5e34]/60">Ngày chuyển dự kiến</span>
                  <span className="inline-flex items-center gap-1.5 text-xs font-black text-[#0f5f59] bg-[#0f766e]/10 border border-[#0f766e]/15 px-3 py-1.5 rounded-full">
                    <CalendarDays className="h-4 w-4" /> {effectiveMovementDate}
                  </span>
                </div>
              </div>

              {/* Roommate checkboxes */}
              <div className="border-t border-[#3d2a18]/10 pt-4">
                <div className="flex flex-wrap items-center justify-between gap-3 mb-3">
                  <div>
                    <h3 className="text-sm font-black text-[#24170d]">Khách cùng chuyển</h3>
                    <p className="text-xs font-semibold text-[#6f6254]">Chọn người tham gia chuyển phòng trong cùng hợp đồng này.</p>
                  </div>
                  <div className="flex gap-2">
                    <button type="button" onClick={selectAllContractTenants} className="inline-flex h-8 items-center gap-1.5 rounded-xl border border-[#3d2a18]/10 bg-white px-3 text-[10px] font-black uppercase tracking-[0.14em] text-[#24170d] transition hover:bg-[#0f766e]/8">
                      Chọn hết
                    </button>
                    <button type="button" onClick={clearOtherContractTenants} className="inline-flex h-8 items-center gap-1.5 rounded-xl border border-[#3d2a18]/10 bg-white px-3 text-[10px] font-black uppercase tracking-[0.14em] text-[#24170d] transition hover:bg-[#d97706]/8">
                      Giữ một
                    </button>
                  </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                  {contractTenants.length > 0 ? contractTenants.map((contractTenant) => {
                    const checked = selectedTenantIds.includes(contractTenant.tenantId)

                    return (
                      <button
                        key={contractTenant.tenantId}
                        type="button"
                        onClick={() => toggleTenantSelection(contractTenant.tenantId)}
                        className={cn(
                          'group rounded-2xl border p-3.5 text-left transition focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/15',
                          checked
                            ? 'border-[#0f766e]/20 bg-[#0f766e]/8'
                            : 'border-[#3d2a18]/10 bg-[#fffaf1]/40 hover:border-[#f3c56b]/25 hover:bg-white',
                        )}
                      >
                        <div className="flex items-center gap-3">
                          <span className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-full border text-xs font-black transition-colors duration-200', checked ? 'border-[#0f766e]/20 bg-[#0f766e]/12 text-[#0f5f59]' : 'border-[#3d2a18]/10 bg-[#efe2cf]/50 text-[#8b5e34]')}>
                            <UserRound className="h-3.5 w-3.5" />
                          </span>
                          <div className="min-w-0 flex-1">
                            <p className="truncate text-xs font-black text-[#24170d]">{contractTenant.fullName}</p>
                            <p className="truncate text-[10px] font-semibold text-[#6f6254]">{contractTenant.phone || 'Không có sđt'}</p>
                          </div>
                          <span className={cn('shrink-0 inline-flex items-center justify-center rounded-full h-4 w-4 border transition-colors duration-200', checked ? 'border-[#0f766e]/25 bg-[#0f766e] text-white' : 'border-[#3d2a18]/20 bg-white')}>
                            {checked && <CheckCircle2 className="h-3 w-3" />}
                          </span>
                        </div>
                      </button>
                    )
                  }) : (
                    <div className="col-span-full rounded-2xl border border-dashed border-[#3d2a18]/12 bg-[#fffaf1]/50 px-4 py-4 text-xs font-semibold text-[#6f6254]">
                      Hợp đồng này chưa có danh sách khách thuê chi tiết. Hệ thống sẽ chuyển theo khách thuê hiện tại.
                    </div>
                  )}
                </div>

                {showRepresentativeWarning && (
                  <div className="mt-4 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50/50 p-4 text-xs font-semibold leading-5 text-[#8a4f18]">
                    <ShieldAlert className="mt-0.5 h-4.5 w-4.5 shrink-0 text-[#a65f16]" />
                    <div>
                      <p className="font-black text-[#24170d]">Chú ý: Chuyển người đại diện hợp đồng</p>
                      <p className="mt-1">Người đại diện hợp đồng cũ ({currentContract?.representative_tenant?.full_name || tenant?.full_name}) đang được chọn chuyển phòng, nhưng vẫn còn khách ở lại phòng cũ. Bạn cần chỉ định người đại diện mới cho hợp đồng cũ sau khi chuyển để tránh lỗi hóa đơn.</p>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </section>

          {/* Card B: Target Room Selection */}
          <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-white p-5 shadow-xl shadow-[#6b3f1d]/6">
            <div>
              <span className="text-[10px] font-black uppercase tracking-[0.22em] text-[#8b5e34]/65">Phòng chuyển đến</span>
              <h2 className="mt-1 text-xl font-black tracking-[-0.04em] text-[#24170d]">Chọn phòng đích</h2>
              <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">
                Phòng trống sẽ lập hợp đồng chờ ký mới. Phòng đang ghép sẽ thêm người vào hợp đồng hiện tại.
              </p>
            </div>

            {/* Room filters grid */}
            <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <label className="relative block">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8b5e34]/60" />
                <input
                  type="text"
                  value={roomKeyword}
                  onChange={(event) => setRoomKeyword(event.target.value)}
                  placeholder="Số phòng, tầng..."
                  className={cn(inputClass, 'pl-9 pr-3 py-2 text-xs rounded-xl')}
                />
              </label>
              <AdminSelect
                value={selectedBuildingId}
                options={destinationBuildingOptions}
                onChange={(value) => setSelectedBuildingId(String(value))}
                disabled={!isSuperAdmin && destinationBuildingOptions.length <= 1}
                className="text-xs"
              />
              <AdminSelect
                value={selectedRoomTypeId}
                options={roomTypeOptions}
                onChange={(value) => setSelectedRoomTypeId(String(value))}
                className="text-xs"
              />
              <AdminSelect
                value={selectedFloor}
                options={floorOptions}
                onChange={(value) => setSelectedFloor(String(value))}
                className="text-xs"
              />
            </div>

            {/* Rooms Grid list */}
            <div className="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-3">
              {isRoomsLoading && Array.from({ length: 6 }).map((_, index) => (
                <div key={index} className="h-36 animate-pulse rounded-2xl bg-stone-100" />
              ))}

              {!isRoomsLoading && roomCandidates.length === 0 && (
                <div className="col-span-full rounded-2xl border border-dashed border-[#3d2a18]/12 bg-[#fffaf1]/40 px-6 py-12 text-center text-sm font-semibold text-[#6f6254]">
                  Không tìm thấy phòng phù hợp với bộ lọc (hoặc do giới tính, sức chứa).
                </div>
              )}

              {!isRoomsLoading && paginatedRoomCandidates.map((room) => {
                const checked = selectedRoom?.id === room.id
                const isOccupied = room.current_occupants > 0
                const statusLabel = isOccupied ? 'Ghép phòng' : 'Phòng trống'
                const statusColor = isOccupied
                  ? 'border-amber-200 bg-amber-50 text-amber-800'
                  : 'border-emerald-200 bg-emerald-50 text-emerald-800'

                return (
                  <button
                    key={room.id}
                    type="button"
                    onClick={() => pickRoom(room)}
                    className={cn(
                      'group relative rounded-2xl border p-4 text-left transition duration-200 focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/15',
                      checked
                        ? 'border-[#0f766e] bg-[#0f766e]/5 shadow-lg shadow-[#0f766e]/6'
                        : 'border-[#3d2a18]/10 bg-[#fffaf1]/40 hover:border-[#f3c56b]/25 hover:bg-white',
                    )}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <span className={cn('inline-flex rounded-full border px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em]', statusColor)}>
                          {statusLabel}
                        </span>
                        <h3 className="mt-2 text-base font-black tracking-tight text-[#24170d]">Phòng {room.room_number}</h3>
                        <p className="text-xs font-semibold text-[#6f6254] truncate">
                          {room.building?.name || room.building_name || `Tòa nhà #${room.building_id}`}
                        </p>
                      </div>

                      <span className={cn('flex h-5 w-5 items-center justify-center rounded-full border transition-colors duration-200', checked ? 'border-[#0f766e] bg-[#0f766e] text-white' : 'border-[#3d2a18]/25 bg-white')}>
                        {checked && <CheckCircle2 className="h-3 w-3" />}
                      </span>
                    </div>

                    {/* Room features grid */}
                    <div className="mt-4 grid grid-cols-2 gap-2 text-xs">
                      <div className="rounded-xl border border-[#3d2a18]/10 bg-white/60 p-2">
                        <p className="text-[9px] font-black uppercase tracking-[0.14em] text-[#8b5e34]/70">Sức chứa</p>
                        <p className="mt-0.5 font-black text-[#24170d]">{room.current_occupants}/{room.max_occupants || '∞'} người</p>
                      </div>
                      <div className="rounded-xl border border-[#3d2a18]/10 bg-white/60 p-2">
                        <p className="text-[9px] font-black uppercase tracking-[0.14em] text-[#8b5e34]/70">Giá phòng</p>
                        <p className="mt-0.5 font-black text-[#0f5f59] tabular-nums">{formatCurrency(room.base_price)}</p>
                      </div>
                    </div>

                    {/* Amenities list */}
                    <div className="mt-3 flex flex-wrap gap-1.5 text-[10px] font-semibold text-[#8b5e34]">
                      <span className="inline-flex items-center gap-1 rounded-lg bg-[#efe2cf]/40 px-2 py-1" title="Diện tích">
                        <Maximize2 className="h-3 w-3" /> {room.area_m2 || 25} m²
                      </span>
                      <span className="inline-flex items-center gap-1 rounded-lg bg-[#efe2cf]/40 px-2 py-1" title="Wifi">
                        <Wifi className="h-3 w-3" /> Wifi
                      </span>
                      <span className="inline-flex items-center gap-1 rounded-lg bg-[#efe2cf]/40 px-2 py-1" title="Điều hòa">
                        <Wind className="h-3 w-3" /> Máy lạnh
                      </span>
                      <span className="inline-flex items-center gap-1 rounded-lg bg-[#efe2cf]/40 px-2 py-1" title="Giường ngủ">
                        <Bed className="h-3 w-3" /> Giường
                      </span>
                    </div>
                  </button>
                )
              })}
            </div>

            {/* Pagination Controls for Target Rooms */}
            {totalRoomsCount > roomPerPage && (
              <div className="mt-5 flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fffaf1]/50 rounded-2xl px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-xs font-black text-[#6f6254]">
                  Hiển thị <span className="tabular-nums text-[#24170d]">{roomPaginationStart}</span>-<span className="tabular-nums text-[#24170d]">{roomPaginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{totalRoomsCount}</span> phòng
                </p>

                <div className="flex items-center justify-end gap-1.5">
                  <button
                    type="button"
                    disabled={roomPage <= 1}
                    onClick={() => setRoomPage(roomPage - 1)}
                    className="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-white text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45"
                    aria-label="Trang trước"
                  >
                    <ChevronLeft className="h-3.5 w-3.5" />
                  </button>

                  {visibleRoomPages.map((page, index) => {
                    const previousPage = visibleRoomPages[index - 1]
                    const hasGap = previousPage && page - previousPage > 1

                    return (
                      <div key={page} className="flex items-center gap-1.5">
                        {hasGap && <span className="px-1 text-xs font-black text-[#8b5e34]/60">...</span>}
                        <button
                          type="button"
                          onClick={() => setRoomPage(page)}
                          className={cn(
                            'inline-flex h-8 min-w-8 items-center justify-center rounded-xl border px-2.5 text-xs font-black transition',
                            page === roomPage
                              ? 'border-[#24170d] bg-[#24170d] text-[#fff4df] shadow-sm'
                              : 'border-[#3d2a18]/10 bg-white text-[#8b5e34] hover:bg-[#f3c56b]/15',
                          )}
                        >
                          {page}
                        </button>
                      </div>
                    )
                  })}

                  <button
                    type="button"
                    disabled={roomPage >= totalRoomPages}
                    onClick={() => setRoomPage(roomPage + 1)}
                    className="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-white text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45"
                    aria-label="Trang sau"
                  >
                    <ChevronRight className="h-3.5 w-3.5" />
                  </button>
                </div>
              </div>
            )}
          </section>
        </div>

        {/* Right Column (Sticky Summary Sidebar Panel) */}
        <aside className="lg:sticky lg:top-6 lg:self-start">
          <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-white p-5 shadow-xl shadow-[#6b3f1d]/6">
            <h3 className="text-lg font-black tracking-[-0.03em] text-[#24170d]">Tóm tắt & Thanh toán</h3>
            <p className="mt-1 text-xs font-semibold text-[#6f6254]">
              {selectedRoom
                ? `${selectedRoom.building?.name || selectedRoom.building_name || `Tòa nhà #${selectedRoom.building_id}`} · Phòng ${selectedRoom.room_number}`
                : 'Vui lòng chọn phòng đích.'}
            </p>

            {/* Calculations List */}
            <div className="mt-5 space-y-3">
              <SummaryLine label="Số khách chuyển" value={`${selectedTenantIds.length} người`} />
              <Field label="Ngày chuyển phòng" error={fieldErrors.movement_date}>
                <AdminDateInput
                  value={movementDate}
                  onChange={setMovementDate}
                  minDate={minimumMovementDate ?? undefined}
                  placeholder="Chọn ngày chuyển"
                  className={cn(inputClass, fieldErrors.movement_date && 'border-rose-300 bg-rose-50/60 focus:border-rose-400 focus:ring-rose-200')}
                />
              </Field>

              <div className="rounded-2xl border border-[#0f766e]/15 bg-[#0f766e]/6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#0f5f59]">Preview phân bổ tiền xe</p>
                    <p className="mt-1 text-xs font-semibold leading-5 text-[#6f6254]">
                      Dựa theo cách lập hóa đơn: HĐ cũ tính đến trước ngày chuyển, HĐ mới tính từ ngày chuyển.
                    </p>
                  </div>
                  <span className="rounded-full border border-[#0f766e]/15 bg-white px-3 py-1 text-[10px] font-black text-[#0f5f59]">
                    {vehicleBillingPreview.vehicleCount} xe
                  </span>
                </div>

                <div className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                  <div className="rounded-xl border border-[#3d2a18]/8 bg-white/80 p-3">
                    <p className="text-[10px] font-black uppercase tracking-[0.14em] text-[#8b5e34]/60">HĐ cũ</p>
                    <p className="mt-1 text-lg font-black tabular-nums text-[#24170d]">{formatCurrency(vehicleBillingPreview.oldAmount)}</p>
                    <p className="mt-1 text-[11px] font-semibold text-[#6f6254]">{vehicleBillingPreview.oldRange}</p>
                  </div>
                  <div className="rounded-xl border border-[#3d2a18]/8 bg-white/80 p-3">
                    <p className="text-[10px] font-black uppercase tracking-[0.14em] text-[#8b5e34]/60">HĐ mới</p>
                    <p className="mt-1 text-lg font-black tabular-nums text-[#0f5f59]">{formatCurrency(vehicleBillingPreview.newAmount)}</p>
                    <p className="mt-1 text-[11px] font-semibold text-[#6f6254]">{vehicleBillingPreview.newRange}</p>
                  </div>
                </div>
              </div>

              <hr className="border-[#3d2a18]/8" />

              <SummaryLine label="Cọc gốc hiện tại" value={formatCurrency(oldDepositBalance)} />
              <SummaryLine label="Cách xử lý cọc cũ" value={usesOldDepositSettlement ? 'Dùng cọc cũ để quyết toán' : 'Giữ nguyên ở HĐ nguồn'} accent={usesOldDepositSettlement} />
              <div className={cn('rounded-2xl border p-3 text-xs font-bold leading-5', usesOldDepositSettlement ? 'border-[#0f766e]/15 bg-[#0f766e]/6 text-[#0f5f59]' : 'border-[#f3c56b]/40 bg-[#fff7df] text-[#8a4f18]')}>
                {usesOldDepositSettlement
                  ? 'Chuyển hết khách trong hợp đồng nguồn: backend dùng cọc cũ để trừ phí/khấu trừ, sau đó chuyển sang cọc phòng mới hoặc hoàn dư.'
                  : 'Chuyển một phần khách: backend không đem cọc cũ bù cọc mới. Cọc mới và phí/khấu trừ sẽ thu qua settlement QR.'}
              </div>

              <div className="flex flex-col gap-2 rounded-2xl border border-[#3d2a18]/8 bg-[#fffaf1]/50 p-3">
                <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/65">Chi phí phát sinh</p>
                <div className="mt-1 space-y-2">
                  <div className="flex items-center justify-between text-sm">
                    <span className="font-semibold text-[#6f6254]">Khấu trừ hư hao</span>
                    <span className="font-bold text-rose-700">{formatCurrency(damageAmount)}</span>
                  </div>
                  <div className="flex items-center justify-between text-sm">
                    <span className="font-semibold text-[#6f6254]">Phí chuyển phòng</span>
                    <span className="font-bold text-rose-700">{formatCurrency(transferFeeAmount)}</span>
                  </div>
                </div>
              </div>

              <SummaryLine label={usesOldDepositSettlement ? 'Cọc khả dụng sau phí' : 'Cọc cũ giữ lại'} value={formatCurrency(availableAfterCosts)} accent={usesOldDepositSettlement && availableAfterCosts > 0} />
              <SummaryLine label="Cọc chuyển sang phòng mới" value={formatCurrency(depositAppliedToNewRoom)} />
              <SummaryLine label="Cọc mới còn thiếu" value={formatCurrency(depositDueAmount)} accent={depositDueAmount > 0} />
              <SummaryLine label="Phí/khấu trừ thu thêm" value={formatCurrency(extraChargeAmount)} accent={extraChargeAmount > 0} />

              <hr className="border-[#3d2a18]/8" />

              {/* Outstanding payment or Refund Highlight */}
              {settlementDueAmount > 0 ? (
                <div className="rounded-2xl border border-rose-200/60 bg-rose-50/50 p-4 text-center">
                  <p className="text-[10px] font-black uppercase tracking-[0.18em] text-rose-700">Khách cần nộp thêm (QR code)</p>
                  <p className="mt-1 text-2xl font-black tabular-nums text-rose-600">{formatCurrency(settlementDueAmount)}</p>
                </div>
              ) : manualRefundAmount > 0 ? (
                <div className="rounded-2xl border border-[#0f766e]/20 bg-[#0f766e]/6 p-4 text-center">
                  <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#0f5f59]">Hoàn cọc cho khách (Thủ công)</p>
                  <p className="mt-1 text-2xl font-black tabular-nums text-[#0f5f59]">{formatCurrency(manualRefundAmount)}</p>
                </div>
              ) : (
                <div className="rounded-2xl border border-stone-200 bg-stone-50 p-4 text-center">
                  <p className="text-[10px] font-black uppercase tracking-[0.18em] text-stone-600">Đối trừ cân bằng</p>
                  <p className="mt-1 text-2xl font-black tabular-nums text-[#24170d]">{formatCurrency(0)}</p>
                </div>
              )}
            </div>

            {/* Adjustments Inputs grouped inside summary */}
            <div className="mt-6 space-y-4">
              <h4 className="text-xs font-black uppercase tracking-[0.18em] text-[#8b5e34]">Điều chỉnh cọc & phí</h4>
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                <Field label="Khấu trừ hư hao" error={fieldErrors.deposit_deduction_amount}>
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
              </div>
              <Field label="Cọc yêu cầu phòng mới" error={fieldErrors.new_deposit_amount}>
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

            {/* Ghi chú */}
            <div className="mt-6">
              <Field label="Ghi chú chuyển phòng" error={fieldErrors.note}>
                <textarea
                  value={note}
                  onChange={(event) => setNote(event.target.value)}
                  placeholder="Lý do chuyển, hư hại phòng cũ..."
                  rows={3}
                  className={cn(inputClass, 'resize-none')}
                />
              </Field>
            </div>

            {submitError && (
              <div className="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-black text-rose-700">
                {submitError}
              </div>
            )}

            {/* Action button inside Card C */}
            <div className="mt-6">
              <button
                type="button"
                onClick={() => void handleSubmit()}
                disabled={!canSubmit}
                className="w-full inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl border border-[#f3c56b]/25 bg-[#24170d] px-5 text-sm font-black uppercase tracking-[0.16em] text-[#fff4df] shadow-lg shadow-[#24170d]/10 transition hover:bg-[#3d2a18] disabled:cursor-not-allowed disabled:opacity-50"
              >
                {isSubmitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <ArrowRightLeft className="h-4 w-4" />}
                {isSubmitting ? 'Đang lên lịch...' : 'Xác nhận chuyển phòng'}
              </button>
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
      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-5 text-[#fff4df] sm:p-6 lg:p-7">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_16%,rgba(243,197,107,0.28),transparent_30%),radial-gradient(circle_at_86%_6%,rgba(15,118,110,0.32),transparent_30%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
          <div className="relative flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
            <div className="max-w-3xl space-y-4">
              <span className="block text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]/80">KHÁCH THUÊ & HỢP ĐỒNG</span>
              <h1 className="mt-3 text-3xl font-black tracking-[-0.055em] sm:text-4xl lg:text-[2.65rem] text-[#fff4df] flex items-center gap-3">
                <Users className="h-8 w-8 text-[#f3c56b] shrink-0" />
                Bắt đầu lịch chuyển phòng
              </h1>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:min-w-[28rem] xl:grid-cols-2">
              <MetricCard label="Tổng khách" value={totalTenants} icon={<Users className="h-4 w-4" />} />
              <MetricCard label="Trang hiện tại" value={currentPage} suffix={`/${totalPages}`} icon={<ReceiptText className="h-4 w-4" />} />
            </div>
          </div>
        </div>
      </section>

      <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-4 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur lg:p-5">
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

      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-white/82 shadow-2xl shadow-[#6b3f1d]/10 backdrop-blur">
        <div className="p-4 sm:p-5">
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
                className="group rounded-[1.4rem] border border-[#3d2a18]/10 bg-[#fffaf1] p-4 text-left transition hover:-translate-y-0.5 hover:border-[#f3c56b]/30 hover:bg-white hover:shadow-lg hover:shadow-[#6b3f1d]/8 focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/15"
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="flex items-start gap-3">
                    <span className="flex h-11 w-11 items-center justify-center rounded-full border border-[#3d2a18]/10 bg-[#efe2cf]/50 text-[#8b5e34] transition-colors duration-200 group-hover:bg-[#efe2cf]/85"><UserRound className="h-5 w-5" /></span>
                    <div className="space-y-1">
                      <p className="text-sm font-black text-[#24170d]">{tenant.full_name || tenant.username}</p>
                      <p className="text-xs font-semibold text-[#6f6254]">{tenant.phone || tenant.email || `Tenant #${tenant.id}`}</p>
                    </div>
                  </div>
                  <span className="inline-flex rounded-full bg-[#f3c56b]/18 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-[#8a4f18] transition-colors duration-200 group-hover:bg-[#f3c56b] group-hover:text-[#24170d]">Chọn</span>
                </div>

                <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
                  <DetailRow label="Phòng hiện tại" value={getTenantRoomText(tenant)} compact />
                  <DetailRow label="Hợp đồng" value={tenant.current_contract?.contract_code || '—'} compact />
                </div>
              </button>
            ))}

            {!isLoading && tenants.length === 0 && (
              <div className="col-span-full rounded-[1.4rem] border border-dashed border-[#3d2a18]/12 bg-[#fffaf1]/70 px-6 py-10 text-center text-sm font-semibold text-[#6f6254]">
                Không tìm thấy khách thuê đang ở phù hợp với bộ lọc.
              </div>
            )}
          </div>
        </div>

        <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb]/85 px-4 py-4 sm:px-5 lg:flex-row lg:items-center lg:justify-between">
          <p className="text-xs font-black text-[#6f6254]">
            Hiển thị <span className="tabular-nums text-[#24170d]">{paginationStart}</span>-<span className="tabular-nums text-[#24170d]">{paginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{totalTenants}</span> khách thuê
          </p>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div className="w-full sm:w-36">
              <AdminSelect value={perPage} options={tenantPickerPerPageOptions} onChange={onPerPageChange} menuPlacement="top" />
            </div>
            <div className="flex items-center justify-end gap-1.5">
              <button type="button" disabled={currentPage <= 1} onClick={() => onPageChange(currentPage - 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang trước">
                <ChevronLeft className="h-4 w-4" />
              </button>
              {visiblePages.map((page, index) => {
                const previousPage = visiblePages[index - 1]
                const hasGap = previousPage && page - previousPage > 1

                return (
                  <div key={page} className="flex items-center gap-1.5">
                    {hasGap && <span className="px-1 text-xs font-black text-[#8b5e34]/60">...</span>}
                    <button type="button" onClick={() => onPageChange(page)} className={cn('inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition', page === currentPage ? 'border-[#24170d] bg-[#24170d] text-[#fff4df] shadow-sm' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15')} aria-current={page === currentPage ? 'page' : undefined}>
                      {page}
                    </button>
                  </div>
                )
              })}
              <button type="button" disabled={currentPage >= totalPages} onClick={() => onPageChange(currentPage + 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang sau">
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
  const titleText = result.executed_immediately ? 'Đã chuyển phòng trong ngày' : result.blocked_immediately ? 'Lịch chuyển phòng đang bị chặn' : 'Đã lên lịch chuyển phòng'
  const descriptionText = result.executed_immediately
    ? `${tenant.full_name || tenant.username} đã được chuyển phòng ngay vì ngày chuyển là hôm nay. Mã chuyển này vẫn dùng cho QR settlement và lịch sử room movement.`
    : result.blocked_immediately
      ? `${tenant.full_name || tenant.username} đã được tạo lịch hôm nay nhưng chưa thể xử lý. Kiểm tra lý do bị chặn trong lịch sử room movement.`
      : `${tenant.full_name || tenant.username} đã được gắn lịch chuyển phòng. Mã chuyển này sẽ được dùng cho QR settlement và lịch sử room movement.`

  return (
    <section className="space-y-6 text-[#24170d] sm:space-y-8">
      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-5 text-[#fff4df] sm:p-6 lg:p-7">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_16%,rgba(243,197,107,0.28),transparent_30%),radial-gradient(circle_at_86%_6%,rgba(15,118,110,0.32),transparent_30%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
          <div className="relative grid gap-6 xl:grid-cols-[1fr_auto] xl:items-end">
            <div>
              <p className="text-[10px] font-black uppercase tracking-[0.22em] text-[#f3c56b]">{titleText}</p>
              <h1 className="mt-2 text-3xl font-black tracking-[-0.055em] sm:text-4xl">{transferCode || 'TRF-...'}</h1>
              <p className="mt-3 max-w-2xl text-sm font-semibold leading-6 text-[#f8e8c8]/78">
                {descriptionText}
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
          <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-4 shadow-xl shadow-[#6b3f1d]/8 sm:p-5 lg:p-6">
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

          <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-4 shadow-xl shadow-[#6b3f1d]/8 sm:p-5 lg:p-6">
            <div className="flex items-center gap-3">
              <span className="flex h-11 w-11 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-white/80 text-[#8b5e34]"><ReceiptText className="h-5 w-5" /></span>
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
          <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] p-5 text-[#fff4df] shadow-2xl shadow-[#6b3f1d]/16">
            <p className="text-[10px] font-black uppercase tracking-[0.22em] text-[#f3c56b]">Thanh toán thủ công</p>
            <h3 className="mt-2 text-2xl font-black tracking-[-0.04em]">Admin tự xử lý phiếu chi</h3>
            <div className="mt-4 space-y-3">
              <SummaryLine label="Mã chuyển" value={transferCode || '—'} />
              <SummaryLine label="Hoàn tay" value={formatCurrency(manualRefundAmount)} accent />
              <SummaryLine label="Settlement QR" value={formatCurrency(settlementDueAmount)} accent />
            </div>
            <div className="mt-5 rounded-[1.5rem] border border-[#f3c56b]/20 bg-[#f3c56b]/10 p-4 text-sm font-semibold leading-6 text-[#f8e8c8]/85">
              Không có phiếu chi auto. Admin tự tạo phiếu chi cho phần hoàn cọc nếu cần.
            </div>
          </section>

          <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-5 shadow-xl shadow-[#6b3f1d]/8">
            <p className="text-[10px] font-black uppercase tracking-[0.22em] text-[#8b5e34]/60">Hành động</p>
            <div className="mt-4 space-y-3">
              <button type="button" onClick={onNewTransfer} className="inline-flex w-full min-h-12 items-center justify-center gap-2 rounded-2xl border border-[#f3c56b]/25 bg-[#24170d] px-5 text-sm font-black uppercase tracking-[0.16em] text-[#fff4df] shadow-lg shadow-[#24170d]/10 transition hover:bg-[#3d2a18]">
                <Plus className="h-4 w-4" /> Lên lịch mới
              </button>
              {transferCode && (
                <button type="button" onClick={() => onViewMovement(transferCode)} className="inline-flex w-full min-h-12 items-center justify-center gap-2 rounded-2xl border border-[#3d2a18]/10 bg-white px-5 text-sm font-black uppercase tracking-[0.16em] text-[#24170d] transition hover:border-[#0f766e]/20 hover:bg-[#0f766e]/8 hover:text-[#0f5f59]">
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
      <div className="flex items-center justify-between gap-3 text-[#f3c56b]">
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
    <div className="flex items-center justify-between gap-3 rounded-2xl border border-[#3d2a18]/8 bg-[#fffaf1]/50 px-4 py-3 text-sm">
      <span className="font-semibold text-[#6f6254]">{label}</span>
      <span className={cn('font-black tabular-nums text-right', accent ? 'text-[#0f5f59]' : 'text-[#24170d]')}>{value}</span>
    </div>
  )
}

function ResultCard({ icon, title, children }: { icon: ReactNode; title: string; children: ReactNode }) {
  return (
    <section className="rounded-[1.6rem] border border-[#3d2a18]/10 bg-white/70 p-4 shadow-sm">
      <div className="flex items-center gap-3 text-[#8b5e34]">
        <span className="flex h-9 w-9 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1]">{icon}</span>
        <p className="text-[10px] font-black uppercase tracking-[0.18em]">{title}</p>
      </div>
      <div className="mt-3">{children}</div>
    </section>
  )
}

function DetailRow({ label, value, compact = false }: { label: string; value?: ReactNode; compact?: boolean }) {
  return (
    <div className={cn('rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3', compact ? 'shadow-none' : 'shadow-sm')}>
      <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/60">{label}</p>
      <div className="mt-1 break-words text-sm font-black text-[#24170d]">{value || '—'}</div>
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
    { value: 6, label: '6 dòng', tone: 'default' as const },
    { value: 12, label: '12 dòng', tone: 'default' as const },
    { value: 18, label: '18 dòng', tone: 'default' as const },
    { value: 36, label: '36 dòng', tone: 'default' as const },
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

function todayDateString(reference = new Date()) {
  return dateToInputString(reference)
}

function dateToInputString(date: Date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function parseDateInput(value?: string | null): Date | null {
  if (!value) return null

  const [year, month, day] = value.split('-').map(Number)
  if (!year || !month || !day) return null

  return new Date(year, month - 1, day)
}

function addDays(date: Date, days: number): Date {
  const next = new Date(date)
  next.setDate(next.getDate() + days)
  return next
}

function formatDisplayDate(date: Date): string {
  return date.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' })
}

function buildVehicleBillingPreview(contract: AdminContractResource | null, tenantIds: number[], movementDate: string): VehicleBillingPreview {
  const movement = parseDateInput(movementDate)
  if (!contract || !movement) {
    return emptyVehicleBillingPreview()
  }

  const periodStart = new Date(movement.getFullYear(), movement.getMonth(), 1)
  const periodEnd = new Date(movement.getFullYear(), movement.getMonth() + 1, 0)
  const oldWindowEnd = addDays(movement, -1)
  const selectedTenantIds = new Set(tenantIds)
  const movingVehicles = (contract.contract_vehicles ?? []).filter((contractVehicle) => {
    const tenantId = Number(contractVehicle.vehicle?.tenant_id)

    return contractVehicle.is_active !== false && selectedTenantIds.has(tenantId)
  })

  const oldWindows = movingVehicles.map((contractVehicle) => {
    const configuredEndDate = parseDateInput(contractVehicle.billing_end_date || contractVehicle.ended_at)
    const oldChargeEnd = configuredEndDate && configuredEndDate < oldWindowEnd ? configuredEndDate : oldWindowEnd

    return calculateVehicleWindowPreview(
      contractVehicle.monthly_fee,
      contractVehicle.billing_start_date || contractVehicle.started_at || dateToInputString(periodStart),
      dateToInputString(oldChargeEnd),
      periodStart,
      periodEnd,
    )
  })
  const newWindows = movingVehicles.map((contractVehicle) => calculateVehicleWindowPreview(
    contractVehicle.monthly_fee,
    movementDate,
    dateToInputString(periodEnd),
    periodStart,
    periodEnd,
  ))

  return {
    vehicleCount: movingVehicles.length,
    oldAmount: sumVehicleWindows(oldWindows),
    newAmount: sumVehicleWindows(newWindows),
    oldRange: summarizeVehicleWindows(oldWindows),
    newRange: summarizeVehicleWindows(newWindows),
  }
}

function emptyVehicleBillingPreview(): VehicleBillingPreview {
  return {
    vehicleCount: 0,
    oldAmount: 0,
    newAmount: 0,
    oldRange: '—',
    newRange: '—',
  }
}

function calculateVehicleWindowPreview(monthlyFee: string | number | null | undefined, startDate: string, endDate: string, periodStart: Date, periodEnd: Date): VehicleWindowPreview {
  const amount = moneyNumber(monthlyFee)
  const chargeStartDate = parseDateInput(startDate)
  const chargeEndDate = parseDateInput(endDate)

  if (amount <= 0 || !chargeStartDate || !chargeEndDate) {
    return { amount: 0, range: 'Không phát sinh' }
  }

  const chargeStart = chargeStartDate > periodStart ? chargeStartDate : periodStart
  const chargeEnd = chargeEndDate < periodEnd ? chargeEndDate : periodEnd

  if (chargeStart > chargeEnd) {
    return { amount: 0, range: 'Không phát sinh' }
  }

  const actualDays = Math.floor((chargeEnd.getTime() - chargeStart.getTime()) / 86_400_000) + 1
  const totalDays = periodEnd.getDate()

  return {
    amount: Math.round((amount * actualDays) / totalDays),
    range: `${formatDisplayDate(chargeStart)} – ${formatDisplayDate(chargeEnd)}`,
  }
}

function sumVehicleWindows(windows: VehicleWindowPreview[]): number {
  return windows.reduce((total, window) => total + window.amount, 0)
}

function summarizeVehicleWindows(windows: VehicleWindowPreview[]): string {
  const chargedRanges = windows.filter((window) => window.amount > 0).map((window) => window.range)

  if (chargedRanges.length === 0) return 'Không phát sinh'

  return Array.from(new Set(chargedRanges)).join(', ')
}

function getTenantRoomText(tenant: AdminTenantResource): ReactNode {
  const roomNumber = tenant.current_room?.room_number ?? tenant.room_number
  const buildingName = tenant.current_room?.building_name ?? tenant.building_name

  if (!roomNumber) return 'Chưa rõ phòng hiện tại'

  return (
    <div className="flex flex-col">
      <span>Phòng {roomNumber}</span>
      {buildingName && (
        <span className="text-[11px] font-semibold text-[#8b5e34]/70 mt-0.5">{buildingName}</span>
      )}
    </div>
  )
}

function getVisibleErrorMessage(error: unknown, fallback: string) {
  if (error instanceof ApiError) return error.message || fallback
  if (error instanceof Error) return error.message
  return fallback
}
