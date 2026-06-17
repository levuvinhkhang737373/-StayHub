export interface ExistingMeterReading {
  id: number
  current_reading: number
  consumption: number
  reading_date: string | null
  status: number
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
}
