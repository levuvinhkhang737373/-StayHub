import test from 'node:test'
import assert from 'node:assert/strict'
import { getAdminRoleLabel, resolveAdminRoleLabel } from '../src/features/admin/shared/config/admin-navigation.ts'

test('admin role label supports numeric and legacy string role values', () => {
  assert.equal(getAdminRoleLabel(1), 'Quản lý tòa nhà')
  assert.equal(getAdminRoleLabel('1'), 'Quản lý tòa nhà')
  assert.equal(getAdminRoleLabel('quan_ly_toa_nha'), 'Quản lý tòa nhà')
  assert.equal(getAdminRoleLabel('building_manager'), 'Quản lý tòa nhà')
  assert.equal(getAdminRoleLabel('Quản lý tòa nhà'), 'Quản lý tòa nhà')

  assert.equal(getAdminRoleLabel(2), 'Quản trị tổng')
  assert.equal(getAdminRoleLabel('2'), 'Quản trị tổng')
  assert.equal(getAdminRoleLabel('quan_tri_tong'), 'Quản trị tổng')
  assert.equal(getAdminRoleLabel('super_admin'), 'Quản trị tổng')
  assert.equal(getAdminRoleLabel('Quản trị tổng'), 'Quản trị tổng')
})

test('admin role label falls back when backend or stale session says unknown', () => {
  assert.equal(resolveAdminRoleLabel(1, 'Không xác định'), 'Quản lý tòa nhà')
  assert.equal(resolveAdminRoleLabel(2, 'Không xác định'), 'Quản trị tổng')
  assert.equal(resolveAdminRoleLabel(1, null), 'Quản lý tòa nhà')
})
