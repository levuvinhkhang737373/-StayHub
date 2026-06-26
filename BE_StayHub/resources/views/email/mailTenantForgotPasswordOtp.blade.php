<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác minh đặt lại mật khẩu StayHub</title>
</head>
<body style="margin:0;background:#f7f0e5;font-family:Arial,Helvetica,sans-serif;color:#24170d;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f7f0e5;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#fffaf1;border:1px solid #eadcc8;border-radius:24px;overflow:hidden;box-shadow:0 18px 42px rgba(61,42,24,0.12);">
                    <tr>
                        <td style="background:#24170d;padding:28px;color:#fff4df;">
                            <div style="font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#f3c56b;">StayHub Tenant</div>
                            <h1 style="margin:10px 0 0;font-size:26px;line-height:1.25;font-weight:800;">Yêu cầu đặt lại mật khẩu</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.7;font-weight:700;">Xin chào {{ $tenant->full_name ?: $tenant->username }},</p>
                            <p style="margin:0 0 22px;font-size:15px;line-height:1.7;color:#5f4b38;">Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản khách thuê StayHub của bạn. Vui lòng sử dụng mã xác minh (OTP) dưới đây để tiến hành đặt lại mật khẩu:</p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0 10px;">
                                <tr>
                                    <td style="padding:20px 16px;background:#24170d;border-radius:16px;text-align:center;">
                                        <div style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#f3c56b;">Mã xác minh (OTP)</div>
                                        <div style="margin-top:12px;font-size:36px;font-weight:900;letter-spacing:6px;color:#fff4df;">{{ $otp }}</div>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:22px 0 10px;font-size:14px;line-height:1.7;color:#6f6254;font-weight:700;">Mã xác minh này có hiệu lực trong vòng 15 phút.</p>
                            <p style="margin:0 0 22px;font-size:14px;line-height:1.7;color:#6f6254;">Vì lý do bảo mật, tuyệt đối không chia sẻ mã này cho bất kỳ ai khác.</p>
                            <hr style="border:0;border-top:1px solid #eadcc8;margin:24px 0;">
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#8b5e34;">Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này hoặc liên hệ bộ phận hỗ trợ StayHub nếu bạn lo ngại về an toàn tài khoản.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
