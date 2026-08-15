# MovieMate — checklist rehearsal có thể thực thi

Đây là checklist duy nhất cho fixture và rehearsal trước bảo vệ. Tất cả định danh dùng mã nghiệp vụ (`CG`, `DEMO`, email, Promotion code, Booking code do command báo), không dùng numeric database ID. Mật khẩu chỉ lấy từ `DEMO_ACCOUNTS.md`.

## 1. Trước khi reset local demo DB

- [ ] Xác nhận đang ở branch `main`, đúng HEAD được đội chấp nhận và source status chỉ có thay đổi đã biết.
- [ ] Xác nhận database là **local/demo disposable**. Không chạy lệnh reset với production hoặc database dùng chung.
- [ ] Xác nhận demo chính không dùng callback VNPAY/PayOS/ZaloPay live.
- [ ] Xác nhận browser/profile, camera tùy chọn và print preview hoạt động; máy in vật lý không phải điều kiện hoàn tất demo.
- [ ] Không tạo database dump và không sửa `.env` để phục vụ rehearsal.

```powershell
git branch --show-current
git rev-parse HEAD
git status --short
```

## 2. Fresh seed được phê duyệt cho local/demo

> CẢNH BÁO: lệnh dưới đây xóa toàn bộ dữ liệu database đang cấu hình. Chỉ chạy khi đã xác nhận đó là local/demo disposable; tuyệt đối không chạy với production/shared DB.

```powershell
php artisan migrate:fresh --seed
php artisan moviemate:demo-check
php artisan test --filter DemoReadinessTest
php artisan test --filter FinalGoldenPathIntegrationTest
php artisan schedule:list
```

Kết quả bắt buộc của `moviemate:demo-check` là exit code `0` và dòng `DEMO READY`. Command chỉ đọc: không seed, không sửa, không giữ ghế, không reserve Promotion, không gọi HTTP và không in QR/token. Nếu command báo lỗi, dừng rehearsal; không tự sửa row trong DB.

## 3. Truth sau fresh seed

- [ ] Accounts active: Global Admin, Manager `CG`, Staff `CG`, Customer; Manager/Staff được gán active đúng MovieMate Cầu Giấy.
- [ ] Branch chính là `CG` — MovieMate Cầu Giấy; Room chính là `DEMO` — Phòng demo bảo vệ.
- [ ] Room hiển thị kích thước vật lý, diện tích, RoomType; published RoomLayout có lưới logic, SEAT, AISLE, BLOCKED, EMPTY, Couple và Seat bảo trì đúng output command.
- [ ] PRIMARY và FALLBACK Showtime đều được command báo customer-bookable, ghim published layout, có exact PresentationFormat và frozen `ShowtimeTicketPrice`.
- [ ] Booking cutoff được đọc từ output: `START + 15 phút`; không kể sai thành “phải đặt trước START”.
- [ ] `MOVIEMATE10` active, không first-order-only, không quota mong manh và quote hợp lệ cho Customer.
- [ ] Food active có trong output; ảnh remote không phải điều kiện hoàn thành workflow.
- [ ] Paid Booking do command báo thuộc Customer/CG, có Food snapshot, redeemed `MOVIEMATE10`, Payment authoritative và số tiền khớp.
- [ ] AdmissionTicket và FoodPickupVoucher đều tồn tại, printed count bằng `0`.
- [ ] Lấy report Cinema/date trực tiếp từ dòng `report filter` của Payment evidence; không ghi nhớ ngày lịch cố định.

## 4. Mười lăm phút trước bảo vệ

Chạy lại, không fresh-seed nếu team đã quyết định giữ dữ liệu hiện tại:

```powershell
php artisan moviemate:demo-check
php artisan schedule:list
git status --short
```

- [ ] PRIMARY vẫn customer-bookable; FALLBACK vẫn xuất hiện.
- [ ] Mở seat map PRIMARY và chọn trong các ghế đang hiển thị khả dụng; không coi một tọa độ là “free mãi mãi”.
- [ ] Không có pending hold vô tình trên ghế dự định chọn. Nếu có, chọn ghế khả dụng khác hoặc dùng expiration hiện hữu; không xóa row.
- [ ] `MOVIEMATE10` vẫn hợp lệ; không dùng `WELCOME20K` cho Customer đã có paid history.
- [ ] Staff `CG` tra cứu được đúng Booking code output; foreign-branch lookup vẫn bị chặn.
- [ ] Paid fixture vẫn có Payment verified/settled evidence và amount khớp Booking.
- [ ] AdmissionTicket/FoodPickupVoucher printed count vẫn `0`; nếu command non-zero thì first-print đã bị tiêu thụ.
- [ ] Report filter dùng Cinema/date vừa được command báo.

## 5. Browser/session setup và role switch

Chuẩn bị ba profile/cửa sổ độc lập để session Cinema không lẫn nhau. Logout/login đúng profile; không impersonation và không tái dùng session Manager cho Staff.

1. **Manager profile:** đăng nhập Manager `CG` → Tổng quan chi nhánh → Room `DEMO` → Sơ đồ/Bảo trì → suất chiếu sắp tới → chi tiết Showtime.
2. **Customer profile:** Phim → Showtime PRIMARY → ghế đang khả dụng → Food → nhập `MOVIEMATE10` → Review/payment selection. Dừng trước bước cần provider live.
3. **Customer profile:** mở Đơn đặt vé paid đã được command báo và nói rõ: “Để demo không phụ thuộc callback mạng, phần sau dùng Booking đã có verified payment evidence.” Không nói đây là Booking vừa tạo nếu không phải.
4. **Staff profile:** Tra cứu & in đơn → nhập Booking code (hoặc quét QR đơn) → Payment evidence → RoomType/PresentationFormat → Print All preview.
5. **Manager profile:** từ Showtime mở Booking liên quan → Payment; sau đó mở Báo cáo và dùng report filter do command báo.

Role sequence: **Manager → Customer → Staff → Manager**, ba lần chuyển có ý nghĩa. Mọi bước đi qua navigation/handoff có sẵn; số bước nhập URL thủ công là `0`.

## 6. Chính sách mutation khi rehearsal

| State | Rehearsal có thể đổi? | Cách bảo vệ/reset | Trạng thái cuối mong đợi |
|---|---:|---|---|
| Seat hold | Có | Chọn ghế khác, chờ `bookings:expire-pending`, hoặc fresh seed local trước defense | Không cản ghế trình bày |
| Promotion reservation | Có | Dùng `MOVIEMATE10`; không xóa `BookingPromotion`; fresh seed local nếu cần | Valid, không quota mong manh |
| Booking creation/history | Có | Dừng ở payment selection; không dùng first-order Promotion | Paid fixture vẫn độc lập |
| Payment | Không trong demo chính | Chuyển sang paid fixture; không mark paid thủ công | Evidence authoritative |
| AdmissionTicket/Food voucher print count | Có, không hoàn tác runtime | Rehearsal thường dừng trước xác nhận print; fresh seed trước defense nếu đã in | First print = `0` |
| Review | Có | Không submit Review trong luồng 8 phút; để viva | Không chặn review demo khác |
| Incident/maintenance | Có nếu resolve/relocate | Chỉ xem Seat bảo trì/incident context; không resolve/relocate fixture | Evidence vận hành còn nguyên |

Không được: live-edit seed DB, tự mark Payment paid, xóa print history, sửa PriceBook, phát hành layout version tùy tiện, dùng first-order Promotion với Customer đã dùng, hoặc bắt buộc live provider.

## 7. Failure plan

| Sự cố | Fallback đúng sản phẩm |
|---|---|
| PRIMARY không còn phù hợp | Chọn FALLBACK từ Room/danh sách Showtime; chạy lại demo-check, không dùng numeric ID |
| Ghế dự định chọn không khả dụng | Chọn ghế khác đang highlight khả dụng; không xóa hold |
| Promotion bị từ chối | Kiểm tra code/gross trên Review; dùng paid fixture cho hậu thanh toán; không sửa usage |
| Provider hoặc Internet không sẵn sàng | Dừng ở payment selection và chuyển sang paid Booking verified fixture |
| First-print đã bị tiêu thụ | Không xóa audit; fresh-seed local trong reset window hoặc chỉ trình bày reprint đúng reason |
| OS print dialog/máy in không ổn định | Mở print-ready/preview nhưng không tuyên bố đã in thành công |
| Camera không sẵn sàng | Nhập Booking code trong cùng Staff lookup form |
| Report trống | Đọc lại Cinema/date từ Payment evidence output; không dùng Booking created date/Showtime date |
| Poster/backdrop remote lỗi | Dùng Movie title, Showtime và navigation text; không cần TMDB/network để hoàn tất |

## 8. Evidence matrix và thời lượng

| Demo step | Fixture semantic | Invariant chứng minh | Fallback |
|---|---|---|---|
| Branch360 → Room | `CG` / `DEMO` | branch-first; physical Room khác logical grid | Room cùng branch nếu UI cần |
| Room → Layout/Bảo trì | published `DEMO` layout | AISLE/BLOCKED/EMPTY khác Seat maintenance; Couple là pair | Xem-only, không mutate incident |
| Room → Showtime | PRIMARY | pinned layout, exact Format, START/END/ROOM_READY, frozen prices | FALLBACK |
| Customer checkout | ghế khả dụng + Food + `MOVIEMATE10` | server pricing, một Promotion trên ticket + food gross | Dừng ở Review |
| Customer paid detail | Booking code từ command | Booking QR chỉ để Staff lookup; Payment evidence authoritative | counter paid fixture nếu được revalidate |
| Staff counter/Print All | cùng paid Booking | one Seat → one AdmissionTicket; one Food voucher/Booking; first print/reprint audit | preview hoặc reasoned reprint |
| Manager finance | Payment date/Cinema từ command | report dựa trên verified/settled Payment | mở Payment detail rồi đặt lại filter |

Thời lượng mục tiêu: Manager `2:45`, Customer `2:30`, Staff `2:05`, Finance kết luận `1:10`; tổng `8:30`. Đoạn TV4 Branch360 → Room → Layout/Bảo trì → Showtime là `2:15` (mục tiêu 2–3 phút).

## 9. Reset window cuối

Fresh seed vào tối hôm trước hoặc sáng ngày bảo vệ khi database đã được xác nhận là local/demo disposable. Sau đó chỉ rehearsal không phá hủy: không hoàn tất provider live, không xác nhận first print, không submit Review và không resolve/relocate incident. Chạy readiness check cuối 15 phút trước khi trình bày.
