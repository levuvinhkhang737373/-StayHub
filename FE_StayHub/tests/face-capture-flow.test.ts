import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { FACE_CAPTURE_MAX_SIZE, FACE_CAPTURE_QUALITY, FACE_REGISTRATION_STEPS, getFaceCaptureSize } from '../src/features/admin/auth/utils/face-capture.ts'

const accountSettingsSource = readFileSync(new URL('../src/layouts/admin/AccountSettingsModal.tsx', import.meta.url), 'utf8')
const adminLoginFormSource = readFileSync(new URL('../src/features/admin/auth/components/admin-login-form.tsx', import.meta.url), 'utf8')

test('face registration keeps three guided capture steps without changing the UI shell', () => {
  assert.deepEqual(FACE_REGISTRATION_STEPS.map((step) => step.title), [
    'Nhìn thẳng vào camera',
    'Xoay nhẹ mặt sang một bên',
    'Đưa mặt gần hoặc xa camera nhẹ',
  ])
})

test('face capture helper resizes large camera frames for fast upload', () => {
  const size = getFaceCaptureSize(1280, 720)

  assert.equal(FACE_CAPTURE_MAX_SIZE, 800)
  assert.equal(FACE_CAPTURE_QUALITY, 0.92)
  assert.deepEqual(size, { width: 800, height: 450 })
})

test('face capture helper keeps small camera frames unchanged', () => {
  assert.deepEqual(getFaceCaptureSize(320, 240), { width: 320, height: 240 })
})

test('face registration does not retry forever after one failed registration attempt', () => {
  assert.equal(accountSettingsSource.includes('while (isFaceRegistrationOpenRef.current)'), false)
  assert.match(accountSettingsSource, /FACE_REGISTRATION_STEPS\.length/)
  assert.match(accountSettingsSource, /Thử lại đăng ký FaceID/)
})

test('face login keeps the fast one-frame flow', () => {
  assert.match(adminLoginFormSource, /images\.push\(await captureFaceImage\(\)\)/)
  assert.equal(adminLoginFormSource.includes('FACE_REGISTRATION_STEPS'), false)
})
