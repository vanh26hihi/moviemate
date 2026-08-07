<?php

namespace Tests\Feature\Counter;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\BookingTicketDelivery;
use App\Models\BookingTicketPrint;
use App\Models\Cinema;
use App\Models\CinemaPricingRule;
use App\Models\FoodItem;
use App\Models\Movie;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\TicketCheckinEvent;
use App\Models\User;
use App\Services\BookingTokenService;
use App\Services\Tickets\TicketCheckinCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

final class CounterSalesR8Test extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_schema_and_online_defaults_are_backward_compatible(): void
    {
        $this->assertTrue(Schema::hasColumns('bookings', [
            'sales_channel', 'created_by_staff_id', 'customer_name', 'customer_phone',
        ]));
        $this->assertTrue(Schema::hasColumns('payments', ['settled_by_user_id', 'settled_at']));

        $scenario = $this->bookingScenario(false);
        $online = $this->reserve($scenario, [$scenario['seats'][0]->id])->booking;

        $this->assertSame(Booking::SALES_CHANNEL_ONLINE, $online->sales_channel);
        $this->assertNull($online->created_by_staff_id);
        $this->assertNull($online->createdByStaff);
    }

    public function test_counter_workspace_permissions_and_inactive_actor_are_enforced(): void
    {
        $staff = $this->userWithRole('staff');
        $customer = $this->userWithRole('user');

        $this->actingAs($staff)->get(route('staff.counter.index'))->assertOk()->assertSee('Bán vé tại quầy');
        $this->actingAs($customer)->get(route('staff.counter.index'))->assertForbidden();
        $staff->forceFill(['status' => 'inactive'])->save();
        $this->flushSession();
        $this->actingAs($staff->fresh())->get(route('staff.counter.index'))->assertRedirect(route('login'));
    }

    public function test_hold_derives_channel_creator_and_totals_and_rejects_forged_authority(): void
    {
        $scenario = $this->bookingScenario(false);
        $staff = $this->userWithRole('staff');
        $token = app(BookingTokenService::class)->issueCheckoutToken();

        $this->actingAs($staff)->post(route('staff.counter.hold', $scenario['showtime']), [
            'seat_ids' => [$scenario['seats'][0]->id],
            'checkout_token' => $token,
            'sales_channel' => 'online',
            'created_by_staff_id' => $this->userWithRole('staff')->id,
            'staff_id' => 999,
            'total_amount' => 1,
        ])->assertSessionHasErrors(['sales_channel', 'created_by_staff_id', 'staff_id', 'total_amount']);
        $this->assertDatabaseCount('bookings', 0);

        $this->post(route('staff.counter.hold', $scenario['showtime']), [
            'seat_ids' => [$scenario['seats'][0]->id],
            'checkout_token' => $token,
            'customer_name' => 'Khách tại quầy',
            'customer_phone' => '090 123-4567',
        ])->assertRedirect();

        $booking = Booking::query()->sole();
        $this->assertSame(Booking::SALES_CHANNEL_COUNTER, $booking->sales_channel);
        $this->assertSame($staff->id, $booking->created_by_staff_id);
        $this->assertNull($booking->user_id);
        $this->assertNull($booking->guest_access_token_hash);
        $this->assertSame('0901234567', $booking->customer_phone);
        $this->assertSame(50000, (int) $booking->seat_subtotal);
        $this->assertSame(50000, (int) $booking->total_amount);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'counter.booking_created', 'actor_user_id' => $staff->id,
        ]);
    }

    public function test_food_is_optional_branch_scoped_and_browser_totals_are_prohibited(): void
    {
        [$booking, $scenario, $staff] = $this->counterHold();
        $globalFood = FoodItem::query()->create(['name' => 'Nước', 'price' => 25000, 'active' => true]);
        $otherCinema = Cinema::factory()->create([
            'status' => 'active', 'archived_at' => null, 'is_primary' => false,
        ]);
        $otherFood = FoodItem::query()->create([
            'cinema_id' => $otherCinema->id, 'name' => 'Sai chi nhánh', 'price' => 1000, 'active' => true,
        ]);

        $this->actingAs($staff)->post(route('staff.counter.food.update', $booking), [
            'food_items' => [['food_id' => $otherFood->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('food_items');
        $this->post(route('staff.counter.food.update', $booking), [
            'food_items' => [['food_id' => $globalFood->id, 'quantity' => 2]],
            'total_amount' => 1,
        ])->assertSessionHasErrors('total_amount');
        $this->post(route('staff.counter.food.update', $booking), [
            'food_items' => [['food_id' => $globalFood->id, 'quantity' => 2]],
        ])->assertRedirect(route('staff.counter.review', $booking));
        $booking->refresh();
        $this->assertSame(50000, (int) $booking->food_subtotal);
        $this->assertSame(100000, (int) $booking->total_amount);

        $this->post(route('staff.counter.food.update', $booking), ['food_items' => []])->assertRedirect();
        $this->assertSame(0, (int) $booking->fresh()->food_subtotal);
        $this->assertNull($booking->fresh()->foodOrder);
    }

    public function test_counter_hold_reuses_gap_couple_and_competing_hold_guards(): void
    {
        $staff = $this->userWithRole('staff');
        $gapScenario = $this->normalRowScenario(Cinema::query()->active()->primary()->firstOrFail(), 4);
        $token = app(BookingTokenService::class)->issueCheckoutToken();
        $this->actingAs($staff)->post(route('staff.counter.hold', $gapScenario['showtime']), [
            'seat_ids' => [$gapScenario['seats'][1]->id], 'checkout_token' => $token,
        ])->assertSessionHasErrors('seat_ids');

        $coupleScenario = $this->bookingScenario(true);
        $this->post(route('staff.counter.hold', $coupleScenario['showtime']), [
            'seat_ids' => [$coupleScenario['seats'][2]->id],
            'checkout_token' => app(BookingTokenService::class)->issueCheckoutToken(),
        ])->assertSessionHasErrors('seat_ids');

        $competitorScenario = $this->bookingScenario(false);
        $this->reserve($competitorScenario, [$competitorScenario['seats'][0]->id]);
        $this->post(route('staff.counter.hold', $competitorScenario['showtime']), [
            'seat_ids' => [$competitorScenario['seats'][0]->id],
            'checkout_token' => app(BookingTokenService::class)->issueCheckoutToken(),
        ])->assertSessionHasErrors('seat_ids');
    }

    public function test_cash_settlement_uses_actual_settler_exact_amount_and_is_idempotent_without_http(): void
    {
        [$booking, , $creator] = $this->counterHold();
        $settler = $this->userWithRole('staff');
        Http::fake(fn () => throw new \RuntimeException('Counter cash must not call an external provider.'));

        $this->actingAs($settler)->post(route('staff.counter.cash', $booking), [
            'amount' => 1,
            'settled_by_user_id' => $creator->id,
        ])->assertSessionHasErrors(['amount', 'settled_by_user_id']);
        $this->assertSame('unpaid', $booking->fresh()->payment_status);

        $this->post(route('staff.counter.cash', $booking))->assertRedirect(route('staff.counter.review', $booking));
        $payment = Payment::query()->sole();
        $this->assertSame(Payment::PROVIDER_COUNTER_CASH, $payment->provider);
        $this->assertSame($settler->id, $payment->settled_by_user_id);
        $this->assertSame((int) $booking->total_amount, $payment->amount);
        $this->assertNull($payment->verified_at);
        $this->assertNotNull($payment->settled_at);
        $this->assertSame('COUNTER-'.$payment->id, $payment->transaction_code);
        $this->assertSame('paid', $booking->fresh()->booking_status);
        $this->assertSame($creator->id, $booking->fresh()->created_by_staff_id);
        $this->assertDatabaseCount('booking_ticket_deliveries', 0);
        Http::assertNothingSent();

        $this->post(route('staff.counter.cash', $booking))->assertRedirect();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('activity_logs', 2);
    }

    public function test_email_delivery_is_enqueued_exactly_once_only_when_recipient_exists(): void
    {
        Mail::fake();
        [$booking, , $staff] = $this->counterHold('counter@example.test');
        $this->actingAs($staff)->post(route('staff.counter.cash', $booking))->assertRedirect();
        $this->post(route('staff.counter.cash', $booking))->assertRedirect();

        $this->assertDatabaseCount('booking_ticket_deliveries', 1);
        $this->assertDatabaseHas('booking_ticket_deliveries', [
            'booking_id' => $booking->id, 'status' => BookingTicketDelivery::STATUS_SENT,
        ]);
    }

    public function test_counter_endpoint_cannot_settle_online_or_provider_attempt_bookings(): void
    {
        $staff = $this->userWithRole('staff');
        $onlineScenario = $this->bookingScenario(false);
        $online = $this->reserve($onlineScenario, [$onlineScenario['seats'][0]->id])->booking;
        Payment::createForProvider('vnpay', [
            'booking_id' => $online->id, 'payment_method' => 'vnpay',
            'order_code' => 'ONLINE-R8', 'amount' => (int) $online->total_amount,
            'currency' => 'VND', 'status' => Payment::STATUS_PENDING,
        ]);

        $this->actingAs($staff)->post(route('staff.counter.cash', $online))->assertNotFound();
        $this->assertSame('unpaid', $online->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_PENDING, Payment::query()->sole()->status);

        [$counter] = $this->counterHold();
        Payment::createForProvider('payos', [
            'booking_id' => $counter->id, 'payment_method' => 'payos',
            'order_code' => 'FORGED-R8', 'amount' => (int) $counter->total_amount,
            'currency' => 'VND', 'status' => Payment::STATUS_PENDING,
        ]);
        $this->actingAs($staff)->post(route('staff.counter.cash', $counter))->assertSessionHasErrors('booking');
        $this->assertSame('unpaid', $counter->fresh()->payment_status);
    }

    public function test_unpaid_counter_cancellation_releases_seat_but_paid_booking_cannot_cancel(): void
    {
        [$booking, $scenario, $staff] = $this->counterHold();
        $this->actingAs($staff)->post(route('staff.counter.cancel', $booking))->assertRedirect(route('staff.counter.index'));
        $this->assertSame('cancelled', $booking->fresh()->booking_status);
        $this->assertDatabaseMissing('booking_seats', [
            'booking_id' => $booking->id, 'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
        ]);
        $this->post(route('staff.counter.cancel', $booking))->assertRedirect();

        $replacement = $this->counterHoldForScenario($scenario, $staff);
        $this->post(route('staff.counter.cash', $replacement))->assertRedirect();
        $this->post(route('staff.counter.cancel', $replacement))->assertSessionHasErrors('booking');
        $this->assertSame('paid', $replacement->fresh()->booking_status);
        $this->assertDatabaseHas('booking_seats', [
            'booking_id' => $replacement->id, 'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
        ]);
    }

    public function test_creator_settler_printer_and_checkin_actor_remain_distinct(): void
    {
        [$booking, , $creator] = $this->counterHold();
        $settler = $this->userWithRole('staff');
        $printer = $this->userWithRole('staff');
        $checker = $this->userWithRole('staff');

        $this->actingAs($settler)->post(route('staff.counter.cash', $booking))->assertRedirect();
        $this->actingAs($printer)->post(route('staff.tickets.print.start', $booking))->assertRedirect();
        $this->post(route('staff.tickets.print.succeed', $booking))->assertRedirect();
        $capability = app(TicketCheckinCapability::class)->issue($booking->fresh());
        $this->actingAs($checker)->post(route('staff.tickets.consume'), ['ticket' => $capability])->assertRedirect();

        $this->assertSame($creator->id, $booking->fresh()->created_by_staff_id);
        $this->assertSame($settler->id, Payment::query()->sole()->settled_by_user_id);
        $this->assertSame($printer->id, BookingTicketPrint::query()->sole()->printed_by_user_id);
        $this->assertSame($checker->id, TicketCheckinEvent::query()->where('result', 'accepted')->sole()->actor_user_id);
        $this->assertSame('used', $booking->fresh()->booking_status);

        $creator->forceFill(['status' => 'inactive'])->save();
        $this->assertSame($creator->name, $booking->fresh()->createdByStaff?->name);
    }

    public function test_cross_branch_counter_urls_are_denied_and_global_admin_is_allowed(): void
    {
        $other = Cinema::factory()->create([
            'code' => 'HD-R8', 'name' => 'Hà Đông R8', 'status' => 'active',
            'archived_at' => null, 'is_primary' => false,
        ]);
        $scenario = $this->normalRowScenario($other, 2);
        $staff = $this->userWithRole('staff');
        $manager = $this->userWithRole('manager');
        $payload = [
            'seat_ids' => $scenario['seats']->pluck('id')->all(),
            'checkout_token' => app(BookingTokenService::class)->issueCheckoutToken(),
        ];

        $this->actingAs($staff)->get(route('staff.counter.seats', $scenario['showtime']))->assertNotFound();
        $this->post(route('staff.counter.hold', $scenario['showtime']), $payload)->assertNotFound();
        $this->actingAs($manager)->post(route('staff.counter.hold', $scenario['showtime']), $payload)->assertNotFound();

        $admin = $this->userWithRole('admin');
        $payload['checkout_token'] = app(BookingTokenService::class)->issueCheckoutToken();
        $this->actingAs($admin)->post(route('staff.counter.hold', $scenario['showtime']), $payload)->assertRedirect();
        $booking = Booking::query()->where('sales_channel', 'counter')->sole();

        $this->actingAs($staff)->get(route('staff.counter.review', $booking))->assertNotFound();
        $this->post(route('staff.counter.cash', $booking))->assertNotFound();
        $this->post(route('staff.counter.cancel', $booking))->assertNotFound();
        $this->actingAs($admin)->post(route('staff.counter.cash', $booking))->assertRedirect();
        $this->assertSame('paid', $booking->fresh()->booking_status);
        $this->assertSame($other->id, $booking->fresh()->cinema_id);
    }

    public function test_admin_filters_and_displays_channel_and_independent_actors(): void
    {
        [$booking, , $creator] = $this->counterHold();
        $settler = $this->userWithRole('staff');
        $this->actingAs($settler)->post(route('staff.counter.cash', $booking));
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('admin.bookings.index', ['sales_channel' => 'counter']))
            ->assertOk()->assertSee($booking->booking_code)->assertSee('Tại quầy');
        $this->get(route('admin.bookings.index', ['sales_channel' => 'online']))
            ->assertOk()->assertDontSee($booking->booking_code);
        $this->get(route('admin.bookings.show', $booking))->assertOk()
            ->assertSee('Thông tin bán vé')->assertSee($creator->name)->assertSee($settler->name)
            ->assertSee('Tiền mặt tại quầy');
        $payment = Payment::query()->where('booking_id', $booking->id)->sole();
        $this->get(route('admin.payments.index', ['provider' => Payment::PROVIDER_COUNTER_CASH]))
            ->assertOk()->assertSee($booking->booking_code)->assertSee('Tiền mặt tại quầy');
        $this->get(route('admin.payments.show', $payment))->assertOk()->assertSee($settler->name);
        $this->get(route('admin.payment-reconciliation.index'))->assertOk()->assertDontSee($booking->booking_code);
    }

    /** @return array{0: Booking, 1: array<string,mixed>, 2: User} */
    private function counterHold(?string $email = null): array
    {
        $scenario = $this->bookingScenario(false);
        $staff = $this->userWithRole('staff');

        return [$this->counterHoldForScenario($scenario, $staff, $email), $scenario, $staff];
    }

    private function counterHoldForScenario(array $scenario, User $staff, ?string $email = null): Booking
    {
        $token = app(BookingTokenService::class)->issueCheckoutToken();
        $this->actingAs($staff)->post(route('staff.counter.hold', $scenario['showtime']), [
            'seat_ids' => [$scenario['seats'][0]->id],
            'checkout_token' => $token,
            'customer_email' => $email,
        ])->assertRedirect();

        return Booking::query()->where('checkout_idempotency_key_hash', hash('sha256', $token))->sole();
    }

    /** @return array{cinema:Cinema,room:Room,movie:Movie,seats:Collection,layout:RoomLayout,showtime:Showtime} */
    private function normalRowScenario(Cinema $cinema, int $seatCount): array
    {
        foreach (range(1, 7) as $day) {
            $cinema->operatingHours()->updateOrCreate(['day_of_week' => $day], [
                'opens_at' => '08:00:00', 'latest_show_start_at' => '23:00:00', 'is_closed' => false,
            ]);
        }
        $room = Room::query()->create([
            'cinema_id' => $cinema->id, 'code' => 'R8'.str()->upper(str()->random(6)),
            'name' => 'R8 room', 'room_type' => '2D', 'total_seats' => $seatCount, 'status' => 'active',
        ]);
        $seats = collect();
        for ($number = 1; $number <= $seatCount; $number++) {
            $seats->push(Seat::query()->create([
                'room_id' => $room->id, 'row' => 'A', 'number' => $number,
                'seat_code' => 'A'.$number, 'type' => 'normal', 'status' => 'active',
            ]));
        }
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id, 'version' => 1, 'name' => 'R8 layout',
            'rows' => 1, 'columns' => $seatCount, 'status' => 'draft',
        ]);
        foreach ($seats as $index => $seat) {
            $layout->cells()->create([
                'x_position' => $index + 1, 'y_position' => 1,
                'cell_type' => 'seat', 'seat_id' => $seat->id,
            ]);
        }
        $layout->update(['status' => 'published', 'published_at' => now()]);
        $movie = Movie::query()->create([
            'title' => 'R8 movie '.str()->random(5), 'slug' => 'r8-'.str()->lower(str()->random(10)),
            'duration' => 90, 'status' => 'now_showing',
        ]);
        CinemaPricingRule::query()->create([
            'cinema_id' => $cinema->id, 'name' => 'R8 base '.str()->random(6),
            'rule_type' => 'base', 'amount_vnd' => 60000, 'priority' => 2000, 'status' => 'active',
        ]);
        $showtime = Showtime::query()->create([
            'movie_id' => $movie->id, 'cinema_id' => $cinema->id, 'room_id' => $room->id,
            'room_layout_id' => $layout->id, 'show_date' => now()->addDays(3)->toDateString(),
            'show_time' => '19:00:00', 'price' => 60000, 'pricing_version' => 'cinema-pricing-v1', 'status' => 'active',
        ]);

        return compact('cinema', 'room', 'movie', 'seats', 'layout', 'showtime');
    }
}
