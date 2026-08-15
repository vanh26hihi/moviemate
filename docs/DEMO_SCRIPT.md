# MovieMate — Kịch bản demo bảo vệ 8 phút 30 giây

Chuẩn bị ba profile độc lập, không dùng công cụ impersonation:

- **A — Manager:** mở Tổng quan chi nhánh của Manager Cầu Giấy.
- **B — Customer:** mở trang Phim và một Đơn đặt vé đã thanh toán hợp lệ.
- **C — Staff:** mở workspace Tra cứu & in tại quầy.

Dùng suất chiếu sắp tới và fixture đã được kiểm tra ở rehearsal. Không phụ thuộc giao dịch provider thật. Không nhập URL trực tiếp trong lúc trình bày; toàn bộ bước dưới đây đi bằng navigation hoặc handoff hiện hữu.

## Luồng duy nhất để trình bày

| Thời gian | Profile | Thao tác trên UI | Câu nói chính | Bằng chứng quan sát |
|---|---|---|---|---|
| 0:00–0:30 | A | Mở Tổng quan chi nhánh | “MovieMate phục vụ một công ty rạp nhiều chi nhánh. Global Admin quản trị toàn chuỗi; Manager vận hành từ chi nhánh được phân công.” | Navigation Manager là branch-first, không phải Admin panel bị bớt nút. |
| 0:30–1:10 | A | Từ Branch360 mở một Room | “Branch360 là workspace vận hành: action queue, phòng, suất hôm nay/sắp tới, quầy và finance context.” | Room hiển thị Loại phòng, kích thước vật lý, lưới logic và sức chứa đúng nghĩa. |
| 1:10–1:50 | A | Room → Sơ đồ; quay lại Room → Bảo trì | “RoomLayout là cấu trúc versioned. Ghế, lối đi, vật cản cố định và ô trống khác nhau; ghế bảo trì vẫn là Seat.” | Published layout bất biến; maintenance/incident không bị gọi là BLOCKED. |
| 1:50–2:45 | A | Room → suất chiếu sắp tới → chi tiết Showtime | “Showtime ghim đúng RoomLayout và Format. END là lúc phim hết; ROOM_READY là sau cleaning. Giá đã khóa cho suất chiếu.” | RoomType tách khỏi Định dạng trình chiếu; có START/END/cleaning/ready và frozen prices. |
| 2:45–3:30 | B | Phim → suất chiếu → chọn ghế | “Customer đi theo Movie-first. Server quyết định branch, ghế, seat-gap và giá; Couple là hai vị trí nhưng một pricing unit.” | Seat map hiển thị ghế khả dụng và policy; không nhập giá từ browser. |
| 3:30–4:10 | B | Tiếp tục qua Food và Review | “Food là tùy chọn. Mỗi Booking tối đa một Khuyến mãi trên ticket + food gross; quote chưa tiêu quota.” | Review hiển thị breakdown server và một lựa chọn Khuyến mãi. |
| 4:10–5:15 | B | Mở Đơn đặt vé đã chuẩn bị | “Browser return không chứng minh thanh toán. Customer nhận booking code và QR đơn đặt vé để Staff tra cứu; đây không phải vé vào rạp.” | Trang Customer có QR đơn, Format và lịch sử; không có digital admission action. |
| 5:15–6:10 | C | Nhập booking code hoặc quét QR đơn tại quầy | “Lookup mở đúng một Booking và cho Staff xem bằng chứng Payment, RoomType, Format cùng hiện vật cần in.” | Staff thấy trạng thái Đã xác minh/Đã thu tiền và đúng Booking context. |
| 6:10–7:20 | C | Mở nghiệp vụ Print All | “Một vị trí ghế vật lý tạo một AdmissionTicket; Couple tạo hai vé nhưng charge một lần. Food có một FoodPickupVoucher cho phần đồ ăn của Booking. First print không cần lý do; reprint bắt buộc lý do và có audit.” | Print All liệt kê AdmissionTickets và Food voucher; actor/thời điểm/reason được tách. Chỉ xác nhận in thật khi rehearsal đã chuẩn bị trạng thái phù hợp. |
| 7:20–8:00 | A | Từ Showtime mở Booking liên quan; Booking → Payment | “Đây là handoff liên domain, không phải tìm kiếm thủ công. Payment detail quay lại đúng Booking; evidence đến từ provider verification hoặc counter settlement.” | Liên kết Showtime → Booking → Payment hoạt động hai chiều theo context. |
| 8:00–8:30 | A | Mở Báo cáo từ navigation | “Báo cáo dùng Payment đã xác minh/đã thu tiền và timestamp evidence; không suy diễn từ attendance hoặc ngày tạo Booking.” | Manager chỉ thấy phạm vi chi nhánh; Global Admin mới có hợp nhất toàn chuỗi. |

**Số lần chuyển role/profile có ý nghĩa:** 3 — Manager → Customer → Staff → Manager.

## Đoạn TV4 vận hành chi nhánh 2 phút 15 giây

Đoạn 0:30–2:45 là câu chuyện TV4 chuẩn:

```text
Branch360 → Room → Sơ đồ / Bảo trì → Showtime operational detail
```

Không quay lại sidebar để tìm Showtime. Room có handoff trực tiếp và Showtime detail là trang quan sát vận hành, không phải Edit.

## Đường đi dự phòng đã có UI

- Nếu suất được chuẩn bị không còn sắp tới, chọn một suất khác ngay từ danh sách của Room; không nhập URL.
- Nếu booking Customer chưa có bằng chứng thanh toán, dùng booking counter paid fixture đã revalidate; không gọi provider live.
- Nếu camera không sẵn sàng, nhập booking code vào cùng form Staff lookup.
- Nếu máy in không sẵn sàng, mở print preview và nêu manual acceptance; không xác nhận print success giả.

## Checklist ngay trước rehearsal

- Revalidate tài khoản trong `DEMO_ACCOUNTS.md` và suất chiếu sắp tới; không hard-code ngày lâu dài.
- Chuẩn bị một Booking paid có Format, RoomType, ít nhất một AdmissionTicket và tùy chọn FoodPickupVoucher.
- Kiểm tra Booking đó đang ở first-print hay reprint state trước khi bấm hành động làm thay đổi audit.
- Mở ba profile sẵn và kiểm tra tất cả handoff bằng click.
- Không tuyên bố callback provider, camera hoặc máy in thật đã thành công nếu chưa thực hiện trên thiết bị trình bày.
