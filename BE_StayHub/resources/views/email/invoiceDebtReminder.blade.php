<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhắc thanh toán hóa đơn StayHub</title>
</head>
<body style="margin:0;background:#f7f0e5;font-family:Arial,Helvetica,sans-serif;color:#24170d;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f7f0e5;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:660px;background:#fffaf1;border:1px solid #eadcc8;border-radius:24px;overflow:hidden;box-shadow:0 18px 42px rgba(61,42,24,0.12);">
                    <tr>
                        <td style="background:#24170d;padding:28px;color:#fff4df;">
                            <div style="font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#f3c56b;">StayHub Billing</div>
                            <h1 style="margin:10px 0 0;font-size:26px;line-height:1.25;font-weight:800;">Nhắc thanh toán tiền phòng tháng {{ $billingPeriod }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.7;font-weight:700;">Xin chào {{ $tenant->full_name ?: $tenant->username }},</p>
                            <p style="margin:0 0 22px;font-size:15px;line-height:1.7;color:#5f4b38;">StayHub ghi nhận hóa đơn tiền phòng của bạn vẫn còn số tiền cần thanh toán. Vui lòng kiểm tra và hoàn tất thanh toán trong thời gian sớm nhất.</p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0 10px;">
                                <tr>
                                    <td style="padding:14px 16px;background:#fff4df;border:1px solid #eadcc8;border-radius:16px;">
                                        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#8b5e34;">Phòng / Tòa nhà</div>
                                        <div style="margin-top:6px;font-size:17px;font-weight:800;color:#24170d;">Phòng {{ $invoice->room?->room_number ?? 'chưa rõ' }}{{ $invoice->room?->building?->name ? ' · '.$invoice->room?->building?->name : '' }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;background:#fff4df;border:1px solid #eadcc8;border-radius:16px;">
                                        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#8b5e34;">Mã hóa đơn</div>
                                        <div style="margin-top:6px;font-size:17px;font-weight:800;color:#24170d;">{{ $invoice->invoice_code }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;background:#24170d;border-radius:16px;">
                                        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#f3c56b;">Số tiền còn thiếu</div>
                                        <div style="margin-top:8px;font-size:26px;font-weight:900;letter-spacing:0.3px;color:#fff4df;">{{ $amountText }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;background:#fff4df;border:1px solid #eadcc8;border-radius:16px;">
                                        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#8b5e34;">Hạn thanh toán</div>
                                        <div style="margin-top:6px;font-size:17px;font-weight:800;color:#24170d;">{{ optional($invoice->due_date)->format('d/m/Y') ?? 'Chưa cập nhật' }}</div>
                                    </td>
                                </tr>
                            </table>



                            @if ($paymentQrUrl)
                                <div style="margin:0 0 24px;padding:18px;background:#ffffff;border:1px solid #eadcc8;border-radius:18px;text-align:center;">
                                    <div style="font-size:12px;font-weight:900;letter-spacing:1.4px;text-transform:uppercase;color:#8b5e34;">QR thanh toán nhanh</div>
                                    <img src="{{ $paymentQrUrl }}" alt="QR thanh toán hóa đơn {{ $invoice->invoice_code }}" style="display:block;margin:14px auto 0;max-width:260px;width:100%;border-radius:14px;border:1px solid #eadcc8;">
                                </div>
                            @endif

                            <p style="margin:0 0 10px;font-size:14px;line-height:1.7;color:#6f6254;">Nếu bạn đã thanh toán, vui lòng bỏ qua email này hoặc tải minh chứng thanh toán trong cổng khách thuê để ban quản lý xác nhận.</p>
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#8b5e34;">Vui lòng không trả lời trực tiếp email này.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
