<?php

namespace Tests\Feature\Rooms;

use App\Models\RoomLayoutCell;
use App\Models\Seat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

final class BlockedLayoutTaxonomyTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_customer_admin_and_staff_maps_render_blocked_as_a_non_seat(): void
    {
        $scenario = $this->bookingScenario(false);
        $layout = $scenario['layout'];
        RoomLayoutCell::query()->create([
            'room_layout_id' => $layout->id,
            'x_position' => 1,
            'y_position' => 2,
            'cell_type' => RoomLayoutCell::TYPE_BLOCKED,
            'seat_id' => null,
        ]);

        $customer = $this->get(route('user.bookings.selectSeat', $scenario['showtime']))->assertOk();
        $this->assertStructuralBlockedNode($customer->getContent(), 1);
        $this->assertSame(2, substr_count($customer->getContent(), 'data-seat-ids="'));

        $staff = $this->userWithRole('staff');
        $counter = $this->actingAs($staff)->get(route('staff.counter.seats', $scenario['showtime']))->assertOk();
        $this->assertStructuralBlockedNode($counter->getContent(), 1);
        $this->assertSame(2, substr_count($counter->getContent(), 'class="sr-only counter-seat"'));

        $preview = $this->get(route('staff.rooms.layout.preview', $scenario['room']))->assertOk();
        $this->assertStructuralBlockedNode($preview->getContent(), 1);
        $this->assertSame(2, Seat::query()->where('room_id', $scenario['room']->id)->count());
    }

    public function test_one_and_one_hundred_blocked_cells_have_constant_map_query_counts(): void
    {
        $scenario = $this->bookingScenario(false);
        $layout = $scenario['layout'];
        DB::table('room_layouts')->where('id', $layout->id)->update(['rows' => 10, 'columns' => 12]);
        RoomLayoutCell::query()->create([
            'room_layout_id' => $layout->id,
            'x_position' => 3,
            'y_position' => 1,
            'cell_type' => RoomLayoutCell::TYPE_BLOCKED,
            'seat_id' => null,
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('user.bookings.selectSeat', $scenario['showtime']))->assertOk();
        $customerOne = count(DB::getQueryLog());

        $admin = $this->userWithRole('admin');
        DB::flushQueryLog();
        $this->actingAs($admin)->get(route('admin.rooms.layout.show', $scenario['room']))->assertOk();
        $editorOne = count(DB::getQueryLog());

        $coordinates = collect(range(1, 10))->flatMap(fn (int $y) => collect(range(1, 12))
            ->map(fn (int $x): array => ['x' => $x, 'y' => $y]))
            ->reject(fn (array $coordinate): bool => $coordinate['y'] === 1 && in_array($coordinate['x'], [1, 2, 3], true))
            ->take(99);
        $now = now();
        DB::table('room_layout_cells')->insert($coordinates->map(fn (array $coordinate): array => [
            'room_layout_id' => $layout->id,
            'x_position' => $coordinate['x'],
            'y_position' => $coordinate['y'],
            'cell_type' => RoomLayoutCell::TYPE_BLOCKED,
            'seat_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        DB::flushQueryLog();
        $manyResponse = $this->get(route('user.bookings.selectSeat', $scenario['showtime']))->assertOk();
        $customerMany = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->get(route('admin.rooms.layout.show', $scenario['room']))->assertOk();
        $editorMany = count(DB::getQueryLog());

        $staff = $this->userWithRole('staff');
        DB::flushQueryLog();
        $this->actingAs($staff)->get(route('staff.counter.seats', $scenario['showtime']))->assertOk();
        $counterMany = count(DB::getQueryLog());
        DB::disableQueryLog();

        fwrite(STDERR, "PHASE6C_BLOCKED_QUERIES customer_one={$customerOne} customer_many={$customerMany} editor_one={$editorOne} editor_many={$editorMany} counter_many={$counterMany}".PHP_EOL);
        $this->assertSame(100, $layout->cells()->where('cell_type', RoomLayoutCell::TYPE_BLOCKED)->count());
        $this->assertSame(0, $layout->cells()->where('cell_type', RoomLayoutCell::TYPE_BLOCKED)->whereNotNull('seat_id')->count());
        $this->assertStructuralBlockedNode($manyResponse->getContent(), 100);
        $this->assertLessThanOrEqual($customerOne + 1, $customerMany);
        $this->assertLessThanOrEqual($editorOne + 1, $editorMany);
        $this->assertLessThanOrEqual(45, $customerMany);
        $this->assertLessThanOrEqual(20, $editorMany);
        $this->assertLessThanOrEqual(45, $counterMany);
    }

    private function assertStructuralBlockedNode(string $html, int $expected): void
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $nodes = (new \DOMXPath($document))->query('//*[contains(@aria-label, "Vật cản cố định")]');

        $this->assertSame($expected, $nodes?->length);
        foreach ($nodes ?: [] as $node) {
            $this->assertSame('span', $node->nodeName);
            $this->assertFalse($node->hasAttribute('data-seat-ids'));
            $this->assertFalse($node->hasAttribute('data-price'));
            $this->assertFalse($node->hasAttribute('disabled'));
        }
    }
}
