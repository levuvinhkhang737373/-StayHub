# LUỒNG XỬ LÝ: NHẬN DIỆN KHUÔN MẶT MICROSERVICES (DEEPFACE + MYSQL + MINIO +Qdrant )

Quy trình Xác thực FaceID (Tối ưu với Qdrant & MinIO)
1. Thành phần hệ thống
Front-end: App/Web Capture ảnh khuôn mặt.
Laravel Backend: Điều phối dữ liệu, quản lý Auth Token.
AI Service (Python): Chỉ làm nhiệm vụ trích xuất (Extraction) vector từ ảnh.
MinIO (S3): Lưu trữ dữ liệu ảnh vật lý (để đối soát).
Qdrant (Vector DB): Lưu trữ vector embedding và thực hiện tìm kiếm 1:N siêu nhanh build thêm trong docker
+ admin_id
+ embedding
MySQL: Lưu trữ thông tin admin 
+ id: Primary Key.
+ image_path_faceid: Đường dẫn ảnh gốc trên MinIO .
+ created_faceid_at, updated_faceid_at
2. Quy trình Đăng ký (FaceID Registration)
Front-end: Người dùng chụp ảnh -> Gửi multipart/form-data lên Laravel.
Laravel:
Thực hiện lưu ảnh vào MinIO (Disk s3). Ví dụ path: face-credentials/admin/admin-123.jpg.
Lấy nội dung ảnh gửi sang AI Service (Endpoint).
AI Service: Xử lý ảnh -> Trả về mảng embedding (vector 128 hoặc 512 chiều).
Laravel:
Lưu vào Qdrant: Đẩy một "Point" (điểm dữ liệu) lên Qdrant Collection với:
id: UUID hoặc ID định danh.
vector: Mảng embedding nhận từ AI Service.
payload: { "admin_id": 123 }.
Kết quả: Trả về thành công cho Front-end.
3. Quy trình Đăng nhập (FaceID Login - 1:N Search)
Đây là quy trình quan trọng nhất, tận dụng sức mạnh của Qdrant:
Front-end: Người dùng chụp ảnh tại màn hình Login -> Gửi lên Laravel.
Laravel:
Gửi ảnh trực tiếp sang AI Service để lấy vector embedding tạm thời của ảnh vừa chụp.
Lưu ý: Không cần lưu ảnh này vào MinIO .
AI Service: Trả về vector embedding của khuôn mặt khách vừa chụp.
Laravel -> Qdrant (Tìm kiếm): Gọi API Search của Qdrant với:
vector: Vector vừa nhận từ AI Service.
limit: 1 (Lấy kết quả giống nhất).
threshold: 0.9 (Chỉ lấy nếu độ khớp trên 90%).
Qdrant: Thực hiện tính toán khoảng cách vector và trả về admin_id có khuôn mặt giống nhất.
Laravel:
Nếu tìm thấy kết quả phù hợp: Lấy admin_id, thực hiện cung cấp santumc cookie.
Nếu không thấy: Trả về lỗi "Không nhận diện được khuôn mặt".
Kết quả: Trả về Token và thông tin Admin cho Front-end.

