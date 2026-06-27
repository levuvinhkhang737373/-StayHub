# Plan AI Camera Fire Safety StayHub

## Kiến trúc chốt
- Camera thật hoặc iPhone IP camera phát LAN stream, ví dụ `http://192.168.1.5:8081`.
- Superadmin thêm camera trong `/admin/fire-safety`, gắn camera vào `building_id` cụ thể.
- Backend luôn suy ra quản lý từ `buildings.manager_admin_id`, không tin FE tự gửi admin nhận cảnh báo.
- Laravel gọi `ai-service` FastAPI; FastAPI chỉ xử lý frame/stream và gọi OmniRoute GPT vision qua `OMNIROUTE_BASE_URL`.
- Khi AI phát hiện lửa/khói/hút thuốc vượt ngưỡng, Laravel lưu alert, broadcast notification type `4`, gửi Discord kèm snapshot.
- Theo yêu cầu hiện tại: không tách Laravel Service/Job/Command/Scheduler; logic API nằm trong Controller + Request + Resource.

## Workflow demo iPhone
1. iPhone và laptop cùng mạng; tốt nhất bật hotspot iPhone rồi laptop kết nối vào.
2. Mở app IP Camera trên iPhone, cấp quyền Camera và Local Network.
3. Nếu app đang bật Basic Auth, nhập username/password vào form camera hoặc tắt auth trong app.
4. Trên web admin vào `/admin/fire-safety`, thêm camera:
   - Tòa nhà: tòa muốn demo.
   - Loại nguồn: `MJPEG/HTTP stream`.
   - URL: `http://192.168.1.5:8081`.
   - Frame: `3`, giây/lần: `2`, cooldown: `60`.
5. Bấm `Test cam`; nếu thấy preview frame thì luồng iPhone → laptop → Docker AI service đã OK.
6. Bấm `Bật giám sát`; hệ thống sẽ gọi AI thật theo chu kỳ từ frame iPhone.
7. Đưa lửa/khói/hành vi hút thuốc vào khung hình iPhone; nếu AI thấy nguy cơ vượt ngưỡng, Laravel lưu alert thật, realtime notification cho admin và gửi Discord kèm ảnh.
8. Nếu chưa có cảnh báo, kiểm tra lại frame preview, ánh sáng, góc camera và đưa lửa/khói rõ hơn vào khung hình rồi bấm `Test AI` hoặc giữ `Bật giám sát`.

## Checklist chống lỗi lúc lên trường
- Trước khi demo 5 phút, mở app IP Camera trên iPhone và giữ màn hình không khóa.
- Laptop và iPhone cùng mạng; nếu Wi-Fi trường chặn LAN client-to-client thì bật hotspot iPhone và cho laptop kết nối hotspot đó.
- Trong trang `/admin/fire-safety`, bấm `Test cam` trước; nếu báo `401 Basic Auth`, nhập đúng username/password hoặc tắt auth trong app.
- Khi `Test cam` có ảnh preview, bấm `Bật giám sát thật`, sau đó đưa lửa/khói vào camera để AI phân tích thật.
- Đây là luồng test thật: hệ thống chỉ tạo alert khi AI phân tích frame thật và phát hiện nguy cơ vượt ngưỡng.

## Env cần có
- Trong `BE_StayHub/.env`:
  - `OMNIROUTE_API_KEY`
  - `OMNIROUTE_BASE_URL=http://host.docker.internal:20128/v1`
  - `FIRE_VISION_MODEL=kc/openai/gpt-4o`
  - `FIRE_FRAME_COUNT=3`
  - `FIRE_ANALYSIS_WINDOW_SECONDS=2`
  - `FIRE_CONFIDENCE_THRESHOLD=0.7`
  - `FIRE_ALERT_COOLDOWN_SECONDS=60`
  - `FIRE_DISCORD_WEBHOOK_URL`
- `docker-compose.yml` cho `ai-service` đọc `BE_StayHub/.env` và có `host.docker.internal` để gọi OmniRoute trên laptop.

## Phân quyền
- Superadmin: tạo/sửa/xóa camera, xem toàn bộ camera và cảnh báo.
- Quản lý tòa nhà: xem/test camera và xử lý cảnh báo thuộc tòa nhà mình quản lý.
- Camera thật sau này chỉ cần đổi URL stream; DB/API không cần đổi.
