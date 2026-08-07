# MovieMate — Bộ câu hỏi bảo vệ

**Vì sao cần multi-branch?** Một chủ chuỗi cần dùng chung catalog và tiêu chuẩn vận hành nhưng phải tách phòng, suất, giá, đơn và nhân sự theo chi nhánh. `cinema_id` cùng assignment tạo ranh giới đó.

**Manager khác Admin thế nào?** Với dữ liệu vận hành theo chi nhánh, Manager chỉ xem và thao tác trên các chi nhánh được gán, kể cả khi được gán nhiều nơi. Catalog phim/thể loại là dữ liệu dùng chung và Manager chỉ thao tác theo các permission nội dung đã cấp. Global Admin có phạm vi toàn chuỗi, quản lý chi nhánh, nội dung toàn cục, người dùng và báo cáo hợp nhất.

**Staff có vào Admin không?** Không. Admin portal yêu cầu `admin.access`; workspace Staff yêu cầu role Staff/Manager/Admin và permission nghiệp vụ cụ thể. Staff có quyền quầy/ticket vẫn không được mở CRUD Admin.

**Vì sao không lưu booking chỉ bằng localStorage?** LocalStorage do browser kiểm soát, có thể sửa/xóa và không khóa ghế giữa nhiều người. Booking, hold, giá và idempotency phải nằm ở server/database; local state chỉ hỗ trợ giao diện.

**Vì sao cần seat hold?** Hold tạo quyền sở hữu ghế tạm thời có hạn và unique active lock, tránh hai khách cùng thanh toán một ghế. Hết hạn có quy trình giải phóng an toàn.

**Vì sao D6 + D8 phải bị chặn?** Tổ hợp đó để D7 thành một ghế lẻ khó bán. Client phản hồi sớm, nhưng `SeatSelectionPolicy` phía server mới là kiểm tra có thẩm quyền.

**Ghế đôi tính tiền thế nào?** Hai bản ghi ghế vật lý dùng chung `pricing_unit_key`; pricing tính một đơn vị ghế đôi và chia snapshot sao cho tổng hai hàng đúng bằng giá cặp. Báo cáo ghi 1 logical ticket, 2 physical seats.

**Giá vé lấy từ đâu?** `TicketPricingService` chọn rule có hiệu lực theo rạp/phòng/suất/ghế và trả số nguyên VND. Browser không gửi giá quyết định.

**Vì sao không nhập giá tùy ý mỗi suất?** Giá thủ công gây lệch giữa kênh online/quầy và phá lịch sử. Trường `showtimes.price` còn để tương thích/snapshot hiển thị, nhưng lịch mới và checkout lấy từ pricing engine.

**Hai suất cùng phòng chồng giờ được xử lý thế nào?** `ShowtimeScheduleService` khóa và so sánh toàn bộ cửa sổ từ start đến hết phim cộng cleaning buffer, kể cả qua nửa đêm; conflict bị từ chối trước khi lưu.

**Vì sao có cleaning buffer?** Phòng chưa sẵn sàng ngay khi phim hết. Buffer phản ánh dọn vệ sinh/chuyển ca và tham gia trực tiếp vào kiểm tra overlap.

**Phim kết thúc sau 0h xử lý thế nào?** `show_date` là ngày bắt đầu kinh doanh; thời điểm kết thúc được tính bằng timezone chi nhánh và có thể sang ngày kế tiếp. Latest show start không đồng nghĩa phim phải kết thúc trước 0h.

**Vì sao return URL không được tự đánh dấu paid?** Return chạy trong browser và có thể bị giả mạo/đóng giữa chừng. VNPAY dùng IPN đã ký; ZaloPay dùng callback MAC; payOS return phải query provider hoặc nhận webhook đã xác minh.

**payOS webhook dùng để làm gì?** Nhận trạng thái provider độc lập với tab browser, kiểm tra chữ ký/order/amount/currency rồi mới finalize idempotent hoặc chuyển review.

**Đóng tab thanh toán thì ghế thế nào?** Attempt pending/unresolved giữ ghế đến hạn an toàn; job query/expiration xử lý tiếp. Đóng tab không được coi là success hoặc cancel.

**Người dùng hủy payment thì ghế thế nào?** Chỉ cancellation/terminal failure đã được provider xác minh mới giải phóng active seat locks đủ điều kiện. Một cancel return giả không có tác dụng.

**Reconciliation dùng khi nào?** Chỉ cho pending/unresolved hoặc exception cần back-office kiểm tra provider; success đã xác minh bình thường không cần Admin “duyệt paid”.

**Vì sao khách không được in vé?** Vé khách là capability điện tử phục vụ QR. In cứng là nghiệp vụ Staff cần máy in, actor, trạng thái và retry evidence; không đặt nút Print/PDF trên trang khách.

**Staff in vé lại thế nào?** Lần đầu start rồi success/failure rõ ràng. Một failure có lý do cho phép một retry tự động; lần tiếp theo cần Manager/Admin cấp quyền. Không reset lịch sử.

**Scan QR có tự check-in không?** Không. Resolve/preview là read-only. Staff phải xác nhận ở endpoint check-in riêng mới đổi booking sang used và ghi event append-only.

**Ai tạo đơn quầy?** `created_by_staff_id` lấy từ actor đang đăng nhập khi tạo hold, không nhận từ browser.

**Ai thu tiền?** `settled_by_user_id` và `settled_at` lấy từ actor đang đăng nhập khi xác nhận thu tiền mặt.

**Có phân biệt người in và người soát vé không?** Có. Print lưu `printed_by_user_id`; check-in event lưu `actor_user_id`; cả hai độc lập với creator và settler.

**Vì sao phim không hard-delete?** Booking, ticket và báo cáo lịch sử cần movie còn tồn tại. Lifecycle draft/coming soon/now showing/inactive/archived kiểm soát sellability mà không phá tham chiếu.

**Vì sao cần layout template?** Template chuẩn hóa thiết kế nhiều phòng, cho preview/apply nhanh. Khi publish, layout version bất biến; layout cũ và ghế đã đặt vẫn được giữ.

**Dashboard tính doanh thu theo ngày nào?** Online theo `payments.verified_at`, counter cash theo `payments.settled_at`, quy đổi sang ngày địa phương của chi nhánh. Không dùng `booking.created_at` hay generic `paid_at`.

**Couple seat trong báo cáo tính thế nào?** Một pair là 1 logical ticket nhưng 2 physical seats. Doanh thu lấy payment authoritative một lần, không nhân qua join ghế.

**Hệ thống chống truy cập chéo chi nhánh ra sao?** Route có auth/role/permission; controller/service gọi `CinemaAccessService`; query index được scope; ID trực tiếp và forged POST được kiểm thử. Revoking assignment có hiệu lực ở request kế tiếp.

**Kiến trúc authoritative tóm tắt?** Browser → Laravel route/controller → validation/permission → domain service → MySQL. Provider và email là boundary ngoài. Server quyết định giá, hold, branch, actor và trạng thái; database giữ snapshot/guard/event lịch sử.

**Các entity chính?** `cinemas` có `rooms`; phòng có versioned `room_layouts` và `seats`; `movies` có `showtimes`; `bookings` có `booking_seats`, food order và `payments`; user assignment tạo scope; ticket delivery, print và check-in là ba workflow độc lập; pricing rules và layout templates là cấu hình dùng lại.
