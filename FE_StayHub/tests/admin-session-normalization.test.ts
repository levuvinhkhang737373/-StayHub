import test from 'node:test'
import assert from 'node:assert/strict'
import { getAdminSessionProfile, normalizeAdminSession } from '../src/features/admin/auth/hooks/admin-session-store.ts'

const managerProfile = {
  id: 73,
  username: 'manager_building_a',
  full_name: 'Quản lý Building A',
  role: 1,
  role_label: null,
}

test('normalizes direct admin profile returned by admin me endpoint', () => {
  const session = normalizeAdminSession(managerProfile)

  assert.equal(session?.admin.full_name, 'Quản lý Building A')
  assert.equal(session?.admin.role, 1)
})

test('unwraps nested admin payload so sidebar does not show fallback admin unknown', () => {
  const legacyNestedPayload = { admin: { admin: managerProfile } }

  const profile = getAdminSessionProfile(legacyNestedPayload)

  assert.equal(profile?.full_name, 'Quản lý Building A')
  assert.equal(profile?.role, 1)
})
