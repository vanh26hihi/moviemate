<?php

namespace Tests\Feature\Defense;

use App\Models\Booking;
use App\Models\BookingPromotion;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\Showtime;
use App\Models\User;
use App\Services\PublicShowtimeCatalog;
use App\Services\Reports\AuthoritativePaymentQuery;
use App\Services\Seats\SeatSelectionPolicy;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCinemaLayoutSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RoomSeeder;
use Database\Seeders\ShowtimeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class DemoReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_demo_data_covers_all_human_actors_and_the_defense_seat_scenario(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-15 01:00:00', 'UTC'));
        $this->seed(DatabaseSeeder::class);

        foreach ([
            'admin@moviemate.test' => 'admin',
            'manager.cg@moviemate.test' => 'manager',
            'staff.cg@moviemate.test' => 'staff',
            'customer@moviemate.test' => 'user',
        ] as $email => $role) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->assertSame($role, $user->role->slug);
            $this->assertTrue(Hash::check('MovieMateDemo2026!', $user->password));
        }

        $customer = User::query()->where('email', 'customer@moviemate.test')->sole();
        $this->assertSame(0, $customer->cinemaAssignments()->count());
        $this->assertDatabaseHas('user_cinema_assignments', [
            'user_id' => User::query()->where('email', 'manager.cg@moviemate.test')->value('id'),
            'cinema_id' => Room::query()->where('code', 'DEMO')->value('cinema_id'),
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('user_cinema_assignments', [
            'user_id' => User::query()->where('email', 'staff.cg@moviemate.test')->value('id'),
            'cinema_id' => Room::query()->where('code', 'DEMO')->value('cinema_id'),
            'status' => 'active',
        ]);
        foreach (['admin.access', 'counter_sales.view', 'counter_sales.create', 'tickets.lookup', 'tickets.print'] as $permission) {
            $this->assertFalse($customer->hasPermission($permission));
        }

        $this->assertSame(['IMAX', 'STANDARD'], Room::query()->distinct()->orderBy('room_type')->pluck('room_type')->all());
        $this->assertSame(1, Room::query()->where('code', 'DEMO')->count());
        $room = Room::query()->where('code', 'DEMO')->firstOrFail();
        $layout = $room->latestPublishedLayout()->firstOrFail();
        $this->assertSame(4, $layout->rows);
        $this->assertSame(9, $layout->columns);
        $this->assertSame([6_500, 9_000, 58_500_000, '58,50'], [
            $room->width_mm,
            $room->length_mm,
            $room->areaMm2(),
            $room->formattedAreaM2(),
        ]);
        $this->assertSame(28, $layout->cells()->where('cell_type', 'seat')->count());
        $this->assertSame(27, $room->seats()->where('status', 'active')->count());
        $this->assertSame(27, $room->seats()->where('type', '!=', 'couple')->count()
            + $room->seats()->where('type', 'couple')->distinct()->count('pair_code'));
        $this->assertDatabaseHas('room_layout_cells', [
            'room_layout_id' => $layout->id,
            'x_position' => 9,
            'y_position' => 2,
            'cell_type' => 'blocked',
            'seat_id' => null,
        ]);
        $this->assertDatabaseMissing('room_layout_cells', [
            'room_layout_id' => $layout->id,
            'x_position' => 9,
            'y_position' => 3,
        ]);
        $defenseSeats = $room->seats()->whereIn('seat_code', ['D6', 'D7', 'D8'])
            ->orderBy('x_position')->get();
        $this->assertSame(['D6', 'D7', 'D8'], $defenseSeats->pluck('seat_code')->all());
        $this->assertSame([6, 7, 8], $defenseSeats->pluck('x_position')->all());
        $this->assertSame([4, 4, 4], $defenseSeats->pluck('y_position')->all());
        $couple = $room->seats()->where('pair_code', 'DEMO-C-PAIR-1')->orderBy('pair_position')->get();
        $this->assertCount(2, $couple);
        $this->assertSame(['left', 'right'], $couple->pluck('pair_position')->sort()->values()->all());
        $this->assertTrue($layout->cells()->where('x_position', 5)->where('y_position', 4)
            ->where('cell_type', 'aisle')->exists());

        $policy = app(SeatSelectionPolicy::class);
        $cells = $layout->cells()->with('seat')->get();
        $this->assertTrue($policy->violates($layout, [], [$defenseSeats[0]->id, $defenseSeats[2]->id], $cells));
        $this->assertFalse($policy->violates($layout, [], $defenseSeats->pluck('id'), $cells));

        $demoShowtimes = Showtime::query()->where('room_id', $room->id)->with('roomLayout')->get();
        $this->assertNotEmpty($demoShowtimes);
        $this->assertTrue($demoShowtimes->every(fn (Showtime $showtime) => $showtime->room_layout_id === $layout->id));
        $this->assertTrue($demoShowtimes->every(fn (Showtime $showtime) => $showtime->roomLayout?->room_id === $room->id));

        $showtime = $demoShowtimes->first(fn (Showtime $showtime) => app(PublicShowtimeCatalog::class)->isCustomerSellable($showtime));
        $this->assertNotNull($showtime);
        $this->assertSame($layout->id, $showtime->room_layout_id);
        $this->assertTrue(app(PublicShowtimeCatalog::class)->isCustomerSellable($showtime));
        $this->assertGreaterThanOrEqual(2, $demoShowtimes
            ->filter(fn (Showtime $candidate) => app(PublicShowtimeCatalog::class)->isCustomerSellable($candidate))->count());

        $this->assertSame(3, Payment::query()->whereIn('provider', Payment::SUPPORTED_PROVIDERS)
            ->where('status', Payment::STATUS_SUCCESS)->count());
        $this->assertDatabaseCount('bookings', 4);
        $this->assertDatabaseCount('admission_tickets', 6);
        $this->assertDatabaseCount('food_pickup_vouchers', 1);

        $paidFixture = Booking::query()->with([
            'payments', 'bookingSeats.seat', 'foodOrder.items.food', 'promotionUsage',
            'admissionTickets', 'foodPickupVoucher', 'showtime.room.cinema',
        ])->where('booking_code', 'MMT-2026-0000000000000004')->sole();
        $payment = $paidFixture->payments->sole();
        $this->assertSame($customer->id, $paidFixture->user_id);
        $this->assertSame('CG', $paidFixture->showtime->room->cinema->code);
        $this->assertSame(['D1'], $paidFixture->bookingSeats->pluck('seat.seat_code')->all());
        $this->assertSame('Bắp rang bơ', $paidFixture->foodOrder->items->sole()->food->name);
        $this->assertSame('MOVIEMATE10', $paidFixture->promotionUsage?->code_snapshot);
        $this->assertSame(BookingPromotion::STATUS_REDEEMED, $paidFixture->promotionUsage?->status);
        $this->assertSame((int) $paidFixture->gross_amount - (int) $paidFixture->promotion_discount_amount, (int) $paidFixture->total_amount);
        $this->assertSame((int) $paidFixture->total_amount, $payment->amount);
        $this->assertTrue($payment->hasAuthoritativeSuccessEvidence());
        $this->assertNotNull($payment->verified_at);
        $this->assertNotNull($payment->query_response_hash);
        $this->assertTrue(app(AuthoritativePaymentQuery::class)->authoritative()
            ->where('booking_id', $paidFixture->id)->exists());
        $this->assertTrue($paidFixture->admissionTickets->every(fn ($ticket): bool => $ticket->print_count === 0));
        $this->assertSame(0, $paidFixture->foodPickupVoucher?->print_count);
        $this->assertSame(1, Promotion::query()->where('code', 'MOVIEMATE10')->sole()->usages()
            ->where('status', BookingPromotion::STATUS_REDEEMED)->count());
    }

    public function test_demo_seeders_are_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $before = [
            'rooms' => Room::query()->count(),
            'layouts' => Room::query()->whereHas('latestPublishedLayout')->count(),
            'showtimes' => Showtime::query()->count(),
            'users' => User::query()->count(),
        ];

        $this->seed([RoomSeeder::class, DemoCinemaLayoutSeeder::class, ShowtimeSeeder::class, DemoUserSeeder::class]);

        $this->assertSame($before, [
            'rooms' => Room::query()->count(),
            'layouts' => Room::query()->whereHas('latestPublishedLayout')->count(),
            'showtimes' => Showtime::query()->count(),
            'users' => User::query()->count(),
        ]);
    }

    public function test_ticket_qr_and_scanner_dependencies_are_lazy_and_have_safe_fallbacks(): void
    {
        $app = File::get(resource_path('js/app.js'));
        $scanner = File::get(resource_path('js/ticket-scanner.js'));

        $this->assertStringNotContainsString("import QRCode from 'qrcode'", $app);
        $this->assertStringContainsString("await import('qrcode')", $app);
        $this->assertStringContainsString("import('./ticket-scanner').catch", $app);
        $this->assertStringContainsString('Không thể tải trình quét camera', $app);
        $this->assertStringContainsString("workspace.dataset.scannerInitialized === 'true'", $scanner);
        $this->assertStringContainsString("window.addEventListener('pagehide', stopCamera);", $scanner);
    }

    public function test_demo_check_is_read_only_and_reports_the_semantic_fixture_chain(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-15 01:00:00', 'UTC'));
        $this->seed(DatabaseSeeder::class);
        $mutations = [];
        DB::listen(function ($query) use (&$mutations): void {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|drop|create|truncate)\b/i', $query->sql)) {
                $mutations[] = $query->sql;
            }
        });

        $this->artisan('moviemate:demo-check')
            ->expectsOutputToContain('MMT-2026-0000000000000004')
            ->expectsOutputToContain('MOVIEMATE10')
            ->expectsOutputToContain('AISLE: 5x1, 5x2, 5x3, 5x4')
            ->expectsOutputToContain('BLOCKED: 9x2')
            ->expectsOutputToContain('EMPTY: 9x1, 9x3, 9x4')
            ->expectsOutputToContain('DEMO READY')
            ->assertSuccessful();

        $this->assertSame([], $mutations);
    }

    public function test_demo_check_fails_without_repairing_consumed_first_print_state(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-15 01:00:00', 'UTC'));
        $this->seed(DatabaseSeeder::class);
        $booking = Booking::query()->where('booking_code', 'MMT-2026-0000000000000004')->sole();
        $ticket = $booking->admissionTickets()->firstOrFail();
        $ticket->forceFill(['print_count' => 1, 'last_printed_at' => now()])->save();

        $this->artisan('moviemate:demo-check')
            ->expectsOutputToContain('DEMO NOT READY')
            ->assertFailed();

        $this->assertSame(1, $ticket->fresh()->print_count);
    }
}
