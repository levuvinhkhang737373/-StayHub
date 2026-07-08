import test from 'node:test'
import assert from 'node:assert/strict'
import type { AdminContractResource } from '../src/features/admin/contracts/types/contract-api.model.ts'
import { getContractPdfRepresentativeTenant } from '../src/features/admin/contracts/utils/contract-pdf.helpers.ts'

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
