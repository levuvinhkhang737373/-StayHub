import test from 'node:test'
import assert from 'node:assert/strict'
import { getDashboardMonthFilterOptions } from '../src/features/admin/dashboard/utils/dashboard-month-filter.helpers.ts'

const optionValues = (year: number) => getDashboardMonthFilterOptions(year, new Date('2026-07-09T12:00:00+07:00')).map((option) => option.value)

test('dashboard month filter only shows months through current month for current year', () => {
  assert.deepEqual(optionValues(2026), [1, 2, 3, 4, 5, 6, 7])
})

test('dashboard month filter shows all months for past years', () => {
  assert.deepEqual(optionValues(2025), [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12])
})
