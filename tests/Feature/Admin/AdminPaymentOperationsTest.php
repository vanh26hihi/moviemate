<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\Permission;
use App\Services\Payments\PaymentReconciliationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Feature\Payments\PaymentTestCase;

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

    public function test_index_keeps_attempts_separate_filters_and_never_renders_secret_payloads(): void
    {
        $booking = $this->payableBooking();
        $failed = $this->pendingPayment($booking, [
            'status' => Payment::STATUS_FAILED,
            'amount' => (int) $booking->total_amount,
            'raw_request' => ['secret' => 'raw-request-must-stay-hidden'],
            'raw_response' => ['secret' => 'raw-response-must-stay-hidden'],
            'payment_url' => 'https://provider.example.test/signed-secret',
        ]);
        $review = $this->pendingPayment($booking, [
            'status' => Payment::STATUS_REVIEW,
            'amount' => (int) $booking->total_amount + 1000,
        ]);

        $response = $this->actingAs($this->userWithRole('manager'))->get(route('admin.payments.index', [
            'booking_code' => $booking->booking_code,
            'review' => 'yes',
            'amount_mismatch' => 'yes',
            'sort' => 'amount',
            'direction' => 'desc',
        ]));

        $response->assertOk()->assertSee('#'.$review->id)->assertDontSee('#'.$failed->id)
            ->assertDontSee('raw-request-must-stay-hidden')->assertDontSee('raw-response-must-stay-hidden')
            ->assertDontSee('signed-secret');

        $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.payments.index', ['sort' => 'drop table payments']))
            ->assertSessionHasErrors('sort');
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
            ->assertDontSee('provider.example.test/private')->assertDontSee(str_repeat('a', 64))
            ->assertDontSee(str_repeat('b', 64))->assertDontSee('Nhật ký thao tác')
            ->assertDontSee('Đánh dấu đã thanh toán')->assertDontSee('Sửa số tiền')
            ->assertDontSee('Xóa giao dịch');
        $this->actingAs($this->userWithRole('manager'))->get(route('admin.payments.index'))
            ->assertOk()->assertSee('Có thẩm quyền');
    }

    public function test_queue_has_deterministic_priority_and_excludes_fresh_matching_pending_attempt(): void
    {
        $fresh = $this->paymentMatchingBooking();
        $mismatch = $this->paymentMatchingBooking([
            'status' => Payment::STATUS_REVIEW,
            'amount' => (int) $fresh->booking->total_amount + 1,
        ]);
        $unresolved = $this->paymentMatchingBooking(['status' => Payment::STATUS_UNRESOLVED]);

        $response = $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.payment-reconciliation.index'));

        $response->assertOk()->assertSee('#'.$mismatch->id)->assertSee('Khẩn cấp')
            ->assertSee('Số tiền giao dịch không khớp tổng đơn')
            ->assertSee('#'.$unresolved->id)->assertSee('Kết quả provider chưa xác định')
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
        $service->shouldReceive('reconcile')->once()->andThrow(new \RuntimeException('provider unavailable'));
        $this->app->instance(PaymentReconciliationService::class, $service);
        $this->actingAs($manager)->post(route('admin.payments.query-provider', $pending))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(Payment::STATUS_PENDING, $pending->fresh()->status);
        $this->assertDatabaseCount('activity_logs', 0);
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

    public function test_vnpay_review_is_preserved_when_no_safe_review_adapter_exists(): void
    {
        $payment = $this->paymentMatchingBooking([
            'provider' => 'vnpay',
            'status' => Payment::STATUS_REVIEW,
            'app_id' => null,
            'app_trans_id' => null,
            'order_code' => 'VNP-REVIEW-'.bin2hex(random_bytes(6)),
        ]);

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('admin.payments.reconcile', $payment))
            ->assertRedirect()->assertSessionHas('warning');

        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertDatabaseCount('payment_review_events', 0);
        $this->assertDatabaseCount('activity_logs', 0);
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

    public function test_payment_index_query_count_is_bounded(): void
    {
        for ($index = 0; $index < 18; $index++) {
            $this->paymentMatchingBooking(['status' => Payment::STATUS_FAILED]);
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

    private function paymentMatchingBooking(array $overrides = []): Payment
    {
        $booking = $this->payableBooking();

        return $this->pendingPayment($booking, [
            'amount' => (int) $booking->total_amount,
            ...$overrides,
        ]);
    }
}
