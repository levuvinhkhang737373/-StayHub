import { FileText, X } from 'lucide-react'
import { Link } from 'react-router-dom'
import { ConfirmModal } from '../../../../../shared/components/ConfirmModal'
import { useConfirmModal } from '../../../../../shared/lib/hooks/use-confirm-modal'
import { formatCurrency, formatDate, formatDateTime, formatMoneyText } from '../../../../../shared/lib/utils/format'
import type { AdminContractResource } from '../../types/contract-api.model'
import { getStatusLabel } from '../../utils/contract.helpers'
import { labelClass } from '../form/form-elements'
import { DetailTile } from '../ui/ui-elements'

function docSoTien(so: number): string {
  if (so === 0) return 'Không đồng'
  const units = ['', 'nghìn', 'triệu', 'tỷ', 'nghìn tỷ', 'triệu tỷ']
  const digits = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín']
  
  const docBlock = (n: number, full: boolean) => {
    let result = ''
    const tram = Math.floor(n / 100)
    const chuc = Math.floor((n % 100) / 10)
    const donvi = n % 10
    
    if (full || tram > 0) {
      result += digits[tram] + ' trăm '
    }
    
    if (chuc > 0) {
      if (chuc === 1) result += 'mười '
      else result += digits[chuc] + ' mươi '
    } else if (donvi > 0 && (full || tram > 0)) {
      result += 'lẻ '
    }
    
    if (donvi > 0) {
      if (donvi === 1 && chuc > 1) result += 'mốt'
      else if (donvi === 5 && chuc > 0) result += 'lăm'
      else result += digits[donvi]
    }
    return result.trim()
  }

  let str = ''
  let temp = so
  
  const blocks: number[] = []
  while (temp > 0) {
    blocks.push(temp % 1000)
    temp = Math.floor(temp / 1000)
  }
  
  for (let idx = blocks.length - 1; idx >= 0; idx--) {
    const b = blocks[idx]
    if (b > 0) {
      const text = docBlock(b, idx < blocks.length - 1)
      if (text) {
        str += text + ' ' + units[idx] + ' '
      }
    }
  }
  
  str = str.trim()
  if (!str) return 'Không đồng'
  
  str = str.charAt(0).toUpperCase() + str.slice(1)
  return str.trim() + ' đồng'
}

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
  const { confirmState, isConfirmLoading, showAlert, closeConfirm } = useConfirmModal()
  const calculateMonths = (start?: string | null, end?: string | null) => {
    if (!start || !end) return '...'
    const s = new Date(start)
    const e = new Date(end)
    if (isNaN(s.getTime()) || isNaN(e.getTime())) return '...'
    const diff = (e.getFullYear() - s.getFullYear()) * 12 + (e.getMonth() - s.getMonth())
    return diff <= 0 ? '1' : String(diff)
  }

  const handleExportPDF = () => {
    const tenantsList = (contract.contract_tenants || []).filter((ct) => ct.is_staying !== false)
    const repTenant = tenantsList[0]?.tenant
    const landlord = contract.landlord_info
    let tenantsHtml = ''
    if (tenantsList.length === 0) {
      tenantsHtml = `
        <div class="info-block">
          Ông/bà <strong>................................................................</strong><br>
          CMND/CCCD số <strong>................................</strong> &nbsp;&nbsp;&nbsp;&nbsp; cấp ngày <strong>..........................</strong> &nbsp;&nbsp;&nbsp;&nbsp; nơi cấp <strong>................................</strong><br>
          Thường trú tại: <strong>...............................................................................................</strong>
        </div>
      `
    } else {
      tenantsHtml = tenantsList.map((ct, index) => {
        const t = ct.tenant
        const prefix = tenantsList.length > 1 ? `<div style="font-weight: bold; margin-top: 10px; margin-bottom: 5px;">Khách thuê ${index + 1}:</div>` : ''
        return `
          ${prefix}
          <div class="info-block" style="margin-left: 20px;">
            Ông/bà: <strong>${t?.full_name || '................................................................'}</strong><br>
            CMND/CCCD số: <strong>${t?.identity_number || '................................'}</strong> &nbsp;&nbsp;&nbsp;&nbsp; cấp ngày: <strong>${t?.identity_date ? formatDate(t.identity_date) : '..........................'}</strong> &nbsp;&nbsp;&nbsp;&nbsp; nơi cấp: <strong>${t?.identity_place || '................................'}</strong><br>
            Thường trú tại: <strong>${t?.permanent_address || '...............................................................................................'}</strong>
          </div>
        `
      }).join('')
    }

    const roomNumber = contract.room?.room_number || contract.room_number || '...'
    const buildingAddress = contract.building_address || '...'
    
    let day = '...'
    let month = '...'
    let year = '...'
    if (contract.start_date) {
      const parts = contract.start_date.split('-')
      if (parts.length === 3) {
        year = parts[0]
        month = String(Number(parts[1]))
        day = String(Number(parts[2]))
      }
    }
    
    const monthsDiff = calculateMonths(contract.start_date, contract.end_date)
    const roomPriceNum = Number(contract.room_price || 0)
    const depositAmountNum = Number(contract.deposit_amount || 0)
    
    const printWindow = window.open('', '_blank')
    if (!printWindow) {
      showAlert('Thông báo', 'Vui lòng cho phép trình duyệt mở popup để xuất PDF.', 'warning')
      return
    }

    const htmlContent = `
      <!DOCTYPE html>
      <html>
      <head>
        <title>Hop_dong_thue_phong_tro_${contract.contract_code}</title>
        <meta charset="utf-8">
        <style>
          @media print {
            body {
              width: 210mm;
              height: 297mm;
              margin: 0;
              padding: 20mm;
            }
            .no-print {
              display: none;
            }
          }
          body {
            font-family: "Times New Roman", Times, serif;
            font-size: 13pt;
            line-height: 1.4;
            color: #000;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
          }
          .header {
            text-align: center;
            margin-bottom: 25px;
          }
          .title {
            font-weight: bold;
            font-size: 15pt;
            text-transform: uppercase;
            margin-top: 20px;
            margin-bottom: 20px;
          }
          .section-title {
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 5px;
          }
          .info-block {
            margin-left: 20px;
            margin-bottom: 15px;
          }
          .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
          }
          .signature-box {
            width: 45%;
            text-align: center;
          }
          .signature-image {
            max-height: 80px;
            max-width: 150px;
            display: block;
            margin: 10px auto;
          }
          table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 15px;
          }
          th, td {
            border: 1px solid black;
            padding: 8px;
            text-align: center;
            font-size: 11pt;
          }
          .footer-note {
            margin-top: 40px;
            font-style: italic;
            text-align: center;
            font-size: 10pt;
            color: #555;
          }
        </style>
      </head>
      <body>
        <div class="header">
          <strong>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</strong><br>
          <strong>Độc lập – Tự do – Hạnh phúc</strong><br>
          <span style="display:inline-block; border-bottom: 1px solid black; width: 120px; margin-top: 5px;"></span>
          <div class="title">HỢP ĐỒNG THUÊ PHÒNG TRỌ</div>
        </div>

        <p>Hôm nay, ngày ${day} tháng ${month} năm ${year}, tại căn nhà số ${buildingAddress}. Chúng tôi ký tên dưới đây gồm có:</p>

        <div class="section-title">BÊN CHO THUÊ PHÒNG TRỌ (gọi tắt là Bên A):</div>
        <div class="info-block">
          Ông/bà (tên chủ hợp đồng) <strong>${landlord?.name || '................................................................'}</strong><br>
          CMND/CCCD số <strong>${landlord?.identity_number || '................................'}</strong> &nbsp;&nbsp;&nbsp;&nbsp; cấp ngày <strong>${landlord?.identity_date ? formatDate(landlord.identity_date) : '..........................'}</strong> &nbsp;&nbsp;&nbsp;&nbsp; nơi cấp <strong>${landlord?.identity_place || '................................'}</strong><br>
          Thường trú tại: <strong>${landlord?.permanent_address || '...............................................................................................'}</strong>
        </div>

        <div class="section-title">BÊN THUÊ PHÒNG TRỌ (gọi tắt là Bên B):</div>
        ${tenantsHtml}

        <p>Sau khi thỏa thuận, hai bên thống nhất như sau:</p>

        <div class="section-title">1. Nội dung thuê phòng trọ</div>
        <p style="margin-left: 20px; text-align: justify;">
          Bên A cho Bên B thuê 01 phòng trọ số <strong>${roomNumber}</strong> tại căn nhà số <strong>${buildingAddress}</strong>. Với thời hạn là: <strong>${monthsDiff}</strong> tháng, giá thuê: <strong>${formatMoneyText(contract.room_price)}</strong> đồng (Bằng chữ: <em>${docSoTien(roomPriceNum)}</em>). Chưa bao gồm chi phí: điện sinh hoạt, nước, dịch vụ.
        </p>

        <div class="section-title">2. Trách nhiệm Bên A</div>
        <ul style="margin-left: 20px; padding-left: 15px;">
          <li>Đảm bảo căn nhà cho thuê không có tranh chấp, khiếu kiện.</li>
          <li>Đăng ký với chính quyền địa phương về thủ tục cho thuê phòng trọ.</li>
        </ul>

        <div class="section-title">3. Trách nhiệm Bên B</div>
        <ul style="margin-left: 20px; padding-left: 15px; text-align: justify;">
          <li>Đặt cọc với số tiền là <strong>${formatMoneyText(contract.deposit_amount)}</strong> đồng (Bằng chữ: <em>${docSoTien(depositAmountNum)}</em>), thanh toán tiền thuê phòng hàng tháng vào ngày <strong>${contract.billing_cycle_day || '……. '}</strong> + tiền điện + nước + dịch vụ.</li>
          <li>Đảm bảo các thiết bị và sửa chữa các hư hỏng trong phòng trong khi sử dụng. Nếu không sửa chữa thì khi trả phòng, bên A sẽ trừ vào tiền đặt cọc, giá trị cụ thể được tính theo giá thị trường.</li>
          <li>Chỉ sử dụng phòng trọ vào mục đích ở, với số lượng tối đa không quá 04 người (kể cả trẻ em); không chứa các thiết bị gây cháy nổ, hàng cấm... cung cấp giấy tờ tùy thân để đăng ký tạm trú theo quy định, giữ gìn an ninh trật tự, nếp sống văn hóa đô thị; không tụ tập nhậu nhẹt, cờ bạc và các hành vi vi phạm pháp luật khác.</li>
          <li>Không được tự ý cải tạo kiếm trúc phòng hoặc trang trí ảnh hưởng tới tường, cột, nền... Nếu có nhu cầu trên phải trao đổi với bên A để được thống nhất</li>
        </ul>

        <div class="section-title">4. Điều khoản thực hiện</div>
        <ul style="margin-left: 20px; padding-left: 15px; text-align: justify;">
          <li>Hai bên nghiêm túc thực hiện những quy định trên trong thời hạn cho thuê, nếu bên A lấy phòng phải báo cho bên B ít nhất 01 tháng, hoặc ngược lại.</li>
          <li>Sau thời hạn cho thuê <strong>${monthsDiff}</strong> tháng nếu bên B có nhu cầu hai bên tiếp tục thương lượng giá thuê để gia hạn hợp đồng bằng miệng hoặc thực hiện như sau.</li>
        </ul>

        <table>
          <thead>
            <tr>
              <th>Số lần gia hạn</th>
              <th>Thời gian gia hạn (tháng)</th>
              <th>Từ ngày</th>
              <th>Đến ngày</th>
              <th>Giá thuê/ tháng (triệu đồng)</th>
              <th>Ký tên</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>1</td>
              <td>&nbsp;</td>
              <td>&nbsp;</td>
              <td>&nbsp;</td>
              <td>&nbsp;</td>
              <td>&nbsp;</td>
            </tr>
            <tr>
              <td>2</td>
              <td>&nbsp;</td>
              <td>&nbsp;</td>
              <td>&nbsp;</td>
              <td>&nbsp;</td>
              <td>&nbsp;</td>
            </tr>
          </tbody>
        </table>

        <div class="signature-section">
          <div class="signature-box">
            <strong>Bên B</strong><br>
            <span style="font-size: 10pt; font-style: italic;">(Ký, ghi rõ họ tên)</span>
            <div style="height: 100px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
              ${contract.tenant_signature_url ? `
                <img src="${contract.tenant_signature_url}" class="signature-image" alt="Chữ ký Bên B" />
                <span style="font-size: 8pt; color: #555; display:block;">Đã ký lúc ${formatDateTime(contract.tenant_signed_at)}</span>
              ` : '<span style="color: #777; font-style: italic;">Chưa ký</span>'}
            </div>
            <strong>${repTenant?.full_name || ''}</strong>
          </div>
          <div class="signature-box">
            <strong>Bên A</strong><br>
            <span style="font-size: 10pt; font-style: italic;">(Ký, ghi rõ họ tên)</span>
            <div style="height: 100px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
              ${landlord?.signature_url ? `
                <img src="${landlord.signature_url}" class="signature-image" alt="Chữ ký Bên A" />
                <span style="font-size: 8pt; color: #555; display:block;">Đã ký</span>
              ` : `
                <span style="border-bottom: 1px dashed #777; width: 120px; margin-top: 40px; display: inline-block;"></span>
              `}
            </div>
            <strong>${landlord?.name || ''}</strong>
          </div>
        </div>

        <div class="footer-note">
          (Hợp đồng này chỉ mang tính chất tham khảo)
        </div>

        <script>
          window.onload = function() {
            setTimeout(function() {
              window.print();
            }, 300);
          }
        </script>
      </body>
      </html>
    `
    printWindow.document.write(htmlContent)
    printWindow.document.close()
  }

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
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={handleExportPDF}
                disabled={isLoading}
                className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#f3c56b]/30 bg-[#f3c56b]/15 px-4 text-sm font-black text-[#f3c56b] transition hover:bg-[#f3c56b]/25 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <FileText className="h-4 w-4" /> Xuất hợp đồng (PDF)
              </button>
              <button
                type="button"
                onClick={onClose}
                className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
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
                <thead className="text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/70">
                  <tr>
                    <th className="py-3 px-4">Khách thuê</th>
                    <th className="py-3 px-4 text-center">Ngày ở</th>
                    <th className="py-3 px-4 text-center">Thời gian tính tiền</th>
                    <th className="py-3 px-4 text-center">Trạng thái</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[#3d2a18]/10">
                  {(contract.contract_tenants || []).map((tenant) => (
                    <tr key={tenant.id || tenant.tenant_id}>
                      <td className="py-3 px-4 font-black">{tenant.tenant?.full_name || tenant.tenant_id}</td>
                      <td className="py-3 px-4 font-black text-center">
                        {formatDate(tenant.join_date)} → {formatDate(tenant.leave_date)}
                      </td>
                      <td className="py-3 px-4 font-black text-center">
                        {formatDate(tenant.billing_start_date)} → {formatDate(tenant.billing_end_date)}
                      </td>
                      <td className="py-3 px-4 font-black text-center">{tenant.is_staying ? 'Đang ở' : 'Đã rời'}</td>
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
            <DetailTile
              label="Lịch sử phòng"
              value={
                <Link to={`/admin/room-movements?contract_id=${contract.id}`} className="inline-flex items-center rounded-full border border-[#0f766e]/15 bg-[#0f766e]/10 px-3 py-1 text-xs font-black text-[#0f5f59] transition hover:bg-[#0f766e]/15">
                  {contract.room_movements_count ?? 0} bản ghi
                </Link>
              }
            />
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
      <ConfirmModal {...confirmState} onCancel={closeConfirm} isLoading={isConfirmLoading} />
    </div>
  )
}
