import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { AlertTriangle, ArrowLeft, Camera, FileText, ImageIcon, Layers, Calendar, Droplet, Edit3, Loader2, RefreshCw, RotateCcw, Save, Sparkles, X, Zap } from 'lucide-react'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import { analyzeMeterImage, fetchMeterReadingsInit, saveMeterReading, bulkGenerateInvoices, generateSingleInvoice } from '../services/meter-readings.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import type { AnalyzeMeterImageResponse, RoomReadingInit, ServicePriceInit } from '../types/meter-readings.model'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { useAdminSocket } from '../../../../shared/lib/socket/socket-context'
import { AdminDateInput } from '../../../../shared/components/AdminDateInput'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { ImageViewerModal } from '../../../../shared/components/ImageViewerModal'

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'
const inputErrorClass = 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100'
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65'

const months = Array.from({ length: 12 }, (_, i) => ({ value: i + 1, label: `Tháng ${i + 1}` }))
const currentYear = new Date().getFullYear()
const years = Array.from({ length: 5 }, (_, i) => ({ value: currentYear - 2 + i, label: `Năm ${currentYear - 2 + i}` }))

type MeterKind = 'elec' | 'water'

type MeterFormState = {
  id: number
  prev: number
  curr: string
  price: number
  unit: string
  code: string
  imagePath: string | null
  imageUrl: string | null
  previewUrl: string | null
  aiReading: number | null
  confidence: AnalyzeMeterImageResponse['confidence']
  warning: string | null
  anomalyWarning: string | null
  imageError: string | null
  isAnalyzing: boolean
}

const imageErrorMessages: Record<string, string> = {
  image_blurry: 'Ảnh bị mờ, vui lòng bật flash và chụp lại rõ hơn',
  image_too_dark: 'Ảnh quá tối, vui lòng bật đèn flash hoặc di chuyển ra nơi sáng hơn',
  image_glare: 'Ảnh bị lóa sáng, vui lòng đổi góc chụp tránh phản chiếu',
  no_meter_found: 'Không tìm thấy đồng hồ trong ảnh, vui lòng chụp lại',
  meter_type_mismatch: 'Ảnh không đúng loại đồng hồ, vui lòng chụp đúng đồng hồ điện/nước',
  ai_service_unavailable: 'Dịch vụ AI tạm thời không khả dụng, vui lòng nhập tay',
  invalid_response: 'AI trả kết quả chưa hợp lệ, vui lòng nhập tay',
  invalid_image: 'Ảnh không hợp lệ, vui lòng chọn ảnh khác',
}

function getVisibleErrorMessage(error: unknown, fallback: string) {
  if (error instanceof ApiError) return error.message || fallback
  if (error instanceof Error) return error.message
  return fallback
}

function getAiImageErrorMessage(error: string | null | undefined) {
  if (!error) return null
  return imageErrorMessages[error] || 'Không thể đọc chỉ số từ ảnh, vui lòng nhập tay'
}

function buildMeterFormState(args: {
  id: number
  prev: number
  curr: string
  price: number
  unit: string
  code: string
  imagePath?: string | null
  imageUrl?: string | null
}): MeterFormState {
  return {
    id: args.id,
    prev: args.prev,
    curr: args.curr,
    price: args.price,
    unit: args.unit,
    code: args.code,
    imagePath: args.imagePath ?? null,
    imageUrl: args.imageUrl ?? null,
    previewUrl: args.imageUrl ?? null,
    aiReading: null,
    confidence: null,
    warning: null,
    anomalyWarning: null,
    imageError: null,
    isAnalyzing: false,
  }
}

export function MeterReadingsScreen() {
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [selectedBuildingId, setSelectedBuildingId] = useState('')
  const [selectedMonth, setSelectedMonth] = useState(new Date().getMonth() + 1)
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear())

  const [rooms, setRooms] = useState<RoomReadingInit[]>([])
  const [servicePrices, setServicePrices] = useState<ServicePriceInit[]>([])

  const [isLoading, setIsLoading] = useState(false)
  const [isSaving, setIsSaving] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [isGeneratingBulk, setIsGeneratingBulk] = useState(false)
  const [isGeneratingSingle, setIsGeneratingSingle] = useState<number | null>(null)
  const { echo } = useAdminSocket()

  // Modal State
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [activeRoom, setActiveRoom] = useState<RoomReadingInit | null>(null)
  const [previewImage, setPreviewImage] = useState<{ src: string; alt: string } | null>(null)

  // Readings Form State (separate fields for Electric and Water meters)
  const [elecMeter, setElecMeter] = useState<MeterFormState | null>(null)
  const [waterMeter, setWaterMeter] = useState<MeterFormState | null>(null)
  const [readingDate, setReadingDate] = useState(new Date().toISOString().split('T')[0])
  const [note, setNote] = useState('')
  const [formErrors, setFormErrors] = useState<{ elec?: string; water?: string; date?: string }>({})

  const loadBuildings = useCallback(async () => {
    try {
      const response = await fetchAdminBuildings({ per_page: 100 })
      const list = response.result || []
      setBuildings(Array.isArray(list) ? list : [])
      if (list.length > 0) {
        setSelectedBuildingId(String(list[0].id))
      }
    } catch (e) {
      console.error('Không thể tải danh sách tòa nhà', e)
    }
  }, [])

  const loadReadingsData = useCallback(async () => {
    if (!selectedBuildingId) return
    setIsLoading(true)
    setErrorMessage(null)
    try {
      const response = await fetchMeterReadingsInit({
        building_id: Number(selectedBuildingId),
        billing_month: selectedMonth,
        billing_year: selectedYear,
      })
      const result = response.result
      setRooms(result?.rooms || [])
      setServicePrices(result?.service_prices || [])
    } catch (e) {
      setErrorMessage(getVisibleErrorMessage(e, 'Không thể tải dữ liệu chốt số.'))
    } finally {
      setIsLoading(false)
    }
  }, [selectedBuildingId, selectedMonth, selectedYear])

  useEffect(() => {
    void loadBuildings()
  }, [loadBuildings])

  useEffect(() => {
    if (selectedBuildingId) {
      void loadReadingsData()
    }
  }, [selectedBuildingId, selectedMonth, selectedYear, loadReadingsData])


  useEffect(() => {
    if (echo && selectedBuildingId) {
      const channelName = `admin.invoices.building.${selectedBuildingId}`
      const channel = echo.channel(channelName)
      channel.listen('.BulkInvoiceGenerated', (e: any) => {
        setSuccessMessage(e.message)
        setIsGeneratingBulk(false)
        void loadReadingsData()
      })
      return () => {
        echo.leave(channelName)
      }
    }
  }, [echo, selectedBuildingId, loadReadingsData])


  useEffect(() => {
    if (successMessage) {
      const timer = setTimeout(() => setSuccessMessage(null), 5000)
      return () => clearTimeout(timer)
    }
  }, [successMessage])

  useEffect(() => {
    if (errorMessage) {
      const timer = setTimeout(() => setErrorMessage(null), 6000)
      return () => clearTimeout(timer)
    }
  }, [errorMessage])

  const rates = useMemo(() => {
    const electricPrice = servicePrices.find(p => p.slug.includes('electric') || p.slug.includes('dien'))
    const waterPrice = servicePrices.find(p => p.slug.includes('water') || p.slug.includes('nuoc'))
    return {
      electric: electricPrice ? electricPrice.price : 0,
      electricUnit: electricPrice ? electricPrice.unit_name || 'kWh' : 'kWh',
      water: waterPrice ? waterPrice.price : 0,
      waterUnit: waterPrice ? waterPrice.unit_name || 'm³' : 'm³',
    }
  }, [servicePrices])


  const handleBulkGenerate = async () => {
    if (isGeneratingBulk || !selectedBuildingId) return
    setIsGeneratingBulk(true)
    setErrorMessage(null)
    setSuccessMessage(null)
    try {
      await bulkGenerateInvoices({
        building_id: Number(selectedBuildingId),
        billing_month: selectedMonth,
        billing_year: selectedYear
      })
      // Success modal handled by socket mostly, but show a toast for queuing
      setSuccessMessage('Đang xếp hàng tạo hóa đơn hàng loạt, vui lòng đợi giây lát...')
    } catch (error) {
      setIsGeneratingBulk(false)
      setErrorMessage(getVisibleErrorMessage(error, 'Không thể tạo hóa đơn hàng loạt.'))
    }
  }

  const handleGenerateSingle = async (contractId: number) => {
    if (isGeneratingSingle === contractId) return
    setIsGeneratingSingle(contractId)
    setErrorMessage(null)
    setSuccessMessage(null)
    try {
      await generateSingleInvoice({
        contract_id: contractId,
        billing_month: selectedMonth,
        billing_year: selectedYear
      })
      setSuccessMessage('Tạo hóa đơn thành công.')
      await loadReadingsData()
    } catch (e) {
      setErrorMessage(getVisibleErrorMessage(e, 'Không thể tạo hóa đơn.'))
    } finally {
      setIsGeneratingSingle(null)
    }
  }


  const openReadingModal = (room: RoomReadingInit) => {
    setActiveRoom(room)
    setNote('')
    setReadingDate(new Date().toISOString().split('T')[0])
    setFormErrors({})

    const electricDevice = room.meters.find(m => m.meter_type === 1)
    const waterDevice = room.meters.find(m => m.meter_type === 2)

    if (electricDevice) {
      setElecMeter(buildMeterFormState({
        id: electricDevice.id,
        prev: electricDevice.previous_reading,
        curr: electricDevice.existing_reading ? String(electricDevice.existing_reading.current_reading) : '',
        price: rates.electric,
        unit: rates.electricUnit,
        code: electricDevice.meter_code || `#${electricDevice.id}`,
        imagePath: electricDevice.existing_reading?.image_path,
        imageUrl: electricDevice.existing_reading?.image_url,
      }))
      if (electricDevice.existing_reading && electricDevice.existing_reading.note) {
        setNote(electricDevice.existing_reading.note)
      }
      if (electricDevice.existing_reading && electricDevice.existing_reading.reading_date) {
        setReadingDate(electricDevice.existing_reading.reading_date)
      }
    } else {
      setElecMeter(null)
    }

    if (waterDevice) {
      setWaterMeter(buildMeterFormState({
        id: waterDevice.id,
        prev: waterDevice.previous_reading,
        curr: waterDevice.existing_reading ? String(waterDevice.existing_reading.current_reading) : '',
        price: rates.water,
        unit: rates.waterUnit,
        code: waterDevice.meter_code || `#${waterDevice.id}`,
        imagePath: waterDevice.existing_reading?.image_path,
        imageUrl: waterDevice.existing_reading?.image_url,
      }))
      if (waterDevice.existing_reading && waterDevice.existing_reading.note) {
        setNote(waterDevice.existing_reading.note)
      }
      if (waterDevice.existing_reading && waterDevice.existing_reading.reading_date) {
        setReadingDate(waterDevice.existing_reading.reading_date)
      }
    } else {
      setWaterMeter(null)
    }

    setIsModalOpen(true)
  }

  const updateMeterState = (kind: MeterKind, updater: (meter: MeterFormState) => MeterFormState) => {
    if (kind === 'elec') {
      setElecMeter(prev => prev ? updater(prev) : prev)
      return
    }

    setWaterMeter(prev => prev ? updater(prev) : prev)
  }

  const handleAnalyzeImage = async (kind: MeterKind, file: File | null) => {
    const meter = kind === 'elec' ? elecMeter : waterMeter
    if (!meter || !file) return

    const previewUrl = URL.createObjectURL(file)
    updateMeterState(kind, current => ({
      ...current,
      previewUrl,
      imageError: null,
      warning: null,
      anomalyWarning: null,
      isAnalyzing: true,
    }))

    setErrorMessage(null)

    try {
      const response = await analyzeMeterImage(file, kind === 'elec' ? 1 : 2, meter.prev)
      const result = response.result

      if (!result) {
        updateMeterState(kind, current => ({
          ...current,
          isAnalyzing: false,
          imageError: 'invalid_response',
        }))
        return
      }

      updateMeterState(kind, current => {
        const aiReading = result.success ? result.reading_value : null
        const canUseAiReading = aiReading !== null && aiReading >= current.prev
        const shouldClearAiReading = aiReading !== null && aiReading < current.prev
        const previousAiReading = current.aiReading !== null ? String(current.aiReading) : null

        return {
          ...current,
          curr: canUseAiReading
            ? String(aiReading)
            : (shouldClearAiReading && current.curr === previousAiReading ? '' : current.curr),
          imagePath: result.image_path ?? current.imagePath,
          imageUrl: result.image_url ?? current.imageUrl,
          previewUrl: result.image_url ?? previewUrl,
          aiReading,
          confidence: result.success ? result.confidence : null,
          warning: result.warning,
          anomalyWarning: result.anomaly_warning,
          imageError: result.success ? null : (result.error ?? 'invalid_response'),
          isAnalyzing: false,
        }
      })
    } catch (error) {
      updateMeterState(kind, current => ({
        ...current,
        isAnalyzing: false,
        imageError: error instanceof ApiError && error.statusCode === 422 ? 'invalid_image' : 'ai_service_unavailable',
      }))
    }
  }

  const handleSaveReadings = async () => {
    if (isSaving || elecMeter?.isAnalyzing || waterMeter?.isAnalyzing || !activeRoom) return

    const errors: { elec?: string; water?: string; date?: string } = {}
    if (!readingDate) {
      errors.date = 'Vui lòng chọn ngày chốt.'
    }

    const hasAnyInput = (elecMeter && elecMeter.curr.trim() !== '') || (waterMeter && waterMeter.curr.trim() !== '')

    if (!hasAnyInput) {
      if (elecMeter) {
        errors.elec = 'Vui lòng nhập chỉ số điện mới.'
      }
      if (waterMeter && !elecMeter) {
        errors.water = 'Vui lòng nhập chỉ số nước mới.'
      }
    } else {
      if (elecMeter && elecMeter.curr.trim() !== '') {
        const val = Number(elecMeter.curr)
        if (isNaN(val) || val < 0) {
          errors.elec = 'Chỉ số điện hiện tại không hợp lệ.'
        } else if (val < elecMeter.prev) {
          errors.elec = `Chỉ số mới không được nhỏ hơn chỉ số cũ (${elecMeter.prev}).`
        }
      }

      if (waterMeter && waterMeter.curr.trim() !== '') {
        const val = Number(waterMeter.curr)
        if (isNaN(val) || val < 0) {
          errors.water = 'Chỉ số nước hiện tại không hợp lệ.'
        } else if (val < waterMeter.prev) {
          errors.water = `Chỉ số mới không được nhỏ hơn chỉ số cũ (${waterMeter.prev}).`
        }
      }
    }

    setFormErrors(errors)
    if (Object.keys(errors).length > 0) return

    setIsSaving(true)
    setErrorMessage(null)
    setSuccessMessage(null)

    try {
      if (elecMeter && elecMeter.curr.trim() !== '') {
        await saveMeterReading({
          meter_device_id: elecMeter.id,
          billing_month: selectedMonth,
          billing_year: selectedYear,
          current_reading: Number(elecMeter.curr),
          reading_date: readingDate,
          note: note || undefined,
          image_path: elecMeter.imagePath || undefined,
        })
      }

      if (waterMeter && waterMeter.curr.trim() !== '') {
        await saveMeterReading({
          meter_device_id: waterMeter.id,
          billing_month: selectedMonth,
          billing_year: selectedYear,
          current_reading: Number(waterMeter.curr),
          reading_date: readingDate,
          note: note || undefined,
          image_path: waterMeter.imagePath || undefined,
        })
      }

      setSuccessMessage(`Đã chốt chỉ số điện nước thành công cho phòng ${activeRoom.room_number}.`)
      setIsModalOpen(false)
      setActiveRoom(null)
      await loadReadingsData()
    } catch (e) {
      setErrorMessage(getVisibleErrorMessage(e, 'Không thể lưu chốt số điện nước.'))
    } finally {
      setIsSaving(false)
    }
  }

  const calculateCost = (currStr: string, prev: number, price: number) => {
    const curr = Number(currStr)
    if (isNaN(curr) || curr < prev) return 0
    return (curr - prev) * price
  }

  const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
  }

  const renderMeterPanel = (kind: MeterKind, meter: MeterFormState) => {
    const isElectric = kind === 'elec'
    const fieldError = isElectric ? formErrors.elec : formErrors.water
    const accent = isElectric
      ? {
        icon: <Zap className="h-4 w-4" />,
        title: 'Đồng hồ Điện',
        text: 'text-[#8a4f18]',
        ring: 'focus:ring-[#f3c56b]/20 focus:border-[#f3c56b]',
        soft: 'bg-[#f3c56b]/10 text-[#8b5e34] border-[#f3c56b]/25',
        usageUnit: 'kWh',
      }
      : {
        icon: <Droplet className="h-4 w-4" />,
        title: 'Đồng hồ Nước',
        text: 'text-cyan-800',
        ring: 'focus:ring-cyan-200 focus:border-cyan-400',
        soft: 'bg-cyan-50/80 text-cyan-900 border-cyan-100',
        usageUnit: 'm³',
      }
    const imageError = getAiImageErrorMessage(meter.imageError)
    const hasValidReading = meter.curr && !isNaN(Number(meter.curr)) && Number(meter.curr) >= meter.prev

    return (
      <div className="rounded-[1.35rem] border border-[#3d2a18]/8 bg-white/60 p-4 shadow-sm shadow-[#3d2a18]/5 space-y-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div className={cn('flex items-center gap-1.5 text-xs font-black uppercase tracking-wider', accent.text)}>
            {accent.icon}
            <span>{accent.title} (Mã: {meter.code})</span>
          </div>
          {meter.aiReading !== null && (
            <span className="inline-flex w-fit items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-700">
              <Sparkles className="h-3 w-3" /> AI đã đọc: {meter.aiReading}
            </span>
          )}
        </div>

        <div className="grid gap-4 lg:grid-cols-[180px_1fr]">
          <div className="relative min-h-40 overflow-hidden rounded-[1.35rem] border border-dashed border-[#3d2a18]/15 bg-[#fff7e8]">
            {meter.previewUrl ? (
              <button
                type="button"
                onClick={() => setPreviewImage({ src: meter.imageUrl || meter.previewUrl || '', alt: `Ảnh ${accent.title.toLowerCase()}` })}
                className="group block h-full min-h-40 w-full overflow-hidden text-left focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/30"
                title="Bấm để xem ảnh lớn"
              >
                <img src={meter.previewUrl} alt={`Ảnh ${accent.title.toLowerCase()}`} className="h-full min-h-40 w-full object-cover transition duration-300 group-hover:scale-105" />
                <span className="absolute inset-x-3 bottom-3 rounded-full bg-[#24170d]/78 px-3 py-1.5 text-center text-[10px] font-black uppercase tracking-wider text-[#fff4df] opacity-0 backdrop-blur transition group-hover:opacity-100">
                  Bấm để xem ảnh lớn
                </span>
              </button>
            ) : (
              <div className="flex h-full min-h-40 flex-col items-center justify-center gap-2 px-3 text-center text-[#8b5e34]/60">
                <ImageIcon className="h-9 w-9" />
                <span className="text-[10px] font-black uppercase tracking-wider">Chưa có ảnh</span>
              </div>
            )}
            {meter.isAnalyzing && (
              <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-[#24170d]/72 text-[#fff4df] backdrop-blur-sm">
                <Loader2 className="h-6 w-6 animate-spin text-[#f3c56b]" />
                <span className="px-3 text-center text-[10px] font-black uppercase tracking-wider">AI đang phân tích ảnh...</span>
              </div>
            )}
          </div>

          <div className="space-y-3">
            <input
              id={`${kind}-meter-image-input`}
              type="file"
              accept="image/*"
              capture="environment"
              className="hidden"
              disabled={meter.isAnalyzing || isSaving}
              onChange={(event) => {
                const file = event.target.files?.[0] || null
                event.target.value = ''
                void handleAnalyzeImage(kind, file)
              }}
            />
            <label
              htmlFor={`${kind}-meter-image-input`}
              className={cn(
                'inline-flex min-h-10 w-full cursor-pointer items-center justify-center gap-2 rounded-xl border px-3 text-[11px] font-black uppercase tracking-wider transition active:scale-[0.98]',
                meter.isAnalyzing || isSaving
                  ? 'pointer-events-none border-[#3d2a18]/10 bg-stone-100 text-stone-400'
                  : 'border-[#24170d]/10 bg-[#24170d] text-[#fff4df] hover:bg-[#3d2a18]'
              )}
            >
              {meter.imageError ? <RotateCcw className="h-3.5 w-3.5 text-[#f3c56b]" /> : <Camera className="h-3.5 w-3.5 text-[#f3c56b]" />}
              {meter.imageError ? 'Chụp lại' : 'Chụp ảnh đồng hồ'}
            </label>
          </div>
        </div>

        {imageError && (
          <div className="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold leading-5 text-rose-700">
            {imageError}
          </div>
        )}
        {meter.confidence === 'low' && (
          <div className="flex gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold leading-5 text-amber-800">
            <AlertTriangle className="mt-0.5 h-4 w-4 flex-none" /> AI không chắc chắn, vui lòng kiểm tra lại số
          </div>
        )}
        {meter.anomalyWarning && (
          <div className="flex gap-2 rounded-xl border border-orange-200 bg-orange-50 px-3 py-2 text-xs font-bold leading-5 text-orange-800">
            <AlertTriangle className="mt-0.5 h-4 w-4 flex-none" /> {meter.anomalyWarning}
          </div>
        )}
        {meter.warning && (
          <div className="rounded-xl border border-[#3d2a18]/10 bg-[#fff7e8] px-3 py-2 text-xs font-bold leading-5 text-[#8b5e34]">
            {meter.warning}
          </div>
        )}

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className={labelClass}>Chỉ số cũ</label>
            <input type="text" readOnly className={cn(inputClass, 'bg-[#efe2cf]/45 opacity-75')} value={meter.prev} />
          </div>
          <div>
            <label className={labelClass}>Chỉ số mới</label>
            <input
              type="number"
              min={0}
              step="any"
              placeholder="Nhập số mới"
              className={cn(inputClass, accent.ring, fieldError && inputErrorClass)}
              value={meter.curr}
              onChange={(event) => {
                const nextValue = event.target.value
                const numericValue = Number(nextValue)

                if (nextValue !== '' && !isNaN(numericValue) && numericValue < meter.prev) {
                  setFormErrors(prev => ({
                    ...prev,
                    [kind]: `Chỉ số mới không được nhỏ hơn chỉ số cũ (${meter.prev}).`,
                  }))
                  return
                }

                updateMeterState(kind, current => ({ ...current, curr: nextValue }))
                setFormErrors(prev => ({ ...prev, [kind]: undefined }))
              }}
            />
          </div>
        </div>

        {fieldError && <p className="text-xs font-bold text-rose-600">{fieldError}</p>}
        {hasValidReading && (
          <div className={cn('rounded-xl border p-2.5 text-xs font-bold', accent.soft)}>
            Sử dụng: <span className="font-black text-[#24170d]">{Number(meter.curr) - meter.prev} {accent.usageUnit}</span>
            <span className="mx-2">•</span>
            Thành tiền: <span className="font-black text-[#24170d]">{formatCurrency(calculateCost(meter.curr, meter.prev, meter.price))}</span>
          </div>
        )}
      </div>
    )
  }

  return (
    <>
      <section className="space-y-5 sm:space-y-6 text-[#24170d]">
        {/* Gradient Banner Header */}
        <section className="relative rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
          <div className="relative p-4 text-[#fff4df] sm:p-5 lg:p-6">
            <div className="pointer-events-none absolute inset-0 rounded-[2rem] bg-[radial-gradient(circle_at_18%_14%,rgba(243,197,107,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(15,118,110,0.26),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
            <div className="pointer-events-none absolute inset-x-6 bottom-0 h-px bg-gradient-to-r from-transparent via-[#f3c56b]/40 to-transparent" />

            <div className="relative flex min-w-0 flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
              <div className="min-w-0">
                <Link to="/admin/dashboard" className="mb-2 inline-flex items-center gap-2 text-xs font-black text-[#f3c56b] transition hover:text-[#ffd56f]">
                  <ArrowLeft className="h-3.5 w-3.5" /> Về dashboard
                </Link>

                <h1 className="mt-3 max-w-3xl text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem]">Chốt điện nước</h1>
              </div>

              {/* Filters / Cycle Selection */}
              <div className="relative flex flex-wrap items-center gap-3 w-full sm:w-auto">
                <div className="w-full sm:w-72">
                  <AdminSelect
                    value={selectedBuildingId}
                    options={buildings.map((b) => ({ value: b.id, label: b.name }))}
                    onChange={(val) => setSelectedBuildingId(String(val))}
                    placeholder="Chọn tòa nhà"
                  />
                </div>
                <div className="w-[calc(50%-0.75rem)] sm:w-36 flex-1">
                  <AdminSelect
                    value={selectedMonth}
                    options={months}
                    onChange={(val) => setSelectedMonth(Number(val))}
                  />
                </div>
                <div className="w-[calc(50%-0.75rem)] sm:w-36 flex-1">
                  <AdminSelect
                    value={selectedYear}
                    options={years}
                    onChange={(val) => setSelectedYear(Number(val))}
                  />
                </div>

                <button
                  type="button"
                  onClick={() => void handleBulkGenerate()}
                  disabled={isGeneratingBulk}
                  className="inline-flex h-12 px-4 shrink-0 items-center justify-center gap-2 rounded-2xl border border-emerald-400/30 bg-emerald-500/20 text-emerald-100 font-bold backdrop-blur-md transition hover:bg-emerald-500/30 disabled:opacity-50"
                >
                  {isGeneratingBulk ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Layers className="h-4 w-4" />}
                  <span className="hidden sm:inline">Tạo tất cả Hóa đơn</span>
                </button>

                <button
                  type="button"
                  onClick={() => void loadReadingsData()}
                  className="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-white/10 bg-white/10 text-white backdrop-blur-md transition hover:bg-white/20"
                  title="Làm mới"
                >
                  <RefreshCw className="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>
        </section>

        {/* Pricing Info Alerts */}
        {selectedBuildingId && servicePrices.length > 0 && (
          <div className="rounded-[1.75rem] border border-[#3d2a18]/8 bg-[#fffaf1]/80 p-4 sm:p-5 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between shadow-sm">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
              <span className="text-xs font-black uppercase tracking-wider text-[#8b5e34]/80 shrink-0">Đơn giá dịch vụ:</span>
              <div className="flex flex-wrap gap-2.5">
                {/* Electric Price Badge */}
                <div className="inline-flex items-center gap-2 rounded-2xl border border-[#f3c56b]/35 bg-[#f3c56b]/8 px-3.5 py-2 text-sm font-black text-[#8a4f18]">
                  <Zap className="h-4 w-4 text-[#a65f16] fill-[#f3c56b]/35" />
                  <span>Điện: <span className="text-[#24170d] text-base">{formatCurrency(rates.electric)}</span> / {rates.electricUnit}</span>
                </div>
                {/* Water Price Badge */}
                <div className="inline-flex items-center gap-2 rounded-2xl border border-cyan-200 bg-cyan-50/50 px-3.5 py-2 text-sm font-black text-cyan-800">
                  <Droplet className="h-4 w-4 text-cyan-600 fill-cyan-500/10" />
                  <span>Nước: <span className="text-cyan-950 text-base">{formatCurrency(rates.water)}</span> / {rates.waterUnit}</span>
                </div>
              </div>
            </div>
            <div className="flex items-center gap-2 rounded-xl bg-[#24170d]/5 px-3 py-1.5 self-start lg:self-auto">
              <Calendar className="h-3.5 w-3.5 text-[#8b5e34]" />
              <span className="text-xs font-black text-[#8b5e34]">Kỳ thanh toán: {selectedMonth}/{selectedYear}</span>
            </div>
          </div>
        )}

        {/* Status Alerts */}
        {(successMessage || errorMessage) && (
          <div
            className={cn(
              'relative rounded-3xl border px-5 py-3.5 pr-12 text-sm font-black shadow-sm transition-all duration-300 flex items-center justify-between',
              errorMessage ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'
            )}
          >
            <span>{errorMessage || successMessage}</span>
            <button
              type="button"
              onClick={() => {
                setSuccessMessage(null)
                setErrorMessage(null)
              }}
              className={cn(
                'absolute right-3 top-1/2 -translate-y-1/2 flex h-8 w-8 items-center justify-center rounded-full transition-colors',
                errorMessage ? 'text-rose-400 hover:bg-rose-100 hover:text-rose-600' : 'text-emerald-400 hover:bg-emerald-100 hover:text-emerald-600'
              )}
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        )}

        {/* Room Readings Sheets */}
        <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
          <div className="overflow-x-auto">
            <table className="w-full min-w-[980px] text-left">
              <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
                <tr>
                  <th className="px-5 py-4 w-[20%]">Phòng</th>
                  <th className="px-5 py-4 w-[28%]">Chỉ số Điện (kWh)</th>
                  <th className="px-5 py-4 w-[28%]">Chỉ số Nước (m³)</th>
                  <th className="px-5 py-4 w-[12%] text-right">Tổng thành tiền</th>
                  <th className="px-5 py-4 text-center w-[16%]">Thao tác</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[#3d2a18]/8 bg-[#fffaf1]/70">
                {isLoading && Array.from({ length: 4 }).map((_, index) => (
                  <tr key={index}>
                    <td colSpan={5} className="px-5 py-4">
                      <div className="h-16 animate-pulse rounded-2xl bg-stone-100" />
                    </td>
                  </tr>
                ))}

                {!isLoading && rooms.map((room) => {
                  const elec = room.meters.find(m => m.meter_type === 1)
                  const water = room.meters.find(m => m.meter_type === 2)
                  const isChotElec = !!elec?.existing_reading
                  const isChotWater = !!water?.existing_reading

                  const elecCost = elec?.existing_reading ? elec.existing_reading.consumption * rates.electric : 0
                  const waterCost = water?.existing_reading ? water.existing_reading.consumption * rates.water : 0
                  const totalUtilityCost = elecCost + waterCost

                  return (
                    <tr key={room.room_id} className="group transition hover:bg-[#f3c56b]/10">
                      <td className="px-5 py-4">
                        <span className="text-sm font-black text-[#24170d]">Phòng {room.room_number}</span>
                      </td>

                      {/* Electricity Detail Column */}
                      <td className="px-5 py-4">
                        {elec ? (
                          <div className="text-xs space-y-1">
                            <div className="flex items-center gap-1.5 font-bold text-[#8a4f18]">
                              <Zap className="h-3.5 w-3.5 fill-[#f3c56b]/10" />
                              <span>Mã: {elec.meter_code || `#${elec.id}`}</span>
                            </div>
                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1 font-semibold text-[#6f6254]">
                              <span>Cũ: <span className="font-bold text-[#24170d]">{elec.previous_reading}</span></span>
                              {isChotElec ? (
                                <>
                                  <span className="text-[#3d2a18]/25">•</span>
                                  <span>Mới: <span className="font-bold text-[#24170d]">{elec.existing_reading?.current_reading}</span></span>
                                  <span className="text-[#3d2a18]/25">•</span>
                                  <span>Dùng: <span className="font-black text-[#a65f16]">{elec.existing_reading?.consumption} kWh</span></span>
                                </>
                              ) : (
                                <span className="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-rose-600 border border-rose-100">
                                  Chưa chốt
                                </span>
                              )}
                            </div>
                            {isChotElec && (
                              <p className="font-bold text-[#8b5e34]/70">Thành tiền: {formatCurrency(elecCost)}</p>
                            )}
                          </div>
                        ) : (
                          <span className="text-xs text-stone-400 italic">Không có công tơ</span>
                        )}
                      </td>

                      {/* Water Detail Column */}
                      <td className="px-5 py-4">
                        {water ? (
                          <div className="text-xs space-y-1">
                            <div className="flex items-center gap-1.5 font-bold text-cyan-700">
                              <Droplet className="h-3.5 w-3.5 fill-cyan-500/10" />
                              <span>Mã: {water.meter_code || `#${water.id}`}</span>
                            </div>
                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1 font-semibold text-[#6f6254]">
                              <span>Cũ: <span className="font-bold text-[#24170d]">{water.previous_reading}</span></span>
                              {isChotWater ? (
                                <>
                                  <span className="text-[#3d2a18]/25">•</span>
                                  <span>Mới: <span className="font-bold text-[#24170d]">{water.existing_reading?.current_reading}</span></span>
                                  <span className="text-[#3d2a18]/25">•</span>
                                  <span>Dùng: <span className="font-black text-cyan-850">{water.existing_reading?.consumption} m³</span></span>
                                </>
                              ) : (
                                <span className="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-rose-600 border border-rose-100">
                                  Chưa chốt
                                </span>
                              )}
                            </div>
                            {isChotWater && (
                              <p className="font-bold text-[#8b5e34]/70">Thành tiền: {formatCurrency(waterCost)}</p>
                            )}
                          </div>
                        ) : (
                          <span className="text-xs text-stone-400 italic">Không có công tơ</span>
                        )}
                      </td>

                      {/* Cost Summary Column */}
                      <td className="px-5 py-4 text-right">
                        {isChotElec || isChotWater ? (
                          <span className="text-sm font-black text-[#24170d] tabular-nums">
                            {formatCurrency(totalUtilityCost)}
                          </span>
                        ) : (
                          <span className="text-xs text-stone-400">-</span>
                        )}
                      </td>

                      {/* Actions Column */}
                      <td className="px-5 py-4 text-center">
                        <div className="flex flex-col sm:flex-row items-center justify-center gap-1.5">
                          <button
                            type="button"
                            onClick={() => openReadingModal(room)}
                            disabled={room.meters.length === 0}
                            className={cn(
                              'inline-flex h-9 w-full sm:w-auto items-center justify-center gap-1.5 rounded-xl px-3.5 text-[11px] font-black transition-all active:scale-[0.98] disabled:opacity-40 disabled:cursor-not-allowed',
                              (isChotElec && isChotWater)
                                ? 'border border-[#3d2a18]/10 bg-white/80 text-[#8b5e34] hover:bg-[#f3c56b]/15'
                                : 'bg-[#24170d] text-[#fff4df] hover:bg-[#3d2a18] shadow-sm shadow-[#24170d]/10'
                            )}
                          >
                            {(isChotElec && isChotWater) ? (
                              <>
                                <Edit3 className="h-3.5 w-3.5" /> Sửa số
                              </>
                            ) : (
                              <>
                                <Calendar className="h-3.5 w-3.5" /> Ghi số
                              </>
                            )}
                          </button>

                          <button
                            type="button"
                            onClick={() => {
                              if (!room.contract_id) return;
                              void handleGenerateSingle(room.contract_id)
                            }}
                            disabled={!room.contract_id || isGeneratingSingle === room.contract_id || (elec && !isChotElec) || (water && !isChotWater)}
                            title={!room.contract_id ? 'Phòng trống chưa có hợp đồng' : (elec && !isChotElec) || (water && !isChotWater) ? 'Cần chốt điện nước' : 'Tạo hóa đơn'}
                            className="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl px-3.5 text-[11px] font-black transition-all active:scale-[0.98] disabled:opacity-40 disabled:cursor-not-allowed border border-emerald-600/20 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 shadow-sm shadow-emerald-900/5 mt-1 sm:mt-0 sm:ml-2"
                          >
                            {isGeneratingSingle === room.contract_id && room.contract_id ? (
                              <RefreshCw className="h-3.5 w-3.5 animate-spin" />
                            ) : (
                              <FileText className="h-3.5 w-3.5" />
                            )}
                            Tạo HĐ
                          </button>

                        </div>
                      </td>
                    </tr>
                  )
                })}

                {!isLoading && rooms.length === 0 && (
                  <tr>
                    <td colSpan={5} className="px-5 py-20 text-center">
                      <div className="mx-auto flex max-w-sm flex-col items-center rounded-[2rem] border border-dashed border-[#3d2a18]/12 bg-white/55 px-6 py-8">
                        <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]"><Zap className="h-9 w-9" /></div>
                        <p className="text-lg font-black tracking-tight text-[#24170d]">Không tìm thấy phòng</p>
                        <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">Vui lòng kiểm tra lại tòa nhà được chọn hoặc chính sách hoạt động.</p>
                      </div>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </section>
      </section>

      {/* Recording Dialog */}
      {isModalOpen && activeRoom && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="reading-dialog-title">
          <button type="button" onClick={() => setIsModalOpen(false)} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />

          <div className="relative z-10 flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-[2.25rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-stone-950/30">
            {/* Modal Header */}
            <div className="bg-[#24170d] px-6 py-5 text-[#fff4df] sm:px-8">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-[10px] font-black uppercase tracking-[0.24em] text-[#f3c56b]">Utility Record</p>
                  <h2 id="reading-dialog-title" className="mt-1.5 text-2xl font-black tracking-tight sm:text-3xl">Chốt chỉ số - Phòng {activeRoom.room_number}</h2>
                  <p className="mt-1 text-xs font-bold text-[#f8e8c8]/70">Khách thuê đại diện: {activeRoom.tenant_name || 'Không có (Phòng trống)'}</p>
                </div>
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>
            </div>

            {/* Modal Body */}
            <div className="flex-1 overflow-y-auto p-5 sm:p-6 lg:p-7">
              <div className="grid gap-5 lg:grid-cols-[1fr_320px] lg:items-start">
                <div className="space-y-5">
                  {/* Electric Meter section */}
                  {elecMeter && renderMeterPanel('elec', elecMeter)}

                  {/* Water Meter section */}
                  {waterMeter && renderMeterPanel('water', waterMeter)}
                </div>

                <aside className="space-y-4 rounded-[1.75rem] border border-[#3d2a18]/10 bg-white/70 p-4 shadow-sm shadow-[#3d2a18]/5 lg:sticky lg:top-0">
                  {/* Reading Date */}
                  <div>
                    <label className={labelClass}>Ngày ghi nhận số liệu</label>
                    <AdminDateInput
                      className={cn(inputClass, formErrors.date && inputErrorClass)}
                      value={readingDate}
                      onChange={(val) => {
                        setReadingDate(val)
                        setFormErrors(prev => ({ ...prev, date: undefined }))
                      }}
                    />
                    {formErrors.date && <p className="mt-2 text-xs font-bold text-rose-600">{formErrors.date}</p>}
                  </div>

                  {/* Note */}
                  <div>
                    <label className={labelClass}>Ghi chú</label>
                    <textarea
                      className={cn(inputClass, 'min-h-[110px] resize-none')}
                      value={note}
                      onChange={(e) => setNote(e.target.value)}
                      placeholder="Ghi chú thêm về kỳ chốt này..."
                    />
                  </div>

                  {/* Invoice Calculations Summary Info */}
                  {(elecMeter?.curr || waterMeter?.curr) && (
                    <div className="rounded-2xl border border-[#3d2a18]/10 bg-[#24170d] p-4 text-[#fff4df] shadow-md shadow-[#24170d]/10">
                      <p className="text-[10px] font-black uppercase tracking-wider text-[#f3c56b]">Dự báo tiền dịch vụ</p>
                      <p className="mt-1 text-2xl font-black tracking-tight">
                        {formatCurrency(
                          calculateCost(elecMeter?.curr || '0', elecMeter?.prev || 0, elecMeter?.price || 0) +
                          calculateCost(waterMeter?.curr || '0', waterMeter?.prev || 0, waterMeter?.price || 0)
                        )}
                      </p>
                    </div>
                  )}
                </aside>
              </div>
            </div>

            {/* Modal Footer */}
            <div className="flex flex-col gap-2 border-t border-[#3d2a18]/10 bg-[#fff7e8]/80 px-5 py-4 sm:flex-row sm:items-center sm:justify-end sm:px-7">
              <button
                type="button"
                onClick={() => setIsModalOpen(false)}
                disabled={isSaving || elecMeter?.isAnalyzing || waterMeter?.isAnalyzing}
                className="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] px-5 text-xs font-black uppercase tracking-wider text-[#6f6254] hover:bg-[#efe2cf] active:scale-95 disabled:opacity-50"
              >
                Hủy
              </button>
              <button
                type="button"
                onClick={() => void handleSaveReadings()}
                disabled={isSaving || elecMeter?.isAnalyzing || waterMeter?.isAnalyzing}
                className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#24170d] px-5 text-xs font-black uppercase tracking-wider text-[#fff4df] hover:bg-[#3d2a18] shadow-md shadow-[#24170d]/10 active:scale-95 disabled:opacity-50"
              >
                <Save className="h-4 w-4 text-[#f3c56b] stroke-[2.8]" />
                {elecMeter?.isAnalyzing || waterMeter?.isAnalyzing ? 'Đang đọc ảnh...' : isSaving ? 'Đang lưu...' : 'Lưu chốt số'}
              </button>
            </div>
          </div>
        </div>
      )}
      <ImageViewerModal
        isOpen={!!previewImage}
        src={previewImage?.src ?? null}
        alt={previewImage?.alt ?? 'Ảnh đồng hồ'}
        onClose={() => setPreviewImage(null)}
      />
    </>
  )
}
