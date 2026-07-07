import { getVisibleErrorMessage as getSharedVisibleErrorMessage } from '../../shared/utils/error-message'
import type { AdminInvoicePaginationMeta, AdminInvoiceResource } from '../types/invoice-api.model'
import {
  INVOICE_STATUS_CANCELLED,
  INVOICE_STATUS_OVERDUE,
  INVOICE_STATUS_PAID,
  INVOICE_STATUS_PARTIALLY_PAID,
  INVOICE_STATUS_UNPAID,
} from '../types/invoice-api.model'

export const invoiceStatusOptions = [
  { value: '', label: 'Tất cả trạng thái', tone: 'default' as const },
  { value: INVOICE_STATUS_UNPAID, label: 'Chưa thanh toán', tone: 'danger' as const },
  { value: INVOICE_STATUS_PARTIALLY_PAID, label: 'Thanh toán 1 phần', tone: 'warning' as const },
  { value: INVOICE_STATUS_PAID, label: 'Đã thanh toán', tone: 'success' as const },
  { value: INVOICE_STATUS_OVERDUE, label: 'Quá hạn', tone: 'danger' as const },
  { value: INVOICE_STATUS_CANCELLED, label: 'Đã hủy', tone: 'default' as const },
]

export const monthOptions = [
  { value: '', label: 'Tất cả tháng', tone: 'default' as const },
  ...Array.from({ length: 12 }, (_, index) => ({ value: index + 1, label: `Tháng ${index + 1}`, tone: 'default' as const })),
]

export const perPageOptions = [
  { value: 5, label: '5 dòng', tone: 'default' as const },
  { value: 10, label: '10 dòng', tone: 'default' as const },
  { value: 20, label: '20 dòng', tone: 'default' as const },
  { value: 50, label: '50 dòng', tone: 'default' as const },
]

export type InvoicesResult = { data?: AdminInvoiceResource[]; meta?: AdminInvoicePaginationMeta | null } | AdminInvoiceResource[] | null | undefined

export function normalizeInvoices(result: InvoicesResult) {
  if (!result) return { data: [] as AdminInvoiceResource[], meta: null as AdminInvoicePaginationMeta | null }
  if (Array.isArray(result)) return { data: result, meta: null }
  return { data: result.data || [], meta: result.meta || null }
}

export function getResourceList<T>(result: { data?: T[] } | T[] | null | undefined): T[] {
  if (!result) return []
  if (Array.isArray(result)) return result
  return result.data || []
}

export function getVisibleErrorMessage(error: unknown, fallback: string) {
  return getSharedVisibleErrorMessage(error, fallback)
}


export function getInvoiceStatusLabel(status?: number | null) {
  if (Number(status) === INVOICE_STATUS_UNPAID) return 'Chưa thanh toán'
  if (Number(status) === INVOICE_STATUS_PARTIALLY_PAID) return 'Thanh toán 1 phần'
  if (Number(status) === INVOICE_STATUS_PAID) return 'Đã thanh toán'
  if (Number(status) === INVOICE_STATUS_OVERDUE) return 'Quá hạn'
  if (Number(status) === INVOICE_STATUS_CANCELLED) return 'Đã hủy'
  return 'Không xác định'
}

export function currentMonthYear() {
  const now = new Date()
  return { month: now.getMonth() + 1, year: now.getFullYear() }
}
