import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { buildBuildingPayload, getTodayIsoDate } from '../src/features/admin/facilities/utils/building-form.utils.ts'
import type { BuildingFormValues } from '../src/features/admin/facilities/validations/building.validation.ts'

const createBuildingScreenSource = readFileSync(new URL('../src/features/admin/facilities/components/create-building-screen.tsx', import.meta.url), 'utf8')

test('building creation service prices default effective_from to creation date in payload', () => {
  const form: BuildingFormValues = {
    region_id: '1',
    manager_admin_id: '',
    name: 'Tòa nhà A',
    address: '123 Test',
    total_floors: 4,
    gender_policy: 1,
    description: '',
    status: 1,
    service_prices: [
      {
        service_id: '2',
        service_name: 'Điện',
        price: '1.000',
        effective_from: '',
        effective_to: '',
        status: 1,
      },
    ],
    settings: [],
  }

  const payload = buildBuildingPayload({
    form,
    imageFiles: [],
    visibleExistingImages: [],
    deleteImageIds: [],
    primaryImageId: null,
    primaryNewImageIndex: null,
    deleteServicePriceIds: [],
    deleteSettingIds: [],
  })

  assert.equal(payload.service_prices?.[0]?.effective_from, getTodayIsoDate())
})

test('building creation UI does not show effective date field for service prices', () => {
  assert.doesNotMatch(createBuildingScreenSource, /ReadOnlyField label="Hiệu lực từ"/)
  assert.doesNotMatch(createBuildingScreenSource, /formatDateForDisplay\(item\.effective_from/)
})
