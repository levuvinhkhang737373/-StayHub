import type { AdminContractResource } from '../types/contract-api.model'

export function canPayContractDeposit(contract: AdminContractResource): boolean {
  return !contract.is_deposit_paid && Number(contract.deposit_amount) > 0
}

export function getContractTransferTenantId(contract: AdminContractResource): number | null {
  const activeContractTenant = (contract.contract_tenants || []).find((contractTenant) => contractTenant.is_staying !== false)
  const tenantId = contract.representative_tenant_id || activeContractTenant?.tenant_id || activeContractTenant?.tenant?.id

  return tenantId ? Number(tenantId) : null
}

export function canTransferContractRoom(contract: AdminContractResource): boolean {
  return Boolean(getContractTransferTenantId(contract))
}
