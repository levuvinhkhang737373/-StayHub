# Thiết kế Cơ sở dữ liệu - StayHub (Đầy đủ 38 Bảng)

Tài liệu này đặc tả chi tiết toàn bộ 38 bảng trong cơ sở dữ liệu của dự án StayHub (gồm Tên trường, Kiểu dữ liệu, Mô tả, Ràng buộc) dùng cho Đồ án Tốt nghiệp.

---

### **2.1.3.1. Bảng admins**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | username | VARCHAR(255) | Tên đăng nhập | UNIQUE, NOT NULL | 
 | full_name | VARCHAR(150) | Họ và tên | NOT NULL | 
 | email | VARCHAR(150) | Địa chỉ email | UNIQUE, NOT NULL | 
 | phone | VARCHAR(10) | Số điện thoại | UNIQUE, NULLABLE | 
 | password | VARCHAR(255) | Mật khẩu | NOT NULL | 
 | role | TINYINT UNSIGNED | Vai trò của Admin / (1: Quản lý toà nhà) / (2: Quản trị tổng) / Mặc định : 1 | DEFAULT: 1, NOT NULL | 
 | avatar_url | VARCHAR(255) | Đường dẫn ảnh đại diện | NULLABLE | 
 | status | TINYINT UNSIGNED | Trạng thái / 1: Hoạt động / 2: Ngừng hoạt động | DEFAULT: 1, NOT NULL | 
 | gender | TINYINT UNSIGNED | Giới tính / 1: Nam / 2: Nữ | DEFAULT: 1, NOT NULL | 
 | date_of_birth | DATE | Ngày tháng năm sinh | NULLABLE | 
 | address | VARCHAR(500) | Địa chỉ thường trú | NULLABLE | 
 | image_path_faceid | VARCHAR(500) | Đường dẫn file ảnh FaceID | NULLABLE | 
 | created_faceid_at | TIMESTAMP | Thời gian đăng ký FaceID | NULLABLE | 
 | updated_faceid_at | TIMESTAMP | Thời gian cập nhật FaceID | NULLABLE | 
 | created_at | TIMESTAMP | Thời điểm tạo bản ghi | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật bản ghi | NULLABLE | 

---

### **2.1.3.2. Bảng tenants**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | full_name | VARCHAR(150) | Họ và tên khách thuê | NOT NULL | 
 | gender | TINYINT UNSIGNED | Giới tính / 1: Nam / 2: Nữ | DEFAULT: 1, NOT NULL | 
 | date_of_birth | DATE | Ngày sinh | NOT NULL | 
 | phone | VARCHAR(10) | Số điện thoại | UNIQUE, NOT NULL | 
 | email | VARCHAR(150) | Địa chỉ email | UNIQUE, NULLABLE | 
 | username | VARCHAR(255) | Tên đăng nhập | UNIQUE, NOT NULL | 
 | password | VARCHAR(255) | Mật khẩu | NOT NULL | 
 | permanent_address | VARCHAR(500) | Địa chỉ thường trú | NULLABLE | 
 | current_address | VARCHAR(500) | Địa chỉ tạm trú hiện tại | NULLABLE | 
 | avatar_url | VARCHAR(500) | Đường dẫn ảnh đại diện | NULLABLE | 
 | status | TINYINT UNSIGNED | Trạng thái / 1: Đang ở / 2: Đã chuyển đi | DEFAULT: 1, NOT NULL | 
 | identity_type | TINYINT UNSIGNED | Loại giấy tờ định danh (1 = CCCD, 2 = CMND, 3 = Hộ chiếu) | DEFAULT: 1, NOT NULL | 
 | identity_number | VARCHAR(30) | Số định danh | UNIQUE, NULLABLE | 
 | front_image_url | VARCHAR(500) | Đường dẫn ảnh mặt trước CCCD | NULLABLE | 
 | back_image_url | VARCHAR(500) | Đường dẫn ảnh mặt sau CCCD | NULLABLE | 
 | created_by | BIGINT UNSIGNED | ID người tạo tài khoản khách | FOREIGN KEY -> admins(id), RESTRICT | 
 | building_id | BIGINT UNSIGNED | ID tòa nhà đang ở | FOREIGN KEY -> buildings(id), CASCADE | 
 | created_at | TIMESTAMP | Thời điểm tạo bản ghi | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật bản ghi | NULLABLE | 

---

### **2.1.3.3. Bảng regions**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | parent_id | BIGINT UNSIGNED | ID khu vực chi tiết cha | FOREIGN KEY -> regions(id), CASCADE | 
 | code | VARCHAR(50) | Mã định danh khu vực | UNIQUE, NOT NULL | 
 | name | VARCHAR(150) | Tên khu vực | NOT NULL | 
 | path | VARCHAR(255) | Đường dẫn phân cấp khu vực | NULLABLE | 
 | slug | VARCHAR(255) | Chuỗi định danh URL | UNIQUE, NOT NULL | 
 | description | TEXT | Mô tả chi tiết khu vực | NULLABLE | 
 | is_active | BOOLEAN | Trạng thái hoạt động | DEFAULT: TRUE, NOT NULL | 
 | created_by | BIGINT UNSIGNED | ID Admin tạo khu vực | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.4. Bảng buildings**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | region_id | BIGINT UNSIGNED | ID khu vực của tòa nhà | FOREIGN KEY -> regions(id), CASCADE | 
 | manager_admin_id | BIGINT UNSIGNED | ID Admin quản lý tòa nhà | FOREIGN KEY -> admins(id), RESTRICT | 
 | name | VARCHAR(150) | Tên tòa nhà | NOT NULL | 
 | slug | VARCHAR(255) | Chuỗi định danh URL | UNIQUE, NOT NULL | 
 | address | VARCHAR(500) | Địa chỉ chi tiết | NOT NULL | 
 | total_floors | INT | Tổng số tầng | DEFAULT: 1, NOT NULL | 
 | gender_policy | TINYINT UNSIGNED | Quy định giới tính (1 = Chung, 2 = Nam, 3 = Nữ) | DEFAULT: 1, NOT NULL | 
 | description | TEXT | Mô tả tổng quan | NULLABLE | 
 | status | TINYINT UNSIGNED | Trạng thái tòa nhà | DEFAULT: 1, NOT NULL | 
 | created_by | BIGINT UNSIGNED | ID Admin tạo tòa nhà | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.5. Bảng building_images**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | building_id | BIGINT UNSIGNED | ID tòa nhà liên kết | FOREIGN KEY -> buildings(id), CASCADE | 
 | image_path | VARCHAR(500) | Đường dẫn file ảnh | NOT NULL | 
 | is_primary | BOOLEAN | Là ảnh chính | DEFAULT: FALSE, NOT NULL | 
 | sort_order | INT | Thứ tự hiển thị | DEFAULT: 0, NOT NULL | 
 | status | TINYINT UNSIGNED | Trạng thái hiển thị | DEFAULT: 1, NOT NULL | 
 | uploaded_by | BIGINT UNSIGNED | ID Admin tải ảnh lên | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.6. Bảng room_types**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | name | VARCHAR(150) | Tên loại phòng | UNIQUE, NOT NULL | 
 | slug | VARCHAR(255) | Chuỗi định danh URL | UNIQUE, NOT NULL | 
 | description | TEXT | Mô tả đặc điểm loại phòng | NULLABLE | 
 | status | TINYINT UNSIGNED | Trạng thái hoạt động | DEFAULT: 1, NOT NULL | 
 | created_by | BIGINT UNSIGNED | ID Admin tạo loại phòng | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.7. Bảng rooms**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | building_id | BIGINT UNSIGNED | ID tòa nhà chứa phòng | FOREIGN KEY -> buildings(id), CASCADE | 
 | room_type_id | BIGINT UNSIGNED | ID loại phòng | FOREIGN KEY -> room_types(id), CASCADE | 
 | room_number | VARCHAR(50) | Số phòng / Tên phòng | NOT NULL | 
 | slug | VARCHAR(255) | Chuỗi định danh URL phòng | UNIQUE, NOT NULL | 
 | floor | INT | Phòng thuộc tầng mấy | DEFAULT: 1, NOT NULL | 
 | area_m2 | DECIMAL(8,2) | Diện tích sử dụng (m2) | DEFAULT: 0.00, NOT NULL | 
 | base_price | DECIMAL(15,2) | Đơn giá thuê hàng tháng | DEFAULT: 0.00, NOT NULL | 
 | max_occupants | INT | Số người ở tối đa | DEFAULT: 1, NOT NULL | 
 | current_occupants | INT | Số người đang ở thực tế | DEFAULT: 0, NOT NULL | 
 | status | TINYINT UNSIGNED | Trạng thái phòng (1 = Trống, 2 = Đã thuê) | DEFAULT: 1, NOT NULL | 
 | description | TEXT | Ghi chú thêm | NULLABLE | 
 | created_by | BIGINT UNSIGNED | ID Admin tạo phòng | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.8. Bảng room_images**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | room_id | BIGINT UNSIGNED | ID phòng liên kết | FOREIGN KEY -> rooms(id), CASCADE | 
 | image_path | VARCHAR(500) | Đường dẫn file ảnh | NOT NULL | 
 | is_primary | BOOLEAN | Là ảnh chính | DEFAULT: FALSE, NOT NULL | 
 | sort_order | INT | Thứ tự hiển thị | DEFAULT: 0, NOT NULL | 
 | status | TINYINT UNSIGNED | Trạng thái hiển thị | DEFAULT: 1, NOT NULL | 
 | uploaded_by | BIGINT UNSIGNED | ID Admin tải ảnh | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.9. Bảng asset_templates**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | building_id | BIGINT UNSIGNED | ID tòa nhà áp dụng | FOREIGN KEY -> buildings(id), CASCADE | 
 | name | VARCHAR(150) | Tên tài sản mẫu | NOT NULL | 
 | slug | VARCHAR(255) | Chuỗi định danh URL | UNIQUE, NOT NULL | 
 | default_unit_name | TINYINT UNSIGNED | Đơn vị mặc định (1 = Cái, 2 = Bộ...) | DEFAULT: 1, NOT NULL | 
 | description | TEXT | Mô tả | NULLABLE | 
 | status | TINYINT UNSIGNED | Trạng thái hoạt động | DEFAULT: 1, NOT NULL | 
 | created_by | BIGINT UNSIGNED | ID Admin tạo mẫu | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.10. Bảng room_assets**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | room_id | BIGINT UNSIGNED | ID phòng liên kết | FOREIGN KEY -> rooms(id), CASCADE | 
 | asset_template_id | BIGINT UNSIGNED | ID mẫu tài sản | FOREIGN KEY -> asset_templates(id), CASCADE | 
 | quantity | INT | Số lượng trong phòng | DEFAULT: 1, NOT NULL | 
 | price | DECIMAL(15,2) | Đơn giá thiết bị | DEFAULT: 0.00, NOT NULL | 
 | note | VARCHAR(500) | Hiện trạng thiết bị | NULLABLE | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.11. Bảng services**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | name | VARCHAR(255) | Tên dịch vụ | UNIQUE, NOT NULL | 
 | slug | VARCHAR(255) | Chuỗi định danh URL | UNIQUE, NOT NULL | 
 | charge_method | TINYINT UNSIGNED | Cách tính (1 = Chỉ số, 2 = Cố định/phòng, 3 = Cố định/người) | DEFAULT: 1, NOT NULL | 
 | unit_name | VARCHAR(50) | Đơn vị đo lường (kWh, m3, tháng...) | NOT NULL | 
 | is_required | BOOLEAN | Có bắt buộc không | DEFAULT: FALSE, NOT NULL | 
 | is_active | BOOLEAN | Trạng thái hoạt động | DEFAULT: TRUE, NOT NULL | 
 | created_by | BIGINT UNSIGNED | ID Admin tạo | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.12. Bảng service_prices**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | service_id | BIGINT UNSIGNED | ID dịch vụ liên kết | FOREIGN KEY -> services(id), CASCADE | 
 | building_id | BIGINT UNSIGNED | ID tòa nhà áp dụng | FOREIGN KEY -> buildings(id), CASCADE | 
 | price | DECIMAL(15,2) | Đơn giá | DEFAULT: 0.00, NOT NULL | 
 | effective_from | DATE | Ngày bắt đầu áp dụng | NOT NULL | 
 | effective_to | DATE | Ngày kết thúc áp dụng | NULLABLE | 
 | status | TINYINT UNSIGNED | Trạng thái | DEFAULT: 1, NOT NULL | 
 | created_by | BIGINT UNSIGNED | ID Admin tạo | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.13. Bảng room_services**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | room_id | BIGINT UNSIGNED | ID phòng liên kết | FOREIGN KEY -> rooms(id), CASCADE | 
 | service_id | BIGINT UNSIGNED | ID dịch vụ liên kết | FOREIGN KEY -> services(id), CASCADE | 
 | price | DECIMAL(15,2) | Đơn giá dịch vụ của phòng | DEFAULT: 0.00, NOT NULL | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.14. Bảng meter_devices**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | room_id | BIGINT UNSIGNED | ID phòng lắp đặt | FOREIGN KEY -> rooms(id), CASCADE | 
 | service_id | BIGINT UNSIGNED | ID dịch vụ | FOREIGN KEY -> services(id), CASCADE | 
 | meter_code | VARCHAR(100) | Mã định danh thiết bị | UNIQUE, NOT NULL | 
 | meter_type | TINYINT UNSIGNED | Loại thiết bị (1 = Điện, 2 = Nước) | DEFAULT: 1, NOT NULL | 
 | initial_reading | DECIMAL(12,2) | Số đo ban đầu lúc lắp | DEFAULT: 0.00, NOT NULL | 
 | installed_at | DATE | Ngày lắp đặt | NOT NULL | 
 | replaced_by_meter_id | BIGINT UNSIGNED | ID thiết bị thay thế | FOREIGN KEY -> meter_devices(id), SET NULL | 
 | status | TINYINT UNSIGNED | Trạng thái thiết bị | DEFAULT: 1, NOT NULL | 
 | image_path | VARCHAR(500) | Ảnh chụp khi lắp đặt | NULLABLE | 
 | note | VARCHAR(500) | Ghi chú | NULLABLE | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.15. Bảng meter_readings**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | meter_device_id | BIGINT UNSIGNED | ID thiết bị công tơ liên kết | FOREIGN KEY -> meter_devices(id), CASCADE | 
 | billing_month | TINYINT UNSIGNED | Tháng chốt số | NOT NULL | 
 | billing_year | SMALLINT UNSIGNED | Năm chốt số | NOT NULL | 
 | previous_reading | DECIMAL(12,2) | Chỉ số cũ | DEFAULT: 0.00, NOT NULL | 
 | current_reading | DECIMAL(12,2) | Chỉ số mới | DEFAULT: 0.00, NOT NULL | 
 | consumption | DECIMAL(12,2) | Lượng tiêu thụ (= Chỉ số mới - Chỉ số cũ) | DEFAULT: 0.00, NOT NULL | 
 | reading_date | DATE | Ngày ghi chỉ số | NOT NULL | 
 | status | TINYINT UNSIGNED | Trạng thái: 1 = Chờ chốt, 2 = Đã tạo hóa đơn | DEFAULT: 1, NOT NULL | 
 | image_path | VARCHAR(500) | Ảnh chụp thực tế làm minh chứng | NULLABLE | 
 | note | VARCHAR(500) | Ghi chú | NULLABLE | 
 | created_by | BIGINT UNSIGNED | ID Admin ghi nhận | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.16. Bảng contracts**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | contract_code | VARCHAR(50) | Mã hợp đồng | UNIQUE, NOT NULL | 
 | room_id | BIGINT UNSIGNED | ID căn phòng thuê | FOREIGN KEY -> rooms(id), CASCADE | 
 | representative_tenant_id | BIGINT UNSIGNED | ID khách đại diện ký hợp đồng | FOREIGN KEY -> tenants(id), CASCADE | 
 | start_date | DATE | Ngày hợp đồng có hiệu lực | NOT NULL | 
 | end_date | DATE | Ngày kết thúc dự kiến | NOT NULL | 
 | actual_end_date | DATE | Ngày thực tế thanh lý | NULLABLE | 
 | billing_cycle_day | TINYINT UNSIGNED | Ngày chốt hóa đơn hàng tháng | DEFAULT: 5, NOT NULL | 
 | room_price | DECIMAL(15,2) | Giá thuê phòng theo thỏa thuận | DEFAULT: 0.00, NOT NULL | 
 | deposit_amount | DECIMAL(15,2) | Số tiền đã đặt cọc | DEFAULT: 0.00, NOT NULL | 
 | status | TINYINT UNSIGNED | Trạng thái (1 = Nháp, 2 = Hiệu lực, 3 = Quá hạn, 4 = Đã thanh lý) | DEFAULT: 1, NOT NULL | 
 | contract_files | JSON | Danh sách tệp đính kèm (ảnh chụp, PDF...) | NULLABLE | 
 | note | TEXT | Điều khoản phụ | NULLABLE | 
 | created_by | BIGINT UNSIGNED | ID Admin tạo hợp đồng | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.17. Bảng contract_tenants**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | contract_id | BIGINT UNSIGNED | ID hợp đồng liên kết | FOREIGN KEY -> contracts(id), CASCADE | 
 | tenant_id | BIGINT UNSIGNED | ID khách thuê liên kết | FOREIGN KEY -> tenants(id), CASCADE | 
 | join_date | DATE | Ngày vào ở | NOT NULL | 
 | leave_date | DATE | Ngày rời đi | NULLABLE | 
 | billing_start_date | DATE | Ngày bắt đầu tính phí | NULLABLE | 
 | billing_end_date | DATE | Ngày kết thúc tính phí | NULLABLE | 
 | is_representative | BOOLEAN | Là người đại diện phòng | DEFAULT: FALSE, NOT NULL | 
 | is_staying | BOOLEAN | Hiện còn đang ở phòng không | DEFAULT: TRUE, NOT NULL | 
 | created_by | BIGINT UNSIGNED | ID Admin lưu thông tin | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.18. Bảng room_movements**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | tenant_id | BIGINT UNSIGNED | ID khách chuyển phòng | FOREIGN KEY -> tenants(id), CASCADE | 
 | contract_id | BIGINT UNSIGNED | ID hợp đồng liên quan | FOREIGN KEY -> contracts(id), CASCADE | 
 | from_room_id | BIGINT UNSIGNED | ID phòng cũ | FOREIGN KEY -> rooms(id), CASCADE | 
 | to_room_id | BIGINT UNSIGNED | ID phòng mới | FOREIGN KEY -> rooms(id), CASCADE | 
 | movement_type | TINYINT UNSIGNED | Loại chuyển (1 = Chuyển phòng, 2 = Trả phòng) | DEFAULT: 1, NOT NULL | 
 | movement_date | DATETIME | Ngày thực tế chuyển phòng | NOT NULL | 
 | old_room_final_amount | DECIMAL(15,2) | Tiền chốt phòng cũ | DEFAULT: 0.00, NOT NULL | 
 | transfer_fee | DECIMAL(15,2) | Phí chuyển phòng | DEFAULT: 0.00, NOT NULL | 
 | deposit_transfer_amount | DECIMAL(15,2) | Tiền cọc chuyển từ phòng cũ sang phòng mới | DEFAULT: 0.00, NOT NULL | 
 | deposit_refund_amount | DECIMAL(15,2) | Tiền cọc hoàn trả khách | DEFAULT: 0.00, NOT NULL | 
 | deduction_amount | DECIMAL(15,2) | Tiền phạt bị khấu trừ | DEFAULT: 0.00, NOT NULL | 
 | final_electric_reading | DECIMAL(12,2) | Số điện chốt phòng cũ | NULLABLE | 
 | final_water_reading | DECIMAL(12,2) | Số nước chốt phòng cũ | NULLABLE | 
 | note | TEXT | Lý do chuyển phòng | NULLABLE | 
 | created_by | BIGINT UNSIGNED | ID Admin thực hiện chuyển | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm lưu lịch sử | NULLABLE | 

---

### **2.1.3.19. Bảng vehicles**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | tenant_id | BIGINT UNSIGNED | ID của khách thuê sở hữu xe | FOREIGN KEY -> tenants(id), CASCADE | 
 | vehicle_type | TINYINT UNSIGNED | Loại phương tiện (1: Xe máy, 2: Xe đạp, 3: Ô tô) | DEFAULT: 1, NOT NULL | 
 | license_plate | VARCHAR(30) | Biển số xe | UNIQUE, NULLABLE | 
 | brand | VARCHAR(100) | Hãng xe / Thương hiệu xe | NULLABLE | 
 | color | VARCHAR(50) | Màu sắc xe | NULLABLE | 
 | is_active | BOOLEAN | Trạng thái xe còn gửi ở bãi hay không | DEFAULT: TRUE, NOT NULL | 
 | created_at | TIMESTAMP | Thời điểm tạo bản ghi | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật bản ghi | NULLABLE | 

---

### **2.1.3.20. Bảng contract_vehicles**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | contract_id | BIGINT UNSIGNED | ID của hợp đồng thuê phòng | FOREIGN KEY -> contracts(id), CASCADE | 
 | vehicle_id | BIGINT UNSIGNED | ID của xe thuộc khách thuê | FOREIGN KEY -> vehicles(id), CASCADE | 
 | started_at | DATE | Ngày bắt đầu gửi xe và tính phí | NOT NULL | 
 | ended_at | DATE | Ngày kết thúc gửi xe | NULLABLE | 
 | billing_start_date | DATE | Ngày bắt đầu chu kỳ tính phí gửi xe | NULLABLE | 
 | billing_end_date | DATE | Ngày dừng chu kỳ tính phí gửi xe | NULLABLE | 
 | monthly_fee | DECIMAL(15,2) | Đơn giá gửi xe hàng tháng | DEFAULT: 0.00, NOT NULL | 
 | charge_policy | TINYINT UNSIGNED | Chính sách thu phí xe | DEFAULT: 1, NOT NULL | 
 | is_active | BOOLEAN | Trạng thái xe còn gửi hay không | DEFAULT: TRUE, NOT NULL | 
 | created_at | TIMESTAMP | Thời điểm tạo bản ghi | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật bản ghi | NULLABLE | 

---

### **2.1.3.21. Bảng contract_deposit_transactions**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | contract_id | BIGINT UNSIGNED | ID hợp đồng liên kết | FOREIGN KEY -> contracts(id), CASCADE | 
 | transaction_type | TINYINT UNSIGNED | Loại (1 = Thu cọc, 2 = Hoàn trả cọc) | DEFAULT: 1, NOT NULL | 
 | amount | DECIMAL(15,2) | Số tiền cọc giao dịch | DEFAULT: 0.00, NOT NULL | 
 | transaction_date | DATE | Ngày thực hiện giao dịch | NOT NULL | 
 | payment_method | TINYINT UNSIGNED | Phương thức (1 = CK, 2 = Tiền mặt) | DEFAULT: 1, NOT NULL | 
 | note | VARCHAR(500) | Ghi chú | NULLABLE | 
 | created_by | BIGINT UNSIGNED | ID Admin duyệt | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo giao dịch | NULLABLE | 

---

### **2.1.3.22. Bảng invoices**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | invoice_code | VARCHAR(255) | Mã hóa đơn duy nhất | UNIQUE, NOT NULL | 
 | contract_id | BIGINT UNSIGNED | ID của hợp đồng thuê phòng | FOREIGN KEY -> contracts(id), CASCADE | 
 | room_id | BIGINT UNSIGNED | ID của phòng xuất hóa đơn | FOREIGN KEY -> rooms(id), CASCADE | 
 | billing_month | TINYINT UNSIGNED | Tháng chốt hóa đơn | NOT NULL | 
 | billing_year | SMALLINT UNSIGNED | Năm chốt hóa đơn | NOT NULL | 
 | period_start | DATE | Ngày bắt đầu tính tiền phòng | NOT NULL | 
 | period_end | DATE | Ngày kết thúc tính tiền phòng | NOT NULL | 
 | previous_debt_amount | DECIMAL(15,2) | Số tiền nợ cũ chưa đóng | DEFAULT: 0.00, NOT NULL | 
 | total_amount | DECIMAL(15,2) | Tổng số tiền phải đóng kỳ này | DEFAULT: 0.00, NOT NULL | 
 | paid_amount | DECIMAL(15,2) | Số tiền khách đã thanh toán | DEFAULT: 0.00, NOT NULL | 
 | remaining_amount | DECIMAL(15,2) | Số tiền còn nợ lại | DEFAULT: 0.00, NOT NULL | 
 | due_date | DATE | Hạn đóng tiền | NULLABLE | 
 | status | TINYINT UNSIGNED | Trạng thái hóa đơn | DEFAULT: 1, NOT NULL | 
 | issued_at | DATETIME | Thời gian gửi hóa đơn cho khách | NULLABLE | 
 | created_by | BIGINT UNSIGNED | ID của Admin lập hóa đơn | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo bản ghi | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật bản ghi | NULLABLE | 

---

### **2.1.3.23. Bảng invoice_items**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | invoice_id | BIGINT UNSIGNED | ID của hóa đơn chính | FOREIGN KEY -> invoices(id), CASCADE | 
 | service_id | BIGINT UNSIGNED | ID dịch vụ áp dụng | FOREIGN KEY -> services(id), SET NULL | 
 | meter_reading_id | BIGINT UNSIGNED | ID chỉ số điện nước sử dụng | FOREIGN KEY -> meter_readings(id), SET NULL | 
 | item_type | TINYINT UNSIGNED | Loại phí (Tiền phòng, dịch vụ...) | DEFAULT: 1, NOT NULL | 
 | description | VARCHAR(255) | Mô tả tên khoản phí hiển thị | NOT NULL | 
 | quantity | DECIMAL(12,2) | Số lượng sử dụng | DEFAULT: 1.00, NOT NULL | 
 | unit_price | DECIMAL(15,2) | Đơn giá của khoản phí | DEFAULT: 0.00, NOT NULL | 
 | amount | DECIMAL(15,2) | Thành tiền của khoản phí | DEFAULT: 0.00, NOT NULL | 
 | created_at | TIMESTAMP | Thời điểm tạo bản ghi | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật bản ghi | NULLABLE | 

---

### **2.1.3.24. Bảng invoice_reminder_logs**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | invoice_id | BIGINT UNSIGNED | ID của hóa đơn được nhắc nợ | FOREIGN KEY -> invoices(id), CASCADE | 
 | contract_id | BIGINT UNSIGNED | ID hợp đồng liên quan | FOREIGN KEY -> contracts(id), CASCADE | 
 | room_id | BIGINT UNSIGNED | ID phòng trọ | FOREIGN KEY -> rooms(id), CASCADE | 
 | notification_id | BIGINT UNSIGNED | ID thông báo hệ thống đã tạo | FOREIGN KEY -> notifications(id), CASCADE | 
 | reminder_date | DATE | Ngày thực hiện nhắc nợ | NOT NULL | 
 | tenant_count | INT UNSIGNED | Số lượng khách thuê nhận nhắc nợ | DEFAULT: 0, NOT NULL | 
 | mail_queued_count | INT UNSIGNED | Số lượng email xếp hàng đợi gửi | DEFAULT: 0, NOT NULL | 
 | status | TINYINT UNSIGNED | Trạng thái gửi nhắc nợ | DEFAULT: 1, NOT NULL | 
 | error_message | TEXT | Chi tiết thông báo lỗi (nếu có) | NULLABLE | 
 | created_at | TIMESTAMP | Thời điểm tạo bản ghi | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật bản ghi | NULLABLE | 

---

### **2.1.3.25. Bảng invoice_debt_rollovers**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | source_invoice_id | BIGINT UNSIGNED | ID hóa đơn gốc mang nợ | FOREIGN KEY -> invoices(id), CASCADE | 
 | target_invoice_id | BIGINT UNSIGNED | ID hóa đơn mới nhận nợ | FOREIGN KEY -> invoices(id), CASCADE | 
 | amount | DECIMAL(15,2) | Số tiền nợ được kết chuyển | NOT NULL | 
 | settled_amount | DECIMAL(15,2) | Số tiền nợ đã được thanh toán xong | DEFAULT: 0.00, NOT NULL | 
 | status | TINYINT UNSIGNED | Trạng thái gia hạn nợ | DEFAULT: 1, NOT NULL | 
 | created_at | TIMESTAMP | Thời điểm tạo bản ghi | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật bản ghi | NULLABLE | 

---

### **2.1.3.26. Bảng payments**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | payment_code | VARCHAR(50) | Mã giao dịch đóng tiền | UNIQUE, NOT NULL | 
 | invoice_id | BIGINT UNSIGNED | ID hóa đơn đóng tiền | FOREIGN KEY -> invoices(id), CASCADE | 
 | allocated_from_payment_id | BIGINT UNSIGNED | Phân bổ từ GD thanh toán trước | FOREIGN KEY -> payments(id), CASCADE | 
 | invoice_debt_rollover_id | BIGINT UNSIGNED | Liên kết từ khoản gia hạn nợ | FOREIGN KEY -> invoice_debt_rollovers(id), CASCADE | 
 | is_internal_allocation | BOOLEAN | GD phân bổ nội bộ từ tiền thừa | DEFAULT: FALSE, NOT NULL | 
 | amount | DECIMAL(15,2) | Số tiền đóng thực tế | DEFAULT: 0.00, NOT NULL | 
 | payment_date | DATETIME | Ngày nộp tiền | NOT NULL | 
 | payment_method | TINYINT UNSIGNED | Cách đóng (1 = CK, 2 = Tiền mặt) | DEFAULT: 1, NOT NULL | 
 | transaction_reference | VARCHAR(150) | Mã tham chiếu giao dịch ngân hàng / SePay | NULLABLE | 
 | status | TINYINT UNSIGNED | 1 = Chờ duyệt, 2 = Thành công, 3 = Bị từ chối | DEFAULT: 1, NOT NULL | 
 | proof_image | VARCHAR(500) | Ảnh chụp biên lai thanh toán | NULLABLE | 
 | note | VARCHAR(500) | Nội dung chuyển tiền | NULLABLE | 
 | collected_by | BIGINT UNSIGNED | ID Admin duyệt | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.27. Bảng maintenance_requests**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | request_code | VARCHAR(50) | Mã phiếu yêu cầu sửa chữa | UNIQUE, NOT NULL | 
 | tenant_id | BIGINT UNSIGNED | ID khách báo sự cố | FOREIGN KEY -> tenants(id), CASCADE | 
 | room_id | BIGINT UNSIGNED | ID căn phòng xảy ra sự cố | FOREIGN KEY -> rooms(id), CASCADE | 
 | title | VARCHAR(255) | Tiêu đề ngắn gọn về sự cố | NOT NULL | 
 | description | TEXT | Nội dung chi tiết tình trạng hư hỏng | NOT NULL | 
 | status | TINYINT UNSIGNED | Trạng thái (1 = Tiếp nhận, 3 = Đang sửa, 4 = Xong, 5 = Hủy) | DEFAULT: 1, NOT NULL | 
 | images | JSON | Danh sách ảnh chụp sự cố | NULLABLE | 
 | assigned_to | BIGINT UNSIGNED | ID Admin/Kỹ thuật được giao sửa | FOREIGN KEY -> admins(id), SET NULL | 
 | received_at | DATETIME | Ngày tiếp nhận | NULLABLE | 
 | completed_at | DATETIME | Ngày hoàn tất | NULLABLE | 
 | created_at | TIMESTAMP | Thời điểm tạo bản ghi | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật bản ghi | NULLABLE | 

---

### **2.1.3.28. Bảng maintenance_feedbacks**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | maintenance_request_id | BIGINT UNSIGNED | ID phiếu sửa chữa | FOREIGN KEY -> maintenance_requests(id), CASCADE | 
 | tenant_id | BIGINT UNSIGNED | ID khách hàng đánh giá | FOREIGN KEY -> tenants(id), CASCADE | 
 | rating | TINYINT UNSIGNED | Điểm số đánh giá (1 - 5 sao) | DEFAULT: 5, NOT NULL | 
 | images | JSON | Ảnh nghiệm thu từ khách | NULLABLE | 
 | comment | TEXT | Nhận xét chi tiết | NULLABLE | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.29. Bảng maintenance_request_logs**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | maintenance_request_id | BIGINT UNSIGNED | ID phiếu sửa chữa | FOREIGN KEY -> maintenance_requests(id), CASCADE | 
 | old_status | TINYINT UNSIGNED | Trạng thái cũ | NULLABLE | 
 | new_status | TINYINT UNSIGNED | Trạng thái mới | NOT NULL | 
 | note | TEXT | Ghi chú người thao tác | NULLABLE | 
 | created_by | BIGINT UNSIGNED | ID Admin cập nhật | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm thực hiện | NULLABLE | 

---

### **2.1.3.30. Bảng notifications**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | title | VARCHAR(255) | Tiêu đề thông báo | NOT NULL | 
 | content | TEXT | Nội dung thông báo | NOT NULL | 
 | notification_type | TINYINT UNSIGNED | Phân loại tin (1 = Hệ thống, 2 = Hóa đơn, 3 = Nhắc nợ) | DEFAULT: 1, NOT NULL | 
 | target_type | TINYINT UNSIGNED | Đối tượng (1 = Tất cả, 2 = Tòa nhà, 3 = Phòng, 4 = Khách hàng) | DEFAULT: 1, NOT NULL | 
 | building_id | BIGINT UNSIGNED | ID tòa nhà nhận tin | FOREIGN KEY -> buildings(id), CASCADE | 
 | room_id | BIGINT UNSIGNED | ID phòng nhận tin | FOREIGN KEY -> rooms(id), CASCADE | 
 | tenant_id | BIGINT UNSIGNED | ID khách thuê nhận tin | FOREIGN KEY -> tenants(id), CASCADE | 
 | action_url | VARCHAR(255) | Link hướng tới khi click | NULLABLE | 
 | published_at | DATETIME | Thời điểm gửi | NULLABLE | 
 | status | TINYINT UNSIGNED | Trạng thái gửi tin | DEFAULT: 1, NOT NULL | 
 | created_by | BIGINT UNSIGNED | ID Admin gửi | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.31. Bảng notification_reads**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | notification_id | BIGINT UNSIGNED | ID thông báo | FOREIGN KEY -> notifications(id), CASCADE | 
 | tenant_id | BIGINT UNSIGNED | ID khách thuê đã đọc | FOREIGN KEY -> tenants(id), CASCADE | 
 | admin_id | BIGINT UNSIGNED | ID Admin đã đọc | FOREIGN KEY -> admins(id), RESTRICT | 
 | read_at | DATETIME | Thời điểm xem thông báo | NOT NULL | 

---

### **2.1.3.32. Bảng expense_categories**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | name | VARCHAR(150) | Tên danh mục khoản chi | UNIQUE, NOT NULL | 
 | description | TEXT | Mô tả chi tiết danh mục | NULLABLE | 
 | is_active | BOOLEAN | Trạng thái hoạt động | DEFAULT: TRUE, NOT NULL | 
 | created_by | BIGINT UNSIGNED | ID của Admin tạo danh mục | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo bản ghi | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật bản ghi | NULLABLE | 

---

### **2.1.3.33. Bảng expenses**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | expense_code | VARCHAR(50) | Mã phiếu chi duy nhất | UNIQUE, NOT NULL | 
 | building_id | BIGINT UNSIGNED | ID tòa nhà phát sinh chi phí | FOREIGN KEY -> buildings(id), CASCADE | 
 | room_id | BIGINT UNSIGNED | ID phòng phát sinh chi phí | FOREIGN KEY -> rooms(id), CASCADE | 
 | expense_category_id | BIGINT UNSIGNED | ID danh mục khoản chi | FOREIGN KEY -> expense_categories(id), CASCADE | 
 | title | VARCHAR(255) | Nội dung chi tiết phiếu chi | NOT NULL | 
 | amount | DECIMAL(15,2) | Số tiền thực tế chi | DEFAULT: 0.00, NOT NULL | 
 | expense_date | DATE | Ngày thực hiện chi tiêu | NOT NULL | 
 | receipt_images | JSON | Danh sách ảnh chụp hóa đơn, biên lai | NULLABLE | 
 | payment_method | TINYINT UNSIGNED | Phương thức thanh toán (Tiền mặt/Chuyển khoản) | DEFAULT: 1, NOT NULL | 
 | note | TEXT | Ghi chú chi tiết | NULLABLE | 
 | status | TINYINT UNSIGNED | Trạng thái phiếu chi (Đã duyệt/Chờ duyệt) | DEFAULT: 1, NOT NULL | 
 | created_by | BIGINT UNSIGNED | ID của Admin tạo phiếu chi | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo bản ghi | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật bản ghi | NULLABLE | 

---

### **2.1.3.34. Bảng settings**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | building_id | BIGINT UNSIGNED | ID tòa nhà cấu hình | FOREIGN KEY -> buildings(id), CASCADE | 
 | setting_label | VARCHAR(150) | Nhãn hiển thị của cài đặt | NOT NULL | 
 | setting_value | VARCHAR(500) | Giá trị của cài đặt | NULLABLE | 
 | description | VARCHAR(500) | Mô tả chi tiết cài đặt | NULLABLE | 
 | is_public | BOOLEAN | Có công khai cho khách thuê xem không | DEFAULT: TRUE, NOT NULL | 
 | created_by | BIGINT UNSIGNED | ID Admin tạo cài đặt | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo bản ghi | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật bản ghi | NULLABLE | 

---

### **2.1.3.35. Bảng security_cameras**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | building_id | BIGINT UNSIGNED | ID tòa nhà lắp đặt | FOREIGN KEY -> buildings(id), CASCADE | 
 | name | VARCHAR(120) | Tên camera | NOT NULL | 
 | location | VARCHAR(160) | Vị trí lắp cụ thể | NULLABLE | 
 | source_type | TINYINT UNSIGNED | Luồng camera (1 = RTSP, 2 = HTTP Web) | DEFAULT: 1, NOT NULL | 
 | stream_url | TEXT | URL kết nối RTSP stream | NOT NULL | 
 | username | VARCHAR(120) | Tài khoản truy cập | NULLABLE | 
 | password | VARCHAR(255) | Mật khẩu truy cập | NULLABLE | 
 | is_ai_enabled | BOOLEAN | Kích hoạt AI giám sát lửa/hút thuốc | DEFAULT: FALSE, NOT NULL | 
 | frame_interval_seconds | SMALLINT UNSIGNED | Tần suất chụp frame (giây) | DEFAULT: 5, NOT NULL | 
 | frames_per_batch | TINYINT UNSIGNED | Số lượng frame gửi xử lý AI | DEFAULT: 1, NOT NULL | 
 | alert_cooldown_seconds | SMALLINT UNSIGNED | Thời gian giãn cách báo động | DEFAULT: 300, NOT NULL | 
 | status | TINYINT UNSIGNED | Trạng thái camera hoạt động | DEFAULT: 1, NOT NULL | 
 | created_by | BIGINT UNSIGNED | ID Admin cấu hình | FOREIGN KEY -> admins(id), RESTRICT | 
 | updated_by | BIGINT UNSIGNED | ID Admin sửa đổi | FOREIGN KEY -> admins(id), RESTRICT | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.36. Bảng fire_safety_alerts**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | security_camera_id | BIGINT UNSIGNED | ID của camera phát hiện sự việc | FOREIGN KEY -> security_cameras(id), CASCADE | 
 | building_id | BIGINT UNSIGNED | ID của tòa nhà xảy ra cảnh báo | FOREIGN KEY -> buildings(id), CASCADE | 
 | source_label | VARCHAR(160) | Nhãn của luồng phát hiện | NULLABLE | 
 | risk_level | TINYINT UNSIGNED | Cấp độ rủi ro cảnh báo | DEFAULT: 1, NOT NULL | 
 | detected_fire | BOOLEAN | AI phát hiện lửa cháy | DEFAULT: FALSE, NOT NULL | 
 | detected_smoke | BOOLEAN | AI phát hiện khói | DEFAULT: FALSE, NOT NULL | 
 | detected_smoking | BOOLEAN | AI phát hiện hành vi hút thuốc | DEFAULT: FALSE, NOT NULL | 
 | confidence | DECIMAL(5,4) | Độ tin cậy của mô hình AI | DEFAULT: 0.0000, NOT NULL | 
 | snapshot_path | VARCHAR(500) | Đường dẫn ảnh chụp sự việc | NULLABLE | 
 | ai_summary | TEXT | Tóm tắt sự việc của AI | NULLABLE | 
 | raw_ai_payload | JSON | Dữ liệu thô gửi về từ AI service | NULLABLE | 
 | status | TINYINT UNSIGNED | Trạng thái xử lý cảnh báo | DEFAULT: 1, NOT NULL | 
 | acknowledged_by | BIGINT UNSIGNED | ID của Admin tiếp nhận xử lý | FOREIGN KEY -> admins(id), SET NULL | 
 | acknowledged_at | TIMESTAMP | Thời điểm tiếp nhận cảnh báo | NULLABLE | 
 | resolved_by | BIGINT UNSIGNED | ID của Admin giải quyết xong | FOREIGN KEY -> admins(id), SET NULL | 
 | resolved_at | TIMESTAMP | Thời điểm giải quyết xong | NULLABLE | 
 | created_at | TIMESTAMP | Thời điểm tạo bản ghi | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật bản ghi | NULLABLE | 

---

### **2.1.3.37. Bảng chat_conversations**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | conversation_type | TINYINT UNSIGNED | Loại hội thoại (1 = Quản lý - Khách, 2 = Admin - Quản lý) | DEFAULT: 1, NOT NULL | 
 | building_id | BIGINT UNSIGNED | ID tòa nhà liên quan | FOREIGN KEY -> buildings(id), CASCADE | 
 | room_id | BIGINT UNSIGNED | ID phòng liên quan | FOREIGN KEY -> rooms(id), CASCADE | 
 | tenant_id | BIGINT UNSIGNED | ID khách thuê tham gia | FOREIGN KEY -> tenants(id), CASCADE | 
 | manager_admin_id | BIGINT UNSIGNED | ID Admin Quản lý tòa nhà | FOREIGN KEY -> admins(id), RESTRICT | 
 | super_admin_id | BIGINT UNSIGNED | ID Super Admin tham gia | FOREIGN KEY -> admins(id), RESTRICT | 
 | last_message_id | BIGINT UNSIGNED | ID tin nhắn mới nhất | FOREIGN KEY -> chat_messages(id), SET NULL | 
 | last_message_at | TIMESTAMP | Thời gian gửi tin nhắn mới nhất | NULLABLE | 
 | tenant_unread_count | INT UNSIGNED | Số tin nhắn chưa đọc của khách | DEFAULT: 0, NOT NULL | 
 | admin_unread_count | INT UNSIGNED | Số tin nhắn chưa đọc của admin | DEFAULT: 0, NOT NULL | 
 | tenant_last_read_at | TIMESTAMP | Thời điểm khách xem tin cuối | NULLABLE | 
 | admin_last_read_at | TIMESTAMP | Thời điểm admin xem tin cuối | NULLABLE | 
 | status | TINYINT UNSIGNED | Trạng thái hội thoại | DEFAULT: 1, NOT NULL | 
 | created_at | TIMESTAMP | Thời điểm tạo | NULLABLE | 
 | updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE | 

---

### **2.1.3.38. Bảng chat_messages**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
 | id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT | 
 | conversation_id | BIGINT UNSIGNED | ID cuộc hội thoại liên kết | FOREIGN KEY -> chat_conversations(id), CASCADE | 
 | sender_type | VARCHAR(32) | Loại model người gửi (Admin/Tenant) | NOT NULL | 
 | sender_id | BIGINT UNSIGNED | ID người gửi | NOT NULL | 
 | sender_role | TINYINT UNSIGNED | Vai trò người gửi (1 = Admin, 2 = Tenant) | DEFAULT: 1, NOT NULL | 
 | body | TEXT | Nội dung tin nhắn hoặc link tệp đính kèm | NOT NULL | 
 | queued_at | TIMESTAMP | Thời gian xếp hàng đợi gửi | NULLABLE | 
 | sent_at | TIMESTAMP | Thời gian gửi tin thành công | NULLABLE | 
 | read_at | TIMESTAMP | Thời gian người nhận đọc tin | NULLABLE | 
 | created_at | TIMESTAMP | Thời gian tạo tin nhắn | NULLABLE | 
 | updated_at | TIMESTAMP | Thời gian cập nhật tin nhắn | NULLABLE | 
