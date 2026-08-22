# MovieMate — Tóm tắt actor và use case cuối cùng

## Ranh giới sản phẩm

MovieMate thuộc **một đơn vị vận hành chuỗi rạp có nhiều chi nhánh**. Hệ thống không phải marketplace, cinema aggregator hoặc SaaS đa công ty.

## Năm actor con người

1. Guest
2. Customer
3. Staff
4. Manager
5. Global Admin

VNPAY, ZaloPay, PayOS và hạ tầng email là external systems, không phải actor con người.

## 32 use case ở mức trình bày

| Mã | Use case | Actor |
|---|---|---|
| UC01 | Xem danh sách và chi tiết Movie | Guest, Customer |
| UC02 | Xem danh sách và chi tiết Chi nhánh | Guest, Customer |
| UC03 | Lọc Showtime theo Movie, Chi nhánh và ngày | Guest, Customer |
| UC04 | Chọn Showtime hợp lệ và xem Định dạng trình chiếu | Guest, Customer |
| UC05 | Chọn Seat theo layout và quy tắc seat-gap/Couple | Guest, Customer |
| UC06 | Giữ Seat có thời hạn phía server | Guest, Customer |
| UC07 | Chọn Food tùy chọn | Guest, Customer |
| UC08 | Quote tối đa một Khuyến mãi cho Booking | Guest, Customer |
| UC09 | Xác nhận Booking với tổng tiền authoritative | Guest, Customer |
| UC10 | Thanh toán online qua provider hoặc xử lý zero-payable | Guest, Customer |
| UC11 | Xem booking code và QR đơn đặt vé | Guest, Customer |
| UC12 | Xem lịch sử, hủy Booking đủ điều kiện và gửi lại email | Customer |
| UC13 | Đánh giá Movie khi có paid Booking, Movie đã kết thúc và chưa review | Customer |
| UC14 | Tạo Booking bán tại quầy | Staff, Manager, Global Admin |
| UC15 | Tra cứu Booking bằng booking code hoặc QR đơn đặt vé | Staff, Manager, Global Admin |
| UC16 | Xem Payment evidence, RoomType và PresentationFormat tại quầy | Staff, Manager, Global Admin |
| UC17 | In AdmissionTicket và FoodPickupVoucher bằng Print All | Staff, Manager, Global Admin |
| UC18 | In lại với reason và print audit | Staff, Manager, Global Admin |
| UC19 | Xem Branch360 và action queue của chi nhánh | Manager, Global Admin |
| UC20 | Quản lý Room vật lý trong phạm vi chi nhánh | Manager, Global Admin |
| UC21 | Tạo draft, áp dụng Template và phát hành RoomLayout | Manager, Global Admin |
| UC22 | Quản lý Seat maintenance và mở SeatIncident | Manager, Global Admin |
| UC23 | Xử lý impact và chuyển Seat cùng Booking | Manager, Global Admin |
| UC24 | Quản lý giờ hoạt động và cleaning buffer | Manager, Global Admin |
| UC25 | Tạo/cập nhật/hủy Showtime theo validation authoritative | Manager, Global Admin |
| UC26 | Preview/publish bulk schedule và copy schedule all-or-nothing | Manager, Global Admin |
| UC27 | Xem Showtime workspace, frozen prices và Booking liên quan | Manager, Global Admin |
| UC28 | Xem Booking, Payment và báo cáo evidence theo phạm vi | Manager, Global Admin |
| UC29 | Quản lý Khuyến mãi đúng chi nhánh; global/mixed scope read-only | Manager, Global Admin |
| UC30 | Quản lý master Movie, Genre và Food toàn chuỗi | Global Admin |
| UC31 | Quản lý PriceBook, RoomType, PresentationFormat và Layout Template toàn chuỗi | Global Admin |
| UC32 | Quản lý Chi nhánh, user/assignment/role, audit và báo cáo hợp nhất | Global Admin |

Các use case trên nhóm endpoint kỹ thuật theo mục tiêu nghiệp vụ. Chúng không thay đổi authorization thực tế: Manager không mutate Movie, Genre, Food hoặc PriceBook master; Manager chỉ quản lý Khuyến mãi thuộc chính xác chi nhánh được phép.

## Phân biệt các hiện vật dễ nhầm

- Booking/Đơn đặt vé giữ giao dịch và sold snapshot.
- QR đơn đặt vé là capability tra cứu toàn Booking tại quầy.
- AdmissionTicket là vé vào rạp bằng giấy, một hiện vật cho mỗi vị trí ghế vật lý.
- FoodPickupVoucher là một phiếu giấy cho phần Food của Booking.
- Couple gồm hai vị trí và hai AdmissionTicket nhưng chỉ một pricing unit.
