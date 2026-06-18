@AGENTS.md
Dự án này là Laravel backend phiên bản 13 sử dụng kiến trúc mvc. Cache dùng Redis, Database chính là mysql,Auth là sanctumc HttpOnly Cookie,  viết đường dẫn api chuẩn resful api ,realtime dùng Soketi và chạy trên nền Octane. Mọi logic phức tạp phải đẩy vào Queues, không chạy đồng bộ. Khi tạo API luôn dùng API Resources
- Tôi đã cấp full quyền cho bạn. Bạn PHẢI TỰ DÙNG các công cụ (tools) của mình để đọc file, chạy lệnh shell (`cat`, `ls`, `grep`) để tự mò đường dẫn và tự đọc dữ liệu bạn cần.
- project của tôi nằm trong docker có những lệnh bạn cần phải vô docker bạn cứ việc vô tôi cho bạn toàn quyền trên project của tôi
- Làm xong việc thì báo cáo, không được hỏi những câu dư thừa.
- hãy làm thật chính xác và chạy đc không để tôi phải sửa nhiều lần , khi sửa 1 file nào đó nhớ xem lại những cái liên quan , ví dụ: sửa chức năng A thì phải coi lại tất cả những gì liên quan đến chức năng đó và sửa theo
# Project Flow & Rules: 
- viết code chuẩn PSR-12
## 1. Quy trình viết Code (Flow)
- Mọi API trả về phải bọc trong một chuẩn JSON chung: `{ "status": ..., "message" , "error_code" , "data", http_code}`.
ví dụ:
return response()->json([
                'status'     => false,
                'messages'   => "Hiện tại tôi không thể xử lí yêu cầu của bạn",
                'error_code' => 500,
                'data'=>""
            ], 500);
            đúng thứ tự này cho tôi nhé và luôn luôn phải có http status ở sau cùng 
            và nhớ đọc full resource để trả về dữ liệu bằng resource nhé
            tôi có cái helper responseJson trong app/Helpers/ApiResponse.php
            ví dụ: ApiResponse::responseJson(false, "Hiện tại tôi không thể xử lí yêu cầu của bạn", 500, "", 500);
            sài cái này cho tôi nhé
## 2. Quy chuẩn Code trong Controller
- **Bất đồng bộ Octane Swoolee** Sử dụng Octane::concurrently() nếu function đó có thể áp dụng 
- **Validation:** Tất cả validation phải nằm trong file Request/FormRequest riêng. Controller không dùng `$request->validate([...])` nữa, mà nhận FormRequest và lấy dữ liệu bằng `$request->validated()` ngay đầu function.
- **Business Logic:** Viết trực tiếp logic xử lý (Eloquent queries, tính toán, gửi mail...) bên trong function của Controller.
- **Database:** Sử dụng Eloquent Model trực tiếp hoặc querybuilder  và  sử dụng with để tránh N+1 query  bắt buộc cái nào cũng vậy tránh N+1 và tối ưu hóa query nhất cho tôi tối ưu nhất nhất cho tôi
- ** luôn luôn xử lí tất cả các trường hợp không thiếu 1 trường hợp nào có thể xảy ra
- ** xử lí đầy đủ tất cả trường hợp khác ví dụ như: stransaction, race condition , Double Spending, Distributed chỉ khi nào thật sự cần thì mới sài Transactions (Giao dịch phân tán bị lỗi),Cache Stampede,Overselling,Sai lệch làm tròn số, Price Manipulation,Negative Quantity,N+1 Query
- ** khi code xong: nhớ comment lại bằng tiếng việt
## 3. Quy trình thực hiện 
Mỗi khi tôi yêu cầu một tính năng, AI hãy làm theo các bước sau:
1. **Đọc Context:** Quét `@Codebase` để hiểu các bảng Database liên quan.
2. **Viết Route:** Thêm route vào `api.php` 
3. **Viết Request/FormRequest:** Validate dữ liệu đầu vào trong file Request riêng.
4. **Viết Controller:** - Bước 1: Nhận FormRequest và lấy `$request->validated()`.
   - Bước 2: Xử lý logic nghiệp vụ và tương tác Database.
   - Bước 3: hãy query tối ưu nhất, đạt tốc độ gần như là 100% ,không bao giờ bị N+1 Query, tránh tất cả các luồng xử lí gây ra N+1 query  
   - Bước 4: Trả về kết quả JSON kèm theo status code .
    - Nếu có lưu , sửa , xóa ảnh thì dùng ImageHelper
    -Nếu mà hành động của admin thì luôn luôn đưa vô log trong model của admin cho tôi nhé file này nè AdminLog.php sài Helper AdminActivityLogger
5. **Tối ưu:** Sử dụng `try-catch` bọc quanh logic chính để bắt lỗi và trả về thông báo lỗi thân thiện. chỉ cần 1 try cath  từ đầu và cuối function
ví dụ  public function sendMessage(RegisterRequest $request)
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
                'data'       => "",
            ], 500);
        } 
        không đc sài if trong catch chỉ trả ra như tôi thôi lỗi 500 thôi còn mấy lỗi khác để ở trên hết
    } không cần nhiều trycatch , chỉ sử dụng 1 try catch lớn 
    ## 4. Chỉ dẫn riêng
- **Code Style:** Viết code ngắn gọn, sử dụng `Collection methods` của Laravel để xử lý mảng dữ liệu.
- **Relationship:** Sử dụng `with()` để tránh N+1 query nhưng vẫn viết trực tiếp trong Controller.
- **rules important:** Code làm sao để không bao giờ bị N+1 Query nhé

   ##5. phần model 
- khi tạo cần phải quét lại các model liên quan và set mối quan hệ thật chặt chẽ với nhau
tôi đang sài octane tên là bạn nhớ chạy lệnh php artisan octane:reload trong docker cụ thể là - docker exec laravel_app php artisan octane:reload lại mới lưu đc code nha bạn với lại bạn hãy làm tối ưu nhất tốc độ nhất nhé thứ tôi cần là tốc độ và sự chính xác 
làm gì cũng phải nhớ tốc độ là số 1 
với lại nhớ 1 điều nữa là khi bạn code đừng hỏi tôi có chấp nhận hay không bạn cứ accept all cho tôi và chỉ cần cho tôi biết bạn đã sửa chỗ nào là đc
tôi cho bạn full quyền truy cập và k cần hỏi tôi cái gì hết
RULE AI (Laravel)

Cuối cùng:
B. Làm API mới / chỉnh API
Khi tạo API mới phải kiểm tra đủ:
Model, Request (FormRequest), Resource, Service/Repository (nếu có), Policy (nếu có), Route, Controller method
use ... import đúng & đủ namespace/class.
Khi chỉnh route/API:
- nhớ comment lại bằng tiếng việt
C. Quy tắc an toàn khi đổi DB (BẮT BUỘC)
Luôn cân nhắc nullable / default để migrate không lỗi.
Với index/FK:
Đặt constraint/index rõ ràng, đảm bảo rollback được, tránh thay đổi gây lock nặng không cần thiết.
D. Chuẩn hóa response API (BẮT BUỘC)
Tất cả API trả về format thống nhất:
Quy tắc “Index vs Detail” (bắt buộc để tối ưu payload)
API danh sách / index (list, paginate):
API chi tiết / detail (show):

+ luôn luôn phải check các resource trước và trả dữ liệu bằng resource nếu không có resource thì tạo resource 
+ khi nào cảm thấy cần dùng transaction thì cứ dùng , nhưng mà phải thấy hợp lí thì mới đc dùng nhé
hễ là lệnh docker thì cứ thế mà làm, không cần hỏi
cho bạn toàn quyền
+ đặc biệt là kh được phá tất cả các file của docker và dự án của tôi , tôi kêu làm chức năng nào thì chỉ làm đúng những cái liên quan chức năng đó thôi khong đụng đến những cái khác nhé
+ sau khi làm xong thì phải báo cáo lại cho tôi đã sửa ở những file nào
