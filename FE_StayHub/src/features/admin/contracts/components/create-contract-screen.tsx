import { useCallback, useEffect, useMemo, useState, useRef } from 'react'
import { useNavigate, useParams, useLocation } from 'react-router-dom'
import { ArrowLeft, BadgeCheck, Car, FileText, RefreshCw, UserPlus, X } from 'lucide-react'
import { AdminDateInput } from '../../../../shared/components/AdminDateInput'
import { cn } from '../../../../shared/lib/utils/cn'
import { canManageContractsRole, isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { buildingAllowsTenantGender } from '../../shared/config/gender-policy'
import { fetchAdminTenants } from '../../tenants/services/tenants.service'
import type { AdminTenantResource } from '../../tenants/types/tenant-api.model'
import {
  createAdminContract,
  createAdminContractDepositTransaction,
  fetchAdminContractDetail,
  fetchAvailableRooms,
  fetchAdminContractVehicles,
  updateAdminContract,
  renewContract,
} from '../services/contracts.service'
import type {
  AdminContractResource,
  AdminVehicleOptionResource,
  ContractFormErrors,
  ContractFormValues,
  ContractTenantFormRow,
  ContractVehicleFormRow,
} from '../types/contract-api.model'
import { validateContractForm } from '../validations/contract.validation'
import { formatMoneyInput, parseMoneyInput } from '../../../../shared/lib/utils/format'

import {
  STATUS_PENDING_SIGN,
  CHARGE_MONTHLY,
  defaultTenantRow,
  defaultForm,
  createStatusOptions,
  getResourceList,
  getVisibleErrorMessage,
  toDate,
  contractToForm,
  buildPayload,
} from '../utils/contract.helpers'
import { FieldError } from './ui/ui-elements'
import { inputClass, inputErrorClass, Field, FormSection, TenantRow, VehicleRow } from './form/form-elements'
import { DepositQRModal } from './modals/DepositQRModal'
import { CreateVehicleModal } from './modals/CreateVehicleModal'

export function CreateContractScreen() {
  const navigate = useNavigate()
  const { contractId } = useParams()
  const location = useLocation()
  const { session } = useAdminSession()
  const adminRole = session?.admin?.role

  const isRenewMode = useMemo(() => location.pathname.endsWith('/renew'), [location.pathname])
  const isEditMode = useMemo(() => Boolean(contractId) && !isRenewMode, [contractId, isRenewMode])

  const canManageContracts = useMemo(() => canManageContractsRole(adminRole), [adminRole])
  const managedBuildingId = session?.admin?.managed_buildings?.[0]?.id

  const [form, setForm] = useState<ContractFormValues>(() => ({ ...defaultForm }))
  const [errors, setErrors] = useState<ContractFormErrors>({})
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [rooms, setRooms] = useState<ContractRoomOption[]>([])
  const [tenants, setTenants] = useState<AdminTenantResource[]>([])
  const [vehicles, setVehicles] = useState<AdminVehicleOptionResource[]>([])
  const [currentContractTenants, setCurrentContractTenants] = useState<AdminTenantResource[]>([])
  const [currentContractVehicles, setCurrentContractVehicles] = useState<AdminVehicleOptionResource[]>([])
  const [editingContract, setEditingContract] = useState<AdminContractResource | null>(null)
  const [isLoading, setIsLoading] = useState(Boolean(contractId))
  const [isSaving, setIsSaving] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [qrModalContract, setQrModalContract] = useState<AdminContractResource | null>(null)
  const [isConfirmingDeposit, setIsConfirmingDeposit] = useState(false)
  const [isCreateVehicleOpen, setIsCreateVehicleOpen] = useState(false)

  const deletedVehicleIdsRef = useRef<number[]>([])

  const buildingOptions = useMemo(() => buildings.map((building) => ({ value: building.id, label: building.name, tone: 'default' as const })), [buildings])
  const selectedBuilding = useMemo(() => buildings.find((building) => String(building.id) === form.building_id) || null, [buildings, form.building_id])
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

  const tenantOptions = useMemo(() => {
    if (!form.building_id || !form.room_id) {
      return []
    }
    const merged = tenants.filter((tenant) => buildingAllowsTenantGender(selectedBuilding?.gender_policy, tenant.gender))
    const existingIds = new Set(merged.map((t) => t.id))
    for (const t of currentContractTenants) {
      if (!existingIds.has(t.id)) {
        merged.push(t)
      }
    }
    return merged.map((tenant) => ({
      value: tenant.id,
      label: tenant.full_name || tenant.username,
      description: tenant.phone || tenant.email || tenant.identity_number || undefined,
      tone: 'default' as const,
    }))
  }, [tenants, currentContractTenants, form.building_id, form.room_id, selectedBuilding?.gender_policy])

  const vehicleOptions = useMemo(() => {
    const merged = [...vehicles]
    const existingIds = new Set(merged.map((v) => v.id))
    for (const v of currentContractVehicles) {
      if (!existingIds.has(v.id)) {
        merged.push(v)
      }
    }
    return merged.map((vehicle) => ({
      value: vehicle.id,
      label: `${vehicle.license_plate || vehicle.vehicle_type_label || 'Phương tiện'}`,
      description: vehicle.tenant_name || undefined,
      tone: vehicle.is_active ? ('success' as const) : ('warning' as const),
    }))
  }, [vehicles, currentContractVehicles])

  const loadBuildings = useCallback(async () => {
    if (!canManageContracts) return

    try {
      const response = await fetchAdminBuildings({ per_page: 100 })
      const list = getResourceList(response.result)
      setBuildings(list)

      if (!isSuperAdminRole(adminRole) && !form.building_id) {
        const buildingId = list[0]?.id || managedBuildingId
        if (buildingId) setForm((current) => ({ ...current, building_id: String(buildingId) }))
      }
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải danh sách tòa nhà.'))
    }
  }, [canManageContracts, form.building_id, adminRole, managedBuildingId])

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
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải danh sách phòng.'))
    }
  }, [])

  const loadTenants = useCallback(async (buildingId: string) => {
    if (!buildingId) {
      setTenants([])
      return
    }
    try {
      const response = await fetchAdminTenants({ building_id: Number(buildingId), status: 1, without_active_contract: true, per_page: 100 })
      setTenants(getResourceList(response.result))
    } catch (error) {
      setTenants([])
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải danh sách khách thuê.'))
    }
  }, [])



  const loadContract = useCallback(async () => {
    if (!contractId) return

    try {
      setIsLoading(true)
      deletedVehicleIdsRef.current = []
      const response = await fetchAdminContractDetail(Number(contractId))
      const contract = response.result
      if (!contract) return
      setEditingContract(contract)

      if (isRenewMode) {
        let nextStartDate = ''
        let nextEndDate = ''
        if (contract.end_date) {
          const endDateObj = new Date(contract.end_date)
          endDateObj.setDate(endDateObj.getDate() + 1)
          nextStartDate = endDateObj.toISOString().split('T')[0]
          const endDateCalc = new Date(nextStartDate)
          endDateCalc.setFullYear(endDateCalc.getFullYear() + 1)
          endDateCalc.setDate(endDateCalc.getDate() - 1)
          nextEndDate = endDateCalc.toISOString().split('T')[0]
        }
        const formValues = contractToForm(contract, isRenewMode)
        setForm({
          ...formValues,
          contract_code: '',
          start_date: nextStartDate,
          end_date: nextEndDate,
          actual_end_date: '',
          parent_contract_id: String(contract.parent_contract_id || contract.id),
          renew_from_contract_id: String(contract.id),
          status: STATUS_PENDING_SIGN,
          vehicles: formValues.vehicles.map((v) => ({
            ...v,
            started_at: nextStartDate,
            ended_at: '',
            billing_start_date: nextStartDate,
            billing_end_date: '',
          })),
          tenants: formValues.tenants.map((t) => ({
            ...t,
            join_date: nextStartDate,
            billing_start_date: nextStartDate,
            leave_date: '',
            billing_end_date: '',
            is_staying: true,
          })),
          deposit_transactions: [],
        })
      } else {
        setForm(contractToForm(contract, isRenewMode))
      }

      const currentTenants = (contract.contract_tenants || []).map((ct) => ct.tenant).filter(Boolean) as AdminTenantResource[]
      setCurrentContractTenants(currentTenants)
      const contractVehicles = contract.contract_vehicles || []
      const filteredVehicles = isRenewMode
        ? contractVehicles.filter((cv) => !cv.ended_at || cv.ended_at === contract.end_date || cv.ended_at === contract.actual_end_date)
        : contractVehicles.filter((cv) => cv.is_active !== false)
      const currentVehicles = filteredVehicles
        .map((cv) => cv.vehicle)
        .filter(Boolean) as AdminVehicleOptionResource[]
      setCurrentContractVehicles(currentVehicles)
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải chi tiết hợp đồng.'))
    } finally {
      setIsLoading(false)
    }
  }, [contractId, isRenewMode])
  // XOÁ: loadVehiclesForTenants và autoPopulateVehicles riêng lẻ
  // THÊM: hàm gộp này, chỉ deps [currentContractVehicles] (stable, chỉ set 1 lần khi load contract)

  const loadAndAutoPopulateVehicles = useCallback(
    async (tenantIds: number[], startDate: string) => {
      if (tenantIds.length === 0) {
        setVehicles([])
        return
      }

      try {
        const responses = await Promise.all(
          tenantIds.map((tenantId) =>
            fetchAdminContractVehicles({
              tenant_id: tenantId,
              is_active: true,
              without_active_contract: true,
              per_page: 100,
            })
          )
        )
        const fetched = responses.flatMap((response) => getResourceList(response.result))
        const uniqueFetched = Array.from(new Map(fetched.map((v) => [v.id, v])).values())

        // Cập nhật danh sách xe cho vehicleOptions dropdown
        setVehicles(uniqueFetched)

        // Auto-populate form — dùng thẳng uniqueFetched, KHÔNG đọc từ `vehicles` state
        // Đây chính là điểm phá vỡ vòng lặp vô tận so với bản cũ
        setForm((current) => {
          const tenantIdSet = new Set(tenantIds)
          const allKnown = [...uniqueFetched, ...currentContractVehicles]

          // 1. Bỏ xe của tenant đã bị remove khỏi form
          const cleaned = current.vehicles.filter((v) => {
            const vid = Number(v.vehicle_id)
            if (!vid) return true
            const info = allKnown.find((av) => av.id === vid)
            if (!info) return true
            return tenantIdSet.has(info.tenant_id)
          })

          // 2. Append xe mới chưa có trong form
          const existingIds = new Set(cleaned.map((v) => Number(v.vehicle_id)).filter((id) => id > 0))
          const newRows: ContractVehicleFormRow[] = uniqueFetched
            .filter((v) => {
              if (existingIds.has(v.id)) return false
              if (deletedVehicleIdsRef.current.includes(v.id)) return false
              return true
            })
            .map((v) => ({
              vehicle_id: String(v.id),
              started_at: startDate || current.start_date,
              ended_at: '',
              billing_start_date: startDate || current.start_date,
              billing_end_date: '',
              monthly_fee: '0',
              charge_policy: CHARGE_MONTHLY,
              is_active: true,
            }))

          if (newRows.length === 0 && cleaned.length === current.vehicles.length) {
            return current // không có gì thay đổi, tránh re-render thừa
          }

          return { ...current, vehicles: [...cleaned, ...newRows] }
        })
      } catch (error) {
        setVehicles([])
        setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải danh sách phương tiện.'))
      }
    },
    [currentContractVehicles] // KHÔNG có `vehicles` trong deps → không tạo reference mới khi vehicles đổi
  )
  useEffect(() => {
    void loadBuildings()
  }, [loadBuildings])

  useEffect(() => {
    void loadContract()
  }, [loadContract])

  useEffect(() => {
    void loadRoomsForBuilding(form.building_id)
    void loadTenants(form.building_id)
  }, [form.building_id, loadRoomsForBuilding, loadTenants])

  useEffect(() => {
    const tenantIds = form.tenants
      .map((tenant) => Number(tenant.tenant_id))
      .filter((id) => id > 0)
    void loadAndAutoPopulateVehicles(tenantIds, form.start_date)
  }, [form.tenants, form.start_date, loadAndAutoPopulateVehicles])


  const updateForm = <K extends keyof ContractFormValues>(key: K, value: ContractFormValues[K]) => {
    setForm((current) => {
      const next = { ...current, [key]: value }
      if (key === 'building_id') {
        next.room_id = ''
        next.tenants = [{ ...defaultTenantRow, join_date: current.start_date, billing_start_date: current.start_date }]
        next.vehicles = []
      }
      if (key === 'room_id') {
        const roomIdStr = String(value)
        const selectedRoom = rooms.find((room) => String(room.id) === roomIdStr)
        if (selectedRoom && selectedRoom.base_price !== undefined && selectedRoom.base_price !== null) {
          const formattedPrice = formatMoneyInput(String(Math.round(Number(selectedRoom.base_price))))
          next.room_price = formattedPrice
          next.deposit_amount = formattedPrice
        } else {
          next.room_price = ''
          next.deposit_amount = '0'
        }
      }
      if (key === 'start_date' && typeof value === 'string') {
        next.vehicles = current.vehicles.map((v) => ({
          ...v,
          started_at: value,
          billing_start_date: value,
        }))
        next.tenants = current.tenants.map((t) => ({
          ...t,
          join_date: value,
          billing_start_date: value,
        }))
        if (value) {
          const startDateObj = new Date(value)
          if (!isNaN(startDateObj.getTime())) {
            startDateObj.setFullYear(startDateObj.getFullYear() + 1)
            startDateObj.setDate(startDateObj.getDate() - 1)
            next.end_date = startDateObj.toISOString().slice(0, 10)
          }
        }
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
      vehicles: current.vehicles.map((vehicle, vehicleIndex) => (vehicleIndex === index ? { ...vehicle, ...patch } : vehicle)),
    }))
    setErrors((current) => ({ ...current, vehicles: undefined, [`vehicles.${index}`]: undefined }))
  }

  const resetForm = () => {
    deletedVehicleIdsRef.current = []
    if (editingContract) {
      if (isRenewMode) {
        let nextStartDate = ''
        let nextEndDate = ''
        if (editingContract.end_date) {
          const endDateObj = new Date(editingContract.end_date)
          endDateObj.setDate(endDateObj.getDate() + 1)
          nextStartDate = endDateObj.toISOString().split('T')[0]
          const endDateCalc = new Date(nextStartDate)
          endDateCalc.setFullYear(endDateCalc.getFullYear() + 1)
          endDateCalc.setDate(endDateCalc.getDate() - 1)
          nextEndDate = endDateCalc.toISOString().split('T')[0]
        }
        const formValues = contractToForm(editingContract, isRenewMode)
        setForm({
          ...formValues,
          contract_code: '',
          start_date: nextStartDate,
          end_date: nextEndDate,
          actual_end_date: '',
          parent_contract_id: String(editingContract.parent_contract_id || editingContract.id),
          renew_from_contract_id: String(editingContract.id),
          status: STATUS_PENDING_SIGN,
          vehicles: formValues.vehicles.map((v) => ({
            ...v,
            started_at: nextStartDate,
            ended_at: '',
            billing_start_date: nextStartDate,
            billing_end_date: '',
          })),
          tenants: formValues.tenants.map((t) => ({
            ...t,
            join_date: nextStartDate,
            billing_start_date: nextStartDate,
            leave_date: '',
            billing_end_date: '',
            is_staying: true,
          })),
          deposit_transactions: [],
        })
      } else {
        setForm(contractToForm(editingContract, isRenewMode))
      }
      return
    }

    const today = new Date().toISOString().slice(0, 10)
    const oneYearLater = (() => {
      const date = new Date(today)
      date.setFullYear(date.getFullYear() + 1)
      date.setDate(date.getDate() - 1)
      return date.toISOString().slice(0, 10)
    })()
    setCurrentContractTenants([])
    setCurrentContractVehicles([])
    setForm({
      ...defaultForm,
      building_id: isSuperAdminRole(adminRole)
        ? ''
        : managedBuildingId
          ? String(managedBuildingId)
          : buildings[0]?.id
            ? String(buildings[0].id)
            : '',
      start_date: today,
      end_date: oneYearLater,
      tenants: [{ ...defaultTenantRow, join_date: today, billing_start_date: today }],
    })
    setErrors({})
  }

  const submit = async () => {
    if (isSaving) return

    const selectedRoom = rooms.find((room) => String(room.id) === form.room_id)
    const roomMaxOccupants = selectedRoom ? selectedRoom.max_occupants : null
    const nextErrors = validateContractForm(form, roomMaxOccupants, isSuperAdminRole(adminRole), isEditMode)
    setErrors(nextErrors)

    if (Object.keys(nextErrors).length > 0) {
      setErrorMessage('Vui lòng kiểm tra lại thông tin hợp đồng.')
      return
    }

    try {
      setIsSaving(true)
      setErrorMessage(null)
      setSuccessMessage(null)

      const payload = buildPayload(form, !isEditMode && !isRenewMode, isEditMode)

      if (isEditMode && editingContract) {
        await updateAdminContract(editingContract.id, payload)
        setSuccessMessage('Cập nhật hợp đồng thành công.')
        navigate('/admin/contracts')
      } else if (isRenewMode && editingContract) {
        const response = await renewContract(editingContract.id, payload)
        const createdContract = response.result
        if (createdContract && Number(createdContract.status) !== STATUS_PENDING_SIGN && form.is_deposit_paid && form.deposit_payment_method === '2' && Number(createdContract.deposit_amount) > 0) {
          setQrModalContract(createdContract)
          setSuccessMessage('Gia hạn hợp đồng thành công. Vui lòng quét mã QR để đóng cọc.')
        } else {
          setSuccessMessage('Gia hạn hợp đồng thành công.')
          navigate('/admin/contracts')
        }
      } else {
        const response = await createAdminContract(payload)
        const createdContract = response.result
        if (createdContract && Number(createdContract.status) !== STATUS_PENDING_SIGN && form.is_deposit_paid && form.deposit_payment_method === '2' && Number(createdContract.deposit_amount) > 0) {
          setQrModalContract(createdContract)
          setSuccessMessage('Tạo hợp đồng thành công. Vui lòng quét mã QR để đóng cọc.')
        } else {
          setSuccessMessage('Tạo hợp đồng thành công.')
          navigate('/admin/contracts')
        }
      }
    } catch (error) {
      setErrorMessage(
        getVisibleErrorMessage(
          error,
          isEditMode ? 'Không thể cập nhật hợp đồng.' : isRenewMode ? 'Không thể gia hạn hợp đồng.' : 'Không thể tạo hợp đồng.'
        )
      )
    } finally {
      setIsSaving(false)
    }
  }

  if (!canManageContracts) {
    return (
      <section className="rounded-[2rem] border border-rose-200 bg-rose-50 p-6 text-rose-700">
        <h1 className="text-xl font-black">Bạn không có quyền quản lý hợp đồng</h1>
      </section>
    )
  }

  return (
    <section className="space-y-5 text-[#24170d]">
      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-5 text-[#fff4df] lg:p-6">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_14%,rgba(243,197,107,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(15,118,110,0.26),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
          <div className="relative flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
              <button
                type="button"
                onClick={() => navigate(-1)}
                className="inline-flex items-center gap-2 text-xs font-black text-[#f3c56b] transition hover:text-[#ffd56f]"
              >
                <ArrowLeft className="h-3.5 w-3.5" /> Quay lại danh sách
              </button>
              <h1 className="mt-4 flex items-center gap-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl">
                <FileText className="h-9 w-9 text-[#f3c56b]" /> {isEditMode ? 'Cập nhật hợp đồng' : isRenewMode ? 'Gia hạn hợp đồng' : 'Thêm hợp đồng'}
              </h1>

            </div>
            <div className="flex gap-2">
              <button
                type="button"
                onClick={resetForm}
                className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/10 px-4 text-sm font-black text-[#fff4df] transition hover:bg-white/20"
              >
                <RefreshCw className="h-4 w-4" /> Làm mới
              </button>
              <button
                type="button"
                onClick={() => navigate(-1)}
                className="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] transition hover:bg-[#ffd56f]"
              >
                <X className="h-4 w-4" /> Hủy
              </button>
            </div>
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
      {isLoading && <div className="rounded-3xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-800">Đang tải thông tin hợp đồng...</div>}

      <div className="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
        <div className="space-y-5">
          <FormSection title="Thông tin hợp đồng" icon={<FileText className="h-5 w-5" />}>
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
              {!isEditMode && !isRenewMode && (
                <Field label="Trạng thái tạo" error={errors.status}>
                  <AdminSelect value={form.status} options={createStatusOptions} onChange={(value: string | number) => updateForm('status', Number(value))} />
                </Field>
              )}
              {isSuperAdminRole(adminRole) && (
                <Field label="Tòa nhà" required error={errors.building_id}>
                  <AdminSelect
                    value={form.building_id}
                    options={buildingOptions}
                    invalid={!!errors.building_id}
                    placeholder="Chọn tòa nhà"
                    onChange={(value: string | number) => updateForm('building_id', String(value))}
                  />
                </Field>
              )}
              <Field label="Phòng" required error={errors.room_id}>
                <AdminSelect
                  value={form.room_id}
                  options={roomOptions}
                  disabled={!form.building_id && isSuperAdminRole(adminRole)}
                  invalid={!!errors.room_id}
                  placeholder="Chọn phòng"
                  onChange={(value: string | number) => updateForm('room_id', String(value))}
                />
              </Field>
              <Field label="Ngày bắt đầu" required error={errors.start_date}>
                <AdminDateInput className={cn(inputClass, errors.start_date && inputErrorClass)} value={form.start_date} onChange={(value: string) => updateForm('start_date', value)} />
              </Field>
              <Field label="Ngày kết thúc" required error={errors.end_date}>
                <AdminDateInput
                  className={cn(inputClass, errors.end_date && inputErrorClass)}
                  value={form.end_date}
                  onChange={(value: string) => updateForm('end_date', value)}
                  minDate={toDate(form.start_date)}
                />
              </Field>
              <Field label="Ngày chốt tiền" required error={errors.billing_cycle_day}>
                <input
                  className={cn(inputClass, errors.billing_cycle_day && inputErrorClass)}
                  value={form.billing_cycle_day}
                  type="number"
                  min={1}
                  max={28}
                  onChange={(event) => updateForm('billing_cycle_day', event.target.value)}
                />
              </Field>
              {isEditMode && (
                <Field label="Ngày kết thúc thực tế" error={errors.actual_end_date}>
                  <AdminDateInput
                    className={cn(inputClass, errors.actual_end_date && inputErrorClass)}
                    value={form.actual_end_date}
                    onChange={(value: string) => updateForm('actual_end_date', value)}
                    minDate={toDate(form.start_date)}
                  />
                </Field>
              )}
              <Field label="Giá phòng" required error={errors.room_price}>
                <input
                  className={cn(inputClass, errors.room_price && inputErrorClass)}
                  value={form.room_price}
                  onChange={(event) => updateForm('room_price', formatMoneyInput(event.target.value))}
                  placeholder="3.500.000"
                />
              </Field>
              <Field label="Tiền cọc" required error={errors.deposit_amount}>
                <input
                  className={cn(inputClass, errors.deposit_amount && inputErrorClass)}
                  value={form.deposit_amount}
                  onChange={(event) => updateForm('deposit_amount', formatMoneyInput(event.target.value))}
                  placeholder="3.500.000"
                />
              </Field>
              {!isEditMode && !isRenewMode && Number(form.deposit_amount) > 0 && (
                <div className="flex flex-col gap-2 p-3 rounded-2xl border border-[#3d2a18]/10 bg-white/40 col-span-1 lg:col-span-2">
                  <label className="inline-flex items-center gap-2 text-xs font-black text-[#6f6254]">
                    <input type="checkbox" checked={form.is_deposit_paid} disabled onChange={(e) => updateForm('is_deposit_paid', e.target.checked)} />
                    Khách đóng tiền cọc khi ký hợp đồng
                  </label>
                  {form.is_deposit_paid && (
                    <div className="w-full sm:w-1/2">
                      <label className="mb-1.5 block px-1 text-[9px] font-black uppercase tracking-widest text-[#8b5e34]/65">Phương thức đóng cọc</label>
                      <AdminSelect
                        value={form.deposit_payment_method}
                        options={[
                          { value: '1', label: 'Tiền mặt' },
                          { value: '2', label: 'Chuyển khoản QR' },
                        ]}
                        onChange={(value: string | number) => updateForm('deposit_payment_method', String(value))}
                      />
                    </div>
                  )}
                </div>
              )}
            </div>
          </FormSection>

          <FormSection
            title="Khách thuê"
            icon={<UserPlus className="h-5 w-5" />}
            action={
              <button
                type="button"
                onClick={() =>
                  updateForm('tenants', [...form.tenants, { ...defaultTenantRow, join_date: form.start_date, billing_start_date: form.start_date }])
                }
                className="rounded-xl bg-[#24170d] px-3 py-2 text-xs font-black text-[#fff4df]"
              >
                <PlusIcon className="mr-1 inline h-3.5 w-3.5" />
                Thêm khách
              </button>
            }
          >
            <FieldError message={errors.tenants} />
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
              {form.tenants.map((tenant, index) => (
                <TenantRow
                  key={index}
                  index={index}
                  row={tenant}
                  options={tenantOptions}
                  error={errors[`tenants.${index}`]}
                  canRemove={form.tenants.length > 1}
                  onChange={(patch) => updateTenantRow(index, patch)}
                  onRemove={() => updateForm('tenants', form.tenants.filter((_, rowIndex) => rowIndex !== index))}
                  isEditMode={isEditMode || isRenewMode}
                  isRenewMode={isRenewMode}
                />
              ))}
            </div>
          </FormSection>

          <FormSection
            title="Phương tiện"
            icon={<Car className="h-5 w-5" />}
            action={
              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={() => setIsCreateVehicleOpen(true)}
                  disabled={form.tenants.every((t) => !t.tenant_id)}
                  className="rounded-xl border border-[#3d2a18]/10 bg-white px-3 py-2 text-xs font-black text-[#8b5e34] disabled:opacity-50"
                >
                  <PlusIcon className="mr-1 inline h-3.5 w-3.5" />
                  Tạo xe mới
                </button>
                <button
                  type="button"
                  onClick={() =>
                    updateForm('vehicles', [
                      ...form.vehicles,
                      {
                        vehicle_id: '',
                        started_at: form.start_date,
                        ended_at: '',
                        billing_start_date: form.start_date,
                        billing_end_date: '',
                        monthly_fee: '0',
                        charge_policy: CHARGE_MONTHLY,
                        is_active: true,
                      },
                    ])
                  }
                  className="rounded-xl bg-[#24170d] px-3 py-2 text-xs font-black text-[#fff4df]"
                >
                  <PlusIcon className="mr-1 inline h-3.5 w-3.5" />
                  Thêm xe
                </button>
              </div>
            }
          >
            <FieldError message={errors.vehicles} />
            {form.vehicles.length === 0 && <p className="text-sm font-bold text-[#8b5e34]/70">Chưa thêm phương tiện.</p>}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
              {form.vehicles.map((vehicle, index) => (
                <VehicleRow
                  key={index}
                  index={index}
                  row={vehicle}
                  options={vehicleOptions}
                  error={errors[`vehicles.${index}`]}
                  onChange={(patch) => updateVehicleRow(index, patch)}
                  onRemove={() => {
                    const vehicleId = Number(vehicle.vehicle_id)
                    if (vehicleId) {
                      deletedVehicleIdsRef.current.push(vehicleId)
                    }
                    updateForm('vehicles', form.vehicles.filter((_, rowIndex) => rowIndex !== index))
                  }}
                  isEditMode={isEditMode || isRenewMode}
                  isRenewMode={isRenewMode}
                />
              ))}
            </div>
          </FormSection>
        </div>

        <aside className="space-y-5 xl:sticky xl:top-6 xl:self-start">
          <FormSection title="File & ghi chú" icon={<FileText className="h-5 w-5" />}>
            <Field label="File hợp đồng" error={errors.contract_files}>
              <label className="flex min-h-14 cursor-pointer items-center justify-center gap-2 rounded-2xl border border-dashed border-[#3d2a18]/15 bg-white/55 px-4 py-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/15">
                <FileText className="h-4 w-4" /> Chọn PDF/ảnh hợp đồng
                <input
                  type="file"
                  className="hidden"
                  multiple
                  accept="application/pdf,image/jpeg,image/png,image/webp"
                  onChange={(event) => updateForm('contract_files', Array.from(event.target.files || []))}
                />
              </label>
              {form.contract_files.length > 0 && <p className="mt-2 text-xs font-bold text-[#6f6254]">{form.contract_files.length} file đã chọn.</p>}
            </Field>
            <Field label="Ghi chú" error={errors.note}>
              <textarea
                className={cn(inputClass, 'min-h-36 resize-none', errors.note && inputErrorClass)}
                value={form.note}
                onChange={(event) => updateForm('note', event.target.value)}
                placeholder="Ghi chú điều khoản hoặc tình trạng hợp đồng"
              />
            </Field>
          </FormSection>

          <div className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/95 p-4 shadow-xl shadow-[#6b3f1d]/8">
            <button
              type="button"
              disabled={isSaving}
              onClick={() => void submit()}
              className="inline-flex min-h-14 w-full items-center justify-center gap-2 rounded-[1.25rem] bg-[#24170d] px-5 py-3.5 text-base font-black text-[#fff4df] shadow-lg shadow-[#24170d]/12 transition hover:bg-[#3d2a18] disabled:opacity-60"
            >
              <BadgeCheck className="h-5 w-5" />{' '}
              {isSaving
                ? 'Đang lưu...'
                : isEditMode
                  ? 'Cập nhật hợp đồng'
                  : isRenewMode
                    ? 'Gia hạn hợp đồng'
                    : 'Tạo hợp đồng'}
            </button>
          </div>
        </aside>
      </div>

      {qrModalContract && (
        <DepositQRModal
          contract={qrModalContract}
          isSaving={isConfirmingDeposit}
          onClose={() => {
            setQrModalContract(null)
            navigate('/admin/contracts')
          }}
          onConfirm={async () => {
            try {
              setIsConfirmingDeposit(true)
              await createAdminContractDepositTransaction(qrModalContract.id, {
                transaction_type: 1, // COLLECT
                amount: qrModalContract.deposit_amount || '0',
                transaction_date: new Date().toISOString().slice(0, 10),
                payment_method: 2, // BANK_TRANSFER / QR
                note: 'Xác nhận thu cọc chuyển khoản QR tại chỗ khi ký hợp đồng',
              })
              setQrModalContract(null)
              navigate('/admin/contracts')
            } catch (error) {
              alert(getVisibleErrorMessage(error, 'Không thể ghi nhận giao dịch cọc.'))
            } finally {
              setIsConfirmingDeposit(false)
            }
          }}
        />
      )}

      {isCreateVehicleOpen && (
        <CreateVehicleModal
          tenantOptions={form.tenants
            .filter((t) => t.tenant_id)
            .map((t) => {
              const tenantInfo = [...tenants, ...currentContractTenants].find((tenant) => tenant.id === Number(t.tenant_id))
              return { value: Number(t.tenant_id), label: tenantInfo?.full_name || tenantInfo?.username || `Khách #${t.tenant_id}` }
            })}
          onClose={() => setIsCreateVehicleOpen(false)}
          onCreated={(newVehicle) => {
            setVehicles((current) => [...current, newVehicle])
            setForm((current) => ({
              ...current,
              vehicles: [
                ...current.vehicles,
                {
                  vehicle_id: String(newVehicle.id),
                  started_at: current.start_date,
                  ended_at: '',
                  billing_start_date: current.start_date,
                  billing_end_date: '',
                  monthly_fee: '0',
                  charge_policy: CHARGE_MONTHLY,
                  is_active: true,
                },
              ],
            }))
            setIsCreateVehicleOpen(false)
          }}
        />
      )}
    </section>
  )
}

type ContractRoomOption = {
  id: number
  building_id: number
  room_number?: string | null
  status?: number | null
  base_price?: string | number | null
  max_occupants?: number | null
  current_occupants?: number | null
}

function PlusIcon(props: React.SVGProps<SVGSVGElement>) {
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
      <path d="M5 12h14" />
      <path d="M12 5v14" />
    </svg>
  )
}
