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

Automatic reconciliation processes only `pending`, `processing`, and `unresolved` ZaloPay attempts within their reconciliation window. The scheduled `payments:query-pending` command deliberately excludes `review` payments.

`review` is an operator-controlled state. An authorized manager or administrator with `bookings.operate` may use **Admin → Payment review** to query the existing provider order. The POST action is authenticated, CSRF protected, rate limited, and audited. It never creates a provider order. Only authoritative success for the same payment identity and amount can use the shared verified transition, and that transition re-locks the booking, payment, and reserved seats.

Keep the payment in review when the provider remains uncertain, the amount or transaction identity differs, the booking has expired, or its seats were released or rebooked. These cases must not issue a ticket. Escalate late success and possible refund cases to the payment provider and finance team before changing any operational state.

Do not initiate a replacement payment while any attempt for the same booking and provider is `pending`, `processing`, `unresolved`, or `review`. Resolve the existing attempt and its refund exposure first.

Duplicate callbacks never repair ticket delivery. For a historical `success` payment whose booking is still fully paid but whose outbox row is missing, an active operator with `bookings.operate` must run `php artisan payments:recover-ticket-delivery PAYMENT_ID --actor=USER_ID`. The command locks and revalidates the payment and booking, creates only the missing pending delivery row, and logs the operator/payment/booking IDs. It never queries or creates a provider order.

## Production ticket email transport

MovieMate provisions `smtp` as the only production leaf transport by default. Set `MAIL_PRODUCTION_ALLOWED_TRANSPORTS=smtp`; add another Laravel delivery leaf only after its required package and delivery mechanism have been explicitly provisioned and its named mailer exists in `config/mail.php`. Values are comma-separated transport identities, not mailer names. Every mailer name must be a simple, non-dotted identifier containing only letters, numbers, hyphens, and underscores. Unknown/custom resolvers cannot be approved by this allow-list because their behavior cannot be inspected from configuration. `log` and `array` are forbidden in every production delivery branch; `null`, `failover`, and `roundrobin` can never be allowed as leaves.

The application recursively validates the selected named mailer at production boot and again before the ticket outbox command queries or claims a row. A `failover` or `roundrobin` mailer is accepted only when every reachable branch ends at an approved leaf. Never configure a `log` or `array` fallback: besides not delivering a message, those transports can expose the guest ticket access fragment in process memory or logs.

After changing any mail environment value, rebuild the configuration cache and restart every web, scheduler, and queue/command worker process. In the deployed production environment, run `php artisan about` as the mail configuration health check. It performs no send and exits non-zero during application boot if the selected graph is missing, malformed, cyclic, too deep, or reaches an unapproved transport. Run the check again after rebuilding the config cache and before starting workers.

Guest ticket email uses a dedicated, booking-scoped access credential with its own hash and expiry. The raw token is never stored or placed in a query string: the email carries it in the URL fragment, and the access page exchanges it for a scoped session capability. Ticket-email delivery never rotates `guest_access_token_hash`, so an existing guest checkout/ticket session remains valid when SMTP fails. Authenticated booking-owner and authorized staff access are unchanged.

The first attempt creates the dedicated email credential while the booking and outbox are locked. Retry attempts reproduce and reuse that credential while it remains valid; they replace it only when it is expired or invalid. Duplicate payment callbacks neither create nor rotate it.

Ticket delivery is at-least-once. An approved SMTP server can accept a message and then lose or delay the acknowledgement, causing MovieMate to retain the row as retryable even though the email may already have been delivered. The stable email credential keeps every retry link valid, but operators must not assume exactly-once delivery. Real transport failures retain the outbox row with exponential backoff while booking/payment state and both authenticated-owner and guest-session ticket access remain unchanged. The scheduled `bookings:send-pending-tickets` command retries due rows. If a historical paid booking has no outbox row, use the explicit `payments:recover-ticket-delivery` procedure above; duplicate payment callbacks are not a recovery mechanism.
