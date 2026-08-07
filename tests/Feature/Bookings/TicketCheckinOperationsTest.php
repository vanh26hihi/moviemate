<?php

namespace Tests\Feature\Bookings;

use App\Models\ActivityLog;
use App\Models\TicketCheckinEvent;
use App\Models\UserCinemaAssignment;
use App\Services\Tickets\TicketCheckinCapability;
use App\Services\Tickets\TicketQrPayload;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Feature\Payments\PaymentTestCase;

final class TicketCheckinOperationsTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_paid_ticket_is_accepted_atomically_with_actor_and_safe_audit(): void
    {
        $payment = $this->verifiedPayment();
        $booking = $payment->booking->fresh();
        $capability = app(TicketCheckinCapability::class)->issue($booking);
        $staff = $this->userWithRole('staff');

        $this->actingAs($this->userWithRole('user'))->post(route('staff.tickets.consume'), ['ticket' => $capability])
            ->assertForbidden();
        $this->assertDatabaseCount('ticket_checkin_events', 0);

        $this->actingAs($staff)->post(route('staff.tickets.consume'), ['ticket' => $capability])
            ->assertRedirect(route('staff.tickets.check'))
            ->assertSessionHas('checkin_result.result', 'accepted');

        $booking->refresh();
        $this->assertSame('used', $booking->booking_status);
        $this->assertNotNull($booking->used_at);
        $this->assertDatabaseHas('ticket_checkin_events', [
            'booking_id' => $booking->id, 'actor_user_id' => $staff->id,
            'actor_role_snapshot' => 'staff', 'result' => 'accepted',
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ticket.checkin_accepted']);
        $serialized = json_encode(TicketCheckinEvent::query()->sole()->getAttributes(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($capability, $serialized);
        $this->assertStringNotContainsString('v1.', $serialized);
    }

    public function test_duplicate_scan_preserves_first_used_time_and_records_only_duplicate(): void
    {
        $payment = $this->verifiedPayment();
        $booking = $payment->booking->fresh();
        $capability = app(TicketCheckinCapability::class)->issue($booking);
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->post(route('staff.tickets.consume'), ['ticket' => $capability]);
        $usedAt = $booking->fresh()->getRawOriginal('used_at');
        $this->travel(2)->minutes();
        $this->actingAs($staff)->post(route('staff.tickets.consume'), ['ticket' => $capability])
            ->assertSessionHas('checkin_result.result', 'already_used');

        $this->assertSame($usedAt, $booking->fresh()->getRawOriginal('used_at'));
        $this->assertSame(1, TicketCheckinEvent::query()->where('result', 'accepted')->count());
        $this->assertSame(1, TicketCheckinEvent::query()->where('result', 'already_used')->count());
        $this->assertSame(1, ActivityLog::query()->where('action', 'ticket.checkin_duplicate')->count());
    }

    public function test_resolved_booking_can_be_checked_in_explicitly_without_printing(): void
    {
        $payment = $this->verifiedPayment();
        $booking = $payment->booking->fresh();
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('staff.tickets.operations', $booking))
            ->assertOk()->assertSee('Soát vé');
        $this->assertDatabaseCount('ticket_checkin_events', 0);

        $this->post(route('staff.tickets.consume-booking', $booking))
            ->assertRedirect(route('staff.tickets.operations', $booking))
            ->assertSessionHas('checkin_result.result', 'accepted');

        $this->assertSame('used', $booking->fresh()->booking_status);
        $this->assertDatabaseHas('ticket_checkin_events', [
            'booking_id' => $booking->id,
            'actor_user_id' => $staff->id,
            'result' => 'accepted',
        ]);
        $this->assertDatabaseCount('booking_ticket_prints', 0);
    }

    public function test_revoked_branch_assignment_blocks_secure_qr_and_direct_booking_checkin(): void
    {
        $payment = $this->verifiedPayment();
        $booking = $payment->booking->fresh();
        $staff = $this->userWithRole('staff');
        $capability = app(TicketCheckinCapability::class)->issue($booking);
        UserCinemaAssignment::query()->where('user_id', $staff->id)->update(['status' => 'revoked']);

        $this->actingAs($staff)->post(route('staff.tickets.consume'), ['ticket' => $capability])->assertNotFound();
        $this->post(route('staff.tickets.consume-booking', $booking))->assertNotFound();
        $this->assertSame('paid', $booking->fresh()->booking_status);
        $this->assertDatabaseCount('ticket_checkin_events', 0);
    }

    public function test_invalid_unpaid_cancelled_and_expired_scans_never_become_used(): void
    {
        $staff = $this->userWithRole('staff');
        $unpaid = $this->payableBooking();
        $unpaidCapability = app(TicketCheckinCapability::class)->issue($unpaid);
        $this->actingAs($staff)->post(route('staff.tickets.consume'), ['ticket' => $unpaidCapability])
            ->assertSessionHas('checkin_result.result', 'unpaid');

        $cancelled = $this->payableBooking(['booking_status' => 'cancelled']);
        $this->actingAs($staff)->post(route('staff.tickets.consume'), [
            'ticket' => app(TicketCheckinCapability::class)->issue($cancelled),
        ])->assertSessionHas('checkin_result.result', 'cancelled');

        $expired = $this->payableBooking(['booking_status' => 'expired']);
        $this->actingAs($staff)->post(route('staff.tickets.consume'), [
            'ticket' => app(TicketCheckinCapability::class)->issue($expired),
        ])->assertSessionHas('checkin_result.result', 'expired');

        $invalid = 'v1.'.$unpaid->id.'.'.str_repeat('A', 43);
        $this->actingAs($staff)->post(route('staff.tickets.consume'), ['ticket' => $invalid])
            ->assertSessionHas('checkin_result.result', 'invalid_token');

        $this->assertDatabaseCount('ticket_checkin_events', 4);
        $this->assertSame(0, TicketCheckinEvent::query()->where('result', 'accepted')->count());
        $this->assertSame(0, ActivityLog::query()->where('action', 'ticket.checkin_accepted')->count());
        $this->assertNotSame('used', $unpaid->fresh()->booking_status);
        $this->assertNotSame('used', $cancelled->fresh()->booking_status);
        $this->assertNotSame('used', $expired->fresh()->booking_status);
    }

    public function test_malformed_capability_is_rate_limited_without_database_spam(): void
    {
        $staff = $this->userWithRole('staff');
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $this->actingAs($staff)->post(route('staff.tickets.consume'), ['ticket' => 'malformed'])->assertRedirect();
        }
        $this->actingAs($staff)->post(route('staff.tickets.consume'), ['ticket' => 'malformed'])->assertTooManyRequests();
        $this->assertDatabaseCount('ticket_checkin_events', 0);
    }

    public function test_checkin_events_are_append_only_in_model_and_database(): void
    {
        $this->assertTrue(Schema::hasTable('ticket_checkin_events'));
        $indexColumns = collect(Schema::getIndexes('ticket_checkin_events'))
            ->map(fn (array $index): array => $index['columns'])
            ->values();
        foreach ([
            ['booking_id'],
            ['showtime_id'],
            ['actor_user_id'],
            ['result'],
            ['scanned_at'],
            ['booking_id', 'result'],
        ] as $expectedIndex) {
            $this->assertTrue($indexColumns->contains($expectedIndex), 'Missing check-in index: '.implode(', ', $expectedIndex));
        }
        foreach (['ticket_token', 'guest_token', 'qr_token', 'capability', 'raw_ip'] as $forbiddenColumn) {
            $this->assertNotContains($forbiddenColumn, Schema::getColumnListing('ticket_checkin_events'));
        }
        $payment = $this->verifiedPayment();
        $event = TicketCheckinEvent::query()->create([
            'booking_id' => $payment->booking_id,
            'showtime_id' => $payment->booking->showtime_id,
            'result' => 'accepted', 'scanned_at' => now(),
        ]);

        try {
            $event->forceFill(['result' => 'rejected'])->save();
            $this->fail('Model update must be rejected.');
        } catch (LogicException) {
            $this->assertSame('accepted', $event->fresh()->result);
        }

        $this->expectException(QueryException::class);
        TicketCheckinEvent::query()->whereKey($event->id)->update(['result' => 'rejected']);
    }

    public function test_checkin_event_delete_is_rejected_by_model(): void
    {
        $payment = $this->verifiedPayment();
        $event = TicketCheckinEvent::query()->create([
            'booking_id' => $payment->booking_id, 'result' => 'accepted', 'scanned_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $event->delete();
    }

    public function test_checkin_event_delete_is_rejected_by_database_trigger(): void
    {
        $payment = $this->verifiedPayment();
        $event = TicketCheckinEvent::query()->create([
            'booking_id' => $payment->booking_id, 'result' => 'accepted', 'scanned_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('ticket_checkin_events')->where('id', $event->id)->delete();
    }

    public function test_booking_detail_shows_event_counts_and_legacy_used_fallback(): void
    {
        $payment = $this->verifiedPayment();
        $booking = $payment->booking->fresh();
        $staff = $this->userWithRole('staff');
        $capability = app(TicketCheckinCapability::class)->issue($booking);
        $this->actingAs($staff)->post(route('staff.tickets.consume'), ['ticket' => $capability]);
        $this->actingAs($staff)->post(route('staff.tickets.consume'), ['ticket' => $capability]);

        $this->actingAs($this->userWithRole('manager'))->get(route('admin.bookings.show', $booking))
            ->assertOk()->assertSee($staff->name)->assertSee('Lần quét trùng')->assertSee('Xem toàn bộ lịch sử soát vé');

        $legacy = $this->payableBooking(['booking_status' => 'used', 'payment_status' => 'paid', 'used_at' => now()->subDay()]);
        $this->actingAs($this->userWithRole('manager'))->get(route('admin.bookings.show', $legacy))
            ->assertOk()->assertSee('Không có dữ liệu lịch sử');
        $this->assertDatabaseMissing('ticket_checkin_events', ['booking_id' => $legacy->id]);
    }

    public function test_ticket_qr_uses_signed_capability_and_used_ticket_remains_readable(): void
    {
        [$owner, $payment] = $this->verifiedOwnerPayment();
        $booking = $payment->booking->fresh();
        $capability = app(TicketQrPayload::class)->url($booking);

        $this->actingAs($owner)->get(route('user.bookings.ticket', $booking))
            ->assertOk()->assertSee('data-qr-value="'.$capability.'"', false)
            ->assertDontSee('data-qr-value="'.$booking->booking_code.'"', false);

        $this->get($capability)->assertOk()
            ->assertSee($booking->booking_code)
            ->assertSee('QR dùng để xác minh vé.');

        $booking->forceFill(['booking_status' => 'used', 'used_at' => now()])->save();
        $this->actingAs($owner)->get(route('user.bookings.ticket', $booking))
            ->assertOk()->assertSee('VÉ ĐÃ ĐƯỢC SỬ DỤNG')
            ->assertSee('data-qr-value="'.$capability.'"', false);
    }

    private function verifiedPayment()
    {
        $payment = $this->pendingPayment();
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))->assertJsonPath('return_code', 1);

        return $payment->fresh();
    }

    private function verifiedOwnerPayment(): array
    {
        $owner = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id], $owner->id)->booking;
        $payment = $this->pendingPayment($booking);
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))->assertJsonPath('return_code', 1);

        return [$owner, $payment->fresh()];
    }
}
