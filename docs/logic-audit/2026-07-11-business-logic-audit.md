# Business Logic Audit - StayHub Backend

> Ngày audit: 2026-07-11  
> Phạm vi: `BE_StayHub` Laravel backend, tập trung các luồng phòng/tòa nhà/hợp đồng/khách thuê/chuyển phòng/hóa đơn/công tơ/dịch vụ.

## Nguyên tắc vá triệt để

- Tạo một lớp guard nghiệp vụ dùng chung thay vì vá rải rác từng controller.
- Các guard quan trọng phải nằm ở điểm ghi dữ liệu cuối cùng trong transaction, không chỉ nằm ở FormRequest/UI.
- Các hành động thay đổi trạng thái cha như tòa nhà/phòng/tenant/vehicle phải kiểm tra dữ liệu con đang hoạt động.
- Các job/command/webhook phải dùng cùng rule với API, tránh API chặn nhưng job vẫn lọt.
- Khi có dữ liệu lịch sử, chỉ chặn hành động mới; không xóa/sửa ngầm dữ liệu cũ nếu không cần.

## Issue 1 - Cập nhật phòng có thể đưa phòng đang có hợp đồng sang bảo trì/ngưng

- Bằng chứng: `BE_StayHub/app/Http/Controllers/Admin/RoomController.php:240` cập nhật trực tiếp `status` từ payload trong `update()`.
- Trong khi đó endpoint riêng `updateStatus()` có guard tại `BE_StayHub/app/Http/Controllers/Admin/RoomController.php:386` chặn chuyển sang maintenance/inactive khi `current_occupants > 0` hoặc có `Contract::STATUS_ACTIVE`.
- Root cause: cùng một field `rooms.status` có 2 đường ghi, chỉ một đường có rule nghiệp vụ.
- Impact: admin có thể gọi `PUT /rooms/{id}` kèm `status=2/3` để né rule, làm phòng có người/hợp đồng active nhưng trạng thái bảo trì/ngưng; kéo theo available room, invoice, meter, dashboard lệch.
- Fix triệt để: tách helper `RoomStateGuard::assertCanTransition(Room $room, int $nextStatus)` và gọi ở cả `RoomController@update` lẫn `RoomController@updateStatus`; dùng `lockForUpdate()` khi đọc phòng trong update; thêm test cho cả 2 endpoint.

## Issue 2 - Cập nhật tòa nhà có thể đưa tòa đang có hợp đồng/phòng có người sang bảo trì/ngưng

- Bằng chứng: `BE_StayHub/app/Http/Controllers/Admin/BuildingController.php:198` đổi `buildings.status` trực tiếp; `BuildingController@update` cũng cho fill `status` qua `payload()` tại `BE_StayHub/app/Http/Controllers/Admin/BuildingController.php:440`.
- Không có guard kiểm tra phòng/hợp đồng/khách đang ở trước khi chuyển tòa nhà sang `Building::STATUS_INACTIVE` hoặc `Building::STATUS_MAINTENANCE`.
- Root cause: trạng thái tòa nhà được xem như field CRUD, chưa được coi là state machine có phụ thuộc con.
- Impact: `ContractController@store` đã chặn lập hợp đồng nếu building không active (`BE_StayHub/app/Http/Controllers/Admin/ContractController.php:1543`), nhưng các hợp đồng active hiện hữu vẫn tồn tại trong tòa inactive/maintenance; nghiệp vụ và báo cáo lệch.
- Fix triệt để: tạo `BuildingStateGuard::assertCanTransition(Building $building, int $nextStatus)` chặn inactive/maintenance khi có phòng đang có `current_occupants > 0`, hợp đồng `PENDING_SIGN/ACTIVE`, hoặc room movement `PENDING/BLOCKED`; gọi ở `update()` và `updateStatus()`; nếu muốn bảo trì một phần thì dùng trạng thái phòng, không set cả tòa.

## Issue 3 - Luồng chuyển phòng không kiểm tra trạng thái tòa nhà đích lúc lên lịch và lúc thực thi

- Bằng chứng lên lịch: `RoomController::destinationValidationMessage()` chỉ kiểm tra `$toRoom->status` tại `BE_StayHub/app/Http/Controllers/Admin/RoomController.php:594`, không kiểm tra `$toRoom->building->status`.
- Bằng chứng thực thi: `ExecuteScheduledRoomTransfers::destinationValidationMessage()` cũng chỉ kiểm tra `$toRoom->status` tại `BE_StayHub/app/Console/Commands/ExecuteScheduledRoomTransfers.php:255`, không kiểm tra building status.
- Root cause: rule phòng hoạt động được áp dụng, nhưng rule tòa nhà hoạt động chỉ nằm ở tạo/gia hạn hợp đồng thủ công (`ContractController::assertRoomCanBeUsed()`), không tái dùng cho chuyển phòng.
- Impact: nếu tòa nhà bị set bảo trì/ngưng sau khi phòng còn active, vẫn có thể lên lịch/chạy chuyển phòng vào đó, command còn tự tạo hợp đồng mới tại `BE_StayHub/app/Console/Commands/ExecuteScheduledRoomTransfers.php:290`.
- Fix triệt để: dùng chung `RoomAvailabilityGuard::assertRoomRentable($room, $admin = null)` trong `ContractController`, `RoomController`, `ExecuteScheduledRoomTransfers`; guard phải check `room.status=ACTIVE`, `building.status=ACTIVE`, access scope, capacity, gender.

## Issue 4 - Cập nhật tenant có thể đổi trạng thái tenant đang ở thành ngừng thuê

- Bằng chứng: `TenantController@updateStatus` forceFill status tại `BE_StayHub/app/Http/Controllers/Admin/TenantController.php:228` không kiểm tra hợp đồng đang active.
- `TenantController@update` cũng cho cập nhật `status` vì `UpdateRequest` cho `status` và `payload()` nhận field này tại `BE_StayHub/app/Http/Controllers/Admin/TenantController.php:330`.
- Root cause: tenant status chưa ràng buộc với `contract_tenants.is_staying` và `contracts.status`.
- Impact: tenant đang ở vẫn bị chuyển sang `STATUS_STOPPED_RENTING`; sau đó `ContractController::assertTenantPayloads()` sẽ chặn cập nhật/gia hạn do tenant inactive, nhưng dữ liệu hợp đồng hiện hữu vẫn active, app tenant có thể bị lệch trạng thái.
- Fix triệt để: `TenantStateGuard::assertCanStopRenting()` chặn stop nếu tenant còn `contractTenants` đang `is_staying=true`, `leave_date=null`, contract `PENDING_SIGN/ACTIVE`; chỉ cho stop sau thanh lý/chuyển đi. Gọi guard trong cả `update()` và `updateStatus()`.

## Issue 5 - Cập nhật giới tính tenant đang ở có thể phá gender policy của tòa nhà/phòng hiện tại

- Bằng chứng: `TenantController@update` chỉ check gender với `$tenantModel->building_id` tại `BE_StayHub/app/Http/Controllers/Admin/TenantController.php:175`.
- Nếu tenant đang ở hợp đồng active trong tòa A nhưng `tenant.building_id` null/sai/cũ, rule không đối chiếu với room/building thực tế của active contract.
- Root cause: nguồn sự thật về phòng hiện tại nằm ở `contract_tenants` + `contracts`, không phải luôn ở `tenants.building_id`.
- Impact: đổi giới tính tenant đang ở có thể làm tenant không còn phù hợp policy tòa/phòng hiện tại; các lần cập nhật hợp đồng sau mới bị chặn.
- Fix triệt để: khi update gender, lấy active contract room/building hiện tại từ `contractTenants` và check `Building::allowsTenantGender()` trên building thực tế; nếu không có active contract thì mới fallback `tenant.building_id`.

## Issue 6 - Cập nhật/xóa active vehicle có thể làm hợp đồng đang tính phí xe lệch

- Bằng chứng: `VehicleController@updateStatus` set `is_active` trực tiếp tại `BE_StayHub/app/Http/Controllers/Admin/VehicleController.php:172`; `VehicleController@update` có thể đổi `tenant_id` tại `BE_StayHub/app/Http/Controllers/Admin/VehicleController.php:136`.
- `ContractController::assertVehiclePayloads()` chặn thêm vehicle inactive/trùng vào hợp đồng tại `BE_StayHub/app/Http/Controllers/Admin/ContractController.php:1514`, nhưng sau khi đã gắn hợp đồng thì VehicleController không kiểm tra.
- Root cause: guard chỉ đặt ở luồng hợp đồng, không đặt ở CRUD vehicle.
- Impact: xe đang active trong `contract_vehicles` của hợp đồng active có thể bị tắt hoặc chuyển chủ; hóa đơn xe và danh sách xe hợp đồng lệch.
- Fix triệt để: `VehicleStateGuard` chặn đổi owner/tắt/xóa khi vehicle còn `contractVehicles.is_active=true` trong contract `PENDING_SIGN/ACTIVE`; nếu muốn ngừng xe thì cập nhật qua hợp đồng để set `ended_at`, `billing_end_date`, `is_active=false` đồng bộ.

## Issue 7 - Ghi chỉ số công tơ cho thiết bị inactive và tháng đã lập hóa đơn vẫn có thể sửa dữ liệu

- Bằng chứng: `MeterReadingController@store` lấy meter bằng `findOrFail()` tại `BE_StayHub/app/Http/Controllers/Admin/MeterReadingController.php:398`, không check `meter.status`, `room.status`, `building.status`.
- Cùng method dùng `updateOrCreate()` tại `BE_StayHub/app/Http/Controllers/Admin/MeterReadingController.php:467`, không chặn record đã `STATUS_INVOICED`.
- Invoice đánh dấu reading invoiced tại `BE_StayHub/app/Http/Controllers/Admin/InvoiceController.php:1627`.
- Root cause: init screen lọc phòng active và meter active/inactive (`BE_StayHub/app/Http/Controllers/Admin/MeterReadingController.php:106`, `:248`), nhưng API store không enforce lại lifecycle và trạng thái đã lập hóa đơn.
- Impact: có thể sửa chỉ số sau khi đã lập hóa đơn, tạo sai số lượng/tiền trên hóa đơn đã phát hành; hoặc chốt cho đồng hồ hỏng/thay thế/ngưng không đúng nghiệp vụ.
- Fix triệt để: trong `store()`, lock meter + existing reading; chỉ cho meter `STATUS_ACTIVE` trừ trường hợp chốt chuyển phòng có cutoff hợp lệ; chặn update nếu existing reading `STATUS_INVOICED` và invoice chưa bị hủy/phát hành lại; không update `meter.initial_reading` khi đang sửa kỳ cũ, chỉ cập nhật nếu kỳ mới nhất.

## Issue 8 - Tạo công tơ cho phòng/tòa không active và rule trùng công tơ quá rộng

- Bằng chứng: `MeterController@store` chỉ check quyền phòng tại `BE_StayHub/app/Http/Controllers/Admin/MeterController.php:99`, không check `room.status`/`building.status`/`service.is_active`.
- Rule trùng công tơ dùng `where('status', '!=', STATUS_REPLACED)` tại `BE_StayHub/app/Http/Controllers/Admin/MeterController.php:109`, nên meter inactive/broken cũng chặn tạo meter mới.
- Root cause: lifecycle meter chưa phân biệt “đang sử dụng” với “có lịch sử”.
- Impact: phòng bảo trì/ngưng vẫn tạo đồng hồ mới; phòng có đồng hồ hỏng/ngưng lại không tạo được đồng hồ thay thế nếu không set replaced đúng cách.
- Fix triệt để: khi tạo meter mới, yêu cầu room/building active và service active/metered; chỉ xem `STATUS_ACTIVE` là đồng hồ đang chiếm slot; khi thay đồng hồ thì lock old meter cùng room/service và set old `STATUS_REPLACED` trong transaction.

## Issue 9 - Cập nhật giá dịch vụ phòng cho phòng/tòa không active vẫn được lên lịch

- Bằng chứng: `RoomServicePriceController@update` load room tại `BE_StayHub/app/Http/Controllers/Admin/RoomServicePriceController.php:105` và chỉ check quyền tại `:110`; không check `room.status` hoặc `building.status`.
- `roomQuery()` hiển thị phòng có roomServices bất kỳ tại `BE_StayHub/app/Http/Controllers/Admin/RoomServicePriceController.php:181`, không lọc room/building active.
- Root cause: service-price scheduling chỉ check lifecycle `RoomService` và contract, chưa check lifecycle phòng/tòa.
- Impact: phòng đang bảo trì/ngưng hoặc tòa inactive vẫn có lịch thay đổi giá tương lai, tạo notification/giá treo không nên áp dụng.
- Fix triệt để: trước scheduling, gọi guard room/building active nếu không có active/reserved contract; nếu có contract thì check contract thuộc trạng thái `PENDING_SIGN/ACTIVE` và chưa kết thúc trước kỳ. Với phòng inactive chỉ cho xem lịch sử, không cho schedule mới.

## Issue 10 - Hóa đơn có thể lập cho contract trong phòng/tòa đang ngưng/bảo trì mà không có policy rõ

- Bằng chứng: `InvoiceController::prepareInvoiceDraft()` chỉ check contract status active/expired/liquidated tại `BE_StayHub/app/Http/Controllers/Admin/InvoiceController.php:272`; không check room/building status.
- Bulk job cũng lấy contract theo building_id và status tại `BE_StayHub/app/Jobs/BulkGenerateInvoicesJob.php:45`, không check building/room status.
- Root cause: invoice policy chưa tách “thu tiền kỳ đã sử dụng” với “phát sinh mới trong tài sản không hoạt động”.
- Impact: có thể lập hóa đơn định kỳ cho phòng/tòa đã bị ngưng/bảo trì do trạng thái bị set sau khi hợp đồng tồn tại; có thể đúng với hóa đơn chốt/thanh lý, nhưng sai với hóa đơn tháng tương lai.
- Fix triệt để: tạo `InvoiceIssuanceGuard`: cho phép invoice nếu contract overlap kỳ và có người/contract phát sinh trong kỳ; nếu room/building không active thì chỉ cho kỳ <= actual_end/end/cutoff hoặc invoice chốt, không cho kỳ tương lai; bulk job dùng cùng guard và log skip reason.

## Issue 11 - Tạo tenant mới trong tòa inactive/maintenance vẫn được

- Bằng chứng: `RegisterRequest` chỉ `exists:buildings,id` tại `BE_StayHub/app/Http/Requests/Admin/Tenant/RegisterRequest.php:22`; `TenantController@store` check gender policy tại `BE_StayHub/app/Http/Controllers/Admin/TenantController.php:79` nhưng không check building status.
- Root cause: tenant được xem như hồ sơ hành chính, nhưng hệ thống lại dùng `tenant.status=STATUS_RENTING` ngay khi tạo.
- Impact: tạo tenant “đang thuê” trong tòa đã ngưng/bảo trì; sau đó danh sách chọn tenant/hợp đồng bị lẫn dữ liệu không sẵn sàng.
- Fix triệt để: nếu tenant tạo với `STATUS_RENTING`, building phải active; nếu cần lưu hồ sơ trước thì cho tạo với trạng thái non-renting/pending riêng hoặc status stopped nhưng không cho vào hợp đồng.

## Issue 12 - Trạng thái building inactive/maintenance không cascade/block room active nên available/options có thể hiển thị lệch

- Bằng chứng: `ContractController@availableRooms()` lọc `rooms.status = ACTIVE` tại `BE_StayHub/app/Http/Controllers/Admin/ContractController.php:99`, không join/check building status trước khi trả danh sách phòng.
- Store contract vẫn chặn building status qua `assertRoomCanBeUsed()` tại `BE_StayHub/app/Http/Controllers/Admin/ContractController.php:1543`, nên UI có thể hiện phòng nhưng submit bị lỗi.
- Root cause: query option và command validation không dùng cùng guard.
- Impact: trải nghiệm lệch: danh sách phòng khả dụng có thể chứa phòng thuộc tòa bảo trì/ngưng; user chọn xong mới bị chặn hoặc một luồng khác không bị chặn.
- Fix triệt để: tất cả option/list “có thể thuê/chuyển/chốt” phải dùng scope chung `Room::rentable()` hoặc guard query `whereHas('building', status ACTIVE)`.

## Plan vá an toàn

### Phase 1 - Tạo guard và test cho state machine

- Tạo `app/Support/BusinessRules/RoomAvailabilityGuard.php` hoặc service tương đương: `assertBuildingActive`, `assertRoomRentable`, `assertRoomCanBeDisabled`, `assertBuildingCanBeDisabled`, `assertTenantCanStopRenting`, `assertVehicleCanMutate`, `assertMeterCanRecord`.
- Viết test trước trong các file hiện có: `tests/Feature/Admin/ContractControllerTest.php`, `RoomMovementControllerTest.php`, `MeterReadingTest.php`, `RoomServicePriceTest.php`, `TenantGenderPolicyTest.php`, `MeterDeviceTest.php`.
- Mục tiêu test: mỗi issue có ít nhất một test đỏ mô phỏng đúng điều kiện lệch logic.

### Phase 2 - Vá đường ghi trạng thái phòng/tòa/tenant/vehicle

- `RoomController@update` và `updateStatus`: lock room, gọi guard trước khi save `status`.
- `BuildingController@update` và `updateStatus`: lock building, gọi guard trước khi save `status`.
- `TenantController@update` và `updateStatus`: chặn stop/gender update nếu active contract hiện tại không phù hợp.
- `VehicleController@update` và `updateStatus`: chặn đổi owner/tắt xe đang gắn contract active/reserved.

### Phase 3 - Đồng bộ guard ở luồng tạo/gia hạn/chuyển phòng

- `ContractController`: thay `assertRoomCanBeUsed()` bằng guard chung nhưng giữ message hiện tại để không gãy frontend.
- `RoomController` và `ExecuteScheduledRoomTransfers`: dùng cùng check room/building active ở destination validation.
- `availableRooms`, tenant room options, transfer room options: lọc building active ngay ở query option.

### Phase 4 - Vá công tơ/chỉ số/hóa đơn/dịch vụ

- `MeterController@store`: check room/building/service active; sửa rule “đồng hồ đang chiếm slot” chỉ tính active meter.
- `MeterReadingController@store`: lock meter + reading, chặn meter invalid và reading invoiced; bảo toàn file ảnh nếu validation fail.
- `RoomServicePriceController@update/index/show`: chặn schedule cho room/building inactive, chỉ cho xem lịch sử.
- `InvoiceController` + `BulkGenerateInvoicesJob`: thêm `InvoiceIssuanceGuard` để phân biệt hóa đơn kỳ đã dùng/chốt với hóa đơn phát sinh tương lai; bulk job skip thay vì tạo sai.

### Phase 5 - Ràng buộc dữ liệu và hồi quy

- Thêm migration index/unique nếu cần: active meter unique theo `(room_id, service_id, status active)` nếu MySQL hỗ trợ bằng generated column hoặc xử lý bằng lock trong app.
- Thêm command audit dữ liệu hiện có: liệt kê active contracts trong inactive rooms/buildings, inactive tenants in active contracts, invoiced readings edited risk, active vehicle mismatch.
- Chạy targeted tests trước, sau đó `php artisan test` trong `BE_StayHub`.

## Thứ tự ưu tiên sửa

1. Issue 1, 2, 3: state phòng/tòa và chuyển phòng vì đây là nguồn lệch lớn nhất.
2. Issue 4, 5, 6: tenant/vehicle vì ảnh hưởng trực tiếp hợp đồng active.
3. Issue 7, 8: công tơ/chỉ số vì ảnh hưởng tiền hóa đơn.
4. Issue 9, 10: giá dịch vụ/hóa đơn kỳ tương lai.
5. Issue 11, 12: option/list và tạo hồ sơ tenant để tránh UX lệch.
