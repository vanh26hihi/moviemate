# Payment reconciliation operations

## Migration and rollback support policy

The supported database lifecycle is:

- Fresh install: supported.
- Forward upgrade from an existing MovieMate schema: supported. The migration chain includes `2026_08_04_124000_add_ticket_email_access_credentials_to_bookings` and the forward rollback guard `2026_08_04_125000_guard_phase4_rollback_data`.
- Exact Phase-4 batch rollback (`100000` through `125000`): supported only when the guard confirms that bookings, booking seats, payments, orders, order items, ticket deliveries, and payment review events are empty. A refusal is a safety result, not a request to delete data.
- Rollback with protected business data: refused. Archive and reconcile the data, or roll forward. Never delete or null business history merely to make a rollback pass.
- Full historical rollback: unsupported. Older migrations predate the Phase-4 safety contract and may intentionally refuse referenced room-layout or booking-seat history. Restore a tested database backup or deploy a forward compatibility migration instead.

Migration `115000` is checksum-immutable. Do not invoke its `down()` directly. Its safe rollback boundary is the exact Phase-4 batch, where `125000` runs first and refuses protected data before `115000` can execute. If MySQL stopped during `115000` after auto-committing only part of its DDL, stop the deployment and compare `payments` and `booking_ticket_deliveries` with a clean rehearsal schema; repair the schema under an approved change plan before retrying. Do not repeatedly run the immutable migration against an unknown partial state.

All destructive MySQL rehearsals must use only `moviemate_phase4_rehearsal`. The test bootstrap and `DatabaseSafetyGuard` hard-refuse `moviemate` and every other MySQL database name. In PowerShell, set the database explicitly and run the guarded suite:

```powershell
$env:DB_DATABASE = 'moviemate_phase4_rehearsal'
php artisan optimize:clear
vendor\bin\phpunit -c phpunit.mysql.xml
```

Never run `migrate:fresh`, `migrate:rollback`, a migration object's `down()`, or manual schema DDL against the primary `moviemate` database. Primary verification is read-only: confirm booking and payment counts and the absence of Phase-4 migration rows before and after rehearsal.

Automatic reconciliation processes only `pending`, `processing`, and `unresolved` ZaloPay or VNPAY attempts within their reconciliation window. The scheduled `payments:query-pending` command deliberately excludes `review` payments.

`review` is an operator-controlled state. An authorized manager or administrator with `bookings.operate` may use **Admin → Payment review** to query the existing provider order. The POST action is authenticated, CSRF protected, rate limited, and audited. It never creates a provider order. Only authoritative success for the same payment identity and amount can use the shared verified transition, and that transition re-locks the booking, payment, and reserved seats.

Keep the payment in review when the provider remains uncertain, the amount or transaction identity differs, the booking has expired, or its seats were released or rebooked. These cases must not issue a ticket. Escalate late success and possible refund cases to the payment provider and finance team before changing any operational state.

Do not initiate a replacement payment while any attempt for the same booking and provider is `pending`, `processing`, `unresolved`, or `review`. Resolve the existing attempt and its refund exposure first.

Duplicate callbacks never repair ticket delivery. For a historical `success` payment whose booking is still fully paid but whose outbox row is missing, an active operator with `bookings.operate` must run `php artisan payments:recover-ticket-delivery PAYMENT_ID --actor=USER_ID`. The command locks and revalidates the payment and booking, creates only the missing pending delivery row, and logs the operator/payment/booking IDs. It never queries or creates a provider order.

## VNPAY Sandbox setup and operations

Register a Sandbox merchant with VNPAY and obtain the merchant `TmnCode` and `HashSecret`. Never commit either credential or paste it into logs, tickets, screenshots, or diagnostic output. Set `PAYMENT_DRIVER=vnpay`, `VNPAY_ENVIRONMENT=sandbox`, `VNPAY_TMN_CODE`, and `VNPAY_HASH_SECRET` in the deployed environment. Keep the supplied Sandbox payment and QueryDr URLs unless VNPAY explicitly changes them. `VNPAY_BANK_CODE` may be blank to let the customer select a channel on VNPAY; the default `VNPAYQR` opens the QR flow.

VNPAY must reach two public MovieMate endpoints:

- Return URL: `https://YOUR_HOST/payments/vnpay/return`
- IPN URL: `https://YOUR_HOST/payments/vnpay/ipn`

For local Sandbox testing, expose the application through an HTTPS tunnel and set `APP_URL` to its stable public origin before caching configuration. Register the exact Return and IPN URLs in the VNPAY Sandbox portal. Do not use localhost, a loopback address, an HTTP production origin, or a tunnel belonging to an untrusted account. Production additionally requires `VNPAY_ENVIRONMENT=production`, HTTPS provider URLs, and the public host in `PAYMENT_PUBLIC_HOSTS`.

After every environment change, run:

```powershell
php artisan optimize:clear
php artisan payments:vnpay-config
php artisan config:cache
```

The diagnostic command prints only environment labels, credential-presence booleans, and URL hosts. It never prints the `HashSecret`, signatures, full payment URLs, or provider payloads. Restart web, scheduler, queue, and command workers after rebuilding the configuration cache.

Use only VNPAY's current Sandbox test cards from the merchant documentation. A commonly documented NCB Sandbox card is `9704198526191432198`, cardholder `NGUYEN VAN A`, expiry `07/15`, OTP `123456`; verify it remains current in the Sandbox portal before testing. Do not store test card data in MovieMate.

The browser Return URL is display-only and cannot mark a booking paid. The unauthenticated IPN verifies the HMAC-SHA512 checksum before database access, and QueryDr verifies its response checksum before applying an outcome. Only a verified `00` response code plus `00` transaction status, matching merchant, reference, amount, and transaction identity can enter the shared locked fulfillment transition. Unknown, malformed, late, conflicting, or checksum-invalid outcomes never issue a ticket. Do not retry a booking while its existing attempt is `pending`, `processing`, `unresolved`, or `review`.

## HTTPS tunnel, reverse proxy, and browser smoke test

Laravel must trust the proxy that directly connects to PHP before it may use `X-Forwarded-For`, `X-Forwarded-Host`, `X-Forwarded-Port`, or `X-Forwarded-Proto`. MovieMate defaults to the loopback proxy addresses used by a local ngrok agent:

```dotenv
APP_URL=https://your-random-domain.ngrok-free.dev
PAYMENT_PUBLIC_HOSTS=your-random-domain.ngrok-free.dev
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
TRUSTED_PROXIES=127.0.0.1,::1
TRUSTED_PROXIES_ALLOW_LOCAL_WILDCARD=false
TRUSTED_HOSTS=
```

`TRUSTED_PROXIES` accepts explicit IP addresses and CIDR ranges. Production must contain only the addresses or ranges of infrastructure-controlled proxies. `*`, `**`, `REMOTE_ADDR`, `0.0.0.0/0`, and `::/0` are ignored unless `APP_ENV=local` and `TRUSTED_PROXIES_ALLOW_LOCAL_WILDCARD=true` are both explicitly set. Prefer the loopback list for local ngrok. The effective request host is then checked against exact hosts from `APP_URL`, `PAYMENT_PUBLIC_HOSTS`, and `TRUSTED_HOSTS`; arbitrary Host and forwarded-host values are rejected. Do not put a temporary tunnel hostname in committed source.

For plain local HTTP, keep `APP_URL=http://localhost` and `SESSION_SECURE_COOKIE=false`. Leave `SESSION_DOMAIN` empty so the browser creates a host-only cookie. For an HTTPS tunnel, set the secure cookie to `true`; `SameSite=lax` supports normal navigation and the top-level VNPAY return GET. Do not mix localhost HTTP and ngrok HTTPS in one booking/login flow because they are different origins with different cookies.

Use compiled assets for a one-tunnel browser test. An HTTP Vite development server (normally port 5173) cannot be loaded by an HTTPS page and will cause mixed content. Stop `npm run dev`, ensure its generated `public/hot` marker is gone, and run `npm run build`. If HMR is genuinely required, it needs its own correctly configured public HTTPS/WSS endpoint; never suppress the browser warning or downgrade the application page.

Start and configure a local tunnel in this order:

1. Start Laravel with `php artisan serve`.
2. Start ngrok with `ngrok http 8000`.
3. Copy the HTTPS forwarding hostname.
4. Update the local, uncommitted `.env` values shown above. A restarted free tunnel may have a different hostname, requiring both `APP_URL` and `PAYMENT_PUBLIC_HOSTS` to change.
5. Stop an HTTP Vite dev server and run `npm run build`.
6. Run `php artisan optimize:clear`, then restart Laravel and any queue/scheduler workers.
7. Run `php artisan app:https-diagnostics` and `php artisan payments:vnpay-config`.
8. Register the exact HTTPS Return and IPN URLs with VNPAY, then open only the HTTPS ngrok origin for this flow.

The free ngrok browser interstitial may appear once. Click **Visit Site** in the test browser. It is separate from Laravel and must not be bypassed by adding `ngrok-skip-browser-warning` to customer forms, payment URLs, or callbacks. Test the server-to-server IPN independently.

The `app:https-diagnostics` command is read-only. It reports the public URL, generated login/Return/IPN URLs, sanitized proxy mode, cookie policy, host allow-list result, configuration-cache state, and whether Vite hot mode is active. It never prints application/payment/mail secrets, cookies, guest capabilities, or signatures. Fix every warning before a public payment test.

### Manual HTTPS checklist

- Authentication: home and login load over HTTPS; login and registration form actions are HTTPS; valid and invalid login, session persistence, logout, profile access, and role redirects work without 419 or a downgrade.
- Customer booking: movie/showtime pages load; normal, VIP, and atomic couple seats work; food can be selected or skipped; review shows the server total; VNPAY opens Sandbox; Return comes back to a clean HTTPS URL; pending/success/review and scoped guest access render correctly.
- Manager/staff: admin login and role middleware work; movie, room/layout, showtime, food, user/role, payment-review, and ticket-check pages submit to the HTTPS origin.
- Negative checks: no form action, redirect, script, stylesheet, media URL, or XHR uses HTTP/localhost; no mixed-content block, session loss, CSRF 419, or redirect loop occurs; `/booking/store` still returns 410; browser Return alone never marks a payment paid; no payment secret or signed payload appears in HTML or logs.
- Network check: confirm the session cookie is `Secure`, `HttpOnly`, `SameSite=Lax`, path `/`, and has no localhost domain on the tunnel host. Confirm the VNPAY IPN response does not set or require a session cookie.

Password reset, email verification, profile update/password update, booking cancellation, and dedicated admin/staff login POST routes are not currently implemented in the active route set. Add HTTPS contract coverage when those features are introduced; do not treat inactive UI-demo templates as production endpoints.

## Production ticket email transport

MovieMate provisions `smtp` as the only production leaf transport by default. Set `MAIL_PRODUCTION_ALLOWED_TRANSPORTS=smtp`; add another Laravel delivery leaf only after its required package and delivery mechanism have been explicitly provisioned and its named mailer exists in `config/mail.php`. Values are comma-separated transport identities, not mailer names. Every mailer name must be a simple, non-dotted identifier containing only letters, numbers, hyphens, and underscores. Unknown/custom resolvers cannot be approved by this allow-list because their behavior cannot be inspected from configuration. `log` and `array` are forbidden in every production delivery branch; `null`, `failover`, and `roundrobin` can never be allowed as leaves.

The application recursively validates the selected named mailer at production boot and again before the ticket outbox command queries or claims a row. A `failover` or `roundrobin` mailer is accepted only when every reachable branch ends at an approved leaf. Never configure a `log` or `array` fallback: besides not delivering a message, those transports can expose the guest ticket access fragment in process memory or logs.

After changing any mail environment value, rebuild the configuration cache and restart every web, scheduler, and queue/command worker process. In the deployed production environment, run `php artisan about` as the mail configuration health check. It performs no send and exits non-zero during application boot if the selected graph is missing, malformed, cyclic, too deep, or reaches an unapproved transport. Run the check again after rebuilding the config cache and before starting workers.

Guest ticket email uses a dedicated, booking-scoped access credential with its own hash and expiry. The raw token is never stored or placed in a query string: the email carries it in the URL fragment, and the access page exchanges it for a scoped session capability. Ticket-email delivery never rotates `guest_access_token_hash`, so an existing guest checkout/ticket session remains valid when SMTP fails. Authenticated booking-owner and authorized staff access are unchanged.

The first attempt creates the dedicated email credential while the booking and outbox are locked. Retry attempts reproduce and reuse that credential while it remains valid; they replace it only when it is expired or invalid. Duplicate payment callbacks neither create nor rotate it.

Ticket delivery is at-least-once. An approved SMTP server can accept a message and then lose or delay the acknowledgement, causing MovieMate to retain the row as retryable even though the email may already have been delivered. The stable email credential keeps every retry link valid, but operators must not assume exactly-once delivery. Real transport failures retain the outbox row with exponential backoff while booking/payment state and both authenticated-owner and guest-session ticket access remain unchanged. The scheduled `bookings:send-pending-tickets` command retries due rows. If a historical paid booking has no outbox row, use the explicit `payments:recover-ticket-delivery` procedure above; duplicate payment callbacks are not a recovery mechanism.
