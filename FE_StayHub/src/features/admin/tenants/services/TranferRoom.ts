import { apiRequest } from '../../../../shared/lib/api/api-client'
import type {
  RoomMovementResource,
  TransferTenantPayload,
} from '../types/TranferModel';

/**
 * Lưu ý: route backend hiện đặt là `room-transfers/...` (xem comment cuối file
 * RoomTransferController.php) - đổi prefix sang `admin/room-transfers/...` để khớp
 * convention các route admin khác (admin/tenants, admin/room...) và áp đúng middleware
 * nhóm admin (hiện route mẫu chỉ có auth:sanctum, chưa có check quyền admin).
 */
export async function transferTenantRoom(payload: TransferTenantPayload) {
  return apiRequest<RoomMovementResource>({
    url: 'admin/room-transfers/tenant',
    method: 'POST',
    data: payload,
  })
}

