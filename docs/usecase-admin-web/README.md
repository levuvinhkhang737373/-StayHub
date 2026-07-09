# Use Case Admin Web - Tách Theo Chức Năng

Các sơ đồ dưới đây chỉ phục vụ phạm vi **admin web**. Không bao gồm Flutter mobile và không bao gồm actor/use case tenant web.

## Danh sách hình vẽ

| STT | Nhóm chức năng | File ảnh PNG | File PlantUML |
|---:|---|---|---|
| 1 | Xác thực & tài khoản cá nhân | `StayHub_Admin_Web_01_XacThuc_TaiKhoan.png` | `01-xac-thuc-tai-khoan.puml` |
| 2 | Cơ sở lưu trú | `StayHub_Admin_Web_02_CoSoLuuTru.png` | `02-co-so-luu-tru.puml` |
| 3 | Khách thuê & hợp đồng | `StayHub_Admin_Web_03_KhachThue_HopDong.png` | `03-khach-thue-hop-dong.puml` |
| 4 | Dịch vụ & điện nước | `StayHub_Admin_Web_04_DichVu_DienNuoc.png` | `04-dich-vu-dien-nuoc.puml` |
| 5 | Tài chính & báo cáo | `StayHub_Admin_Web_05_TaiChinh_BaoCao.png` | `05-tai-chinh-bao-cao.puml` |
| 6 | Vận hành | `StayHub_Admin_Web_06_VanHanh.png` | `06-van-hanh.puml` |
| 7 | Hệ thống | `StayHub_Admin_Web_07_HeThong.png` | `07-he-thong.puml` |

## Lệnh render lại

Chạy từ root project:

```bash
docker run --rm --user "$(id -u):$(id -g)" -v "$PWD/docs/usecase-admin-web:/data" -w /data plantuml/plantuml -tpng *.puml
```
