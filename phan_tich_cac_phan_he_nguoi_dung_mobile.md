# Tài Liệu Phân Tích Các Phân Hệ Người Dùng Trên Ứng Dụng Di Động StayHub

Tài liệu này tổng hợp toàn bộ các chức năng thuộc cả hai phân hệ **Khách thuê (Tenant)** và **Quản lý (Admin)** trên ứng dụng di động StayHub. Mỗi chức năng được trình bày chi tiết theo định dạng **Mô tả** và **Thao tác & Ràng buộc** nhằm phục vụ công tác xây dựng báo cáo kỹ thuật.

---

# PHẦN I: PHÂN HỆ KHÁCH THUÊ (TENANT SUBSYSTEM)

## 1. Chức năng: Đăng Nhập Tài Khoản (Tenant Login)

- **Mô tả:** Khách thuê đăng nhập vào ứng dụng di động bằng tài khoản (Tên đăng nhập/SĐT/Email) và mật khẩu được cung cấp hoặc tự thiết lập để truy cập các dịch vụ của tòa nhà.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Người dùng mở ứng dụng, chọn vai trò "Khách thuê", nhập Tên đăng nhập (hoặc Email/SĐT), nhập Mật khẩu và nhấn nút "Đăng nhập".
    *   **Ràng buộc:** 
        *   Các trường thông tin không được để trống.
        *   Tài khoản đăng nhập phải tồn tại trong cơ sở dữ liệu và đang ở trạng thái hoạt động (`status = STATUS_RENTING`). Nếu tài khoản bị khóa, hệ thống hiển thị thông báo lỗi 403 cụ thể.
        *   Nếu nhập sai thông tin đăng nhập, hệ thống trả về lỗi 401: *"Tên đăng nhập hoặc mật khẩu không chính xác"*.
        *   Thiết bị di động sẽ lưu giữ session đăng nhập qua cookie/token để tránh việc người dùng phải đăng nhập lại mỗi lần mở app.

---

## 2. Chức năng: Quên & Đặt Lại Mật Khẩu (Forgot & Reset Password)

- **Mô tả:** Khách thuê tự khôi phục mật khẩu thông qua mã xác minh OTP được gửi về địa chỉ Email đã đăng ký để bảo mật tài khoản khi quên mật khẩu.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Người dùng nhấn "Quên mật khẩu", nhập Email đăng ký để nhận mã OTP. Sau khi có mã OTP gửi về hòm thư, người dùng nhập mã đó cùng với Mật khẩu mới và xác nhận mật khẩu mới để hoàn tất đổi mật khẩu.
    *   **Ràng buộc:**
        *   Email yêu cầu khôi phục phải đúng định dạng Regex và đã được đăng ký cho một Khách thuê đang hoạt động trong hệ thống.
        *   Mã OTP gồm đúng 6 chữ số và chỉ có giá trị hiệu lực trong vòng **15 phút**. Hết thời gian này, bản ghi OTP tự động bị hủy.
        *   Giao diện ứng dụng di động khóa nút gửi lại mã (Resend OTP) bằng bộ đếm ngược cooldown **60 giây** để hạn chế spam email.
        *   Mật khẩu mới và mật khẩu xác nhận phải trùng khớp hoàn toàn, đồng thời độ dài tối thiểu phải từ **6 ký tự**.
        *   Mã OTP sau khi được xác minh và đổi mật khẩu thành công sẽ bị xóa lập tức khỏi cơ sở dữ liệu để ngăn ngừa việc tái sử dụng.

---

## 3. Chức năng: Trang Chủ Tổng Quan (Tenant Dashboard)

- **Mô tả:** Hiển thị tổng quan các thông tin phòng thuê hiện tại, số dư công nợ hóa đơn, trạng thái hợp đồng thuê và các phím tắt truy cập nhanh vào các tính năng dịch vụ.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Hiển thị tự động sau khi đăng nhập thành công. Người dùng có thể nhấn vào các thẻ hiển thị (Hợp đồng, Hóa đơn, Điện nước, Sửa chữa) để điều hướng nhanh.
    *   **Ràng buộc:** 
        *   Dữ liệu được cập nhật thời gian thực thông qua cơ chế kéo để làm mới (Pull-to-refresh) hoặc các sự kiện đẩy từ WebSocket.
        *   Nếu khách thuê chưa có phòng hoạt động hoặc hợp đồng chưa ký, hệ thống hiển thị cảnh báo yêu cầu ký hợp đồng ngay tại đầu trang Dashboard.

---

## 4. Chức năng: Xem & Ký Hợp Đồng Điện Tử (Tenant Contract & Signing)

- **Mô tả:** Khách thuê xem chi tiết nội dung hợp đồng thuê phòng (tiền phòng, cọc, kỳ thanh toán, điều khoản) và thực hiện ký hợp đồng bằng chữ ký vẽ tay điện tử trực tiếp trên điện thoại.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Người dùng xem bản nháp hợp đồng -> Điền thông tin pháp lý bên B -> Vẽ chữ ký tay vào khung vẽ -> Tích chọn đồng ý điều khoản -> Nhấn xác nhận để ký.
    *   **Ràng buộc:**
        *   Thông tin pháp lý bắt buộc: Họ tên (không trống), Số CCCD/Hộ chiếu (tối thiểu 9 ký tự), Ngày cấp (phải nhỏ hơn ngày hiện tại), Nơi cấp, Địa chỉ thường trú. Các trường này mặc định được tự động điền sẵn từ hồ sơ cá nhân để tiết kiệm thời gian.
        *   Người dùng phải hoàn thành vẽ chữ ký tay trên khung vẽ cảm ứng (Canvas). Khung vẽ xuất dữ liệu dưới dạng byte ảnh PNG nền trắng để chèn trực tiếp vào bản hợp đồng PDF của hệ thống.
        *   Người dùng bắt buộc phải tích chọn checkbox đồng ý chịu trách nhiệm pháp lý mới có thể nhấn nút "Xác nhận ký hợp đồng".
        *   Khi ký thành công, trạng thái hợp đồng chuyển thành "Đã ký" (Active) và thông tin định danh của khách thuê sẽ tự động cập nhật đồng bộ vào bảng dữ liệu cá nhân.

---

## 5. Chức năng: Theo Dõi Chỉ Số Điện Nước (Tenant Utility Tracking)

- **Mô tả:** Khách thuê theo dõi chỉ số tiêu thụ điện, nước hàng tháng, biểu đồ xu hướng biến động đơn giá và hình ảnh minh chứng chỉ số thực tế do quản lý tòa nhà ghi nhận.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Người dùng chọn tab "Chỉ số Điện Nước", chọn giữa tab "Điện tiêu thụ" hoặc "Nước tiêu thụ" để xem lịch sử chỉ số, biểu đồ đơn giá và nhấn "Xem ảnh minh chứng" để kiểm tra ảnh đồng hồ thực tế.
    *   **Ràng buộc:**
        *   Biểu đồ xu hướng đơn giá tự động lấy tối đa 5 bản ghi thay đổi đơn giá gần nhất để vẽ đồ thị đường thẳng (Line Chart) trực quan hóa xu hướng giá.
        *   Chi tiết lịch sử tiêu thụ điện nước hiển thị rõ: Chỉ số cũ, Chỉ số mới, Tiêu thụ thực tế (kWh hoặc m³) và Đơn giá áp dụng tại thời điểm ghi nhận.
        *   Ảnh minh chứng đồng hồ điện/nước được tải từ hệ thống lưu trữ đám mây (MinIO/S3), nếu ảnh bị lỗi hoặc không tồn tại sẽ hiển thị biểu tượng thay thế mặc định (broken image helper).

---

## 6. Chức năng: Quản Lý & Thanh Toán Hóa Đơn (Tenant Invoices & Payments)

- **Mô tả:** Khách thuê quản lý danh sách hóa đơn theo kỳ, theo dõi trạng thái công nợ và thực hiện thanh toán trực tuyến nhanh qua mã QR chuyển khoản VietQR tự động.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Người dùng chọn mục Hóa đơn -> Xem danh sách "Chưa thanh toán" và "Đã thanh toán" -> Nhấn vào hóa đơn cụ thể để xem chi tiết chi phí -> Nhấn "Thanh toán ngay" để mở mã QR VietQR và sao chép thông tin chuyển khoản.
    *   **Ràng buộc:**
        *   Hóa đơn chưa thanh toán hiển thị rõ hạn nợ (Due Date) và số tiền còn lại cần thanh toán.
        *   Mã QR VietQR được tạo tự động với nội dung chuyển khoản là mã hóa đơn duy nhất (Invoice Code) và số tiền chính xác cần đóng.
        *   Khi người dùng chuyển khoản đúng nội dung, hệ thống thông qua tích hợp SePay Webhook tự động khớp lệnh thanh toán, cập nhật trạng thái hóa đơn thành "Đã thanh toán" tức thì.
        *   Giao diện hóa đơn tích hợp kết nối WebSocket để tự động tải lại trạng thái thanh toán theo thời gian thực (Real-time update) ngay khi có thông báo `invoice_paid` hoặc `invoice_reissued` từ backend mà người dùng không cần tải lại trang thủ công.

---

## 7. Chức năng: Báo Cáo Sự Cố & Yêu Cầu Sửa Chữa (Tenant Maintenance Requests)

- **Mô tả:** Khách thuê gửi báo cáo sự cố hỏng hóc cơ sở vật chất trong phòng thuê (như hỏng điện, rò nước, đồ gia dụng hư hại) kèm ảnh chụp thực tế và đánh giá chất lượng sau khi hoàn thành.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Người dùng nhấn nút "+" để tạo yêu cầu mới -> Nhập Tiêu đề, Mô tả chi tiết -> Chụp hoặc đính kèm ảnh minh chứng sự cố -> Gửi yêu cầu. Khi sự cố đã sửa xong, nhấn "Gửi phản hồi / đánh giá" để nhập ý kiến nhận xét chất lượng.
    *   **Ràng buộc:**
        *   Tiêu đề và Mô tả chi tiết sự cố không được để trống để đảm bảo kỹ thuật viên nắm rõ hiện trạng trước khi đến.
        *   Được phép đính kèm 1 ảnh minh chứng trạng thái hư hỏng (Before Image).
        *   Sau khi kỹ thuật viên sửa chữa xong và cập nhật trạng thái "Đã hoàn thành" (`status = 4`), giao diện hiển thị thêm ảnh sau khi sửa (After Image) để khách thuê đối soát đối chứng.
        *   Văn bản phản hồi đánh giá chất lượng chỉ hiển thị khi trạng thái yêu cầu là "Đã hoàn thành", bắt buộc không được bỏ trống khi gửi và chỉ được gửi phản hồi một lần duy nhất cho mỗi yêu cầu.

---

## 8. Chức năng: Trò Chuyện Trực Tuyến Với Quản Lý (Realtime Chat with Manager)

- **Mô tả:** Khách thuê nhắn tin trực tiếp và gửi ảnh trao đổi thông tin thời gian thực với Quản lý tòa nhà (Admin) khi có thắc mắc hoặc cần hỗ trợ khẩn cấp.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Người dùng mở màn hình Chat, nhập nội dung tin nhắn và đính kèm tối đa 5 hình ảnh từ thư viện, nhấn nút Gửi để trò chuyện thời gian thực.
    *   **Ràng buộc:**
        *   Hệ thống duy trì kết nối WebSocket liên tục khi mở màn hình chat để truyền nhận tin nhắn tức thì (`chat_message_sent`).
        *   Tin nhắn chỉ gửi được khi có nội dung văn bản (đã cắt bỏ khoảng trắng đầu/cuối) hoặc có hình ảnh đính kèm.
        *   Số lượng ảnh đính kèm tối đa cho một lượt gửi là **5 hình ảnh**. Các ảnh được nén dung lượng và kích thước tự động (maxWidth/Height 1024px, quality 70%) trước khi tải lên để tiết kiệm băng thông.
        *   Có trạng thái thông báo tin nhắn "Đang gửi..." (Optimistic UI) và chuyển sang hiển thị thời gian gửi chính xác khi đã lưu vào cơ sở dữ liệu thành công.
        *   Khi đối phương đã xem tin nhắn, trạng thái "Đã đọc" sẽ được cập nhật real-time thông qua sự kiện WebSocket Read.

---

## 9. Chức năng: Thay Đổi Hồ Sơ & Mật Khẩu (Tenant Settings & Profile)

- **Mô tả:** Khách thuê chỉnh sửa các thông tin cá nhân cơ bản (ảnh đại diện, email, SĐT), xem thông tin định danh và thực hiện đổi mật khẩu tài khoản định kỳ.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:**
        *   *Avatar:* Nhấn vào ảnh đại diện -> Chọn ảnh mới từ thư viện ảnh thiết bị để tải lên.
        *   *Email/SĐT:* Nhấn nút chỉnh sửa -> Nhập giá trị mới vào ô nhập liệu -> Xác nhận cập nhật.
        *   *Đổi mật khẩu:* Nhập Mật khẩu hiện tại, Mật khẩu mới và Nhập lại mật khẩu mới.
    *   **Ràng buộc:**
        *   **Cập nhật Số điện thoại:** Bắt buộc nhập đúng định dạng số điện thoại Việt Nam (10 chữ số, bắt đầu bằng đầu số hợp lệ của các nhà mạng: 03/05/07/08/09).
        *   **Cập nhật Email:** Bắt buộc tuân thủ đúng định dạng Regex của địa chỉ thư điện tử tiêu chuẩn.
        *   **Thay đổi Mật khẩu:** 
            *   Mật khẩu hiện tại nhập vào phải khớp với mật khẩu cũ lưu trong database (kiểm tra qua `Hash::check`).
            *   Mật khẩu mới phải có độ dài tối thiểu từ **6 ký tự**.
            *   Mật khẩu mới và xác nhận mật khẩu mới phải khớp nhau hoàn toàn, đồng thời mật khẩu mới không được trùng với mật khẩu hiện tại.

---

# PHẦN II: PHÂN HỆ QUẢN LÝ (ADMIN SUBSYSTEM)

## 1. Chức năng: Đăng Nhập Tài Khoản & Quét FaceID (Admin Login & FaceID Auth)

- **Mô tả:** Quản lý (Admin) đăng nhập vào ứng dụng di động StayHub bằng tài khoản truyền thống hoặc sử dụng tính năng nhận diện khuôn mặt sinh trắc học FaceID để truy cập trung tâm điều hành.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Người dùng mở ứng dụng, chọn vai trò "Quản lý", nhập tên đăng nhập/mật khẩu truyền thống OR nhấn nút quét FaceID để hệ thống tự động kích hoạt camera trước, căn chỉnh khuôn mặt và xác thực.
    *   **Ràng buộc:** 
        *   Các trường đăng nhập truyền thống không được để trống.
        *   Tài khoản đăng nhập phải tồn tại trong cơ sở dữ liệu và có vai trò phù hợp (`role` là Admin hoặc Quản lý tòa nhà) ở trạng thái hoạt động.
        *   Khi sử dụng FaceID, ảnh quét từ camera trước phải rõ nét, độ sáng tốt và không bị rung. AI Service sẽ thực hiện trích xuất vector khuôn mặt và thực hiện tìm kiếm 1:N trong Qdrant Vector DB, yêu cầu điểm tương đồng (similarity score) phải lớn hơn **90%** mới cho phép đăng nhập thành công.
        *   Hệ thống tự động nạp danh sách các ID tòa nhà được phân quyền quản lý (`managedBuildingIds`) của Admin vào bộ nhớ thiết bị sau khi đăng nhập thành công.

---

## 2. Chức năng: Quản Lý Khách Thuê & Khai Báo Hồ Sơ (Tenant Profile Management)

- **Mô tả:** Quản lý có thể xem danh sách cư dân, thêm mới khách thuê phòng và chỉnh sửa hồ sơ thông tin định danh kèm hình ảnh giấy tờ (CCCD/Hộ chiếu).

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Admin chọn "Danh sách khách thuê" -> Chọn "Thêm mới" hoặc chọn một khách thuê để "Chỉnh sửa" -> Nhập thông tin tài khoản, thông tin cá nhân, định danh, chụp ảnh mặt trước/mặt sau CCCD và nhấn "Lưu".
    *   **Ràng buộc:**
        *   Tên đăng nhập (`username`) của Tenant là bắt buộc và duy nhất, không thể thay đổi sau khi tạo thành công.
        *   Số điện thoại và Email phải đúng định dạng và không được trùng lắp với các tài khoản khác.
        *   Yêu cầu chụp và đính kèm ảnh mặt trước và mặt sau của giấy tờ tùy thân (CCCD).
        *   Khi tạo mới thành công, hệ thống tự động sinh mật khẩu ngẫu nhiên và gửi thư thông báo tài khoản tự động về Email của khách thuê.

---

## 3. Chức năng: Ghi Chỉ Số Điện Nước Hàng Tháng (Utility Meter Recording)

- **Mô tả:** Quản lý thực hiện ghi nhận chỉ số công tơ điện và nước tiêu thụ định kỳ của từng phòng trong tòa nhà để làm căn cứ lập hóa đơn.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Admin chọn tòa nhà, chọn kỳ (tháng/năm) -> Danh sách phòng hiển thị với trạng thái "Đã chốt" hoặc "Chưa xong" -> Nhấn "Chốt số" -> Điền chỉ số mới chốt bằng tay, chọn ngày ghi và lưu lại.
    *   **Ràng buộc:**
        *   Chỉ số mới chốt (`current_reading`) bắt buộc phải lớn hơn hoặc bằng chỉ số cũ ghi nhận ở kỳ trước (`current_reading >= previous_reading`).
        *   Ngày ghi nhận chỉ số bắt buộc phải được chọn và không được là ngày trong tương lai.
        *   Không cho phép chốt số hoặc chỉnh sửa chỉ số của các kỳ chốt đã qua mà đã xuất hóa đơn hoặc đã thanh toán thành công.

---

## 4. Chức năng: Nhận Diện Chỉ Số Bằng AI (AI OCR Utility Scan)

- **Mô tả:** Tự động phân tích ảnh chụp mặt đồng hồ công tơ điện/nước của phòng thuê và điền chỉ số nhận diện số học qua dịch vụ AI (FastAPI AI Service).

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Tại form ghi chỉ số điện nước, Admin chụp ảnh đồng hồ công tơ thực tế hoặc tải ảnh lên từ thư viện -> Đợi hệ thống AI tự động đọc số -> Xác nhận chỉ số nhận dạng được để điền vào form.
    *   **Ràng buộc:**
        *   Ảnh chụp đồng hồ tải lên được AI Service kiểm tra: độ sáng, độ nét (blur score $\ge 28$), phát hiện đồng hồ và phân loại đúng loại công tơ.
        *   API gửi ảnh đi `POST /admin/meter-readings/analyze-image` thiết lập timeout tối đa là **60 giây**.
        *   Admin có quyền chỉnh sửa lại chỉ số bằng tay nếu kết quả nhận diện của AI bị sai lệch hoặc có độ tin cậy thấp (Confidence: Low).
        *   Nếu AI trả về cảnh báo bất thường so với lượng tiêu thụ trung bình (`anomaly_warning`), hệ thống hiển thị cảnh báo đỏ và yêu cầu Admin kiểm duyệt xác nhận lại thủ công.

---

## 5. Chức năng: Tạo Hóa Đơn Tiền Phòng & Dịch Vụ (Invoice Generation)

- **Mô tả:** Quản lý lập hóa đơn tiền phòng và dịch vụ hàng tháng cho từng phòng hoặc phát hành hàng loạt cho toàn bộ các phòng trong tòa nhà sau khi đã hoàn tất chốt số điện nước.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Admin chọn nút "Tạo HĐ" bên cạnh phòng đã chốt chỉ số, hoặc chọn "TẠO TẤT CẢ HÓA ĐƠN" ở đầu trang để lập hóa đơn hàng loạt cho tòa nhà trong kỳ được chọn.
    *   **Ràng buộc:**
        *   Chỉ cho phép lập hóa đơn cho các phòng đã hoàn thành việc chốt chỉ số điện và nước của kỳ đó.
        *   Hệ thống tính toán chi tiết hóa đơn dựa trên: tiền phòng cố định trong hợp đồng, tiền điện nước (chênh lệch chỉ số $\times$ đơn giá), tiền dịch vụ đi kèm.
        *   Hóa đơn tạo hàng loạt được đẩy vào hệ thống hàng đợi (Queue Job) của Laravel Backend để xử lý bất đồng bộ nhằm tránh quá tải hệ thống.

---

## 6. Chức năng: Quản Lý Danh Sách Hóa Đơn (Invoice Management)

- **Mô tả:** Admin giám sát và lọc toàn bộ hóa đơn của tòa nhà theo trạng thái thanh toán và kỳ đóng phí để phục vụ công tác đối soát nợ.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Admin chọn menu "Hóa đơn" trên Dashboard -> Sử dụng thanh lọc trạng thái (Tất cả / Chưa TT / Đã TT / Quá hạn) -> Nhấn vào hóa đơn cụ thể để xem chi tiết các mục phí.
    *   **Ràng buộc:**
        *   Danh sách hóa đơn hiển thị badge số lượng chưa thanh toán và được cập nhật realtime qua WebSocket (Laravel Reverb) khi có thông báo thanh toán thành công.
        *   Chi tiết hóa đơn hiển thị đầy đủ: mã hóa đơn, kỳ thanh toán, danh sách khoản phí chi tiết (`invoice_items`), tổng tiền và số tiền còn nợ.

---

## 7. Chức năng: Xác Nhận Thanh Toán Thủ Công (Confirm Cash Payment)

- **Mô tả:** Admin xác nhận khách thuê đã thanh toán hóa đơn đối với các giao dịch trả tiền mặt hoặc chuyển khoản thủ công không qua đối soát tự động.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Admin mở chi tiết một hóa đơn ở trạng thái "Chưa thanh toán" hoặc "Thanh toán một phần" -> Nhấn "Xác nhận thanh toán" -> Nhập số tiền thực nhận và chọn phương thức (Tiền mặt/Chuyển khoản).
    *   **Ràng buộc:**
        *   Số tiền thanh toán xác nhận thủ công không được vượt quá số tiền còn nợ lại của hóa đơn (`remaining_amount`).
        *   Sau khi xác nhận thành công, hệ thống tự động ghi nhận giao dịch vào bảng `payments` và cập nhật trạng thái hóa đơn thành "Đã thanh toán" trên UI.

---

## 8. Chức năng: Sửa Hóa Đơn & Phát Hành Lại (Modify & Reissue Invoice)

- **Mô tả:** Admin sửa đổi thông tin các mục phí trong hóa đơn đã phát hành và gửi lại phiên bản hóa đơn cập nhật cho cư dân.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Admin nhấn nút "Sửa hóa đơn" tại chi tiết hóa đơn chưa thanh toán -> Thay đổi hạn thanh toán, chỉ số điện nước, hoặc thêm/bớt khoản phí điều chỉnh -> Nhập lý do điều chỉnh và nhấn "Phát hành lại".
    *   **Ràng buộc:**
        *   Chỉ có hóa đơn chưa thanh toán mới được phép chỉnh sửa và phát hành lại.
        *   Lý do chỉnh sửa (`reason`) là bắt buộc nhập để lưu vết nhật ký hoạt động trên hệ thống.
        *   Hóa đơn mới phát hành lại sẽ tăng phiên bản hiệu lực (`revision`) lên 1, đồng thời mã QR VietQR động cũng sẽ tự động tái sinh theo số tiền mới được cập nhật.

---

## 9. Chức năng: Gửi Nhắc Nhở Thanh Toán Hóa Đơn (Send Payment Reminder)

- **Mô tả:** Admin gửi thông báo đẩy và email nhắc nợ đến thiết bị của cư dân khi hóa đơn tiền phòng sắp đến hạn hoặc đã quá hạn thanh toán.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Admin nhấn nút "Nhắc nợ" trên hóa đơn chưa thanh toán -> Kiểm tra nội dung nhắc nhở tự sinh -> Nhấn Xác nhận để hệ thống gửi đi.
    *   **Ràng buộc:**
        *   Chức năng chỉ khả dụng cho hóa đơn có trạng thái Chưa thanh toán và đã quá hạn thanh toán hoặc sắp đến hạn.
        *   Nhật ký gửi nhắc nợ được ghi nhận vào bảng `invoice_reminder_logs`, hệ thống giới hạn tần suất gửi nhắc nhợ tối đa **1 lần mỗi 24 giờ** cho mỗi hóa đơn để tránh spam cư dân.

---

## 10. Chức năng: Quản Lý Hợp Đồng Thuê Phòng (Contract Management)

- **Mô tả:** Admin xem danh sách toàn bộ hợp đồng thuê, theo dõi thời hạn hiệu lực và giám sát các hợp đồng sắp hết hạn để chủ động liên hệ cư dân.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Admin chọn menu "Hợp đồng" -> Lọc theo trạng thái (Đang hiệu lực / Chờ ký / Hết hạn / Đã thanh lý) -> Nhấn vào hợp đồng để xem chi tiết điều khoản và xem tệp đính kèm.
    *   **Ràng buộc:**
        *   Hệ thống tự động quét và hiển thị badge cảnh báo đối với các hợp đồng sắp hết hạn trong vòng **30 ngày** để Admin có kế hoạch gia hạn hoặc thanh lý.
        *   Dữ liệu hợp đồng được hiển thị real-time, tự động làm mới danh sách khi cư dân hoàn thành ký điện tử vẽ tay trên thiết bị của họ.

---

## 11. Chức năng: Lập Hợp Đồng Thuê Mới (Create New Contract)

- **Mô tả:** Khởi tạo hợp đồng thuê phòng mới cho khách thuê, quy định rõ thời hạn, giá thuê và số tiền cọc làm căn cứ ký kết pháp lý.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Admin nhấn nút "Tạo hợp đồng mới" -> Nhập mã hợp đồng, chọn phòng trọ, chọn khách thuê đại diện, thời gian thuê (ngày bắt đầu, ngày kết thúc), giá phòng thỏa thuận và tiền cọc -> Nhấn Lưu nháp.
    *   **Ràng buộc:**
        *   Mã hợp đồng phải là duy nhất trên toàn hệ thống.
        *   Phòng được chọn phải có trạng thái trống hoặc chưa đạt giới hạn số người ở tối đa.
        *   Khách thuê đại diện bắt buộc phải tồn tại trong cơ sở dữ liệu cư dân của hệ thống.
        *   Hợp đồng sau khi tạo thành công sẽ ở trạng thái "Chờ ký" (nháp), hệ thống tự động phát đi thông báo yêu cầu cư dân ký xác nhận trên thiết bị di động của họ.

---

## 12. Chức năng: Thanh Lý Hợp Đồng Thuê (Liquidate Contract)

- **Mô tả:** Thực hiện thủ tục chấm dứt hợp đồng thuê phòng đang hiệu lực, xác nhận thời điểm bàn giao phòng thực tế.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Admin vào chi tiết hợp đồng đang hiệu lực -> Nhấn "Thanh lý hợp đồng" -> Xác nhận ngày kết thúc thực tế, nhập ghi chú bàn giao phòng và xác nhận đóng hợp đồng.
    *   **Ràng buộc:**
        *   Chỉ hợp đồng đang hiệu lực (`active`) mới được phép thanh lý.
        *   Ngày kết thúc thực tế (`actual_end_date`) không được là ngày trong quá khứ.
        *   Khi thanh lý thành công, trạng thái phòng trọ tự động chuyển về Trống (`status: 1`) và số lượng người ở thực tế được reset về 0.

---

## 13. Chức năng: Gia Hạn Hợp Đồng Thuê (Renew Contract)

- **Mô tả:** Gia hạn thời gian thuê đối với các hợp đồng đã hết hạn hoặc sắp hết hạn bằng cách nối dài chu kỳ thuê phòng.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Admin nhấn "Gia hạn" trên chi tiết hợp đồng cũ -> Chọn ngày kết thúc hợp đồng mới -> Xác nhận thông tin và gửi yêu cầu gia hạn để tạo hợp đồng mới kế tiếp.
    *   **Ràng buộc:**
        *   Hợp đồng được chọn gia hạn phải ở trạng thái Đang hoạt động (`active`) hoặc Đã hết hạn (`expired`).
        *   Ngày bắt đầu của hợp đồng gia hạn mới sẽ tự động được gán là ngày kế tiếp của ngày kết thúc hợp đồng cũ. Ngày kết thúc mới bắt buộc phải sau ngày bắt đầu mới.
        *   Hệ thống tự động sao chép danh sách khách thuê và phương tiện gửi xe từ hợp đồng cũ sang hợp đồng gia hạn mới để đảm bảo tính liên tục của dữ liệu.

---

## 14. Chức năng: Quản Lý Phòng Trọ (Room Management)

- **Mô tả:** Admin giám sát toàn bộ căn phòng trong các tòa nhà mình quản lý, theo dõi số người ở thực tế và cập nhật trạng thái phòng.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Admin chọn mục "Danh sách phòng" -> Tìm kiếm theo số phòng hoặc lọc theo trạng thái (Trống / Đang ở / Bảo trì) -> Nhấn vào phòng để xem danh sách cư dân đang cư trú, tài sản trang bị và đổi trạng thái hoạt động của phòng.
    *   **Ràng buộc:**
        *   Trạng thái phòng được tự động đồng bộ dựa trên biến động của hợp đồng thuê (Có hợp đồng hiệu lực -> Đang ở; Thanh lý hợp đồng -> Trống).
        *   Admin chỉ có thể chuyển phòng sang trạng thái "Bảo trì" khi phòng đó không có hợp đồng đang hoạt động và số lượng người ở thực tế bằng 0.

---

## 15. Chức năng: Quản Lý Sự Cố & Phiếu Bảo Trì (Maintenance Assignment)

- **Mô tả:** Xem danh sách phiếu báo hỏng thiết bị từ cư dân gửi lên, phân công nhân sự sửa chữa và cập nhật tiến trình giải quyết sự cố.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Admin chọn menu "Bảo trì" -> Xem danh sách yêu cầu -> Nhấn vào yêu cầu chi tiết -> Chọn nhân sự kỹ thuật để phân công (`assigned_to`) -> Cập nhật trạng thái và tải lên hình ảnh sau khi sửa chữa để hoàn tất phiếu.
    *   **Ràng buộc:**
        *   Giao diện cập nhật trạng thái chỉ mở khi phiếu đã được phân công người xử lý cụ thể.
        *   Khi cập nhật trạng thái sửa chữa thành "Hoàn tất", hệ thống yêu cầu Admin phải tải lên ít nhất 1 ảnh chụp kết quả khắc phục sự cố (After Image) làm minh chứng đối chiếu cho cư dân.
        *   Log lịch sử chuyển đổi trạng thái của phiếu bảo trì được ghi nhận tự động vào bảng `maintenance_request_logs`.

---

## 16. Chức năng: Trò Chuyện & Hỗ Trợ Cư Dân (Admin Realtime Chat Support)

- **Mô tả:** Nhắn tin trò chuyện thời gian thực qua giao thức WebSocket để giải quyết các thắc mắc của cư dân trong tòa nhà.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Admin chọn biểu tượng Chat trên thanh điều hướng -> Chọn cuộc hội thoại của phòng/khách thuê -> Nhập nội dung tin nhắn, đính kèm ảnh chụp thực tế và gửi đi.
    *   **Ràng buộc:**
        *   Danh sách chat tự động lọc hiển thị cư dân theo các tòa nhà mà Admin đó được phân quyền quản lý.
        *   Mọi tin nhắn gửi đi và nhận về đều được cập nhật real-time không trễ thông qua kết nối Laravel Reverb WebSocket.
        *   Trạng thái "Đã xem" của cư dân được hiển thị ngay khi cư dân mở cuộc hội thoại chat bên phía thiết bị của họ.

---

## 17. Chức năng: Gửi Thông Báo Tòa Nhà (Broadcast Customer Notifications)

- **Mô tả:** Biên soạn nội dung và phát hành thông báo đẩy (push notifications) đến toàn bộ cư dân trong tòa nhà hoặc một phòng cụ thể.

- **Thao tác & Ràng buộc:**
    *   **Thao tác:** Admin chọn chức năng "Thông báo" -> Nhấn "Tạo thông báo mới" -> Nhập Tiêu đề, Nội dung -> Chọn phạm vi áp dụng (Toàn bộ tòa nhà / Phòng cụ thể / Khách thuê cụ thể) -> Nhấn nút Gửi.
    *   **Ràng buộc:**
        *   Tiêu đề và nội dung thông báo không được bỏ trống.
        *   Khi Admin nhấn Gửi, hệ thống lập tức lưu thông báo vào cơ sở dữ liệu với trạng thái "Đã gửi" (`status: 2`) và phát sự kiện realtime qua WebSocket để hiển thị tức thì trên Dashboard thiết bị của cư dân thuộc phạm vi nhận tin.
