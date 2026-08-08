<?php

namespace Tests\Feature\Admin;

use App\Domain\Payments\VnpaySigner;
use App\Exceptions\VnpayTransportException;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\Permission;
use App\Services\Admin\PaymentReconciliationQuery;
use App\Services\Payments\PaymentReconciliationService;
use App\Support\PrivacyMask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Feature\Payments\PaymentTestCase;
use TypeError;

class AdminPaymentOperationsTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_canonical_pages_and_actions_are_permission_protected(): void
    {
        $payment = $this->paymentMatchingBooking();

        $this->get(route('admin.payments.index'))->assertRedirect(route('login'));
        $this->get(route('admin.payments.show', $payment))->assertRedirect(route('login'));
        $this->get(route('admin.payment-reconciliation.index'))->assertRedirect(route('login'));
        $this->post(route('admin.payments.query-provider', $payment))->assertRedirect(route('login'));

        $this->actingAs($this->userWithRole('user'))->get(route('admin.payments.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('staff'))->get(route('admin.payment-reconciliation.index'))->assertForbidden();

        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->get(route('admin.payments.index'))->assertOk();
        $this->actingAs($manager)->get(route('admin.payments.show', $payment))->assertOk();
        $this->actingAs($manager)->get(route('admin.payment-reconciliation.index'))->assertOk();

        $manager->role->permissions()->detach(Permission::query()->where('slug', 'payments.reconcile')->value('id'));
        $manager->unsetRelation('role');
        $this->actingAs($manager)->post(route('admin.payments.query-provider', $payment))->assertForbidden();
    }

    public function test_index_only_displays_authoritative_successes_with_exact_summary_and_safe_output(): void
    {
        $vnpay = $this->successfulPayment('vnpay', 50_000, [
            'raw_request' => ['secret' => 'raw-request-must-stay-hidden'],
            'raw_response' => ['secret' => 'raw-response-must-stay-hidden'],
            'payment_url' => 'https://provider.example.test/signed-secret',
        ]);
        $zalopay = $this->successfulPayment('zalopay', 60_000);
        $payos = $this->successfulPayment('payos', 70_000);
        $counter = $this->successfulCounterPayment(80_000);
        $pending = $this->paymentMatchingBooking(['status' => Payment::STATUS_PENDING]);
        $processing = $this->paymentMatchingBooking(['status' => Payment::STATUS_PROCESSING]);
        $unresolved = $this->paymentMatchingBooking(['status' => Payment::STATUS_UNRESOLVED]);
        $review = $this->paymentMatchingBooking(['status' => Payment::STATUS_REVIEW]);
        $failed = $this->paymentMatchingBooking(['status' => Payment::STATUS_FAILED]);
        $expired = $this->paymentMatchingBooking(['status' => Payment::STATUS_EXPIRED]);
        $staleSuccess = $this->paymentMatchingBooking(['status' => Payment::STATUS_SUCCESS, 'verified_at' => null]);

        $response = $this->actingAs($this->userWithRole('manager'))->get(route('admin.payments.index'));

        $response->assertOk()
            ->assertSee($vnpay->booking->booking_code)->assertSee($zalopay->booking->booking_code)
            ->assertSee($payos->booking->booking_code)->assertSee($counter->booking->booking_code)
            ->assertSee('260.000 VNĐ')->assertSee('Tổng giao dịch')->assertSee('Tổng doanh thu')
            ->assertDontSee('raw-request-must-stay-hidden')->assertDontSee('raw-response-must-stay-hidden')
            ->assertDontSee('signed-secret')->assertDontSee('name="status"', false)
            ->assertDontSee('name="review"', false)->assertDontSee('name="reconciled"', false);
        foreach ([$pending, $processing, $unresolved, $review, $failed, $expired, $staleSuccess] as $hidden) {
            $response->assertDontSee($hidden->booking->booking_code);
        }

        $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.payments.index', ['sort' => 'drop table payments']))
            ->assertSessionHasErrors('sort');

        $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.payments.index', [
                'provider' => 'payos',
                'sales_channel' => 'online',
                'search' => $payos->booking->booking_code,
                'date_from' => today()->toDateString(),
                'date_to' => today()->toDateString(),
            ]))
            ->assertOk()->assertSee($payos->booking->booking_code)
            ->assertDontSee($vnpay->booking->booking_code)->assertDontSee($counter->booking->booking_code);
    }

    public function test_detail_marks_authoritative_payment_and_exposes_only_safe_evidence(): void
    {
        $booking = $this->payableBooking();
        $payment = $this->pendingPayment($booking, ['amount' => (int) $booking->total_amount]);
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))->assertJsonPath('return_code', 1);
        $payment->forceFill([
            'payment_url' => 'https://provider.example.test/private',
            'callback_payload_hash' => str_repeat('a', 64),
            'query_response_hash' => str_repeat('b', 64),
        ])->save();

        $this->actingAs($this->userWithRole('manager'))->get(route('admin.payments.show', $payment))
            ->assertOk()->assertSee('Giao dịch có thẩm quyền')->assertSee('Đã khớp')
            ->assertSee('Đang xem')->assertSee($booking->showtime_label)
            ->assertSee(PrivacyMask::email($booking->recipient_email))
            ->assertDontSee('provider.example.test/private')->assertDontSee(str_repeat('a', 64))
            ->assertDontSee(str_repeat('b', 64))->assertDontSee('Nhật ký thao tác')
            ->assertDontSee('Đánh dấu đã thanh toán')->assertDontSee('Sửa số tiền')
            ->assertDontSee('Xóa giao dịch');
        $this->actingAs($this->userWithRole('manager'))->get(route('admin.payments.index'))
            ->assertOk()->assertSee($booking->booking_code)->assertSee('Nhà cung cấp xác minh');
    }

    public function test_queue_has_deterministic_priority_and_excludes_fresh_matching_pending_attempt(): void
    {
        $fresh = $this->paymentMatchingBooking();
        $mismatch = $this->paymentMatchingBooking([
            'status' => Payment::STATUS_REVIEW,
            'amount' => (int) $fresh->booking->total_amount + 1,
        ]);
        $unresolved = $this->paymentMatchingBooking(['status' => Payment::STATUS_UNRESOLVED]);
        $failedQuery = $this->paymentMatchingBooking([
            'status' => Payment::STATUS_FAILED,
            'failure_reason' => 'query_failed',
        ]);

        $response = $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.payment-reconciliation.index'));

        $response->assertOk()->assertSee('#'.$mismatch->id)->assertSee('Khẩn cấp')
            ->assertSee('Số tiền giao dịch không khớp tổng đơn')
            ->assertSee('#'.$unresolved->id)->assertSee('Kết quả provider chưa xác định')
            ->assertSee('#'.$failedQuery->id)->assertSee('Provider xác nhận giao dịch thất bại qua truy vấn')
            ->assertDontSee('#'.$fresh->id);
    }

    public function test_provider_query_uses_route_bound_payment_ignores_browser_fields_and_writes_one_safe_audit(): void
    {
        $payment = $this->paymentMatchingBooking();
        $manager = $this->userWithRole('manager');
        $service = Mockery::mock(PaymentReconciliationService::class);
        $service->shouldReceive('reconcile')->once()
            ->with(Mockery::on(fn (Payment $selected): bool => $selected->is($payment)))
            ->andReturn(Payment::STATUS_PENDING);
        $this->app->instance(PaymentReconciliationService::class, $service);

        $this->actingAs($manager)->post(route('admin.payments.query-provider', $payment), [
            'payment_id' => 999999,
            'status' => Payment::STATUS_SUCCESS,
            'verified_at' => now()->toIso8601String(),
            'amount' => 1,
            'provider' => 'attacker',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->verified_at);
        $this->assertSame(1, ActivityLog::query()->where('action', 'payment.provider_query_requested')->count());
        $this->assertSame(1, ActivityLog::query()->where('action', 'payment.provider_query_completed')->count());
        $log = ActivityLog::query()->where('action', 'payment.provider_query_completed')->sole();
        $encoded = json_encode($log->toArray());
        $this->assertStringNotContainsString('attacker', $encoded);
        $this->assertStringNotContainsString('999999', $encoded);
    }

    public function test_final_or_failed_actions_cannot_create_false_success_audit(): void
    {
        $final = $this->paymentMatchingBooking(['status' => Payment::STATUS_FAILED]);
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->post(route('admin.payments.query-provider', $final))
            ->assertRedirect()->assertSessionHas('warning');
        $this->actingAs($manager)->post(route('admin.payments.reconcile', $final))
            ->assertRedirect()->assertSessionHas('warning');

        $pending = $this->paymentMatchingBooking();
        $service = Mockery::mock(PaymentReconciliationService::class);
        $service->shouldReceive('reconcile')->once()->andThrow(new VnpayTransportException('provider unavailable'));
        $this->app->instance(PaymentReconciliationService::class, $service);
        $this->actingAs($manager)->post(route('admin.payments.query-provider', $pending))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(Payment::STATUS_PENDING, $pending->fresh()->status);
        $this->assertSame(1, ActivityLog::query()->where('action', 'payment.provider_query_requested')->count());
        $this->assertDatabaseMissing('activity_logs', ['action' => 'payment.provider_query_completed']);
    }

    public function test_rate_limit_is_scoped_by_actor_and_payment(): void
    {
        $payment = $this->paymentMatchingBooking();
        $other = $this->paymentMatchingBooking();
        $manager = $this->userWithRole('manager');
        $service = Mockery::mock(PaymentReconciliationService::class);
        $service->shouldReceive('reconcile')->times(8)->andReturn(Payment::STATUS_PENDING);
        $this->app->instance(PaymentReconciliationService::class, $service);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->actingAs($manager)->post(route('admin.payments.query-provider', $payment))->assertRedirect();
        }
        $this->actingAs($manager)->post(route('admin.payments.query-provider', $payment))->assertTooManyRequests();
        $this->actingAs($manager)->post(route('admin.payments.query-provider', $other))->assertRedirect();
        $this->actingAs($this->userWithRole('manager'))->post(route('admin.payments.query-provider', $payment))->assertRedirect();
    }

    public function test_canonical_review_reconciliation_queries_existing_zalopay_order_and_audits_once(): void
    {
        $payment = $this->paymentMatchingBooking([
            'status' => Payment::STATUS_REVIEW,
            'failure_reason' => 'manual_review',
            'failed_at' => now(),
        ]);
        Http::fake(['*' => Http::response(['return_code' => 3, 'return_message' => 'Pending'], 200)]);

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('admin.payments.reconcile', $payment))
            ->assertRedirect()->assertSessionHas('success');

        Http::assertSentCount(1);
        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertDatabaseCount('payment_review_events', 1);
        $this->assertSame(1, ActivityLog::query()->where('action', 'payment.reconciliation_completed')->count());
    }

    public function test_provider_query_audits_a_real_transition_into_review_once(): void
    {
        $payment = $this->paymentMatchingBooking();
        $service = Mockery::mock(PaymentReconciliationService::class);
        $service->shouldReceive('reconcile')->once()->andReturnUsing(function (Payment $selected): string {
            $selected->forceFill([
                'status' => Payment::STATUS_REVIEW,
                'failure_reason' => 'query_identity_mismatch',
                'failed_at' => now(),
            ])->save();

            return Payment::STATUS_REVIEW;
        });
        $this->app->instance(PaymentReconciliationService::class, $service);

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('admin.payments.query-provider', $payment))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertSame(1, ActivityLog::query()->where('action', 'payment.review_entered')->count());
        $this->assertSame(1, ActivityLog::query()->where('action', 'payment.provider_query_completed')->count());
    }

    public function test_vnpay_review_is_resolved_without_requiring_zalopay_configuration(): void
    {
        $this->configureVnpay();
        config(['payment.zalopay.app_id' => null]);
        $payment = $this->paymentMatchingBooking([
            'provider' => 'vnpay',
            'status' => Payment::STATUS_REVIEW,
            'app_id' => null,
            'app_trans_id' => null,
            'order_code' => 'VNP-REVIEW-'.bin2hex(random_bytes(6)),
            'provider_transaction_created_at' => now(),
        ]);
        $fields = [
            'vnp_ResponseId' => 'ADMINREVIEW01',
            'vnp_Command' => 'querydr',
            'vnp_ResponseCode' => '00',
            'vnp_Message' => 'Success',
            'vnp_TmnCode' => 'MOVIE123',
            'vnp_TxnRef' => $payment->order_code,
            'vnp_Amount' => (string) ($payment->amount * 100),
            'vnp_BankCode' => 'NCB',
            'vnp_PayDate' => now('Asia/Ho_Chi_Minh')->format('YmdHis'),
            'vnp_TransactionNo' => '987654321',
            'vnp_TransactionType' => '01',
            'vnp_TransactionStatus' => '00',
        ];
        $fields['vnp_SecureHash'] = hash_hmac(
            'sha512',
            app(VnpaySigner::class)->queryResponseCanonical($fields),
            (string) config('payment.vnpay.hash_secret'),
        );
        Http::fake(['*' => Http::response($fields, 200)]);

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('admin.payments.reconcile', $payment))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->verified_at);
        $this->assertSame('paid', $payment->booking->fresh()->payment_status);
        $this->assertDatabaseHas('payment_review_events', [
            'payment_id' => $payment->id,
            'resulting_status' => Payment::STATUS_SUCCESS,
            'provider_result_category' => 'authoritative_success',
        ]);
        $this->assertDatabaseHas('booking_ticket_deliveries', ['booking_id' => $payment->booking_id]);
        $this->assertSame(1, ActivityLog::query()->where('action', 'payment.review_resolved')->count());
    }

    public function test_vnpay_review_amount_mismatch_stays_unpaid_and_is_not_resolved(): void
    {
        $this->configureVnpay();
        $payment = $this->paymentMatchingBooking([
            'provider' => 'vnpay',
            'status' => Payment::STATUS_REVIEW,
            'app_id' => null,
            'app_trans_id' => null,
            'order_code' => 'VNP-REVIEW-'.bin2hex(random_bytes(6)),
            'provider_transaction_created_at' => now(),
        ]);
        $fields = [
            'vnp_ResponseId' => 'ADMINREVIEW02',
            'vnp_Command' => 'querydr',
            'vnp_ResponseCode' => '00',
            'vnp_Message' => 'Success',
            'vnp_TmnCode' => 'MOVIE123',
            'vnp_TxnRef' => $payment->order_code,
            'vnp_Amount' => (string) (($payment->amount + 1000) * 100),
            'vnp_BankCode' => 'NCB',
            'vnp_PayDate' => now('Asia/Ho_Chi_Minh')->format('YmdHis'),
            'vnp_TransactionNo' => '987654322',
            'vnp_TransactionType' => '01',
            'vnp_TransactionStatus' => '00',
        ];
        $fields['vnp_SecureHash'] = hash_hmac(
            'sha512',
            app(VnpaySigner::class)->queryResponseCanonical($fields),
            (string) config('payment.vnpay.hash_secret'),
        );
        Http::fake(['*' => Http::response($fields, 200)]);

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('admin.payments.reconcile', $payment))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->verified_at);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
        $this->assertDatabaseHas('payment_review_events', [
            'payment_id' => $payment->id,
            'provider_result_category' => 'validation_rejected',
        ]);
        $this->assertDatabaseMissing('booking_ticket_deliveries', ['booking_id' => $payment->booking_id]);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'payment.review_resolved']);
    }

    public function test_unsupported_provider_query_does_not_mutate_or_log_false_success(): void
    {
        $payment = $this->paymentMatchingBooking();
        $payment->forceFill(['provider' => 'unsupported-provider'])->save();

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('admin.payments.query-provider', $payment))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->verified_at);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_manual_payment_mutation_routes_do_not_exist(): void
    {
        foreach ([
            'admin.payments.mark-paid', 'admin.payments.confirm-success', 'admin.payments.force-success',
            'admin.payments.set-verified', 'admin.payments.update-amount', 'admin.payments.update-reference',
            'admin.payments.destroy', 'admin.payments.reset',
        ] as $routeName) {
            $this->assertFalse(app('router')->getRoutes()->hasNamedRoute($routeName));
        }
    }

    public function test_programming_errors_are_not_hidden_as_provider_failures(): void
    {
        $payment = $this->paymentMatchingBooking();
        $service = Mockery::mock(PaymentReconciliationService::class);
        $service->shouldReceive('reconcile')->once()->andThrow(new TypeError('confirmed programming defect'));
        $this->app->instance(PaymentReconciliationService::class, $service);
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->userWithRole('manager'))
                ->post(route('admin.payments.query-provider', $payment));
            $this->fail('Programming errors must escape the safe provider-error handler.');
        } catch (TypeError $exception) {
            $this->assertSame('confirmed programming defect', $exception->getMessage());
        }

        $this->assertDatabaseHas('activity_logs', ['action' => 'payment.provider_query_requested']);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'payment.provider_query_completed']);
    }

    public function test_payment_index_query_count_is_bounded(): void
    {
        for ($index = 0; $index < 18; $index++) {
            $this->successfulPayment('zalopay', 50_000);
        }
        $manager = $this->userWithRole('manager');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($manager)->get(route('admin.payments.index', ['per_page' => 15]));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk()->assertSee('page=2', false);
        $this->assertLessThanOrEqual(12, $queryCount, 'Danh sách payment có dấu hiệu N+1.');
    }

    public function test_detail_and_queue_queries_are_bounded_while_reconciliation_stays_out_of_navigation(): void
    {
        $payment = $this->paymentMatchingBooking([
            'status' => Payment::STATUS_UNRESOLVED,
            'failure_reason' => 'query_unresolved',
        ]);
        $manager = $this->userWithRole('manager');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $detail = $this->actingAs($manager)->get(route('admin.payments.show', $payment));
        $detailQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        $queue = $this->actingAs($manager)->get(route('admin.payment-reconciliation.index'));
        $queueQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $detail->assertOk();
        $queue->assertOk();
        // Multi-cinema adds exactly one bounded query per admin page: the branch selector
        // loads the accessible cinema list once and memoises it for the rest of the request.
        $this->assertLessThanOrEqual(15, $detailQueries, 'Chi tiết payment có dấu hiệu N+1.');
        $this->assertLessThanOrEqual(8, $queueQueries, 'Queue đối soát có dấu hiệu N+1.');
        $this->assertSame(1, substr_count($detail->getContent(), 'aria-current="page"'));
        $this->assertSame(0, substr_count($queue->getContent(), 'aria-current="page"'));
        $this->assertMatchesRegularExpression('/data-admin-nav-route="admin\.payments\.index"[^>]*aria-current="page"|aria-current="page"[^>]*data-admin-nav-route="admin\.payments\.index"/s', $detail->getContent());
        $this->assertStringNotContainsString('data-admin-nav-route="admin.payment-reconciliation.index"', $queue->getContent());
    }

    public function test_reconciliation_badge_is_capped_at_ninety_nine_plus(): void
    {
        $booking = $this->payableBooking();
        for ($index = 0; $index < 100; $index++) {
            $this->pendingPayment($booking, [
                'status' => Payment::STATUS_FAILED,
                'failure_reason' => 'query_failed',
            ]);
        }

        $this->assertSame('99+', app(PaymentReconciliationQuery::class)->badgeLabel());
    }

    private function configureVnpay(): void
    {
        config([
            'payment.vnpay.environment' => 'sandbox',
            'payment.vnpay.tmn_code' => 'MOVIE123',
            'payment.vnpay.hash_secret' => str_repeat('sandbox-secret-', 4),
            'payment.vnpay.payment_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'payment.vnpay.query_url' => 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction',
            'payment.vnpay.bank_code' => 'VNPAYQR',
            'payment.vnpay.locale' => 'vn',
            'payment.vnpay.order_type' => 'other',
            'payment.vnpay.payment_ttl_minutes' => 15,
            'payment.vnpay.http_timeout_seconds' => 10,
            'payment.vnpay.query_interval_seconds' => 60,
            'payment.vnpay.query_ip' => '127.0.0.1',
        ]);
    }

    private function paymentMatchingBooking(array $overrides = []): Payment
    {
        $booking = $this->payableBooking();

        return $this->pendingPayment($booking, [
            'amount' => (int) $booking->total_amount,
            ...$overrides,
        ]);
    }

    private function successfulPayment(string $provider, int $amount, array $overrides = []): Payment
    {
        $booking = $this->payableBooking([
            'total_amount' => $amount,
            'payment_status' => 'paid',
            'booking_status' => 'paid',
            'paid_at' => now(),
        ]);

        return $this->pendingPayment($booking, [
            'provider' => $provider,
            'amount' => $amount,
            'status' => Payment::STATUS_SUCCESS,
            'verified_at' => now(),
            'paid_at' => now(),
            ...$overrides,
        ]);
    }

    private function successfulCounterPayment(int $amount): Payment
    {
        $booking = $this->payableBooking(['total_amount' => $amount]);
        $settler = $this->userWithRole('staff');
        DB::table('bookings')->where('id', $booking->id)->update([
            'sales_channel' => 'counter',
            'created_by_staff_id' => $settler->id,
            'payment_status' => 'paid',
            'booking_status' => 'paid',
            'paid_at' => now(),
        ]);

        $payment = new Payment;
        $payment->forceFill([
            'booking_id' => $booking->id,
            'provider' => Payment::PROVIDER_COUNTER_CASH,
            'payment_method' => Payment::PROVIDER_COUNTER_CASH,
            'amount' => $amount,
            'currency' => 'VND',
            'status' => Payment::STATUS_SUCCESS,
            'settled_by_user_id' => $settler->id,
            'settled_at' => now(),
            'paid_at' => now(),
        ])->save();

        return $payment;
    }
}
