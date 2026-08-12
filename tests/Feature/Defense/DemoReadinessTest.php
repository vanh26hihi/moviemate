<?php

namespace Tests\Feature\Defense;

use App\Models\Payment;
use App\Models\Room;
use App\Models\Showtime;
use App\Models\User;
use App\Services\PublicShowtimeCatalog;
use App\Services\Seats\SeatSelectionPolicy;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCinemaLayoutSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RoomSeeder;
use Database\Seeders\ShowtimeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class DemoReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_demo_data_covers_all_human_actors_and_the_defense_seat_scenario(): void
    {
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
        foreach (['admin.access', 'counter_sales.view', 'counter_sales.create', 'tickets.lookup', 'tickets.print'] as $permission) {
            $this->assertFalse($customer->hasPermission($permission));
        }

        $this->assertSame(['IMAX', 'STANDARD'], Room::query()->distinct()->orderBy('room_type')->pluck('room_type')->all());
        $this->assertSame(1, Room::query()->where('code', 'DEMO')->count());
        $room = Room::query()->where('code', 'DEMO')->firstOrFail();
        $layout = $room->latestPublishedLayout()->firstOrFail();
        $this->assertSame(4, $layout->rows);
        $this->assertSame(8, $layout->columns);
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

        $showtime = Showtime::query()->where('room_id', $room->id)->where('status', 'active')
            ->where('show_date', '>=', now($room->cinema->timezone)->toDateString())->firstOrFail();
        $this->assertSame($layout->id, $showtime->room_layout_id);
        $this->assertTrue(app(PublicShowtimeCatalog::class)->isSellable($showtime));

        $this->assertSame(3, Payment::query()->whereIn('provider', Payment::SUPPORTED_PROVIDERS)
            ->where('status', Payment::STATUS_SUCCESS)->count());
        $this->assertDatabaseCount('bookings', 4);
        $this->assertDatabaseCount('admission_tickets', 6);
        $this->assertDatabaseCount('food_pickup_vouchers', 1);
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
}
