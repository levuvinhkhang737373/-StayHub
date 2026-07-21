# Thiết kế chuẩn hóa Meilisearch

## Mục tiêu

Đảm bảo bốn tài nguyên đang dùng Laravel Scout (`Region`, `Building`, `Tenant`, `Invoice`) tìm kiếm ổn định bằng Meilisearch, đồng thời giữ nguyên các màn hình đang tìm kiếm trực tiếp bằng MySQL.

## Thiết kế

- Mỗi model Scout khai báo index cố định và dữ liệu tìm kiếm đầy đủ qua `toSearchableArray()`.
- `config/scout.php` là nguồn cấu hình duy nhất cho searchable, filterable và sortable attributes.
- Các controller tiếp tục dùng `Model::search()` và chỉ sort/filter theo thuộc tính đã khai báo trong cấu hình.
- Một Artisan command riêng đồng bộ settings trước, sau đó import lại từng index. Command chờ tác vụ Meilisearch hoàn tất giữa hai bước để tránh import chạy trên settings cũ.
- Command trả exit code lỗi ngay khi bất kỳ index nào không thể đồng bộ, phù hợp để chạy trong quy trình deploy.

## Phạm vi

- Meilisearch: khu vực, tòa nhà, khách thuê, hóa đơn.
- MySQL: toàn bộ tìm kiếm còn lại, không thay đổi hành vi.
- Không tự động chuyển truy vấn Scout sang MySQL vì điều đó có thể che giấu lỗi hạ tầng và tạo kết quả/ranking khác nhau giữa các lần gọi.

## Kiểm thử

- Kiểm tra mọi model Scout mục tiêu có cấu hình index tương ứng.
- Kiểm tra các thuộc tính searchable/filterable/sortable khớp với document được index và query thực tế.
- Kiểm tra command thực thi đúng thứ tự: sync settings rồi import đủ bốn model.
- Chạy command trên môi trường Docker và xác minh tìm theo tên tòa nhà/tên khách thuê không còn lỗi `invalid_search_sort`.
