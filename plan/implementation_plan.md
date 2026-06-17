# Kế hoạch triển khai tính năng Hóa đơn (Invoice) cho BE_StayHub

Tài liệu này mô tả chi tiết phương thức tính tiền phòng, tiền dịch vụ dựa trên các Models hiện có, các API cần phát triển, và cơ chế phát thông tin realtime khi phát hành hóa đơn hoặc thanh toán thành công.

---

## 1. Công thức tính tiền (Ưu tiên thông tin trong Hợp đồng)

Hệ thống sẽ tính tiền tự động dựa trên các thông tin của Hợp đồng (`Contract`), Thiết bị đo (`MeterDevice`), Chỉ số đo (`MeterReading`), Dịch vụ (`Service`), và Xe (`Vehicle`). Theo đúng yêu cầu, các giá trị thỏa thuận trong hợp đồng sẽ được ưu tiên hàng đầu.

### A. Tiền phòng (Room Rent)
*   **Chu kỳ chốt tiền cố định**: Hệ thống chốt tiền chung vào **ngày cuối cùng của tháng**.
    *   Kỳ tính tiền mặc định từ ngày 1 đầu tháng (`period_start`) đến ngày cuối cùng của tháng (`period_end`).
    *   Hạn thanh toán (`due_date`) mặc định là ngày 5 của tháng tiếp theo (Admin có thể thay đổi).
*   **Công thức tính**:
    *   **Trường hợp 1 (Ở trọn tháng)**: $T_{phong} = P_{hop\_dong}$
    *   **Trường hợp 2 (Khách vào ở giữa tháng đầu tiên)**: $T_{phong} = \frac{P_{hop\_dong}}{D_{total}} \times D_{actual}$
*   **Chi tiết**:
    *   $P_{hop\_dong}$: Đơn giá thuê phòng trong hợp đồng (`contracts.room_price`).
    *   $D_{total}$: Tổng số ngày của tháng chốt tiền (sử dụng thư viện Carbon để tự động xác định số ngày thực tế của tháng đó: 30 ngày, 31 ngày, hoặc 28/29 ngày đối với tháng 2 năm nhuận).
    *   $D_{actual}$: Số ngày thực tế ở tính từ ngày hợp đồng bắt đầu (`contracts.start_date`) đến hết tháng chốt tiền (`period_end`). Tính bằng: `period_end - contracts.start_date + 1`.
    *   *Ví dụ ở tháng 2*: Nếu hợp đồng bắt đầu từ 15/02/2026 (tháng 2 có 28 ngày):
        *   $D_{total} = 28$ ngày.
        *   $D_{actual} = 28 - 15 + 1 = 14$ ngày.
        *   Tiền phòng = $\frac{P_{hop\_dong}}{28} \times 14$.
        *   Nếu vào năm nhuận 2028 (tháng 2 có 29 ngày), hệ thống tự động chia cho 29 và nhân với 15 ngày thực tế.
*   **Mã hóa đơn đầu mục**: `ITEM_TYPE_ROOM` (1).

### B. Tiền Điện & Nước (Điện nước tính theo chỉ số)
*   **Loại hình**: Dịch vụ có phương thức tính `CHARGE_METHOD_BY_METER` (1).
*   **Công thức**: $T_{dien\_nuoc} = (Reading_{current} - Reading_{previous}) \times P_{dich\_vu}$
*   **Chi tiết**:
    *   $Reading_{current}, Reading_{previous}$: Chỉ số mới và cũ lấy từ bảng `meter_readings` của kỳ hóa đơn (tháng/năm) tương ứng với đồng hồ (`MeterDevice`) lắp tại phòng đó. Lượng tiêu thụ tương ứng là `meter_readings.consumption`.
    *   $P_{dich\_vu}$: Đơn giá dịch vụ áp dụng cho tòa nhà chứa phòng đó tại thời điểm tính tiền, lấy từ bảng `service_prices` tương ứng với `service_id` và `building_id`.
*   **Mã hóa đơn đầu mục**: Điện là `ITEM_TYPE_ELECTRIC` (2), Nước là `ITEM_TYPE_WATER` (3).

### C. Dịch vụ tính theo số người (By Person)
*   **Loại hình**: Dịch vụ có phương thức tính `CHARGE_METHOD_BY_PERSON` (2) (Ví dụ: Phí rác, phí nước sinh hoạt tính theo đầu người).
*   **Công thức**: $T_{dich\_vu\_nguoi} = N_{tenant} \times P_{dich\_vu}$
*   **Chi tiết**:
    *   $N_{tenant}$: Số lượng khách thuê đang ở thực tế trong phòng tại thời điểm tính tiền. Đếm số bản ghi trong bảng `contract_tenants` có `contract_id` tương ứng và trạng thái đang ở `is_staying = true`.
    *   $P_{dich\_vu}$: Đơn giá dịch vụ của tòa nhà trong `service_prices`.
*   **Mã hóa đơn đầu mục**: `ITEM_TYPE_TRASH` (5) hoặc loại dịch vụ tương ứng.

### D. Dịch vụ tính theo phòng / Cố định (By Room / Fixed)
*   **Loại hình**: Dịch vụ tính `CHARGE_METHOD_BY_ROOM` (3) hoặc `CHARGE_METHOD_FIXED` (5) (Ví dụ: Internet, vệ sinh chung, phí quản lý cố định).
*   **Công thức**: $T_{dich\_vu\_phong} = 1 \times P_{dich\_vu}$
*   **Chi tiết**:
    *   $P_{dich\_vu}$: Đơn giá dịch vụ của tòa nhà trong `service_prices`.
*   **Mã hóa đơn đầu mục**: Internet là `ITEM_TYPE_INTERNET` (4), Vệ sinh/Phụ thu khác là `ITEM_TYPE_SURCHARGE` (7) hoặc tương ứng.

### E. Dịch vụ tính theo xe (By Vehicle)
*   **Loại hình**: Phí gửi xe của khách thuê đăng ký trong hợp đồng `CHARGE_METHOD_BY_VEHICLE` (4).
*   **Công thức**: $T_{gui\_xe} = \sum_{xe\_active} P_{xe}$
*   **Chi tiết**:
    *   $P_{xe}$: Đơn giá gửi xe của từng xe đăng ký trong hợp đồng (`contract_vehicles.monthly_fee` với trạng thái `is_active = true`), **ưu tiên** giá thỏa thuận cụ thể của từng xe trong hợp đồng thay vì đơn giá chung của tòa nhà.
    *   Số lượng là số xe đang đăng ký hoạt động trong bảng `contract_vehicles`.
*   **Mã hóa đơn đầu mục**: `ITEM_TYPE_PARKING` (6).

### F. Nợ cũ (Previous Debt)
*   **Công thức**: $T_{no\_cu} = \sum RemainingAmount_{invoices\_unpaid}$
*   **Chi tiết**:
    *   Hệ thống tự động quét các hóa đơn của các kỳ trước thuộc hợp đồng này đang có trạng thái chưa thanh toán (`STATUS_UNPAID` = 2) hoặc thanh toán một phần (`STATUS_PARTIALLY_PAID` = 3) và cộng dồn số tiền còn nợ (`remaining_amount`) vào mục Nợ cũ của hóa đơn mới.
*   **Mã hóa đơn đầu mục**: `ITEM_TYPE_OLD_DEBT` (9).

### G. Phụ thu / Giảm trừ / Điều chỉnh tăng giảm (Surcharges & Discounts)
*   Do Admin nhập tay thêm khi kiểm tra và lập hóa đơn nháp.
*   **Mã hóa đơn đầu mục**: `ITEM_TYPE_SURCHARGE` (7), `ITEM_TYPE_DISCOUNT` (8) (được trừ đi), `ITEM_TYPE_ADJUST_INCREASE` (10), `ITEM_TYPE_ADJUST_DECREASE` (11) (được trừ đi).

### H. Công thức tính tổng tiền hóa đơn (Total Invoice Amount)
$$\text{total\_amount} = T_{phong} + \sum T_{dien\_nuoc} + \sum T_{dich\_vu\_nguoi} + \sum T_{dich\_vu\_phong} + T_{gui\_xe} + T_{no\_cu} + Surcharges - Discounts$$
$$\text{remaining\_amount} = \text{total\_amount} - \text{paid\_amount}$$

---

## 2. Kiểm tra cấu trúc CSDL cho Webhook thanh toán hóa đơn

> [!NOTE]
> **Kết quả kiểm tra**: CSDL hiện tại **đã có sẵn bảng `payments`** với cấu trúc hoàn chỉnh để ghi nhận lịch sử giao dịch và tích hợp webhook của hóa đơn. Không cần tạo thêm bảng mới.

Cấu trúc bảng `payments` đang có sẵn bao gồm:
*   `id`: Khóa chính.
*   `payment_code`: Mã thanh toán độc bản (ví dụ: `PAY-2026-06-0001`).
*   `invoice_id`: Liên kết ngoại khóa với bảng `invoices`.
*   `amount`: Số tiền thanh toán.
*   `payment_date`: Thời điểm giao dịch.
*   `payment_method`: Phương thức thanh toán (1 = Tiền mặt, 2 = Chuyển khoản).
*   `transaction_reference`: Mã tham chiếu giao dịch ngân hàng từ SePay (dùng để kiểm tra trùng lặp giao dịch).
*   `status`: Trạng thái thanh toán (1 = Chờ xác nhận, 2 = Đã xác nhận, 3 = Đã hủy).
*   `proof_image`: Ảnh minh chứng chuyển khoản (nếu khách upload thủ công).
*   `note`: Ghi chú giao dịch.
*   `collected_by`: ID admin xác nhận (bằng null nếu thanh toán tự động qua SePay).

---

## 3. Thiết kế luồng Realtime (Websockets qua Laravel Reverb)

Để đảm bảo trải nghiệm người dùng liền mạch và tức thời (realtime):

### A. Phát hành hóa đơn (Invoice Issued)
*   **Hành động**: Khi Admin bấm phát hành hóa đơn (`/admin/invoices/{id}/issue`).
*   **Realtime**: Hệ thống kích hoạt Sự kiện `App\Events\InvoiceIssued` gửi tới kênh riêng tư của từng Tenant trong phòng đó (`tenant.{tenantId}`).
*   **Hiệu quả**: Ứng dụng phía Tenant sẽ ngay lập tức nhận được thông tin và cập nhật giao diện hiển thị hóa đơn mới mà không cần F5. Đồng thời gửi thông báo hệ thống (Database Notification) cho họ.

### B. Thanh toán qua mã QR động (QR Code Payment Screen Live updates)
*   **Hành động**: Khách thuê mở xem hóa đơn trên App Tenant. Màn hình hiển thị mã QR thanh toán VietQR động (chứa thông tin số tài khoản, số tiền và nội dung chuyển khoản là mã hóa đơn).
*   **Kịch bản**: Khách thuê quét mã QR và thực hiện chuyển khoản trên ứng dụng ngân hàng.
*   **Webhook SePay**: Hệ thống nhận webhook thanh toán tự động, so khớp mã hóa đơn, ghi nhận bản ghi `Payment` hợp lệ ở trạng thái `Confirmed`, cập nhật hóa đơn sang `PAID`/`PARTIALLY_PAID`.
*   **Realtime**: 
    1.  Kích hoạt Sự kiện `App\Events\InvoicePaid` gửi tới kênh `tenant.{tenantId}` của toàn bộ thành viên trong phòng đó. 
    2.  Màn hình thanh toán hóa đơn của Tenant đang mở sẽ lập tức nhận được tín hiệu và tự động chuyển giao diện sang trạng thái **"Đã thanh toán thành công"** kèm hiệu ứng chúc mừng (realtime update).
    3.  Tạo Database Notification gửi tới Tenant báo thanh toán thành công.

### C. Thông báo cho Admin (Admin Payment Notification)
*   **Hành động**: Khi hóa đơn được thanh toán thành công (qua SePay hoặc khi Admin khác duyệt).
*   **Realtime**: Hệ thống gửi Sự kiện tới kênh chung của Admin (`admin-maintenance`).
*   **Hiệu quả**: Admin đang quản lý hệ thống sẽ lập tức nhận được thông báo: *"Phòng A101 của tòa nhà StayHub Sài Gòn Central đã thanh toán thành công hóa đơn tháng 05/2026 số tiền 5.720.000 VND."*

---

## 4. Danh sách các thay đổi đề xuất

### A. Backend - API Routes (`BE_StayHub/routes/api.php`)
Khai báo các route quản lý hóa đơn cho Admin và Tenant.

### B. Laravel Events (`BE_StayHub/app/Events`)
*   `[NEW] App\Events\InvoiceIssued`: Phát tín hiệu hóa đơn mới tới các Tenant trong phòng.
*   `[NEW] App\Events\InvoicePaid`: Phát tín hiệu cập nhật trạng thái hóa đơn đã thanh toán tới cả Tenant và Admin.

### C. Controllers & Resources
*   `[NEW] App\Http\Controllers\Admin\InvoiceController`: Nghiệp vụ lập và quản lý hóa đơn.
*   `[NEW] App\Http\Controllers\Tenant\InvoiceController`: Nghiệp vụ xem hóa đơn và gửi minh chứng thanh toán của khách thuê.
*   `[MODIFY] App\Http\Controllers\Webhook\SePayWebhookController`: Cải tiến để tự động phân tích và xử lý giao dịch nhận tiền theo mã hóa đơn, kích hoạt sự kiện realtime.
*   Tạo các Resources tương ứng để chuẩn hóa dữ liệu trả về cho API.
