# MovieMate — Dàn ý thuyết trình

## Slide 1 — Đề tài, người dùng, phạm vi, actor

- **MovieMate — Hệ thống quản lý và đặt vé cho chuỗi rạp chiếu phim**
- Người dùng mục tiêu: 16–40 tuổi, ở gần các chi nhánh MovieMate, cần tra cứu phim/suất/ghế và đặt vé online.
- Phạm vi: một chủ chuỗi rạp, nhiều chi nhánh; không phải marketplace hoặc SaaS đa công ty.
- 5 actor con người: Guest, Registered Customer, Staff, Manager, Global/System Admin.
- 32 use case ở mức trình bày.
- Hệ thống ngoài: VNPAY, ZaloPay, payOS và hạ tầng email.

## Slide 2 — Kiến trúc và đa chi nhánh

- Browser → Laravel route/controller → validation/policy → domain service → MySQL.
- Cinema → Room → Layout → Showtime → Booking → Payment → Ticket operations.
- Global Admin thấy toàn chuỗi; Manager/Staff bị giới hạn theo phân công; lựa chọn rạp của khách chỉ là preference.

## Slide 3 — Hai luồng đặt vé khách hàng

- Movie-first: Phim → Rạp → Ngày/suất → Ghế → Đồ ăn → Thanh toán → Vé.
- Cinema-first: Rạp → Phim → Suất → Ghế → Đồ ăn → Thanh toán → Vé.
- Server khóa chi nhánh theo suất chiếu, không tin `cinema_id` từ browser.

## Slide 4 — Đúng đắn ghế và giá

- Seat hold phía server, idempotency, không để lại một ghế lẻ.
- Demo D6 + D8 bị chặn vì bỏ D7; aisle/sparse gap và ghế đôi được xử lý riêng.
- Giá nguyên VND: base + loại ghế + định dạng phòng + khung giờ + cuối tuần/ngày lễ + điều chỉnh rạp/phòng.
- Snapshot giá giữ nguyên lịch sử; ghế đôi tính một đơn vị giá cho hai ghế vật lý.

## Slide 5 — Thanh toán và vé điện tử

- VNPAY/ZaloPay/payOS chỉ thành công sau xác minh có thẩm quyền; return URL không tự đánh dấu paid.
- Pending giữ ghế; thất bại/hủy đã xác minh mới giải phóng ghế; trường hợp bất thường vào review.
- Vé điện tử dùng capability/QR không phải booking code thô; email qua outbox idempotent.

## Slide 6 — Staff tại quầy

- Bán vé quầy → đồ ăn tùy chọn → thu tiền mặt → in tùy chọn → check-in sau.
- Tra QR chỉ đọc; in và check-in là hai state machine độc lập.
- Creator, settler, printer và checker lấy từ người dùng đang đăng nhập.

## Slide 7 — Manager và Admin

- Manager vận hành chi nhánh được gán: phòng, lịch, giá, giờ mở cửa, báo cáo; đồng thời có các permission catalog phim/thể loại dùng chung đã được RBAC cấp.
- Global Admin quản lý toàn chuỗi, nội dung, người dùng/phân quyền và báo cáo hợp nhất.
- Layout phát hành bất biến; phim dùng lifecycle thay vì hard-delete.

## Slide 8 — Dashboard và báo cáo

- Doanh thu online theo `verified_at`; tiền mặt theo `settled_at`.
- Vận hành theo ngày bắt đầu suất chiếu tại múi giờ chi nhánh.
- Tách logical ticket/physical seat, provider/channel, print/check-in và creator/settler.

## Slide 9 — Bảo mật và toàn vẹn dữ liệu

- Middleware + permission + service-level cinema scope; direct URL/forged POST đều được test.
- Server-authoritative price/actor/branch/amount.
- Capability được băm/ký, log được lọc, webhook kiểm tra chữ ký và idempotency.
- FK, unique guard, immutable snapshots và append-only event bảo vệ lịch sử.

## Slide 10 — Demo và kết luận

- Ba cửa sổ riêng: Customer, Manager/Admin, Staff.
- Trình diễn đặt vé, seat gap, quầy, in/check-in, vận hành và báo cáo.
- Kết luận: đúng phạm vi chuỗi rạp, phân quyền theo chi nhánh, thanh toán an toàn và sẵn sàng bảo vệ; các hạng mục provider/hardware thật nằm trong checklist acceptance.
