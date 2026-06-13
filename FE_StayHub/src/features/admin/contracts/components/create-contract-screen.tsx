import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, BadgeCheck, Car, FileText, Plus, RefreshCw, Trash2, UserPlus, X } from 'lucide-react'
import { AdminDateInput } from '../../../../shared/components/AdminDateInput'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatCurrency } from '../../../../shared/lib/utils/format'
import { canManageContractsRole, isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { useAdminSocket } from '../../../../shared/lib/socket/socket-context'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { fetchAdminTenants } from '../../tenants/services/tenants.service'
import type { AdminTenantResource } from '../../tenants/types/tenant-api.model'
import { createAdminContract, createAdminContractDepositTransaction, createAdminVehicle, fetchAdminContractDetail, fetchAvailableRooms, fetchAdminContractVehicles, updateAdminContract } from '../services/contracts.service'
import type {
  AdminContractPayload,
  AdminContractResource,
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

const STATUS_ACTIVE = 1
const CHARGE_MONTHLY = 1
const CHARGE_FREE = 3

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'
const inputErrorClass = 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100'
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65'

const todayStr = new Date().toISOString().slice(0, 10)
const oneYearLaterStr = (() => {
  const date = new Date()
  date.setFullYear(date.getFullYear() + 1)
  date.setDate(date.getDate() - 1)
  return date.toISOString().slice(0, 10)
})()

const defaultTenantRow: ContractTenantFormRow = {
  tenant_id: '',
  join_date: todayStr,
  leave_date: '',
  billing_start_date: todayStr,
  billing_end_date: '',
  is_staying: true,
}

const defaultForm: ContractFormValues = {
  contract_code: '',
  building_id: '',
  room_id: '',
  start_date: todayStr,
  end_date: oneYearLaterStr,
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

const createStatusOptions = [
  { value: STATUS_ACTIVE, label: 'Đang hiệu lực', tone: 'success' as const },
]

const chargePolicyOptions = [
  { value: 1, label: 'Tính theo tháng', tone: 'default' as const },
  { value: 2, label: 'Tính theo ngày', tone: 'warning' as const },
  { value: 3, label: 'Miễn phí', tone: 'success' as const },
]



export function CreateContractScreen() {
  const navigate = useNavigate()
  const { contractId } = useParams()
  const { session } = useAdminSession()
  const adminRole = session?.admin?.role
  const isEditMode = Boolean(contractId)
  const isSuperAdmin = useMemo(() => isSuperAdminRole(adminRole), [adminRole])
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

  const buildingOptions = useMemo(() => buildings.map((building) => ({ value: building.id, label: building.name, tone: 'default' as const })), [buildings])
  const roomOptions = useMemo(() => rooms.map((room) => ({ value: room.id, label: `Phòng ${room.room_number || room.id}`, description: `Đang ở ${room.current_occupants ?? 0}/${room.max_occupants ?? '—'} người`, tone: room.status === 1 ? 'success' as const : 'warning' as const })), [rooms])
  
  const tenantOptions = useMemo(() => {
    const merged = [...tenants]
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
      tone: 'default' as const
    }))
  }, [tenants, currentContractTenants])

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
      tone: vehicle.is_active ? 'success' as const : 'warning' as const
    }))
  }, [vehicles, currentContractVehicles])

  const loadBuildings = useCallback(async () => {
    if (!canManageContracts) return

    try {
      const response = await fetchAdminBuildings({ per_page: 100 })
      const list = getResourceList(response.result)
      setBuildings(list)

      if (!isSuperAdmin && !form.building_id) {
        const buildingId = list[0]?.id || managedBuildingId
        if (buildingId) setForm((current) => ({ ...current, building_id: String(buildingId) }))
      }
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải danh sách tòa nhà.'))
    }
  }, [canManageContracts, form.building_id, isSuperAdmin, managedBuildingId])

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
      setVehicles(Array.from(new Map(nextVehicles.map((vehicle) => [vehicle.id, vehicle])).values()))
    } catch (error) {
      setVehicles([])
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải danh sách phương tiện.'))
    }
  }, [])

  const autoPopulateVehicles = useCallback(async (tenantIds: number[], startDate: string) => {
    // 1. Remove vehicles belonging to tenants no longer in the form
    setForm((current) => {
      const tenantIdSet = new Set(tenantIds)
      const allKnownVehicles = [...vehicles, ...currentContractVehicles]
      const cleanedVehicles = current.vehicles.filter((v) => {
        const vehicleId = Number(v.vehicle_id)
        if (!vehicleId) return true // keep empty/uncommitted rows
        const vehicleInfo = allKnownVehicles.find((av) => av.id === vehicleId)
        if (!vehicleInfo) return true // keep if we can't determine ownership
        return tenantIdSet.has(vehicleInfo.tenant_id)
      })
      if (cleanedVehicles.length !== current.vehicles.length) {
        return { ...current, vehicles: cleanedVehicles }
      }
      return current
    })

    // 2. Fetch available vehicles for these tenants
    if (tenantIds.length === 0) return

    try {
      const responses = await Promise.all(tenantIds.map((tenantId) => fetchAdminContractVehicles({ tenant_id: tenantId, is_active: true, without_active_contract: true, per_page: 100 })))
      const fetchedVehicles = responses.flatMap((response) => getResourceList(response.result))
      const uniqueFetched = Array.from(new Map(fetchedVehicles.map((v) => [v.id, v])).values())

      // 3. Auto-append new vehicles that are not already in the form
      setForm((current) => {
        const existingVehicleIds = new Set(current.vehicles.map((v) => Number(v.vehicle_id)).filter((id) => id > 0))
        const newRows: ContractVehicleFormRow[] = uniqueFetched
          .filter((v) => !existingVehicleIds.has(v.id))
          .map((v) => ({
            vehicle_id: String(v.id),
            started_at: startDate || current.start_date,
            ended_at: '',
            billing_start_date: startDate || current.start_date,
            billing_end_date: '',
            monthly_fee: '0.00',
            charge_policy: CHARGE_MONTHLY,
            is_active: true,
          }))

        if (newRows.length === 0) return current
        return { ...current, vehicles: [...current.vehicles, ...newRows] }
      })
    } catch {
      // Vehicle fetch errors are non-blocking for auto-population
    }
  }, [vehicles, currentContractVehicles])

  const loadContract = useCallback(async () => {
    if (!contractId) return

    try {
      setIsLoading(true)
      const response = await fetchAdminContractDetail(Number(contractId))
      const contract = response.result
      if (!contract) return
      setEditingContract(contract)
      setForm(contractToForm(contract))
      const currentTenants = (contract.contract_tenants || [])
        .map((ct) => ct.tenant)
        .filter(Boolean) as AdminTenantResource[]
      setCurrentContractTenants(currentTenants)
      const currentVehicles = (contract.contract_vehicles || [])
        .filter((cv) => cv.is_active !== false)
        .map((cv) => cv.vehicle)
        .filter(Boolean) as AdminVehicleOptionResource[]
      setCurrentContractVehicles(currentVehicles)
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tải chi tiết hợp đồng.'))
    } finally {
      setIsLoading(false)
    }
  }, [contractId])

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void loadBuildings()
  }, [loadBuildings])

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void loadContract()
  }, [loadContract])

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void loadRoomsForBuilding(form.building_id)
    void loadTenants(form.building_id)
  }, [form.building_id, loadRoomsForBuilding, loadTenants])

  useEffect(() => {
    const tenantIds = form.tenants.map((tenant) => Number(tenant.tenant_id)).filter((id) => id > 0)
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void loadVehiclesForTenants(tenantIds)
    void autoPopulateVehicles(tenantIds, form.start_date)
  }, [form.tenants, form.start_date, loadVehiclesForTenants, autoPopulateVehicles])

  const updateForm = <K extends keyof ContractFormValues>(key: K, value: ContractFormValues[K]) => {
    setForm((current) => {
      const next = { ...current, [key]: value }
      if (key === 'start_date' && typeof value === 'string') {
        next.vehicles = current.vehicles.map((v) => ({
          ...v,
          started_at: value,
          billing_start_date: value
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
    setForm((current) => ({ ...current, vehicles: current.vehicles.map((vehicle, vehicleIndex) => vehicleIndex === index ? { ...vehicle, ...patch } : vehicle) }))
    setErrors((current) => ({ ...current, vehicles: undefined, [`vehicles.${index}`]: undefined }))
  }



  const resetForm = () => {
    if (editingContract) {
      setForm(contractToForm(editingContract))
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
      building_id: isSuperAdmin ? '' : managedBuildingId ? String(managedBuildingId) : buildings[0]?.id ? String(buildings[0].id) : '',
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

      const payload = buildPayload(form, !isEditMode)

      if (isEditMode && editingContract) {
        await updateAdminContract(editingContract.id, payload)
        setSuccessMessage('Cập nhật hợp đồng thành công.')
        navigate('/admin/contracts')
      } else {
        const response = await createAdminContract(payload)
        const createdContract = response.result
        if (createdContract && form.is_deposit_paid && form.deposit_payment_method === '2' && Number(createdContract.deposit_amount) > 0) {
          setQrModalContract(createdContract)
          setSuccessMessage('Tạo hợp đồng thành công. Vui lòng quét mã QR để đóng cọc.')
        } else {
          setSuccessMessage('Tạo hợp đồng thành công.')
          navigate('/admin/contracts')
        }
      }
    } catch (error) {
      setErrorMessage(getVisibleErrorMessage(error, isEditMode ? 'Không thể cập nhật hợp đồng.' : 'Không thể tạo hợp đồng.'))
    } finally {
      setIsSaving(false)
    }
  }

  if (!canManageContracts) {
    return <section className="rounded-[2rem] border border-rose-200 bg-rose-50 p-6 text-rose-700"><h1 className="text-xl font-black">Bạn không có quyền quản lý hợp đồng</h1></section>
  }

  return (
    <section className="space-y-5 text-[#24170d]">
      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-5 text-[#fff4df] lg:p-6">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_14%,rgba(243,197,107,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(15,118,110,0.26),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
          <div className="relative flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
              <Link to="/admin/contracts" className="inline-flex items-center gap-2 text-xs font-black text-[#f3c56b] transition hover:text-[#ffd56f]"><ArrowLeft className="h-3.5 w-3.5" /> Quay lại danh sách</Link>
              <h1 className="mt-4 flex items-center gap-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl">
                <FileText className="h-9 w-9 text-[#f3c56b]" /> {isEditMode ? 'Cập nhật hợp đồng' : 'Thêm hợp đồng'}
              </h1>
              <p className="mt-2 max-w-3xl text-sm font-semibold text-[#f8e8c8]/75">Form được tách ra page riêng để nhập đầy đủ tenant, xe, tiền cọc và file hợp đồng.</p>
            </div>
            <div className="flex gap-2">
              <button type="button" onClick={resetForm} className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/10 px-4 text-sm font-black text-[#fff4df] transition hover:bg-white/20"><RefreshCw className="h-4 w-4" /> Làm mới</button>
              <button type="button" onClick={() => navigate('/admin/contracts')} className="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] transition hover:bg-[#ffd56f]"><X className="h-4 w-4" /> Hủy</button>
            </div>
          </div>
        </div>
      </section>

      {(errorMessage || successMessage) && <div className={cn('rounded-3xl border px-4 py-3 text-sm font-black shadow-sm', errorMessage ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700')}>{errorMessage || successMessage}</div>}
      {isLoading && <div className="rounded-3xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-800">Đang tải thông tin hợp đồng...</div>}

      <div className="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
        <div className="space-y-5">
          <FormSection title="Thông tin hợp đồng" icon={<FileText className="h-5 w-5" />}>
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
              {!isEditMode && <Field label="Trạng thái tạo" error={errors.status}><AdminSelect value={form.status} options={createStatusOptions} onChange={(value) => updateForm('status', Number(value))} /></Field>}
              {isSuperAdmin && <Field label="Tòa nhà" required error={errors.building_id}><AdminSelect value={form.building_id} options={buildingOptions} invalid={!!errors.building_id} placeholder="Chọn tòa nhà" onChange={(value) => { updateForm('building_id', String(value)); updateForm('room_id', '') }} /></Field>}
              <Field label="Phòng" required error={errors.room_id}><AdminSelect value={form.room_id} options={roomOptions} disabled={!form.building_id && isSuperAdmin} invalid={!!errors.room_id} placeholder="Chọn phòng" onChange={(value) => updateForm('room_id', String(value))} /></Field>
              <Field label="Ngày bắt đầu" required error={errors.start_date}><AdminDateInput className={cn(inputClass, errors.start_date && inputErrorClass)} value={form.start_date} onChange={(value) => updateForm('start_date', value)} /></Field>
              <Field label="Ngày kết thúc" required error={errors.end_date}><AdminDateInput className={cn(inputClass, errors.end_date && inputErrorClass)} value={form.end_date} onChange={(value) => updateForm('end_date', value)} minDate={toDate(form.start_date)} /></Field>
              <Field label="Ngày chốt tiền" required error={errors.billing_cycle_day}><input className={cn(inputClass, errors.billing_cycle_day && inputErrorClass)} value={form.billing_cycle_day} type="number" min={1} max={28} onChange={(event) => updateForm('billing_cycle_day', event.target.value)} /></Field>
              <Field label="Ngày kết thúc thực tế" error={errors.actual_end_date}><AdminDateInput className={cn(inputClass, errors.actual_end_date && inputErrorClass)} value={form.actual_end_date} onChange={(value) => updateForm('actual_end_date', value)} minDate={toDate(form.start_date)} /></Field>
              <Field label="Giá phòng" required error={errors.room_price}><input className={cn(inputClass, errors.room_price && inputErrorClass)} value={form.room_price} onChange={(event) => updateForm('room_price', event.target.value)} placeholder="3500000.00" /></Field>
              <Field label="Tiền cọc" required error={errors.deposit_amount}>
                <input className={cn(inputClass, errors.deposit_amount && inputErrorClass)} value={form.deposit_amount} onChange={(event) => updateForm('deposit_amount', event.target.value)} placeholder="3500000.00" />
              </Field>
              {!isEditMode && Number(form.deposit_amount) > 0 && (
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
                          { value: '2', label: 'Chuyển khoản QR' }
                        ]}
                        onChange={(value) => updateForm('deposit_payment_method', String(value))}
                      />
                    </div>
                  )}
                </div>
              )}
            </div>
          </FormSection>

          <FormSection title="Khách thuê" icon={<UserPlus className="h-5 w-5" />} action={<button type="button" onClick={() => updateForm('tenants', [...form.tenants, { ...defaultTenantRow, join_date: form.start_date, billing_start_date: form.start_date }])} className="rounded-xl bg-[#24170d] px-3 py-2 text-xs font-black text-[#fff4df]"><Plus className="mr-1 inline h-3.5 w-3.5" />Thêm khách</button>}>
            <FieldError message={errors.tenants} />
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
              {form.tenants.map((tenant, index) => <TenantRow key={index} index={index} row={tenant} options={tenantOptions} error={errors[`tenants.${index}`]} canRemove={form.tenants.length > 1} onChange={(patch) => updateTenantRow(index, patch)} onRemove={() => updateForm('tenants', form.tenants.filter((_, rowIndex) => rowIndex !== index))} isEditMode={isEditMode} />)}
            </div>
          </FormSection>

          <FormSection title="Phương tiện" icon={<Car className="h-5 w-5" />} action={
            <div className="flex gap-2">
              <button type="button" onClick={() => setIsCreateVehicleOpen(true)} disabled={form.tenants.every((t) => !t.tenant_id)} className="rounded-xl border border-[#3d2a18]/10 bg-white px-3 py-2 text-xs font-black text-[#8b5e34] disabled:opacity-50"><Plus className="mr-1 inline h-3.5 w-3.5" />Tạo xe mới</button>
              <button type="button" onClick={() => updateForm('vehicles', [...form.vehicles, { vehicle_id: '', started_at: form.start_date, ended_at: '', billing_start_date: form.start_date, billing_end_date: '', monthly_fee: '0.00', charge_policy: CHARGE_MONTHLY, is_active: true }])} className="rounded-xl bg-[#24170d] px-3 py-2 text-xs font-black text-[#fff4df]"><Plus className="mr-1 inline h-3.5 w-3.5" />Thêm xe</button>
            </div>
          }>
            <FieldError message={errors.vehicles} />
            {form.vehicles.length === 0 && <p className="text-sm font-bold text-[#8b5e34]/70">Chưa thêm phương tiện.</p>}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
              {form.vehicles.map((vehicle, index) => <VehicleRow key={index} index={index} row={vehicle} options={vehicleOptions} error={errors[`vehicles.${index}`]} onChange={(patch) => updateVehicleRow(index, patch)} onRemove={() => updateForm('vehicles', form.vehicles.filter((_, rowIndex) => rowIndex !== index))} isEditMode={isEditMode} />)}
            </div>
          </FormSection>
        </div>

        <aside className="space-y-5 xl:sticky xl:top-6 xl:self-start">
          <FormSection title="File & ghi chú" icon={<FileText className="h-5 w-5" />}>
            <Field label="File hợp đồng" error={errors.contract_files}>
              <label className="flex min-h-14 cursor-pointer items-center justify-center gap-2 rounded-2xl border border-dashed border-[#3d2a18]/15 bg-white/55 px-4 py-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/15">
                <FileText className="h-4 w-4" /> Chọn PDF/ảnh hợp đồng
                <input type="file" className="hidden" multiple accept="application/pdf,image/jpeg,image/png,image/webp" onChange={(event) => updateForm('contract_files', Array.from(event.target.files || []))} />
              </label>
              {form.contract_files.length > 0 && <p className="mt-2 text-xs font-bold text-[#6f6254]">{form.contract_files.length} file đã chọn.</p>}
            </Field>
            <Field label="Ghi chú" error={errors.note}><textarea className={cn(inputClass, 'min-h-36 resize-none', errors.note && inputErrorClass)} value={form.note} onChange={(event) => updateForm('note', event.target.value)} placeholder="Ghi chú điều khoản hoặc tình trạng hợp đồng" /></Field>
          </FormSection>

          <div className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/95 p-4 shadow-xl shadow-[#6b3f1d]/8">
            <button type="button" disabled={isSaving} onClick={() => void submit()} className="inline-flex min-h-14 w-full items-center justify-center gap-2 rounded-[1.25rem] bg-[#24170d] px-5 py-3.5 text-base font-black text-[#fff4df] shadow-lg shadow-[#24170d]/12 transition hover:bg-[#3d2a18] disabled:opacity-60">
              <BadgeCheck className="h-5 w-5" /> {isSaving ? 'Đang lưu...' : isEditMode ? 'Cập nhật hợp đồng' : 'Tạo hợp đồng'}
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
                amount: qrModalContract.deposit_amount || '0.00',
                transaction_date: new Date().toISOString().slice(0, 10),
                payment_method: 2, // BANK_TRANSFER / QR
                note: 'Xác nhận thu cọc chuyển khoản QR tại chỗ khi ký hợp đồng'
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
                  monthly_fee: '0.00',
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
  const { echo } = useAdminSocket()
  const [timeLeft, setTimeLeft] = useState(1800) // 30 minutes in seconds
  const [isPaidSuccess, setIsPaidSuccess] = useState(false)

  useEffect(() => {
    if (!echo) return
    const channel = echo.private('admin-maintenance')
    channel.listen('.ContractDepositPaid', (event: any) => {
      const updatedContract = event.contract
      if (updatedContract && Number(updatedContract.id) === Number(contract.id) && updatedContract.is_deposit_paid) {
        setIsPaidSuccess(true)
        setTimeout(() => {
          onClose()
        }, 2000)
      }
    })
    return () => {
      channel.stopListening('.ContractDepositPaid')
    }
  }, [echo, contract.id, onClose])

  useEffect(() => {
    if (timeLeft <= 0 || isPaidSuccess) return
    const timer = setInterval(() => {
      setTimeLeft((prev) => prev - 1)
    }, 1000)
    return () => clearInterval(timer)
  }, [timeLeft, isPaidSuccess])

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
            {isPaidSuccess ? (
              <div className="flex h-[280px] w-[280px] flex-col items-center justify-center text-center p-4 bg-emerald-50 rounded-2xl border border-emerald-200">
                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 animate-bounce">
                  <BadgeCheck className="h-9 w-9" />
                </div>
                <p className="mt-4 text-sm font-black text-emerald-700 font-black">Thanh toán thành công!</p>
                <p className="mt-1.5 text-xs text-emerald-600/80 font-bold">Hệ thống đang tự động chuyển hướng...</p>
              </div>
            ) : isExpired ? (
              <div className="flex h-[280px] w-[280px] flex-col items-center justify-center text-center p-4 bg-stone-50 rounded-2xl">
                <p className="text-sm font-bold text-rose-500">Mã QR đã hết hạn (30 phút)</p>
                <p className="mt-2 text-xs text-stone-500">Vui lòng đóng modal và tạo lại/mở lại để lấy mã mới.</p>
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
              <span className="font-black text-[#a65f16]">{contract.contract_code}</span>
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

function FormSection({ title, icon, action, children }: { title: string; icon: React.ReactNode; action?: React.ReactNode; children: React.ReactNode }) {
  return <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/95 p-4 shadow-xl shadow-[#6b3f1d]/8"><div className="mb-4 flex items-center justify-between gap-3"><h2 className="flex items-center gap-2 text-lg font-black text-[#24170d]">{icon}{title}</h2>{action}</div>{children}</section>
}

const vehicleTypeOptions = [
  { value: 1, label: 'Xe máy' },
  { value: 2, label: 'Xe đạp' },
  { value: 3, label: 'Ô tô' },
  { value: 4, label: 'Xe điện' },
]

function CreateVehicleModal({
  tenantOptions,
  onClose,
  onCreated,
}: {
  tenantOptions: Array<{ value: number; label: string }>
  onClose: () => void
  onCreated: (vehicle: AdminVehicleOptionResource) => void
}) {
  const [vehicleForm, setVehicleForm] = useState({
    tenant_id: tenantOptions.length === 1 ? String(tenantOptions[0].value) : '',
    vehicle_type: '1',
    license_plate: '',
    brand: '',
    color: '',
  })
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async () => {
    if (!vehicleForm.tenant_id) { setError('Vui lòng chọn khách thuê sở hữu xe.'); return }
    if (!vehicleForm.license_plate.trim()) { setError('Vui lòng nhập biển số xe.'); return }

    try {
      setIsSaving(true)
      setError(null)
      const response = await createAdminVehicle({
        tenant_id: Number(vehicleForm.tenant_id),
        vehicle_type: Number(vehicleForm.vehicle_type),
        license_plate: vehicleForm.license_plate.trim(),
        brand: vehicleForm.brand.trim() || undefined,
        color: vehicleForm.color.trim() || undefined,
      })
      if (response.result) {
        onCreated(response.result)
      }
    } catch (err) {
      setError(getVisibleErrorMessage(err, 'Không thể tạo phương tiện.'))
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true">
      <button type="button" aria-label="Đóng" onClick={onClose} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />
      <div className="relative z-10 w-full max-w-md rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] p-5 shadow-2xl">
        <h2 className="text-lg font-black text-[#24170d]">Tạo phương tiện mới</h2>
        <p className="mt-1 text-xs font-bold text-[#8b5e34]/70">Xe sẽ được tạo và tự động thêm vào hợp đồng.</p>

        {error && <p className="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-black text-rose-700">{error}</p>}

        <div className="mt-4 space-y-3">
          <div>
            <label className={labelClass}>Khách thuê sở hữu <span className="text-rose-500">*</span></label>
            <AdminSelect
              value={vehicleForm.tenant_id}
              options={tenantOptions.map((t) => ({ value: t.value, label: t.label, tone: 'default' as const }))}
              placeholder="Chọn khách thuê"
              onChange={(value) => setVehicleForm((f) => ({ ...f, tenant_id: String(value) }))}
            />
          </div>
          <div>
            <label className={labelClass}>Loại xe <span className="text-rose-500">*</span></label>
            <AdminSelect
              value={Number(vehicleForm.vehicle_type)}
              options={vehicleTypeOptions.map((t) => ({ ...t, tone: 'default' as const }))}
              onChange={(value) => setVehicleForm((f) => ({ ...f, vehicle_type: String(value) }))}
            />
          </div>
          <div>
            <label className={labelClass}>Biển số xe <span className="text-rose-500">*</span></label>
            <input className={inputClass} value={vehicleForm.license_plate} onChange={(e) => setVehicleForm((f) => ({ ...f, license_plate: e.target.value }))} placeholder="59F1-12345" />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className={labelClass}>Hiệu xe</label>
              <input className={inputClass} value={vehicleForm.brand} onChange={(e) => setVehicleForm((f) => ({ ...f, brand: e.target.value }))} placeholder="Honda" />
            </div>
            <div>
              <label className={labelClass}>Màu xe</label>
              <input className={inputClass} value={vehicleForm.color} onChange={(e) => setVehicleForm((f) => ({ ...f, color: e.target.value }))} placeholder="Đen" />
            </div>
          </div>
        </div>

        <div className="mt-5 flex gap-3">
          <button type="button" onClick={onClose} className="h-12 flex-1 rounded-xl border border-[#3d2a18]/10 bg-white text-sm font-black text-[#8b5e34]">Hủy</button>
          <button type="button" disabled={isSaving} onClick={handleSubmit} className="h-12 flex-1 rounded-xl bg-[#24170d] text-sm font-black text-[#fff4df] disabled:opacity-60">{isSaving ? 'Đang tạo...' : 'Tạo xe'}</button>
        </div>
      </div>
    </div>
  )
}

function Field({ label, required, error, children }: { label: string; required?: boolean; error?: string; children: React.ReactNode }) {
  return <div><label className={labelClass}>{label} {required && <span className="text-rose-500">*</span>}</label>{children}<FieldError message={error} /></div>
}

function TenantRow({ index, row, options, error, canRemove, onChange, onRemove, isEditMode }: { index: number; row: ContractTenantFormRow; options: Array<{ value: string | number; label: string; description?: string }>; error?: string; canRemove: boolean; onChange: (patch: Partial<ContractTenantFormRow>) => void; onRemove: () => void; isEditMode: boolean }) {
  return (
    <div className="rounded-2xl border border-[#3d2a18]/10 bg-white/60 p-3">
      <RowHeader title={`Khách #${index + 1}`} canRemove={canRemove} onRemove={onRemove} />
      <div className="mt-3 space-y-3">
        <AdminSelect value={row.tenant_id} options={options} invalid={!!error} placeholder="Chọn khách thuê" onChange={(value) => onChange({ tenant_id: String(value) })} />
        <div className={cn("grid grid-cols-1 gap-3", isEditMode && "sm:grid-cols-2")}>
          <AdminDateInput className={inputClass} value={row.join_date} onChange={(value) => onChange({ join_date: value, billing_start_date: row.billing_start_date || value })} />
          {isEditMode && (
            <AdminDateInput className={inputClass} value={row.leave_date} onChange={(value) => onChange({ leave_date: value, is_staying: !value })} placeholder="Ngày rời đi" />
          )}
        </div>
        {isEditMode && (
          <div className="flex flex-wrap gap-3 text-xs font-black text-[#6f6254]">
            <label className="inline-flex items-center gap-2">
              <input type="checkbox" checked={row.is_staying} onChange={(event) => onChange({ is_staying: event.target.checked })} /> Đang ở
            </label>
          </div>
        )}
        <FieldError message={error} />
      </div>
    </div>
  )
}

function VehicleRow({ index, row, options, error, onChange, onRemove, isEditMode }: { index: number; row: ContractVehicleFormRow; options: Array<{ value: string | number; label: string; description?: string }>; error?: string; onChange: (patch: Partial<ContractVehicleFormRow>) => void; onRemove: () => void; isEditMode: boolean }) {
  return (
    <div className="rounded-2xl border border-[#3d2a18]/10 bg-white/60 p-3">
      <RowHeader title={`Xe #${index + 1}`} canRemove onRemove={onRemove} />
      <div className="mt-3 space-y-3">
        <AdminSelect value={row.vehicle_id} options={options} invalid={!!error} placeholder="Chọn phương tiện" onChange={(value) => onChange({ vehicle_id: String(value) })} />
        <div className={cn("grid grid-cols-1 gap-3", isEditMode && "sm:grid-cols-2")}>
          <AdminDateInput className={inputClass} value={row.started_at} disabled onChange={(value) => onChange({ started_at: value, billing_start_date: row.billing_start_date || value })} />
          {isEditMode && (
            <AdminDateInput className={inputClass} value={row.ended_at} onChange={(value) => onChange({ ended_at: value, is_active: !value })} placeholder="Ngày kết thúc" />
          )}
        </div>
        <AdminSelect value={row.charge_policy} options={chargePolicyOptions} onChange={(value) => onChange({ charge_policy: Number(value), monthly_fee: Number(value) === CHARGE_FREE ? '0.00' : row.monthly_fee })} />
        <input className={cn(inputClass, error && inputErrorClass)} value={row.monthly_fee} disabled={Number(row.charge_policy) === CHARGE_FREE} onChange={(event) => onChange({ monthly_fee: event.target.value })} placeholder="Phí gửi xe" />
        {isEditMode && (
          <label className="inline-flex items-center gap-2 text-xs font-black text-[#6f6254]">
            <input type="checkbox" checked={row.is_active} onChange={(event) => onChange({ is_active: event.target.checked })} /> Còn tính phí
          </label>
        )}
        <FieldError message={error} />
      </div>
    </div>
  )
}



function RowHeader({ title, canRemove, onRemove }: { title: string; canRemove: boolean; onRemove: () => void }) {
  return <div className="flex items-center justify-between gap-2"><p className="text-xs font-black text-[#24170d]">{title}</p>{canRemove && <button type="button" onClick={onRemove} className="inline-flex items-center gap-1 text-xs font-black text-rose-600"><Trash2 className="h-3.5 w-3.5" /> Xóa</button>}</div>
}

function FieldError({ message }: { message?: string }) {
  if (!message) return null
  return <p className="mt-1.5 px-1 text-xs font-bold text-rose-600">{message}</p>
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
    vehicles: (contract.contract_vehicles || []).filter((vehicle) => vehicle.is_active !== false).map((vehicle) => ({
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
    tenants: form.tenants.map((tenant) => ({ tenant_id: Number(tenant.tenant_id), join_date: tenant.join_date, leave_date: tenant.leave_date || null, billing_start_date: tenant.billing_start_date || tenant.join_date, billing_end_date: tenant.billing_end_date || tenant.leave_date || null, is_staying: tenant.is_staying })),
    vehicles: form.vehicles.map((vehicle) => ({ vehicle_id: Number(vehicle.vehicle_id), started_at: vehicle.started_at, ended_at: vehicle.ended_at || null, billing_start_date: vehicle.billing_start_date || vehicle.started_at, billing_end_date: vehicle.billing_end_date || vehicle.ended_at || null, monthly_fee: Number(vehicle.charge_policy) === CHARGE_FREE ? '0.00' : vehicle.monthly_fee.trim(), charge_policy: Number(vehicle.charge_policy), is_active: vehicle.is_active })),
    deposit_transactions: form.deposit_transactions.map((transaction) => ({ transaction_type: Number(transaction.transaction_type), amount: transaction.amount.trim(), transaction_date: transaction.transaction_date, payment_method: Number(transaction.payment_method), note: transaction.note.trim() || null })),
    is_deposit_paid: isQr ? false : form.is_deposit_paid,
    deposit_payment_method: isQr ? null : (form.is_deposit_paid ? Number(form.deposit_payment_method) : null),
  }

  if (includeStatus) payload.status = Number(form.status)
  return payload
}
