import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import type { AdminContractResource } from '../src/features/admin/contracts/types/contract-api.model.ts'
import { canPayContractDeposit, getContractTransferTenantId } from '../src/features/admin/contracts/utils/contract-actions.helpers.ts'
import { getContractPdfRepresentativeTenant } from '../src/features/admin/contracts/utils/contract-pdf.helpers.ts'

const contractsScreenSource = readFileSync(new URL('../src/features/admin/contracts/components/contracts-screen.tsx', import.meta.url), 'utf8')
const contractDetailModalSource = readFileSync(new URL('../src/features/admin/contracts/components/modals/ContractDetailModal.tsx', import.meta.url), 'utf8')

test('uses representative tenant for exported contract PDF instead of first active tenant', () => {
  const contract = {
    id: 1,
    contract_code: 'HD-REP-PDF',
    room_id: 1,
    status: 1,
    representative_tenant_id: 20,
    representative_tenant: {
      id: 20,
      full_name: 'Nguyễn Đại Diện',
      phone: '0900000002',
      email: 'rep@example.test',
      identity_number: '079000000002',
    },
    contract_tenants: [
      {
        tenant_id: 10,
        is_staying: true,
        tenant: {
          id: 10,
          full_name: 'Trần Người Đầu',
          identity_number: '079000000001',
        },
      },
      {
        tenant_id: 20,
        is_staying: true,
        tenant: {
          id: 20,
          full_name: 'Nguyễn Đại Diện',
          identity_number: '079000000002',
        },
      },
    ],
  } as AdminContractResource

  const representativeTenant = getContractPdfRepresentativeTenant(contract)

  assert.equal(representativeTenant?.id, 20)
  assert.equal(representativeTenant?.full_name, 'Nguyễn Đại Diện')
})

test('shows deposit action only when contract still has deposit to collect', () => {
  const contract = {
    id: 1,
    contract_code: 'HD-DEPOSIT-ACTION',
    room_id: 1,
    status: 1,
    deposit_amount: '1000000',
    is_deposit_paid: false,
  } as AdminContractResource

  assert.equal(canPayContractDeposit(contract), true)
  assert.equal(canPayContractDeposit({ ...contract, is_deposit_paid: true }), false)
  assert.equal(canPayContractDeposit({ ...contract, deposit_amount: '0' }), false)
})

test('uses representative tenant when building contract transfer action link', () => {
  const contract = {
    id: 1,
    contract_code: 'HD-TRANSFER-ACTION',
    room_id: 1,
    status: 1,
    representative_tenant_id: 20,
    contract_tenants: [
      { tenant_id: 10, is_staying: true },
      { tenant_id: 20, is_staying: true },
    ],
  } as AdminContractResource

  assert.equal(getContractTransferTenantId(contract), 20)
})

test('contracts list exposes deposit and room-transfer actions in the action menu', () => {
  assert.match(contractsScreenSource, /ActionMenu/)
  assert.match(contractsScreenSource, /Xác nhận tiền cọc/)
  assert.match(contractsScreenSource, /Chuyển phòng/)
})

test('contract detail moves deposit action from deposit section into the action menu', () => {
  assert.match(contractDetailModalSource, /ActionMenu/)
  assert.match(contractDetailModalSource, /Xác nhận tiền cọc/)
  assert.doesNotMatch(contractDetailModalSource, />\s*Đóng cọc\s*</)
})
