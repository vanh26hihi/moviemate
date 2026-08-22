# MovieMate — Checklist nghiệm thu và rehearsal bảo vệ

`[x]` là invariant đã có source/test evidence. `[ ]` là kiểm tra môi trường trình bày phải làm lại ở Phase 8B6; không suy diễn thành công từ môi trường phát triển.

## PRODUCT SCOPE

- [x] Một công ty rạp, nhiều chi nhánh; không marketplace hoặc SaaS đa công ty.
- [x] Năm actor: Guest, Customer, Staff, Manager, Global Admin.
- [x] Global Admin quản trị master toàn chuỗi; Manager branch-first.
- [x] Movie, Genre, Food chỉ Global Admin mutate; Manager read/use.
- [x] Không có Loyalty, refund ledger, AI pricing hoặc branch-specific Food price.

## ROOM / LAYOUT / INCIDENT

- [x] Width/length lưu integer mm và hiển thị mét; area là xấp xỉ hành chính.
- [x] Rows × columns được gọi là Lưới logic, không phải kích thước phòng.
- [x] Ghế, Lối đi, Vật cản cố định và Ô trống được phân biệt.
- [x] Ghế bảo trì vẫn là Seat; SeatIncident không sửa cell thành BLOCKED.
- [x] Published RoomLayout bất biến; structural change tạo version mới.
- [x] Template apply tạo bản sao; sửa Template không mutate Room đã áp dụng.
- [x] Showtime ghim đúng `room_layout_id`.
- [x] Chuyển ghế giữ Booking, chỉ tương đương/nâng hạng, không thu thêm và Couple atomic.

## SHOWTIME

- [x] Validation server-authoritative gồm Room, layout phát hành, Format, runtime, giờ hoạt động, cleaning và overlap.
- [x] Bulk publish all-or-nothing; schedule copy re-derives authoritative intent.
- [x] START, END, CLEANING_START và ROOM_READY là bốn mốc riêng.
- [x] END là lúc Movie kết thúc; ROOM_READY là sau cleaning.
- [x] Cross-midnight được hỗ trợ; business date là local START date.
- [x] Customer booking cutoff chính xác START + 15 phút.
- [x] RoomType tách PresentationFormat; PresentationFormat price-neutral.

## PRICEBOOK / PROMOTION

- [x] PriceBook → PriceBookVersion → ShowtimeTicketPrice → sold snapshot.
- [x] Mỗi PriceBookVersion có đúng một Giá cơ sở toàn chuỗi.
- [x] Cinema/Room là adjustment, không phải base price thứ hai.
- [x] Holiday thay Weekend; không stack hai calendar adjustment.
- [x] Couple có hai vị trí vật lý, một pricing unit và charge một lần.
- [x] Giá đã khóa cho suất chiếu không bị version mới viết lại.
- [x] Tối đa một Khuyến mãi mỗi Booking.
- [x] Minimum basis là ticket + food gross trước Khuyến mãi.
- [x] Fixed không có cap; percentage có optional positive cap.
- [x] Booking confirm giữ quota dưới row lock; quote không tiêu quota.
- [x] Released không tiêu quota hiện tại nhưng usage vẫn khóa định nghĩa Promotion.

## BOOKING / PAYMENT / PRINT

- [x] Customer nhận booking code và QR đơn đặt vé cho toàn Booking.
- [x] QR đơn chỉ phục vụ lookup, không phải AdmissionTicket hoặc credential vào phòng.
- [x] Mỗi vị trí ghế vật lý tạo một AdmissionTicket; Couple tạo hai vé giấy.
- [x] Booking có Food tạo một FoodPickupVoucher cho phần đồ ăn.
- [x] First print không cần reason; reprint cần reason; Print All có audit.
- [x] Browser return không đánh dấu paid.
- [x] Provider verification hoặc counter settlement là Payment evidence.
- [x] Zero-payable dùng `internal_zero` và không gọi provider ngoài.
- [x] Báo cáo dùng trạng thái/thời gian Đã xác minh hoặc Đã thu tiền.
- [x] Review eligibility không phụ thuộc attendance: paid Booking, Movie đã END và chưa review.

## OPERATIONAL HANDOFFS

- [x] Branch → Room → Showtime.
- [x] Showtime → Booking.
- [x] Booking → Payment và Payment → Booking.
- [x] Booking → Tra cứu & in tại quầy.
- [x] Room/Booking → exact incident context khi có impact.
- [x] Showtime detail dùng để quan sát; không cần vào Edit để xem trạng thái vận hành.

## DEFENSE DOCUMENT CONTRACT

- [x] Booking QR và AdmissionTicket được phân biệt trong toàn bộ active defense package.
- [x] Không có current claim về digital attendance/check-in hoặc used-ticket state.
- [x] Không còn pricing authority cũ, multi-Promotion stacking hoặc Format surcharge.
- [x] Không tuyên bố CAD, QCVN certification, occupancy analytics hoặc net revenue after refund.
- [x] Không dựng phỏng vấn/khảo sát; Product Owner decision tách khỏi repository evidence và desk research.
- [x] Canonical demo dài 8 phút 30 giây, ba role switches, không nhập URL thủ công.
- [x] DOCX generator dùng cùng frozen model.

## REHEARSAL — PHASE 8B6

- [ ] Chạy `migrate:status`, route count, focused/full suite và build trên laptop trình bày.
- [ ] Revalidate fixture Showtime sắp tới, Booking paid, Payment evidence và print state.
- [ ] Click toàn bộ demo bằng đúng ba browser profiles; không dùng direct URL.
- [ ] Kiểm tra responsive trên màn hình/máy chiếu thực.
- [ ] Nếu dùng camera, kiểm tra QR lookup trên thiết bị thật.
- [ ] Nếu in thật, xác nhận máy in và trạng thái first print/reprint trước rehearsal.
- [ ] Nếu demo provider live, xác nhận credential/webhook trước; nếu không, dùng paid fixture ổn định.
- [ ] Không tuyên bố manual acceptance item đã hoàn tất khi chưa có bằng chứng.

## GIT / RELEASE

- [x] Không stage `.env`, protected audit documents, binary DOCX/PDF hoặc build artifacts.
- [x] Không dùng destructive Git.
- [x] Không push trong phase documentation alignment.
- [x] Known debt PayOS selection query budget `20 > 19` được tách khỏi functional payment claims.
