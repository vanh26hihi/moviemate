<?php

namespace Tests\Feature\Rooms;

use App\Models\Room;
use App\Models\SeatIncident;
use App\Models\Showtime;
use App\Models\User;
use App\Services\PublicShowtimeCatalog;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ExistingSeatIdentityLayoutPresentationHotfixTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_persisted_seat_identity_is_shared_by_manager_and_customer_presentations_without_mutation(): void
    {
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-08-15 01:00:00', 'UTC'));
        $this->seed(DatabaseSeeder::class);

        $room = Room::query()->where('code', 'DEMO')->sole();
        $layout = $room->latestPublishedLayout()->sole();
        $manager = User::query()->where('email', 'manager.cg@moviemate.test')->sole();
        $customer = User::query()->where('email', 'customer@moviemate.test')->sole();
        $before = $this->identitySnapshot($room, $layout->id);
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $managerResponse = $this->actingAs($manager)->get(route('admin.rooms.layout.show', $room));
        $managerQueryCount = $queries;
        $managerResponse->assertOk();
        $this->assertSame(86, $managerQueryCount, 'Manager layout query count changed from the measured pre-hotfix baseline.');
        foreach ([
            '\u0022row\u0022:\u0022A\u0022,\u0022number\u0022:1,\u0022seat_code\u0022:\u0022A1\u0022',
            '\u0022row\u0022:\u0022A\u0022,\u0022number\u0022:4,\u0022seat_code\u0022:\u0022A4\u0022',
            '\u0022row\u0022:\u0022C\u0022,\u0022number\u0022:1,\u0022seat_code\u0022:\u0022C1\u0022',
            '\u0022row\u0022:\u0022C\u0022,\u0022number\u0022:2,\u0022seat_code\u0022:\u0022C2\u0022',
            '\u0022row\u0022:\u0022D\u0022,\u0022number\u0022:8,\u0022seat_code\u0022:\u0022D8\u0022',
        ] as $persistedIdentity) {
            $managerResponse->assertSee($persistedIdentity, false);
        }
        foreach (['\u0022seat_code\u0022:\u0022A5\u0022', '\u0022seat_code\u0022:\u0022C5\u0022', '\u0022seat_code\u0022:\u0022D14\u0022'] as $falseIdentity) {
            $managerResponse->assertDontSee($falseIdentity, false);
        }
        $this->assertSame($before, $this->identitySnapshot($room, $layout->id));

        $showtime = Showtime::query()->with([
            'movie', 'cinema.operatingHours', 'room.cinema.operatingHours', 'roomLayout.cells.seat',
            'presentationFormat', 'ticketPrices.seatType',
        ])->where('room_id', $room->id)->where('status', 'active')
            ->orderBy('show_date')->orderBy('show_time')->get()
            ->first(fn (Showtime $candidate): bool => app(PublicShowtimeCatalog::class)->isCustomerSellable($candidate));
        $this->assertNotNull($showtime);
        $customerResponse = $this->actingAs($customer)->get(route('user.bookings.selectSeat', [
            'showtime' => $showtime,
            'cinema' => 'CG',
        ]));
        $customerResponse->assertOk()
            ->assertSee('Ghế A1, loại Thường', false)
            ->assertSee('Ghế đôi C1–C2', false)
            ->assertSee('Ghế D8, loại Thường, đang bảo trì', false);
        $this->assertSame($before, $this->identitySnapshot($room, $layout->id));

        $couple = collect($before['seats'])->whereIn('seat_code', ['C1', 'C2'])->values();
        $this->assertSame(['C1', 'C2'], $couple->pluck('seat_code')->all());
        $this->assertSame(['left', 'right'], $couple->pluck('pair_position')->all());
        $this->assertCount(1, $couple->pluck('pair_code')->unique());
        $maintenance = collect($before['seats'])->firstWhere('seat_code', 'D8');
        $this->assertSame(['D', 8, 'maintenance'], [
            $maintenance['row'], $maintenance['number'], $maintenance['status'],
        ]);
    }

    private function identitySnapshot(Room $room, int $layoutId): array
    {
        $codes = ['A1', 'A4', 'C1', 'C2', 'D8'];

        return [
            'seats' => $room->seats()->whereIn('seat_code', $codes)->orderBy('seat_code')->get()
                ->map->only(['id', 'row', 'number', 'seat_code', 'status', 'pair_code', 'pair_position'])->all(),
            'cells' => DB::table('room_layout_cells')->where('room_layout_id', $layoutId)
                ->orderBy('y_position')->orderBy('x_position')->get()->map(fn ($cell) => (array) $cell)->all(),
            'incidents' => SeatIncident::query()->where('room_id', $room->id)->get()->map->getAttributes()->all(),
        ];
    }
}
