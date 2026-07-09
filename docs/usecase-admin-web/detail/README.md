# Use Case Chi Tiết Từng Chức Năng - Admin Web

Các sơ đồ này dùng style: actor bên trái, use case tổng ở giữa, các thao tác chi tiết nối bằng `<<extend>>` giống mẫu báo cáo.

| Hình | Chức năng | File ảnh PNG | File PlantUML |
|---:|---|---|---|
| 1 | Xác thực admin | `UC_01_XacThuc_Admin.png` | `01-xac-thuc-admin.puml` |
| 2 | Quản lí khu vực & tòa nhà | `UC_02_KhuVuc_ToaNha.png` | `02-khu-vuc-toa-nha.puml` |
| 3 | Quản lí loại phòng & mẫu tài sản | `UC_03_LoaiPhong_MauTaiSan.png` | `03-loai-phong-mau-tai-san.puml` |
| 4 | Quản lí phòng | `UC_04_Phong.png` | `04-phong.puml` |
| 5 | Quản lí dịch vụ | `UC_05_DichVu.png` | `05-dich-vu.puml` |
| 6 | Quản lí đồng hồ điện/nước | `UC_06_DongHo_DienNuoc.png` | `06-dong-ho-dien-nuoc.puml` |
| 7 | Chốt điện nước & sinh hóa đơn hàng loạt | `UC_07_ChotChiSo_HoaDonHangLoat.png` | `07-chot-chi-so-hoa-don-hang-loat.puml` |
| 8 | Quản lí khách thuê | `UC_08_KhachThue.png` | `08-khach-thue.puml` |
| 9 | Quản lí phương tiện | `UC_09_PhuongTien.png` | `09-phuong-tien.puml` |
| 10 | Quản lí hợp đồng | `UC_10_HopDong.png` | `10-hop-dong.puml` |
| 11 | Quản lí chuyển phòng & lịch sử cọc | `UC_11_ChuyenPhong_LichSuCoc.png` | `11-chuyen-phong-lich-su-coc.puml` |
| 12 | Quản lí hóa đơn & thanh toán | `UC_12_HoaDon_ThanhToan.png` | `12-hoa-don-thanh-toan.puml` |
| 13 | Quản lí công nợ | `UC_13_CongNo.png` | `13-cong-no.puml` |
| 14 | Quản lí phiếu chi | `UC_14_PhieuChi.png` | `14-phieu-chi.puml` |
| 15 | Quản lí danh mục phiếu chi | `UC_15_DanhMucPhieuChi.png` | `15-danh-muc-phieu-chi.puml` |
| 16 | Xem báo cáo lợi nhuận | `UC_16_BaoCaoLoiNhuan.png` | `16-bao-cao-loi-nhuan.puml` |
| 17 | Quản lí bảo trì | `UC_17_BaoTri.png` | `17-bao-tri.puml` |
| 18 | Quản lí thông báo | `UC_18_ThongBao.png` | `18-thong-bao.puml` |
| 19 | Quản lí chat | `UC_19_Chat.png` | `19-chat.puml` |
| 20 | Quản lí tài khoản admin | `UC_20_TaiKhoanAdmin.png` | `20-tai-khoan-admin.puml` |
| 21 | Xem nhật ký admin | `UC_21_NhatKyAdmin.png` | `21-nhat-ky-admin.puml` |
| 22 | Quản lí cài đặt | `UC_22_CaiDat.png` | `22-cai-dat.puml` |
| 23 | Xem dashboard tổng quan | `UC_23_Dashboard.png` | `23-dashboard.puml` |

## Lệnh render lại

```bash
docker run --rm --user "$(id -u):$(id -g)" -v "$PWD/docs/usecase-admin-web/detail:/data" -w /data plantuml/plantuml -tpng *.puml
```
