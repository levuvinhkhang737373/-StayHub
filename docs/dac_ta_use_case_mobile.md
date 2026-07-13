# BẢNG ĐẶC TẢ CHI TIẾT USE CASE - PHÂN HỆ MOBILE APP STAYHUB

Tài liệu này đặc tả chi tiết 20 Use Case của Phân hệ Mobile App StayHub dựa trên sơ đồ Use Case tổng quan (Hình 2) và tuân thủ định dạng bảng đặc tả của hình mẫu (Hình 1).

---

## DANH SÁCH CÁC USE CASE

| Use Case ID | Tên Use Case | Tác nhân | Phân nhóm | Mô tả tóm tắt |
| :---: | :--- | :--- | :--- | :--- |
| **UC-1** | Đăng nhập | Người dùng (Quản lý, Khách thuê) | Chung | Đăng nhập vào ứng dụng bằng tài khoản (Email/SĐT) và mật khẩu. |
| **UC-2** | Đăng xuất | Người dùng (Quản lý, Khách thuê) | Chung | Đăng xuất khỏi hệ thống và hủy token/phiên làm việc. |
| **UC-3** | Quản lý tài khoản | Thành viên StayHub (Khách thuê, Quản lý) | Chung | Chỉnh sửa thông tin cá nhân (Avatar, Email, SĐT) và đổi mật khẩu. |
| **UC-4** | Xem nội quy | Khách thuê | Khách thuê | Xem nội quy phòng trọ và các quy định chung của tòa nhà đang thuê. |
| **UC-5** | Xem thông báo | Khách thuê | Khách thuê | Nhận và xem thông báo từ Ban quản lý hoặc thông báo tự động từ hệ thống. |
| **UC-6** | Dashboard cư dân | Khách thuê | Khách thuê | Xem tổng quan phòng thuê, dư nợ hóa đơn và trạng thái hợp đồng. |
| **UC-7** | Quản lý hợp đồng cá nhân | Khách thuê | Khách thuê | Xem chi tiết nội dung hợp đồng và thực hiện ký điện tử bằng vẽ tay. |
| **UC-8** | Quản lý hóa đơn (Khách thuê) | Khách thuê | Khách thuê | Theo dõi hóa đơn và thanh toán trực tuyến qua mã QR VietQR động. |
| **UC-9** | Quản lý sửa chữa | Khách thuê | Khách thuê | Gửi báo cáo sự cố hỏng hóc kèm ảnh chụp thực tế và đánh giá chất lượng. |
| **UC-10** | Xem điện nước | Khách thuê | Khách thuê | Theo dõi chỉ số tiêu thụ điện nước và xem ảnh đồng hồ chốt thực tế. |
| **UC-11** | Chat với quản lý | Khách thuê | Khách thuê | Nhắn tin và gửi hình ảnh thời gian thực trao đổi với Ban quản lý tòa nhà. |
| **UC-12** | Quản lý khách thuê | Ban quản lý | Ban quản lý | Xem danh sách hồ sơ cư dân và kích hoạt hoặc khóa tài khoản khách thuê. |
| **UC-13** | Quản lý hợp đồng | Ban quản lý | Ban quản lý | Xem danh sách và chi tiết các hợp đồng thuê phòng của tòa nhà phụ trách. |
| **UC-14** | Chuyển phòng | Ban quản lý | Ban quản lý | Ghi nhận thủ tục chuyển phòng cho cư dân từ phòng cũ sang phòng trống mới. |
| **UC-15** | Quản lý điện nước | Ban quản lý | Ban quản lý | Chốt chỉ số điện nước định kỳ bằng tay hoặc quét ảnh AI OCR để xuất hóa đơn. |
| **UC-16** | Quản lý hóa đơn (Ban quản lý) | Ban quản lý | Ban quản lý | Theo dõi công nợ, nhắc nợ khách thuê hoặc xác nhận thanh toán thủ công. |
| **UC-17** | Quản lý sự cố | Ban quản lý | Ban quản lý | Tiếp nhận, phân công kỹ thuật viên và cập nhật trạng thái xử lý sự cố. |
| **UC-18** | Gửi thông báo | Ban quản lý | Ban quản lý | Soạn thảo và gửi thông báo hàng loạt qua Push Notification đến khách thuê. |
| **UC-19** | Chat cư dân | Ban quản lý | Ban quản lý | Nhắn tin trao đổi thời gian thực với các cư dân trong tòa nhà quản lý. |
| **UC-20** | Xem danh sách phòng | Ban quản lý | Ban quản lý | Xem tình trạng phòng (Trống, Đang ở, Bảo trì) và đổi trạng thái phòng. |

---

## CHI TIẾT ĐẶC TẢ USE CASE

### 1. Nhóm Use Case Chung (General)

#### Bảng 1.1: Đặc tả Use Case Đăng nhập

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-1** |
| **Tên Use Case** | **Đăng nhập** |
| **Tác nhân** | Người dùng (Quản lý, Khách thuê) |
| **Mô tả** | Người dùng đăng nhập vào hệ thống StayHub Mobile bằng tài khoản đã được cấp để sử dụng các chức năng tương ứng với vai trò. |
| **Điều kiện tiên quyết** | Người dùng đã có tài khoản trong hệ thống. |
| **Hoàn tất** | Người dùng đăng nhập thành công và được chuyển đến trang chủ tương ứng với vai trò (Quản lý hoặc Khách thuê). |
| **Quy trình bình thường** | 1. Người dùng mở ứng dụng StayHub Mobile.<br>2. Hệ thống hiển thị màn hình đăng nhập.<br>3. Người dùng nhập email/số điện thoại và mật khẩu.<br>4. Người dùng nhấn nút "Đăng nhập".<br>5. Hệ thống xác thực thông tin đăng nhập.<br>6. Hệ thống hiển thị trang chủ tương ứng với vai trò của người dùng. |
| **Luồng mở rộng** | Không có |
| **Ngoại lệ** | - Nếu email/số điện thoại hoặc mật khẩu không đúng, hệ thống hiển thị thông báo "Thông tin đăng nhập không chính xác" và yêu cầu nhập lại.<br>- Nếu tài khoản bị khóa, hệ thống hiển thị thông báo "Tài khoản đã bị khóa, vui lòng liên hệ quản trị viên". |
| **Luật nghiệp vụ** | - Mật khẩu phải có tối thiểu 6 ký tự.<br>- Sau 5 lần đăng nhập sai liên tiếp, tài khoản sẽ bị tạm khóa. |
| **Giả định** | Hệ thống hoạt động bình thường và có kết nối mạng. |
| **Ghi chú & các vấn đề** | Phục vụ xác thực vai trò để phân quyền điều hướng giao diện phù hợp. |

---

#### Bảng 1.2: Đặc tả Use Case Đăng xuất

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-2** |
| **Tên Use Case** | **Đăng xuất** |
| **Tác nhân** | Người dùng (Quản lý, Khách thuê) |
| **Mô tả** | Người dùng đăng xuất khỏi hệ thống ứng dụng StayHub. |
| **Điều kiện tiên quyết** | Người dùng đã đăng nhập vào hệ thống. |
| **Hoàn tất** | Người dùng được đăng xuất và chuyển về màn hình đăng nhập. |
| **Quy trình bình thường** | 1. Người dùng nhấn nút "Đăng xuất" tại màn hình cài đặt/hồ sơ cá nhân.<br>2. Hệ thống yêu cầu xác nhận đăng xuất.<br>3. Người dùng nhấn nút xác nhận.<br>4. Hệ thống gọi API hủy token/phiên đăng nhập trên backend và xóa thông tin session cục bộ trên thiết bị di động.<br>5. Hệ thống chuyển hướng người dùng về màn hình Đăng nhập. |
| **Luồng mở rộng** | Không có |
| **Ngoại lệ** | - Nếu có lỗi kết nối mạng khi hủy token trên backend, hệ thống vẫn thực hiện xóa session cục bộ trên thiết bị và chuyển hướng người dùng về màn hình đăng nhập. |
| **Luật nghiệp vụ** | Không có |
| **Giả định** | Hệ thống hoạt động bình thường. |
| **Ghi chú & các vấn đề** | Đảm bảo tính an toàn dữ liệu, tránh bị người khác sử dụng thiết bị truy cập trái phép. |

---

#### Bảng 1.3: Đặc tả Use Case Quản lý tài khoản

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-3** |
| **Tên Use Case** | **Quản lý tài khoản** |
| **Tác nhân** | Thành viên StayHub (Khách thuê, Ban quản lý) |
| **Mô tả** | Cho phép người dùng chỉnh sửa các thông tin cá nhân cơ bản (ảnh đại diện, email, số điện thoại) và thực hiện đổi mật khẩu tài khoản. |
| **Điều kiện tiên quyết** | Người dùng đã đăng nhập vào hệ thống ứng dụng di động. |
| **Hoàn tất** | Thông tin cá nhân được cập nhật thành công hoặc mật khẩu được thay đổi thành công trên hệ thống. |
| **Quy trình bình thường** | 1. Người dùng truy cập màn hình Cài đặt (Settings) trên ứng dụng di động.<br>2. Hệ thống hiển thị các thông tin tài khoản hiện tại.<br>3. Người dùng chọn chỉnh sửa thông tin cá nhân (email, số điện thoại, ảnh đại diện) hoặc chọn Đổi mật khẩu.<br>4. Người dùng nhập thông tin mới và xác nhận cập nhật.<br>5. Hệ thống gửi yêu cầu cập nhật đến Backend, thực hiện xác thực và cập nhật cơ sở dữ liệu.<br>6. Hệ thống hiển thị thông báo "Cập nhật thành công" và cập nhật thông tin hiển thị trên giao diện. |
| **Luồng mở rộng** | - Tại bước 3, nếu người dùng chọn đổi ảnh đại diện (avatar), hệ thống sẽ mở thư viện ảnh/camera trên thiết bị để người dùng chọn ảnh, nén ảnh tự động và upload lên server. |
| **Ngoại lệ** | - Nếu số điện thoại hoặc email nhập vào không đúng định dạng Regex, hệ thống hiển thị thông báo lỗi và yêu cầu nhập lại.<br>- Khi đổi mật khẩu, nếu mật khẩu hiện tại nhập vào không khớp với mật khẩu lưu trên hệ thống, báo lỗi: "Mật khẩu hiện tại không chính xác".<br>- Nếu mật khẩu mới có độ dài dưới 6 ký tự hoặc không trùng khớp với mật khẩu xác nhận, hệ thống báo lỗi tương ứng. |
| **Luật nghiệp vụ** | - Số điện thoại phải đúng định dạng mạng viễn thông Việt Nam (10 chữ số).<br>- Mật khẩu mới bắt buộc có độ dài tối thiểu từ 6 ký tự và không được trùng với mật khẩu hiện tại. |
| **Giả định** | Thiết bị di động kết nối mạng ổn định. |
| **Ghi chú & các vấn đề** | Không hỗ trợ cập nhật ảnh chụp CCCD/CMND của Khách thuê trên thiết bị di động (chức năng này chỉ được hỗ trợ trên Web Dashboard để đảm bảo tính xác thực pháp lý). |

---

### 2. Nhóm Use Case Khách Thuê (Tenant)

#### Bảng 2.1: Đặc tả Use Case Xem nội quy

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-4** |
| **Tên Use Case** | **Xem nội quy** |
| **Tác nhân** | Khách thuê |
| **Mô tả** | Khách thuê xem nội quy phòng trọ, quy định chung của tòa nhà đang thuê để chấp hành đúng nội dung quy định. |
| **Điều kiện tiên quyết** | Khách thuê đã đăng nhập vào hệ thống ứng dụng di động. |
| **Hoàn tất** | Hệ thống hiển thị chi tiết văn bản nội quy của tòa nhà tương ứng. |
| **Quy trình bình thường** | 1. Khách thuê chọn mục "Nội quy" trên màn hình Dashboard hoặc Cài đặt.<br>2. Hệ thống gửi yêu cầu API đến Backend để lấy nội quy của tòa nhà mà khách thuê đang lưu trú.<br>3. Hệ thống hiển thị danh sách các quy định nội quy dưới dạng văn bản có cấu trúc rõ ràng. |
| **Luồng mở rộng** | Không có |
| **Ngoại lệ** | - Nếu tòa nhà chưa được Ban quản lý cấu hình nội quy, hệ thống hiển thị thông báo: "Chưa có nội quy được cập nhật cho tòa nhà này". |
| **Luật nghiệp vụ** | Nội dung nội quy là dạng chỉ đọc (Read-only), khách thuê không thể chỉnh sửa. |
| **Giả định** | Ban quản lý hoặc Super Admin đã thiết lập nội quy tòa nhà trên Web Dashboard. |
| **Ghi chú & các vấn đề** | Không có |

---

#### Bảng 2.2: Đặc tả Use Case Xem thông báo

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-5** |
| **Tên Use Case** | **Xem thông báo** |
| **Tác nhân** | Khách thuê |
| **Mô tả** | Khách thuê nhận và xem các thông báo từ Ban quản lý tòa nhà hoặc các thông báo tự động từ hệ thống. |
| **Điều kiện tiên quyết** | Khách thuê đã đăng nhập vào ứng dụng di động. |
| **Hoàn tất** | Khách thuê đọc được danh sách thông báo và nội dung chi tiết của từng thông báo. |
| **Quy trình bình thường** | 1. Hệ thống tự động gửi tin nhắn đẩy (Push Notification) hoặc hiển thị số thông báo chưa đọc tại biểu tượng Chuông.<br>2. Khách thuê bấm vào biểu tượng Chuông để mở danh sách thông báo.<br>3. Khách thuê chọn một thông báo cụ thể để xem chi tiết tiêu đề, nội dung, thời gian và người gửi.<br>4. Hệ thống cập nhật trạng thái thông báo đó thành "Đã đọc". |
| **Luồng mở rộng** | - Nếu thông báo có đính kèm liên kết hành động (Hóa đơn, Sự cố), khách thuê có thể nhấn nút liên kết để đi thẳng đến màn hình thanh toán hóa đơn hoặc chi tiết sự cố bảo trì. |
| **Ngoại lệ** | - Nếu không kết nối được máy chủ, hệ thống hiển thị thông báo lỗi và yêu cầu tải lại danh sách. |
| **Luật nghiệp vụ** | Trạng thái "Đã đọc" được đồng bộ lên cơ sở dữ liệu backend. |
| **Giả định** | Dịch vụ thông báo đẩy (Firebase Cloud Messaging) hoạt động bình thường. |
| **Ghi chú & các vấn đề** | Phân loại thông báo rõ ràng để người dùng dễ theo dõi (Thông báo chung, Hóa đơn, Nhắc nợ, Sự cố). |

---

#### Bảng 2.3: Đặc tả Use Case Dashboard cư dân

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-6** |
| **Tên Use Case** | **Dashboard cư dân** |
| **Tác nhân** | Khách thuê |
| **Mô tả** | Hiển thị tổng quan các thông tin phòng thuê hiện tại, số dư công nợ hóa đơn chưa thanh toán, trạng thái hợp đồng thuê và lối tắt truy cập các tính năng. |
| **Điều kiện tiên quyết** | Khách thuê đã đăng nhập vào ứng dụng di động. |
| **Hoàn tất** | Hệ thống hiển thị giao diện Dashboard cư dân với đầy đủ dữ liệu thực tế thời gian thực. |
| **Quy trình bình thường** | 1. Ngay khi đăng nhập thành công, hệ thống tự động tải và hiển thị trang Dashboard cư dân.<br>2. Khách thuê xem thông tin: Tên phòng, Địa chỉ tòa nhà, Trạng thái hợp đồng, Tổng dư nợ chưa thanh toán, các yêu cầu sửa chữa đang xử lý.<br>3. Khách thuê thực hiện thao tác vuốt từ trên xuống để làm mới (Pull-to-refresh) dữ liệu. |
| **Luồng mở rộng** | - Bấm chọn các thẻ chức năng nhanh (Ví dụ: Thẻ "Hóa Đơn", "Điện Nước", "Sửa Chữa", "Hợp Đồng") để điều hướng trực tiếp đến màn hình chức năng tương ứng. |
| **Ngoại lệ** | - Nếu khách thuê chưa được gán vào bất kỳ hợp đồng thuê phòng nào hoặc hợp đồng đã thanh lý, hệ thống hiển thị banner cảnh báo: "Bạn chưa có hợp đồng thuê phòng đang hoạt động. Vui lòng liên hệ quản lý". |
| **Luật nghiệp vụ** | - Dữ liệu dashboard được lấy từ API `GET /api/v1/tenant/me` và đồng bộ thời gian thực qua Laravel Reverb WebSocket. |
| **Giả định** | Hệ thống backend hoạt động bình thường. |
| **Ghi chú & các vấn đề** | Không có |

---

#### Bảng 2.4: Đặc tả Use Case Quản lý hợp đồng cá nhân

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-7** |
| **Tên Use Case** | **Quản lý hợp đồng cá nhân** |
| **Tác nhân** | Khách thuê |
| **Mô tả** | Khách thuê xem chi tiết nội dung bản hợp đồng thuê phòng (tiền phòng, cọc, kỳ thanh toán, điều khoản bên A - bên B) và thực hiện ký hợp đồng bằng chữ ký vẽ tay điện tử trực tiếp trên màn hình điện thoại. |
| **Điều kiện tiên quyết** | Khách thuê đã đăng nhập và có hợp đồng ở trạng thái "Chờ ký". |
| **Hoàn tất** | Hợp đồng được ký thành công, trạng thái hợp đồng chuyển sang "Đang hoạt động" và thông tin cư dân được kích hoạt. |
| **Quy trình bình thường** | 1. Khách thuê chọn mục "Hợp đồng" trên ứng dụng di động.<br>2. Xem danh sách hợp đồng cá nhân và chọn hợp đồng đang ở trạng thái "Chờ ký".<br>3. Khách thuê đọc kỹ các điều khoản hợp đồng được hiển thị.<br>4. Khách thuê nhấn nút "Ký hợp đồng" để mở màn hình khai báo thông tin pháp lý bên B và khung vẽ chữ ký.<br>5. Khách thuê điền/kiểm tra thông tin pháp lý (CMND/CCCD, Ngày cấp, Nơi cấp, Địa chỉ thường trú) và vẽ chữ ký tay trên Canvas cảm ứng.<br>6. Khách thuê tích chọn đồng ý điều khoản chịu trách nhiệm pháp lý và nhấn "Xác nhận ký hợp đồng".<br>7. Hệ thống gửi yêu cầu lên Backend (`POST /api/v1/tenant/contracts/{id}/sign`), Backend chèn ảnh chữ ký vào PDF hợp đồng và cập nhật trạng thái hợp đồng sang "Đang hoạt động". |
| **Luồng mở rộng** | - Tại bước 5, khách thuê có thể nhấn "Xóa nét vẽ" hoặc "Hoàn tác" để vẽ lại chữ ký tay nếu nét vẽ bị lỗi. |
| **Ngoại lệ** | - Nếu thông tin pháp lý bên B bị bỏ trống hoặc Số định danh dưới 9 ký tự, hệ thống hiển thị thông báo lỗi và chặn không cho gửi.<br>- Nếu chữ ký tay trống (canvas không có nét vẽ), hệ thống báo lỗi: "Vui lòng ký tên trước khi xác nhận". |
| **Luật nghiệp vụ** | - Ảnh chữ ký phải xuất ở định dạng ảnh PNG nền trong suốt/nền trắng để chèn vào tài liệu PDF của hợp đồng.<br>- Khi hợp đồng chuyển sang "Đang hoạt động" (Active), trạng thái phòng trọ tương ứng tự động chuyển thành "Đang ở" và tài khoản khách thuê chuyển thành hoạt động. |
| **Giả định** | Hệ thống tạo PDF hoạt động ổn định trên Backend. |
| **Ghi chú & các vấn đề** | Không hỗ trợ sửa đổi điều khoản hợp đồng trên di động, mọi thay đổi điều khoản phải do Quản lý hoặc Admin thực hiện trên Web Dashboard. |

---

#### Bảng 2.5: Đặc tả Use Case Quản lý hóa đơn (Khách thuê)

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-8** |
| **Tên Use Case** | **Quản lý hóa đơn (Khách thuê)** |
| **Tác nhân** | Khách thuê |
| **Mô tả** | Khách thuê xem danh sách hóa đơn hàng tháng, theo dõi trạng thái thanh toán và thực hiện thanh toán trực tuyến nhanh qua mã QR VietQR động. |
| **Điều kiện tiên quyết** | Khách thuê đã đăng nhập vào ứng dụng di động. |
| **Hoàn tất** | Hóa đơn được thanh toán thành công và chuyển sang trạng thái "Đã thanh toán" thông qua đối soát tự động của hệ thống. |
| **Quy trình bình thường** | 1. Khách thuê chọn mục "Hóa đơn" trên ứng dụng di động.<br>2. Hệ thống hiển thị danh sách hóa đơn theo hai phân loại (Tab): "Chưa thanh toán" và "Đã thanh toán".<br>3. Khách thuê chọn hóa đơn cụ thể để xem chi tiết các chi phí (Tiền phòng, điện, nước, internet, vệ sinh...).<br>4. Khách thuê bấm nút "Thanh toán ngay".<br>5. Hệ thống hiển thị mã QR VietQR động và thông tin tài khoản thụ hưởng của tòa nhà cùng nút "Sao chép thông tin".<br>6. Khách thuê quét mã QR bằng ứng dụng ngân hàng để thực hiện chuyển khoản.<br>7. Hệ thống nhận thông báo thanh toán thành công từ Webhook cổng thanh toán (SePay/PayOS), cập nhật trạng thái hóa đơn thành "Đã thanh toán" và tự động gửi thông báo cho khách thuê. |
| **Luồng mở rộng** | - Tại bước 5, khách thuê có thể nhấn nút "Tải ảnh QR" để lưu mã QR về thư viện ảnh của thiết bị. |
| **Ngoại lệ** | - Cổng thanh toán hoặc ngân hàng gặp sự cố bảo trì khiến mã QR VietQR không thể tải, hệ thống báo lỗi và hiển thị thông tin tài khoản chuyển khoản thủ công bằng văn bản. |
| **Luật nghiệp vụ** | - Mã QR VietQR động chứa chính xác số tiền cần thanh toán và nội dung chuyển khoản là mã hóa đơn duy nhất (Invoice Code) để tự động đối soát khớp lệnh tức thì.<br>- Hóa đơn đã thanh toán thành công không được phép yêu cầu thanh toán lại hoặc thay đổi thông tin. |
| **Giả định** | Dịch vụ Webhook cổng thanh toán (SePay) hoạt động bình thường và phản hồi nhanh. |
| **Ghi chú & các vấn đề** | Trạng thái thanh toán của hóa đơn được tự động cập nhật thời gian thực trên giao diện nhờ kết nối Laravel Reverb WebSocket (`invoice_paid`). |

---

#### Bảng 2.6: Đặc tả Use Case Quản lý sửa chữa

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-9** |
| **Tên Use Case** | **Quản lý sửa chữa** |
| **Tác nhân** | Khách thuê |
| **Mô tả** | Khách thuê gửi báo cáo sự cố hỏng hóc cơ sở vật chất trong phòng thuê kèm ảnh chụp thực tế và đánh giá chất lượng dịch vụ sau khi hoàn thành. |
| **Điều kiện tiên quyết** | Khách thuê đã đăng nhập vào ứng dụng di động. |
| **Hoàn tất** | Yêu cầu sửa chữa được gửi thành công lên backend và nhận phản hồi từ kỹ thuật viên, sau đó khách thuê gửi đánh giá chất lượng phục vụ. |
| **Quy trình bình thường** | 1. Khách thuê chọn mục "Bảo trì/Sự cố" trên ứng dụng di động.<br>2. Hệ thống hiển thị danh sách các yêu cầu sửa chữa đã gửi cùng trạng thái hiện tại (Chờ xử lý, Đang sửa, Đã hoàn thành, Đã hủy).<br>3. Khách thuê nhấn nút "+" để tạo yêu cầu mới.<br>4. Nhập thông tin: Tiêu đề và Mô tả chi tiết sự cố. Chụp ảnh hoặc đính kèm ảnh minh chứng sự cố từ thiết bị di động.<br>5. Nhấn nút "Gửi yêu cầu" để gửi lên backend (`POST /api/v1/tenant/maintenance-requests`).<br>6. Sau khi kỹ thuật viên sửa chữa xong và cập nhật trạng thái "Đã hoàn thành", khách thuê nhấn nút "Đánh giá dịch vụ" để gửi phản hồi nhận xét chất lượng phục vụ. |
| **Luồng mở rộng** | - Tại bước 2, khách thuê có thể chọn một yêu cầu đang ở trạng thái "Chờ xử lý" để nhấn nút "Hủy yêu cầu" nếu không có nhu cầu sửa chữa nữa. |
| **Ngoại lệ** | - Không tải được ảnh đính kèm do dung lượng quá lớn, hệ thống hiển thị thông báo lỗi và yêu cầu nén ảnh hoặc chọn ảnh khác dưới 10MB. |
| **Luật nghiệp vụ** | - Khách thuê chỉ được đính kèm tối đa 1 ảnh minh chứng trạng thái hư hỏng ban đầu (Before Image).<br>- Form đánh giá chất lượng chỉ hiển thị khi trạng thái yêu cầu là "Đã hoàn thành", bắt buộc không được bỏ trống khi gửi và chỉ được gửi phản hồi một lần duy nhất cho mỗi yêu cầu. |
| **Giả định** | Thiết bị di động kết nối mạng ổn định. |
| **Ghi chú & các vấn đề** | Không có |

---

#### Bảng 2.7: Đặc tả Use Case Xem điện nước

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-10** |
| **Tên Use Case** | **Xem điện nước** |
| **Tác nhân** | Khách thuê |
| **Mô tả** | Khách thuê theo dõi chỉ số tiêu thụ điện, nước hàng tháng, xem biểu đồ xu hướng biến động đơn giá và hình ảnh minh chứng chỉ số thực tế do quản lý tòa nhà ghi nhận lúc chốt số. |
| **Điều kiện tiên quyết** | Khách thuê đã đăng nhập vào ứng dụng di động. |
| **Hoàn tất** | Khách thuê xem được biểu đồ chỉ số điện nước và ảnh đồng hồ chốt thực tế. |
| **Quy trình bình thường** | 1. Khách thuê chọn tab "Chỉ số Điện Nước" trên ứng dụng di động.<br>2. Hệ thống hiển thị biểu đồ đường xu hướng tiêu thụ điện/nước các tháng gần nhất.<br>3. Khách thuê chọn xem lịch sử chỉ số ghi nhận theo từng tháng: Chỉ số cũ, Chỉ số mới, Tiêu thụ thực tế (kWh hoặc m³) và Đơn giá áp dụng.<br>4. Khách thuê bấm chọn nút "Xem ảnh minh chứng" để kiểm tra ảnh đồng hồ công tơ thực tế do Quản lý chụp tại thời điểm chốt chỉ số. |
| **Luồng mở rộng** | Không có |
| **Ngoại lệ** | - Ảnh minh chứng đồng hồ điện/nước bị lỗi hoặc không tồn tại trên Cloud Storage, hệ thống hiển thị ảnh helper thay thế mặc định (broken image helper). |
| **Luật nghiệp vụ** | Chỉ số điện nước này là dạng chỉ đọc (Read-only) đối với khách thuê. |
| **Giả định** | Hệ thống lưu trữ Cloud Storage (MinIO/S3) hoạt động bình thường. |
| **Ghi chú & các vấn đề** | Không có |

---

#### Bảng 2.8: Đặc tả Use Case Chat với quản lý

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-11** |
| **Tên Use Case** | **Chat với quản lý** |
| **Tác nhân** | Khách thuê |
| **Mô tả** | Khách thuê nhắn tin trực tiếp và gửi ảnh trao đổi thông tin thời gian thực với Quản lý tòa nhà (Admin) khi có thắc mắc hoặc cần hỗ trợ khẩn cấp. |
| **Điều kiện tiên quyết** | Khách thuê đã đăng nhập vào ứng dụng di động. |
| **Hoàn tất** | Tin nhắn và hình ảnh được gửi đi thành công và hiển thị tức thời trên khung chat của hai bên. |
| **Quy trình bình thường** | 1. Khách thuê chọn mục "Chat" trên ứng dụng di động.<br>2. Hệ thống tải lịch sử tin nhắn cũ và duy trì kết nối WebSocket thời gian thực (Laravel Reverb).<br>3. Khách thuê nhập nội dung tin nhắn vào ô soạn thảo văn bản.<br>4. Nhấn nút "Gửi".<br>5. Hệ thống gửi tin nhắn thông qua API backend, lưu vào database và truyền phát WebSocket đến tài khoản của Quản lý tòa nhà. |
| **Luồng mở rộng** | - Tại bước 3, khách thuê có thể nhấn chọn nút đính kèm để chọn tối đa 5 hình ảnh từ thư viện thiết bị để gửi đi. |
| **Ngoại lệ** | - Nếu mất kết nối mạng giữa chừng, hệ thống hiển thị biểu tượng chấm than đỏ cạnh tin nhắn và thông báo "Gửi lỗi. Nhấn để gửi lại". |
| **Luật nghiệp vụ** | - Tin nhắn chỉ gửi được khi có nội dung văn bản (đã trim khoảng trắng) hoặc có hình ảnh đính kèm.<br>- Số lượng ảnh đính kèm tối đa cho một lượt gửi là 5 hình ảnh. Các ảnh được nén dung lượng tự động trước khi tải lên.<br>- Khi đối phương đã xem tin nhắn, trạng thái "Đã đọc" sẽ được cập nhật real-time nhờ sự kiện WebSocket Read. |
| **Giả định** | Máy chủ WebSocket Laravel Reverb hoạt động ổn định. |
| **Ghi chú & các vấn đề** | Tự động đồng bộ tin nhắn qua giao diện Optimistic UI để tối ưu trải nghiệm người dùng. |

---

### 3. Nhóm Use Case Ban Quản Lý (Manager)

#### Bảng 3.1: Đặc tả Use Case Quản lý khách thuê

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-12** |
| **Tên Use Case** | **Quản lý khách thuê** |
| **Tác nhân** | Ban quản lý |
| **Mô tả** | Quản lý danh sách cư dân (khách thuê) cư trú tại các tòa nhà được phân quyền phụ trách quản lý thực tế, hỗ trợ xem thông tin liên hệ chi tiết và kích hoạt hoặc khóa tài khoản cư dân. |
| **Điều kiện tiên quyết** | Tài khoản Ban quản lý đã đăng nhập vào ứng dụng di động. |
| **Hoàn tất** | Xem được thông tin cư dân và thực hiện thay đổi trạng thái kích hoạt/khóa tài khoản khách thuê thành công. |
| **Quy trình bình thường** | 1. Quản lý truy cập màn hình "Danh sách Khách thuê" (hoặc `/admin/tenants` trên di động).<br>2. Hệ thống hiển thị danh sách khách thuê đang thuê phòng thuộc các tòa nhà được gán quyền cho Quản lý.<br>3. Quản lý tìm kiếm khách thuê theo tên, số điện thoại hoặc số phòng trọ.<br>4. Quản lý bấm chọn khách thuê cụ thể để xem chi tiết hồ sơ (CCCD, email, quê quán, thông tin phòng...).<br>5. Quản lý nhấp vào nút Switch bên phải để thay đổi trạng thái hoạt động (Bật/Tắt) của tài khoản khách thuê đó. |
| **Luồng mở rộng** | Không có |
| **Ngoại lệ** | - Không tìm thấy khách thuê phù hợp với từ khóa tìm kiếm, hệ thống hiển thị thông báo: "Không tìm thấy khách thuê phù hợp". |
| **Luật nghiệp vụ** | - Quản lý chỉ xem và thao tác được trên các cư dân đang thuê phòng thuộc tòa nhà mình được phân quyền quản lý (`managedBuildingIds`).<br>- Trên ứng dụng di động chỉ hỗ trợ đổi trạng thái kích hoạt/khóa tài khoản, các thao tác thêm mới hồ sơ cư dân hoặc cập nhật ảnh CCCD phải được thực hiện trên Web Dashboard bởi Super Admin. |
| **Giả định** | Dữ liệu đồng bộ chính xác với cơ sở dữ liệu backend. |
| **Ghi chú & các vấn đề** | Đảm bảo đồng bộ tức thì trạng thái tài khoản với cơ chế bảo mật xác thực (Sanctum/Session). |

---

#### Bảng 3.2: Đặc tả Use Case Quản lý hợp đồng

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-13** |
| **Tên Use Case** | **Quản lý hợp đồng** |
| **Tác nhân** | Ban quản lý |
| **Mô tả** | Quản lý xem danh sách hợp đồng thuê phòng của tòa nhà phụ trách, theo dõi trạng thái hợp đồng (Chờ ký, Đang hoạt động, Đã quá hạn, Đã thanh lý). |
| **Điều kiện tiên quyết** | Tài khoản Ban quản lý đã đăng nhập vào ứng dụng di động. |
| **Hoàn tất** | Quản lý xem được danh sách và chi tiết thông tin hợp đồng của tòa nhà do mình phụ trách. |
| **Quy trình bình thường** | 1. Quản lý truy cập mục "Quản lý Hợp đồng" trên ứng dụng di động.<br>2. Hệ thống hiển thị danh sách các hợp đồng thuê phòng thuộc tòa nhà được phân quyền quản lý.<br>3. Quản lý lọc hợp đồng theo trạng thái hoặc tìm kiếm theo số phòng, tên khách thuê đại diện.<br>4. Quản lý chọn hợp đồng để xem chi tiết các điều khoản, thông tin tiền cọc, ngày bắt đầu/kết thúc và lịch sử ký tên của khách thuê. |
| **Luồng mở rộng** | Không có |
| **Ngoại lệ** | - Không tải được danh sách hợp đồng, hệ thống báo lỗi kết nối và yêu cầu tải lại trang. |
| **Luật nghiệp vụ** | - Phạm vi hợp đồng hiển thị bị giới hạn nghiêm ngặt theo danh sách các Tòa nhà mà Manager được phân quyền quản lý.<br>- Trên ứng dụng di động dành cho Manager chỉ hỗ trợ **Xem danh sách và chi tiết hợp đồng**, các chức năng Lập hợp đồng mới, Thêm người vào hợp đồng, Gia hạn & Thanh lý hợp đồng không hỗ trợ trên di động (chỉ thực hiện trên Web Dashboard bởi Super Admin). |
| **Giả định** | Các bản hợp đồng đã được số hóa và lưu trữ đúng định dạng trên máy chủ. |
| **Ghi chú & các vấn đề** | Đảm bảo thông tin hợp đồng đồng bộ với trạng thái thực tế của phòng trọ. |

---

#### Bảng 3.3: Đặc tả Use Case Chuyển phòng

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-14** |
| **Tên Use Case** | **Chuyển phòng** |
| **Tác nhân** | Ban quản lý |
| **Mô tả** | Cho phép Ban quản lý ghi nhận thủ tục chuyển phòng cho khách thuê từ phòng này sang phòng khác cùng tòa nhà hoặc khác tòa nhà (nếu được phân quyền quản lý). |
| **Điều kiện tiên quyết** | Khách thuê đang có hợp đồng thuê hoạt động và phòng đích đang ở trạng thái Trống. |
| **Hoàn tất** | Hợp đồng cũ được thanh lý/cập nhật, phòng cũ được chuyển về trạng thái Trống, phòng mới chuyển sang trạng thái Đang ở và thông tin cư dân được cập nhật sang phòng mới. |
| **Quy trình bình thường** | 1. Quản lý truy cập màn hình "Chuyển phòng cư dân" trên ứng dụng di động.<br>2. Quản lý chọn tòa nhà, chọn phòng hiện tại của khách thuê và chọn khách thuê cần chuyển phòng.<br>3. Hệ thống hiển thị thông tin hợp đồng hiện tại và danh sách phòng trống tương ứng làm điểm đến.<br>4. Quản lý chọn phòng đích mới, thiết lập ngày chuyển chính thức và nhập lý do chuyển phòng.<br>5. Quản lý kiểm tra đối soát chênh lệch tiền cọc, tiền phòng giữa phòng cũ và phòng mới.<br>6. Quản lý nhấn "Xác nhận chuyển phòng". Hệ thống cập nhật trạng thái phòng cũ thành Trống, chuyển khách sang phòng mới, cập nhật hợp đồng và lưu vết lịch sử chuyển phòng. |
| **Luồng mở rộng** | - Nếu có chênh lệch tiền phòng/tiền cọc, hệ thống tự động sinh ra một phiếu hóa đơn bổ sung hoặc khấu trừ cọc tương ứng cho kỳ thanh toán tiếp theo. |
| **Ngoại lệ** | - Nếu phòng đích mới chọn không ở trạng thái "Trống", hệ thống hiển thị cảnh báo lỗi và yêu cầu chọn phòng khác. |
| **Luật nghiệp vụ** | - Quản lý chỉ được phép thực hiện chuyển phòng giữa các tòa nhà mà mình được phân quyền quản lý.<br>- Phải chốt chỉ số điện nước cuối cùng của phòng cũ để lập hóa đơn thanh toán dứt điểm trước khi chuyển phòng. |
| **Giả định** | Cơ sở dữ liệu hoạt động ổn định và nhất quán thông tin phòng trọ. |
| **Ghi chú & các vấn đề** | Quy trình chuyển phòng đòi hỏi cập nhật nhiều bảng dữ liệu (Rooms, Contracts, Tenants, Invoices) cần được bọc trong Database Transaction để tránh mất mát/sai lệch dữ liệu. |

---

#### Bảng 3.4: Đặc tả Use Case Quản lý điện nước

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-15** |
| **Tên Use Case** | **Quản lý điện nước** |
| **Tác nhân** | Ban quản lý |
| **Mô tả** | Quản lý ghi nhận chỉ số điện, nước tiêu thụ định kỳ hàng tháng cho từng phòng trọ. Hỗ trợ chụp ảnh mặt đồng hồ bằng Camera điện thoại để AI tự động nhận dạng số hoặc nhập tay thủ công, sau đó thực hiện xuất hóa đơn. |
| **Điều kiện tiên quyết** | Tài khoản Ban quản lý đã đăng nhập vào ứng dụng di động. |
| **Hoàn tất** | Ghi nhận thành công chỉ số điện nước mới và tạo được hóa đơn thanh toán cho phòng trọ đó. |
| **Quy trình bình thường** | 1. Quản lý truy cập màn hình "Chốt số Điện nước" (hoặc `/admin/meters` trên di động).<br>2. Chọn Tòa nhà, chọn Kỳ chốt (Tháng/Năm) -> Hệ thống hiển thị danh sách phòng phân loại theo "ĐÃ CHỐT" hoặc "CHƯA XONG".<br>3. Quản lý bấm nút "Chốt số" trên phòng tương ứng.<br>4. Quản lý chụp ảnh đồng hồ điện/nước thực tế bằng camera điện thoại hoặc tải ảnh lên.<br>5. Hệ thống tự động gửi ảnh lên API backend AI-Service để phân tích nhận dạng chữ số và điền vào form chỉ số (OCR).<br>6. Quản lý kiểm tra lại độ chính xác, chỉnh sửa nếu cần và nhấn "LƯU LẠI" để ghi nhận chỉ số. |
| **Luồng mở rộng** | - Tại bước 4, quản lý có thể chọn nhập trực tiếp chỉ số mới của công tơ điện và đồng hồ nước vào ô nhập liệu bằng tay mà không cần qua bước quét ảnh AI.<br>- Sau khi chốt số thành công ở bước 6, Quản lý có thể bấm nút "Tạo HĐ" để xuất hóa đơn ngay cho phòng đó, hoặc bấm "TẠO TẤT CẢ HÓA ĐƠN" để lập hóa đơn hàng loạt cho toàn bộ các phòng đã chốt số. |
| **Ngoại lệ** | - Nếu chỉ số mới nhập vào nhỏ hơn chỉ số cũ ghi nhận ở kỳ liền trước (`current_reading < previous_reading`), hệ thống sẽ chặn không cho lưu và hiển thị thông báo lỗi: "Chỉ số mới không được nhỏ hơn chỉ số cũ".<br>- Nếu chất lượng ảnh không đạt chuẩn (lóa, mờ, tối...), hệ thống trả về mã lỗi (`image_blurry`, `image_too_dark`, `image_glare`, `no_meter_found`, `meter_type_mismatch`) và yêu cầu nhập tay. |
| **Luật nghiệp vụ** | - Ảnh chốt số đồng hồ điện/nước bắt buộc phải được chụp/tải lên để làm minh chứng hiển thị cho khách thuê đối soát trên hóa đơn.<br>- Không cho phép sửa đổi chỉ số chốt đối với những phòng trọ đã xuất hóa đơn hoặc hóa đơn của kỳ đó đã thanh toán. |
| **Giả định** | Dịch vụ AI nhận dạng chữ số hoạt động ổn định và có phản hồi nhanh. |
| **Ghi chú & các vấn đề** | Không có |

---

#### Bảng 3.5: Đặc tả Use Case Quản lý hóa đơn (Ban quản lý)

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-16** |
| **Tên Use Case** | **Quản lý hóa đơn (Ban quản lý)** |
| **Tác nhân** | Ban quản lý |
| **Mô tả** | Quản lý xem danh sách hóa đơn theo kỳ, theo dõi trạng thái công nợ của các phòng trọ và gửi QR nhắc nợ hoặc xác nhận thanh toán thủ công cho khách thuê. |
| **Điều kiện tiên quyết** | Tài khoản Ban quản lý đã đăng nhập vào ứng dụng di động. |
| **Hoàn tất** | Hóa đơn được cập nhật trạng thái thanh toán hoặc gửi nhắc nợ thành công đến thiết bị khách thuê. |
| **Quy trình bình thường** | 1. Quản lý truy cập mục "Quản lý Hóa đơn" trên ứng dụng di động.<br>2. Hệ thống hiển thị danh sách hóa đơn của các phòng trọ thuộc tòa nhà quản lý, lọc theo các tab trạng thái: Chưa thanh toán, Đã thanh toán, Quá hạn.<br>3. Quản lý chọn một hóa đơn cụ thể để xem chi tiết tiền phòng, điện nước và các khoản phụ phí.<br>4. Quản lý bấm nút "Nhắc nợ" để hệ thống tự động gửi Push Notification nhắc thanh toán kèm link hóa đơn đến ứng dụng của Khách thuê phòng đó. |
| **Luồng mở rộng** | - Nếu khách thuê thanh toán bằng tiền mặt trực tiếp cho Quản lý, Quản lý có thể chọn nút "Xác nhận thanh toán thủ công" trên giao diện để chuyển trạng thái hóa đơn thành "Đã thanh toán" và ghi chú lý do "Thanh toán tiền mặt cho Quản lý". |
| **Ngoại lệ** | - Lỗi mạng không gửi được yêu cầu cập nhật trạng thái hóa đơn, hệ thống báo lỗi và giữ nguyên trạng thái cũ của hóa đơn. |
| **Luật nghiệp vụ** | - Quản lý chỉ thao tác được hóa đơn của những phòng thuộc tòa nhà mình quản lý.<br>- Chỉ được phép gửi nhắc nợ đối với hóa đơn ở trạng thái "Chưa thanh toán" hoặc "Quá hạn". |
| **Giả định** | Thiết bị di động kết nối mạng ổn định. |
| **Ghi chú & các vấn đề** | Hệ thống SePay tự động khớp lệnh chuyển khoản nên hầu hết hóa đơn sẽ được thanh toán tự động, nút xác nhận thủ công chỉ dùng làm phương án dự phòng khi khách đóng tiền mặt. |

---

#### Bảng 3.6: Đặc tả Use Case Quản lý sự cố

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-17** |
| **Tên Use Case** | **Quản lý sự cố** |
| **Tác nhân** | Ban quản lý |
| **Mô tả** | Tiếp nhận các yêu cầu sửa chữa, báo cáo sự cố từ khách thuê gửi lên; cập nhật trạng thái xử lý sự cố và chỉ định kỹ thuật viên xử lý. |
| **Điều kiện tiên quyết** | Tài khoản Ban quản lý đã đăng nhập vào ứng dụng di động. |
| **Hoàn tất** | Sự cố được ghi nhận, cập nhật trạng thái xử lý và gửi thông báo kết quả cho khách thuê phòng. |
| **Quy trình bình thường** | 1. Quản lý truy cập mục "Quản lý Sự cố" trên ứng dụng di động.<br>2. Hệ thống hiển thị danh sách các yêu cầu sửa chữa được phân loại theo trạng thái.<br>3. Quản lý chọn một yêu cầu cụ thể để xem chi tiết mô tả, hình ảnh hư hỏng do khách thuê chụp gửi lên.<br>4. Quản lý nhấn nút "Tiếp nhận" để chuyển trạng thái sang "Đang sửa" và gán kỹ thuật viên chịu trách nhiệm.<br>5. Sau khi sự cố được khắc phục, Quản lý chụp ảnh kết quả sửa chữa, nhập ghi chú nghiệm thu và nhấn "Xác nhận hoàn thành".<br>6. Hệ thống cập nhật trạng thái yêu cầu thành "Đã hoàn thành" và gửi thông báo đến Khách thuê để thực hiện đánh giá. |
| **Luồng mở rộng** | - Tại bước 4, nếu sự cố không nằm trong phạm vi sửa chữa của tòa nhà hoặc do khách thuê tự ý làm hỏng sai quy định, Quản lý có thể chọn nút "Từ chối/Hủy yêu cầu" kèm nhập lý do từ chối. |
| **Ngoại lệ** | - Quản lý không tải được ảnh chụp nghiệm thu lên hệ thống do dung lượng ảnh lớn hoặc kết nối mạng yếu, hệ thống báo lỗi và yêu cầu thử lại. |
| **Luật nghiệp vụ** | - Khi chuyển trạng thái sang "Đã hoàn thành", hệ thống bắt buộc yêu cầu tải lên ảnh sau khi sửa (After Image) làm minh chứng đối chiếu cho Khách thuê. |
| **Giả định** | Máy chủ lưu trữ ảnh hoạt động bình thường. |
| **Ghi chú & các vấn đề** | Không có |

---

#### Bảng 3.7: Đặc tả Use Case Gửi thông báo

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-18** |
| **Tên Use Case** | **Gửi thông báo** |
| **Tác nhân** | Ban quản lý |
| **Mô tả** | Cho phép Quản lý soạn thảo văn bản thông báo và gửi hàng loạt đến ứng dụng của toàn bộ khách thuê trong một hoặc nhiều tòa nhà do mình phụ trách. |
| **Điều kiện tiên quyết** | Tài khoản Ban quản lý đã đăng nhập vào ứng dụng di động. |
| **Hoàn tất** | Thông báo được lưu vào database và gửi qua Push Notification đến tất cả khách thuê trong tòa nhà được chọn. |
| **Quy trình bình thường** | 1. Quản lý truy cập mục "Gửi thông báo" trên ứng dụng di động.<br>2. Chọn tòa nhà đích nhận thông báo (hoặc chọn toàn bộ tòa nhà thuộc quyền quản lý).<br>3. Nhập tiêu đề thông báo và nội dung chi tiết thông báo.<br>4. Nhấn nút "Gửi thông báo".<br>5. Hệ thống gửi yêu cầu lên API backend, lưu thông tin thông báo và kích hoạt dịch vụ Push Notification (FCM/OneSignal) gửi đến thiết bị di động của các khách thuê. |
| **Luồng mở rộng** | - Quản lý có thể chọn đính kèm thêm file hoặc ảnh minh họa đính kèm thông báo. |
| **Ngoại lệ** | - Dịch vụ Push Notification gặp sự cố, hệ thống vẫn lưu thông báo vào cơ sở dữ liệu để hiển thị trong ứng dụng khi khách thuê mở danh sách chuông thông báo, nhưng sẽ hiện cảnh báo: "Không thể gửi tin nhắn đẩy Push, dữ liệu vẫn được lưu trên hệ thống". |
| **Luật nghiệp vụ** | - Tiêu đề thông báo không được vượt quá 100 ký tự; nội dung thông báo bắt buộc không được để trống. |
| **Giả định** | Dịch vụ thông báo đẩy (Firebase Cloud Messaging) hoạt động bình thường. |
| **Ghi chú & các vấn đề** | Không có |

---

#### Bảng 3.8: Đặc tả Use Case Chat cư dân

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-19** |
| **Tên Use Case** | **Chat cư dân** |
| **Tác nhân** | Ban quản lý |
| **Mô tả** | Quản lý xem danh sách các cuộc trò chuyện và nhắn tin phản hồi thời gian thực với các cư dân (khách thuê) trong tòa nhà của mình để hỗ trợ giải đáp thắc mắc. |
| **Điều kiện tiên quyết** | Tài khoản Ban quản lý đã đăng nhập vào ứng dụng di động. |
| **Hoàn tất** | Tin nhắn phản hồi được gửi và hiển thị lập tức trên màn hình chat của khách thuê tương ứng. |
| **Quy trình bình thường** | 1. Quản lý truy cập mục "Chat cư dân" trên ứng dụng di động.<br>2. Hệ thống hiển thị danh sách các phòng trọ/khách thuê đang mở hội thoại chat, sắp xếp theo thời gian tin nhắn mới nhất và làm nổi bật các hội thoại có tin nhắn chưa đọc.<br>3. Quản lý bấm chọn một hội thoại để vào màn hình chat chi tiết.<br>4. Nhập nội dung tin nhắn và nhấn nút "Gửi".<br>5. Hệ thống gửi tin nhắn qua WebSocket đến tài khoản khách thuê đó tức thì. |
| **Luồng mở rộng** | - Tại bước 4, quản lý có thể gửi đính kèm hình ảnh minh chứng hoặc tài liệu hướng dẫn cho khách thuê (tối đa 5 ảnh). |
| **Ngoại lệ** | - Nếu WebSocket bị ngắt kết nối tạm thời, hệ thống tự động cố gắng kết nối lại (auto-reconnect) và hiển thị thanh trạng thái "Đang kết nối lại...". Tin nhắn gửi trong lúc này được xếp vào hàng đợi chờ kết nối lại. |
| **Luật nghiệp vụ** | - Quản lý chỉ thấy và chat được với các cư dân thuộc tòa nhà mình quản lý.<br>- Khi mở màn hình chat, hệ thống tự động gửi sự kiện `read` qua WebSocket để cập nhật trạng thái "Đã đọc" cho các tin nhắn của cư dân. |
| **Giả định** | Máy chủ Laravel Reverb WebSocket hoạt động bình thường. |
| **Ghi chú & các vấn đề** | Không có |

---

#### Bảng 3.9: Đặc tả Use Case Xem danh sách phòng

| Thuộc tính | Nội dung |
| :--- | :--- |
| **Use Case ID** | **UC-20** |
| **Tên Use Case** | **Xem danh sách phòng** |
| **Tác nhân** | Ban quản lý |
| **Mô tả** | Xem danh sách phòng trọ trực thuộc tòa nhà do Quản lý phụ trách, hiển thị trạng thái hiện tại (Trống, Đang ở, Bảo trì) và cho phép đổi nhanh trạng thái hoạt động của phòng. |
| **Điều kiện tiên quyết** | Tài khoản Ban quản lý đã đăng nhập vào ứng dụng di động. |
| **Hoàn tất** | Quản lý xem được tình trạng các phòng và thay đổi thành công trạng thái hoạt động của phòng trọ. |
| **Quy trình bình thường** | 1. Quản lý truy cập mục "Trạng thái phòng" (hoặc `/admin/rooms` trên di động).<br>2. Hệ thống hiển thị danh sách phòng của tòa nhà được phân quyền phụ trách.<br>3. Quản lý tìm kiếm theo số phòng hoặc lọc nhanh theo trạng thái phòng: Trống, Đang ở, Bảo trì.<br>4. Quản lý nhấn vào một phòng cụ thể để xem thông tin chi tiết (khách thuê hiện tại, thiết bị điện nước đang gán, lịch sử thanh toán hóa đơn gần nhất).<br>5. Để thay đổi trạng thái hoạt động của phòng, Quản lý nhấn nút "Cập nhật" và chọn trạng thái mới (ví dụ: Bảo trì).<br>6. Hệ thống lưu trạng thái mới và hiển thị thông tin cập nhật trên giao diện. |
| **Luồng mở rộng** | Không có |
| **Ngoại lệ** | - Nếu phòng đang có khách thuê ở thực tế (số lượng khách ở > 0 hoặc hợp đồng hoạt động), hệ thống sẽ chặn không cho chuyển phòng sang trạng thái "Bảo trì" và hiển thị thông báo lỗi: "Không thể bảo trì phòng đang có người ở". |
| **Luật nghiệp vụ** | - Quản lý chỉ có quyền xem danh sách và cập nhật trạng thái nhanh (Trống/Bảo trì), không có quyền thêm mới phòng hoặc sửa đổi đơn giá phòng (các quyền này thuộc về Super Admin trên Web). |
| **Giả định** | Cơ sở dữ liệu đồng bộ chính xác. |
| **Ghi chú & các vấn đề** | Không có |
