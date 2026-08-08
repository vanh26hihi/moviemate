# MovieMate — Tóm tắt actor và use case

## Ranh giới sản phẩm

MovieMate là hệ thống của **một đơn vị vận hành chuỗi rạp, có nhiều chi nhánh**; không phải marketplace và không phải SaaS đa công ty.

## Actor

Có đúng **5 actor con người**:

1. Guest
2. Registered Customer
3. Staff
4. Manager
5. Global/System Admin

Hệ thống hỗ trợ bên ngoài, không tính là actor con người: **VNPAY, ZaloPay, payOS** và **hạ tầng gửi email**.

Guest và Registered Customer dùng chung luồng khám phá/đặt vé. Registered Customer kế thừa các thao tác công khai và có thêm hồ sơ, lịch sử cùng quyền quản lý đơn thuộc chính mình.

## 32 use case ở mức trình bày

| Mã | Use case | Actor |
|---|---|---|
| UC01 | Xem danh sách và chi tiết phim | Guest, Registered Customer |
| UC02 | Xem danh sách và chi tiết chi nhánh | Guest, Registered Customer |
| UC03 | Lọc suất chiếu theo phim, rạp và ngày | Guest, Registered Customer |
| UC04 | Chọn suất chiếu hợp lệ | Guest, Registered Customer |
| UC05 | Chọn ghế và kiểm tra quy tắc khoảng trống/ghế đôi | Guest, Registered Customer |
| UC06 | Giữ ghế có thời hạn và tạo đơn | Guest, Registered Customer |
| UC07 | Chọn đồ ăn tùy chọn theo chi nhánh | Guest, Registered Customer |
| UC08 | Thanh toán online qua provider | Guest, Registered Customer |
| UC09 | Xem vé điện tử bằng quyền sở hữu/capability | Guest, Registered Customer |
| UC10 | Đăng ký, đăng nhập và quản lý hồ sơ | Registered Customer |
| UC11 | Xem lịch sử đặt vé | Registered Customer |
| UC12 | Hủy đơn đủ điều kiện và yêu cầu gửi lại email vé | Registered Customer |
| UC13 | Bán vé tại quầy | Staff, Manager, Global/System Admin |
| UC14 | Tra cứu/giải mã QR vé không làm thay đổi trạng thái | Staff, Manager, Global/System Admin |
| UC15 | In vé cứng theo quy trình có bằng chứng | Staff, Manager, Global/System Admin |
| UC16 | Soát vé/check-in | Staff, Manager, Global/System Admin |
| UC17 | Quản lý phòng trong chi nhánh | Manager, Global/System Admin |
| UC18 | Quản lý phiên bản sơ đồ và trạng thái ghế | Manager, Global/System Admin |
| UC19 | Xem/áp dụng mẫu sơ đồ; quản lý vòng đời mẫu theo quyền | Manager, Global/System Admin |
| UC20 | Quản lý suất chiếu | Manager, Global/System Admin |
| UC21 | Quản lý bảng giá tập trung | Manager, Global/System Admin |
| UC22 | Quản lý giờ hoạt động và cleaning buffer | Manager, Global/System Admin |
| UC23 | Quản lý đồ ăn tại chi nhánh | Manager, Global/System Admin |
| UC24 | Xem đơn đặt vé và thanh toán trong phạm vi | Manager, Global/System Admin |
| UC25 | Đối soát và xử lý payment review | Manager, Global/System Admin |
| UC26 | Cho phép lần thử in bổ sung | Manager, Global/System Admin |
| UC27 | Xem dashboard/báo cáo theo chi nhánh | Manager, Global/System Admin |
| UC28 | Quản lý chi nhánh toàn chuỗi | Global/System Admin |
| UC29 | Quản lý danh mục và lifecycle phim/thể loại theo quyền | Manager, Global/System Admin |
| UC30 | Xem người dùng/phân công chi nhánh; quản lý vai trò toàn cục theo quyền | Manager, Global/System Admin |
| UC31 | Xem nhật ký hoạt động và vận hành bảo mật | Global/System Admin |
| UC32 | Xem báo cáo hợp nhất toàn chuỗi | Global/System Admin |

**Số dùng trên slide và sơ đồ use case: 5 actor con người, 32 use case trình bày.** Con số này nhóm các endpoint kỹ thuật thành mục tiêu người dùng; không đếm từng route.

Lưu ý phạm vi quyền: Manager có thể xem/áp dụng layout template và vận hành phân công chi nhánh theo các permission được cấp, nhưng chỉ Global/System Admin quản lý vòng đời template và vai trò toàn cục. Catalog phim/thể loại là dữ liệu dùng chung toàn chuỗi; Manager hiện có các permission nội dung được liệt kê trong RBAC, không phải quyền sở hữu một phim theo chi nhánh.
