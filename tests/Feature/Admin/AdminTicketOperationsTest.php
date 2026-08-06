<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\BookingTicketDelivery;
use App\Models\Permission;
use App\Models\TicketCheckinEvent;
use App\Services\Admin\AdminTicketDeliveryQuery;
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

    public function test_delivery_and_checkin_routes_follow_permissions(): void
    {
        $delivery = $this->verifiedDelivery();
        $event = TicketCheckinEvent::query()->create([
            'booking_id' => $delivery->booking_id,
            'showtime_id' => $delivery->booking->showtime_id,
            'result' => TicketCheckinEvent::RESULT_ACCEPTED,
            'reason_code' => 'verified_paid_ticket',
            'scanned_at' => now(),
        ]);

        $this->get(route('admin.ticket-deliveries.index'))->assertRedirect(route('login'));
        $this->get(route('admin.ticket-checkins.index'))->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole('user'))->get(route('admin.ticket-deliveries.show', $delivery))->assertForbidden();
        $this->actingAs($staff = $this->userWithRole('staff'))->get(route('admin.ticket-deliveries.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.ticket-checkins.show', $event))->assertForbidden();

        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->get(route('admin.ticket-deliveries.index'))->assertOk();
        $this->actingAs($manager)->get(route('admin.ticket-deliveries.show', $delivery))->assertOk();
        $this->actingAs($manager)->get(route('admin.ticket-checkins.index'))->assertOk();

        $manager->role->permissions()->detach(Permission::query()->where('slug', 'ticket_deliveries.retry')->value('id'));
        $manager->unsetRelation('role');
        $this->actingAs($manager)->post(route('admin.ticket-deliveries.retry', $delivery))->assertForbidden();
    }

    public function test_delivery_index_and_detail_mask_sensitive_data_and_filter_safely(): void
    {
        $delivery = $this->verifiedDelivery();
        $delivery->booking->forceFill([
            'customer_email' => 'delivery.operations@example.test',
            'guest_access_token_hash' => hash('sha256', 'guest-capability-secret'),
            'ticket_email_token_hash' => hash('sha256', 'email-capability-secret'),
        ])->save();
        $delivery->forceFill([
            'status' => BookingTicketDelivery::STATUS_FAILED,
            'attempts' => 3,
            'last_error_code' => 'smtp_authentication_failed',
            'available_at' => now()->subMinute(),
        ])->save();

        $response = $this->actingAs($this->userWithRole('manager'))->get(route('admin.ticket-deliveries.index', [
            'booking_code' => $delivery->booking->booking_code,
            'recipient' => 'operations',
            'status' => 'failed',
            'has_error' => 'yes',
            'retry_due' => 'yes',
        ]));
        $response->assertOk()->assertSee($delivery->booking->booking_code)
            ->assertSee('d***@e***.test')->assertSee('Xác thực SMTP thất bại')
            ->assertDontSee('delivery.operations@example.test')
            ->assertDontSee('guest-capability-secret')->assertDontSee('email-capability-secret');

        $this->actingAs($this->userWithRole('manager'))->get(route('admin.ticket-deliveries.show', $delivery))
            ->assertOk()->assertSee('Xác thực SMTP thất bại')->assertSee($delivery->booking->seat_codes)
            ->assertDontSee($delivery->booking->getRawOriginal('guest_access_token_hash'))
            ->assertDontSee($delivery->booking->getRawOriginal('ticket_email_token_hash'));

        $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.ticket-deliveries.index', ['sort' => 'last_error_code']))
            ->assertSessionHasErrors('sort');
    }

    public function test_retry_preserves_authoritative_recipient_attempts_and_booking_state(): void
    {
        $delivery = $this->verifiedDelivery();
        $delivery->forceFill([
            'status' => BookingTicketDelivery::STATUS_FAILED,
            'attempts' => 4,
            'last_error_code' => 'smtp_connection_failed',
            'available_at' => now()->addHour(),
        ])->save();
        $beforeBooking = $delivery->booking->fresh()->only(['booking_status', 'payment_status', 'customer_email', 'used_at']);

        $this->actingAs($this->userWithRole('manager'))->post(route('admin.ticket-deliveries.retry', $delivery), [
            'recipient' => 'attacker@example.test',
            'attempts' => 0,
            'status' => 'sent',
        ])->assertRedirect()->assertSessionHas('success');

        $delivery->refresh();
        $this->assertSame(BookingTicketDelivery::STATUS_PENDING, $delivery->status);
        $this->assertSame(4, $delivery->attempts);
        $this->assertSame('smtp_connection_failed', $delivery->last_error_code);
        $this->assertSame($beforeBooking, $delivery->booking->fresh()->only(array_keys($beforeBooking)));
        $this->assertDatabaseCount('booking_ticket_deliveries', 1);
        $this->assertSame(1, ActivityLog::query()->where('action', 'ticket_delivery.retry_requested')->count());

        $this->actingAs($this->userWithRole('manager'))->post(route('admin.ticket-deliveries.retry', $delivery))
            ->assertRedirect()->assertSessionHas('warning');
        $this->assertSame(1, ActivityLog::query()->where('action', 'ticket_delivery.retry_requested')->count());
    }

    public function test_sent_active_claim_and_unpaid_delivery_are_not_requeued(): void
    {
        $manager = $this->userWithRole('manager');
        $sent = $this->verifiedDelivery();
        $sent->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
        $this->actingAs($manager)->post(route('admin.ticket-deliveries.retry', $sent))->assertSessionHas('warning');
        $this->assertSame('sent', $sent->fresh()->status);

        $active = $this->verifiedDelivery();
        $active->forceFill(['status' => 'processing', 'processing_started_at' => now(), 'lease_expires_at' => now()->addMinutes(5)])->save();
        $this->actingAs($manager)->post(route('admin.ticket-deliveries.retry', $active))->assertSessionHas('warning');
        $this->assertSame('processing', $active->fresh()->status);

        $booking = $this->payableBooking();
        $unpaid = BookingTicketDelivery::query()->create(['booking_id' => $booking->id, 'status' => 'failed', 'attempts' => 2]);
        $this->actingAs($manager)->post(route('admin.ticket-deliveries.retry', $unpaid))->assertSessionHas('warning');
        $this->assertSame('failed', $unpaid->fresh()->status);
        $this->assertSame(0, ActivityLog::query()->where('action', 'ticket_delivery.retry_requested')->count());
    }

    public function test_expired_claim_is_released_without_decrementing_attempts(): void
    {
        $delivery = $this->verifiedDelivery();
        $delivery->forceFill([
            'status' => 'processing', 'attempts' => 2,
            'processing_started_at' => now()->subMinutes(10), 'lease_expires_at' => now()->subMinute(),
        ])->save();

        $this->actingAs($this->userWithRole('manager'))->post(route('admin.ticket-deliveries.retry', $delivery))
            ->assertSessionHas('success');

        $this->assertSame('pending', $delivery->fresh()->status);
        $this->assertSame(2, $delivery->fresh()->attempts);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ticket_delivery.expired_claim_released']);
    }

    public function test_processing_claim_without_explicit_lease_uses_the_worker_lease_window(): void
    {
        config(['payment.ticket_delivery.lease_seconds' => 300]);
        $delivery = $this->verifiedDelivery();
        $delivery->forceFill([
            'status' => BookingTicketDelivery::STATUS_PROCESSING,
            'attempts' => 2,
            'processing_started_at' => now(),
            'lease_expires_at' => null,
        ])->save();
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->get(route('admin.ticket-deliveries.show', $delivery))
            ->assertOk()->assertDontSee('>Thử gửi lại</button>', false);
        $this->actingAs($manager)->post(route('admin.ticket-deliveries.retry', $delivery))
            ->assertSessionHas('warning');
        $this->assertSame(BookingTicketDelivery::STATUS_PROCESSING, $delivery->fresh()->status);

        $this->travel(301)->seconds();
        $this->actingAs($manager)->post(route('admin.ticket-deliveries.retry', $delivery))
            ->assertSessionHas('success');
        $this->assertSame(BookingTicketDelivery::STATUS_PENDING, $delivery->fresh()->status);
        $this->assertSame(2, $delivery->fresh()->attempts);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ticket_delivery.expired_claim_released']);
    }

    public function test_delivery_retry_is_rate_limited_by_actor_and_delivery(): void
    {
        $delivery = $this->verifiedDelivery();
        $delivery->forceFill(['status' => 'failed', 'available_at' => now()->addHour()])->save();
        $other = $this->verifiedDelivery();
        $other->forceFill(['status' => 'failed', 'available_at' => now()->addHour()])->save();
        $manager = $this->userWithRole('manager');

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->actingAs($manager)->post(route('admin.ticket-deliveries.retry', $delivery))->assertRedirect();
        }
        $delivery->forceFill(['status' => 'failed', 'available_at' => now()->addHour()])->save();
        $this->actingAs($manager)->get(route('admin.ticket-deliveries.show', $delivery))
            ->assertOk()->assertDontSee('>Thử gửi lại</button>', false);
        $this->actingAs($manager)->post(route('admin.ticket-deliveries.retry', $delivery))->assertTooManyRequests();
        $this->actingAs($manager)->post(route('admin.ticket-deliveries.retry', $other))->assertRedirect();
    }

    public function test_delivery_badge_is_bounded_and_sidebar_has_one_active_item(): void
    {
        $booking = $this->payableBooking();
        for ($index = 0; $index < 100; $index++) {
            $copy = $booking->replicate();
            $copy->forceFill([
                'booking_code' => 'BADGE-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'guest_access_token_hash' => null,
                'checkout_idempotency_key_hash' => hash('sha256', 'badge-idempotency-'.$index),
                'checkout_request_fingerprint_hash' => hash('sha256', 'badge-fingerprint-'.$index),
            ]);
            $copy->save();
            BookingTicketDelivery::query()->create([
                'booking_id' => $copy->id, 'status' => 'failed', 'attempts' => 1, 'available_at' => now(),
            ]);
        }
        $this->assertSame('99+', app(AdminTicketDeliveryQuery::class)->badgeLabel());

        $response = $this->actingAs($this->userWithRole('manager'))->get(route('admin.ticket-deliveries.index'));
        $response->assertOk()->assertSee('99+');
        preg_match_all('/<a[^>]*aria-current="page"[^>]*>/s', $response->getContent(), $activeLinks);
        $this->assertCount(1, $activeLinks[0], implode("\n", $activeLinks[0]));
    }

    public function test_checkin_history_is_read_only_filtered_and_contains_no_capability(): void
    {
        $delivery = $this->verifiedDelivery();
        $actor = $this->userWithRole('staff');
        $event = TicketCheckinEvent::query()->create([
            'booking_id' => $delivery->booking_id,
            'showtime_id' => $delivery->booking->showtime_id,
            'actor_user_id' => $actor->id,
            'actor_role_snapshot' => 'staff',
            'result' => 'already_used',
            'reason_code' => 'booking_already_used',
            'scanned_at' => now(),
            'context' => ['source' => 'test'],
        ]);
        $secret = 'v1.'.$delivery->booking_id.'.'.str_repeat('A', 43);

        $response = $this->actingAs($this->userWithRole('manager'))->get(route('admin.ticket-checkins.index', [
            'booking_code' => $delivery->booking->booking_code,
            'duplicates_only' => 'yes',
        ]));
        $response->assertOk()->assertSee($delivery->booking->booking_code)->assertSee('Quét trùng')->assertDontSee($secret);
        $this->actingAs($this->userWithRole('manager'))->get(route('admin.ticket-checkins.show', $event))
            ->assertOk()->assertSee('Bản ghi chỉ đọc')->assertDontSee($secret)->assertDontSee('safe_ip_hash');

        foreach (['admin.ticket-checkins.update', 'admin.ticket-checkins.destroy', 'admin.ticket-checkins.retry'] as $route) {
            $this->assertFalse(app('router')->getRoutes()->hasNamedRoute($route));
        }
    }

    public function test_phase_four_admin_pages_have_bounded_query_counts(): void
    {
        $delivery = $this->verifiedDelivery();
        $actor = $this->userWithRole('staff');
        $event = TicketCheckinEvent::query()->create([
            'booking_id' => $delivery->booking_id,
            'showtime_id' => $delivery->booking->showtime_id,
            'actor_user_id' => $actor->id,
            'actor_role_snapshot' => 'staff',
            'result' => 'accepted',
            'reason_code' => 'verified_paid_ticket',
            'scanned_at' => now(),
        ]);
        $manager = $this->userWithRole('manager');

        foreach ([
            [route('admin.ticket-deliveries.index'), 14, 'delivery index'],
            [route('admin.ticket-deliveries.show', $delivery), 18, 'delivery detail'],
            [route('admin.ticket-checkins.index'), 16, 'check-in index'],
            [route('admin.ticket-checkins.show', $event), 18, 'check-in detail'],
            [route('admin.bookings.show', $delivery->booking), 23, 'booking detail'],
        ] as [$url, $limit, $label]) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $response = $this->actingAs($manager)->get($url);
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            $response->assertOk();
            $this->assertLessThanOrEqual($limit, $count, $label.' có dấu hiệu N+1.');
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(AdminTicketDeliveryQuery::class)->badgeLabel();
        $badgeCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(1, $badgeCount);
    }

    private function verifiedDelivery(): BookingTicketDelivery
    {
        $payment = $this->pendingPayment();
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))->assertJsonPath('return_code', 1);

        return BookingTicketDelivery::query()->with('booking')->where('booking_id', $payment->booking_id)->sole();
    }
}
