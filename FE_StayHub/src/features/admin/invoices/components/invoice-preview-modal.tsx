import { AlertTriangle, Loader2, ReceiptText, Send, ShieldCheck, X } from 'lucide-react'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatCurrency, formatDate, formatDateTime } from '../../../../shared/lib/utils/format'
import type { AdminInvoicePreviewResource } from '../types/invoice-api.model'

type InvoicePreviewModalProps = {
  invoice: AdminInvoicePreviewResource
  isIssuing: boolean
  onClose: () => void
  onConfirm: () => void
}

export function InvoicePreviewModal({ invoice, isIssuing, onClose, onConfirm }: InvoicePreviewModalProps) {
  const items = invoice.items || []
  const transferCutoffs = invoice.transfer_cutoffs || []
  const roomLabel = invoice.room?.room_number || invoice.room_number || invoice.room_id
  const tenantNames = (invoice.tenants || [])
    .filter((tenant) => tenant.is_staying !== false)
    .map((tenant) => tenant.full_name)
    .filter(Boolean)
    .join(', ')

  return (
    <div className="fixed inset-0 z-[90] flex items-center justify-center p-3 sm:p-5" role="dialog" aria-modal="true" aria-labelledby="invoice-preview-title">
      <button type="button" className="absolute inset-0 bg-stone-950/75 backdrop-blur-sm" onClick={isIssuing ? undefined : onClose} aria-label="Đóng xem trước hóa đơn" />

      <div className="relative z-10 flex max-h-[94vh] w-full max-w-6xl flex-col overflow-hidden rounded-[2rem] border border-[#f3c56b]/25 bg-[#fffaf1] shadow-2xl shadow-stone-950/35">
        <div className="relative overflow-hidden bg-[#211409] px-5 py-5 text-[#fff4df] sm:px-7">
          <div className="pointer-events-none absolute inset-0 opacity-25 [background-image:linear-gradient(90deg,rgba(243,197,107,.22)_1px,transparent_1px),linear-gradient(rgba(243,197,107,.18)_1px,transparent_1px)] [background-size:28px_28px]" />
          <div className="relative flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <div className="inline-flex items-center gap-2 rounded-full border border-[#f3c56b]/35 bg-[#f3c56b]/15 px-3 py-1 text-[10px] font-black uppercase tracking-[0.22em] text-[#f3c56b]">
                <ShieldCheck className="h-3.5 w-3.5" /> Bản xem trước
              </div>
              <h2 id="invoice-preview-title" className="mt-3 text-2xl font-black tracking-tight sm:text-3xl">Kiểm tra hóa đơn trước khi phát hành</h2>
              <p className="mt-2 max-w-3xl text-sm font-bold leading-6 text-[#f8e8c8]/78">
                Hóa đơn chưa được tạo mã, chưa gửi thông báo và chưa đổi trạng thái chỉ số. Khi bấm phát hành, hệ thống sẽ tính lại lần cuối rồi mới ghi nhận.
              </p>
            </div>

            <button
              type="button"
              onClick={onClose}
              disabled={isIssuing}
              className="absolute right-0 top-0 inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20 disabled:opacity-50 lg:static"
              aria-label="Đóng"
            >
              <X className="h-5 w-5" />
            </button>
          </div>
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-7">
          <div className="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
            <section className="rounded-[1.7rem] border border-[#3d2a18]/10 bg-white/75 p-4 shadow-sm">
              <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <PreviewTile label="Phòng" value={`Phòng ${roomLabel}`} />
                <PreviewTile label="Tòa nhà" value={invoice.room?.building_name || invoice.building_name || '—'} />
                <PreviewTile label="Người thuê" value={tenantNames || invoice.tenant_name || '—'} />
                <PreviewTile label="Kỳ hóa đơn" value={`Tháng ${invoice.billing_month}/${invoice.billing_year}`} />
                <PreviewTile label="Hạn thanh toán" value={formatDate(invoice.due_date)} />
                <PreviewTile label="Mã hóa đơn" value={invoice.invoice_code_note || 'Cấp khi phát hành'} />
              </div>
            </section>

            <aside className="rounded-[1.7rem] border border-[#0f766e]/15 bg-[#0f766e]/8 p-4 shadow-sm">
              <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#0f766e]">Tổng dự kiến</p>
              <p className="mt-2 text-4xl font-black tracking-tight text-[#123b35] tabular-nums">{formatCurrency(invoice.total_amount)}</p>
              <div className="mt-3 grid grid-cols-2 gap-2 text-xs font-bold text-[#416158]">
                <span className="rounded-2xl bg-white/70 p-3">Nợ cũ: <b className="text-[#24170d]">{formatCurrency(invoice.previous_debt_amount || '0')}</b></span>
                <span className="rounded-2xl bg-white/70 p-3">Còn lại: <b className="text-[#24170d]">{formatCurrency(invoice.remaining_amount)}</b></span>
              </div>
            </aside>
          </div>

          <div className="mt-4 rounded-[1.7rem] border border-amber-300/35 bg-amber-50/75 p-4 text-sm font-bold text-amber-900">
            <div className="flex gap-3">
              <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
              <div>
                <p className="font-black">Đây chỉ là bản nháp kiểm tra.</p>
                <p className="mt-1 text-xs leading-5 text-amber-900/75">Mã hóa đơn, thông báo người thuê, log phát hành và trạng thái chỉ số chỉ phát sinh sau khi xác nhận phát hành.</p>
              </div>
            </div>
          </div>

          {transferCutoffs.length > 0 && (
            <div className="mt-4 rounded-[1.7rem] border border-[#0f766e]/20 bg-[#e6fffb] p-4 text-sm font-bold text-[#0f3f3b]">
              <div className="flex gap-3">
                <ShieldCheck className="mt-0.5 h-5 w-5 shrink-0 text-[#0f766e]" />
                <div>
                  <p className="font-black">Đã áp dụng lịch chuyển phòng đang chờ xử lý.</p>
                  <p className="mt-1 text-xs leading-5 text-[#0f3f3b]/75">Tiền phòng/dịch vụ đi theo hợp đồng phòng: chỉ cắt khi hợp đồng cũ kết thúc hoặc hợp đồng mới bắt đầu; chuyển ghép phòng active chỉ cộng/cắt tiền xe theo ngày chuyển.</p>
                  <div className="mt-3 grid gap-2 sm:grid-cols-2">
                    {transferCutoffs.map((cutoff) => (
                      <div key={`${cutoff.direction || 'outgoing'}-${cutoff.transfer_code}`} className="rounded-2xl border border-[#0f766e]/15 bg-white/70 p-3">
                        <p className="text-[10px] font-black uppercase tracking-[0.16em] text-[#0f766e]">{cutoff.transfer_code}</p>
                        <p className="mt-1 text-xs leading-5 text-[#0f3f3b]/80">
                          {cutoff.direction === 'incoming' ? (
                            <>Chuyển vào ngày <b>{formatDate(cutoff.movement_date)}</b>, xe phòng mới tính từ <b>{formatDate(cutoff.vehicle_start_date || cutoff.movement_date)}</b>.</>
                          ) : (
                            <>Chuyển đi ngày <b>{formatDate(cutoff.movement_date)}</b>, xe phòng cũ tính đến <b>{formatDate(cutoff.cutoff_date)}</b>.</>
                          )}
                        </p>
                        <p className="mt-1 text-xs leading-5 text-[#0f3f3b]/70">
                          {cutoff.direction === 'incoming'
                            ? `Phòng mới có lịch nhận khách, chỉ cộng tiền xe của: ${cutoff.tenant_names?.join(', ') || cutoff.tenant_ids.join(', ')}`
                            : cutoff.closes_source_contract
                            ? 'Lịch này đóng hợp đồng cũ nên tiền phòng/dịch vụ cũng tính theo ngày kết thúc hợp đồng.'
                            : `Chuyển lẻ, chỉ cắt tiền xe của: ${cutoff.tenant_names?.join(', ') || cutoff.tenant_ids.join(', ')}`}
                        </p>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          )}

          <section className="mt-4 overflow-hidden rounded-[1.7rem] border border-[#3d2a18]/10 bg-white shadow-sm">
            <div className="flex items-center justify-between gap-3 border-b border-[#3d2a18]/10 bg-[#fff7e8] px-4 py-3">
              <div className="flex items-center gap-2 text-sm font-black text-[#24170d]"><ReceiptText className="h-4 w-4 text-[#8b5e34]" /> Chi tiết khoản thu</div>
              <span className="rounded-full bg-[#24170d] px-3 py-1 text-[10px] font-black uppercase tracking-wider text-[#fff4df]">{items.length} dòng</span>
            </div>

            <div className="overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="bg-[#fffaf1] text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/70">
                  <tr>
                    <th className="px-4 py-3">Khoản mục</th>
                    <th className="px-4 py-3 text-right">SL</th>
                    <th className="px-4 py-3 text-right">Đơn giá</th>
                    <th className="px-4 py-3 text-right">Thành tiền</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[#3d2a18]/8">
                  {items.map((item, index) => (
                    <tr key={`${item.item_type}-${item.description}-${index}`} className="text-[#24170d]">
                      <td className="px-4 py-3">
                        <p className="font-black">{item.description}</p>
                        <p className="mt-1 text-xs font-bold text-[#8b5e34]/65">{item.item_type_label || item.service_name || 'Khoản thu'}</p>
                      </td>
                      <td className="px-4 py-3 text-right font-bold tabular-nums">{item.quantity}</td>
                      <td className="px-4 py-3 text-right font-bold tabular-nums">{formatCurrency(item.unit_price)}</td>
                      <td className={cn('px-4 py-3 text-right font-black tabular-nums', Number(item.amount) < 0 ? 'text-rose-600' : 'text-[#0f766e]')}>{formatCurrency(item.amount)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          {invoice.preview_generated_at && (
            <p className="mt-3 text-right text-[11px] font-bold text-[#8b5e34]/65">Tạo preview lúc {formatDateTime(invoice.preview_generated_at)}</p>
          )}
        </div>

        <div className="flex flex-col gap-2 border-t border-[#3d2a18]/10 bg-[#fff7e8]/95 px-5 py-4 sm:flex-row sm:justify-end sm:px-7">
          <button type="button" onClick={onClose} disabled={isIssuing} className="inline-flex h-12 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-white px-5 text-xs font-black uppercase tracking-wider text-[#6f6254] transition hover:bg-[#efe2cf] disabled:opacity-50">
            Hủy
          </button>
          <button type="button" onClick={onConfirm} disabled={isIssuing || !invoice.can_issue} className="inline-flex h-12 items-center justify-center gap-2 rounded-2xl bg-[#0f766e] px-6 text-xs font-black uppercase tracking-wider text-white shadow-lg shadow-[#0f766e]/20 transition hover:bg-[#0c5f59] disabled:opacity-60">
            {isIssuing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
            {isIssuing ? 'Đang phát hành...' : 'Phát hành hóa đơn'}
          </button>
        </div>
      </div>
    </div>
  )
}

function PreviewTile({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="min-w-0 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3">
      <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/60">{label}</p>
      <p className="mt-1 break-words text-sm font-black text-[#24170d]">{value}</p>
    </div>
  )
}
