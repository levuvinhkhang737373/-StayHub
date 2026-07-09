import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const confirmModalSource = readFileSync(new URL('../src/shared/components/ConfirmModal.tsx', import.meta.url), 'utf8')

test('success alert confirm button still runs its confirm action when cancel is hidden', () => {
  assert.match(confirmModalSource, /onClick=\{onConfirm\}/)
  assert.doesNotMatch(confirmModalSource, /hideCancel\s*\?\s*onCancel\s*:\s*onConfirm/)
})
