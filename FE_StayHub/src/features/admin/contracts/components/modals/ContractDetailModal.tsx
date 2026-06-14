import { X } from 'lucide-react'
import { formatCurrency, formatDate, formatDateTime } from '../../../../../shared/lib/utils/format'
import type { AdminContractResource } from '../../types/contract-api.model'
import { getStatusLabel } from '../../utils/contract.helpers'
import { labelClass } from '../form/form-elements'
import { DetailTile } from '../ui/ui-elements'

export function ContractDetailModal({
  contract,
  isLoading,
  errorMessage,
  onClose,
  onPayDeposit,
}: {
  contract: AdminContractResource
  isLoading: boolean
  errorMessage: string | null
  onClose: () => void
  onPayDeposit: (contract: AdminContractResource) => void
}) {
  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true">
      <button type="button" aria-label="Đóng chi tiết hợp đồng" onClick={onClose} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />
      <div className="relative z-10 w-full max-w-6xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-stone-950/30">
        <div className="bg-[#24170d] p-5 text-[#fff4df]">
          <div className="flex items-start justify-between gap-4">
            <div>
              <p className="text-[10px] font-black uppercase tracking-[0.24em] text-[#f3c56b]">Chi tiết hợp đồng</p>
              <h2 className="mt-2 text-2xl font-black tracking-tight">{contract.contract_code}</h2>
            </div>
            <button
              type="button"
              onClick={onClose}
              className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20"
            >
              <X className="h-5 w-5" />
            </button>
          </div>
        </div>

        <div className="max-h-[calc(100dvh-12rem)] space-y-4 overflow-y-auto p-5">
          {isLoading && <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-800">Đang tải chi tiết hợp đồng...</div>}
          {errorMessage && <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{errorMessage}</div>}

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-5">
            <DetailTile label="Trạng thái" value={contract.status_label || getStatusLabel(contract.status)} />
            <DetailTile
              label="Trạng thái cọc"
              value={
                <span
                  className={
                    contract.payment_status === 2 // SUCCESS
                      ? 'text-emerald-600 font-black'
                      : contract.payment_status === 3 // CANCELLED
                        ? 'text-rose-600 font-black'
                        : contract.payment_status === 4 // EXPIRED
                          ? 'text-red-600 font-black'
                          : 'text-amber-600 font-black' // PENDING / others
                  }
                >
                  {contract.payment_status_label || (contract.is_deposit_paid ? 'Đã đóng cọc' : 'Chưa đóng cọc')}
                </span>
              }
            />
            <DetailTile label="Phòng" value={`Phòng ${contract.room?.room_number || contract.room_number || contract.room_id}`} />
            <DetailTile label="Tòa nhà" value={contract.room?.building_name || contract.building_name || '—'} />
            <DetailTile label="Người tạo" value={contract.creator_name || '—'} />
          </div>

          <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
            <p className={labelClass}>Thông tin tài chính & thời hạn</p>
            <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-4">
              <DetailTile label="Thời hạn" value={`${formatDate(contract.start_date)} → ${formatDate(contract.end_date)}`} />
              <DetailTile label="Ngày kết thúc thực tế" value={formatDate(contract.actual_end_date)} />
              <DetailTile label="Giá phòng" value={formatCurrency(contract.room_price)} />
              <DetailTile label="Tiền cọc" value={formatCurrency(contract.deposit_amount)} />
            </div>
          </section>

          <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
            <p className={labelClass}>Khách thuê trong hợp đồng</p>
            <div className="mt-3 overflow-x-auto">
              <table className="w-full min-w-[720px] text-left text-sm">
                <thead className="text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/70">
                  <tr>
                    <th className="py-2">Khách thuê</th>
                    <th>Ngày ở</th>
                    <th>Tính tiền</th>
                    <th>Trạng thái</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[#3d2a18]/10">
                  {(contract.contract_tenants || []).map((tenant) => (
                    <tr key={tenant.id || tenant.tenant_id}>
                      <td className="py-3 font-black">{tenant.tenant?.full_name || tenant.tenant_id}</td>
                      <td className="font-black">
                        {formatDate(tenant.join_date)} → {formatDate(tenant.leave_date)}
                      </td>
                      <td className="font-black">
                        {formatDate(tenant.billing_start_date)} → {formatDate(tenant.billing_end_date)}
                      </td>
                      <td className="font-black">{tenant.is_staying ? 'Đang ở' : 'Đã rời'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {(contract.contract_tenants || []).length === 0 && <p className="py-4 text-sm font-bold text-[#8b5e34]/70">Chưa có dữ liệu khách thuê.</p>}
            </div>
          </section>

          <section className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
              <p className={labelClass}>Phương tiện</p>
              <div className="mt-3 space-y-2">
                {(contract.contract_vehicles || [])
                  .filter((vehicle) => vehicle.is_active !== false)
                  .map((vehicle) => (
                    <div key={vehicle.id || vehicle.vehicle_id} className="rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3 text-sm font-bold">
                      <p className="font-black text-[#24170d]">{vehicle.vehicle?.license_plate || vehicle.vehicle_id}</p>
                      <p className="text-xs font-black text-[#6f6254]">
                        {vehicle.charge_policy_label} · {formatCurrency(vehicle.monthly_fee)} · {vehicle.is_active ? 'Còn tính phí' : 'Hết tính phí'}
                      </p>
                    </div>
                  ))}
                {(contract.contract_vehicles || []).filter((vehicle) => vehicle.is_active !== false).length === 0 && (
                  <p className="text-sm font-bold text-[#8b5e34]/70">Chưa có phương tiện.</p>
                )}
              </div>
            </div>
            <div className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
              <div className="flex items-center justify-between">
                <p className={labelClass}>Giao dịch cọc</p>
                {!contract.is_deposit_paid && Number(contract.deposit_amount) > 0 && (
                  <button
                    type="button"
                    onClick={() => onPayDeposit(contract)}
                    className="rounded-xl bg-[#24170d] px-3 py-1.5 text-xs font-black text-[#fff4df] transition hover:bg-[#3d2a18] active:scale-95"
                  >
                    Đóng cọc
                  </button>
                )}
              </div>
              <div className="mt-3 space-y-2">
                {(contract.deposit_transactions || []).map((transaction) => (
                  <div key={transaction.id} className="rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3 text-sm font-bold">
                    <p className="font-black text-[#24170d]">
                      {transaction.transaction_type_label} · {formatCurrency(transaction.amount)}
                    </p>
                    <p className="text-xs font-black text-[#6f6254]">
                      {formatDate(transaction.transaction_date)} · {transaction.payment_method_label} · {transaction.creator_name || '—'}
                    </p>
                  </div>
                ))}
                {(contract.deposit_transactions || []).length === 0 && <p className="text-sm font-bold text-[#8b5e34]/70">Chưa có giao dịch cọc.</p>}
              </div>
            </div>
          </section>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <DetailTile label="Chuyển phòng" value={contract.room_movements_count ?? 0} />
            <DetailTile label="Cập nhật" value={formatDateTime(contract.updated_at)} />
          </div>

          {contract.note && (
            <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
              <p className={labelClass}>Ghi chú</p>
              <p className="whitespace-pre-wrap text-sm font-bold text-[#3d2a18]">{contract.note}</p>
            </section>
          )}
        </div>
      </div>
    </div>
  )
}
