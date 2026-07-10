import test from 'node:test'
import assert from 'node:assert/strict'
import { resolveAdminLayoutState } from '../src/layouts/admin/admin-layout-state.ts'

test('admin layout hides protected shell as soon as logout clears the session', () => {
  assert.equal(resolveAdminLayoutState('checking', false), 'checking')
  assert.equal(resolveAdminLayoutState('guest', false), 'guest')
  assert.equal(resolveAdminLayoutState('authenticated', true), 'authenticated')
  assert.equal(resolveAdminLayoutState('authenticated', false), 'guest')
})
