# MovieMate — Dàn ý thuyết trình 10 slide

## Slide 1 — Bài toán và phạm vi

- MovieMate phục vụ **một công ty rạp chiếu phim, nhiều chi nhánh**.
- Năm actor: Guest, Customer, Staff, Manager, Global Admin.
- Không phải marketplace, multi-company SaaS hoặc cinema aggregator.
- Mục tiêu: kết nối cấu hình toàn chuỗi với vận hành chi nhánh và trải nghiệm đặt vé có bằng chứng.

## Slide 2 — Mental model vai trò

- Global Admin: governance/configuration toàn chuỗi.
- Manager: branch-first operations.
- Staff: tra cứu Booking, bằng chứng Payment và in hiện vật giấy tại quầy.
- Customer/Guest: khám phá, đặt vé, thanh toán và review.
- Movie, Genre, Food là master toàn chuỗi; Manager chỉ read/use.

## Slide 3 — Từ Branch đến Payment

```text
Branch → Room → Showtime → Booking → Payment
Booking → Counter / Print
Room / Booking → SeatIncident context
```

- Branch360, Showtime detail và handoff liên domain biến hệ thống thành workspace theo tác vụ.
- Detail dùng để quan sát; Edit chỉ dành cho thay đổi được phép.

## Slide 4 — Room và lịch sử cấu trúc

- Kích thước vật lý lưu integer mm, hiển thị mét; diện tích là xấp xỉ hình chữ nhật hành chính.
- Rows × columns là **Lưới logic**, không phải kích thước mét.
- Taxonomy: Ghế, Lối đi, Vật cản cố định, Ô trống.
- Published RoomLayout bất biến; Template apply tạo bản sao độc lập.
- Ghế bảo trì vẫn là Seat; incident không sửa structural layout.

## Slide 5 — Showtime đúng đắn

- Movie + Room + pinned RoomLayout + PresentationFormat + runtime + operating constraints.
- Validation phía server; bulk publish all-or-nothing; copy re-derives authoritative intent.
- START → END → CLEANING_START → ROOM_READY.
- Cross-midnight hợp lệ; business date là local START date.
- RoomType và PresentationFormat tách biệt; PresentationFormat không tạo phụ thu.

## Slide 6 — Giá và Khuyến mãi deterministic

```text
PriceBook → PriceBookVersion → ShowtimeTicketPrice
          → Booking/BookingSeat sold snapshot → Payment
```

- Mỗi version có một Giá cơ sở toàn chuỗi.
- Adjustment: SeatType, RoomType, Time, Weekend/Holiday, Cinema, Room; Holiday thay Weekend.
- Couple: hai vị trí vật lý, một pricing unit, charge một lần.
- Tối đa một Khuyến mãi mỗi Booking; quota được giữ khi confirm dưới row lock.

## Slide 7 — Booking, QR và hiện vật giấy

- Customer nhận booking code và **QR đơn đặt vé** cho toàn Booking để tra cứu tại quầy.
- QR đơn không phải AdmissionTicket hoặc credential vào phòng.
- Mỗi vị trí ghế vật lý tạo một AdmissionTicket; Couple tạo hai vé giấy.
- Booking có Food tạo một FoodPickupVoucher cho phần đồ ăn.
- First print không cần reason; reprint cần reason; Print All có audit.

## Slide 8 — Payment và báo cáo

- Browser return không đánh dấu paid.
- Provider callback/query đã xác minh hoặc counter settlement mới là evidence.
- Zero-payable dùng `internal_zero`, không gọi provider ngoài.
- Báo cáo dựa trên Đã xác minh/Đã thu tiền và timestamp evidence.
- Showtime business date không đồng nhất với payment settlement time.

## Slide 9 — Incident và bảo toàn lịch sử

- Seat maintenance + SeatIncident mô tả ngoại lệ vận hành, không biến Seat thành BLOCKED.
- Booking bị ảnh hưởng có thể chuyển ghế tương đương/nâng hạng, không downgrade, không thu thêm.
- Couple chuyển nguyên tử; giữ nguyên Booking identity và có thể in thay thế.
- Snapshot RoomLayout, Showtime price, sold amount và Payment evidence không bị cấu hình mới viết lại.

## Slide 10 — Demo, bằng chứng và giới hạn

- Demo 8 phút 30 giây: Manager → Customer → Staff → Manager, ba lần chuyển role.
- Bằng chứng: runtime UI, automated tests và handoff trực tiếp; không nhập URL thủ công.
- Không tuyên bố digital attendance/check-in, refund ledger, Loyalty, AI pricing, CAD/QCVN automation, branch Food pricing hoặc Format surcharge.
- Kết luận: thẩm quyền rõ, invariant phía server, concurrency có kiểm soát và lịch sử bất biến.
