<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\BookingTicketDelivery;
use App\Models\FoodItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Permission;
use App\Services\Payments\PaymentReconciliationService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Feature\Payments\PaymentTestCase;

class AdminBookingOperationsTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_canonical_pages_are_permission_protected(): void
    {
        $booking = $this->payableBooking();

        $this->get(route('admin.bookings.index'))->assertRedirect(route('login'));
        $this->get(route('admin.bookings.show', $booking))->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole('user'))->get(route('admin.bookings.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('staff'))->get(route('admin.bookings.index'))->assertForbidden();

        $inactive = $this->userWithRole('manager', ['status' => 'inactive']);
        $this->actingAs($inactive)->get(route('admin.bookings.index'))->assertRedirect(route('login'));

        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->get(route('admin.bookings.index'))->assertOk();
        $this->actingAs($manager)->get(route('admin.bookings.show', $booking))->assertOk();
    }

    public function test_success_index_filters_masks_guests_and_merges_couple_seats_without_payment_inflation(): void
    {
        $scenario = $this->bookingScenario();
        $couple = $scenario['seats']->where('type', 'couple')->values();
        $booking = $this->reserve($scenario, $couple->pluck('id')->all())->booking;
        $booking->forceFill([
            'customer_email' => 'guest.operations@example.test',
            'guest_access_token_hash' => hash('sha256', 'never-show-this-capability'),
        ])->save();
        $payment = $this->pendingPayment($booking, ['amount' => (int) $booking->total_amount]);
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))->assertJsonPath('return_code', 1);
        $other = $this->payableBooking(['booking_status' => 'expired']);

        $response = $this->actingAs($this->userWithRole('manager'))->get(route('admin.bookings.index', [
            'search' => $booking->booking_code,
            'sales_channel' => 'online',
            'date_from' => today()->toDateString(),
            'date_to' => today()->toDateString(),
            'sort' => 'total_amount',
            'direction' => 'asc',
        ]));

        $response->assertOk()
            ->assertSee($booking->booking_code)
            ->assertDontSee($other->booking_code)
            ->assertSee('Khách đặt vé')
            ->assertSee('g***@e***.test')
            ->assertDontSee('guest.operations@example.test')
            ->assertDontSee('never-show-this-capability')
            ->assertSee('Ghế đôi B1–B2')
            ->assertDontSee('Ghế B1, Ghế B2')
            ->assertSee(number_format((int) $booking->total_amount, 0, ',', '.').' VNĐ');

        $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.bookings.index', ['sort' => 'verified_at']))
            ->assertSessionHasErrors('sort');
    }

    public function test_index_paginates_with_a_bounded_query_count(): void
    {
        $scenario = $this->bookingScenario(false);
        for ($index = 0; $index < 27; $index++) {
            $booking = $this->bookingForScenario($scenario, [
                'booking_code' => 'PAGE-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            ]);
            $this->recordSuccessfulPayment($booking);
        }
        $manager = $this->userWithRole('manager');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($manager)->get(route('admin.bookings.index', [
            'per_page' => 15,
        ]));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk()->assertSee('PAGE-026')->assertSee('page=2', false);
        $this->assertLessThanOrEqual(20, $queryCount, 'Danh sách booking có dấu hiệu N+1.');
    }

    public function test_index_only_shows_authoritatively_paid_bookings_and_summary_is_exact(): void
    {
        $scenario = $this->bookingScenario(false);
        $draft = $this->bookingForScenario($scenario, ['booking_code' => 'VIS-DRAFT']);
        $expired = $this->bookingForScenario($scenario, [
            'booking_code' => 'VIS-EXPIRED',
            'booking_status' => 'expired',
            'expires_at' => now()->subMinute(),
        ]);
        $paidWithoutEvidence = $this->bookingForScenario($scenario, [
            'booking_code' => 'VIS-PAID-NO-EVIDENCE',
            'booking_status' => 'paid',
            'payment_status' => 'paid',
        ]);
        $paid = $this->bookingForScenario($scenario, [
            'booking_code' => 'VIS-PAID',
            'booking_status' => 'paid',
            'payment_status' => 'paid',
        ]);
        $secondPaid = $this->bookingForScenario($scenario, [
            'booking_code' => 'VIS-PAID-SECOND',
            'booking_status' => 'paid',
            'payment_status' => 'paid',
        ]);
        $refunded = $this->bookingForScenario($scenario, [
            'booking_code' => 'VIS-REFUNDED',
            'booking_status' => 'cancelled',
            'payment_status' => 'refunded',
        ]);
        $unverifiedSuccess = $this->bookingForScenario($scenario, [
            'booking_code' => 'VIS-UNVERIFIED-SUCCESS',
            'booking_status' => 'paid',
            'payment_status' => 'paid',
        ]);
        $this->recordSuccessfulPayment($paid, 50_000);
        $this->recordSuccessfulPayment($secondPaid, 50_000);
        $this->pendingPayment($unverifiedSuccess, ['status' => Payment::STATUS_SUCCESS, 'verified_at' => null]);
        $refundedPayment = $this->recordSuccessfulPayment($refunded, 50_000);
        $refundedPayment->booking->forceFill(['booking_status' => 'cancelled', 'payment_status' => 'refunded'])->save();

        $response = $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertSee('Đơn thành công')->assertSee('Doanh thu')->assertSee('Chỗ đã bán')->assertDontSee('Đã soát vé')
            ->assertSee($paid->booking_code)->assertSee($secondPaid->booking_code)
            ->assertSee('100.000 VNĐ');

        foreach ([$draft, $expired, $paidWithoutEvidence, $refunded, $unverifiedSuccess] as $hidden) {
            $response->assertDontSee($hidden->booking_code);
        }
        $response->assertDontSee('include_drafts', false)->assertDontSee('Hiển thị đơn tạm');
    }

    public function test_detail_renders_authoritative_sections_and_hides_secrets_and_activity_by_permission(): void
    {
        [$booking, $payment] = $this->verifiedBooking();
        $failedPayment = $this->pendingPayment($booking, [
            'status' => Payment::STATUS_FAILED,
            'amount' => 85000,
        ]);
        $booking->forceFill([
            'booking_status' => 'paid',
            'food_subtotal' => 35000,
            'total_amount' => 85000,
            'guest_access_token_hash' => hash('sha256', 'guest-secret-value'),
            'ticket_email_token_hash' => hash('sha256', 'ticket-secret-value'),
        ])->save();
        $payment->forceFill(['amount' => 85000, 'payment_url' => 'https://provider.test/secret-signed-url'])->save();
        BookingTicketDelivery::query()->where('booking_id', $booking->id)->update([
            'status' => BookingTicketDelivery::STATUS_FAILED,
            'attempts' => 2,
            'last_error_code' => 'smtp_connection_failed',
            'available_at' => now()->addHour(),
        ]);
        $order = Order::query()->create([
            'booking_id' => $booking->id,
            'customer_name' => 'Khách thử nghiệm',
            'customer_email' => $booking->recipient_email,
            'subtotal' => 35000,
            'total_amount' => 35000,
            'status' => 'paid',
        ]);
        $food = FoodItem::query()->create(['name' => 'Bắp rang', 'price' => 35000, 'active' => true]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'food_item_id' => $food->id,
            'quantity' => 1,
            'snapshot_name' => 'Bắp rang snapshot',
            'unit_price' => 35000,
            'line_total' => 35000,
            'price' => 35000,
            'total' => 35000,
        ]);
        ActivityLog::query()->create([
            'actor_user_id' => null,
            'action' => 'booking.payment_query_requested',
            'subject_type' => $booking->getMorphClass(),
            'subject_id' => (string) $booking->id,
            'subject_label' => 'Đơn đặt vé '.$booking->booking_code,
            'request_id' => 'test-request-1234',
            'method' => 'POST',
        ]);

        $managerResponse = $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.bookings.show', $booking));
        $managerResponse->assertOk()
            ->assertSee($booking->booking_code)
            ->assertSee('Giao dịch xác thực')
            ->assertSee('Bắp rang snapshot')
            ->assertSee($failedPayment->status_label)
            ->assertDontSee('Soát vé')
            ->assertSee('Gửi lỗi')->assertSee('Không thể kết nối máy chủ email')->assertSee('Gửi lại tài liệu nhận vé')
            ->assertSee(number_format((int) $payment->amount, 0, ',', '.').' VNĐ')
            ->assertDontSee('Lịch sử hoạt động')
            ->assertDontSee('guest-secret-value')
            ->assertDontSee('ticket-secret-value')
            ->assertDontSee('secret-signed-url')
            ->assertDontSee('pending_payment')
            ->assertDontSee($booking->recipient_email);

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()->assertSee('Lịch sử hoạt động')->assertSee('Truy vấn nhà cung cấp');
        $this->actingAs($this->userWithRole('manager'))
            ->get(route('staff.tickets.operations', $booking))
            ->assertOk()->assertSee($booking->booking_code)
            ->assertSee('Vé xem phim theo ghế')->assertSee('Số bản đã in')->assertDontSee('data-qr-value', false)
            ->assertDontSee($booking->recipient_email);
        $this->actingAs($this->userWithRole('manager'))
            ->post(route('admin.bookings.ticket-email.resend', $booking))
            ->assertRedirect()->assertSessionHas('success');
    }

    public function test_resend_uses_stored_recipient_one_outbox_row_one_notification_and_one_safe_audit(): void
    {
        [$booking] = $this->verifiedBooking();
        BookingTicketDelivery::query()->where('booking_id', $booking->id)->update([
            'status' => BookingTicketDelivery::STATUS_FAILED,
            'attempts' => 2,
            'last_error_code' => 'smtp_connection_failed',
            'available_at' => now()->addHour(),
        ]);
        $manager = $this->userWithRole('manager');
        $response = $this->actingAs($manager)->post(route('admin.bookings.ticket-email.resend', $booking), [
            'email' => 'attacker@example.test',
        ]);

        $response->assertRedirect()->assertSessionHas('success', 'Yêu cầu gửi lại tài liệu nhận vé đã được ghi nhận.');
        $this->assertDatabaseCount('booking_ticket_deliveries', 1);
        $this->assertSame(1, ActivityLog::query()->where('action', 'booking.ticket_resend_requested')->count());
        $log = ActivityLog::query()->where('action', 'booking.ticket_resend_requested')->sole();
        $this->assertStringNotContainsString('attacker@example.test', json_encode($log->toArray()));

        $html = $this->withSession(['success' => 'Yêu cầu gửi lại tài liệu nhận vé đã được ghi nhận.'])
            ->actingAs($manager)->get(route('admin.bookings.show', $booking))->getContent();
        $this->assertSame(1, substr_count(strip_tags($html), 'Yêu cầu gửi lại tài liệu nhận vé đã được ghi nhận.'));
    }

    public function test_provider_query_uses_server_selected_payment_and_ignores_browser_result(): void
    {
        $booking = $this->payableBooking();
        $payment = $this->pendingPayment($booking);
        $manager = $this->userWithRole('manager');
        $service = Mockery::mock(PaymentReconciliationService::class);
        $service->shouldReceive('reconcile')->once()
            ->with(Mockery::on(fn (Payment $selected): bool => $selected->is($payment)))
            ->andReturn(Payment::STATUS_PENDING);
        $this->app->instance(PaymentReconciliationService::class, $service);

        $this->actingAs($manager)->post(route('admin.bookings.payment-query', $booking), [
            'status' => Payment::STATUS_SUCCESS,
            'verified_at' => now()->toIso8601String(),
            'amount' => 1,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->verified_at);
        $this->assertSame(1, ActivityLog::query()->where('action', 'booking.payment_query_requested')->count());
    }

    public function test_safe_cancellation_releases_locks_but_paid_booking_cannot_be_cancelled(): void
    {
        $pending = $this->payableBooking();
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->post(route('admin.bookings.cancel', $pending))
            ->assertRedirect()->assertSessionHas('success', 'Đơn đặt vé đã được hủy an toàn.');
        $this->assertSame('cancelled', $pending->fresh()->booking_status);
        $this->assertSame(0, BookingSeat::query()->where('booking_id', $pending->id)->whereNotNull('active_lock_key')->count());
        $this->assertSame(1, ActivityLog::query()->where('action', 'booking.cancelled')->count());

        [$paid] = $this->verifiedBooking();
        $before = $paid->getRawOriginal();
        $this->actingAs($manager)->post(route('admin.bookings.cancel', $paid))
            ->assertRedirect()->assertSessionHas('warning');
        $this->assertSame($before['booking_status'], $paid->fresh()->booking_status);
        $this->assertSame(1, ActivityLog::query()->where('action', 'booking.cancelled')->count());
    }

    public function test_direct_actions_require_specific_permissions_and_forbidden_mutation_routes_do_not_exist(): void
    {
        [$booking] = $this->verifiedBooking();
        $manager = $this->userWithRole('manager');
        $manager->role->permissions()->detach(Permission::query()->where('slug', 'ticket_deliveries.retry')->value('id'));

        $this->actingAs($manager)->post(route('admin.bookings.ticket-email.resend', $booking))->assertForbidden();
        $this->actingAs($this->userWithRole('user'))->post(route('admin.bookings.cancel', $booking))->assertForbidden();

        foreach (['admin.bookings.update', 'admin.bookings.destroy', 'admin.bookings.mark-paid', 'admin.bookings.payment-success', 'admin.bookings.seats.update'] as $routeName) {
            $this->assertFalse(app('router')->getRoutes()->hasNamedRoute($routeName));
        }
    }

    public function test_ineligible_or_failed_actions_create_no_false_success_audit(): void
    {
        $unpaid = $this->payableBooking();
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->post(route('admin.bookings.ticket-email.resend', $unpaid))->assertNotFound();
        $this->actingAs($manager)->post(route('staff.tickets.print.start', $unpaid))->assertStatus(409);
        $this->actingAs($manager)->post(route('admin.bookings.payment-query', $unpaid))
            ->assertRedirect()->assertSessionHas('warning');
        $this->assertDatabaseCount('activity_logs', 0);

        $payment = $this->pendingPayment($unpaid);
        $service = Mockery::mock(PaymentReconciliationService::class);
        $service->shouldReceive('reconcile')->once()->andThrow(new \RuntimeException('provider unavailable'));
        $this->app->instance(PaymentReconciliationService::class, $service);

        $this->actingAs($manager)->post(route('admin.bookings.payment-query', $unpaid))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    private function verifiedBooking(): array
    {
        $scenario = $this->bookingScenario(false);
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id])->booking;
        $payment = $this->pendingPayment($booking, ['amount' => (int) $booking->total_amount]);
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertJsonPath('return_code', 1);

        return [$booking->fresh(), $payment->fresh()];
    }

    private function recordSuccessfulPayment(Booking $booking, ?int $amount = null): Payment
    {
        $booking->forceFill(['booking_status' => 'paid', 'payment_status' => 'paid', 'paid_at' => now()])->save();

        return $this->pendingPayment($booking, [
            'status' => Payment::STATUS_SUCCESS,
            'verified_at' => now(),
            'paid_at' => now(),
            'amount' => $amount ?? (int) $booking->total_amount,
        ]);
    }
}
