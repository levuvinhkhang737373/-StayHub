import { apiRequest } from "../../../../shared/lib/api/api-client"
import { compressImage } from '../../../../shared/lib/utils/compress-image'
import type { AnalyzeMeterImageResponse, MeterReadingsInitResponse, SaveMeterReadingPayload } from '../types/meter-readings.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, String(value))
  })
  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchMeterReadingsInit(params: {
  building_id: number
  billing_month: number
  billing_year: number
}) {
  return apiRequest<MeterReadingsInitResponse>({
    url: `admin/meter-readings/init${buildQuery(params)}`,
    method: 'GET',
  })
}

export async function saveMeterReading(payload: SaveMeterReadingPayload) {
  return apiRequest<any>({
    url: 'admin/meter-readings',
    method: 'POST',
    data: payload,
  })
}

export async function analyzeMeterImage(
  file: File,
  meterType: number,
  previousReading?: number,
  oldImagePath?: string | null,
) {
  const compressedFile = await compressImage(file)
  const formData = new FormData()
  formData.append('image', compressedFile)
  formData.append('meter_type', String(meterType))

  if (previousReading !== undefined && previousReading !== null) {
    formData.append('previous_reading', String(previousReading))
  }

  // Truyền ảnh cũ lên để backend tự xóa khi người dùng chụp lại
  if (oldImagePath) {
    formData.append('old_image_path', oldImagePath)
  }

  return apiRequest<AnalyzeMeterImageResponse>({
    url: 'admin/meter-readings/analyze-image',
    method: 'POST',
    data: formData,
  })
}

export async function bulkGenerateInvoices(payload: {
  building_id: number
  billing_month: number
  billing_year: number
}) {
  return apiRequest<any>({
    url: `admin/buildings/${payload.building_id}/invoices/bulk-generate`,
    method: 'POST',
    data: {
      building_id: payload.building_id,
      billing_month: payload.billing_month,
      billing_year: payload.billing_year
    },
  })
}

export async function updateUtilityPrices(buildingId: number, payload: {
  electric_price: number
  water_price: number
  billing_month: number
  billing_year: number
}) {
  return apiRequest<any>({
    url: `admin/buildings/${buildingId}/utility-prices`,
    method: 'PUT',
    data: payload,
  })
}

export async function fetchUtilityPriceHistory(buildingId: number) {
  return apiRequest<any[]>({
    url: `admin/buildings/${buildingId}/utility-price-history`,
    method: 'GET',
  })
}
