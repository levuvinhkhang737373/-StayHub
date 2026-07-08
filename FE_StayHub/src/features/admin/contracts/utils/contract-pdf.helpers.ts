import type { AdminContractResource } from '../types/contract-api.model'

export function getContractPdfRepresentativeTenant(contract: AdminContractResource) {
  const activeTenants = (contract.contract_tenants || []).filter((contractTenant) => contractTenant.is_staying !== false)
  const representativeTenantId = contract.representative_tenant_id ?? contract.representative_tenant?.id ?? null

  if (representativeTenantId) {
    const tenantFromContractRows = activeTenants.find((contractTenant) => Number(contractTenant.tenant_id) === Number(representativeTenantId))?.tenant

    return tenantFromContractRows ?? contract.representative_tenant ?? null
  }

  return activeTenants[0]?.tenant ?? null
}
