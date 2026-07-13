import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const source = readFileSync(new URL('../src/features/admin/meter-readings/components/meter-readings-screen.tsx', import.meta.url), 'utf8')

test('utility price save redirects to clean meter readings page after success', () => {
  const savePricesStart = source.indexOf('const handleSavePrices = async () => {')
  assert.notEqual(savePricesStart, -1)

  const savePricesEnd = source.indexOf('\n  const calculateCost', savePricesStart)
  assert.notEqual(savePricesEnd, -1)

  const savePricesSource = source.slice(savePricesStart, savePricesEnd)

  assert.match(savePricesSource, /await updateUtilityPrices/)
  assert.match(savePricesSource, /navigate\('\/admin\/meter-readings', \{ replace: true \}\)/)
})

test('utility price api errors stay visible inside price popup', () => {
  const savePricesStart = source.indexOf('const handleSavePrices = async () => {')
  assert.notEqual(savePricesStart, -1)

  const savePricesEnd = source.indexOf('\n  const calculateCost', savePricesStart)
  assert.notEqual(savePricesEnd, -1)

  const savePricesSource = source.slice(savePricesStart, savePricesEnd)

  assert.match(savePricesSource, /setPriceFormErrors\(\{ general: getVisibleErrorMessage\(e, 'Không thể cập nhật đơn giá dịch vụ\.'\) \}\)/)
  assert.doesNotMatch(savePricesSource, /catch \(e\) \{\s*setErrorMessage\(getVisibleErrorMessage\(e, 'Không thể cập nhật đơn giá dịch vụ\.'\)\)/)
  assert.match(source, /priceFormErrors\.general &&/)
})

test('meter reading save payload includes active room contract id', () => {
  const saveReadingsStart = source.indexOf('const handleSaveReadings = async () => {')
  assert.notEqual(saveReadingsStart, -1)

  const saveReadingsEnd = source.indexOf('\n  const handleOpenPriceHistoryModal', saveReadingsStart)
  assert.notEqual(saveReadingsEnd, -1)

  const saveReadingsSource = source.slice(saveReadingsStart, saveReadingsEnd)

  assert.match(saveReadingsSource, /contract_id: activeRoom\.contract_id \?\? undefined/)
})
