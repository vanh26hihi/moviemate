<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\BookingTicketDelivery;
use App\Models\TicketCheckinEvent;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Payments\PaymentTestCase;

final class AdminTicketOperationsTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_standalone_delivery_routes_are_removed_and_checkin_routes_stay_protected(): void
    {
        foreach (['admin.ticket-deliveries.index', 'admin.ticket-deliveries.show', 'admin.ticket-deliveries.retry'] as $route) {
            $this->assertFalse(app('router')->getRoutes()->hasNamedRoute($route));
        }

        $delivery = $this->verifiedDelivery();
        $event = TicketCheckinEvent::query()->create([
            'booking_id' => $delivery->booking_id,
            'showtime_id' => $delivery->booking->showtime_id,
            'result' => TicketCheckinEvent::RESULT_ACCEPTED,
            'reason_code' => 'verified_paid_ticket',
            'scanned_at' => now(),
        ]);
        $this->get(route('admin.ticket-checkins.index'))->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole('staff'))->get(route('admin.ticket-checkins.show', $event))->assertForbidden();
        $this->actingAs($this->userWithRole('manager'))->get(route('admin.ticket-checkins.index'))->assertOk();
    }

    public function test_verified_payment_still_enqueues_exactly_one_automatic_delivery(): void
    {
        $payment = $this->pendingPayment();
        $body = $this->callbackBody($payment);

        $this->postJson(route('payments.zalopay.callback'), $body)->assertJsonPath('return_code', 1);
        $this->postJson(route('payments.zalopay.callback'), $body)->assertJsonPath('return_code', 1);

        $this->assertDatabaseCount('booking_ticket_deliveries', 1);
        $this->assertDatabaseHas('booking_ticket_deliveries', [
            'booking_id' => $payment->booking_id,
            'status' => BookingTicketDelivery::STATUS_PENDING,
        ]);
    }

    public function test_booking_detail_is_the_delivery_incident_context_and_masks_sensitive_data(): void
    {
        $delivery = $this->failedDelivery();
        $delivery->booking->forceFill([
            'customer_email' => 'delivery.operations@example.test',
            'guest_access_token_hash' => hash('sha256', 'guest-capability-secret'),
            'ticket_email_token_hash' => hash('sha256', 'email-capability-secret'),
        ])->save();

        $response = $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.bookings.show', $delivery->booking));

        $response->assertOk()->assertSee('Vé điện tử')->assertSee('Gửi lỗi')
            ->assertSee('Không thể kết nối máy chủ email')->assertSee('Gửi lại vé')
            ->assertSee('d***@e***.test')->assertDontSee('delivery.operations@example.test')
            ->assertDontSee('guest-capability-secret')->assertDontSee('email-capability-secret');
    }

    public function test_failed_delivery_retry_uses_stored_recipient_and_preserves_attempt_history(): void
    {
        $delivery = $this->failedDelivery();
        $before = $delivery->booking->only(['booking_status', 'payment_status', 'customer_email', 'used_at']);

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('admin.bookings.ticket-email.resend', $delivery->booking), [
                'recipient' => 'attacker@example.test', 'attempts' => 0, 'status' => 'sent',
            ])->assertRedirect()->assertSessionHas('success');

        $delivery->refresh();
        $this->assertSame(BookingTicketDelivery::STATUS_PENDING, $delivery->status);
        $this->assertSame(4, $delivery->attempts);
        $this->assertSame('smtp_connection_failed', $delivery->last_error_code);
        $this->assertSame($before, $delivery->booking->fresh()->only(array_keys($before)));
        $this->assertSame(1, ActivityLog::query()->where('action', 'booking.ticket_resend_requested')->count());
        $this->assertStringNotContainsString('attacker@example.test', ActivityLog::query()
            ->where('action', 'booking.ticket_resend_requested')->sole()->toJson());
    }

    public function test_sent_pending_and_unpaid_deliveries_cannot_be_spammed(): void
    {
        $manager = $this->userWithRole('manager');
        $sent = $this->verifiedDelivery();
        $sent->forceFill(['status' => BookingTicketDelivery::STATUS_SENT, 'sent_at' => now()])->save();
        $this->actingAs($manager)->post(route('admin.bookings.ticket-email.resend', $sent->booking))
            ->assertSessionHas('warning');
        $this->assertSame(BookingTicketDelivery::STATUS_SENT, $sent->fresh()->status);

        $pending = $this->verifiedDelivery();
        $pending->forceFill(['available_at' => now()->addHour()])->save();
        $this->actingAs($manager)->post(route('admin.bookings.ticket-email.resend', $pending->booking))
            ->assertSessionHas('warning');
        $this->assertSame(BookingTicketDelivery::STATUS_PENDING, $pending->fresh()->status);
        $this->assertTrue($pending->fresh()->available_at->isFuture());

        $booking = $this->payableBooking();
        BookingTicketDelivery::query()->create([
            'booking_id' => $booking->id, 'status' => BookingTicketDelivery::STATUS_FAILED, 'attempts' => 2,
        ]);
        $this->actingAs($manager)->post(route('admin.bookings.ticket-email.resend', $booking))->assertNotFound();
        $this->assertSame(0, ActivityLog::query()->where('action', 'booking.ticket_resend_requested')->count());
    }

    public function test_retry_action_is_only_rendered_for_valid_failed_delivery(): void
    {
        $manager = $this->userWithRole('manager');
        $failed = $this->failedDelivery();
        $this->actingAs($manager)->get(route('admin.bookings.show', $failed->booking))
            ->assertOk()->assertSee('Gửi lại vé');

        $sent = $this->verifiedDelivery();
        $sent->forceFill(['status' => BookingTicketDelivery::STATUS_SENT, 'sent_at' => now()])->save();
        $this->actingAs($manager)->get(route('admin.bookings.show', $sent->booking))
            ->assertOk()->assertDontSee('Gửi lại vé');
    }

    public function test_booking_retry_endpoint_is_rate_limited_per_actor_and_booking(): void
    {
        $delivery = $this->failedDelivery();
        $manager = $this->userWithRole('manager');
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $delivery->refresh()->forceFill(['status' => BookingTicketDelivery::STATUS_FAILED, 'available_at' => now()->addHour()])->save();
            $this->actingAs($manager)->post(route('admin.bookings.ticket-email.resend', $delivery->booking))->assertRedirect();
        }
        $delivery->refresh()->forceFill(['status' => BookingTicketDelivery::STATUS_FAILED, 'available_at' => now()->addHour()])->save();
        $this->actingAs($manager)->post(route('admin.bookings.ticket-email.resend', $delivery->booking))->assertTooManyRequests();
    }

    public function test_checkin_history_remains_read_only_filtered_and_contains_no_capability(): void
    {
        $delivery = $this->verifiedDelivery();
        $actor = $this->userWithRole('staff');
        $event = TicketCheckinEvent::query()->create([
            'booking_id' => $delivery->booking_id,
            'showtime_id' => $delivery->booking->showtime_id,
            'actor_user_id' => $actor->id,
            'actor_role_snapshot' => 'staff',
            'result' => TicketCheckinEvent::RESULT_ALREADY_USED,
            'reason_code' => 'booking_already_used',
            'scanned_at' => now(),
            'context' => ['source' => 'test'],
        ]);
        $secret = 'v1.'.$delivery->booking_id.'.'.str_repeat('A', 43);

        $response = $this->actingAs($this->userWithRole('manager'))->get(route('admin.ticket-checkins.index', [
            'booking_code' => $delivery->booking->booking_code, 'duplicates_only' => 'yes',
        ]));
        $response->assertOk()->assertSee($delivery->booking->booking_code)->assertSee('Quét trùng')->assertDontSee($secret);
        $this->actingAs($this->userWithRole('manager'))->get(route('admin.ticket-checkins.show', $event))
            ->assertOk()->assertSee('Bản ghi chỉ đọc')->assertDontSee($secret)->assertDontSee('safe_ip_hash');
    }

    public function test_booking_detail_and_checkin_pages_keep_bounded_query_counts(): void
    {
        $delivery = $this->verifiedDelivery();
        $manager = $this->userWithRole('manager');

        foreach ([
            [route('admin.bookings.show', $delivery->booking), 30, 'booking detail'],
            [route('admin.ticket-checkins.index'), 16, 'check-in index'],
        ] as [$url, $limit, $label]) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $response = $this->actingAs($manager)->get($url);
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();
            $response->assertOk();
            $this->assertLessThanOrEqual($limit, $count, $label.' có dấu hiệu N+1.');
        }
    }

    private function verifiedDelivery(): BookingTicketDelivery
    {
        $payment = $this->pendingPayment();
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))->assertJsonPath('return_code', 1);

        return BookingTicketDelivery::query()->with('booking')->where('booking_id', $payment->booking_id)->sole();
    }

    private function failedDelivery(): BookingTicketDelivery
    {
        $delivery = $this->verifiedDelivery();
        $delivery->forceFill([
            'status' => BookingTicketDelivery::STATUS_FAILED,
            'attempts' => 4,
            'last_error_code' => 'smtp_connection_failed',
            'available_at' => now()->addHour(),
        ])->save();

        return $delivery;
    }
}
