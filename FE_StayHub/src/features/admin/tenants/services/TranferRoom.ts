import { apiRequest } from '../../../../shared/lib/api/api-client'
import type {
  TransferTenantPayload,
  TransferRoomResultResource,
} from '../types/TranferModel';


export async function transferTenantRoom(payload: TransferTenantPayload) {
  return apiRequest<TransferRoomResultResource>({
    url: 'admin/room-transfers/tenant',
    method: 'POST',
    data: payload,
  })
}
