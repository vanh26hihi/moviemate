# MovieMate — Defense readiness audit R10

Audit target: `main` at starting HEAD `5cc7d6e2ce12ad2f4d7a6105be4fff7e1b02a02e`. Product boundary: one MovieMate cinema-chain operator with multiple branches, serving the stated 16–40 target audience; not a marketplace and not multi-company SaaS. Presentation scope: exactly 5 human actors and 32 business-level use cases, mapped in `USE_CASE_SUMMARY.md`.

Statuses: `PASS`, `PASS_WITH_MANUAL_ACCEPTANCE`, `NOT_APPLICABLE`, `BLOCKER`.

## Teacher-feedback matrix

| Teacher requirement | Implementation | Relevant route/service/model | Automated test | Manual QA | Status | Remaining limitation |
|---|---|---|---|---|---|---|
| Multi-branch chain scope | Cinema ownership and user assignments scope operational data | `CinemaAccessService`, `Cinema`, `UserCinemaAssignment` | `MultiCinemaAuthorizationTest`, `MultiCinemaMutationIsolationTest` | Chưa tổng duyệt đa cửa sổ; automated scope tests green | PASS | None found |
| Five human actors | Guest, Customer, Staff, Manager, Global Admin; demo Customer added | `DemoUserSeeder`, RBAC seeders | `DemoReadinessTest`, `RbacSeederTest` | Separate public/admin/staff surfaces | PASS | External systems are shown separately |
| Movie-first booking | Branch/date showtime catalog feeds authoritative checkout | `/movies/{slug}`, `PublicShowtimeCatalog`, checkout services | `CustomerMovieDiscoveryFlowTest` | Chưa tổng duyệt browser; automated flow green | PASS | Provider completion depends on test merchant |
| Cinema-first booking | Active cinema directory/detail and branch-scoped showtimes | `/cinemas`, `/cinemas/{cinema:code}`, `CinemaController` | `CustomerCinemaDiscoveryTest` | Chưa tổng duyệt browser; automated flow green | PASS | None found |
| Customer branch preference | Preference ranks discovery only and cannot override checkout | `CustomerCinemaPreference`, `BookingCheckoutService` | discovery and multi-cinema authorization tests | Chưa tổng duyệt browser; forged-input tests green | PASS | None found |
| Seat hold | Database-backed active lock and expiration | `BookingCheckoutService`, `BookingExpirationService`, `booking_seats` guard | `ActiveSeatLockTest`, expiration tests | Countdown shown in checkout | PASS | Clock accuracy depends on client clock only for display; server deadline governs |
| D6+D8 seat gap | Shared seat policy plus JS feedback; dedicated local room has D6/D7/D8 | `SeatSelectionPolicy`, `seat-gap-guard.js`, room `DEMO` | `SeatGapCheckoutTest`, `DemoReadinessTest` | Chưa click browser; chính layout seed được chạy qua server policy | PASS | None found |
| Aisle/sparse/couple seats | Segment-aware policy and two-half couple validation | seat policy/layout geometry/pricing | seat unit and gap tests | Demo room includes aisle and C1+C2 pair | PASS | None found |
| Optional food flow | Zero-food canonical state; branch pricing and state persistence | `/booking/food`, `BookingFoodService` | food persistence/security/UI tests | Chưa tổng duyệt browser; persistence tests green | PASS | None found |
| Server-authoritative totals | Price/food/branch/actor are derived, forged fields rejected | `TicketPricingService`, checkout requests/services | checkout, pricing, counter tests | Review page displays server total | PASS | None found |
| VNPAY safety | Signed request; only validated IPN fulfils | VNPAY gateway/IPN service/routes | `VnpayPaymentFlowTest` and signer/config tests | Provider option rendered | PASS | Real merchant round trip not part of local release |
| ZaloPay safety | MAC callback/query verification and attempt-scoped return | ZaloPay services/callback/return | callback/return/production config tests | Provider option rendered | PASS | Real merchant round trip not part of local release |
| payOS safety | Signed webhook/query, exact order/amount/currency, idempotency | payOS gateway/webhook/return/cancel | PayOS initiation/webhook/return/query-count tests | Provider option rendered | PASS_WITH_MANUAL_ACCEPTANCE | Credentials absent; public HTTPS app endpoint reachable, but webhook registration and real payment/cancellation remain unaccepted |
| Browser return safety | Return alone cannot mark paid, issue ticket or release seats | provider return controllers/services | VNPAY, ZaloPay and payOS return tests | Pending UI has no ticket action | PASS | None found |
| Terminal seat release | Only verified cancel/failure/expiration releases eligible locks | provider finalizers, cancellation/expiration services | provider cancellation and booking expiration tests | State messaging inspected | PASS | Refund workflow intentionally out of scope |
| Counter cash | Counter booking only; actual settler; no external HTTP | `CounterCashPaymentService` | `CounterSalesR8Test` | Staff counter flow | PASS | None found |
| Ticket email | Idempotent outbox, secure fragment capability, safe retry | `TicketDeliveryOutbox`, send command, mailable | outbox/email operation/render tests | Local render covered; mail catcher unavailable | PASS_WITH_MANUAL_ACCEPTANCE | Real email-client QR rendering remains manual |
| Electronic ticket | Ownership/capability, stable opaque QR, invalid-state UI | ticket route, `GuestBookingAccessService`, capability service | guest access/printable ticket tests | Browser ticket surface | PASS | Real email handoff remains manual |
| Staff scanner | Camera UI and manual fallback; resolve is read-only | `/staff/tickets`, `ticket-scanner.js`, resolution service | `TicketOperationsR3Test` | Manual input is tested; physical camera chưa thử | PASS_WITH_MANUAL_ACCEPTANCE | Physical camera not exercised |
| Hard-ticket printing | Start/success/failure/retry authorization; append-only events | staff print routes/services/models | R3 ticket operations tests | Workflow tested; print dialog/printer chưa thử | PASS_WITH_MANUAL_ACCEPTANCE | Physical print dialog/printer not exercised |
| Check-in | Atomic used transition, append-only event, duplicate safe | `TicketCheckinService`, check-in event | `TicketCheckinOperationsTest` | Workflow automated green; camera là acceptance riêng | PASS | Physical QR camera remains separate acceptance |
| Independent counter actors | Creator, settler, printer, checker from authenticated sessions | counter, print and check-in services | `CounterSalesR8Test` | Admin booking detail labels inspected | PASS | None found |
| Centralized pricing | Integer VND rule engine with immutable seat snapshots | `TicketPricingService`, `CinemaPricingRule`, `BookingSeat` | `CentralizedPricingTest`, food/counter pricing tests | Price shown on showtime/seat/review | PASS | Legacy showtime price columns retained for compatibility, not authoritative input |
| Operating hours | Per-cinema hours and latest-start validation | operating-hours controller, schedule service | `OperatingHoursAndCleaningTest` | Chưa tổng duyệt browser; validation tests green | PASS | None found |
| Cleaning/overlap | Movie end + room/cinema buffer, cross-midnight comparisons | `ShowtimeScheduleService` | overlap/update/operating-hours tests | Chưa tổng duyệt browser; calculated-window tests green | PASS | None found |
| Layout templates | Standard/VIP/Compact, preview/apply/version/publish | template controllers/services/models | `LayoutTemplateAndMovieLifecycleTest` | Chưa tổng duyệt browser; lifecycle tests green | PASS | None found |
| Historical layout safety | Published immutable; booked seats retained; removed seats retired | `RoomLayoutService` | layout service/access/lifecycle tests | Version history visible | PASS | Draft cells are replaceable by design before publication |
| Movie lifecycle | Draft/coming soon/now showing/inactive/archived; no hard delete | `Movie` lifecycle service/controller | lifecycle tests | Chưa tổng duyệt browser; source/test evidence green | PASS | None found |
| Showtime lifecycle/history | R10 changed legacy DELETE action to non-destructive cancellation and blocks booking history | `ShowtimeController::destroy`, `Showtime` | `ShowtimeUpdateTest` | Chưa click browser; route/view/test evidence green | PASS | Permission/route retain legacy `delete`/`destroy` names for compatibility |
| Reporting definitions | Authoritative finance time, operational date, dedupe, ticket units | report services/routes | `ReportingR9Test` | Admin and Manager reports | PASS | Historical occupancy omitted because no availability snapshot exists |
| Reporting privacy | Aggregates contain no customer PII/capability/provider secrets | report service/views | R9 privacy assertions | Rendered-response assertions; chưa tổng duyệt browser | PASS | None found |
| Global Admin authorization | All branches and global management per permission | admin middleware/RBAC/access service | admin route and multi-cinema tests | Admin navigation/direct pages | PASS | None found |
| Manager authorization | Assigned branches only for branch-owned operations; multi-assignment supported; shared catalog follows explicit RBAC | cinema scope middleware/service | authorization/pricing/report tests | Chưa tổng duyệt đa cửa sổ; cross-branch tests green | PASS | Movie/genre catalog is global and Manager currently has its configured content permissions |
| Staff authorization | Staff workspace only; branch operation permissions | staff middleware and services | admin-route and ticket/counter tests | Staff navigation/direct Admin denial | PASS | None found |
| Direct forged requests | Route + request validation + service scope | controllers/form requests/domain services | authorization, checkout, payment, ticket suites | Automated foreign-scope requests return 403 | PASS | None found |
| Secrets/tokens/logging | Config via environment, masked diagnostics, sanitizer denylist | config/domain config/sanitizer | config, diagnostics, activity-log tests | Static tracked-source audit | PASS | Demo password is intentionally source-defined and local/testing only |
| Database integrity | FK/unique guards and append-only triggers | migrations and MySQL schema | schema/migration safety tests | Ten read-only anomaly queries all zero | PASS | Current local DB has no external-provider success by design |
| Demo data | Three branches, three room formats, layouts, pricing, food, future shows and five actor types | demo seeders | `DemoReadinessTest`, `ShowtimeSeederTest` | Local DB verified; room `DEMO` prepared | PASS | Useful paid ticket is created through real counter flow, never fake provider success |
| Performance | Bounded queries across discovery, seat/ticket/admin/report/template/payment pages | eager-loading/query services | query-budget tests across modules | Automated budgets recorded; no three-window load test | PASS | Main CSS remains large; scanner/QR JS is now lazy-loaded |
| Responsive UX | Mobile/tablet/desktop layouts, overflow wrappers and controls | Blade/Tailwind/CSS | UI source assertions | Chưa tổng duyệt 390px/tablet/desktop trên thiết bị bảo vệ | PASS_WITH_MANUAL_ACCEPTANCE | Physical devices should be included in rehearsal |
| Accessibility quick pass | Labels, named buttons, table headings, live feedback, text chart alternatives | Blade/JS views | UI/accessibility source assertions | Chưa thực hiện keyboard/screen-reader rehearsal đầy đủ | PASS_WITH_MANUAL_ACCEPTANCE | Not a formal WCAG certification |

## Findings and disposition

- **BLOCKER found and fixed:** the legacy showtime DELETE endpoint physically deleted the row and could cascade booking history. It now performs a locked status transition to `cancelled`, retains the showtime, is idempotent, and refuses cancellation when any booking history exists.
- **HIGH:** none remaining.
- **MEDIUM fixed:** `qrcode` and camera scanner dependencies were loaded on every page. They are now dynamically imported only on ticket/scanner pages.
- **MEDIUM fixed:** local demo data lacked a Registered Customer, multiple room formats and an exact seat-gap-friendly room. Seeders now provide them without creating fake provider success.
- **LOW deferred:** legacy permission/route names `showtimes.delete` and `admin.showtimes.destroy` remain for backward compatibility although the UX/action is cancellation.
- **LOW deferred:** Vite may still report large CSS/font assets; no unsafe frontend restructure is justified for defense.
- **FUTURE / out of scope:** refunds, loyalty, inventory, promotions, commissions, marketplace/multi-company and new providers.

## payOS production-acceptance snapshot

- Credentials configured: **no**.
- Public HTTPS `APP_URL` configured: **yes**.
- Configured public application endpoint reachable during audit: **yes**.
- Webhook registered/usable: **no evidence; treat as no**.
- Controlled real payOS payment/cancellation: **not performed**.

Code release is not blocked because provider integration tests are complete; real payOS acceptance remains explicitly manual.

## Final automated evidence

- Focused R10 risk suite: **338 tests, 3,365 assertions**, all passing in **128.995 seconds**.
- Full suite: **959 tests, 6,446 assertions**, all passing in **188.055 seconds**. Compared with R9 (954/6,397), R10 adds three demo-readiness tests and two showtime-history/lifecycle tests; no payment, security or ticket test was removed.
- Application gates: 175 routes compiled, no pending migration in `migrate --pretend`, Blade cache successful and Pint successful.
- Query budgets exercised by the automated suites remain bounded: customer discovery pages at most 25 queries; ticket/admin ticket surfaces at most 30; booking index at most 20; dashboard/report at most 30; template/catalog surfaces at most 30; payOS customer/provider/admin surfaces at most 22.
- Production frontend build succeeded. The previous eager entry asset was 522,419 bytes; the R10 entry is 21.36 kB, with QR support in a 23.46 kB chunk and the camera scanner in a 478.98 kB chunk loaded only on demand.
- Three independent browser profiles, physical camera/printer and a real email client/provider transaction were not exercised during this code audit; they remain rehearsal/manual-acceptance items below.

## Database/ERD notes for defense

- `cinemas` owns `rooms`; assignments connect Manager/Staff users to allowed cinemas.
- A room keeps versioned `room_layouts`; layout cells point to durable `seats`.
- `movies` and rooms produce `showtimes`, each pinned to a published layout.
- `bookings` snapshot cinema/showtime and own `booking_seats`; food orders/items are optional.
- `payments` record provider attempts and authoritative evidence; an active-attempt guard prevents concurrent live attempts.
- Ticket delivery, hard-ticket printing and check-in events are separate workflows.
- Pricing rules and layout templates are reusable configuration; booking-seat snapshots preserve historical price semantics.

## Architecture summary

Browser → Laravel routes/controllers → validation/permissions → domain services → MySQL. Laravel integrates outward with VNPAY/ZaloPay/payOS and an email transport. The server—not browser input—is authoritative for branch, price, actor, seat hold and state transitions; provider verification and immutable snapshots/events preserve evidence.
