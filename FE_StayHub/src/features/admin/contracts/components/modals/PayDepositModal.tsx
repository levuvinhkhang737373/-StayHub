import { useEffect, useState } from 'react'
import { BadgeCheck, X } from 'lucide-react'
import { useAdminSocket } from '../../../../../shared/lib/socket/socket-context'
import { cn } from '../../../../../shared/lib/utils/cn'
import { formatCurrency } from '../../../../../shared/lib/utils/format'
import type { AdminContractResource } from '../../types/contract-api.model'

const DEPOSIT_QR_EXPIRES_SECONDS = 24 * 60 * 60

function getDepositQrTimeLeft(contract: AdminContractResource) {
  if (!contract.created_at) return DEPOSIT_QR_EXPIRES_SECONDS

  const normalizedCreatedAt = contract.created_at.includes('T') ? contract.created_at : contract.created_at.replace(' ', 'T')
  const createdAtWithTimezone = /(?:Z|[+-]\d{2}:?\d{2})$/.test(normalizedCreatedAt) ? normalizedCreatedAt : `${normalizedCreatedAt}Z`
  const expiresAt = new Date(createdAtWithTimezone).getTime() + DEPOSIT_QR_EXPIRES_SECONDS * 1000
  if (Number.isNaN(expiresAt)) return DEPOSIT_QR_EXPIRES_SECONDS

  return Math.max(0, Math.floor((expiresAt - Date.now()) / 1000))
}

export function PayDepositModal({
  contract,
  isSaving,
  onClose,
  onConfirm,
}: {
  contract: AdminContractResource
  isSaving: boolean
  onClose: () => void
  onConfirm: (method: number) => Promise<void>
}) {
  const { echo } = useAdminSocket()
  const [method, setMethod] = useState<number | null>(null) // null, 1: Cash, 2: QR
  const [timeLeft, setTimeLeft] = useState(() => getDepositQrTimeLeft(contract))
  const [isPaidSuccess, setIsPaidSuccess] = useState(false)

  useEffect(() => {
    if (!echo || method !== 2) return
    const channel = echo.private('admin-payments')
    channel.listen('.ContractDepositPaid', (event: any) => {
      const updatedContract = event.contract
      if (updatedContract && Number(updatedContract.id) === Number(contract.id) && updatedContract.is_deposit_paid) {
        setIsPaidSuccess(true)
        setTimeout(() => {
          onClose()
        }, 2000)
      }
    })
    return () => {
      channel.stopListening('.ContractDepositPaid')
    }
  }, [echo, method, contract.id, onClose])

  useEffect(() => {
    if (method !== 2 || timeLeft <= 0 || isPaidSuccess) return
    const timer = setInterval(() => {
      setTimeLeft((prev) => prev - 1)
    }, 1000)
    return () => clearInterval(timer)
  }, [method, timeLeft, isPaidSuccess])

  const formatTime = (seconds: number) => {
    const days = Math.floor(seconds / 86400)
    const hours = Math.floor((seconds % 86400) / 3600)
    const mins = Math.floor((seconds % 3600) / 60)
    const secs = seconds % 60

    if (days > 0) {
      return `${days} ngày ${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
    }

    return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
  }

  const isExpired = timeLeft <= 0

  const handleConfirm = () => {
    if (method) {
      void onConfirm(method)
    }
  }

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center p-4">
      <button type="button" className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" onClick={onClose} aria-label="Đóng QR" />
      <div className="relative z-10 w-full max-w-md overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] p-5 shadow-2xl">
        <div className="flex items-center justify-between gap-3 border-b border-[#3d2a18]/10 pb-3">
          <h2 className="text-lg font-black text-[#24170d]">Ghi nhận đóng tiền cọc</h2>
          <button type="button" onClick={onClose} className="rounded-xl p-1.5 text-[#8b5e34] transition hover:bg-rose-50 hover:text-rose-600">
            <X className="h-4 w-4" />
          </button>
        </div>

        {method === null ? (
          <div className="mt-5 space-y-4">
            <p className="text-sm font-bold text-[#6f6254]">
              Vui lòng chọn phương thức thu cọc cho hợp đồng <span className="font-black text-[#24170d]">{contract.contract_code}</span>:
            </p>
            <div className="grid grid-cols-2 gap-4">
              <button
                type="button"
                onClick={() => setMethod(1)}
                className="flex flex-col items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-white p-4 transition hover:bg-[#f3c56b]/15 active:scale-95"
              >
                <span className="text-2xl">💵</span>
                <span className="mt-2 text-sm font-black text-[#24170d]">Tiền mặt</span>
              </button>
              <button
                type="button"
                onClick={() => setMethod(2)}
                className="flex flex-col items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-white p-4 transition hover:bg-[#f3c56b]/15 active:scale-95"
              >
                <span className="text-2xl">📱</span>
                <span className="mt-2 text-sm font-black text-[#24170d]">Chuyển khoản QR</span>
              </button>
            </div>
          </div>
        ) : method === 1 ? (
          <div className="mt-5 space-y-4">
            <div className="rounded-2xl border border-[#3d2a18]/10 bg-white/60 p-4 text-center">
              <p className="text-sm font-bold text-[#6f6254]">Xác nhận ghi nhận thu cọc bằng tiền mặt:</p>
              <p className="mt-2 text-2xl font-black text-[#24170d]">{formatCurrency(contract.deposit_amount)}</p>
            </div>
            <div className="flex gap-3">
              <button type="button" onClick={() => setMethod(null)} className="h-12 flex-1 rounded-xl border border-[#3d2a18]/10 bg-white text-sm font-black text-[#8b5e34]">
                Quay lại
              </button>
              <button
                type="button"
                disabled={isSaving}
                onClick={handleConfirm}
                className="h-12 flex-1 rounded-xl bg-[#24170d] text-sm font-black text-[#fff4df] hover:bg-[#3d2a18] disabled:opacity-60"
              >
                {isSaving ? 'Đang xác nhận...' : 'Xác nhận'}
              </button>
            </div>
          </div>
        ) : (
          <div className="mt-4 flex flex-col items-center">
            <div className="rounded-3xl border border-[#3d2a18]/10 bg-white p-4 shadow-sm">
              {isPaidSuccess ? (
                <div className="flex h-[240px] w-[240px] flex-col items-center justify-center text-center p-4 bg-emerald-50 rounded-2xl border border-emerald-200">
                  <div className="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 animate-bounce">
                    <BadgeCheck className="h-7 w-7" />
                  </div>
                  <p className="mt-4 text-sm font-black text-emerald-700">Thanh toán thành công!</p>
                  <p className="mt-1.5 text-xs text-emerald-600/80 font-bold">Hệ thống đang tự động cập nhật...</p>
                </div>
              ) : isExpired ? (
                <div className="flex h-[240px] w-[240px] flex-col items-center justify-center text-center p-4 bg-stone-50 rounded-2xl">
                  <p className="text-sm font-bold text-rose-500">Mã QR đã hết hạn (1 ngày)</p>
                  <p className="mt-2 text-xs text-stone-500">Vui lòng đóng modal và mở lại để tạo mã mới.</p>
                </div>
              ) : contract.deposit_qr_url ? (
                <img src={contract.deposit_qr_url} alt="VietQR Deposit Code" className="h-[240px] w-[240px] rounded-2xl object-contain" />
              ) : (
                <div className="flex h-[240px] w-[240px] items-center justify-center bg-stone-50 text-xs font-bold text-stone-500">Không tìm thấy mã QR</div>
              )}
            </div>

            <div className="mt-3 w-full text-center">
              <p className="text-xs font-bold text-[#8b5e34]/70">Mã QR hết hạn trong:</p>
              <p className={cn('text-base font-black mt-0.5', isExpired ? 'text-rose-500' : 'text-[#a65f16] animate-pulse')}>{formatTime(timeLeft)}</p>
            </div>

            <div className="mt-3 w-full space-y-2 rounded-2xl border border-[#3d2a18]/10 bg-white/60 p-3 text-xs font-bold text-[#6f6254]">
              <div className="flex justify-between">
                <span>Số tiền cọc:</span>
                <span className="font-black text-[#24170d]">{formatCurrency(contract.deposit_amount)}</span>
              </div>
              <div className="flex justify-between">
                <span>Nội dung CK:</span>
                <span className="font-black text-[#a65f16]">{`COC ${contract.contract_code}`}</span>
              </div>
            </div>

            <div className="mt-4 w-full">
              <button type="button" onClick={() => setMethod(null)} className="h-12 w-full rounded-xl border border-[#3d2a18]/10 bg-white text-sm font-black text-[#8b5e34]">
                Quay lại
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
