import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { ArrowLeft, ArrowRightLeft, Building2, CheckCircle2, ChevronLeft, ChevronRight, DoorOpen, FileSignature, Loader2, Plus, ReceiptText, Search, Trash2, UserRound, Users, WalletCards } from 'lucide-react'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { AdminSelect, type AdminSelectOption } from '../../shared/components/AdminSelect'
import { buildingAllowsTenantGender } from '../../shared/config/gender-policy'
import { fetchAdminTenantDetail, fetchAdminTenants } from '../services/tenants.service'
import { transferTenantRoom } from '../services/TranferRoom'
import { fetchAdminRooms, fetchBuilding } from '../../rooms/services/rooms.service'
import type { AdminPaginationMeta, AdminPaginator, AdminTenantResource } from '../types/tenant-api.model'
import type { AdminRoomResource, BuildingResource } from '../../rooms/types/rooms.model'
import type { TransferDeductionItemInput, TransferRoomResultResource, TransferTenantPayload } from '../types/TranferModel'
import type { AdminMeterDeviceResource } from '../../meters/types/meter-api.model'
import { fetchAdminMeterDevices } from '../../meters/services/meters.service'
import type { AdminVehicleOptionResource } from '../../contracts/types/contract-api.model'
import { fetchAdminContractVehicles } from '../../contracts/services/contracts.service'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'

const ROOM_STATUS_ACTIVE = 1
const STATUS_RENTING = 1
const TENANT_PICKER_PAGE_SIZE = 10
const PAYMENT_METHOD_CASH = 1
const PAYMENT_METHOD_BANK_TRANSFER = 2

const tenantPickerPerPageOptions = [
  { value: 5, label: '5 dòng', tone: 'default' as const },
  { value: 10, label: '10 dòng', tone: 'default' as const },
  { value: 20, label: '20 dòng', tone: 'default' as const },
  { value: 50, label: '50 dòng', tone: 'default' as const },
]

const paymentMethodOptions = [
  { value: String(PAYMENT_METHOD_BANK_TRANSFER), label: 'Chuyển khoản', tone: 'success' as const },
  { value: String(PAYMENT_METHOD_CASH), label: 'Tiền mặt', tone: 'warning' as const },
]

const inputClass =
  'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'

interface MeterReadingRow {
  meterDeviceId: string
  currentReading: string
}

interface DeductionRow {
  id: string
  name: string
  amount: string
  note: string
}

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

  const maybePaginated = result as AdminPaginator<AdminTenantResource> & {
    pagination?: AdminPaginationMeta | null
    result?: AdminTenantsResult | null
  }

  if (Array.isArray(maybePaginated.data)) {
    return { data: maybePaginated.data, meta: maybePaginated.meta || maybePaginated.pagination || null }
  }

  const nested = maybePaginated.data as unknown
  if (nested && typeof nested === 'object' && !Array.isArray(nested)) {
    const nestedPaginated = nested as AdminPaginator<AdminTenantResource> & { pagination?: AdminPaginationMeta | null }
    if (Array.isArray(nestedPaginated.data)) {
      return { data: nestedPaginated.data, meta: nestedPaginated.meta || nestedPaginated.pagination || null }
    }
  }

  if (Array.isArray(maybePaginated.result)) {
    return { data: maybePaginated.result, meta: null }
  }

  return { data: [], meta: maybePaginated.meta || maybePaginated.pagination || null }
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
  const [tenantBuildingFilter, setTenantBuildingFilter] = useState<string>('')
  const [tenantOptions, setTenantOptions] = useState<AdminTenantResource[]>([])
  const [tenantOptionsMeta, setTenantOptionsMeta] = useState<AdminPaginationMeta | null>(null)
  const [tenantCurrentPage, setTenantCurrentPage] = useState(1)
  const [tenantPerPage, setTenantPerPage] = useState(TENANT_PICKER_PAGE_SIZE)
  const [isTenantOptionsLoading, setIsTenantOptionsLoading] = useState(false)
  const [tenantOptionsError, setTenantOptionsError] = useState<string | null>(null)

  const [step, setStep] = useState<1 | 2>(1)

  const [tenant, setTenant] = useState<AdminTenantResource | null>(null)
  const [isTenantLoading, setIsTenantLoading] = useState(false)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [buildings, setBuildings] = useState<BuildingResource[]>([])
  const [selectedBuildingId, setSelectedBuildingId] = useState<string>('')
  const [rooms, setRooms] = useState<AdminRoomResource[]>([])
  const [isRoomsLoading, setIsRoomsLoading] = useState(false)
  const [roomKeyword, setRoomKeyword] = useState('')
  const [selectedRoom, setSelectedRoom] = useState<AdminRoomResource | null>(null)

  const [movementDate, setMovementDate] = useState(todayDateString())
  const [note, setNote] = useState('')
  const [depositRefundAmount, setDepositRefundAmount] = useState('')
  const [refundPaymentMethod, setRefundPaymentMethod] = useState(String(PAYMENT_METHOD_BANK_TRANSFER))
  const [newDepositAmount, setNewDepositAmount] = useState('')
  const [additionalDepositAmount, setAdditionalDepositAmount] = useState('')
  const [additionalDepositPaymentMethod, setAdditionalDepositPaymentMethod] = useState(String(PAYMENT_METHOD_BANK_TRANSFER))
  const [deductionRows, setDeductionRows] = useState<DeductionRow[]>([])
  const [transferFee, setTransferFee] = useState('')
  const [meterReadingRows, setMeterReadingRows] = useState<MeterReadingRow[]>([])
  const [meterDevices, setMeterDevices] = useState<AdminMeterDeviceResource[]>([])
  const [carryVehicleIds, setCarryVehicleIds] = useState<number[]>([])
  const [vehicles, setVehicles] = useState<AdminVehicleOptionResource[]>([])

  const [isSubmitting, setIsSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [transferResult, setTransferResult] = useState<TransferRoomResultResource | null>(null)

  const currentRoomId = tenant?.current_room?.room_id ?? tenant?.room_id ?? null
  const currentBuildingId = tenant?.current_room?.building_id ?? tenant?.building_id ?? null
  const currentRoomNumber = tenant?.current_room?.room_number ?? tenant?.room_number ?? null
  const currentBuildingName = tenant?.current_room?.building_name ?? tenant?.building_name ?? null

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
          setTenantOptionsError(getVisibleErrorMessage(error, 'Không thể tải danh sách khách thuê đang ở.'))
        })
        .finally(() => {
          if (isMounted) setIsTenantOptionsLoading(false)
        })
    }, 250)

    return () => {
      isMounted = false
      window.clearTimeout(timer)
    }
  }, [hasSelectedTenant, isSuperAdmin, managedBuildingId, tenantBuildingFilter, tenantCurrentPage, tenantKeyword, tenantPerPage])

  useEffect(() => {
    if (!hasSelectedTenant) return

    let isMounted = true
    queueMicrotask(() => {
      if (!isMounted) return
      setIsTenantLoading(true)
      setLoadError(null)
    })

    fetchAdminTenantDetail(parsedTenantId)
      .then((response) => {
        if (!isMounted) return
        setTenant(unwrap<AdminTenantResource>(response) ?? null)
      })
      .catch((error) => {
        if (!isMounted) return
        setTenant(null)
        setLoadError(getVisibleErrorMessage(error, 'Không thể tải thông tin khách thuê.'))
      })
      .finally(() => {
        if (isMounted) setIsTenantLoading(false)
      })

    return () => {
      isMounted = false
    }
  }, [hasSelectedTenant, parsedTenantId])

  useEffect(() => {
    queueMicrotask(() => {
      setStep(1)
      setSelectedRoom(null)
      setRoomKeyword('')
      setMovementDate(todayDateString())
      setNote('')
      setDepositRefundAmount('')
      setRefundPaymentMethod(String(PAYMENT_METHOD_BANK_TRANSFER))
      setNewDepositAmount('')
      setAdditionalDepositAmount('')
      setAdditionalDepositPaymentMethod(String(PAYMENT_METHOD_BANK_TRANSFER))
      setDeductionRows([])
      setTransferFee('')
      setCarryVehicleIds([])
      setSubmitError(null)
      setFieldErrors({})
      setTransferResult(null)
    })
  }, [parsedTenantId])

  useEffect(() => {
    let isMounted = true

    fetchBuilding()
      .then((response) => {
        if (!isMounted) return
        setBuildings(normalizeBuildingResponse(response))
      })
      .catch(() => {
        if (isMounted) setBuildings([])
      })

    return () => {
      isMounted = false
    }
  }, [])

  useEffect(() => {
    if (!hasSelectedTenant) {
      return
    }

    queueMicrotask(() => {
      setSelectedBuildingId(currentBuildingId ? String(currentBuildingId) : '')
    })
  }, [currentBuildingId, hasSelectedTenant])

  useEffect(() => {
    if (!selectedRoom) return

    queueMicrotask(() => setNewDepositAmount(String(selectedRoom.base_price ?? '')))
  }, [selectedRoom])

  useEffect(() => {
    if (!hasSelectedTenant) return

    let isMounted = true
    queueMicrotask(() => {
      if (isMounted) setIsRoomsLoading(true)
    })

    fetchAdminRooms({
      building_id: selectedBuildingId ? Number(selectedBuildingId) : undefined,
      per_page: 100,
    })
      .then((response) => {
        if (!isMounted) return
        setRooms(unwrap<AdminRoomResource[]>(response) ?? [])
      })
      .catch(() => {
        if (isMounted) setRooms([])
      })
      .finally(() => {
        if (isMounted) setIsRoomsLoading(false)
      })

    return () => {
      isMounted = false
    }
  }, [hasSelectedTenant, selectedBuildingId])

  useEffect(() => {
    if (!currentRoomId) {
      queueMicrotask(() => {
        setMeterDevices([])
        setMeterReadingRows([])
      })
      return
    }

    let isMounted = true

    fetchAdminMeterDevices({
      room_id: currentRoomId,
      status: 1,
      per_page: 100,
    })
      .then((response) => {
        if (!isMounted) return
        const devices = unwrap(response).data ?? []
        setMeterDevices(devices)
        setMeterReadingRows(
          devices.map((meter) => ({
            meterDeviceId: String(meter.id),
            currentReading: '',
          })),
        )
      })
      .catch(() => {
        if (!isMounted) return
        setMeterDevices([])
        setMeterReadingRows([])
      })

    return () => {
      isMounted = false
    }
  }, [currentRoomId])

  useEffect(() => {
    if (!hasSelectedTenant) return

    queueMicrotask(() => {
      setSelectedRoom(null)
      setStep(1)
    })
  }, [selectedBuildingId, hasSelectedTenant])

  useEffect(() => {
    if (!hasSelectedTenant) return

    let isMounted = true

    fetchAdminContractVehicles({
      tenant_id: parsedTenantId,
      is_active: true,
      per_page: 100,
    })
      .then((response) => {
        if (!isMounted) return
        setVehicles(unwrap(response).data ?? [])
      })
      .catch(() => {
        if (isMounted) setVehicles([])
      })

    return () => {
      isMounted = false
    }
  }, [hasSelectedTenant, parsedTenantId])

  const sessionManagedBuildings = useMemo(
    () => (session?.admin.managed_buildings ?? []).map((building) => ({
      id: Number(building.id),
      name: building.name,
      gender_policy: building.gender_policy ?? null,
    })),
    [session?.admin.managed_buildings],
  )

  const tenantOptionBuildings = useMemo(
    () => tenantsToBuildingResources(tenantOptions),
    [tenantOptions],
  )

  const roomOptionBuildings = useMemo(
    () => roomsToBuildingResources(rooms),
    [rooms],
  )

  const buildingSources = useMemo(
    () => mergeBuildingResources(buildings, sessionManagedBuildings, tenantOptionBuildings, roomOptionBuildings),
    [buildings, roomOptionBuildings, sessionManagedBuildings, tenantOptionBuildings],
  )

  const availableRooms = useMemo(() => {
    const keyword = roomKeyword.trim().toLowerCase()
    return rooms.filter((room) => {
      if (room.id === currentRoomId) return false
      if (room.status !== ROOM_STATUS_ACTIVE) return false
      if (room.current_occupants > 0) return false
      const roomBuildingPolicy = room.building?.gender_policy ?? buildingSources.find((building) => building.id === room.building_id)?.gender_policy
      if (!buildingAllowsTenantGender(roomBuildingPolicy, tenant?.gender)) return false
      if (keyword && !(room.room_number ?? '').toLowerCase().includes(keyword)) return false
      return true
    })
  }, [rooms, currentRoomId, roomKeyword, buildingSources, tenant?.gender])

  const scopedBuildings = useMemo(() => {
    if (isSuperAdmin) return buildingSources

    const managedIds = new Set((session?.admin.managed_buildings ?? []).map((building) => Number(building.id)))
    return buildingSources.filter((building) => managedIds.size === 0 || managedIds.has(Number(building.id)))
  }, [buildingSources, isSuperAdmin, session?.admin.managed_buildings])

  const tenantBuildingOptions = useMemo(
    () => buildBuildingOptions(scopedBuildings, isSuperAdmin ? 'Tất cả tòa nhà' : undefined),
    [isSuperAdmin, scopedBuildings],
  )

  const destinationBuildingOptions = useMemo(
    () => buildBuildingOptions(scopedBuildings, isSuperAdmin ? 'Tất cả tòa nhà' : undefined),
    [isSuperAdmin, scopedBuildings],
  )

  const hasTenantNextPageFallback = !tenantOptionsMeta && tenantOptions.length >= tenantPerPage
  const tenantTotalPages = Math.max(1, tenantOptionsMeta?.last_page ?? (hasTenantNextPageFallback ? tenantCurrentPage + 1 : tenantCurrentPage))
  const safeTenantCurrentPage = Math.min(tenantCurrentPage, tenantTotalPages)
  const totalTenantOptions = tenantOptionsMeta?.total ?? (safeTenantCurrentPage - 1) * tenantPerPage + tenantOptions.length
  const tenantPaginationStart = tenantOptionsMeta?.from ?? (tenantOptions.length === 0 ? 0 : (safeTenantCurrentPage - 1) * tenantPerPage + 1)
  const tenantPaginationEnd = tenantOptionsMeta?.to ?? (tenantOptions.length === 0 ? 0 : (safeTenantCurrentPage - 1) * tenantPerPage + tenantOptions.length)
  const visibleTenantPages = useMemo(() => {
    const pages = new Set<number>([1, tenantTotalPages, safeTenantCurrentPage - 1, safeTenantCurrentPage, safeTenantCurrentPage + 1])
    return Array.from(pages)
      .filter((page) => page >= 1 && page <= tenantTotalPages)
      .sort((a, b) => a - b)
  }, [safeTenantCurrentPage, tenantTotalPages])

  function changeTenantPickerKeyword(value: string) {
    setTenantKeyword(value)
    setTenantCurrentPage(1)
  }

  function changeTenantPickerBuilding(value: string | number) {
    setTenantBuildingFilter(String(value))
    setTenantCurrentPage(1)
  }

  function changeTenantPickerPage(page: number) {
    setTenantCurrentPage(Math.min(Math.max(1, page), tenantTotalPages))
  }

  function changeTenantPickerPerPage(value: string | number) {
    setTenantPerPage(Number(value))
    setTenantCurrentPage(1)
  }

  function selectTenantForTransfer(nextTenant: AdminTenantResource) {
    navigate(`/admin/transfer-room?tenantId=${nextTenant.id}`)
  }

  function changeTenant() {
    navigate('/admin/transfer-room')
  }

  function pickRoom(room: AdminRoomResource) {
    setSelectedRoom(room)
    setNewDepositAmount(String(room.base_price ?? ''))
    setStep(2)
    setSubmitError(null)
    setFieldErrors({})
    setTransferResult(null)
  }

  function backToStep1() {
    setStep(1)
  }

  function updateMeterReadingRow(index: number, key: keyof MeterReadingRow, value: string) {
    setMeterReadingRows((rows) => rows.map((row, i) => (i === index ? { ...row, [key]: value } : row)))
  }

  function toggleVehicle(vehicleId: number, checked: boolean) {
    setCarryVehicleIds((current) => (checked ? [...current, vehicleId] : current.filter((id) => id !== vehicleId)))
  }

  function addDeductionRow() {
    setDeductionRows((rows) => [...rows, { id: crypto.randomUUID(), name: '', amount: '', note: '' }])
  }

  function updateDeductionRow(rowId: string, key: keyof Omit<DeductionRow, 'id'>, value: string) {
    setDeductionRows((rows) => rows.map((row) => (row.id === rowId ? { ...row, [key]: value } : row)))
  }

  function removeDeductionRow(rowId: string) {
    setDeductionRows((rows) => rows.filter((row) => row.id !== rowId))
  }

  const oldDepositBalance = moneyNumber(tenant?.current_contract?.deposit_balance)
  const deductionTotal = deductionRows.reduce((total, row) => total + moneyNumber(row.amount), 0)
  const requestedRefundAmount = moneyNumber(depositRefundAmount)
  const cappedDeductionAmount = Math.min(deductionTotal, oldDepositBalance)
  const remainingAfterDeduction = Math.max(oldDepositBalance - cappedDeductionAmount, 0)
  const cappedRefundAmount = Math.min(requestedRefundAmount, remainingAfterDeduction)
  const estimatedTransferAmount = Math.max(remainingAfterDeduction - cappedRefundAmount, 0)
  const extraChargeAmount = Math.max(deductionTotal - oldDepositBalance, 0)
  const requiredNewDepositAmount = moneyNumber(newDepositAmount)
  const collectedAdditionalDeposit = moneyNumber(additionalDepositAmount)
  const estimatedNewDepositBalance = estimatedTransferAmount + collectedAdditionalDeposit
  const estimatedNewDepositDue = Math.max(requiredNewDepositAmount - estimatedNewDepositBalance, 0)
  const isDepositOverLimit = estimatedNewDepositBalance > requiredNewDepositAmount

  async function handleSubmit() {
    if (!selectedRoom || !hasSelectedTenant) return

    setSubmitError(null)
    setFieldErrors({})

    const deductionItems: TransferDeductionItemInput[] = deductionRows
      .map((row) => ({
        name: row.name.trim(),
        amount: moneyNumber(row.amount),
        note: row.note.trim() || undefined,
      }))
      .filter((item) => item.name !== '' && item.amount > 0)

    const meterReadings = meterReadingRows
      .filter((row) => row.meterDeviceId && row.currentReading)
      .map((row) => ({
        meter_device_id: Number(row.meterDeviceId),
        current_reading: Number(row.currentReading),
      }))

    const payload: TransferTenantPayload = {
      tenant_id: parsedTenantId,
      to_room_id: selectedRoom.id,
      movement_date: movementDate,
      note: note || undefined,
      meter_readings: meterReadings.length ? meterReadings : undefined,
      deposit_refund_amount: depositRefundAmount ? Number(depositRefundAmount) : undefined,
      refund_payment_method: Number(refundPaymentMethod),
      new_deposit_amount: newDepositAmount ? Number(newDepositAmount) : undefined,
      additional_deposit_amount: additionalDepositAmount ? Number(additionalDepositAmount) : undefined,
      additional_deposit_payment_method: Number(additionalDepositPaymentMethod),
      deduction_items: deductionItems.length ? deductionItems : undefined,
      transfer_fee: transferFee ? Number(transferFee) : undefined,
      carry_vehicle_ids: carryVehicleIds.length ? carryVehicleIds : undefined,
    }

    try {
      setIsSubmitting(true)
      const response = await transferTenantRoom(payload)
      setTransferResult(response.result ?? null)
    } catch (error) {
      if (error instanceof ApiError) {
        const errors = error.validationErrors
        if (errors) setFieldErrors(errors)
        setSubmitError(error.message || 'Chuyển phòng thất bại.')
      } else {
        setSubmitError('Chuyển phòng thất bại.')
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
        onKeywordChange={changeTenantPickerKeyword}
        onBuildingChange={changeTenantPickerBuilding}
        onPageChange={changeTenantPickerPage}
        onPerPageChange={changeTenantPickerPerPage}
        onSelectTenant={selectTenantForTransfer}
      />
    )
  }

  if (isTenantLoading) {
    return (
      <div className="flex h-64 items-center justify-center text-[#8b5e34]">
        <Loader2 className="h-6 w-6 animate-spin" />
      </div>
    )
  }

  if (loadError || !tenant) {
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
        result={transferResult}
        onNewTransfer={changeTenant}
        onViewMovement={() => navigate(`/admin/room-movements?tenant_id=${parsedTenantId}`)}
        onViewInvoice={(invoiceId) => navigate(`/admin/invoices?id=${invoiceId}`)}
      />
    )
  }

  return (
    <section className="space-y-5 text-[#24170d] sm:space-y-6">
      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-4 text-[#fff4df] sm:p-5 lg:p-6">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_14%,rgba(243,197,107,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(15,118,110,0.26),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
          <div className="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <Link to="/admin/room-movements" className="mb-2 inline-flex items-center gap-2 text-xs font-black text-[#f3c56b] transition hover:text-[#ffd56f]">
                <ArrowLeft className="h-3.5 w-3.5" /> Về lịch sử phòng & cọc
              </Link>
              <h1 className="mt-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl">Chuyển phòng</h1>
              <p className="mt-2 text-sm font-bold text-[#f8e8c8]/80">
                {tenant.full_name || tenant.username} · đang ở {currentRoomNumber ? `phòng ${currentRoomNumber}` : 'chưa rõ phòng'}
                {currentBuildingName ? ` · ${currentBuildingName}` : ''}
              </p>
            </div>
            <button type="button" onClick={changeTenant} className="inline-flex h-11 items-center justify-center rounded-2xl border border-[#f3c56b]/25 bg-[#fff4df]/10 px-4 text-xs font-black uppercase tracking-[0.16em] text-[#f3c56b] transition hover:bg-[#f3c56b]/15 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#f3c56b]/20">
              Đổi khách thuê
            </button>
          </div>
        </div>
      </section>

      <div className="flex items-center gap-3 text-xs font-black text-[#8b5e34]">
        <StepBadge index={1} label="Chọn phòng đích" active={step === 1} done={step === 2} />
        <ChevronRight className="h-4 w-4" />
        <StepBadge index={2} label="Thông tin chuyển phòng" active={step === 2} done={false} />
      </div>

      {step === 1 && (
        <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-4 shadow-xl shadow-[#6b3f1d]/8 sm:p-5">
          <div className="grid gap-3 sm:grid-cols-[minmax(16rem,1fr)_minmax(10rem,14rem)]">
            <div className="relative">
              <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
              <input
                type="text"
                value={roomKeyword}
                onChange={(event) => setRoomKeyword(event.target.value)}
                placeholder="Tìm theo số phòng..."
                className={`${inputClass} pl-11`}
              />
            </div>
            <AdminSelect
              value={selectedBuildingId}
              options={destinationBuildingOptions}
              onChange={(value) => setSelectedBuildingId(String(value))}
              disabled={!isSuperAdmin && destinationBuildingOptions.length <= 1}
            />
          </div>

          <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {isRoomsLoading &&
              Array.from({ length: 6 }).map((_, index) => (
                <div key={index} className="h-28 animate-pulse rounded-2xl bg-stone-100" />
              ))}

            {!isRoomsLoading && availableRooms.length === 0 && (
              <div className="col-span-full rounded-2xl border border-dashed border-[#3d2a18]/12 bg-white/55 px-6 py-10 text-center text-sm font-bold text-[#6f6254]">
                Không có phòng trống hoàn toàn phù hợp để tạo hợp đồng mới chờ ký.
              </div>
            )}

            {!isRoomsLoading &&
              availableRooms.map((room) => {
                return (
                  <button
                    key={room.id}
                    type="button"
                    onClick={() => pickRoom(room)}
                    className="flex flex-col items-start gap-2 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-[#0f766e]/30 hover:shadow-lg"
                  >
                    <div className="flex w-full items-center justify-between">
                      <span className="inline-flex items-center gap-1.5 text-sm font-black text-[#24170d]">
                        <DoorOpen className="h-4 w-4 text-[#a65f16]" /> Phòng {room.room_number}
                      </span>
                      <span className="inline-flex items-center gap-1 rounded-full border border-[#3d2a18]/10 bg-[#efe2cf]/65 px-2.5 py-1 text-[10px] font-black text-[#6f6254]">
                        <Users className="h-3 w-3" /> {room.current_occupants}/{room.max_occupants}
                      </span>
                    </div>
                    <span className="inline-flex items-center gap-1.5 text-xs font-bold text-[#0f5f59]">
                      <Building2 className="h-3.5 w-3.5" /> {room.building?.name || room.building_name}
                    </span>
                  </button>
                )
              })}
          </div>
        </section>
      )}

      {step === 2 && selectedRoom && (
        <section className="space-y-4">
          <div className="flex items-center justify-between rounded-2xl border border-[#0f766e]/20 bg-[#0f766e]/8 px-4 py-3">
            <p className="text-sm font-black text-[#0f5f59]">
              Phòng đích: {selectedRoom.room_number} · {selectedRoom.building?.name || selectedRoom.building_name}
            </p>
            <button type="button" onClick={backToStep1} className="text-xs font-black text-[#0f5f59] underline underline-offset-2">
              Đổi phòng khác
            </button>
          </div>

          {submitError && (
            <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-black text-rose-700">{submitError}</div>
          )}

          <FormSection title="Thông tin chung">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <Field label="Ngày chuyển" error={fieldErrors.movement_date}>
                <input type="date" value={movementDate} onChange={(e) => setMovementDate(e.target.value)} className={inputClass} max={todayDateString()} />
              </Field>
              <Field label="Ghi chú" error={fieldErrors.note}>
                <input type="text" value={note} onChange={(e) => setNote(e.target.value)} placeholder="Lý do chuyển phòng..." className={inputClass} />
              </Field>
            </div>
          </FormSection>

          <FormSection title="Quyết toán cọc" hint="Cọc cũ, khoản khấu trừ, tiền hoàn và cọc chuyển sang hợp đồng mới đều được ghi vào ledger. Hợp đồng mới sẽ ở trạng thái chờ ký.">
            <div className="grid grid-cols-1 gap-3 lg:grid-cols-4">
              <LedgerTile label="Số dư cọc cũ" value={formatCurrency(oldDepositBalance)} tone="neutral" />
              <LedgerTile label="Khấu trừ vật dụng" value={formatCurrency(cappedDeductionAmount)} tone="danger" />
              <LedgerTile label="Hoàn cọc" value={formatCurrency(cappedRefundAmount)} tone="neutral" />
              <LedgerTile label="Chuyển sang hợp đồng mới" value={formatCurrency(estimatedTransferAmount)} tone="success" />
            </div>

            <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <Field label="Tiền cọc yêu cầu phòng mới" error={fieldErrors.new_deposit_amount}>
                <input type="number" min={0} value={newDepositAmount} onChange={(e) => setNewDepositAmount(e.target.value)} placeholder="0" className={inputClass} />
              </Field>
              <Field label="Tiền thu thêm cho cọc mới" error={fieldErrors.additional_deposit_amount}>
                <input type="number" min={0} value={additionalDepositAmount} onChange={(e) => setAdditionalDepositAmount(e.target.value)} placeholder="0" className={inputClass} />
              </Field>
              <Field label="Phương thức thu thêm" error={fieldErrors.additional_deposit_payment_method}>
                <AdminSelect value={additionalDepositPaymentMethod} options={paymentMethodOptions} onChange={(value) => setAdditionalDepositPaymentMethod(String(value))} />
              </Field>
              <Field label="Hoàn cọc" error={fieldErrors.deposit_refund_amount}>
                <input type="number" min={0} value={depositRefundAmount} onChange={(e) => setDepositRefundAmount(e.target.value)} placeholder="0" className={inputClass} />
              </Field>
              <Field label="Phương thức hoàn cọc" error={fieldErrors.refund_payment_method}>
                <AdminSelect value={refundPaymentMethod} options={paymentMethodOptions} onChange={(value) => setRefundPaymentMethod(String(value))} />
              </Field>
              <Field label="Phí chuyển phòng" error={fieldErrors.transfer_fee}>
                <input type="number" min={0} value={transferFee} onChange={(e) => setTransferFee(e.target.value)} placeholder="0" className={inputClass} />
              </Field>
            </div>

            <div className="mt-4 grid grid-cols-1 gap-3 rounded-2xl border border-dashed border-[#3d2a18]/12 bg-white/55 p-4 lg:grid-cols-3">
              <LedgerTile label="Cọc yêu cầu" value={formatCurrency(requiredNewDepositAmount)} tone="neutral" compact />
              <LedgerTile label="Cọc đã chuyển + thu thêm" value={formatCurrency(estimatedNewDepositBalance)} tone="success" compact />
              <LedgerTile label="Còn thiếu" value={formatCurrency(estimatedNewDepositDue)} tone="warning" compact />
            </div>

            {isDepositOverLimit && (
              <div className="mt-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-black text-rose-700">
                Cọc chuyển sang và cọc thu thêm đang vượt cọc yêu cầu của phòng mới. Vui lòng giảm cọc thu thêm hoặc hoàn thêm tiền.
              </div>
            )}
          </FormSection>

          <FormSection title="Khấu trừ vật dụng / hư hỏng" hint="Mỗi dòng là một khoản khấu trừ riêng để ledger rõ ràng khi thanh lý cọc.">
            <div className="space-y-3">
              {deductionRows.length === 0 ? (
                <p className="rounded-2xl border border-dashed border-[#3d2a18]/12 bg-white/55 px-4 py-3 text-xs font-bold text-[#6f6254]">
                  Chưa có khoản khấu trừ nào.
                </p>
              ) : (
                deductionRows.map((row) => (
                  <div key={row.id} className="grid gap-2 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3 lg:grid-cols-[1.2fr_0.7fr_1fr_auto]">
                    <input
                      type="text"
                      value={row.name}
                      onChange={(event) => updateDeductionRow(row.id, 'name', event.target.value)}
                      placeholder="Tên vật dụng / khoản khấu trừ"
                      className={inputClass}
                    />
                    <input
                      type="number"
                      min={0}
                      value={row.amount}
                      onChange={(event) => updateDeductionRow(row.id, 'amount', event.target.value)}
                      placeholder="0"
                      className={inputClass}
                    />
                    <input
                      type="text"
                      value={row.note}
                      onChange={(event) => updateDeductionRow(row.id, 'note', event.target.value)}
                      placeholder="Ghi chú"
                      className={inputClass}
                    />
                    <button
                      type="button"
                      onClick={() => removeDeductionRow(row.id)}
                      className="inline-flex h-12 items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 px-4 text-sm font-black text-rose-700 transition hover:bg-rose-100"
                    >
                      <Trash2 className="mr-2 h-4 w-4" /> Xóa
                    </button>
                  </div>
                ))
              )}
            </div>
            <div className="mt-4 flex items-center justify-between gap-3">
              <p className="text-xs font-bold text-[#8b5e34]/70">
                Tổng khấu trừ hiện tại: {formatCurrency(deductionTotal)}
                {extraChargeAmount > 0 ? ` · Phần vượt cọc cũ: ${formatCurrency(extraChargeAmount)}` : ''}
              </p>
              <button
                type="button"
                onClick={addDeductionRow}
                className="inline-flex h-11 items-center justify-center gap-2 rounded-2xl border border-[#3d2a18]/12 bg-[#fffaf1] px-4 text-sm font-black text-[#3d2a18] transition hover:bg-[#f3c56b]/10"
              >
                <Plus className="h-4 w-4" /> Thêm khoản khấu trừ
              </button>
            </div>
          </FormSection>

          <FormSection
            title="Chỉ số điện/nước chốt sổ phòng cũ"
            hint="Admin tự đọc chỉ số trên đồng hồ rồi nhập chỉ số tương ứng. Để trống nếu không cần chốt sổ điện/nước cho lần chuyển này."
          >
            {meterReadingRows.length === 0 ? (
              <p className="rounded-2xl border border-dashed border-[#3d2a18]/12 bg-white/55 px-4 py-3 text-xs font-bold text-[#6f6254]">Phòng hiện tại chưa có đồng hồ đang hoạt động.</p>
            ) : (
              meterReadingRows.map((row, index) => {
                const meter = meterDevices.find((m) => String(m.id) === row.meterDeviceId)
                const label = meter ? `${meter.service_name} (${meter.meter_code ?? ''})` : ''
                return (
                  <div key={row.meterDeviceId} className="mb-2 grid grid-cols-1 gap-2 sm:grid-cols-[1fr_1fr]">
                    <input className={inputClass} value={label} disabled />
                    <input type="number" placeholder="Chỉ số hiện tại" value={row.currentReading} onChange={(e) => updateMeterReadingRow(index, 'currentReading', e.target.value)} className={inputClass} />
                  </div>
                )
              })
            )}
          </FormSection>

          <FormSection title="Phương tiện mang theo" hint="Chọn các phương tiện sẽ được chuyển sang phòng mới.">
            {vehicles.length === 0 ? (
              <p className="rounded-2xl border border-dashed border-[#3d2a18]/12 bg-white/55 px-4 py-3 text-xs font-bold text-[#6f6254]">Khách thuê này chưa có phương tiện đang hoạt động.</p>
            ) : (
              <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                {vehicles.map((vehicle) => (
                  <label key={vehicle.id} className="flex items-center gap-3 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-black text-[#3d2a18]">
                    <input
                      type="checkbox"
                      checked={carryVehicleIds.includes(vehicle.id)}
                      onChange={(event) => toggleVehicle(vehicle.id, event.target.checked)}
                      className="h-4 w-4 rounded border-[#8b5e34]/30 text-[#0f5f59] focus:ring-[#0f766e]/20"
                    />
                    <span>{vehicle.license_plate} ({vehicle.vehicle_type_label})</span>
                  </label>
                ))}
              </div>
            )}
          </FormSection>

          <div className="flex items-center justify-end gap-3 pb-2">
            <button type="button" onClick={backToStep1} className="inline-flex h-11 items-center justify-center rounded-2xl border border-[#3d2a18]/15 bg-[#fffaf1] px-5 text-sm font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/10">
              Quay lại
            </button>
            <button
              type="button"
              disabled={isSubmitting || isDepositOverLimit}
              onClick={() => void handleSubmit()}
              className="inline-flex h-11 items-center justify-center gap-2 rounded-2xl bg-[#24170d] px-6 text-sm font-black text-[#fff4df] shadow-lg transition hover:bg-[#3d2a18] disabled:cursor-not-allowed disabled:opacity-60"
            >
              {isSubmitting && <Loader2 className="h-4 w-4 animate-spin" />}
              Xác nhận chuyển phòng
            </button>
          </div>
        </section>
      )}
    </section>
  )
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
  onBuildingChange: (value: string | number) => void
  onPageChange: (page: number) => void
  onPerPageChange: (value: string | number) => void
  onSelectTenant: (tenant: AdminTenantResource) => void
}

function TenantPicker({ keyword, buildingFilter, buildingOptions, isBuildingFilterDisabled, tenants, isLoading, errorMessage, currentPage, totalPages, totalTenants, paginationStart, paginationEnd, visiblePages, perPage, onKeywordChange, onBuildingChange, onPageChange, onPerPageChange, onSelectTenant }: TenantPickerProps) {
  return (
    <section className="space-y-5 text-[#24170d] sm:space-y-6">
      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-4 text-[#fff4df] sm:p-5 lg:p-6">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_16%_16%,rgba(243,197,107,0.3),transparent_32%),radial-gradient(circle_at_85%_4%,rgba(15,118,110,0.28),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
          <div className="relative max-w-3xl">
            <span className="inline-flex items-center gap-2 rounded-full border border-[#f3c56b]/25 bg-[#fff4df]/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.2em] text-[#f3c56b]">
              <ArrowRightLeft className="h-3.5 w-3.5" /> Chuyển phòng
            </span>
            <p className="mt-2 text-sm font-bold leading-6 text-[#f8e8c8]/80">
              Chọn khách thuê đang ở phòng hiện tại, sau đó chọn phòng đích và chốt thông tin chuyển phòng.
            </p>
          </div>
        </div>
      </section>

      <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-4 shadow-xl shadow-[#6b3f1d]/8 sm:p-5">
        <div className="grid gap-3 lg:grid-cols-[minmax(16rem,1fr)_minmax(12rem,18rem)]">
          <div className="relative">
            <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
            <input
              type="text"
              value={keyword}
              onChange={(event) => onKeywordChange(event.target.value)}
              placeholder="Tìm khách thuê theo tên, username, SĐT..."
              className={`${inputClass} pl-11`}
            />
          </div>
          <AdminSelect
            value={buildingFilter}
            options={buildingOptions}
            onChange={onBuildingChange}
            disabled={isBuildingFilterDisabled}
            placeholder="Lọc theo tòa nhà"
          />
        </div>

        {errorMessage && <div className="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-black text-rose-700">{errorMessage}</div>}

        <div className="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
          {isLoading &&
            Array.from({ length: 4 }).map((_, index) => (
              <div key={index} className="h-28 animate-pulse rounded-2xl bg-stone-100" />
            ))}

          {!isLoading && tenants.length === 0 && (
            <div className="col-span-full rounded-2xl border border-dashed border-[#3d2a18]/12 bg-white/55 px-6 py-10 text-center text-sm font-bold text-[#6f6254]">
              Không tìm thấy khách thuê đang ở phù hợp.
            </div>
          )}

          {!isLoading && tenants.map((tenant) => (
            <button
              key={tenant.id}
              type="button"
              onClick={() => onSelectTenant(tenant)}
              className="group flex items-start gap-3 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-[#0f766e]/30 hover:bg-[#fff8eb] hover:shadow-lg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#0f766e]/12"
            >
              <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-white/70 text-[#8b5e34] transition group-hover:text-[#0f5f59]">
                <UserRound className="h-5 w-5" />
              </span>
              <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-black text-[#24170d]">{tenant.full_name || tenant.username}</span>
                <span className="mt-1 block truncate text-xs font-bold text-[#8b5e34]/75">{tenant.phone || tenant.email || tenant.username}</span>
                <span className="mt-3 inline-flex items-center gap-1.5 rounded-full border border-[#0f766e]/15 bg-[#0f766e]/8 px-3 py-1 text-[11px] font-black text-[#0f5f59]">
                  <DoorOpen className="h-3.5 w-3.5" /> {getTenantRoomText(tenant)}
                </span>
              </span>
            </button>
          ))}
        </div>

        <div className="mt-5 flex flex-col gap-3 border-t border-[#3d2a18]/10 pt-4 lg:flex-row lg:items-center lg:justify-between">
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

function StepBadge({ index, label, active, done }: { index: number; label: string; active: boolean; done: boolean }) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-2 rounded-full border px-3 py-1.5',
        active && 'border-[#24170d] bg-[#24170d] text-[#fff4df]',
        done && !active && 'border-[#0f766e]/30 bg-[#0f766e]/10 text-[#0f5f59]',
        !active && !done && 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34]',
      )}
    >
      <span className="flex h-5 w-5 items-center justify-center rounded-full bg-white/20 text-[10px]">{index}</span>
      {label}
    </span>
  )
}

function FormSection({ title, hint, children }: { title: string; hint?: string; children: React.ReactNode }) {
  return (
    <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
      <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">{title}</p>
      {hint && <p className="mb-3 mt-1 text-xs font-semibold text-[#8b5e34]/70">{hint}</p>}
      <div className={hint ? '' : 'mt-3'}>{children}</div>
    </section>
  )
}

function Field({ label, error, children }: { label: string; error?: string[]; children: React.ReactNode }) {
  return (
    <label className="block text-xs font-black text-[#8b5e34]/70">
      {label}
      <div className="mt-1.5">{children}</div>
      {error?.length ? <p className="mt-1 text-[11px] font-bold text-rose-600">{error[0]}</p> : null}
    </label>
  )
}

function LedgerTile({ label, value, tone = 'neutral', compact = false }: { label: string; value: string; tone?: 'neutral' | 'success' | 'danger' | 'warning'; compact?: boolean }) {
  const toneClasses = {
    neutral: 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#24170d]',
    success: 'border-[#0f766e]/20 bg-[#0f766e]/8 text-[#0f5f59]',
    danger: 'border-rose-200 bg-rose-50 text-rose-700',
    warning: 'border-[#f3c56b]/35 bg-[#f3c56b]/12 text-[#8a4f18]',
  }

  return (
    <div className={cn('rounded-2xl border px-4 py-3', toneClasses[tone], compact && 'py-2.5')}>
      <p className="text-[10px] font-black uppercase tracking-[0.18em] opacity-70">{label}</p>
      <p className={cn('mt-1 font-black tabular-nums', compact ? 'text-base' : 'text-xl')}>{value}</p>
    </div>
  )
}

function TransferResultPanel({ tenant, result, onNewTransfer, onViewMovement, onViewInvoice }: { tenant: AdminTenantResource; result: TransferRoomResultResource; onNewTransfer: () => void; onViewMovement: () => void; onViewInvoice: (invoiceId: number) => void }) {
  const oldInvoice = result.old_invoice
  const newContract = result.new_contract
  const deposit = result.deposit

  return (
    <section className="space-y-5 text-[#24170d] sm:space-y-6">
      <section className="overflow-hidden rounded-[2rem] border border-[#0f766e]/20 bg-[#0f3f3b] shadow-2xl shadow-[#0f766e]/18">
        <div className="relative p-5 text-[#f0fdfa] lg:p-6">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_12%_16%,rgba(243,197,107,0.28),transparent_32%),radial-gradient(circle_at_86%_10%,rgba(45,212,191,0.24),transparent_34%),linear-gradient(135deg,#0f3f3b_0%,#123f35_48%,#24170d_100%)]" />
          <div className="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <span className="inline-flex items-center gap-2 rounded-full border border-emerald-200/25 bg-white/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.2em] text-emerald-100">
                <CheckCircle2 className="h-3.5 w-3.5" /> Chuyển phòng thành công
              </span>
              <h1 className="mt-3 text-3xl font-black tracking-[-0.05em] text-white sm:text-4xl">Đã tạo hợp đồng mới chờ tenant ký</h1>
              <p className="mt-2 text-sm font-bold text-emerald-50/82">
                {tenant.full_name || tenant.username} đã được chuyển sang hợp đồng mới. Hóa đơn chốt phòng cũ đã phát realtime cho tenant nếu có phát sinh.
              </p>
            </div>
            <button type="button" onClick={onNewTransfer} className="inline-flex h-11 items-center justify-center rounded-2xl border border-white/20 bg-white/10 px-4 text-xs font-black uppercase tracking-[0.16em] text-white transition hover:bg-white/15">
              Chuyển tenant khác
            </button>
          </div>
        </div>
      </section>

      <div className="grid gap-4 lg:grid-cols-3">
        <ResultCard icon={<ReceiptText className="h-5 w-5" />} title="Hóa đơn chốt phòng cũ">
          {oldInvoice ? (
            <>
              <p className="text-lg font-black text-[#24170d]">{oldInvoice.invoice_code}</p>
              <p className="mt-1 text-sm font-bold text-[#8b5e34]">Tổng tiền: {formatCurrency(moneyNumber(oldInvoice.total_amount))}</p>
              <p className="text-xs font-semibold text-[#8b5e34]/70">Kỳ: {oldInvoice.period_start} → {oldInvoice.period_end}</p>
              {oldInvoice.id ? (
                <button type="button" onClick={() => onViewInvoice(Number(oldInvoice.id))} className="mt-4 inline-flex h-10 items-center justify-center rounded-2xl bg-[#24170d] px-4 text-xs font-black text-[#fff4df] transition hover:bg-[#3d2a18]">
                  Xem hóa đơn
                </button>
              ) : null}
            </>
          ) : (
            <p className="text-sm font-bold text-[#8b5e34]">Không phát sinh hóa đơn phòng cũ trong kỳ này.</p>
          )}
        </ResultCard>

        <ResultCard icon={<FileSignature className="h-5 w-5" />} title="Hợp đồng phòng mới">
          <p className="text-lg font-black text-[#24170d]">{newContract?.contract_code || 'Đang cập nhật'}</p>
          <p className="mt-1 text-sm font-bold text-[#0f5f59]">{newContract?.status_label || 'Chờ ký'}</p>
          <p className="text-xs font-semibold text-[#8b5e34]/70">Phòng {newContract?.room_number || 'chưa rõ'} · bắt đầu {newContract?.start_date || 'chưa rõ'}</p>
          <p className="mt-3 text-sm font-bold text-[#8b5e34]">Giá phòng mới: {formatCurrency(moneyNumber(newContract?.room_price))}</p>
          <p className="mt-2 text-xs font-semibold leading-5 text-[#8b5e34]/70">Sau khi tenant ký, hóa đơn tháng của phòng mới sẽ tính từ ngày bắt đầu đến cuối tháng theo luồng hóa đơn hiện có.</p>
        </ResultCard>

        <ResultCard icon={<WalletCards className="h-5 w-5" />} title="Cọc hợp đồng mới">
          <div className="space-y-2">
            <MiniMoneyRow label="Cọc yêu cầu" value={deposit?.new_required_amount} />
            <MiniMoneyRow label="Đã chuyển/thu" value={deposit?.new_balance} />
            <MiniMoneyRow label="Còn thiếu" value={deposit?.new_due_amount} highlight />
          </div>
        </ResultCard>
      </div>

      <div className="flex flex-wrap justify-end gap-3">
        <button type="button" onClick={onViewMovement} className="inline-flex h-11 items-center justify-center rounded-2xl border border-[#3d2a18]/12 bg-[#fffaf1] px-5 text-sm font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/10">
          Xem lịch sử phòng & cọc
        </button>
        <Link to="/admin/contracts" className="inline-flex h-11 items-center justify-center rounded-2xl bg-[#24170d] px-5 text-sm font-black text-[#fff4df] transition hover:bg-[#3d2a18]">
          Mở danh sách hợp đồng
        </Link>
      </div>
    </section>
  )
}

function ResultCard({ icon, title, children }: { icon: React.ReactNode; title: string; children: React.ReactNode }) {
  return (
    <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-4 shadow-xl shadow-[#6b3f1d]/8">
      <div className="mb-3 flex items-center gap-2 text-[#8b5e34]">
        <span className="flex h-10 w-10 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-white/70">{icon}</span>
        <p className="text-[10px] font-black uppercase tracking-[0.2em]">{title}</p>
      </div>
      {children}
    </section>
  )
}

function MiniMoneyRow({ label, value, highlight = false }: { label: string; value?: string | number | null; highlight?: boolean }) {
  return (
    <div className="flex items-center justify-between gap-3 text-sm">
      <span className="font-bold text-[#8b5e34]">{label}</span>
      <span className={cn('font-black tabular-nums text-[#24170d]', highlight && 'text-[#0f5f59]')}>{formatCurrency(moneyNumber(value))}</span>
    </div>
  )
}

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

function moneyNumber(value: string | number | null | undefined): number {
  const amount = Number(value ?? 0)
  return Number.isFinite(amount) ? Math.max(amount, 0) : 0
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(Math.round(value))
}

function getTenantRoomText(tenant: AdminTenantResource) {
  const roomNumber = tenant.current_room?.room_number ?? tenant.room_number
  const buildingName = tenant.current_room?.building_name ?? tenant.building_name

  if (!roomNumber) return 'Chưa rõ phòng hiện tại'
  return `Phòng ${roomNumber}${buildingName ? ` · ${buildingName}` : ''}`
}

function todayDateString() {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

/**
 * apiRequest() trong api-client.ts đã tự bóc lớp axios (return response.data) trước khi trả
 * ra ngoài, nên response ở đây CHÍNH LÀ ApiEnvelope<T> thật - field chứa dữ liệu là `result`
 * (xác nhận qua getValidationErrors() trong api-client.ts, đọc payload?.result). Field `data`
 * không tồn tại trong envelope thật - đây từng là suy đoán sai của mình, đã bỏ fallback đó.
 */
function unwrap<T>(response: { result: T }): T {
  return response.result
}

function getVisibleErrorMessage(error: unknown, fallback: string) {
  if (error instanceof ApiError) return error.message || fallback
  if (error instanceof Error) return error.message
  return fallback
}
