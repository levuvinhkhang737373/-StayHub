<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông tin đăng nhập khách thuê StayHub</title>
</head>
<body style="margin:0;background:#f7f0e5;font-family:Arial,Helvetica,sans-serif;color:#24170d;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f7f0e5;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#fffaf1;border:1px solid #eadcc8;border-radius:24px;overflow:hidden;box-shadow:0 18px 42px rgba(61,42,24,0.12);">
                    <tr>
                        <td style="background:#24170d;padding:28px;color:#fff4df;">
                            <div style="font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#f3c56b;">StayHub Tenant</div>
                            <h1 style="margin:10px 0 0;font-size:26px;line-height:1.25;font-weight:800;">Tài khoản khách thuê đã được tạo</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.7;font-weight:700;">Xin chào {{ $tenant->full_name ?: $tenant->username }},</p>
                            <p style="margin:0 0 22px;font-size:15px;line-height:1.7;color:#5f4b38;">Tài khoản khách thuê StayHub của bạn đã được tạo. Vui lòng dùng thông tin dưới đây để đăng nhập cổng khách thuê.</p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0 10px;">
                                <tr>
                                    <td style="padding:14px 16px;background:#fff4df;border:1px solid #eadcc8;border-radius:16px;">
                                        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#8b5e34;">Tên đăng nhập</div>
                                        <div style="margin-top:6px;font-size:17px;font-weight:800;color:#24170d;">{{ $tenant->username }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;background:#fff4df;border:1px solid #eadcc8;border-radius:16px;">
                                        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#8b5e34;">Email</div>
                                        <div style="margin-top:6px;font-size:17px;font-weight:800;color:#24170d;">{{ $tenant->email }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;background:#24170d;border-radius:16px;">
                                        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#f3c56b;">Mật khẩu tạm thời</div>
                                        <div style="margin-top:8px;font-size:24px;font-weight:900;letter-spacing:1px;color:#fff4df;">{{ $password }}</div>
                                    </td>
                                </tr>
                            </table>

                            <div style="margin:26px 0;text-align:center;">
                                <a href="{{ $loginUrl }}" style="display:inline-block;background:#f3c56b;color:#24170d;text-decoration:none;font-size:15px;font-weight:900;padding:14px 24px;border-radius:16px;">Đăng nhập cổng khách thuê</a>
                            </div>

                            <p style="margin:0 0 10px;font-size:14px;line-height:1.7;color:#6f6254;">Vì lý do bảo mật, hãy đổi mật khẩu sau lần đăng nhập đầu tiên và không chia sẻ mật khẩu này cho người khác.</p>
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#8b5e34;">Nếu bạn không yêu cầu tài khoản này, vui lòng liên hệ quản lý tòa nhà hoặc bộ phận hỗ trợ StayHub.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
