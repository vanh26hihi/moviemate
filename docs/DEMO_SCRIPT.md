# MovieMate — Kịch bản demo bảo vệ 15 phút

Chuẩn bị ba profile/cửa sổ độc lập trước giờ bảo vệ:

- **WINDOW A — CUSTOMER:** public hoặc `customer@moviemate.test`.
- **WINDOW B — ADMIN/MANAGER:** `admin@moviemate.test`; mở thêm profile Manager CG nếu cần chứng minh scope.
- **WINDOW C — STAFF:** đăng nhập `staff.cg@moviemate.test` tại `/login`, sau đó vào `/staff`.

Không dùng giao dịch provider giả. Nếu provider/hardware thật chưa được acceptance, dùng booking tiền mặt tạo qua luồng quầy để tiếp tục phần vé.

| Thời gian | Cửa sổ / trang | Click hoặc thao tác | Câu trình bày | Kết quả mong đợi | Phương án dự phòng |
|---|---|---|---|---|---|
| 0:00–1:15 | Slide 1 | Giới thiệu đề tài | “MovieMate phục vụ một chủ chuỗi rạp nhiều chi nhánh, hướng tới khách 16–40 tuổi. Hệ thống có 5 actor con người và 32 use case trình bày.” | Phạm vi rõ: không marketplace, không SaaS đa công ty. | Dùng `PRESENTATION_OUTLINE.md` nếu slide lỗi. |
| 1:15–2:30 | A `/movies` | Mở một phim đang chiếu, đổi rạp/ngày | “Đây là movie-first: phim trước, sau đó rạp và ngày. Preference chỉ ưu tiên hiển thị, không đổi checkout đang hoạt động.” | Chỉ suất hợp lệ; rạp/phòng/giá hiện rõ. | Dùng `/cinemas/CG` để đi cinema-first. |
| 2:30–4:00 | A `/booking/select-seat/{showtime}` tại phòng `DEMO` | Chọn D6 rồi D8 | “Nếu nhận D6 và D8 thì D7 bị cô lập; client phát hiện ngay và server cũng áp dụng cùng policy.” | Có phản hồi, không thể tiếp tục với khoảng trống một ghế. | Nếu JS lỗi, gửi trực tiếp request bằng form/devtools: server trả validation; test `SeatGapCheckoutTest` là bằng chứng. |
| 4:00–4:30 | A seat map | Bỏ chọn và chọn D6+D7+D8; chỉ ra aisle cột 5 và cặp C1+C2 | “Aisle tách phân đoạn; sparse gap và ghế đôi không bị hiểu sai. Ghế đôi chọn cả cặp.” | Tổ hợp hợp lệ, nút tiếp tục hoạt động. | Chọn một tổ hợp liền nhau khác. |
| 4:30–5:30 | A `/booking/food` | Thêm/bớt món, quay lại ghế rồi tiếp tục | “Đồ ăn là tùy chọn; số lượng 0 hợp lệ. Ghế và đồ ăn được giữ phía server, đồng hồ là hạn hold có thẩm quyền.” | State ghế/đồ ăn còn; không có nút “Bỏ qua đồ ăn” gây hiểu nhầm. | Chọn 0 món và tiếp tục; dùng test persistence nếu phiên demo hết hạn. |
| 5:30–6:30 | A `/booking/review` | Mở lựa chọn VNPAY/ZaloPay/payOS | “Browser chỉ chọn provider; số tiền do server tính. Return URL không tự đánh dấu paid.” | Phương thức được trình bày; attempt chỉ được tạo khi cấu hình provider hợp lệ. | Không gửi provider nếu credential/HTTPS chưa acceptance; chuyển sang booking quầy hợp lệ. |
| 6:30–7:15 | A vé đã chuẩn bị | Mở capability ticket | “QR là capability ký, không phải booking code. Khách chỉ xem vé điện tử, không có Print/PDF/Save image.” | Đúng booking, chi nhánh, suất, ghế; used vẫn đọc được; trạng thái không hợp lệ không có QR. | Dùng test render/ticket fixture nếu link demo đã hết hạn. |
| 7:15–8:00 | A `/cinemas` → `/cinemas/CG` | Chọn rạp rồi phim/suất | “Cinema-first dùng cùng catalog và pricing service, nhưng cố định ngữ cảnh chi nhánh từ đầu.” | Chỉ active branch và showtime của CG. | Quay về movie-first nếu dữ liệu ngày hiện tại ít. |
| 8:00–10:15 | C `/staff/counter` | Chọn CG, phòng demo/suất, ghế, đồ ăn 0 hoặc có món, review, thu tiền mặt | “Đây là bán vé quầy thật trong hệ thống nội bộ. Creator và settler lấy từ session, không nhận từ form.” | Booking counter paid; payment `counter_cash`; không gọi provider ngoài. | Dùng booking counter đã chuẩn bị nếu thao tác chậm. |
| 10:15–11:30 | C `/staff/tickets` | Nhập/scan capability, xem preview, bắt đầu in, xác nhận thành công hoặc thất bại | “Preview chỉ đọc. In có start/success/failure, một retry tự động; lần sau cần Manager cho phép.” | Preview không check-in; print event/actor/time được ghi; check-in chưa đổi. | Camera/printer thật: dùng manual input và giải thích mục acceptance còn mở. |
| 11:30–12:15 | C `/staff/tickets/check` | Check-in rồi gửi lại capability | “Check-in atomic, append-only và ghi actor thật; quét lại không đổi thời điểm đầu tiên.” | Lần đầu accepted/used, lần hai duplicate; print state độc lập. | Dùng manual fallback thay camera. |
| 12:15–13:30 | B `/admin/cinemas`, `/admin/pricing-rules`, `/admin/showtimes`, `/admin/layout-templates`, `/admin/movies` | Chỉ nhanh giờ mở cửa, giá, cleaning window, template và lifecycle | “Manager chỉ vận hành chi nhánh được gán. Layout phát hành và booking snapshot giữ lịch sử; phim chuyển lifecycle thay vì hard-delete.” | End/cleaning window được tính; showtime có booking không thể hủy trực tiếp. | Dùng màn hình read-only, không mutate dữ liệu demo. |
| 13:30–14:30 | B `/admin` và `/admin/reports` | Đổi ngày/rạp; xem breakdown | “Finance dùng `verified_at` cho online, `settled_at` cho tiền mặt; operations dùng ngày bắt đầu suất tại rạp.” | Revenue/ticket/channel/provider/print/check-in/actor tách đúng; Manager chỉ thấy CG. | Chọn khoảng ngày chứa booking counter vừa tạo. |
| 14:30–15:00 | Slide 9–10 | Kết luận | “Ba lớp bảo vệ chính là scope theo chi nhánh, dữ liệu authoritative phía server và bằng chứng thanh toán/ticket bất biến.” | Kết thúc trong 15 phút. | Nêu checklist manual acceptance thay vì tuyên bố provider/hardware chưa thử. |

## Hai đường đi khách hàng để luyện trước

Movie-first: `/movies` → `/movies/{slug}` → suất/rạp/ngày → `/booking/select-seat/{showtime}` → `/booking/food` → `/booking/review` → provider → `/bookings/{booking}/ticket`.

Cinema-first: `/cinemas` → `/cinemas/CG` → phim/suất trong chi nhánh → cùng checkout authoritative ở trên.

## Checklist ngay trước demo

- Chạy `php artisan migrate:status`, bảo đảm không migration pending.
- Kiểm tra phòng `DEMO` có D6/D7/D8, cặp C1/C2 và suất tương lai.
- Mở sẵn ba profile; không logout/login liên tục.
- Tạo trước một booking counter nếu muốn có fallback vé.
- Không gọi payOS/VNPAY/ZaloPay nếu merchant test hoặc public webhook chưa sẵn sàng.
