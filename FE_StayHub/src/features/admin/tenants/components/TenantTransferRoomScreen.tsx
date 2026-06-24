import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, Building2, ChevronRight, DoorOpen, Loader2, Plus, Search, Trash2, Users } from 'lucide-react'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { buildingAllowsTenantGender } from '../../shared/config/gender-policy'
import { fetchAdminTenantDetail } from '../services/tenants.service'
import { transferTenantRoom } from '../services/TranferRoom'
import { fetchAdminRooms, fetchBuilding } from '../../rooms/services/rooms.service'
import type { AdminTenantResource } from '../types/tenant-api.model'
import type { AdminRoomResource, BuildingResource } from '../../rooms/types/rooms.model'
import type { TransferTenantPayload } from '../types/TranferModel'
import type { AdminMeterDeviceResource } from '../../meters/types/meter-api.model'
import { fetchAdminMeterDevices } from '../../meters/services/meters.service'
import type { AdminVehicleOptionResource } from '../../contracts/types/contract-api.model'
import { fetchAdminContractVehicles } from '../../contracts/services/contracts.service'
const ROOM_STATUS_ACTIVE = 1

const inputClass =
  'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'

interface MeterReadingRow {
  meterDeviceId: string
  currentReading: string
}


export function TenantTransferRoomScreen() {
  const { tenantId } = useParams<{ tenantId: string }>()
  const navigate = useNavigate()
  const parsedTenantId = Number(tenantId)

  const [step, setStep] = useState<1 | 2>(1)

  const [tenant, setTenant] = useState<AdminTenantResource | null>(null)
  const [isTenantLoading, setIsTenantLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [buildings, setBuildings] = useState<BuildingResource[]>([])
  const [selectedBuildingId, setSelectedBuildingId] = useState<string>('')
  const [rooms, setRooms] = useState<AdminRoomResource[]>([])
  const [isRoomsLoading, setIsRoomsLoading] = useState(false)
  const [roomKeyword, setRoomKeyword] = useState('')
  const [selectedRoom, setSelectedRoom] = useState<AdminRoomResource | null>(null)

  const [movementDate, setMovementDate] = useState(todayDateString())
  const [note, setNote] = useState('')
  const [depositSettlementAmount, setDepositSettlementAmount] = useState('')
  const [depositDeductionAmount, setDepositDeductionAmount] = useState('')
  const [depositRefundAmount, setDepositRefundAmount] = useState('')
  const [transferFee, setTransferFee] = useState('')
  const [meterReadingRows, setMeterReadingRows] = useState<MeterReadingRow[]>([])
  const [meterDevices,setMeterDevices]=useState<AdminMeterDeviceResource[]>([]);
  const [carryVehicleIds, setCarryVehicleIds] = useState<number[]>([]);
  const [vehicles,setVehicles]=useState<AdminVehicleOptionResource[]>([]);   

  const [isSubmitting, setIsSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})

  const currentRoomId = tenant?.current_room?.room_id ?? tenant?.room_id ?? null
  const currentBuildingId = tenant?.current_room?.building_id ?? tenant?.building_id ?? null
  const currentRoomNumber = tenant?.current_room?.room_number ?? tenant?.room_number ?? null
  const currentBuildingName = tenant?.current_room?.building_name ?? tenant?.building_name ?? null
   
  // --- Tải thông tin tenant + phòng hiện tại ---
  useEffect(() => {
    if (!parsedTenantId) return
    let isMounted = true
    setIsTenantLoading(true)
    setLoadError(null)

    fetchAdminTenantDetail(parsedTenantId)
      .then((response) => {
        if (!isMounted) return
        setTenant(unwrap<AdminTenantResource>(response) ?? null)
      })
      .catch((error) => {
        if (!isMounted) return
        setLoadError(getVisibleErrorMessage(error, 'Không thể tải thông tin khách thuê.'))
      })
      .finally(() => {
        if (isMounted) setIsTenantLoading(false)
      })

    return () => {
      isMounted = false
    }
  }, [parsedTenantId])

  // --- Tải danh sách tòa nhà, mặc định lọc theo tòa hiện tại của tenant ---
  useEffect(() => {
    fetchBuilding()
      .then((response) => {
        setBuildings(unwrap<BuildingResource[]>(response) ?? [])
      })
      .catch(() => setBuildings([]))
  }, [])

  useEffect(() => {
    if (currentBuildingId) {
      setSelectedBuildingId(String(currentBuildingId))
    }
  }, [currentBuildingId])

  // --- Tải danh sách phòng theo tòa đã chọn ---
  useEffect(() => {
    setIsRoomsLoading(true)
    fetchAdminRooms({
      building_id: selectedBuildingId ? Number(selectedBuildingId) : undefined,
      per_page: 100,
    })
      .then((response) => {
        setRooms(unwrap<AdminRoomResource[]>(response) ?? [])
      })
      .catch(() => setRooms([]))
      .finally(() => setIsRoomsLoading(false))
  }, [selectedBuildingId])
  useEffect(()=>{
    if(!currentRoomId) return;
    fetchAdminMeterDevices({
      room_id:currentRoomId,
      status: 1,
      per_page:100,
    }).then((response)=>{
      const result=unwrap(response);
        const devices = result.data ?? []
      setMeterDevices(devices);
      setMeterReadingRows(
          devices.map((meter)=>({
            meterDeviceId:String(meter.id),
            currentReading:"",
          }))
      )
    }).catch(()=>{
      setMeterDevices([]);
        setMeterReadingRows([])

    });
  },[currentRoomId]);

   useEffect(()=>{
    if(!parsedTenantId) return
    fetchAdminContractVehicles({
      tenant_id:parsedTenantId,
      is_active:true,
      per_page:100,
    }).then((response)=>{
      const result=unwrap(response);
      setVehicles(result.data??[]);
    }).catch(()=>{
      setVehicles([]);
    });
   },[parsedTenantId])
  

  const availableRooms = useMemo(() => {
    const keyword = roomKeyword.trim().toLowerCase()
    return rooms.filter((room) => {
      if (room.id === currentRoomId) return false
      if (room.status !== ROOM_STATUS_ACTIVE) return false
      if (room.current_occupants >= room.max_occupants) return false
      const roomBuildingPolicy = room.building?.gender_policy ?? buildings.find((building) => building.id === room.building_id)?.gender_policy
      if (!buildingAllowsTenantGender(roomBuildingPolicy, tenant?.gender)) return false
      if (keyword && !(room.room_number ?? '').toLowerCase().includes(keyword)) return false
      return true
    })
  }, [rooms, currentRoomId, roomKeyword, buildings, tenant?.gender])

  const buildingOptions = useMemo(
    () => [
      { value: '', label: 'Tất cả tòa nhà', tone: 'default' as const },
      ...buildings.map((building) => ({ value: String(building.id), label: building.name, tone: 'default' as const })),
    ],
    [buildings],
  )

  function pickRoom(room: AdminRoomResource) {
    setSelectedRoom(room)
    setStep(2)
    setSubmitError(null)
    setFieldErrors({})
  }

  function backToStep1() {
    setStep(1)
  }

  function updateMeterReadingRow(index: number, key: keyof MeterReadingRow, value: string) {
    setMeterReadingRows((rows) => rows.map((row, i) => (i === index ? { ...row, [key]: value } : row)))
  }


  async function handleSubmit() {
    if (!selectedRoom || !parsedTenantId) return

    setSubmitError(null)
    setFieldErrors({})

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
      deposit_settlement_amount: depositSettlementAmount ? Number(depositSettlementAmount) : undefined,
      deposit_deduction_amount: depositDeductionAmount ? Number(depositDeductionAmount) : undefined,
      deposit_refund_amount: depositRefundAmount ? Number(depositRefundAmount) : undefined,
      transfer_fee: transferFee ? Number(transferFee) : undefined,
      carry_vehicle_ids: carryVehicleIds.length ? carryVehicleIds : undefined,
    }

    try {
      setIsSubmitting(true)
      await transferTenantRoom(payload)
      navigate('/admin/tenants', {
        state: {
          transferSuccessMessage: `Đã chuyển ${tenant?.full_name || tenant?.username} sang phòng ${selectedRoom.room_number} thành công.`,
        },
      })
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

  if (isTenantLoading) {
    return (
      <div className="flex h-64 items-center justify-center text-[#8b5e34]">
        <Loader2 className="h-6 w-6 animate-spin" />
      </div>
    )
  }

  if (loadError || !tenant) {
    return (
      <div className="rounded-3xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-black text-rose-700">
        {loadError || 'Không tìm thấy khách thuê.'}
      </div>
    )
  }

  return (
    <section className="space-y-5 text-[#24170d] sm:space-y-6">
      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-4 text-[#fff4df] sm:p-5 lg:p-6">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_14%,rgba(243,197,107,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(15,118,110,0.26),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
          <div className="relative">
            <Link to="/admin/tenants" className="mb-2 inline-flex items-center gap-2 text-xs font-black text-[#f3c56b] transition hover:text-[#ffd56f]">
              <ArrowLeft className="h-3.5 w-3.5" /> Về danh sách khách thuê
            </Link>
            <h1 className="mt-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl">Chuyển phòng</h1>
            <p className="mt-2 text-sm font-bold text-[#f8e8c8]/80">
              {tenant.full_name || tenant.username} · đang ở {currentRoomNumber ? `phòng ${currentRoomNumber}` : 'chưa rõ phòng'}
              {currentBuildingName ? ` · ${currentBuildingName}` : ''}
            </p>
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
            <AdminSelect value={selectedBuildingId} options={buildingOptions} onChange={(value) => setSelectedBuildingId(String(value))} />
          </div>

          <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {isRoomsLoading &&
              Array.from({ length: 6 }).map((_, index) => (
                <div key={index} className="h-28 animate-pulse rounded-2xl bg-stone-100" />
              ))}

            {!isRoomsLoading && availableRooms.length === 0 && (
              <div className="col-span-full rounded-2xl border border-dashed border-[#3d2a18]/12 bg-white/55 px-6 py-10 text-center text-sm font-bold text-[#6f6254]">
                Không có phòng nào còn chỗ trống phù hợp.
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

          <FormSection title="Tiền cọc">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <Field label="Cọc bàn giao (settlement)" error={fieldErrors.deposit_settlement_amount}>
                <input type="number" min={0} value={depositSettlementAmount} onChange={(e) => setDepositSettlementAmount(e.target.value)} placeholder="0" className={inputClass} />
              </Field>
              <Field label="Trừ hư hỏng/nợ" error={fieldErrors.deposit_deduction_amount}>
                <input type="number" min={0} value={depositDeductionAmount} onChange={(e) => setDepositDeductionAmount(e.target.value)} placeholder="0" className={inputClass} />
              </Field>
              <Field label="Hoàn lại tiền mặt" error={fieldErrors.deposit_refund_amount}>
                <input type="number" min={0} value={depositRefundAmount} onChange={(e) => setDepositRefundAmount(e.target.value)} placeholder="0" className={inputClass} />
              </Field>
              <Field label="Phí chuyển phòng" error={fieldErrors.transfer_fee}>
                <input type="number" min={0} value={transferFee} onChange={(e) => setTransferFee(e.target.value)} placeholder="0" className={inputClass} />
              </Field>
            </div>
            <p className="mt-2 text-xs font-bold text-[#8b5e34]/70">
              Số cọc chuyển sang hợp đồng mới = Cọc bàn giao − Trừ hư hỏng − Hoàn lại. Để 0 nếu không phát sinh.
            </p>
          </FormSection>

          <FormSection
            title="Chỉ số điện/nước chốt sổ phòng cũ"
            hint="Admin tự đọc chỉ số trên đồng hồ rồi nhập meter_device_id + chỉ số tương ứng. Để trống nếu không cần chốt sổ điện/nước cho lần chuyển này."
          >
            {meterReadingRows.map((row, index) => {
              const meter = meterDevices.find((m) => String(m.id) === row.meterDeviceId)
              const label = meter ? `${meter.service_name} (${meter.meter_code ?? ''})` : ''
              return (
                <div key={index} className="mb-2 grid grid-cols-1 gap-2 sm:grid-cols-[1fr_1fr_auto]">
                  <input className={inputClass} value={label} disabled />
                  <input type="number" placeholder="Chỉ số hiện tại" value={row.currentReading} onChange={(e) => updateMeterReadingRow(index, 'currentReading', e.target.value)} className={inputClass} />
                </div>
              )
            })}
            
          </FormSection>


          <FormSection title="Phương tiện mang theo" hint="Chọn các phương tiện sẽ được chuyển sang phòng mới.">
              {vehicles.map((vehicle) => (
  <label key={vehicle.id}>
    <input
            type="checkbox"
          checked={carryVehicleIds.includes(vehicle.id)}
      onChange={(e) => {
        if (e.target.checked) {
          setCarryVehicleIds((prev) => [...prev, vehicle.id])
        } else {
          setCarryVehicleIds((prev) =>
            prev.filter((id) => id !== vehicle.id),
          )
        }
      }}
    />

    {vehicle.license_plate} ({vehicle.vehicle_type_label})
  </label>
))}
          </FormSection>

          <div className="flex items-center justify-end gap-3 pb-2">
            <button type="button" onClick={backToStep1} className="inline-flex h-11 items-center justify-center rounded-2xl border border-[#3d2a18]/15 bg-[#fffaf1] px-5 text-sm font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/10">
              Quay lại
            </button>
            <button
              type="button"
              disabled={isSubmitting}
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
