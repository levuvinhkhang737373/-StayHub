# TÀI LIỆU PHÂN TÍCH CÁC PHÂN HỆ NGƯỜI DÙNG TRÊN DI ĐỘNG STAYHUB

Tài liệu này chi tiết hóa các phân hệ chức năng dành cho người dùng trên nền tảng di động (Ứng dụng di động Flutter dành cho **Khách thuê / Tenant** và **Quản lý / Manager**). Tài liệu tuân thủ nghiêm ngặt ràng buộc thực tế: **Di động chỉ có vai trò Tenant và Manager, không hỗ trợ nhận diện FaceID hay vai trò Super Admin**.

Mỗi chức năng được trình bày đầy đủ theo ba phần: **Mô tả**, **Thao tác** và **Ràng buộc** nhằm phục vụ thiết kế kỹ thuật và xây dựng báo cáo.

---

# PHẦN I: PHÂN HỆ KHÁCH THUÊ (TENANT SUBSYSTEM)

## 3.1. Phân Hệ Khách Thuê Trên Mobile

### 3.1.1. Đăng nhập tài khoản
*   **Mô tả:** Khách thuê đăng nhập vào ứng dụng di động bằng tài khoản (Tên đăng nhập/SĐT/Email) và mật khẩu được cung cấp khi làm hợp đồng hoặc tự thiết lập để truy cập các dịch vụ của phòng thuê.
*   **Thao tác:**
    1.  Người dùng mở ứng dụng di động, chọn vai trò **Khách thuê**.
    2.  Nhập thông tin bao gồm: Tên đăng nhập (hoặc Email/Số điện thoại) và Mật khẩu.
    3.  Nhấn nút **Đăng nhập** để gửi yêu cầu xác thực tới API của Laravel Backend (`POST /api/v1/tenant/login`).
    4.  Nếu đăng nhập thành công, hệ thống lưu token/cookie session, bắt đầu kết nối WebSocket (Laravel Reverb) và điều hướng đến màn hình Dashboard Khách thuê.
*   **Ràng buộc:**
    *   Các trường thông tin không được để trống (validator ngăn chặn nhấn nút đăng nhập nếu form chưa hợp lệ).
    *   Tài khoản đăng nhập phải tồn tại trong cơ sở dữ liệu và đang ở trạng thái hoạt động (`status = 1` - Active / Renting). Nếu tài khoản bị khóa, hệ thống hiển thị thông báo lỗi 403 cụ thể.
    *   Nếu nhập sai thông tin đăng nhập, hệ thống trả về lỗi 401: *"Tên đăng nhập hoặc mật khẩu không chính xác"*.
    *   Thiết bị di động sẽ lưu giữ session đăng nhập qua cookie/token để duy trì phiên đăng nhập mà không cần nhập lại mỗi lần mở ứng dụng.

### 3.1.2. Quên & Đặt lại mật khẩu
*   **Mô tả:** Khách thuê tự khôi phục mật khẩu thông qua mã xác minh OTP gửi về địa chỉ Email đã đăng ký để bảo mật tài khoản khi quên mật khẩu.
*   **Thao tác:**
    1.  Tại màn hình đăng nhập, người dùng nhấn chọn **Quên mật khẩu**.
    2.  Nhập địa chỉ Email đã đăng ký trong hệ thống để yêu cầu nhận mã OTP (`POST /api/v1/tenant/forgot-password`).
    3.  Sau khi có mã OTP gửi về hòm thư, người dùng nhập mã đó cùng với **Mật khẩu mới** và **Xác nhận mật khẩu mới** (`POST /api/v1/tenant/reset-password`).
    4.  Nhấn nút xác nhận để hoàn tất đổi mật khẩu và quay lại màn hình đăng nhập.
*   **Ràng buộc:**
    *   Email yêu cầu khôi phục phải đúng định dạng Regex và đã được đăng ký cho một Khách thuê đang hoạt động trong hệ thống.
    *   Mã OTP gồm đúng 6 chữ số và chỉ có giá trị hiệu lực trong vòng **15 phút**. Hết thời gian này, bản ghi OTP tự động bị hủy trên backend.
    *   Giao diện ứng dụng di động khóa nút gửi lại mã (Resend OTP) bằng bộ đếm ngược cooldown **60 giây** để hạn chế spam email.
    *   Mật khẩu mới và mật khẩu xác nhận phải trùng khớp hoàn toàn, đồng thời độ dài tối thiểu phải từ **6 ký tự**.
    *   Mã OTP sau khi được xác minh và đổi mật khẩu thành công sẽ bị xóa lập tức khỏi cơ sở dữ liệu để ngăn ngừa việc tái sử dụng.

### 3.1.3. Trang chủ tổng quan (Tenant Dashboard)
*   **Mô tả:** Hiển thị tổng quan các thông tin phòng thuê hiện tại, số dư công nợ hóa đơn, trạng thái hợp đồng thuê và các phím tắt truy cập nhanh vào các tính năng dịch vụ.
*   **Thao tác:**
    1.  Màn hình hiển thị tự động sau khi đăng nhập thành công.
    2.  Người dùng có thể kéo từ trên xuống (Pull-to-refresh) để làm mới dữ liệu.
    3.  Bấm chọn các thẻ hiển thị nhanh (Ví dụ: Thẻ "Hóa Đơn", "Điện Nước", "Sửa Chữa", "Hợp Đồng") để điều hướng nhanh đến màn hình chức năng.
*   **Ràng buộc:**
    *   Dữ liệu được cập nhật thời gian thực thông qua API `GET /api/v1/tenant/me` và lắng nghe các sự kiện đẩy từ Laravel Reverb WebSocket.
    *   Nếu khách thuê chưa có phòng hoạt động hoặc hợp đồng thuê phòng chưa được ký, hệ thống hiển thị banner cảnh báo yêu cầu ký hợp đồng ngay tại đầu trang Dashboard.

### 3.1.4. Xem & Ký hợp đồng điện tử
*   **Mô tả:** Khách thuê xem chi tiết nội dung bản hợp đồng thuê phòng (tiền phòng, cọc, kỳ thanh toán, điều khoản bên A - bên B) và thực hiện ký hợp đồng bằng chữ ký vẽ tay điện tử trực tiếp trên màn hình điện thoại.
*   **Thao tác:**
    1.  Người dùng truy cập mục **Hợp đồng**, xem bản nháp hợp đồng được tạo bởi Admin.
    2.  Nhấn chọn nút **Ký hợp đồng** để mở màn hình khai báo thông tin pháp lý bên B và ký tên.
    3.  Điền thông tin pháp lý bên B: Họ tên, Số CMND/CCCD/Hộ chiếu, Ngày cấp, Nơi cấp, Địa chỉ thường trú. (Mặc định được tự động điền sẵn từ hồ sơ cá nhân để tiết kiệm thời gian).
    4.  Đặt ngón tay hoặc bút cảm ứng và vẽ chữ ký tay của mình vào khung vẽ cảm ứng (Canvas drawing pad). Có thể chọn **Hoàn tác** để vẽ lại nét trước đó, hoặc **Xóa vẽ** để xóa toàn bộ chữ ký vẽ lại từ đầu.
    5.  Tích chọn checkbox đồng ý chịu trách nhiệm pháp lý và nhấn **XÁC NHẬN KÝ HỢP ĐỒNG** (`POST /api/v1/tenant/contracts/{id}/sign`).
*   **Ràng buộc:**
    *   Thông tin pháp lý bắt buộc: Họ tên không trống, Số định danh CCCD tối thiểu 9 ký tự, Ngày cấp phải nhỏ hơn ngày hiện tại, Nơi cấp và Địa chỉ thường trú không được bỏ trống.
    *   Khung vẽ chữ ký tay cảm ứng bắt buộc phải có nét vẽ (`_points` không rỗng). Khung vẽ xuất dữ liệu dưới dạng byte ảnh PNG nền trắng để truyền lên backend chèn trực tiếp vào bản hợp đồng PDF của hệ thống.
    *   Người dùng bắt buộc phải tích chọn checkbox đồng ý với các điều khoản chịu trách nhiệm pháp lý mới có thể kích hoạt nút gửi đi.
    *   Khi ký thành công, trạng thái hợp đồng chuyển thành "Đang hoạt động" (Active), trạng thái phòng chuyển sang "Đang ở", và thông tin định danh của khách thuê sẽ tự động cập nhật đồng bộ vào bảng dữ liệu cá nhân cư dân.

### 3.1.5. Theo dõi chỉ số điện nước
*   **Mô tả:** Khách thuê theo dõi chỉ số tiêu thụ điện, nước hàng tháng, biểu đồ xu hướng biến động đơn giá và hình ảnh minh chứng chỉ số thực tế do quản lý tòa nhà ghi nhận lúc chốt số.
*   **Thao tác:**
    1.  Người dùng chọn tab **Chỉ số Điện Nước** trên ứng dụng di động.
    2.  Xem biểu đồ xu hướng đơn giá dịch vụ điện/nước thay đổi theo thời gian.
    3.  Lọc xem lịch sử chỉ số ghi nhận theo từng tháng: Chỉ số cũ, Chỉ số mới, Tiêu thụ thực tế (kWh hoặc m³) và Đơn giá áp dụng.
    4.  Bấm chọn nút **Xem ảnh minh chứng** để kiểm tra ảnh đồng hồ công tơ thực tế do Admin chụp tại thời điểm chốt chỉ số.
*   **Ràng buộc:**
    *   Biểu đồ xu hướng đơn giá tự động lấy tối đa 5 bản ghi thay đổi đơn giá gần nhất từ backend để vẽ đồ thị đường thẳng (Line Chart).
    *   Ảnh minh chứng đồng hồ điện/nước được tải từ hệ thống lưu trữ đám mây (MinIO/S3), nếu ảnh bị lỗi hoặc không tồn tại sẽ hiển thị biểu tượng thay thế mặc định (broken image helper).

### 3.1.6. Quản lý & Thanh toán hóa đơn
*   **Mô tả:** Khách thuê quản lý danh sách hóa đơn theo kỳ, theo dõi trạng thái công nợ và thực hiện thanh toán trực tuyến nhanh qua mã QR chuyển khoản VietQR tự động.
*   **Thao tác:**
    1.  Người dùng chọn mục **Hóa đơn** trên ứng dụng di động.
    2.  Xem danh sách hóa đơn phân loại theo tab **Chưa thanh toán** và **Đã thanh toán**.
    3.  Nhấn vào hóa đơn cụ thể để xem chi tiết chi phí (Tiền phòng, điện, nước, dịch vụ kèm theo).
    4.  Nhấn nút **Thanh toán ngay** để hiển thị mã QR VietQR động và thông tin chuyển khoản (Ngân hàng, Số tài khoản, Tên chủ tài khoản, Số tiền, Nội dung chuyển khoản).
    5.  Bấm chọn **Sao chép thông tin** chuyển khoản hoặc quét mã QR trên ứng dụng ngân hàng.
*   **Ràng buộc:**
    *   Hóa đơn chưa thanh toán hiển thị rõ hạn nợ (Due Date) và số tiền còn lại cần thanh toán.
    *   Mã QR VietQR được tạo tự động với nội dung chuyển khoản là mã hóa đơn duy nhất (Invoice Code) và số tiền chính xác cần đóng.
    *   Khi người dùng chuyển khoản đúng nội dung, hệ thống thông qua tích hợp SePay Webhook tự động khớp lệnh thanh toán, cập nhật trạng thái hóa đơn thành "Đã thanh toán" tức thì.
    *   Giao diện hóa đơn tích hợp kết nối WebSocket để tự động tải lại trạng thái thanh toán theo thời gian thực (Real-time update) ngay khi có thông báo `invoice_paid` hoặc `invoice_reissued` từ backend mà người dùng không cần tải lại trang thủ công.

### 3.1.7. Báo cáo sự cố & Yêu cầu sửa chữa
*   **Mô tả:** Khách thuê gửi báo cáo sự cố hỏng hóc cơ sở vật chất trong phòng thuê (như hỏng điện, rò nước, đồ gia dụng hư hại) kèm ảnh chụp thực tế và đánh giá chất lượng sau khi hoàn thành.
*   **Thao tác:**
    1.  Người dùng truy cập mục **Bảo trì/Sự cố**, nhấn nút **+** để tạo yêu cầu mới.
    2.  Nhập thông tin: **Tiêu đề** và **Mô tả chi tiết sự cố**.
    3.  Nhấp chọn chụp ảnh hoặc đính kèm ảnh minh chứng sự cố từ thiết bị di động.
    4.  Nhấn nút **Gửi yêu cầu** để gửi lên hệ thống (`POST /api/v1/tenant/maintenance-requests`).
    5.  Khi sự cố đã được sửa chữa xong, nhấn nút **Gửi phản hồi / đánh giá** để nhập nhận xét chất lượng phục vụ.
*   **Ràng buộc:**
    *   Tiêu đề và Mô tả chi tiết sự cố không được để trống để đảm bảo kỹ thuật viên nắm rõ hiện trạng trước khi đến.
    *   Được phép đính kèm tối đa 1 ảnh minh chứng trạng thái hư hỏng (Before Image).
    *   Sau khi kỹ thuật viên sửa chữa xong và cập nhật trạng thái "Đã hoàn thành" (`status = 4`), giao diện hiển thị thêm ảnh sau khi sửa (After Image) để khách thuê đối soát đối chứng.
    *   Form đánh giá chất lượng chỉ hiển thị khi trạng thái yêu cầu là "Đã hoàn thành", bắt buộc không được bỏ trống khi gửi và chỉ được gửi phản hồi một lần duy nhất cho mỗi yêu cầu.

### 3.1.8. Trò chuyện trực tuyến với quản lý (Realtime Chat)
*   **Mô tả:** Khách thuê nhắn tin trực tiếp và gửi ảnh trao đổi thông tin thời gian thực với Quản lý tòa nhà (Admin) khi có thắc mắc hoặc cần hỗ trợ khẩn cấp.
*   **Thao tác:**
    1.  Người dùng mở màn hình **Chat** trên ứng dụng di động.
    2.  Nhập nội dung tin nhắn vào ô soạn thảo.
    3.  Nhấp chọn đính kèm tối đa 5 hình ảnh từ thư viện thiết bị.
    4.  Nhấn nút **Gửi** để trò chuyện thời gian thực.
*   **Ràng buộc:**
    *   Hệ thống duy trì kết nối WebSocket liên tục khi mở màn hình chat để truyền nhận tin nhắn tức thì (`chat_message_sent`).
    *   Tin nhắn chỉ gửi được khi có nội dung văn bản (đã cắt bỏ khoảng trắng đầu/cuối) hoặc có hình ảnh đính kèm.
    *   Số lượng ảnh đính kèm tối đa cho một lượt gửi là **5 hình ảnh**. Các ảnh được nén dung lượng và kích thước tự động trước khi tải lên để tiết kiệm băng thông.
    *   Có trạng thái thông báo tin nhắn "Đang gửi..." (Optimistic UI) và chuyển sang hiển thị thời gian gửi chính xác khi đã lưu vào cơ sở dữ liệu thành công.
    *   Khi đối phương đã xem tin nhắn, trạng thái "Đã đọc" sẽ được cập nhật real-time thông qua sự kiện WebSocket Read.

### 3.1.9. Thay đổi hồ sơ & Mật khẩu
*   **Mô tả:** Khách thuê chỉnh sửa các thông tin cá nhân cơ bản (ảnh đại diện, email, SĐT) và thực hiện đổi mật khẩu tài khoản định kỳ.
*   **Thao tác:**
    1.  Người dùng truy cập màn hình **Cài đặt** (Settings).
    2.  *Avatar:* Nhấn vào ảnh đại diện -> Chọn ảnh mới từ thư viện ảnh thiết bị để tải lên.
    3.  *Email/SĐT:* Nhấn nút chỉnh sửa -> Nhập giá trị mới vào ô nhập liệu -> Xác nhận cập nhật.
    4.  *Đổi mật khẩu:* Nhập Mật khẩu hiện tại, Mật khẩu mới và Nhập lại mật khẩu mới để đổi mật khẩu.
*   **Ràng buộc:**
    *   **Cập nhật Số điện thoại:** Bắt buộc nhập đúng định dạng số điện thoại Việt Nam (10 chữ số, bắt đầu bằng đầu số hợp lệ của các nhà mạng: 03/05/07/08/09).
    *   **Cập nhật Email:** Bắt buộc tuân thủ đúng định dạng Regex của địa chỉ thư điện tử tiêu chuẩn.
    *   **Thay đổi Mật khẩu:** 
        *   Mật khẩu hiện tại nhập vào phải khớp với mật khẩu cũ lưu trong database (kiểm tra qua `Hash::check`).
        *   Mật khẩu mới phải có độ dài tối thiểu từ **6 ký tự**.
        *   Mật khẩu mới và xác nhận mật khẩu mới phải khớp nhau hoàn toàn, đồng thời mật khẩu mới không được trùng với mật khẩu hiện tại.

---

# PHẦN II: PHÂN HỆ QUẢN LÝ (MANAGER SUBSYSTEM)

## 3.2. Phân Hệ Xác Thực & Đăng Nhập

### 3.2.1. Đăng nhập Manager
*   **Mô tả:** Cho phép người dùng có vai trò Quản lý (Manager - phụ trách tòa nhà) đăng nhập vào ứng dụng StayHub trên thiết bị di động để thực hiện kiểm tra trạng thái phòng, chốt số điện nước và quản lý cư dân.
*   **Thao tác:**
    1.  Người dùng mở ứng dụng di động, chọn vai trò **Quản lý (Manager)**.
    2.  Nhập thông tin đăng nhập bao gồm: **Tên đăng nhập** và **Mật khẩu**.
    3.  Nhấn nút **ĐĂNG NHẬP** để gửi yêu cầu xác thực tới API của Laravel Backend (`POST /api/v1/admin/login`).
    4.  Nếu thông tin chính xác, hệ thống khởi tạo phiên làm việc (Sanctum Token), đăng ký kết nối WebSocket (Laravel Reverb) và điều hướng người dùng đến màn hình Dashboard Quản lý.
*   **Ràng buộc:**
    *   Các trường nhập liệu không được để trống.
    *   Tài khoản đăng nhập phải có vai trò Quản lý (`role = 1` - Admin thông thường / Manager), tồn tại trong cơ sở dữ liệu và đang ở trạng thái hoạt động (`status = 1` - Active).
    *   **Lưu ý:** Không hỗ trợ đăng nhập cho tài khoản Super Admin trên ứng dụng di động.
    *   Trường hợp nhập sai thông tin đăng nhập, hệ thống trả về mã lỗi 401: *"Tên đăng nhập hoặc mật khẩu không chính xác"*.

### 3.2.2. Đăng Ký FaceID
*   **Mô tả:** **[KHÔNG HỖ TRỢ TRÊN DI ĐỘNG]** Tính năng nhận diện khuôn mặt sinh trắc học FaceID không được triển khai trên thiết bị di động cho vai trò Manager (chỉ hỗ trợ xác thực bằng thông tin tài khoản truyền thống).

---

## 3.3. Phân Hệ Dashboard

### 3.3.1. Dashboard Quản lý (Manager)
*   **Mô tả:** Trang chủ tổng quan dành cho Manager sau khi đăng nhập thành công, cung cấp số liệu thống kê nhanh về phòng trọ và hóa đơn của các tòa nhà được phân quyền quản lý.
*   **Thao tác:**
    1.  Màn hình hiển thị tự động sau khi đăng nhập thành công.
    2.  Manager chọn tòa nhà cần xem trong danh sách tòa nhà được gán (Dropdown) để lọc dữ liệu hiển thị.
    3.  Người dùng có thể thực hiện thao tác kéo để làm mới (Pull-to-refresh) dữ liệu.
*   **Ràng buộc:**
    *   Phạm vi số liệu hiển thị bị giới hạn nghiêm ngặt theo danh sách các Tòa nhà mà Manager đó được phân quyền quản lý (`managedBuildingIds`).
    *   Không hiển thị các báo cáo tài chính cấp cao của Super Admin (như tổng doanh thu toàn hệ thống, lợi nhuận ròng, dòng tiền tổng).

---

## 3.4. Phân hệ Quản lý Cơ sở vật chất

### 3.4.1. Quản lý Khu vực & Tòa nhà
*   **Mô tả:** Xem danh sách các Khu vực địa lý và Tòa nhà cho thuê mà Manager được phân quyền phụ trách quản lý thực tế.
*   **Thao tác:**
    1.  Manager truy cập mục **StayHub Facilities** (Khu vực & Tòa nhà) trên ứng dụng di động.
    2.  Xem danh sách các Khu vực và Tòa nhà hiển thị trong phạm vi quản lý của mình.
*   **Ràng buộc:**
    *   Manager trên di động chỉ có quyền **Xem danh sách** các tòa nhà được phân công, không có quyền thêm mới, chỉnh sửa thông tin, đổi trạng thái hay xóa khu vực/tòa nhà (các nút chức năng này chỉ hiển thị trên Web dành cho Super Admin).

### 3.4.2. Tạo/Chỉnh sửa Tòa nhà
*   **Mô tả:** **[KHÔNG HỖ TRỢ TRÊN DI ĐỘNG]** Tính năng đăng ký mới hoặc thay đổi thông tin tòa nhà chỉ thực hiện trên Web Dashboard bởi Super Admin.

### 3.4.3. Mẫu Tài sản
*   **Mô tả:** **[KHÔNG HỖ TRỢ TRÊN DI ĐỘNG]** Tính năng quản lý, tạo mới danh mục mẫu trang thiết bị vật chất không được triển khai trên ứng dụng di động.

### 3.4.4. Loại phòng
*   **Mô tả:** **[KHÔNG HỖ TRỢ TRÊN DI ĐỘNG]** Tính năng cấu hình các loại hình phòng trọ không triển khai trên ứng dụng di động.

---

## 3.5. Phân hệ Quản lý Phòng

### 3.5.1. Danh sách Phòng
*   **Mô tả:** Xem danh sách phòng trọ trực thuộc tòa nhà do Manager phụ trách, hiển thị trạng thái và cho phép đổi nhanh trạng thái hoạt động của phòng sang Bảo trì hoặc Trống.
*   **Thao tác:**
    1.  Manager truy cập mục **Trạng thái phòng** (hoặc `/admin/rooms` trên di động).
    2.  Hệ thống hiển thị danh sách phòng của tòa nhà được phân quyền phụ trách.
    3.  Tìm kiếm theo Số phòng hoặc lọc nhanh theo trạng thái phòng: Trống, Đang ở, Bảo trì.
    4.  Nhấn nút **Cập nhật** để thay đổi nhanh trạng thái hoạt động của phòng (ví dụ: chuyển sang bảo trì).
*   **Ràng buộc:**
    *   Manager chỉ xem và thao tác được trên các phòng thuộc tòa nhà mình quản lý.
    *   Chỉ cho phép chuyển phòng sang trạng thái "Đang bảo trì" khi số lượng khách đang ở thực tế của phòng đó bằng 0.

### 3.5.2. Tạo Phòng mới
*   **Mô tả:** **[KHÔNG HỖ TRỢ TRÊN DI ĐỘNG]** Tính năng thêm phòng trọ mới vào tòa nhà chỉ thực hiện trên giao diện Web Dashboard bởi Super Admin.

### 3.5.3. Màn hình Chỉnh sửa Phòng
*   **Mô tả:** **[KHÔNG HỖ TRỢ TRÊN DI ĐỘNG]** Tính năng chỉnh sửa cấu hình phòng, diện tích, đơn giá phòng và gán tài sản phòng không triển khai trên di động.

---

## 3.6. Phân hệ Quản lý Dịch vụ & Công tơ

### 3.6.1. Dịch vụ
*   **Mô tả:** Xem danh sách các dịch vụ tiện ích áp dụng tại tòa nhà cùng đơn giá hiện hành (Điện, Nước, Internet...).
*   **Thao tác:**
    1.  Manager mở mục **StayHub Services** trên ứng dụng di động.
    2.  Xem thông tin đơn giá và cách tính của các dịch vụ đang được áp dụng cho tòa nhà.
*   **Ràng buộc:**
    *   Manager chỉ có quyền **Xem danh sách**, không có quyền thêm mới dịch vụ hoặc sửa đổi đơn giá dịch vụ (chức năng này chỉ được cấu hình bởi Super Admin trên Web).

### 3.6.2. Thiết bị Công tơ
*   **Mô tả:** **[KHÔNG HỖ TRỢ TRÊN DI ĐỘNG]** Tính năng đăng ký thiết bị công tơ điện/nước mới hoặc gỡ bỏ thiết bị công tơ khỏi phòng trọ không triển khai trên ứng dụng di động.

### 3.6.3. Chốt chỉ số Công tơ
*   **Mô tả:** Ghi nhận chỉ số điện, nước tiêu thụ định kỳ hàng tháng cho từng phòng trọ do Manager quản lý. Hỗ trợ chụp ảnh mặt đồng hồ bằng Camera điện thoại để AI tự động nhận dạng số hoặc nhập tay thủ công, sau đó thực hiện xuất hóa đơn.
*   **Thao tác:**
    1.  Manager truy cập màn hình **Chốt số Điện nước** (hoặc `/admin/meters` trên di động).
    2.  Chọn **Tòa nhà**, chọn **Kỳ chốt (Tháng/Năm)** -> Xem danh sách phòng **ĐÃ CHỐT** hoặc **CHƯA XONG**.
    3.  Bấm nút **Chốt số** trên phòng tương ứng:
        *   *Cách 1:* Nhập trực tiếp chỉ số mới của công tơ điện và đồng hồ nước vào ô nhập liệu.
        *   *Cách 2 (Quét AI):* Nhấn **Chụp ảnh đồng hồ**, dùng camera điện thoại chụp hoặc tải ảnh lên. Hệ thống tự động gửi ảnh lên API backend để phân tích nhận dạng chữ số và điền vào form chỉ số.
    4.  Nhấn **LƯU LẠI** để ghi nhận chỉ số. Sau khi chốt, bấm nút **Tạo HĐ** để xuất hóa đơn ngay cho phòng đó, hoặc bấm **TẠO TẤT CẢ HÓA ĐƠN** để lập hóa đơn hàng loạt cho toàn bộ các phòng đã chốt số.
*   **Ràng buộc:**
    *   Chỉ số mới nhập vào (`current_reading`) bắt buộc phải lớn hơn hoặc bằng chỉ số cũ ghi nhận ở kỳ liền trước (`current_reading >= previous_reading`).
    *   Quét ảnh AI chỉ thực hiện ghi nhận; nếu chất lượng ảnh không đạt chuẩn (lóa, mờ, tối...), hệ thống trả về mã lỗi (`image_blurry`, `image_too_dark`, `image_glare`, `no_meter_found`, `meter_type_mismatch`) và yêu cầu nhập tay.
    *   Không cho phép sửa đổi chỉ số chốt đối với những phòng trọ đã xuất hóa đơn hoặc hóa đơn của kỳ đó đã thanh toán.

---

## 3.7. Phân hệ Quản lý Khách thuê & Phòng

### 3.7.1. Màn hình Danh sách Khách thuê
*   **Mô tả:** Quản lý danh sách cư dân (khách thuê) cư trú tại các tòa nhà do Manager phụ trách phụ trách, hỗ trợ xem thông tin liên hệ chi tiết và bật/tắt (kích hoạt/khóa) tài khoản cư dân.
*   **Thao tác:**
    1.  Manager mở màn hình **Danh sách Khách thuê** (hoặc `/admin/tenants` trên di động).
    2.  Tìm kiếm khách thuê theo tên, số điện thoại hoặc số phòng.
    3.  Bấm vào biểu tượng thông tin để mở sheet xem chi tiết hồ sơ (CCCD, email, quê quán, tòa nhà...).
    4.  Nhấp nút Switch bên phải dòng cư dân để bật/tắt trạng thái hoạt động của tài khoản khách thuê đó.
*   **Ràng buộc:**
    *   Manager chỉ xem và thao tác được trên các cư dân đang thuê phòng thuộc tòa nhà mình phụ trách quản lý.
    *   Manager có thể khóa/mở khóa tài khoản khách thuê trên di động, nhưng các thao tác thêm mới hồ sơ cư dân hoặc cập nhật ảnh CCCD phải được thực hiện trên Web Dashboard.
