# MovieMate — Talk track và Viva Q&A

## 10 điều cả đội phải nhớ

1. MovieMate phục vụ **một công ty rạp, nhiều chi nhánh**; không phải marketplace hoặc SaaS đa công ty.
2. Global Admin quản trị toàn chuỗi; Manager vận hành chi nhánh được phân công.
3. Room là phòng vật lý; RoomLayout là phiên bản cấu trúc được phát hành và ghim vào Showtime.
4. BLOCKED là vật cản cố định; ghế bảo trì vẫn là Seat và có thể gắn SeatIncident.
5. RoomType là loại trải nghiệm phòng; PresentationFormat là phương thức trình chiếu và không tạo phụ thu.
6. END là lúc phim kết thúc; ROOM_READY là sau cleaning buffer.
7. PriceBookVersion cấu hình giá tương lai; ShowtimeTicketPrice khóa giá của Showtime; Booking khóa số tiền đã bán.
8. QR đơn đặt vé dùng tra cứu Booking; AdmissionTicket là vé giấy theo từng vị trí ghế vật lý.
9. Mỗi Booking áp dụng tối đa một Khuyến mãi.
10. Browser return không chứng minh đã trả tiền; báo cáo dựa trên Payment đã xác minh hoặc đã thu tiền.

## 15 câu hỏi hội đồng dễ hỏi

**1. Vì sao hệ thống này không chỉ là CRUD?**
MovieMate có read model theo tác vụ, handoff Branch → Room → Showtime → Booking → Payment, snapshot lịch sử, invariant phía server, row lock và authorization theo chi nhánh. Giá trị nằm ở tính đúng đắn liên domain, không phải số lượng màn hình.

**2. Manager khác Global Admin thế nào?**
Global Admin quản trị cấu hình và master toàn chuỗi. Manager bắt đầu từ chi nhánh hiện tại, vận hành Room, Showtime, Booking, báo cáo và Khuyến mãi đúng phạm vi; Movie, Genre, Food chỉ read/use.

**3. Room khác RoomLayout thế nào?**
Room là phòng chiếu vật lý có width/length lưu mm. RoomLayout là phiên bản lưới cấu trúc; khi phát hành thì bất biến và Showtime ghim đúng version.

**4. Vì sao lưới Room không phải mét?**
Rows × columns là lưới logic để định vị Ghế, Lối đi, Vật cản cố định và Ô trống. Kích thước vật lý được lưu riêng bằng mm, hiển thị mét; hệ thống không tuyên bố CAD.

**5. BLOCKED có phải ghế hỏng không?**
Không. BLOCKED là vật cản cấu trúc; ghế hỏng vẫn là Seat, được đánh dấu bảo trì và có thể tạo SeatIncident.

**6. RoomType khác PresentationFormat thế nào?**
RoomType mô tả trải nghiệm/auditorium như phòng premium hoặc IMAX. PresentationFormat là phương thức chiếu như 2D/3D và hiện tại price-neutral.

**7. Showtime kết thúc khi nào?**
END = START + runtime của Movie. ROOM_READY = END + cleaning buffer; phim đã hoàn tất ở END dù phòng vẫn đang vệ sinh.

**8. Qua nửa đêm và ngày nghiệp vụ xử lý ra sao?**
Showtime được phép qua nửa đêm; ngày nghiệp vụ là ngày local của START. Payment dùng timestamp xác minh/thu tiền thực tế, không dùng ngày nghiệp vụ của Showtime.

**9. Vì sao giá của Showtime cũ không đổi?**
PriceBookVersion cấu hình phép tính tương lai, ShowtimeTicketPrice đóng băng khi lập lịch và Booking lưu sold snapshot. Thay đổi cấu hình sau đó không viết lại lịch sử.

**10. Vì sao Holiday không cộng thêm Weekend?**
Rule được Product Owner duyệt là Holiday thay Weekend để một ngữ cảnh lịch không nhận hai adjustment. Đây là quyết định sản phẩm, không phải tuyên bố quy luật ngành.

**11. Ghế đôi tính vé và tính tiền thế nào?**
Couple có hai vị trí ghế vật lý nên in hai AdmissionTicket, nhưng là một pricing unit và chỉ charge một lần. Hai vị trí phải được xử lý nguyên tử.

**12. Vì sao chỉ một Khuyến mãi?**
Đây là rule Product Owner giúp loại bỏ stacking ambiguity và làm quota, snapshot, audit deterministic. Khuyến mãi áp dụng trên ticket + food gross trước giảm.

**13. Quota Khuyến mãi chống tranh chấp ra sao?**
Quote không tiêu quota. Authoritative Booking confirm giữ slot dưới row lock; reserved và redeemed tiêu quota, released không còn tiêu quota nhưng định nghĩa vẫn bất biến vì đã có usage.

**14. Vì sao không có digital check-in?**
MovieMate không duy trì attendance điện tử. QR của Customer chỉ tra cứu Booking; Staff in AdmissionTicket và rạp kiểm tra vé giấy theo quy trình thủ công.

**15. Vì sao dùng “đã xác minh/đã thu tiền”?**
Browser redirect có thể bị giả mạo hoặc đóng giữa chừng. Provider callback/query đã xác minh hoặc counter settlement mới là evidence; báo cáo dùng các trạng thái và timestamp đó.

## Câu hỏi tình huống ngắn

**Ghế hỏng sau khi đã bán thì sao?** Seat vẫn là Seat; ghi incident, xác định Booking bị ảnh hưởng và chuyển sang vị trí tương đương/nâng hạng, không downgrade, không thu thêm, giữ nguyên Booking. Couple được chuyển nguyên tử và có thể cần in thay thế.

**Template đổi thì Room đã áp dụng có đổi không?** Không. Apply tạo bản sao độc lập; Template không phải runtime authority của RoomLayout đã áp dụng.

**PriceBook đổi thì Showtime cũ có đổi không?** Không. Showtime giữ snapshot từ PriceBookVersion ban đầu.

**Promotion reservation được released thì có sửa nội dung lại được không?** Không. Released không còn tiêu quota hiện tại nhưng usage đã tồn tại nên economics, eligibility và scope vẫn khóa; chỉ lifecycle controls còn hiệu lực.

**Payment callback đến muộn thì sao?** Hệ thống áp dụng provider verification và idempotency trên đúng attempt. Trạng thái ambiguous được giữ để reconciliation, không suy diễn từ browser return.

## Không được tuyên bố

- QR vào cổng, vé điện tử hoặc attendance tracking.
- Refund ledger, net revenue sau refund hoặc hoàn tiền tự động.
- Occupancy/check-in analytics.
- AI/dynamic pricing hoặc yield management.
- CAD, mô phỏng thoát hiểm, QCVN/legal certification.
- Manager sở hữu Movie/Food theo chi nhánh.
- Nhiều Khuyến mãi được stacking.
- Phụ thu dựa trên PresentationFormat.
- Phỏng vấn/khảo sát không có bằng chứng nguồn.
