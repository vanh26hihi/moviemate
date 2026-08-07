# MovieMate — Final acceptance checklist

`[x]` nghĩa là đã xác minh bằng source/test/static gate hoặc browser QA được ghi rõ. Các mục phần cứng/provider thật để `[ ]` cho đến khi được thực hiện ngoài môi trường phát triển.

## ENVIRONMENT

- [x] Branch `main`, không tạo worktree/branch phụ.
- [x] `APP_ENV=local`, MySQL loopback, database đúng `moviemate`, không có `DB_URL` override trước mutation demo.
- [x] Pre-audit Git bundle được verify ngoài repository.
- [x] Không đọc/ghi `.env` hoặc in secret.

## DATABASE

- [x] Không migration pending; `migrate --pretend` không có thao tác.
- [x] FK/index cho booking, seat locks, payment attempts, assignments, ticket operations và templates được audit.
- [x] 10 truy vấn integrity R10 không phát hiện anomaly.
- [x] Không chạy fresh/wipe/truncate/rollback/reseed phá dữ liệu.

## CUSTOMER

- [x] Movie-first và cinema-first dùng showtime/branch phía server.
- [x] D6 + D8 bị client phản hồi và server policy từ chối; D6+D7+D8 hợp lệ.
- [x] Aisle, sparse gap và couple pair được test.
- [x] Đồ ăn là tùy chọn, 0 món hợp lệ, state ghế/đồ ăn được giữ.
- [x] Browser amount/cinema/food giả không quyết định aggregate.
- [x] Demo Customer local/testing được seed có chủ ý.

## PAYMENTS

- [x] VNPAY chỉ finalize qua IPN đã xác minh.
- [x] ZaloPay chỉ finalize qua callback/query có MAC và đối chiếu dữ liệu.
- [x] payOS webhook/query kiểm tra chữ ký, order, amount, currency và idempotency.
- [x] Return/cancel browser đơn thuần không đánh dấu paid hoặc giải phóng ghế.
- [x] Counter cash chỉ dành cho booking quầy và lấy settler từ session.
- [x] Không có generic manual mark-paid action.

## TICKETS

- [x] Outbox tạo một delivery cho success có recipient; không recipient không tạo false failure.
- [x] Email render có code/rạp/suất/ghế/link/QR và không có secret/print instruction.
- [x] Capability exact-booking, chống tamper/substitution; used ticket vẫn đọc được.
- [x] Trang khách không có Print/PDF/Save image/Admin/Staff operation.
- [x] QR không phải booking code thô.

## STAFF

- [x] Workspace quầy/ticket tách khỏi Admin portal.
- [x] Counter sale, optional food, cash settlement và cancellation có permission/scope.
- [x] Resolve preview read-only; manual scanner fallback tồn tại.
- [x] Print state machine và check-in state machine độc lập.
- [x] Creator, settler, printer và checker lấy từ authenticated actor.

## MANAGER

- [x] Chỉ thấy và thao tác các chi nhánh được gán; hỗ trợ nhiều assignment.
- [x] Direct foreign branch URL/POST bị từ chối.
- [x] Report chỉ gồm các chi nhánh được gán.
- [x] Không quản lý security/global role ngoài quyền.

## ADMIN

- [x] Global Admin thấy toàn chuỗi và báo cáo all-branch.
- [x] Quản lý chi nhánh, nội dung, user/assignment và audit theo permission.
- [x] Sidebar có đúng một active destination trên trang đại diện.
- [x] Hủy showtime là non-destructive và bị chặn khi đã có booking history.

## SECURITY

- [x] Route mutation có web/CSRF trừ callback/webhook exemption chính xác.
- [x] Auth/active/role/permission và service-level cinema scope được audit.
- [x] Không secret pattern thực tế trong tracked source; password cứng duy nhất là local demo seeder đã phê duyệt.
- [x] Activity log sanitizer lọc token, signature, payload và URL nhạy cảm.
- [x] Không customer PII/capability trong aggregate report hoặc Admin ticket event views.

## REPORTS

- [x] Finance online dùng `verified_at`, counter dùng `settled_at`.
- [x] Operations dùng ngày bắt đầu suất theo timezone chi nhánh.
- [x] Một success/booking, logical ticket/physical seat và archived history được kiểm thử.
- [x] Channel/provider, print/check-in và creator/settler được tách.
- [x] Query count dashboard/report nằm trong budget.

## HARDWARE/EXTERNAL

- [ ] Quét QR bằng camera vật lý trên thiết bị trình bày.
- [ ] Mở print dialog và in bằng máy in vật lý.
- [ ] Kiểm tra QR trong email client thật.
- [ ] Thanh toán thật bằng payOS test merchant.
- [ ] Đăng ký và nhận payOS webhook trên public HTTPS endpoint.
- [ ] Hủy giao dịch ở provider payOS và xác minh seat release.

## GIT

- [x] Chỉ stage file R10 được liệt kê rõ.
- [x] Không stage `.env`, audit notes, `public/build`, log, upload, database hoặc backup.
- [x] Không push.

## DEMO

- [x] Có 3 chi nhánh, 3 định dạng phòng, published layouts, pricing, food và future showtimes.
- [x] Có phòng `DEMO` với D6/D7/D8, aisle và couple pair.
- [x] Có Admin, Customer, Manager và Staff local/testing accounts.
- [x] Không seed fake successful VNPAY/ZaloPay/payOS.
- [x] Có outline 10 slide, use-case count, script 15 phút và talk track.
- [ ] Tổng duyệt bằng đúng laptop, browser profiles, camera, printer và mạng sẽ dùng khi bảo vệ.
