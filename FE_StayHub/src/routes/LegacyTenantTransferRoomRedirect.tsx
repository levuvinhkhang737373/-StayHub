import { Navigate, useParams } from 'react-router-dom'

export function LegacyTenantTransferRoomRedirect() {
  const { tenantId } = useParams<{ tenantId: string }>()

  return <Navigate to={tenantId ? `/admin/transfer-room?tenantId=${tenantId}` : '/admin/transfer-room'} replace />
}
