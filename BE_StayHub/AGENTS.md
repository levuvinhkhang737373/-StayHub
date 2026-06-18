# Project Rules

Dự án này là Laravel backend phiên bản 13 sử dụng kiến trúc MVC. Cache dùng Redis, database chính là MySQL, Auth dùng Sanctum HttpOnly Cookie, API viết chuẩn RESTful, realtime dùng Soketi và chạy trên nền Octane. Mọi logic phức tạp phải đẩy vào Queues, không chạy đồng bộ. Khi tạo API luôn dùng API Resources.

- Tự dùng tools để đọc file, chạy shell (`cat`, `ls`, `grep`, `rg`) và tự mò đường dẫn/dữ liệu cần thiết.
- Project nằm trong Docker; lệnh nào cần chạy trong Docker thì tự chạy.
- Làm xong việc thì báo cáo, không hỏi câu dư thừa.
- Làm chính xác, chạy được, hạn chế để người dùng phải sửa lại.
- Khi sửa một chức năng, đọc lại tất cả file liên quan đến chức năng đó rồi sửa theo.
- Chỉ sửa đúng phần liên quan đến yêu cầu, không đụng file Docker hoặc phần không liên quan.

## Code Style

- Viết code chuẩn PSR-12.
- Ưu tiên code ngắn gọn, rõ ràng, dùng Laravel Collection methods khi xử lý mảng dữ liệu.
- Khi code xong, comment bằng tiếng Việt ở những đoạn cần giải thích.
- Tốc độ và độ chính xác là ưu tiên số 1.

## API Response

Mọi API phải trả về một chuẩn JSON chung và luôn có HTTP status code ở cuối response:

```php
return response()->json([
    'status'     => false,
    'message'    => 'Hiện tại tôi không thể xử lí yêu cầu của bạn',
    'error_code' => 500,
    'data'       => '',
], 500);
```

Ưu tiên dùng helper `app/Helpers/ApiResponse.php`:

```php
ApiResponse::responseJson(false, 'Hiện tại tôi không thể xử lí yêu cầu của bạn', 500, '', 500);
```

Luôn đọc Resource đầy đủ và trả dữ liệu bằng Resource. Nếu chưa có Resource phù hợp thì tạo Resource.

## Controller Rules

- Dùng `Octane::concurrently()` nếu function có thể áp dụng.
- Validation phải nằm trong file Request/FormRequest riêng, không viết `$request->validate([...])` trong Controller nữa.
- Controller nhận FormRequest đã validate và lấy dữ liệu bằng `$request->validated()` ngay đầu function.
- Viết business logic trực tiếp trong function của Controller: Eloquent queries, tính toán, gửi mail...
- Dùng Eloquent Model trực tiếp hoặc query builder.
- Luôn dùng `with()` khi có relationship để tránh N+1 query.
- Xử lý đầy đủ các trường hợp có thể xảy ra.
- Cân nhắc transaction khi thật sự cần để xử lý race condition, double spending, overselling, negative quantity, sai lệch làm tròn số, price manipulation, cache stampede, distributed issues.
- Chỉ dùng một `try-catch` lớn từ đầu đến cuối function cho logic chính.
- Trong `catch`, chỉ trả lỗi 500, không dùng `if` trong `catch`; các lỗi khác xử lý ở phía trên.

Ví dụ pattern:

```php
public function sendMessage(RegisterRequest $request)
{
    try {
        $validatedData = $request->validated();

        $region = Region::create($validatedData);

        return response()->json([
            'status'     => true,
            'message'    => 'Gửi tin nhắn thành công',
            'error_code' => 201,
            'data'       => $region,
        ], 201);
    } catch (\Exception $e) {
        return response()->json([
            'status'     => false,
            'message'    => 'Server Error' . $e->getMessage(),
            'error_code' => 500,
            'data'       => '',
        ], 500);
    }
}
```

## Feature Flow

Khi làm API mới hoặc chỉnh API, kiểm tra đủ:

- Model
- Request/FormRequest chứa toàn bộ rule validation
- Resource
- Service/Repository nếu có
- Policy nếu có
- Route
- Controller method
- Namespace/class imports đúng và đủ

Quy trình:

1. Quét codebase để hiểu bảng database/model liên quan.
2. Thêm route vào `routes/api.php`.
3. Tạo/cập nhật Request/FormRequest để validate đầu vào.
4. Trong Controller: nhận FormRequest, lấy `$request->validated()`, xử lý nghiệp vụ/database, tối ưu query, trả JSON kèm status code.
5. Nếu lưu/sửa/xóa ảnh thì dùng `ImageHelper`.
6. Nếu là hành động admin thì log bằng model `AdminLog.php` và helper `AdminActivityLogger`.
7. Sau khi làm xong, báo cáo đã sửa file nào.

## Database And Models

- Khi tạo/chỉnh model, quét lại model liên quan và set relationship chặt chẽ.
- Luôn tránh N+1 query.
- Khi đổi DB, cân nhắc `nullable`/`default` để migration không lỗi.
- Với index/FK, đặt constraint/index rõ ràng, đảm bảo rollback được, tránh lock nặng không cần thiết.

## Docker And Octane

Khi cần reload Octane sau khi sửa backend, chạy:

```bash
docker exec laravel_app php artisan octane:reload
```

Lệnh Docker thì tự chạy, không cần hỏi lại.
