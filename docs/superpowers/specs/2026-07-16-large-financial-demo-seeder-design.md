# Thiết kế seeder dữ liệu demo tài chính quy mô lớn

**Ngày:** 2026-07-16  
**Phạm vi:** Backend Laravel tại `BE_StayHub`  
**Mục tiêu:** Bổ sung dữ liệu demo có lịch sử hóa đơn và doanh thu chính xác mà không xóa hoặc sửa dữ liệu đang tồn tại.

## 1. Kết quả cần tạo

Seeder mới bổ sung đúng:

- 12 tòa nhà mới có tên và địa chỉ thuần Việt Nam.
- 10 phòng cho mỗi tòa, tổng cộng 120 phòng mới.
- 20 khách thuê cho mỗi phòng, tổng cộng 2.400 khách thuê mới.
- Một hợp đồng đang hiệu lực cho mỗi phòng, tổng cộng 120 hợp đồng mới.
- 18 kỳ hóa đơn cho mỗi hợp đồng từ tháng 01/2025 đến hết tháng 06/2026, tổng cộng 2.160 hóa đơn mới.
- Hai đồng hồ điện/nước cho mỗi phòng và một chỉ số mỗi kỳ, tổng cộng 4.320 chỉ số.
- Các khoản mục và thanh toán đủ để báo cáo doanh thu, công nợ và cơ cấu doanh thu có dữ liệu phong phú.

Mỗi phòng mới có `max_occupants = 20` và `current_occupants = 20`. Mỗi hợp đồng liên kết đúng 20 `contract_tenants` đang ở.

## 2. Nguyên tắc bảo toàn dữ liệu cũ

Tạo `StayHubLargeFinancialDemoSeeder` độc lập và chỉ chạy trực tiếp bằng tên class. Không đăng ký seeder này vào `DatabaseSeeder`, vì `DatabaseSeeder` hiện gọi `StayHubDemoSeeder` và seeder cũ có các thao tác cập nhật/xóa có thể chạm dữ liệu đã tồn tại.

Seeder mới tuân thủ các quy tắc sau:

- Không dùng `truncate`, `delete`, `migrate:fresh`, `updateOrCreate` hoặc cập nhật toàn bảng.
- Không gọi `StayHubDemoSeeder`.
- Không cập nhật bất kỳ bản ghi nào không mang namespace riêng của seeder mới.
- Mọi khóa tự nhiên mới có prefix deterministic `SHOWCASE26` hoặc miền `demo.example.test`.
- Khi chạy lại, bản ghi đã có sẽ được nhận diện và bỏ qua; seeder không nhân đôi và không đặt lại mật khẩu, timestamp hay giá trị của bản ghi cũ.
- Toàn bộ quá trình tạo dữ liệu nằm trong một transaction. Nếu có lỗi, không để lại dữ liệu dở dang.
- Chèn theo chunk để tránh hàng nghìn truy vấn Eloquent và tránh kích hoạt Scout/event không cần thiết.

## 3. Dữ liệu danh mục dùng chung

Seeder chỉ đọc các danh mục hiện có nếu tìm được đúng slug nghiệp vụ:

- `electric`/dịch vụ điện theo đồng hồ.
- `water`/dịch vụ nước theo đồng hồ.
- Internet theo phòng.
- Rác theo người.
- Vệ sinh theo phòng.
- Gửi xe theo xe, nếu cần cho khoản mục demo.

Nếu thiếu danh mục bắt buộc, seeder tạo bản ghi mới bằng slug có namespace riêng và không sửa bản ghi gần giống của người dùng. Giá áp dụng được tạo riêng theo 12 tòa mới và có hiệu lực từ `2025-01-01`.

Seeder tái sử dụng một `room_type` đang hoạt động nếu có. Nếu không có, nó tạo loại phòng `Ký túc xá 20 người SHOWCASE26` với slug riêng.

## 4. Tòa nhà và phòng

Danh sách 12 tòa nhà dùng tên, địa chỉ và người quản lý Việt Nam. Tên dự kiến:

1. Ký túc xá Hoa Phượng Đỏ
2. Ký túc xá Bến Nghé
3. Ký túc xá Gia Định
4. Ký túc xá Thủ Thiêm
5. Ký túc xá Bình Quới
6. Ký túc xá Tân Cảng
7. Ký túc xá Hoàng Sa
8. Ký túc xá Trường Sa
9. Ký túc xá Phú Nhuận
10. Ký túc xá Chợ Lớn
11. Ký túc xá An Đông
12. Ký túc xá Văn Thánh

Mỗi tòa có mã `SHOWCASE26-B01` đến `SHOWCASE26-B12`, một quản lý riêng, địa chỉ hợp lệ tại TP.HCM và chính sách giới tính hỗn hợp. Mỗi quản lý dùng email giả dạng `quanly.b01@demo.example.test`; mật khẩu gốc là `12345678` và được hash bằng Laravel.

Mỗi tòa có 10 phòng, đánh số theo mẫu `B01-P101` đến `B01-P502`, hai phòng mỗi tầng trong năm tầng. Phòng có:

- Giá thuê dao động có quy luật theo tòa và tầng để doanh thu giữa các tòa khác nhau.
- Diện tích và mô tả bằng tiếng Việt.
- `max_occupants = 20`.
- `current_occupants = 20` ngay sau khi đủ 20 liên kết khách thuê.
- Trạng thái hoạt động.
- Không tạo `building_images`, `room_images` hoặc đường dẫn ảnh giả.

## 5. Khách thuê và tài khoản

Tạo 20 khách thuê cho mỗi phòng bằng tổ hợp họ, tên đệm và tên tiếng Việt deterministic. Dữ liệu bắt buộc đều unique:

- Username: `showcase26_b01_p101_t01`.
- Email: `showcase26.b01.p101.t01@demo.example.test`.
- Điện thoại: chuỗi 10 số thuộc dải demo riêng.
- CCCD: chuỗi 12 số thuộc dải demo riêng.
- Mật khẩu gốc: `12345678`, hash một lần và tái sử dụng hash trong cùng lần chạy để tăng tốc.

Tenant được gắn đúng `building_id`, quản lý tạo, trạng thái đang thuê và giới tính phân bổ gần cân bằng. Ngày sinh nằm trong khoảng hợp lý cho sinh viên/người đi làm trẻ. Các trường `avatar_url`, `front_image_url` và `back_image_url` là `null`; không tạo file ảnh.

## 6. Hợp đồng và cư trú

Mỗi phòng có một hợp đồng namespace `SHOWCASE26-HD-B01-P101`, bắt đầu `2025-01-01` và kết thúc sau `2026-06-30` để bao phủ toàn bộ 18 kỳ. Hợp đồng:

- Trạng thái đang hiệu lực.
- Có một khách đại diện.
- Giá phòng khớp với phòng.
- Tiền cọc lớn hơn tiền phòng theo validation hiện tại.
- Trạng thái cọc thành công và có giao dịch thu cọc riêng nếu schema yêu cầu.
- `contract_files = null`, không tạo PDF/chữ ký/ảnh giả ngoài default schema bắt buộc.

Mỗi hợp đồng có đúng 20 dòng `contract_tenants`, cùng ngày bắt đầu, `is_staying = true`, `leave_date = null`; chỉ một dòng có `is_representative = true`.

## 7. Dịch vụ, đồng hồ và chỉ số

Mỗi phòng được cấu hình dịch vụ internet, rác và vệ sinh qua `room_services`/`room_service_prices`. Giá điện và nước lấy từ `service_prices` cấp tòa nhà, đúng quy tắc backend không deal điện/nước theo hợp đồng.

Mỗi phòng có:

- Một đồng hồ điện, mã `SHOWCASE26-DIEN-B01-P101`.
- Một đồng hồ nước, mã `SHOWCASE26-NUOC-B01-P101`.
- `image_path = null`.
- Trạng thái hoạt động.

Mỗi kỳ tạo một chỉ số cho từng đồng hồ. Chỉ số tăng liên tục, không âm và thỏa:

```text
consumption = current_reading - previous_reading
```

Mức tiêu thụ có biến động deterministic theo tòa, phòng, mùa và tháng để biểu đồ không phẳng. Reading liên kết đúng `contract_id`, `billing_year`, `billing_month`, service/meter tương ứng; `status = INVOICED`, `image_path = null`.

## 8. Cấu trúc hóa đơn

Mỗi hợp đồng có đúng một hóa đơn không hủy cho mỗi kỳ từ 01/2025 đến 06/2026. Mã hóa đơn theo mẫu `SHOWCASE26-HDD-B01-P101-202501`.

Ngày tháng:

- `period_start`: ngày đầu tháng.
- `period_end`: ngày cuối tháng.
- `issued_at`: ngày cuối tháng hoặc ngày đầu tháng kế tiếp, thống nhất toàn bộ seeder.
- `due_date`: ngày 5 của tháng kế tiếp.

Mỗi hóa đơn thường có các khoản mục:

- Tiền phòng.
- Tiền điện = consumption điện × giá điện của tòa.
- Tiền nước = consumption nước × giá nước của tòa.
- Internet theo phòng.
- Phí rác = 20 người × đơn giá/người.
- Vệ sinh theo phòng.
- Một số hóa đơn có gửi xe, phụ thu hoặc giảm trừ deterministic để cơ cấu doanh thu đa dạng.

Các bất biến bắt buộc:

```text
total_amount = SUM(invoice_items.amount)
paid_amount = SUM(payments.amount WHERE status = CONFIRMED AND is_internal_allocation = false)
remaining_amount = total_amount - paid_amount
```

`previous_debt_amount` luôn bằng 0 trong seeder này. Seeder không tạo kết chuyển nợ; phạm vi đó được loại bỏ để tránh double-count và giữ dữ liệu tài chính dễ đối soát.

## 9. Thanh toán và biểu đồ doanh thu

Doanh thu thực thu của hệ thống lấy từ payment đã xác nhận theo `payment_date`, không lấy trực tiếp từ `invoice.total_amount`. Vì vậy seeder tạo payment khớp tuyệt đối với `paid_amount`.

Phân bố deterministic:

- Các kỳ 01/2025–04/2026: khoảng 96% hóa đơn thanh toán đủ; phần nhỏ thanh toán trễ hoặc chia hai đợt nhưng đều được giải quyết trước kỳ gần nhất.
- Kỳ 05–06/2026: có đủ hóa đơn paid, partial, unpaid/overdue, pending proof và một tỷ lệ rất nhỏ cancelled để demo trạng thái.
- Thanh toán đủ: status invoice `PAID`, remaining bằng 0.
- Thanh toán một phần: status `PARTIALLY_PAID` nếu chưa quá hạn; nếu quá hạn so với ngày chạy demo thì status `OVERDUE`.
- Chưa trả: `UNPAID` nếu chưa tới hạn, ngược lại `OVERDUE`.
- Hóa đơn hủy: status `CANCELLED`, remaining bằng 0 và không có payment confirmed.
- Payment pending/cancelled không được cộng vào `paid_amount` hoặc doanh thu.
- `is_internal_allocation = false` cho mọi payment tiền thật.
- `proof_image = null`; chuyển khoản dùng mã tham chiếu giả deterministic, tiền mặt để tham chiếu null.

Phần lớn payment nằm cùng tháng hóa đơn để biểu đồ 2025–2026 ổn định; một tỷ lệ nhỏ payment nằm ở tháng sau để demo dòng tiền trả trễ. Số tiền và ngày thanh toán được phân bổ sao cho tổng theo tháng không phẳng, đồng thời doanh thu từng tòa khác nhau có chủ đích.

## 10. Kiến trúc triển khai

Tách trách nhiệm thành các đơn vị nhỏ:

- `StayHubLargeFinancialDemoSeeder`: điều phối transaction và thứ tự phụ thuộc.
- Một lớp builder/dataset thuần PHP: sinh tên, mã, ngày, giá, consumption và phân bố trạng thái deterministic.
- Một lớp writer dùng query builder và chèn theo chunk, chỉ insert các mã thuộc namespace `SHOWCASE26` chưa tồn tại.
- Một lớp verifier hoặc command/test helper: đối soát count và bất biến tài chính sau khi seed.

Nếu cấu trúc dự án hiện tại ưu tiên seeder một file, vẫn giữ các method ngắn theo từng domain; không đưa toàn bộ logic vào một method lớn. Không thay đổi controller/model nghiệp vụ để phục vụ seeder.

## 11. Xử lý lỗi và khả năng chạy lại

- Kiểm tra schema/danh mục bắt buộc trước khi tạo volume lớn.
- Ném exception rõ tiếng Việt nếu thiếu admin/region/dịch vụ không thể tạo an toàn.
- Chèn theo thứ tự FK: admin/region → building/type → room → tenant → contract/pivot → service/meter → reading → invoice → item → payment.
- Dùng lookup map sau từng batch; không dựa vào ID cố định.
- Khi phát hiện một mã `SHOWCASE26` đã tồn tại, không cập nhật dòng đó. Nếu bộ dữ liệu namespace bị dở dang hoặc mâu thuẫn, verifier báo lỗi thay vì âm thầm sửa dữ liệu.
- Transaction bảo đảm lần chạy lỗi không tạo bộ dữ liệu nửa vời.

## 12. Kiểm thử và đối soát

Thêm feature test chạy trên SQLite in-memory cho seeder mới. Test theo TDD phải chứng minh:

1. Lần chạy đầu tạo đúng 12 tòa, 120 phòng, 2.400 tenant, 120 hợp đồng và 2.160 hóa đơn.
2. Mỗi phòng có đúng 20 người đang ở và sức chứa 20.
3. Mỗi hợp đồng có 18 kỳ liên tục từ 01/2025 đến 06/2026.
4. Tất cả meter readings thỏa công thức consumption và liên kết đúng contract.
5. Mỗi invoice thỏa tổng item, paid, remaining và mapping status.
6. Chỉ confirmed real payment được tính vào paid/doanh thu.
7. Mọi trường ảnh thuộc dữ liệu mới đều null và không sinh file.
8. Email/username/phone/CCCD/mã hóa đơn/mã payment/mã meter không trùng.
9. Chạy seeder lần hai không tăng count và không làm thay đổi snapshot dữ liệu lần đầu.
10. Một bản ghi dữ liệu có sẵn được tạo trước test vẫn giữ nguyên sau cả hai lần seed.

Ngoài test tự động, sau khi seed trên MySQL cần chạy truy vấn đối soát tổng và API báo cáo năm 2025, năm 2026. Tuyệt đối không chạy lệnh xóa database để kiểm thử trên dữ liệu demo của người dùng.

## 13. Lệnh vận hành an toàn

Lệnh dự kiến khi môi trường Docker khả dụng:

```bash
docker exec laravel_app php artisan db:seed \
  --class='Database\\Seeders\\StayHubLargeFinancialDemoSeeder' \
  --force
```

Không chạy `php artisan db:seed` không chỉ định class, vì lệnh đó đi qua `DatabaseSeeder` hiện tại và có thể chạm bộ demo cũ.
