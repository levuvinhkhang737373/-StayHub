# Chương 4: KẾT QUẢ

## 4.1. Tổng quan hệ thống

Hệ thống **StayHub** được triển khai trên nền tảng **Web Admin**, tập trung phục vụ công tác quản lý và vận hành căn hộ, phòng trọ, ký túc xá. Phạm vi chương này chỉ trình bày các giao diện thuộc **Web Admin**, tập trung vào các chức năng quản trị và vận hành của hệ thống.

Hệ thống phân quyền cho **2 nhóm người dùng chính**:

| Nhóm người dùng | Mô tả |
|---|---|
| **Super Admin** (Quản trị hệ thống) | Có toàn quyền quản lý hệ thống: dashboard tổng quan, khu vực, tòa nhà, loại phòng, phòng, mẫu tài sản, dịch vụ, tài khoản admin, nhật ký thao tác và các báo cáo. |
| **Admin** (Quản lý tòa nhà) | Quản lý nghiệp vụ vận hành hằng ngày trong phạm vi tòa nhà được phân công: phòng, khách thuê, hợp đồng, điện nước, hóa đơn, công nợ, thanh toán, bảo trì, thông báo và trò chuyện. |

Các màn hình trong chương được trình bày theo từng phân hệ chức năng. Mỗi màn hình đều có **đường dẫn**, **mô tả**, **thao tác & ràng buộc** và **vị trí chèn hình** để thuận tiện khi hoàn thiện báo cáo.

---

## 4.2. Phân hệ Xác thực & Tài khoản

### 4.2.1. Màn hình Đăng nhập

**Đường dẫn:** `/admin/login`

**Mô tả:** Màn hình đăng nhập dành cho quản trị viên và quản lý tòa nhà truy cập vào hệ thống Web Admin. Người dùng có thể đăng nhập bằng tài khoản/mật khẩu hoặc sử dụng FaceID nếu đã đăng ký khuôn mặt.

**Thao tác & Ràng buộc:**

- Người dùng nhập **tên đăng nhập** và **mật khẩu** để đăng nhập.
- Hệ thống kiểm tra thông tin đăng nhập, trạng thái tài khoản và quyền truy cập.
- Người dùng có thể đăng nhập bằng **FaceID** bằng cách mở camera và quét khuôn mặt.
- Khi dùng FaceID, trình duyệt phải được cấp quyền camera và khuôn mặt phải nằm rõ trong khung hình.
- Nếu đăng nhập thành công, hệ thống chuyển người dùng đến màn hình **Dashboard** theo đúng quyền hạn.
- Nếu đăng nhập thất bại, hệ thống hiển thị thông báo lỗi tương ứng như sai tài khoản/mật khẩu, tài khoản bị khóa hoặc FaceID không khớp.

**Hình 4.1:** Màn hình đăng nhập Web Admin *(chèn hình tại đây)*

### 4.2.2. Màn hình Cài đặt tài khoản cá nhân

**Đường dẫn:** Mở từ khu vực thông tin tài khoản trên thanh giao diện Admin

**Mô tả:** Màn hình cài đặt tài khoản cho phép admin cập nhật thông tin cá nhân, đổi mật khẩu và quản lý FaceID đăng nhập nhanh.

**Thao tác & Ràng buộc:**

- Người dùng cập nhật họ tên, số điện thoại và ảnh đại diện.
- Họ tên không được để trống.
- Số điện thoại nếu nhập phải đúng định dạng số điện thoại Việt Nam.
- Người dùng đổi mật khẩu bằng cách nhập mật khẩu hiện tại, mật khẩu mới và xác nhận mật khẩu mới.
- Mật khẩu xác nhận phải trùng với mật khẩu mới.
- Khi đăng ký FaceID, hệ thống yêu cầu camera hoạt động và khuôn mặt đủ rõ để lưu dữ liệu nhận diện.
- Người dùng có thể xóa FaceID đã đăng ký nếu không muốn sử dụng đăng nhập khuôn mặt.

**Hình 4.2:** Màn hình cài đặt tài khoản admin *(chèn hình tại đây)*

---

## 4.3. Phân hệ Dashboard

### 4.3.1. Màn hình Dashboard tổng quan

**Đường dẫn:** `/admin/dashboard`

**Mô tả:** Dashboard là màn hình tổng quan sau khi đăng nhập, giúp người dùng nắm nhanh tình hình vận hành hệ thống qua các chỉ số, biểu đồ và cảnh báo nghiệp vụ.

**Thao tác & Ràng buộc:**

- Người dùng xem các chỉ số tổng quan như doanh thu, công nợ, chi phí, lợi nhuận, số phòng, tỷ lệ lấp đầy, hóa đơn và yêu cầu bảo trì.
- Có thể lọc dữ liệu theo **tháng**, **năm** hoặc **tòa nhà** tùy quyền truy cập.
- **Super Admin** có thể xem dữ liệu toàn hệ thống.
- **Admin** chỉ xem dữ liệu thuộc tòa nhà được phân công quản lý.
- Các biểu đồ chỉ hiển thị đầy đủ khi hệ thống đã phát sinh hóa đơn, thanh toán hoặc phiếu chi.
- Người dùng có thể nhấn vào các thẻ cảnh báo/lối tắt để chuyển nhanh sang phân hệ liên quan.

**Hình 4.3:** Màn hình Dashboard tổng quan *(chèn hình tại đây)*

---

## 4.4. Phân hệ Quản lý lưu trú

### 4.4.1. Màn hình Quản lý khu vực & tòa nhà

**Đường dẫn:** `/admin/facilities`

**Mô tả:** Màn hình quản lý khu vực và tòa nhà dùng để thiết lập cấu trúc lưu trú của hệ thống. Đây là dữ liệu nền cho phòng, khách thuê, hợp đồng, dịch vụ, hóa đơn và báo cáo.

**Thao tác & Ràng buộc:**

- Người dùng xem, tìm kiếm, thêm mới, cập nhật và đổi trạng thái khu vực.
- Người dùng xem, thêm mới, cập nhật, đổi trạng thái tòa nhà và gán người quản lý tòa nhà.
- Khu vực phải có **mã khu vực** và **tên khu vực**.
- Mã khu vực tối đa 50 ký tự, chỉ gồm chữ, số, dấu gạch ngang hoặc gạch dưới và không được trùng.
- Tên khu vực và tên tòa nhà tối đa 150 ký tự.
- Tòa nhà phải thuộc một khu vực hợp lệ.
- Tổng số tầng của tòa nhà phải là số nguyên từ 1 đến 1000.
- Ảnh tòa nhà hỗ trợ JPG, PNG, WEBP; mỗi ảnh tối đa 10MB và mỗi lần tải tối đa 20 ảnh.
- Chức năng này thuộc nhóm quyền ưu tiên của **Super Admin**.

**Hình 4.4:** Màn hình quản lý khu vực và tòa nhà *(chèn hình tại đây)*

### 4.4.2. Màn hình Tạo/Cập nhật tòa nhà

**Đường dẫn:** `/admin/facilities/buildings/create`, `/admin/facilities/buildings/:buildingId/edit`

**Mô tả:** Màn hình tạo hoặc cập nhật tòa nhà cho phép nhập thông tin chi tiết của tòa nhà, cấu hình bảng giá dịch vụ, chính sách giới tính và các cài đặt hiển thị cho khách thuê.

**Thao tác & Ràng buộc:**

- Người dùng chọn khu vực, nhập tên tòa nhà, địa chỉ, số tầng, mô tả và trạng thái.
- Có thể gán quản lý tòa nhà phụ trách.
- Có thể cấu hình giá dịch vụ theo tòa nhà như điện, nước, internet hoặc các dịch vụ khác.
- Bảng giá dịch vụ cần có dịch vụ, giá hợp lệ và ngày bắt đầu hiệu lực đúng định dạng.
- Có thể cấu hình các nội dung cài đặt của tòa nhà như nội quy, hướng dẫn thanh toán hoặc thông tin liên hệ.
- Cài đặt cần có tên hiển thị rõ ràng nếu muốn lưu vào hệ thống.
- Chính sách giới tính của tòa nhà ảnh hưởng đến việc thêm khách thuê vào tòa nhà/phòng.

**Hình 4.5:** Màn hình tạo/cập nhật tòa nhà *(chèn hình tại đây)*

### 4.4.3. Màn hình Quản lý loại phòng

**Đường dẫn:** `/admin/room-types`

**Mô tả:** Màn hình quản lý loại phòng dùng để chuẩn hóa danh mục loại phòng trong hệ thống, giúp quá trình tạo phòng và báo cáo dữ liệu được đồng nhất.

**Thao tác & Ràng buộc:**

- Người dùng xem danh sách, tìm kiếm, thêm mới, cập nhật và đổi trạng thái loại phòng.
- Tên loại phòng là bắt buộc và tối đa 150 ký tự.
- Mô tả loại phòng tối đa 2000 ký tự.
- Trạng thái loại phòng phải hợp lệ theo cấu hình hệ thống.
- Loại phòng ngừng hoạt động không nên được dùng để tạo phòng mới.
- Chức năng này thuộc nhóm quyền ưu tiên của **Super Admin**.

**Hình 4.6:** Màn hình quản lý loại phòng *(chèn hình tại đây)*

### 4.4.4. Màn hình Quản lý mẫu tài sản

**Đường dẫn:** `/admin/asset-templates`

**Mô tả:** Màn hình quản lý mẫu tài sản dùng để khai báo các tài sản mẫu như giường, tủ, bàn, ghế, máy lạnh,... Các mẫu tài sản này được sử dụng khi tạo hoặc cập nhật phòng.

**Thao tác & Ràng buộc:**

- Người dùng xem danh sách, tìm kiếm, thêm mới, cập nhật và đổi trạng thái mẫu tài sản.
- Tên mẫu tài sản là bắt buộc và tối đa 150 ký tự.
- Đơn vị mặc định phải thuộc danh sách hệ thống cho phép.
- Mô tả mẫu tài sản tối đa 2000 ký tự.
- Trạng thái mẫu tài sản phải hợp lệ.
- Chỉ nên chọn các mẫu tài sản đang hoạt động khi gán tài sản vào phòng.
- Chức năng này thuộc nhóm quyền ưu tiên của **Super Admin**.

**Hình 4.7:** Màn hình quản lý mẫu tài sản *(chèn hình tại đây)*

### 4.4.5. Màn hình Quản lý phòng

**Đường dẫn:** `/admin/rooms`

**Mô tả:** Màn hình quản lý phòng hiển thị danh sách phòng trong hệ thống, hỗ trợ theo dõi trạng thái phòng, thông tin tòa nhà, loại phòng, giá phòng và sức chứa.

**Thao tác & Ràng buộc:**

- Người dùng lọc phòng theo tòa nhà, trạng thái hoặc từ khóa.
- Người dùng xem thông tin phòng, chuyển sang tạo phòng hoặc cập nhật phòng.
- Có thể đổi trạng thái phòng khi cần quản lý tình trạng sử dụng.
- **Super Admin** có thể xem/quản lý toàn bộ phòng.
- **Admin** chỉ thao tác trên phòng thuộc tòa nhà mình quản lý.
- Không nên đổi trạng thái phòng đang có hợp đồng hoặc khách thuê nếu chưa kiểm tra nghiệp vụ liên quan.

**Hình 4.8:** Màn hình quản lý phòng *(chèn hình tại đây)*

### 4.4.6. Màn hình Tạo/Cập nhật phòng

**Đường dẫn:** `/admin/rooms/create`, `/admin/rooms/update/:id`

**Mô tả:** Màn hình tạo/cập nhật phòng cho phép nhập thông tin chi tiết của phòng, bao gồm tòa nhà, loại phòng, số phòng, tầng, diện tích, giá cơ bản, sức chứa, tài sản, ảnh phòng và chỉ số điện nước ban đầu.

**Thao tác & Ràng buộc:**

- Người dùng phải chọn tòa nhà trước khi nhập hoặc chọn các dữ liệu phụ thuộc.
- Cần chọn loại phòng và nhập số phòng hợp lệ.
- Tầng, diện tích, giá phòng và số người tối đa phải là dữ liệu hợp lệ.
- Có thể chọn tài sản có sẵn hoặc tạo nhanh mẫu tài sản mới.
- Số lượng tài sản trong phòng phải là số hợp lệ, ghi chú tài sản tối đa 500 ký tự.
- Có thể tải nhiều ảnh phòng để minh họa tình trạng phòng.
- Dữ liệu phòng ảnh hưởng trực tiếp đến hợp đồng, hóa đơn và báo cáo nên cần nhập chính xác.

**Hình 4.9:** Màn hình tạo/cập nhật phòng *(chèn hình tại đây)*

---

## 4.5. Phân hệ Khách thuê & Hợp đồng

### 4.5.1. Màn hình Quản lý khách thuê

**Đường dẫn:** `/admin/tenants`

**Mô tả:** Màn hình quản lý khách thuê dùng để quản lý tài khoản và hồ sơ cá nhân của khách thuê. Dữ liệu khách thuê được sử dụng trong quá trình lập hợp đồng, chuyển phòng, quản lý phương tiện, hóa đơn và bảo trì.

**Thao tác & Ràng buộc:**

- Người dùng tìm kiếm, lọc và xem danh sách khách thuê.
- Có thể thêm mới, cập nhật thông tin hoặc đổi trạng thái khách thuê.
- Tên đăng nhập, họ tên, email, số điện thoại, ngày sinh, giới tính và số giấy tờ là các thông tin quan trọng cần nhập đúng.
- Tên đăng nhập chỉ gồm chữ, số, dấu gạch ngang, gạch dưới hoặc dấu chấm; tối đa 255 ký tự.
- Email phải đúng định dạng và tối đa 150 ký tự.
- Số điện thoại phải gồm 10 số và thuộc đầu số nhà mạng Việt Nam hợp lệ.
- Ngày sinh không được lớn hơn ngày hiện tại.
- CCCD phải gồm đúng 12 chữ số; hộ chiếu phải gồm đúng 9 ký tự chữ/số.
- Ảnh giấy tờ hỗ trợ JPG, PNG, WEBP và mỗi ảnh tối đa 10MB.
- Chính sách giới tính của tòa nhà có thể giới hạn khách thuê nam/nữ khi thêm vào tòa nhà hoặc hợp đồng.

**Hình 4.10:** Màn hình quản lý khách thuê *(chèn hình tại đây)*

### 4.5.2. Màn hình Tạo/Cập nhật khách thuê

**Đường dẫn:** `/admin/tenants/create`, `/admin/tenants/:tenantId/edit`

**Mô tả:** Màn hình tạo/cập nhật khách thuê cho phép nhập thông tin đăng nhập, thông tin cá nhân, thông tin liên hệ, giấy tờ tùy thân và trạng thái của khách thuê.

**Thao tác & Ràng buộc:**

- Người dùng nhập tài khoản, họ tên, email, số điện thoại, ngày sinh, giới tính và trạng thái.
- Người dùng chọn loại giấy tờ và nhập số giấy tờ tương ứng.
- Có thể nhập địa chỉ thường trú, địa chỉ hiện tại và tải ảnh giấy tờ.
- Với **Super Admin**, cần chọn tòa nhà khi dữ liệu yêu cầu phạm vi tòa nhà.
- Ảnh đại diện của khách thuê không do admin cập nhật ở màn hình này; khách thuê tự cập nhật ở giao diện riêng nếu có.
- Thông tin khách thuê cần chính xác để tránh sai lệch hợp đồng và hóa đơn sau này.

**Hình 4.11:** Màn hình tạo/cập nhật khách thuê *(chèn hình tại đây)*

### 4.5.3. Màn hình Quản lý phương tiện

**Đường dẫn:** `/admin/vehicles`

**Mô tả:** Màn hình phương tiện dùng để quản lý xe của khách thuê trong tòa nhà. Dữ liệu phương tiện có thể được liên kết với hợp đồng để tính phí gửi xe.

**Thao tác & Ràng buộc:**

- Người dùng lọc phương tiện theo tòa nhà, khách thuê, loại phương tiện hoặc trạng thái.
- Có thể thêm mới, cập nhật và đổi trạng thái phương tiện.
- Phải chọn tòa nhà và khách thuê trước khi tạo phương tiện.
- Phải chọn loại phương tiện hợp lệ.
- Biển số xe là bắt buộc với các loại xe cần quản lý biển số.
- Biển số xe tối đa 30 ký tự.
- Thông tin phương tiện cần chính xác để tính phí gửi xe và quản lý bãi xe.

**Hình 4.12:** Màn hình quản lý phương tiện *(chèn hình tại đây)*

### 4.5.4. Màn hình Quản lý hợp đồng

**Đường dẫn:** `/admin/contracts`

**Mô tả:** Màn hình hợp đồng quản lý toàn bộ vòng đời thuê phòng, từ tạo hợp đồng, thêm khách thuê, ghi nhận tiền cọc, gia hạn, chấm dứt đến cập nhật trạng thái hợp đồng.

**Thao tác & Ràng buộc:**

- Người dùng lọc hợp đồng theo tòa nhà, phòng, trạng thái hoặc từ khóa.
- Có thể xem chi tiết, tạo mới, cập nhật, gia hạn hoặc chấm dứt hợp đồng.
- Hợp đồng liên kết dữ liệu phòng, khách thuê, phương tiện, dịch vụ, tiền cọc và hóa đơn.
- **Super Admin** và **Admin** đều có thể quản lý hợp đồng theo quyền được cấp.
- Admin chỉ thao tác trên hợp đồng thuộc tòa nhà mình quản lý.
- Các thao tác gia hạn/chấm dứt cần kiểm tra công nợ, tiền cọc và tình trạng phòng trước khi thực hiện.

**Hình 4.13:** Màn hình quản lý hợp đồng *(chèn hình tại đây)*

### 4.5.5. Màn hình Tạo/Cập nhật hợp đồng

**Đường dẫn:** `/admin/contracts/create`, `/admin/contracts/:contractId/edit`, `/admin/contracts/:contractId/renew`

**Mô tả:** Màn hình tạo/cập nhật hợp đồng cho phép nhập thông tin thuê phòng, thời hạn, giá phòng, tiền cọc, danh sách khách thuê, phương tiện, dịch vụ và file hợp đồng.

**Thao tác & Ràng buộc:**

- Người dùng chọn tòa nhà, phòng, ngày bắt đầu, ngày kết thúc, giá phòng và tiền cọc.
- Phải chọn phòng ký hợp đồng; **Super Admin** cần chọn tòa nhà trước khi chọn phòng.
- Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.
- Giá phòng là bắt buộc và phải là số tiền không âm.
- Tiền cọc là bắt buộc, phải lớn hơn 0 và theo ràng buộc hiện tại phải lớn hơn tiền phòng.
- Hợp đồng phải có ít nhất 1 khách thuê và ít nhất 1 khách thuê đang ở.
- Số khách thuê đang ở không được vượt quá sức chứa tối đa của phòng.
- Không được chọn trùng khách thuê trong cùng hợp đồng.
- File hợp đồng tối đa 10 file, hỗ trợ PDF, JPG, PNG, WEBP và mỗi file không vượt quá 20MB.

**Hình 4.14:** Màn hình tạo/cập nhật hợp đồng *(chèn hình tại đây)*

### 4.5.6. Màn hình Chi tiết hợp đồng

**Đường dẫn:** Mở từ màn hình `/admin/contracts`

**Mô tả:** Màn hình chi tiết hợp đồng hiển thị đầy đủ thông tin hợp đồng, khách thuê, phòng, tiền cọc, phương tiện, dịch vụ, trạng thái và các thao tác nghiệp vụ liên quan.

**Thao tác & Ràng buộc:**

- Người dùng xem thông tin hợp đồng, thời hạn thuê, phòng, khách thuê và trạng thái ký.
- Có thể ghi nhận giao dịch cọc hoặc xem mã QR cọc nếu hệ thống phát sinh.
- Có thể thêm khách thuê vào hợp đồng khi hợp đồng còn phù hợp để bổ sung người ở.
- Khi thêm khách thuê, không được vượt quá sức chứa phòng và không được chọn trùng khách thuê.
- Các thay đổi liên quan hợp đồng cần đảm bảo không làm sai lệch dữ liệu hóa đơn, cọc và trạng thái phòng.

**Hình 4.15:** Màn hình chi tiết hợp đồng *(chèn hình tại đây)*

### 4.5.7. Màn hình Chuyển phòng

**Đường dẫn:** `/admin/transfer-room`

**Mô tả:** Màn hình chuyển phòng hỗ trợ chuyển khách thuê từ phòng hiện tại sang phòng mới, đồng thời lưu lại lịch sử biến động phòng và dữ liệu cọc liên quan.

**Thao tác & Ràng buộc:**

- Người dùng chọn khách thuê hoặc hợp đồng cần chuyển phòng.
- Chọn tòa nhà, phòng mới và ngày chuyển phòng.
- Phòng mới phải còn khả dụng và phù hợp với sức chứa.
- Nếu phòng cũ cần chốt điện nước trước khi chuyển, hệ thống yêu cầu chốt chỉ số đến ngày chuyển.
- Số tiền thu chuyển phòng được hệ thống tự tính, không gửi trực tiếp từ giao diện.
- Dữ liệu chuyển phòng được lưu lại để phục vụ đối soát lịch sử cư trú và tiền cọc.

**Hình 4.16:** Màn hình chuyển phòng khách thuê *(chèn hình tại đây)*

### 4.5.8. Màn hình Lịch sử phòng & cọc

**Đường dẫn:** `/admin/room-movements`

**Mô tả:** Màn hình lịch sử phòng & cọc cho phép theo dõi toàn bộ quá trình chuyển phòng, thay đổi nơi ở và các giao dịch cọc phát sinh trong quá trình cư trú.

**Thao tác & Ràng buộc:**

- Người dùng lọc lịch sử theo tòa nhà, phòng, khách thuê hoặc thời gian.
- Xem thông tin phòng cũ, phòng mới, ngày chuyển, trạng thái chuyển và dữ liệu cọc.
- Có thể cập nhật ngày chuyển phòng khi lịch chuyển thay đổi.
- Có thể ghi nhận tiền quyết toán chuyển phòng nếu phát sinh thanh toán tiền mặt.
- Lịch sử phòng & cọc là dữ liệu đối soát, không nên chỉnh sửa nếu không có nghiệp vụ thực tế.

**Hình 4.17:** Màn hình lịch sử phòng và tiền cọc *(chèn hình tại đây)*

---

## 4.6. Phân hệ Dịch vụ & Điện nước

### 4.6.1. Màn hình Quản lý dịch vụ

**Đường dẫn:** `/admin/services`

**Mô tả:** Màn hình dịch vụ dùng để khai báo các khoản phí như điện, nước, internet, vệ sinh, gửi xe hoặc các dịch vụ khác. Dịch vụ là dữ liệu đầu vào cho bảng giá, đồng hồ và hóa đơn.

**Thao tác & Ràng buộc:**

- Người dùng xem danh sách, tìm kiếm, thêm mới, cập nhật và đổi trạng thái dịch vụ.
- Tên dịch vụ là bắt buộc và tối đa 150 ký tự.
- Phương thức tính phí phải thuộc danh sách hệ thống cho phép.
- Đơn vị tính tối đa 50 ký tự.
- Trạng thái bắt buộc và trạng thái hoạt động phải là giá trị hợp lệ.
- Dịch vụ ngừng hoạt động không nên được dùng để phát sinh hóa đơn mới.
- Chức năng này thuộc nhóm quyền ưu tiên của **Super Admin**.

**Hình 4.18:** Màn hình quản lý dịch vụ *(chèn hình tại đây)*

### 4.6.2. Màn hình Quản lý đồng hồ điện nước

**Đường dẫn:** `/admin/meters`

**Mô tả:** Màn hình đồng hồ điện nước dùng để khai báo thiết bị đo cho từng phòng. Mỗi đồng hồ được gắn với tòa nhà, phòng, dịch vụ và chỉ số ban đầu.

**Thao tác & Ràng buộc:**

- Người dùng lọc đồng hồ theo tòa nhà, phòng, dịch vụ hoặc trạng thái.
- Có thể thêm mới, cập nhật và đổi trạng thái đồng hồ.
- Phải chọn tòa nhà, phòng và dịch vụ trước khi tạo đồng hồ.
- Chỉ số ban đầu là bắt buộc và phải là số không âm.
- Nếu trạng thái là đã bị thay thế, phải chọn đồng hồ thay thế.
- Đồng hồ thay thế phải là đồng hồ hợp lệ trong hệ thống.
- Dữ liệu đồng hồ ảnh hưởng trực tiếp đến việc chốt điện nước và tính hóa đơn.

**Hình 4.19:** Màn hình quản lý đồng hồ điện nước *(chèn hình tại đây)*

### 4.6.3. Màn hình Chốt điện nước

**Đường dẫn:** `/admin/meter-readings`

**Mô tả:** Màn hình chốt điện nước cho phép quản lý nhập chỉ số điện/nước theo từng phòng và từng kỳ. Hệ thống hỗ trợ tải ảnh công tơ và phân tích ảnh bằng AI để gợi ý chỉ số.

**Thao tác & Ràng buộc:**

- Người dùng chọn tòa nhà, tháng và năm cần chốt chỉ số.
- Mở từng phòng để nhập chỉ số điện, nước mới.
- Có thể tải ảnh công tơ để lưu minh chứng hoặc gửi AI phân tích chỉ số.
- Chỉ số mới không được nhỏ hơn chỉ số cũ.
- Nếu AI trả kết quả không hợp lệ hoặc nhận diện sai, người dùng phải nhập tay chỉ số chính xác.
- Ảnh công tơ cần rõ nét, đủ sáng và thể hiện đầy đủ mặt số.
- Cần chốt đủ các đồng hồ bắt buộc trước khi sinh hóa đơn.
- Phòng trống hoặc phòng chưa có hợp đồng không thể sinh hóa đơn thuê phòng.

**Hình 4.20:** Màn hình chốt chỉ số điện nước *(chèn hình tại đây)*

### 4.6.4. Màn hình Sinh hóa đơn từ chốt điện nước

**Đường dẫn:** `/admin/meter-readings`

**Mô tả:** Sau khi chốt đủ chỉ số điện nước, người dùng có thể sinh hóa đơn cho từng phòng hoặc sinh hóa đơn hàng loạt theo tòa nhà. Hệ thống tự tính sản lượng tiêu thụ và tổng hợp các khoản phí liên quan.

**Thao tác & Ràng buộc:**

- Người dùng kiểm tra trạng thái chốt điện/nước của từng phòng.
- Nhấn sinh hóa đơn cho từng phòng hoặc sinh hóa đơn hàng loạt.
- Hệ thống tính tiêu thụ bằng chỉ số mới trừ chỉ số cũ.
- Hóa đơn chỉ được sinh khi hợp đồng còn phù hợp và dữ liệu dịch vụ đã đầy đủ.
- Với phòng có lịch chuyển phòng, hệ thống có thể yêu cầu chốt chỉ số đến ngày chuyển.
- Người dùng cần xem trước hóa đơn trước khi xác nhận phát hành để tránh sai lệch tiền phòng, dịch vụ hoặc công nợ.

**Hình 4.21:** Màn hình sinh hóa đơn sau khi chốt điện nước *(chèn hình tại đây)*

---

## 4.7. Phân hệ Tài chính & Báo cáo

### 4.7.1. Màn hình Quản lý hóa đơn

**Đường dẫn:** `/admin/invoices`

**Mô tả:** Màn hình hóa đơn cho phép quản lý theo dõi, tạo, xem chi tiết, cập nhật, hủy hóa đơn và xử lý thanh toán. Đây là phân hệ trung tâm của nghiệp vụ thu tiền.

**Thao tác & Ràng buộc:**

- Người dùng lọc hóa đơn theo tòa nhà, phòng, tháng, năm, trạng thái hoặc từ khóa.
- Có thể xem chi tiết hóa đơn để kiểm tra tiền phòng, điện, nước, dịch vụ, xe và nợ cũ.
- Có thể tạo hóa đơn, xem trước hóa đơn, cập nhật hoặc phát hành lại khi có sai lệch.
- Hóa đơn chỉ nên được tạo sau khi đã có hợp đồng và dữ liệu dịch vụ cần thiết.
- Hóa đơn điện nước cần có chỉ số kỳ hiện tại và kỳ trước để tính tiêu thụ.
- Không nên hủy hóa đơn đã được đối soát thanh toán nếu chưa kiểm tra kỹ nghiệp vụ.
- Hóa đơn đã kết chuyển nợ sang kỳ sau không hiển thị QR thanh toán trực tiếp để tránh thanh toán nhầm kỳ.

**Hình 4.22:** Màn hình quản lý hóa đơn *(chèn hình tại đây)*

### 4.7.2. Màn hình Chi tiết hóa đơn và VietQR

**Đường dẫn:** Mở từ màn hình `/admin/invoices`

**Mô tả:** Màn hình chi tiết hóa đơn hiển thị đầy đủ các khoản phí, lịch sử thanh toán và mã VietQR để thanh toán. Chức năng này giúp admin kiểm tra hóa đơn trước khi xác nhận hoặc xử lý thanh toán.

**Thao tác & Ràng buộc:**

- Người dùng xem các khoản phí chi tiết của hóa đơn.
- Có thể xem mã VietQR, nội dung chuyển khoản và số tiền cần thanh toán.
- Có thể sao chép mã hóa đơn hoặc thông tin chuyển khoản khi cần đối soát.
- Thanh toán qua VietQR cần đúng số tiền và đúng nội dung chuyển khoản để hệ thống SePay tự động đối soát.
- Nếu hóa đơn có thanh toán một phần, hệ thống hiển thị số tiền đã trả và số tiền còn lại.
- Không nên xác nhận thanh toán nếu thông tin giao dịch chưa khớp với hóa đơn.

**Hình 4.23:** Màn hình chi tiết hóa đơn và VietQR *(chèn hình tại đây)*

### 4.7.3. Màn hình Ghi nhận/Xác nhận thanh toán

**Đường dẫn:** Mở từ màn hình `/admin/invoices`

**Mô tả:** Màn hình thanh toán cho phép admin ghi nhận giao dịch thủ công hoặc xác nhận các giao dịch đang chờ kiểm tra. Chức năng này phục vụ trường hợp khách thuê chuyển khoản, nộp tiền mặt hoặc gửi minh chứng thanh toán.

**Thao tác & Ràng buộc:**

- Người dùng nhập số tiền thanh toán, phương thức thanh toán, ngày thanh toán và ghi chú nếu có.
- Có thể kiểm tra minh chứng thanh toán do khách thuê gửi lên.
- Chỉ xác nhận thanh toán khi số tiền, mã giao dịch, nội dung chuyển khoản hoặc chứng từ đã hợp lệ.
- Giao dịch đã xác nhận sẽ ảnh hưởng đến trạng thái hóa đơn, công nợ và báo cáo doanh thu.
- Không nên xác nhận giao dịch thiếu chứng cứ hoặc không khớp số tiền thực tế.

**Hình 4.24:** Màn hình ghi nhận và xác nhận thanh toán *(chèn hình tại đây)*

### 4.7.4. Màn hình Công nợ

**Đường dẫn:** `/admin/debts`

**Mô tả:** Màn hình công nợ giúp quản lý theo dõi các khoản hóa đơn chưa thanh toán, thanh toán thiếu hoặc quá hạn. Đây là màn hình hỗ trợ nhắc nợ và kiểm soát dòng tiền.

**Thao tác & Ràng buộc:**

- Người dùng lọc công nợ theo tòa nhà, phòng, khách thuê hoặc trạng thái.
- Xem số tiền còn phải thu, hạn thanh toán và trạng thái quá hạn.
- Mở hóa đơn liên quan để kiểm tra chi tiết khoản phí.
- Có thể gửi nhắc nợ cho hóa đơn chưa thanh toán đúng hạn.
- Công nợ được tính từ hóa đơn chưa thanh toán đủ.
- Số tiền còn nợ có thể bao gồm nợ cũ kết chuyển từ kỳ trước.
- Chỉ nên gửi nhắc nợ sau khi đã kiểm tra trạng thái thanh toán thực tế.

**Hình 4.25:** Màn hình quản lý công nợ *(chèn hình tại đây)*

### 4.7.5. Màn hình Lịch sử thanh toán

**Đường dẫn:** `/admin/payment-history`

**Mô tả:** Màn hình lịch sử thanh toán lưu lại các giao dịch thanh toán theo hóa đơn. Chức năng này phục vụ đối soát, kiểm tra chứng từ và theo dõi trạng thái thanh toán.

**Thao tác & Ràng buộc:**

- Người dùng lọc giao dịch theo tòa nhà, phòng, hóa đơn, phương thức thanh toán hoặc khoảng thời gian.
- Xem mã giao dịch, ngày thanh toán, số tiền, phương thức và trạng thái.
- Kiểm tra các khoản thanh toán chờ xác nhận hoặc đã xác nhận.
- Đối chiếu với hóa đơn liên quan khi có khiếu nại hoặc sai lệch.
- Giao dịch tự động từ SePay phụ thuộc vào nội dung chuyển khoản và mã hóa đơn.
- Không nên xác nhận giao dịch nếu số tiền hoặc chứng từ chưa khớp với hóa đơn.

**Hình 4.26:** Màn hình lịch sử thanh toán *(chèn hình tại đây)*

### 4.7.6. Màn hình Phiếu chi

**Đường dẫn:** `/admin/expenses`

**Mô tả:** Màn hình phiếu chi dùng để ghi nhận các khoản chi phí vận hành như sửa chữa, mua sắm, hoàn cọc hoặc chi phí quản lý khác. Dữ liệu này được dùng để tính báo cáo lợi nhuận.

**Thao tác & Ràng buộc:**

- Người dùng tạo phiếu chi với tòa nhà, danh mục, số tiền, ngày chi và nội dung chi.
- Có thể lọc phiếu chi theo tòa nhà, danh mục, trạng thái hoặc khoảng thời gian.
- Có thể cập nhật hoặc hủy phiếu chi khi phát hiện sai sót.
- Phiếu chi cần có tòa nhà, danh mục, số tiền và ngày chi hợp lệ.
- Số tiền chi phải là số dương.
- Phiếu chi đã hủy không được tính như khoản chi hợp lệ trong báo cáo lợi nhuận.

**Hình 4.27:** Màn hình quản lý phiếu chi *(chèn hình tại đây)*

### 4.7.7. Màn hình Danh mục phiếu chi

**Đường dẫn:** `/admin/expense-categories`

**Mô tả:** Màn hình danh mục phiếu chi dùng để chuẩn hóa các loại chi phí, giúp quá trình ghi nhận phiếu chi và tổng hợp báo cáo được nhất quán.

**Thao tác & Ràng buộc:**

- Người dùng xem danh sách, thêm mới, cập nhật và đổi trạng thái danh mục phiếu chi.
- Danh mục cần có tên rõ ràng để người dùng chọn đúng khi lập phiếu chi.
- Danh mục ngừng hoạt động không nên được dùng cho phiếu chi mới.
- Với admin thường, danh mục phiếu chi có thể chỉ được xem hoặc sử dụng theo quyền được cấp.
- Dữ liệu danh mục ảnh hưởng đến việc phân nhóm chi phí trên báo cáo lợi nhuận.

**Hình 4.28:** Màn hình danh mục phiếu chi *(chèn hình tại đây)*

### 4.7.8. Màn hình Báo cáo lợi nhuận

**Đường dẫn:** `/admin/financials`

**Mô tả:** Màn hình báo cáo lợi nhuận tổng hợp doanh thu, số tiền đã thu, công nợ, chi phí và lợi nhuận theo tòa nhà hoặc khoảng thời gian.

**Thao tác & Ràng buộc:**

- Người dùng chọn tòa nhà, tháng, năm hoặc khoảng thời gian cần thống kê.
- Xem tổng doanh thu, số đã thu, công nợ, chi phí và lợi nhuận.
- Quan sát biểu đồ hoặc bảng dữ liệu để so sánh biến động theo kỳ.
- Doanh thu phụ thuộc vào dữ liệu hóa đơn đã phát sinh.
- Số đã thu phụ thuộc vào thanh toán đã ghi nhận hoặc xác nhận.
- Chi phí phụ thuộc vào phiếu chi hợp lệ trong kỳ báo cáo.
- Lợi nhuận có thể âm nếu chi phí lớn hơn doanh thu hoặc số tiền thu trong kỳ.

**Hình 4.29:** Màn hình báo cáo lợi nhuận *(chèn hình tại đây)*

---

## 4.8. Phân hệ Vận hành

### 4.8.1. Màn hình Bảo trì

**Đường dẫn:** `/admin/maintenance`

**Mô tả:** Màn hình bảo trì giúp admin tiếp nhận và xử lý các yêu cầu sửa chữa từ khách thuê. Mỗi yêu cầu gồm tiêu đề, mô tả, phòng liên quan, hình ảnh, trạng thái xử lý và phản hồi.

**Thao tác & Ràng buộc:**

- Người dùng lọc yêu cầu bảo trì theo tòa nhà, phòng, trạng thái hoặc thời gian.
- Xem chi tiết nội dung yêu cầu và hình ảnh minh chứng.
- Cập nhật trạng thái xử lý như tiếp nhận, đang xử lý, hoàn tất hoặc hủy.
- Không nên chuyển sang trạng thái hoàn tất nếu yêu cầu chưa được xử lý thực tế.
- Trạng thái bảo trì cần được cập nhật đúng tiến độ để khách thuê theo dõi.
- Ảnh minh chứng giúp quản lý xác định mức độ sự cố và lưu lịch sử xử lý.

**Hình 4.30:** Màn hình quản lý bảo trì *(chèn hình tại đây)*

### 4.8.2. Màn hình Thông báo

**Đường dẫn:** `/admin/notifications`

**Mô tả:** Màn hình thông báo cho phép admin tạo và gửi thông báo đến khách thuê hoặc nhóm người dùng liên quan. Hệ thống ghi nhận trạng thái đã đọc để theo dõi hiệu quả truyền thông.

**Thao tác & Ràng buộc:**

- Người dùng tạo thông báo với tiêu đề, nội dung, loại thông báo và đối tượng nhận.
- Có thể lọc danh sách thông báo theo trạng thái hoặc thời gian.
- Xem chi tiết thông báo và đánh dấu đã đọc.
- Nên chọn đúng đối tượng nhận để tránh gửi nhầm thông tin giữa các tòa nhà hoặc phòng.
- Tiêu đề và nội dung thông báo cần rõ ràng, tránh gây hiểu nhầm.
- Thông báo realtime phụ thuộc vào kết nối websocket của người dùng.

**Hình 4.31:** Màn hình quản lý thông báo *(chèn hình tại đây)*

### 4.8.3. Màn hình Chat

**Đường dẫn:** `/admin/chat`

**Mô tả:** Màn hình chat hỗ trợ admin trao đổi với khách thuê và chat nội bộ giữa các tài khoản admin. Tin nhắn được cập nhật realtime và có thể đính kèm hình ảnh.

**Thao tác & Ràng buộc:**

- Người dùng chọn tab chat với khách thuê hoặc chat nội bộ admin.
- Có thể tìm kiếm đoạn chat theo tên, phòng hoặc tòa nhà.
- Chọn một đoạn chat để xem lịch sử tin nhắn.
- Nhập nội dung, đính kèm ảnh và gửi tin nhắn.
- Không thể gửi tin nhắn trống nếu không có nội dung hoặc ảnh đính kèm.
- Chat realtime cần kết nối websocket hoạt động.
- Admin chỉ nên trao đổi trong phạm vi dữ liệu và khách thuê thuộc quyền quản lý.

**Hình 4.32:** Màn hình chat Web Admin *(chèn hình tại đây)*

---

## 4.9. Phân hệ Hệ thống

### 4.9.1. Màn hình Quản lý tài khoản admin

**Đường dẫn:** `/admin/system-users`

**Mô tả:** Màn hình tài khoản admin cho phép Super Admin tạo, cập nhật và khóa/mở khóa tài khoản quản trị. Chức năng này giúp phân quyền giữa quản trị hệ thống và quản lý tòa nhà.

**Thao tác & Ràng buộc:**

- Super Admin xem danh sách tài khoản admin và lọc theo trạng thái hoặc vai trò.
- Có thể tạo tài khoản admin mới với tên đăng nhập, họ tên, email, số điện thoại và vai trò.
- Có thể gán tòa nhà phụ trách cho tài khoản quản lý tòa nhà.
- Tên đăng nhập và email cần duy nhất trong hệ thống.
- Vai trò tài khoản quyết định menu, quyền thao tác và phạm vi dữ liệu.
- Khi khóa tài khoản, người dùng đó không nên tiếp tục được phép thao tác nghiệp vụ.
- Chức năng này chỉ dành cho **Super Admin**.

**Hình 4.33:** Màn hình quản lý tài khoản admin *(chèn hình tại đây)*

### 4.9.2. Màn hình Tạo/Cập nhật tài khoản admin

**Đường dẫn:** `/admin/system-users/create`, `/admin/system-users/update/:id`

**Mô tả:** Màn hình tạo/cập nhật tài khoản admin cho phép nhập thông tin tài khoản, phân vai trò và gán phạm vi quản lý cho người dùng nội bộ.

**Thao tác & Ràng buộc:**

- Người dùng nhập tên đăng nhập, họ tên, email, số điện thoại, mật khẩu và vai trò.
- Khi tạo tài khoản quản lý tòa nhà, cần gán đúng tòa nhà phụ trách.
- Email phải đúng định dạng và không trùng.
- Tên đăng nhập không được trùng với tài khoản khác.
- Mật khẩu cần đáp ứng quy định bảo mật của hệ thống.
- Phân quyền sai có thể khiến người dùng nhìn thấy dữ liệu ngoài phạm vi quản lý.

**Hình 4.34:** Màn hình tạo/cập nhật tài khoản admin *(chèn hình tại đây)*

### 4.9.3. Màn hình Nhật ký admin

**Đường dẫn:** `/admin/activity-logs`

**Mô tả:** Màn hình nhật ký admin ghi lại các thao tác quan trọng như đăng nhập FaceID, tạo tòa nhà, cập nhật phòng, tạo hóa đơn, xác nhận thanh toán, hủy hóa đơn hoặc thay đổi trạng thái dữ liệu.

**Thao tác & Ràng buộc:**

- Người dùng tìm kiếm nhật ký theo admin, đối tượng, hành động hoặc địa chỉ IP.
- Có thể lọc nhật ký theo loại hành động, loại đối tượng và khoảng ngày.
- Xem chi tiết một bản ghi nhật ký để biết nội dung thao tác.
- Nhật ký là dữ liệu truy vết, không nên chỉnh sửa thủ công.
- Bộ lọc ngày cần chọn đúng khoảng thời gian để tránh bỏ sót thao tác cần kiểm tra.
- Chức năng này chỉ dành cho **Super Admin**.

**Hình 4.35:** Màn hình nhật ký thao tác admin *(chèn hình tại đây)*

### 4.9.4. Màn hình Cài đặt tòa nhà

**Đường dẫn:** `/admin/settings`

**Mô tả:** Màn hình cài đặt tòa nhà dùng để quản lý các thông tin cấu hình hiển thị hoặc phục vụ vận hành, ví dụ nội quy, hướng dẫn thanh toán, thông tin liên hệ hoặc các ghi chú quản lý khác.

**Thao tác & Ràng buộc:**

- Người dùng lọc cài đặt theo tòa nhà hoặc trạng thái công khai.
- Có thể thêm mới hoặc cập nhật nội dung cài đặt.
- Có thể bật/tắt trạng thái công khai để quyết định thông tin nào được hiển thị cho người dùng liên quan.
- Cài đặt cần có tên hiển thị rõ ràng.
- Chỉ nên công khai các thông tin phù hợp, tránh hiển thị dữ liệu nội bộ.
- Admin chỉ nên chỉnh cài đặt thuộc tòa nhà mình phụ trách.

**Hình 4.36:** Màn hình cài đặt tòa nhà *(chèn hình tại đây)*

---

## 4.10. Đánh giá kết quả đạt được

Thông qua các giao diện Web Admin đã xây dựng, hệ thống **StayHub** đáp ứng được các nhóm nghiệp vụ trọng tâm trong quản lý căn hộ, phòng trọ và ký túc xá:

- Hoàn thiện phân quyền giữa **Super Admin** và **Admin quản lý tòa nhà**.
- Quản lý được dữ liệu nền gồm khu vực, tòa nhà, loại phòng, phòng, mẫu tài sản và dịch vụ.
- Hỗ trợ quản lý khách thuê, phương tiện, hợp đồng, tiền cọc, chuyển phòng và lịch sử cư trú.
- Hỗ trợ quản lý đồng hồ, chốt điện nước, phân tích ảnh công tơ bằng AI và sinh hóa đơn.
- Hỗ trợ quản lý hóa đơn, công nợ, thanh toán, VietQR, SePay, phiếu chi và báo cáo lợi nhuận.
- Hỗ trợ xử lý bảo trì, gửi thông báo và trò chuyện realtime giữa admin với khách thuê/admin khác.
- Hỗ trợ kiểm soát hệ thống bằng tài khoản admin, phân quyền, nhật ký thao tác và cài đặt tòa nhà.

Nhìn chung, giao diện Web Admin đã thể hiện được quy trình vận hành khép kín từ cấu hình cơ sở lưu trú, quản lý khách thuê, lập hợp đồng, ghi nhận dịch vụ, phát hành hóa đơn, xử lý thanh toán, bảo trì đến báo cáo. Các màn hình được tổ chức theo từng phân hệ rõ ràng và có ràng buộc nhập liệu nhằm hạn chế sai sót trong quá trình vận hành thực tế.

---

## 4.11. Danh sách hình cần chụp

| STT | Tên hình | Màn hình cần chụp |
|---:|---|---|
| 1 | Hình 4.1 | Đăng nhập Web Admin |
| 2 | Hình 4.2 | Cài đặt tài khoản admin |
| 3 | Hình 4.3 | Dashboard tổng quan |
| 4 | Hình 4.4 | Quản lý khu vực và tòa nhà |
| 5 | Hình 4.5 | Tạo/cập nhật tòa nhà |
| 6 | Hình 4.6 | Quản lý loại phòng |
| 7 | Hình 4.7 | Quản lý mẫu tài sản |
| 8 | Hình 4.8 | Quản lý phòng |
| 9 | Hình 4.9 | Tạo/cập nhật phòng |
| 10 | Hình 4.10 | Quản lý khách thuê |
| 11 | Hình 4.11 | Tạo/cập nhật khách thuê |
| 12 | Hình 4.12 | Quản lý phương tiện |
| 13 | Hình 4.13 | Quản lý hợp đồng |
| 14 | Hình 4.14 | Tạo/cập nhật hợp đồng |
| 15 | Hình 4.15 | Chi tiết hợp đồng |
| 16 | Hình 4.16 | Chuyển phòng khách thuê |
| 17 | Hình 4.17 | Lịch sử phòng và tiền cọc |
| 18 | Hình 4.18 | Quản lý dịch vụ |
| 19 | Hình 4.19 | Quản lý đồng hồ điện nước |
| 20 | Hình 4.20 | Chốt chỉ số điện nước |
| 21 | Hình 4.21 | Sinh hóa đơn sau chốt điện nước |
| 22 | Hình 4.22 | Quản lý hóa đơn |
| 23 | Hình 4.23 | Chi tiết hóa đơn và VietQR |
| 24 | Hình 4.24 | Ghi nhận/xác nhận thanh toán |
| 25 | Hình 4.25 | Quản lý công nợ |
| 26 | Hình 4.26 | Lịch sử thanh toán |
| 27 | Hình 4.27 | Quản lý phiếu chi |
| 28 | Hình 4.28 | Danh mục phiếu chi |
| 29 | Hình 4.29 | Báo cáo lợi nhuận |
| 30 | Hình 4.30 | Quản lý bảo trì |
| 31 | Hình 4.31 | Quản lý thông báo |
| 32 | Hình 4.32 | Chat Web Admin |
| 33 | Hình 4.33 | Quản lý tài khoản admin |
| 34 | Hình 4.34 | Tạo/cập nhật tài khoản admin |
| 35 | Hình 4.35 | Nhật ký admin |
| 36 | Hình 4.36 | Cài đặt tòa nhà |
