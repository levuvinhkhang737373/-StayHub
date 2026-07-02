import { ApiError } from '../../../../shared/lib/api/api-client'
import type {
  AdminPaymentHistoryPaginationMeta,
  AdminPaymentHistoryPaginator,
  AdminPaymentHistoryRecord,
  AdminPaymentHistorySummary,
  PaymentHistoryAmountDirection,
  PaymentHistorySourceType,
  PaymentHistoryStatusGroup,
} from '../types/payment-history.model'
import { DEPOSIT_TRANSACTION_TYPE_COLLECT, DEPOSIT_TRANSACTION_TYPE_REFUND, PAYMENT_METHOD_BANK_TRANSFER, PAYMENT_METHOD_CASH } from '../types/payment-history.model'

export const perPageOptions = [
  { value: 10, label: '10 dòng', tone: 'default' as const },
  { value: 20, label: '20 dòng', tone: 'default' as const },
  { value: 50, label: '50 dòng', tone: 'default' as const },
  { value: 100, label: '100 dòng', tone: 'default' as const },
]

export const sourceTypeOptions: Array<{ value: PaymentHistorySourceType | ''; label: string; tone: 'default' | 'success' | 'warning' | 'danger' }> = [
  { value: '', label: 'Tất cả nguồn', tone: 'default' },
  { value: 'invoice_payment', label: 'Thanh toán hóa đơn', tone: 'success' },
  { value: 'deposit_transaction', label: 'Giao dịch cọc', tone: 'warning' },
  { value: 'room_transfer', label: 'Thanh toán chuyển phòng', tone: 'default' },
]

export const statusGroupOptions: Array<{ value: PaymentHistoryStatusGroup | ''; label: string; tone: 'default' | 'success' | 'warning' | 'danger' }> = [
  { value: '', label: 'Tất cả trạng thái', tone: 'default' },
  { value: 'pending', label: 'Chờ xác nhận', tone: 'warning' },
  { value: 'confirmed', label: 'Đã ghi nhận', tone: 'success' },
  { value: 'partial', label: 'Thanh toán một phần', tone: 'warning' },
  { value: 'paid', label: 'Đã thanh toán', tone: 'success' },
  { value: 'cancelled', label: 'Đã hủy', tone: 'danger' },
]

export const paymentMethodOptions = [
  { value: '', label: 'Tiền mặt + chuyển khoản', tone: 'default' as const },
  { value: PAYMENT_METHOD_CASH, label: 'Tiền mặt', tone: 'success' as const },
  { value: PAYMENT_METHOD_BANK_TRANSFER, label: 'Chuyển khoản', tone: 'default' as const },
]

export const amountDirectionOptions: Array<{ value: PaymentHistoryAmountDirection | ''; label: string; tone: 'default' | 'success' | 'warning' | 'danger' }> = [
  { value: '', label: 'Tất cả dòng tiền', tone: 'default' },
  { value: 'in', label: 'Tiền vào', tone: 'success' },
  { value: 'out', label: 'Tiền ra/hoàn', tone: 'danger' },
]

export const depositTransactionTypeOptions = [
  { value: '', label: 'Tất cả giao dịch cọc', tone: 'default' as const },
  { value: DEPOSIT_TRANSACTION_TYPE_COLLECT, label: 'Thu cọc', tone: 'success' as const },
  { value: DEPOSIT_TRANSACTION_TYPE_REFUND, label: 'Hoàn cọc', tone: 'danger' as const },
]

export function normalizePaymentHistory(result: AdminPaymentHistoryPaginator<AdminPaymentHistoryRecord> | AdminPaymentHistoryRecord[] | null | undefined) {
  if (!result) {
    return { data: [] as AdminPaymentHistoryRecord[], meta: null as AdminPaymentHistoryPaginationMeta | null, summary: null as AdminPaymentHistorySummary | null }
  }

  if (Array.isArray(result)) {
    return { data: result, meta: null as AdminPaymentHistoryPaginationMeta | null, summary: null as AdminPaymentHistorySummary | null }
  }

  return {
    data: result.data || [],
    meta: result.pagination || result.meta || null,
    summary: result.summary || null,
  }
}

export function getVisibleErrorMessage(error: unknown, fallback: string) {
  if (error instanceof ApiError) return error.message || fallback
  if (error instanceof Error) return error.message
  return fallback
}

export function getSourceLabel(source?: PaymentHistorySourceType | null) {
  return sourceTypeOptions.find((option) => option.value === source)?.label || 'Giao dịch'
}

export function getStatusTone(status?: PaymentHistoryStatusGroup | null) {
  if (status === 'pending' || status === 'partial') return 'warning'
  if (status === 'cancelled') return 'danger'
  if (status === 'confirmed' || status === 'paid') return 'success'
  return 'default'
}

export function getDirectionLabel(direction?: PaymentHistoryAmountDirection | null) {
  if (direction === 'out') return 'Tiền ra'
  if (direction === 'adjustment') return 'Điều chỉnh'
  return 'Tiền vào'
}

export function getDirectionSign(direction?: PaymentHistoryAmountDirection | null) {
  return direction === 'out' ? '-' : '+'
}

export function getDirectionClass(direction?: PaymentHistoryAmountDirection | null) {
  if (direction === 'out') return 'text-rose-700 bg-rose-50 border-rose-200'
  if (direction === 'adjustment') return 'text-amber-700 bg-amber-50 border-amber-200'
  return 'text-emerald-700 bg-emerald-50 border-emerald-200'
}
