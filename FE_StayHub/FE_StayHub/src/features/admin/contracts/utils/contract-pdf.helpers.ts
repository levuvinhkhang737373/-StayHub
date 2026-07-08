import type { AdminContractResource } from '../types/contract-api.model'

export function getContractPdfRepresentativeTenant(contract: AdminContractResource) {
  const activeTenants = (contract.contract_tenants || []).filter((contractTenant) => contractTenant.is_staying !== false)

  return activeTenants[0]?.tenant ?? null
}
