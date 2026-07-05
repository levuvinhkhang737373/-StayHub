export interface ExistingMeterReading {
  id: number
  current_reading: number
  consumption: number
  reading_date: string | null
  status: number
  image_path: string | null
  image_url: string | null
  note: string | null
}

export interface MeterDeviceReadingInit {
  id: number
  meter_code: string | null
  meter_type: number
  service_id: number
  service_name: string
  previous_reading: number
  existing_reading: ExistingMeterReading | null
}

export interface RoomReadingInit {
  room_id: number
  room_number: string
  tenant_name: string | null
  contract_id: number | null
  contract_code?: string | null
  contract_status?: number | null
  is_transfer_finalization?: boolean
  should_finalize_before_transfer?: boolean
  transfer_code?: string | null
  movement_date?: string | null
  utility_cutoff_date?: string | null
  cutoff_reason?: string | null
  meters: MeterDeviceReadingInit[]
}

export interface ServicePriceInit {
  service_id: number
  name: string
  slug: string
  price: number
  unit_name: string | null
}

export interface MeterReadingsInitResponse {
  rooms: RoomReadingInit[]
  service_prices: ServicePriceInit[]
}

export interface SaveMeterReadingPayload {
  meter_device_id: number
  billing_month: number
  billing_year: number
  current_reading: number
  reading_date: string
  note?: string
  image_path?: string
}

export interface MeterReadingUncertainDigit {
  position: number | null
  lower_digit: number | null
  upper_digit: number | null
  chosen_digit: number | null
  note: string | null
}

export interface AnalyzeMeterImageResponse {
  success: boolean
  reading_value: number | null
  confidence: 'high' | 'medium' | 'low' | null
  warning: string | null
  anomaly_warning: string | null
  uncertain_digits: MeterReadingUncertainDigit[]
  error: 'image_blurry' | 'image_too_dark' | 'image_glare' | 'no_meter_found' | 'meter_type_mismatch' | 'ai_service_unavailable' | 'invalid_response' | 'invalid_image' | string | null
  image_path: string | null
  image_url: string | null
}
