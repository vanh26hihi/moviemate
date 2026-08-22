# MovieMate — Hồ sơ sẵn sàng bảo vệ

Tài liệu này mô tả **sản phẩm cuối cùng**: một công ty rạp chiếu phim vận hành nhiều chi nhánh. MovieMate không phải marketplace, nền tảng đa công ty hay hệ thống multi-tenant SaaS.

## Mô hình quyền và trách nhiệm

- **Global Admin:** quản trị cấu hình toàn chuỗi, chi nhánh, danh mục dùng chung, người dùng và báo cáo hợp nhất.
- **Manager:** vận hành chi nhánh được phân công; xem Movie, Genre, Food dùng chung nhưng không sửa các master toàn chuỗi.
- **Staff:** tra cứu đơn tại quầy, xem bằng chứng thanh toán và in hiện vật giấy.
- **Customer:** khám phá, đặt vé, thanh toán, xem lịch sử và đánh giá khi đủ điều kiện.
- **Guest:** khám phá và bắt đầu luồng khách hàng.

Movie, Genre và Food là master toàn chuỗi do Global Admin quản lý. Manager chỉ quản lý Khuyến mãi thuộc chính xác chi nhánh của mình; Khuyến mãi toàn chuỗi hoặc mixed-scope là read-only.

## Bốn chuỗi thẩm quyền cốt lõi

```text
Room → RoomLayout đã phát hành → Seat
Seat → bảo trì / SeatIncident                     (ngoại lệ vận hành)

Movie + Room + RoomLayout được ghim + Định dạng trình chiếu
      + runtime + giờ hoạt động + cleaning buffer → Showtime

PriceBook → PriceBookVersion → ShowtimeTicketPrice
          → snapshot bán của Booking/BookingSeat → Payment evidence

Booking snapshot → AdmissionTicket theo từng vị trí ghế vật lý
                 → một FoodPickupVoucher cho phần đồ ăn của đơn
QR đơn đặt vé chỉ dùng tra cứu tại quầy
```

## Ma trận bằng chứng

| Claim bảo vệ | Bằng chứng runtime | Bằng chứng tự động | Bước demo |
|---|---|---|---|
| Manager bị giới hạn theo chi nhánh | Branch360 và navigation branch-first | `NavigationOwnershipTest`, `MultiCinemaAuthorizationTest` | Mở Tổng quan chi nhánh bằng Manager |
| RoomLayout lịch sử bất biến | Showtime ghim đúng `room_layout_id` | `RoomLayoutHistoryIntegrityTest` | Room → Sơ đồ → Showtime |
| Giá suất chiếu không trôi | Trang Showtime hiển thị Giá đã khóa cho suất chiếu | `ShowtimeTicketPriceSnapshotTest` | Mở chi tiết Showtime |
| Một Khuyến mãi mỗi đơn | Review chỉ có một lựa chọn Khuyến mãi | `PromotionAuthorityTest` | Customer review |
| Quota được bảo vệ đồng thời | Xác nhận Booking mới giữ quota cuối | `PromotionQuotaMySqlTest` | Nêu bằng chứng test, không mô phỏng race |
| QR đơn không phải vé vào rạp | Customer hiển thị QR đơn; Staff in hiện vật riêng | `BookingQrPrintFlowTest` | Customer → Staff counter |
| In vật lý có audit | First print, reprint reason và Print All | `TicketOperationsR3Test` | Staff mở nghiệp vụ in |
| Báo cáo dựa trên bằng chứng tiền | Trạng thái Đã xác minh/Đã thu tiền | `ReportingR9Test` | Booking → Payment → Báo cáo |

## Trước và sau phản biện

| Phản biện trước đây | Giải pháp cuối cùng |
|---|---|
| Giao diện nặng CRUD | Branch360, Showtime workspace và các handoff theo tác vụ |
| Lẫn đơn đặt vé với vé vào rạp | QR đơn đặt vé để tra cứu; AdmissionTicket là hiện vật giấy riêng |
| Room thiếu tính vật lý | Kích thước mm hiển thị mét, lưới logic và taxonomy cấu trúc riêng |
| Showtime phân mảnh | Workspace tập trung Room, Format, lifecycle, giá khóa và Booking liên quan |
| Giá không rõ thẩm quyền | PriceBook versioned và snapshot Showtime bất biến |
| Khuyến mãi khó xác định | Tối đa một Khuyến mãi, quota có row lock và định nghĩa bất biến sau sử dụng |
| Manager global scope mơ hồ | Manager branch-first; Global Admin sở hữu master toàn chuỗi |

## Ánh xạ câu hỏi hội đồng

| Câu hỏi | Bằng chứng sản phẩm | Demo | Không được tuyên bố |
|---|---|---|---|
| Vì sao không chỉ là CRUD? | Read model vận hành, handoff liên domain, snapshot và invariant server | Branch → Room → Showtime → Booking → Payment | “Vì giao diện hiện đại” |
| Vì sao sơ đồ không phải mét? | Width/length vật lý tách khỏi rows × columns | Room → Sơ đồ | CAD hoặc kiểm định kiến trúc |
| Vì sao giá cũ không đổi? | `ShowtimeTicketPrice` và sold snapshot | Chi tiết Showtime/Booking | Showtime luôn lấy bảng giá mới nhất |
| QR dùng để làm gì? | Capability tra cứu chính xác một Booking | Customer QR → Staff lookup | QR vào cổng hoặc vé điện tử |
| Thanh toán nào được tính báo cáo? | Provider verification hoặc counter settlement | Booking → Payment → Báo cáo | Browser redirect là bằng chứng thành công |
| Ghế hỏng sau khi bán xử lý ra sao? | SeatIncident và chuyển ghế cùng Booking | Room/Booking → incident context | Đổi cell thành BLOCKED hoặc tạo Booking mới |

## Điểm đóng băng lịch sử

- RoomLayout đóng băng khi phát hành; thay đổi cấu trúc tạo draft/version mới.
- Template được áp dụng thành bản sao độc lập; sửa Template không đổi Room đã áp dụng.
- PriceBookVersion đóng băng khi phát hành.
- ShowtimeTicketPrice đóng băng khi lập lịch Showtime.
- Booking/BookingSeat giữ sự thật tài chính đã bán.
- BookingPromotion giữ snapshot đặt chỗ quota; released không còn tiêu quota nhưng vẫn chứng minh đã từng sử dụng.
- AdmissionTicket được cấp sau bằng chứng thanh toán authoritative và giữ identity của vị trí ghế vật lý.

## Phân loại nguồn phát biểu

- **Repository evidence:** route, model, service, view và test đang tồn tại.
- **Implementation invariant:** ràng buộc được server/database thực thi.
- **Product Owner decision:** ví dụ một Khuyến mãi mỗi Booking và Holiday thay Weekend; không trình bày như quy luật ngành.
- **Desk research:** chỉ dùng khi có nguồn kiểm chứng; bộ tài liệu này không dựng phỏng vấn hoặc khảo sát.

## Giới hạn phải nói đúng

- Không duy trì digital attendance/check-in; QR đơn đặt vé chỉ phục vụ tra cứu và vé giấy được kiểm tra thủ công.
- Không có refund ledger hoặc “net revenue after refund”.
- Không có Loyalty.
- Không có dynamic/AI pricing; PriceBook là bộ quy tắc deterministic.
- Không có CAD, mô phỏng thoát hiểm hoặc máy kiểm tra chiều rộng lối đi.
- Không chứng nhận QCVN hay tuân thủ pháp lý tự động.
- Không có miền giá Food riêng theo chi nhánh.
- Định dạng trình chiếu không tạo phụ thu.
- Branch360 không tuyên bố occupancy, attendance rate hay check-in rate.

Đây là các ranh giới phạm vi có chủ ý, không phải lời hứa tính năng chưa hoàn thành.

## Trạng thái sẵn sàng

- Runtime Phase 1–8B4 là nguồn thẩm quyền và không bị thay đổi bởi tài liệu này.
- Handoff hiện hành: Branch → Room → Showtime → Booking → Payment; Booking → Counter/Print; Room/Booking → incident context.
- Route demo phải đi qua navigation và liên kết hiện hữu, không nhập URL thủ công.
- Provider thật, camera và máy in vật lý chỉ được tuyên bố sau rehearsal trên thiết bị thực.
- Known technical debt duy nhất được giữ riêng: PayOS selection query budget `20 > 19`; đây không phải lỗi chức năng thanh toán.
