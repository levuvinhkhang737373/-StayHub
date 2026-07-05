import { useCallback, useEffect, useState, useMemo, Fragment } from 'react'
import { ConfirmModal } from '../../../../shared/components/ConfirmModal'
import { useConfirmModal } from '../../../../shared/lib/hooks/use-confirm-modal'
import {
  CheckCircle,
  CreditCard,
  Eye,
  FileText,
  QrCode,
  AlertTriangle,
  Upload,
  X,
  Wifi,
  Copy,
  Camera,
  ChevronLeft,
  ChevronRight,
} from 'lucide-react'
import { apiRequest } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatCurrency, formatDate } from '../../../../shared/lib/utils/format'
import {
  fetchTenantInvoices,
  fetchTenantInvoiceDetail,
  uploadTenantPaymentProof,
} from '../services/invoices.service'
import type { TenantInvoiceResource } from '../types/invoice.types'
import {
  INVOICE_STATUS_CANCELLED,
  INVOICE_STATUS_OVERDUE,
  INVOICE_STATUS_PAID,
  INVOICE_STATUS_PARTIALLY_PAID,
  INVOICE_STATUS_UNPAID,
} from '../types/invoice.types'
import { useTenantSocket } from '../../../../shared/lib/socket/socket-context'

export function TenantInvoicesScreen() {
  const { echo } = useTenantSocket()
  const [tenant, setTenant] = useState<any>(null)
  const [invoices, setInvoices] = useState<TenantInvoiceResource[]>([])
  const [paginationMeta, setPaginationMeta] = useState<any>(null)
  const [currentPage, setCurrentPage] = useState(1)
  const [selectedStatus, setSelectedStatus] = useState('')
  const [selectedMonth, setSelectedMonth] = useState('')
  const [selectedYear, setSelectedYear] = useState(String(new Date().getFullYear()))

  const totalPages = paginationMeta?.last_page ?? 1
  const safeCurrentPage = Math.max(1, Math.min(currentPage, totalPages))
  const visiblePages = useMemo(() => {
    const pages = new Set<number>([1, totalPages, safeCurrentPage - 1, safeCurrentPage, safeCurrentPage + 1])
    return Array.from(pages)
      .filter((page) => page >= 1 && page <= totalPages)
      .sort((a, b) => a - b)
  }, [safeCurrentPage, totalPages])

  const [detailInvoice, setDetailInvoice] = useState<TenantInvoiceResource | null>(null)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [isProofOpen, setIsProofOpen] = useState(false)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isLoading, setIsLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const { confirmState, isConfirmLoading, showAlert, closeConfirm } = useConfirmModal()

  // Payment proof form state
  const [proofAmount, setProofAmount] = useState('')
  const [proofRef, setProofRef] = useState('')
  const [proofNote, setProofNote] = useState('')
  const [proofFile, setProofFile] = useState<File | null>(null)
  const [isSavingProof, setIsSavingProof] = useState(false)
  const [proofError, setProofError] = useState<string | null>(null)

  // Socket connection for real-time updates

  // 1. Fetch current tenant session
  useEffect(() => {
    apiRequest<any>({ url: 'tenant/me', method: 'GET' })
      .then((res) => {
        setTenant(res.result)
      })
      .catch((err) => {
        console.error('Không thể lấy thông tin khách thuê:', err)
        setErrorMessage('Vui lòng đăng nhập để xem thông tin hóa đơn.')
      })
  }, [])

  // 2. Fetch invoices list
  const loadInvoices = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)
    try {
      const response = await fetchTenantInvoices({
        status: selectedStatus ? Number(selectedStatus) : undefined,
        billing_month: selectedMonth ? Number(selectedMonth) : undefined,
        billing_year: selectedYear ? Number(selectedYear) : undefined,
        page: currentPage,
      })
      if (response.status && response.result) {
        setInvoices(response.result.data || [])
        setPaginationMeta(response.result.meta || null)
      }
    } catch (err: any) {
      setErrorMessage(err.message || 'Không thể tải danh sách hóa đơn.')
    } finally {
      setIsLoading(false)
    }
  }, [currentPage, selectedStatus, selectedMonth, selectedYear])

  useEffect(() => {
    loadInvoices()
  }, [loadInvoices])

  // 3. Setup WebSocket listener for real-time updates
  useEffect(() => {
    if (!tenant?.id || !echo) return

    // Subscribe to tenant private channel
    const channel = echo.private(`tenant.${tenant.id}`)

    // Listen to InvoicePaid event
    channel.listen('.InvoicePaid', (event: any) => {
      console.log('Realtime WS: Hóa đơn đã được thanh toán', event)
      loadInvoices()
      setSuccessMessage(`Hóa đơn ${event.invoice?.invoice_code} đã được thanh toán thành công!`)

      // Update detail modal if currently looking at the same invoice
      if (detailInvoice && detailInvoice.id === event.invoice?.id) {
        setDetailInvoice(event.invoice)
      }
      setTimeout(() => setSuccessMessage(null), 5000)
    })

    // Listen to InvoiceIssued event
    channel.listen('.InvoiceIssued', (event: any) => {
      console.log('Realtime WS: Hóa đơn mới phát hành', event)
      loadInvoices()
      setSuccessMessage(`Có hóa đơn mới ${event.invoice?.invoice_code} vừa được phát hành!`)
      setTimeout(() => setSuccessMessage(null), 5000)
    })

    channel.listen('.InvoiceReissued', (event: any) => {
      console.log('Realtime WS: Hóa đơn được phát hành lại', event)
      loadInvoices()
      setSuccessMessage(`Hóa đơn ${event.invoice?.invoice_code} đã được cập nhật. Mã QR thanh toán mới đã sẵn sàng!`)

      if (detailInvoice && detailInvoice.id === event.invoice?.id) {
        fetchTenantInvoiceDetail(detailInvoice.id)
          .then((response) => {
            if (response.status && response.result) {
              setDetailInvoice(response.result)
            }
          })
          .catch(() => undefined)

        if (isProofOpen) {
          setIsProofOpen(false)
          setProofFile(null)
          setProofError('Hóa đơn vừa được cập nhật. Vui lòng kiểm tra số tiền và mã QR mới trước khi thanh toán.')
        }
      }

      setTimeout(() => setSuccessMessage(null), 6000)
    })

    return () => {
      channel.stopListening('.InvoicePaid')
      channel.stopListening('.InvoiceIssued')
      channel.stopListening('.InvoiceReissued')
    }
  }, [tenant?.id, echo, detailInvoice?.id, isProofOpen, loadInvoices])

  // 4. View Detail
  const handleViewDetail = async (invoiceId: number) => {
    setIsDetailLoading(true)
    setErrorMessage(null)
    setIsDetailOpen(true)
    try {
      const response = await fetchTenantInvoiceDetail(invoiceId)
      if (response.status && response.result) {
        setDetailInvoice(response.result)
      }
    } catch (err: any) {
      setErrorMessage(err.message || 'Không thể xem chi tiết hóa đơn.')
      setIsDetailOpen(false)
    } finally {
      setIsDetailLoading(false)
    }
  }

  // 5. Open Upload Proof Modal
  const handleOpenProof = (invoice: TenantInvoiceResource) => {
    if (invoice.is_debt_rolled_over) {
      showAlert('Khoản nợ đã chuyển hóa đơn', `Khoản nợ này đã chuyển sang hóa đơn ${invoice.rolled_to_invoice_code || 'sau'}, vui lòng thanh toán hóa đơn đó.`, 'warning')
      return
    }

    setDetailInvoice(invoice)
    setProofAmount(String(invoice.collectible_remaining_amount || invoice.remaining_amount))
    setProofRef('')
    setProofNote('')
    setProofFile(null)
    setProofError(null)
    setIsProofOpen(true)
  }

  // 6. Handle submit payment proof
  const handleSubmitProof = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!detailInvoice) return
    if (!proofFile) {
      setProofError('Vui lòng chọn ảnh minh chứng giao dịch.')
      return
    }
    if (!proofAmount || Number(proofAmount) <= 0) {
      setProofError('Số tiền thanh toán phải lớn hơn 0.')
      return
    }

    setIsSavingProof(true)
    setProofError(null)

    try {
      const response = await uploadTenantPaymentProof(detailInvoice.id, {
        amount: proofAmount,
        transaction_reference: proofRef || undefined,
        note: proofNote || undefined,
        proof_image: proofFile,
      })

      if (response.status) {
        setSuccessMessage('Gửi minh chứng thanh toán thành công! Vui lòng chờ admin duyệt.')
        setIsProofOpen(false)
        loadInvoices()
        setTimeout(() => setSuccessMessage(null), 4000)
      }
    } catch (err: any) {
      setProofError(err.message || 'Có lỗi xảy ra khi tải lên minh chứng.')
    } finally {
      setIsSavingProof(false)
    }
  }

  const copyToClipboard = (text: string, label: string) => {
    navigator.clipboard.writeText(text)
    showAlert('Đã sao chép', `Đã sao chép ${label}: ${text}`, 'info')
  }

  const getStatusBadge = (status: number, label: string) => {
    let classes = ''
    switch (status) {
      case INVOICE_STATUS_UNPAID:
        classes = 'bg-[#ef4444]/10 text-[#dc2626]'
        break
      case INVOICE_STATUS_PARTIALLY_PAID:
        classes = 'bg-[#3b82f6]/10 text-[#2563eb]'
        break
      case INVOICE_STATUS_PAID:
        classes = 'bg-[#10b981]/10 text-[#059669]'
        break
      case INVOICE_STATUS_OVERDUE:
        classes = 'bg-[#dc2626]/20 text-[#b91c1c] font-black animate-pulse'
        break
      case INVOICE_STATUS_CANCELLED:
        classes = 'bg-[#6b7280]/10 text-[#4b5563]'
        break
      default:
        classes = 'bg-slate-100 text-slate-600'
    }

    return (
      <span className={cn('inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold', classes)}>
        {label}
      </span>
    )
  }

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      {/* Header section with Premium Aesthetics */}
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="text-3xl font-extrabold tracking-tight text-[#24170d] md:text-4xl">
            Hóa đơn của tôi
          </h1>
          <p className="text-sm font-semibold text-[#8b5e34]/70">
            Theo dõi, thanh toán các khoản tiền phòng và phí dịch vụ StayHub.
          </p>
        </div>

        {/* Real-time Indicator */}
        <div className="flex items-center gap-2 rounded-2xl border border-[#3d2a18]/5 bg-white/70 px-4 py-2.5 shadow-sm backdrop-blur-md">
          <span className="relative flex h-2 w-2">
            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
            <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
          </span>
          <span className="text-xs font-bold text-[#24170d]/80 flex items-center gap-1">
            <Wifi className="h-3.5 w-3.5" /> Kết nối thanh toán tự động (Realtime)
          </span>
        </div>
      </div>

      {/* Message Notifications */}
      {successMessage && (
        <div className="flex items-center gap-3 rounded-2xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800 shadow-sm transition-all duration-300">
          <CheckCircle className="h-5 w-5 shrink-0 text-emerald-600" />
          <p className="text-sm font-bold">{successMessage}</p>
        </div>
      )}

      {errorMessage && (
        <div className="flex items-center gap-3 rounded-2xl bg-rose-50 border border-rose-200 p-4 text-rose-800 shadow-sm">
          <AlertTriangle className="h-5 w-5 shrink-0 text-rose-600" />
          <p className="text-sm font-bold">{errorMessage}</p>
        </div>
      )}

      {/* Filter and Content section */}
      <div className="grid gap-6">
        {/* Filters Card */}
        <div className="rounded-3xl border border-[#3d2a18]/10 bg-white/80 p-6 shadow-sm backdrop-blur-md">
          <div className="grid gap-4 sm:grid-cols-4">
            <div>
              <label className="mb-2 block text-xs font-bold text-[#8b5e34]">Trạng thái</label>
              <select
                value={selectedStatus}
                onChange={(e) => {
                  setSelectedStatus(e.target.value)
                  setCurrentPage(1)
                }}
                className="h-11 w-full rounded-2xl border border-[#3d2a18]/10 bg-white px-3 text-sm font-bold text-[#24170d] outline-none focus:border-[#0f766e] focus:ring-4 focus:ring-[#0f766e]/10"
              >
                <option value="">Tất cả trạng thái</option>
                <option value={INVOICE_STATUS_UNPAID}>Chưa thanh toán</option>
                <option value={INVOICE_STATUS_PARTIALLY_PAID}>Thanh toán 1 phần</option>
                <option value={INVOICE_STATUS_PAID}>Đã thanh toán</option>
                <option value={INVOICE_STATUS_OVERDUE}>Quá hạn</option>
              </select>
            </div>

            <div>
              <label className="mb-2 block text-xs font-bold text-[#8b5e34]">Tháng</label>
              <select
                value={selectedMonth}
                onChange={(e) => {
                  setSelectedMonth(e.target.value)
                  setCurrentPage(1)
                }}
                className="h-11 w-full rounded-2xl border border-[#3d2a18]/10 bg-white px-3 text-sm font-bold text-[#24170d] outline-none focus:border-[#0f766e] focus:ring-4 focus:ring-[#0f766e]/10"
              >
                <option value="">Tất cả tháng</option>
                {Array.from({ length: 12 }, (_, i) => (
                  <option key={i + 1} value={i + 1}>
                    Tháng {i + 1}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="mb-2 block text-xs font-bold text-[#8b5e34]">Năm</label>
              <select
                value={selectedYear}
                onChange={(e) => {
                  setSelectedYear(e.target.value)
                  setCurrentPage(1)
                }}
                className="h-11 w-full rounded-2xl border border-[#3d2a18]/10 bg-white px-3 text-sm font-bold text-[#24170d] outline-none focus:border-[#0f766e] focus:ring-4 focus:ring-[#0f766e]/10"
              >
                {Array.from({ length: 7 }, (_, i) => {
                  const y = new Date().getFullYear() - 3 + i
                  return (
                    <option key={y} value={y}>
                      Năm {y}
                    </option>
                  )
                })}
              </select>
            </div>

            <div className="flex items-end">
              <button
                onClick={() => {
                  setSelectedStatus('')
                  setSelectedMonth('')
                  setSelectedYear(String(new Date().getFullYear()))
                  setCurrentPage(1)
                }}
                className="h-11 w-full rounded-2xl bg-[#3d2a18]/5 text-sm font-bold text-[#3d2a18] hover:bg-[#3d2a18]/10 transition-colors"
              >
                Đặt lại bộ lọc
              </button>
            </div>
          </div>
        </div>

        {/* Invoices List */}
        {isLoading ? (
          <div className="flex h-64 items-center justify-center rounded-3xl border border-[#3d2a18]/10 bg-white/60">
            <span className="text-sm font-bold text-[#8b5e34]/70 animate-pulse">Đang tải hóa đơn...</span>
          </div>
        ) : invoices.length === 0 ? (
          <div className="flex h-64 flex-col items-center justify-center rounded-3xl border border-[#3d2a18]/10 bg-white/60 p-6 text-center">
            <FileText className="mb-3 h-12 w-12 text-[#8b5e34]/30" />
            <p className="text-lg font-bold text-[#24170d]">Không có hóa đơn nào</p>
            <p className="text-xs text-[#8b5e34]/70 mt-1">Không tìm thấy thông tin hóa đơn phù hợp với điều kiện lọc.</p>
          </div>
        ) : (
          <div className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              {invoices.map((invoice) => {
                const isPayable = [INVOICE_STATUS_UNPAID, INVOICE_STATUS_PARTIALLY_PAID, INVOICE_STATUS_OVERDUE].includes(invoice.status)

                return (
                  <div
                    key={invoice.id}
                    className="flex flex-col justify-between rounded-3xl border border-[#3d2a18]/10 bg-white/95 p-6 shadow-sm hover:shadow-md transition-shadow"
                  >
                    <div>
                      {/* Code & Status Row */}
                      <div className="flex items-center justify-between">
                        <span className="text-lg font-black tracking-tight text-[#24170d]">
                          {invoice.invoice_code}
                        </span>
                        {getStatusBadge(invoice.status, invoice.status_label || '')}
                      </div>

                      {/* Room & Building info */}
                      <div className="mt-2 text-xs font-bold text-[#8b5e34]/80">
                        {invoice.building_name} · Phòng {invoice.room_number}
                      </div>

                      <div className="mt-4 border-t border-[#3d2a18]/5 pt-4 space-y-2 text-sm">
                        <div className="flex justify-between font-medium text-[#8b5e34]">
                          <span>Kỳ thanh toán:</span>
                          <span className="font-bold text-[#24170d]">Tháng {invoice.billing_month}/{invoice.billing_year}</span>
                        </div>
                        <div className="flex justify-between font-medium text-[#8b5e34]">
                          <span>Tổng số tiền:</span>
                          <span className="font-extrabold text-[#24170d]">{formatCurrency(invoice.total_amount)}</span>
                        </div>
                        {Number(invoice.paid_amount) > 0 && (
                          <div className="flex justify-between font-medium text-[#8b5e34]">
                            <span>Đã trả:</span>
                            <span className="font-bold text-emerald-600">-{formatCurrency(invoice.paid_amount)}</span>
                          </div>
                        )}
                        {isPayable && (
                          <div className="flex justify-between font-medium text-[#8b5e34]">
                            <span>Số tiền còn lại:</span>
                            <span className="font-black text-rose-600">{formatCurrency(invoice.remaining_amount)}</span>
                          </div>
                        )}
                        {invoice.is_debt_rolled_over && (
                          <div className="rounded-xl bg-amber-50 px-3 py-2 text-xs font-black text-amber-700">
                            Nợ đã chuyển sang {invoice.rolled_to_invoice_code || 'hóa đơn sau'}
                          </div>
                        )}
                        <div className="flex justify-between font-medium text-[#8b5e34] text-xs">
                          <span>Hạn thanh toán:</span>
                          <span className="font-bold text-[#24170d]">{formatDate(invoice.due_date)}</span>
                        </div>
                      </div>
                    </div>

                    {/* Actions bar */}
                    <div className="mt-6 flex gap-3 border-t border-[#3d2a18]/5 pt-4">
                      <button
                        onClick={() => handleViewDetail(invoice.id)}
                        className="flex-1 inline-flex items-center justify-center gap-1.5 h-10 rounded-xl bg-[#3d2a18]/5 text-xs font-bold text-[#3d2a18] hover:bg-[#3d2a18]/10 transition-colors"
                      >
                        <Eye className="h-4 w-4" /> Chi tiết
                      </button>
                      {isPayable && (
                        <button
                          onClick={() => handleOpenProof(invoice)}
                          className="flex-1 inline-flex items-center justify-center gap-1.5 h-10 rounded-xl bg-[#eab308] text-xs font-extrabold text-[#1c1917] hover:bg-[#d9a307] transition-colors"
                        >
                          <CreditCard className="h-4 w-4" /> Thanh toán
                        </button>
                      )}
                    </div>
                  </div>
                )
              })}
            </div>

            {/* Pagination Controls */}
            {paginationMeta && paginationMeta.last_page > 1 && (
              <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <span className="text-xs font-bold text-[#8b5e34]">
                  Trang {paginationMeta.current_page} / {paginationMeta.last_page} (Tổng {paginationMeta.total} hóa đơn)
                </span>
                <div className="flex items-center justify-end gap-1.5">
                  <button
                    type="button"
                    disabled={currentPage <= 1}
                    onClick={() => setCurrentPage((p) => p - 1)}
                    className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-white text-[#8b5e34] transition hover:bg-[#efe2cf] disabled:cursor-not-allowed disabled:opacity-40"
                    aria-label="Trang trước"
                  >
                    <ChevronLeft className="h-4 w-4" />
                  </button>
                  {visiblePages.map((page, index) => {
                    const previousPage = visiblePages[index - 1]
                    const hasGap = previousPage && page - previousPage > 1

                    return (
                      <Fragment key={page}>
                        {hasGap && <span className="px-1 text-xs font-black text-[#8b5e34]/60">...</span>}
                        <button
                          type="button"
                          onClick={() => setCurrentPage(page)}
                          className={cn(
                            'inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition',
                            page === safeCurrentPage
                              ? 'border-[#24170d] bg-[#24170d] text-[#fff4df] shadow-sm'
                              : 'border-[#3d2a18]/10 bg-white text-[#8b5e34] hover:bg-[#efe2cf]',
                          )}
                        >
                          {page}
                        </button>
                      </Fragment>
                    )
                  })}
                  <button
                    type="button"
                    disabled={currentPage >= totalPages}
                    onClick={() => setCurrentPage((p) => p + 1)}
                    className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-white text-[#8b5e34] transition hover:bg-[#efe2cf] disabled:cursor-not-allowed disabled:opacity-40"
                    aria-label="Trang sau"
                  >
                    <ChevronRight className="h-4 w-4" />
                  </button>
                </div>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Invoice Detail Modal */}
      {isDetailOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
          <div className="relative w-full max-w-2xl rounded-3xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto border border-[#3d2a18]/10">
            {/* Modal header */}
            <div className="flex items-center justify-between border-b border-[#3d2a18]/5 pb-4">
              <div>
                <h3 className="text-xl font-extrabold text-[#24170d]">
                  Chi tiết hóa đơn {detailInvoice?.invoice_code}
                </h3>
                <p className="text-xs text-[#8b5e34]/70 font-semibold">
                  Kỳ hóa đơn: Tháng {detailInvoice?.billing_month}/{detailInvoice?.billing_year}
                </p>
              </div>
              <button
                onClick={() => setIsDetailOpen(false)}
                className="rounded-lg p-1.5 hover:bg-slate-100 text-[#3d2a18]/60 transition-colors"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {isDetailLoading ? (
              <div className="py-12 text-center text-sm font-bold text-[#8b5e34] animate-pulse">
                Đang tải chi tiết hóa đơn...
              </div>
            ) : detailInvoice ? (
              <div className="mt-4 space-y-6">
                {/* General Status card */}
                <div className="grid grid-cols-2 gap-4 rounded-2xl bg-[#3d2a18]/5 p-4 text-sm font-bold text-[#24170d]">
                  <div>
                    <span className="text-[#8b5e34]/80 text-xs block mb-0.5">Tòa nhà & Phòng</span>
                    {detailInvoice.building_name} · Phòng {detailInvoice.room_number}
                  </div>
                  <div>
                    <span className="text-[#8b5e34]/80 text-xs block mb-0.5">Trạng thái</span>
                    {getStatusBadge(detailInvoice.status, detailInvoice.status_label || '')}
                  </div>
                  <div>
                    <span className="text-[#8b5e34]/80 text-xs block mb-0.5">Tổng số tiền</span>
                    <span className="text-lg font-black">{formatCurrency(detailInvoice.total_amount)}</span>
                  </div>
                  <div>
                    <span className="text-[#8b5e34]/80 text-xs block mb-0.5">Hạn thanh toán</span>
                    {formatDate(detailInvoice.due_date)}
                  </div>
                </div>

                {/* Items Breakdown */}
                <div>
                  <h4 className="text-sm font-extrabold text-[#24170d] mb-2 uppercase tracking-wide">
                    Chi tiết các khoản tiền
                  </h4>
                  <div className="overflow-hidden rounded-2xl border border-[#3d2a18]/10 bg-white text-xs">
                    <table className="w-full text-left border-collapse">
                      <thead>
                        <tr className="bg-[#3d2a18]/5 text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34] border-b border-[#3d2a18]/10">
                          <th className="p-3">Khoản mục</th>
                          <th className="p-3 text-right">Số lượng</th>
                          <th className="p-3 text-right">Đơn giá</th>
                          <th className="p-3 text-right">Thành tiền</th>
                          <th className="p-3 text-right">Minh chứng</th>
                        </tr>
                      </thead>
                      <tbody>
                        {detailInvoice.items?.map((item) => (
                          <tr key={item.id} className="border-b border-[#3d2a18]/5 last:border-0 hover:bg-slate-50 transition-colors font-bold text-[#24170d]">
                            <td className="p-3 max-w-[200px]">
                              <div>{item.description}</div>
                              {item.item_type_label && (
                                <span className="text-[10px] text-[#8b5e34]/60 bg-[#3d2a18]/5 px-1.5 py-0.5 rounded-md font-bold mt-1 inline-block">
                                  {item.item_type_label}
                                </span>
                              )}
                            </td>
                            <td className="p-3 text-right text-[#8b5e34]">{Number(item.quantity)}</td>
                            <td className="p-3 text-right text-[#8b5e34]">{formatMoney(item.unit_price)}</td>
                            <td className="p-3 text-right font-black">
                              {formatMoney(item.amount)}
                            </td>
                            <td className="p-3 text-right">
                              {item.meter_reading?.image_url ? (
                                <a
                                  href={item.meter_reading.image_url}
                                  target="_blank"
                                  rel="noreferrer"
                                  className="inline-flex items-center gap-1.5 rounded-lg border border-[#eab308]/35 bg-[#eab308]/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-[#8a5a00] transition hover:bg-[#eab308]/20"
                                  title={`Cũ: ${item.meter_reading.previous_reading} · Mới: ${item.meter_reading.current_reading} · Dùng: ${item.meter_reading.consumption}`}
                                >
                                  <Camera className="h-3.5 w-3.5" /> Xem ảnh
                                </a>
                              ) : (
                                <span className="text-[10px] font-bold text-slate-300">—</span>
                              )}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>

                {/* Payments History */}
                {detailInvoice.payments && detailInvoice.payments.length > 0 && (
                  <div>
                    <h4 className="text-sm font-extrabold text-[#24170d] mb-2 uppercase tracking-wide">
                      Lịch sử giao dịch thanh toán
                    </h4>
                    <div className="space-y-2">
                      {detailInvoice.payments.map((pmt) => (
                        <div key={pmt.id} className="flex items-center justify-between rounded-xl border border-[#3d2a18]/10 p-3 text-xs bg-white">
                          <div className="font-bold text-[#24170d]">
                            <div>Mã GD: {pmt.payment_code}</div>
                            <div className="text-[10px] text-slate-400 mt-0.5">
                              {pmt.payment_date ? formatDate(pmt.payment_date) : ''} · {pmt.payment_method_label}
                            </div>
                            {pmt.note && <div className="text-[10px] text-[#8b5e34]/70 italic mt-0.5">"{pmt.note}"</div>}
                          </div>
                          <div className="text-right">
                            <span className="font-black text-emerald-600 block">+{formatCurrency(pmt.amount)}</span>
                            <span className={cn('text-[9px] font-bold px-1.5 py-0.5 rounded-md inline-block mt-0.5',
                              pmt.status === 2 ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'
                            )}>
                              {pmt.status_label}
                            </span>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* Dynamic VietQR display for payments */}
                {detailInvoice.payment_qr_url && !detailInvoice.is_debt_rolled_over && (
                  <div className="rounded-2xl border border-amber-200 bg-amber-50/50 p-4">
                    <div className="flex flex-col items-center text-center gap-2">
                      <QrCode className="h-6 w-6 text-amber-600" />
                      <h5 className="text-sm font-bold text-amber-800">
                        Quét mã VietQR chuyển khoản nhanh
                      </h5>
                      <p className="text-[11px] text-[#8b5e34]/80 max-w-md">
                        Hệ thống tự động đồng bộ tài khoản sau khi giao dịch thành công. Vui lòng giữ đúng số tiền và nội dung chuyển khoản để nhận tiền tự động trong 1 phút.
                      </p>

                      <div className="mt-2 rounded-2xl bg-white p-3 border border-amber-200 shadow-sm flex flex-col items-center gap-1.5">
                        <img
                          src={detailInvoice.payment_qr_url}
                          alt="VietQR Code"
                          className="h-44 w-44 object-contain"
                        />
                        <div className="text-[10px] text-slate-400 font-medium">VietQR Cổng SePay</div>
                      </div>

                      <div className="w-full grid grid-cols-2 gap-2 mt-2 text-left font-bold text-xs max-w-sm">
                        <div className="rounded-xl bg-white border border-amber-100 p-2 text-[#24170d]">
                          <span className="text-[10px] text-[#8b5e34]/60 block">Nội dung chuyển khoản</span>
                          <span className="flex items-center justify-between font-black mt-0.5">
                            {detailInvoice.invoice_code}
                            <button
                              onClick={() => copyToClipboard(detailInvoice.invoice_code, 'nội dung')}
                              className="text-amber-600 hover:text-amber-800"
                            >
                              <Copy className="h-3.5 w-3.5" />
                            </button>
                          </span>
                        </div>
                        <div className="rounded-xl bg-white border border-amber-100 p-2 text-[#24170d]">
                          <span className="text-[10px] text-[#8b5e34]/60 block">Số tiền chuyển</span>
                          <span className="flex items-center justify-between font-black mt-0.5">
                            {formatMoney(detailInvoice.collectible_remaining_amount || detailInvoice.remaining_amount)}
                            <button
                              onClick={() => copyToClipboard(String(Math.round(Number(detailInvoice.collectible_remaining_amount || detailInvoice.remaining_amount))), 'số tiền')}
                              className="text-amber-600 hover:text-amber-800"
                            >
                              <Copy className="h-3.5 w-3.5" />
                            </button>
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                )}
              </div>
            ) : null}

            {/* Modal footer */}
            <div className="mt-8 flex justify-end gap-3 border-t border-[#3d2a18]/5 pt-4">
              <button
                onClick={() => setIsDetailOpen(false)}
                className="h-10 rounded-xl bg-slate-100 px-4 text-xs font-bold text-[#3d2a18] hover:bg-slate-200 transition-colors"
              >
                Đóng
              </button>
              {detailInvoice && !detailInvoice.is_debt_rolled_over && [INVOICE_STATUS_UNPAID, INVOICE_STATUS_PARTIALLY_PAID, INVOICE_STATUS_OVERDUE].includes(detailInvoice.status) && (
                <button
                  onClick={() => {
                    setIsDetailOpen(false)
                    handleOpenProof(detailInvoice)
                  }}
                  className="h-10 rounded-xl bg-[#eab308] px-4 text-xs font-extrabold text-[#1c1917] hover:bg-[#d9a307] transition-colors"
                >
                  Gửi minh chứng chuyển khoản
                </button>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Upload Payment Proof Modal */}
      {isProofOpen && detailInvoice && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
          <form
            onSubmit={handleSubmitProof}
            className="w-full max-w-lg rounded-3xl bg-white p-6 shadow-xl border border-[#3d2a18]/10"
          >
            {/* Modal header */}
            <div className="flex items-center justify-between border-b border-[#3d2a18]/5 pb-4">
              <div>
                <h3 className="text-xl font-extrabold text-[#24170d]">
                  Gửi minh chứng thanh toán
                </h3>
                <p className="text-xs text-[#8b5e34]/70 font-semibold">
                  Hóa đơn: {detailInvoice.invoice_code}
                </p>
              </div>
              <button
                type="button"
                onClick={() => setIsProofOpen(false)}
                className="rounded-lg p-1.5 hover:bg-slate-100 text-[#3d2a18]/60 transition-colors"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {proofError && (
              <div className="mt-3 rounded-xl bg-rose-50 border border-rose-200 p-3 text-xs font-bold text-rose-800">
                {proofError}
              </div>
            )}

            <div className="mt-4 space-y-4 text-sm font-bold text-[#24170d]">
              {/* Note about bank info */}
              <div className="rounded-xl bg-[#3d2a18]/5 p-3 text-xs text-[#8b5e34] border border-[#3d2a18]/5">
                Nếu bạn đã chuyển khoản thủ công không qua VietQR hoặc giao dịch chưa cập nhật, vui lòng tải ảnh minh chứng dưới đây để quản lý kiểm tra.
              </div>

              {/* Amount input */}
              <div>
                <label className="mb-1.5 block text-xs font-bold text-[#8b5e34]">
                  Số tiền đã thanh toán (VNĐ) <span className="text-rose-500">*</span>
                </label>
                <input
                  type="number"
                  required
                  value={proofAmount}
                  onChange={(e) => setProofAmount(e.target.value)}
                  placeholder="Nhập số tiền VNĐ"
                  className="h-11 w-full rounded-2xl border border-[#3d2a18]/10 bg-white/80 px-4 text-sm font-bold text-[#24170d] outline-none transition placeholder:text-[#8b5e34]/45 focus:border-[#0f766e] focus:ring-4 focus:ring-[#0f766e]/10"
                />
              </div>

              {/* Transaction Ref */}
              <div>
                <label className="mb-1.5 block text-xs font-bold text-[#8b5e34]">
                  Mã tham chiếu / Mã giao dịch ngân hàng
                </label>
                <input
                  type="text"
                  value={proofRef}
                  onChange={(e) => setProofRef(e.target.value)}
                  placeholder="Ví dụ: FT2606012456"
                  className="h-11 w-full rounded-2xl border border-[#3d2a18]/10 bg-white/80 px-4 text-sm font-bold text-[#24170d] outline-none transition placeholder:text-[#8b5e34]/45 focus:border-[#0f766e] focus:ring-4 focus:ring-[#0f766e]/10"
                />
              </div>

              {/* Proof Image Upload */}
              <div>
                <label className="mb-1.5 block text-xs font-bold text-[#8b5e34]">
                  Ảnh hóa đơn chuyển khoản <span className="text-rose-500">*</span>
                </label>
                <div className="relative flex min-h-[120px] flex-col items-center justify-center rounded-2xl border-2 border-dashed border-[#3d2a18]/20 bg-slate-50 hover:bg-slate-100 transition-colors p-4 cursor-pointer">
                  <input
                    type="file"
                    required
                    accept="image/*"
                    onChange={(e) => {
                      if (e.target.files?.[0]) setProofFile(e.target.files[0])
                    }}
                    className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                  />
                  {proofFile ? (
                    <div className="text-center text-xs">
                      <FileText className="mx-auto h-8 w-8 text-emerald-600 mb-1" />
                      <p className="font-bold text-emerald-800 max-w-[280px] truncate">{proofFile.name}</p>
                      <p className="text-[10px] text-slate-400">{(proofFile.size / 1024 / 1024).toFixed(2)} MB</p>
                    </div>
                  ) : (
                    <div className="text-center text-xs text-[#8b5e34]/60">
                      <Upload className="mx-auto h-8 w-8 text-[#8b5e34]/40 mb-1" />
                      <p className="font-bold">Bấm hoặc kéo thả ảnh hóa đơn vào đây</p>
                      <p className="text-[10px] text-slate-400">Hỗ trợ JPG, PNG, WEBP (Tối đa 5MB)</p>
                    </div>
                  )}
                </div>
              </div>

              {/* Note */}
              <div>
                <label className="mb-1.5 block text-xs font-bold text-[#8b5e34]">
                  Ghi chú
                </label>
                <textarea
                  value={proofNote}
                  onChange={(e) => setProofNote(e.target.value)}
                  placeholder="Nhập ghi chú chuyển khoản (nếu có)..."
                  className="min-h-16 w-full rounded-2xl border border-[#3d2a18]/10 bg-white/80 px-4 py-3 text-sm font-bold text-[#24170d] outline-none transition placeholder:text-[#8b5e34]/45 focus:border-[#0f766e] focus:ring-4 focus:ring-[#0f766e]/10"
                />
              </div>
            </div>

            {/* Modal footer */}
            <div className="mt-8 flex justify-end gap-3 border-t border-[#3d2a18]/5 pt-4">
              <button
                type="button"
                onClick={() => setIsProofOpen(false)}
                className="h-10 rounded-xl bg-slate-100 px-4 text-xs font-bold text-[#3d2a18] hover:bg-slate-200 transition-colors"
              >
                Hủy
              </button>
              <button
                type="submit"
                disabled={isSavingProof}
                className="h-10 rounded-xl bg-[#eab308] px-4 text-xs font-extrabold text-[#1c1917] hover:bg-[#d9a307] transition-colors disabled:opacity-50"
              >
                {isSavingProof ? 'Đang gửi...' : 'Gửi minh chứng'}
              </button>
            </div>
          </form>
        </div>
      )}
      <ConfirmModal {...confirmState} onCancel={closeConfirm} isLoading={isConfirmLoading} />
    </div>
  )
}

function formatMoney(value: string | number | null | undefined): string {
  if (!value) return '0'
  const [integerPart] = String(value).split('.')
  return integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
}
