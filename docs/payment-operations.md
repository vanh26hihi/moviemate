# Payment reconciliation operations

Automatic reconciliation processes only `pending`, `processing`, and `unresolved` ZaloPay attempts within their reconciliation window. The scheduled `payments:query-pending` command deliberately excludes `review` payments.

`review` is an operator-controlled state. An authorized manager or administrator with `bookings.operate` may use **Admin → Payment review** to query the existing provider order. The POST action is authenticated, CSRF protected, rate limited, and audited. It never creates a provider order. Only authoritative success for the same payment identity and amount can use the shared verified transition, and that transition re-locks the booking, payment, and reserved seats.

Keep the payment in review when the provider remains uncertain, the amount or transaction identity differs, the booking has expired, or its seats were released or rebooked. These cases must not issue a ticket. Escalate late success and possible refund cases to the payment provider and finance team before changing any operational state.

Do not initiate a replacement payment while any attempt for the same booking and provider is `pending`, `processing`, `unresolved`, or `review`. Resolve the existing attempt and its refund exposure first.

Duplicate callbacks never repair ticket delivery. For a historical `success` payment whose booking is still fully paid but whose outbox row is missing, an active operator with `bookings.operate` must run `php artisan payments:recover-ticket-delivery PAYMENT_ID --actor=USER_ID`. The command locks and revalidates the payment and booking, creates only the missing pending delivery row, and logs the operator/payment/booking IDs. It never queries or creates a provider order.
