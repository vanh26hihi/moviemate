# Payment reconciliation operations

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
