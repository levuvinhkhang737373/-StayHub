import type { ReactNode } from 'react'
import { AlertTriangle, BadgeCheck, Banknote, Calculator, ClipboardCheck, ReceiptText, X } from 'lucide-react'
import { AdminDateInput } from '../../../../../shared/components/AdminDateInput'
import { cn } from '../../../../../shared/lib/utils/cn'
import { formatCurrency, formatDate, formatMoneyInput } from '../../../../../shared/lib/utils/format'
import { AdminSelect } from '../../../shared/components/AdminSelect'
import type { AdminContractResource } from '../../types/contract-api.model'
import { toDate } from '../../utils/contract.helpers'
import { inputClass, inputErrorClass, labelClass } from '../form/form-elements'

const paymentMethodOptions = [
  { value: 1, label: 'Tiền mặt', tone: 'default' as const },
  { value: 2, label: 'Chuyển khoản', tone: 'success' as const },
]

export type TerminateContractForm = {
  actual_end_date: string
  deduction_amount: string
  payment_method: number
  note: string
}

function moneyNumber(value: string | number | null | undefined): number {
  if (value === null || value === undefined) return 0
  if (typeof value === 'number') return value

  const valStr = String(value).trim()
  if (/^\d+\.\d{1,2}$/.test(valStr)) {
    return Math.max(Math.round(Number(valStr)), 0)
  }

  const parsed = Number(valStr.replace(/\./g, '').replace(/,/g, '').trim() || '0')
  return Number.isFinite(parsed) ? Math.max(parsed, 0) : 0
}

function moneyString(value: number) {
  return value.toFixed(2)
}

export function TerminateContractModal({
  contract,
  form,
  finalInvoiceWarning,
  isCheckingFinalInvoice = false,
  isSaving,
  onChange,
  onClose,
  onSubmit,
}: {
  contract: AdminContractResource
  form: TerminateContractForm
  finalInvoiceWarning?: string | null
  isCheckingFinalInvoice?: boolean
  isSaving: boolean
  onChange: (value: TerminateContractForm) => void
  onClose: () => void
  onSubmit: () => void
}) {
  const depositBalance = Math.max(0, moneyNumber(contract.deposit_balance))
  const deductionAmount = Math.max(0, moneyNumber(form.deduction_amount))
  const refundAmount = Math.max(depositBalance - deductionAmount, 0)
  const isOverDeducted = deductionAmount > depositBalance
  const netDeposit = depositBalance - deductionAmount
  const minDate = contract.start_date ? toDate(contract.start_date) : undefined
  const maxDate = new Date()

  return (
    <div className="fixed inset-0 z-[80] flex items-center justify-center p-4">
      <button type="button" className="absolute inset-0 bg-[#120b06]/75 backdrop-blur-md" onClick={onClose} aria-label="Đóng thanh lý hợp đồng" />
      <div className="relative z-10 max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-[2rem] border border-[#f3c56b]/25 bg-[#fffaf1] shadow-2xl shadow-black/30">
        <div className="relative overflow-hidden rounded-t-[2rem] bg-[#24170d] px-6 py-6 text-[#fff4df]">
          <div className="absolute -right-12 -top-16 h-40 w-40 rounded-full border border-[#f3c56b]/20" />
          <div className="absolute bottom-0 left-0 h-px w-full bg-gradient-to-r from-transparent via-[#f3c56b]/70 to-transparent" />
          <button type="button" onClick={onClose} className="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-[#fff4df] transition hover:bg-white/20" aria-label="Đóng">
            <X className="h-5 w-5" />
          </button>
          <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p className="inline-flex items-center gap-2 rounded-full border border-[#f3c56b]/30 bg-[#f3c56b]/12 px-3 py-1 text-[10px] font-black uppercase tracking-[0.2em] text-[#f3c56b]">
                <ClipboardCheck className="h-3.5 w-3.5" /> Settlement ledger
              </p>
              <h2 className="mt-3 text-2xl font-black tracking-tight">Thanh lý hợp đồng</h2>
              <p className="mt-1 text-sm font-bold text-[#fff4df]/70">
                {contract.contract_code} · Phòng {contract.room_number || contract.room_id} · {formatDate(contract.start_date)} → {formatDate(contract.end_date)}
              </p>
            </div>
            <div className="rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-right">
              <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#f3c56b]/85">Số dư cọc đang giữ</p>
              <p className="mt-1 text-2xl font-black tabular-nums">{formatCurrency(moneyString(depositBalance))}</p>
            </div>
          </div>
        </div>

        <div className="grid gap-5 p-5 lg:grid-cols-[1fr_0.9fr]">
          <div className="space-y-4">
            {(isCheckingFinalInvoice || finalInvoiceWarning) && (
              <div className="rounded-[1.5rem] border border-amber-300/70 bg-amber-50 px-4 py-3 text-amber-950 shadow-sm shadow-amber-900/5">
                <div className="flex gap-3">
                  <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                  <div>
                    <p className="text-sm font-black">Cảnh báo hóa đơn kỳ chốt</p>
                    <p className="mt-1 text-xs font-bold leading-5 text-amber-900/80">
                      {isCheckingFinalInvoice
                        ? 'Đang kiểm tra hóa đơn tháng thanh lý...'
                        : finalInvoiceWarning}
                    </p>
                  </div>
                </div>
              </div>
            )}

            <div>
              <label className={labelClass}>Ngày thanh lý</label>
              <AdminDateInput className={inputClass} value={form.actual_end_date} minDate={minDate} maxDate={maxDate} onChange={(value) => onChange({ ...form, actual_end_date: value })} />
            </div>

            <div>
              <label className={labelClass}>Cấn trừ từ tiền cọc</label>
              <div className="relative">
                <Calculator className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8b5e34]/55" />
                <input
                  value={form.deduction_amount}
                  inputMode="decimal"
                  onChange={(event) => onChange({ ...form, deduction_amount: formatMoneyInput(event.target.value) })}
                  placeholder="0"
                  className={cn(inputClass, 'pl-11 tabular-nums', isOverDeducted && inputErrorClass)}
                />
              </div>
              {isOverDeducted && <p className="mt-1.5 px-1 text-xs font-bold text-rose-600">Số tiền cấn trừ không được vượt quá số dư cọc.</p>}
            </div>

            <div className="grid gap-4 sm:grid-cols-[0.85fr_1.15fr]">
              <div>
                <label className={labelClass}>Phương thức hoàn cọc</label>
                <AdminSelect value={form.payment_method} options={paymentMethodOptions} onChange={(value) => onChange({ ...form, payment_method: Number(value) })} />
              </div>

            </div>

            <div>
              <label className={labelClass}>Ghi chú thanh lý</label>
              <textarea
                value={form.note}
                onChange={(event) => onChange({ ...form, note: event.target.value })}
                className={cn(inputClass, 'min-h-28')}
                placeholder="Ví dụ: Khấu trừ chi phí vệ sinh, hoàn cọc phần còn lại cho khách thuê..."
              />
            </div>
          </div>

          <aside className="space-y-3 rounded-[1.75rem] border border-[#3d2a18]/10 bg-white/65 p-4 shadow-inner shadow-[#6b3f1d]/5">
            <div className="flex items-center gap-2 text-sm font-black text-[#24170d]">
              <ReceiptText className="h-5 w-5 text-[#8a4f18]" /> Bảng quyết toán cọc
            </div>
            <LedgerRow icon={<Banknote className="h-4 w-4" />} label="Số dư cọc" value={formatCurrency(moneyString(depositBalance))} />
            <LedgerRow icon={<AlertTriangle className="h-4 w-4" />} label="Cấn trừ/hư hỏng/nợ" value={`-${formatCurrency(moneyString(Math.min(deductionAmount, depositBalance)))}`} tone="danger" />
            <LedgerRow icon={<BadgeCheck className="h-4 w-4" />} label="Hoàn lại" value={formatCurrency(moneyString(refundAmount))} tone="success" />
            <div className={cn(
              "rounded-2xl border p-4 transition-colors duration-200",
              netDeposit >= 0 
                ? "border-[#0f766e]/15 bg-[#0f766e]/8" 
                : "border-rose-600/15 bg-rose-600/8"
            )}>
              <p className={cn(
                "text-[10px] font-black uppercase tracking-[0.18em]",
                netDeposit >= 0 ? "text-[#0f5f59]/70" : "text-rose-600/70"
              )}>Số dư cọc sau thanh lý</p>
              <p className={cn(
                "mt-1 text-3xl font-black tabular-nums",
                netDeposit >= 0 ? "text-[#0f5f59]" : "text-rose-600"
              )}>{formatCurrency(moneyString(netDeposit))}</p>
            </div>
          </aside>
        </div>

        <div className="flex flex-col-reverse gap-3 border-t border-[#3d2a18]/10 bg-[#f7ecd8] p-5 sm:flex-row sm:justify-end">
          <button type="button" onClick={onClose} className="h-12 rounded-2xl border border-[#3d2a18]/10 bg-white px-5 text-sm font-black text-[#8b5e34] transition hover:bg-[#fffaf1]">
            Hủy
          </button>
          <button
            type="button"
            disabled={isSaving || isOverDeducted || !form.actual_end_date}
            onClick={onSubmit}
            className="h-12 rounded-2xl bg-[#24170d] px-6 text-sm font-black text-[#fff4df] shadow-xl shadow-[#24170d]/20 transition hover:-translate-y-0.5 hover:bg-[#3a2616] disabled:cursor-not-allowed disabled:opacity-55 disabled:hover:translate-y-0"
          >
            {isSaving ? 'Đang thanh lý...' : finalInvoiceWarning ? 'Tiếp tục thanh lý' : 'Xác nhận thanh lý'}
          </button>
        </div>
      </div>
    </div>
  )
}

function LedgerRow({ icon, label, value, tone = 'neutral' }: { icon: ReactNode; label: string; value: string; tone?: 'neutral' | 'success' | 'danger' }) {
  const toneClassName = tone === 'success' ? 'text-[#0f5f59]' : tone === 'danger' ? 'text-rose-600' : 'text-[#24170d]'

  return (
    <div className="flex items-center justify-between gap-3 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3">
      <div className="flex min-w-0 items-center gap-2 text-xs font-black text-[#8b5e34]">
        {icon}
        <span className="truncate">{label}</span>
      </div>
      <p className={cn('shrink-0 text-sm font-black tabular-nums', toneClassName)}>{value}</p>
    </div>
  )
}
