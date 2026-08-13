<?php

namespace Tests\Feature\Seats;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Cinema;
use App\Models\CinemaPricingRule;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\BookingTokenService;
use App\Services\CinemaContext;
use App\Services\RoomLayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DynamicSeatBookingTest extends TestCase
{
    use RefreshDatabase;

    private Cinema $cinema;

    private $rooms;

    private Showtime $showtime;

    private string $checkoutToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cinema = Cinema::query()->where('canonical_key', CinemaContext::CANONICAL_KEY)->firstOrFail();
        foreach ([
            ['name' => 'Dynamic seat base', 'rule_type' => 'base', 'seat_type' => null, 'amount_vnd' => 50000],
            ['name' => 'Dynamic seat VIP', 'rule_type' => 'seat_type', 'seat_type' => 'vip', 'amount_vnd' => 20000],
            ['name' => 'Dynamic seat couple', 'rule_type' => 'seat_type', 'seat_type' => 'couple', 'amount_vnd' => 50000],
        ] as $rule) {
            CinemaPricingRule::query()->create([
                ...$rule, 'cinema_id' => $this->cinema->id, 'priority' => 1000, 'status' => 'active',
            ]);
        }
        foreach (['P01', 'P02', 'P03'] as $index => $code) {
            Room::query()->create([
                'cinema_id' => $this->cinema->id, 'code' => $code, 'name' => 'Phòng '.($index + 1),
                'room_type' => '2D', 'width_mm' => 8_000, 'length_mm' => 10_000, 'status' => 'active',
            ]);
        }
        $this->artisan('moviemate:rebuild-seat-layouts', ['--initialize-empty' => true])->assertSuccessful();
        $this->rooms = Room::query()->whereIn('code', ['P01', 'P02', 'P03'])->get()->keyBy('code');
        $movieId = DB::table('movies')->insertGetId([
            'title' => 'Booking Movie', 'slug' => 'booking-movie', 'duration' => 100,
            'age_rating' => 'P', 'status' => 'now_showing', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $room = $this->rooms['P01'];
        $this->showtime = Showtime::query()->create([
            'movie_id' => $movieId, 'cinema_id' => $this->cinema->id, 'room_id' => $room->id,
            'presentation_format_id' => $this->presentationFormatFixture($movieId, $room)->id,
            'room_layout_id' => $room->latestPublishedLayout()->first()->id,
            'show_date' => now()->addDays(10)->toDateString(), 'show_time' => '10:00:00',
            'price' => 50000, 'vip_price' => 70000, 'status' => 'active',
        ]);
        $this->checkoutToken = app(BookingTokenService::class)->issueCheckoutToken();
    }

    public function test_user_map_renders_dynamic_coordinates_aisle_screen_and_pair_metadata(): void
    {
        $response = $this->get(route('user.bookings.selectSeat', $this->showtime));
        $response->assertOk()
            ->assertSee('Sơ đồ ghế động')
            ->assertSee('repeat(13', false)
            ->assertSee('Lối đi')
            ->assertSee('data-pair-code="K-PAIR-1"', false)
            ->assertSee('data-seat-code="K1–K2"', false)
            ->assertSee('data-seat-ids="', false)
            ->assertSee('data-price="100000"', false)
            ->assertSee('checkout-seat-couple col-span-2', false)
            ->assertDontSee('data-seat-code="K2"', false);
    }

    public function test_new_showtime_renders_freeform_h13_h14_while_existing_showtime_keeps_snapshot(): void
    {
        $room = $this->rooms['P01'];
        $service = app(RoomLayoutService::class);
        $originalHIds = $room->latestPublishedLayout()->with('cells')->firstOrFail()
            ->cells->where('y_position', 8)->where('cell_type', 'seat')->pluck('seat_id')->sort()->values();
        $draft = $service->clonePublishedToDraft($room)->load('cells.seat');
        $cells = $draft->cells->map(function ($cell): array {
            $x = $cell->x_position;
            if ($cell->cell_type === 'aisle') {
                return ['kind' => 'aisle', 'x' => $x, 'y' => $cell->y_position];
            }

            return [
                'kind' => $cell->seat->type, 'seat_id' => $cell->seat_id,
                'x' => $x, 'y' => $cell->y_position,
                'row' => $cell->seat->row, 'number' => $cell->seat->number,
                'seat_code' => $cell->seat->seat_code, 'status' => $cell->seat->status,
                'pair_code' => $cell->seat->pair_code, 'pair_position' => $cell->seat->pair_position,
            ];
        })->push(
            ['kind' => 'normal', 'x' => 14, 'y' => 8, 'row' => 'H', 'number' => 13, 'seat_code' => 'H13', 'status' => 'active'],
            ['kind' => 'normal', 'x' => 15, 'y' => 8, 'row' => 'H', 'number' => 14, 'seat_code' => 'H14', 'status' => 'active'],
            ['kind' => 'vip', 'x' => 14, 'y' => 10, 'row' => 'J', 'number' => 13, 'seat_code' => 'J13', 'status' => 'active'],
            ['kind' => 'vip', 'x' => 15, 'y' => 10, 'row' => 'J', 'number' => 14, 'seat_code' => 'J14', 'status' => 'active'],
            ['kind' => 'vip', 'x' => 16, 'y' => 10, 'row' => 'J', 'number' => 15, 'seat_code' => 'J15', 'status' => 'active'],
            ['kind' => 'vip', 'x' => 17, 'y' => 10, 'row' => 'J', 'number' => 16, 'seat_code' => 'J16', 'status' => 'active'],
        )->all();

        $saved = $service->saveDraft($draft, [
            'schema_version' => 3, 'rows' => 11, 'columns' => 17,
            'screen_position' => 'top', 'cells' => $cells,
        ]);
        $hIdsBeforePublish = $saved->cells->where('y_position', 8)->where('cell_type', 'seat')->pluck('seat_id')->sort()->values();
        $v2 = $service->publish($saved);
        $newShowtime = Showtime::query()->create([
            'movie_id' => $this->showtime->movie_id, 'cinema_id' => $this->cinema->id, 'room_id' => $room->id,
            'presentation_format_id' => $this->presentationFormatFixture($this->showtime->movie_id, $room)->id,
            'room_layout_id' => $v2->id, 'show_date' => now()->addDays(10)->toDateString(), 'show_time' => '13:00:00',
            'price' => 50000, 'vip_price' => 70000, 'status' => 'active',
        ]);

        $this->get(route('user.bookings.selectSeat', $newShowtime))
            ->assertOk()->assertSee('repeat(17', false)
            ->assertSee('data-seat-code="H13"', false)
            ->assertSee('data-seat-code="H14"', false)
            ->assertSee('data-seat-code="J16"', false);
        $this->get(route('user.bookings.selectSeat', $this->showtime))
            ->assertOk()->assertDontSee('data-seat-code="H13"', false)->assertDontSee('data-seat-code="H14"', false);
        $this->assertSame(14, $v2->cells()->where('y_position', 8)->where('cell_type', 'seat')->count());
        $this->assertSame(16, $v2->cells()->where('y_position', 10)->where('cell_type', 'seat')->count());
        $this->assertSame(12, $v2->cells()->where('y_position', 9)->where('cell_type', 'seat')->count());
        $this->assertDatabaseHas('room_layout_cells', ['room_layout_id' => $v2->id, 'x_position' => 7, 'y_position' => 8, 'cell_type' => 'aisle']);
        $this->assertDatabaseHas('room_layout_cells', ['room_layout_id' => $v2->id, 'x_position' => 7, 'y_position' => 10, 'cell_type' => 'aisle']);
        $this->assertDatabaseHas('room_layout_cells', ['room_layout_id' => $v2->id, 'x_position' => 7, 'y_position' => 7, 'cell_type' => 'aisle']);
        $this->assertEquals($hIdsBeforePublish, $v2->cells()->where('y_position', 8)->where('cell_type', 'seat')->pluck('seat_id')->sort()->values());
        $this->assertEquals($originalHIds, $v2->cells->where('y_position', 8)->where('cell_type', 'seat')
            ->whereIn('seat.seat_code', collect(range(1, 12))->map(fn (int $number): string => "H{$number}"))
            ->pluck('seat_id')->sort()->values());
    }

    public function test_half_couple_is_rejected_at_checkout_and_store(): void
    {
        $half = Seat::query()->where('room_id', $this->rooms['P01']->id)->where('seat_code', 'K1')->firstOrFail();
        $this->get(route('user.bookings.checkout', ['showtime' => $this->showtime, 'selected_seats' => $half->id]))
            ->assertRedirect(route('user.bookings.selectSeat', $this->showtime))
            ->assertSessionHas('error');

        $this->post(route('user.bookings.store'), [
            'showtime_id' => $this->showtime->id, 'seat_ids' => [$half->id],
            'payment_method' => 'fake', 'customer_email' => 'guest@example.test',
            'checkout_token' => $this->checkoutToken,
        ])->assertGone();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_complete_couple_pair_reaches_checkout(): void
    {
        $pair = Seat::query()->where('room_id', $this->rooms['P01']->id)->where('pair_code', 'K-PAIR-1')->orderBy('number')->get();
        $this->assertCount(2, $pair);
        $this->get(route('user.bookings.checkout', [
            'showtime' => $this->showtime,
            'selected_seats' => $pair->pluck('id')->implode(','),
        ]))->assertOk()->assertSee('K1')->assertSee('K2');
    }

    public function test_seat_from_another_layout_is_rejected(): void
    {
        $foreign = Seat::query()->where('room_id', $this->rooms['P02']->id)->where('status', 'active')->firstOrFail();
        $this->get(route('user.bookings.checkout', ['showtime' => $this->showtime, 'selected_seats' => $foreign->id]))
            ->assertRedirect(route('user.bookings.selectSeat', $this->showtime))->assertSessionHas('error');

        $this->post(route('user.bookings.store'), [
            'showtime_id' => $this->showtime->id, 'seat_ids' => [$foreign->id],
            'payment_method' => 'fake', 'customer_email' => 'guest@example.test',
            'checkout_token' => $this->checkoutToken,
        ])->assertGone();
    }

    public function test_maintenance_seat_is_disabled_and_rejected_by_backend(): void
    {
        $room = $this->rooms['P02'];
        $maintenance = Seat::query()->where('room_id', $room->id)->where('status', 'maintenance')->firstOrFail();
        $showtime = Showtime::query()->create([
            'movie_id' => $this->showtime->movie_id, 'cinema_id' => $this->cinema->id, 'room_id' => $room->id,
            'presentation_format_id' => $this->presentationFormatFixture($this->showtime->movie_id, $room)->id,
            'room_layout_id' => $room->latestPublishedLayout()->first()->id,
            'show_date' => now()->addDays(10)->toDateString(), 'show_time' => '12:00:00',
            'price' => 50000, 'vip_price' => 70000, 'status' => 'active',
        ]);

        $this->get(route('user.bookings.selectSeat', $showtime))
            ->assertOk()->assertSee('aria-label="Ghế F6, loại VIP, đang bảo trì', false)->assertSee('disabled', false);
        $this->post(route('user.bookings.store'), [
            'showtime_id' => $showtime->id, 'seat_ids' => [$maintenance->id],
            'payment_method' => 'fake', 'customer_email' => 'guest@example.test',
            'checkout_token' => $this->checkoutToken,
        ])->assertGone();
    }

    public function test_booked_seat_is_disabled(): void
    {
        $seat = Seat::query()->where('room_id', $this->rooms['P01']->id)->where('seat_code', 'A1')->firstOrFail();
        $booking = Booking::query()->create([
            'user_id' => null, 'customer_email' => 'old@example.test', 'showtime_id' => $this->showtime->id,
            'booking_code' => 'MMT-2026-9999', 'total_amount' => 50000,
            'payment_status' => 'paid', 'booking_status' => 'paid',
        ]);
        BookingSeat::query()->create([
            'booking_id' => $booking->id,
            'showtime_id' => $this->showtime->id,
            'seat_id' => $seat->id,
            'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
            'price' => 50000,
        ]);

        $this->get(route('user.bookings.selectSeat', $this->showtime))
            ->assertOk()->assertSee('aria-label="Ghế A1', false)->assertSee('cursor-not-allowed', false);
    }

    public function test_partial_couple_lock_disables_the_single_merged_pair_control(): void
    {
        $pair = Seat::query()->where('room_id', $this->rooms['P01']->id)->where('pair_code', 'K-PAIR-1')->orderBy('number')->get();
        $booking = Booking::query()->create([
            'customer_email' => 'old@example.test', 'showtime_id' => $this->showtime->id,
            'booking_code' => 'MMT-2026-PAIR', 'total_amount' => 100000,
            'payment_status' => 'paid', 'booking_status' => 'paid',
        ]);
        BookingSeat::query()->create([
            'booking_id' => $booking->id, 'showtime_id' => $this->showtime->id,
            'seat_id' => $pair->first()->id, 'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
            'price' => 50000,
        ]);

        $response = $this->get(route('user.bookings.selectSeat', $this->showtime))->assertOk();
        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, 'data-seat-code="K1–K2"'));
        $this->assertMatchesRegularExpression('/data-seat-code="K1–K2"[\s\S]*?disabled/', $html);
    }

    public function test_inconsistent_legacy_pair_is_rendered_unavailable_with_a_warning(): void
    {
        Seat::query()->where('room_id', $this->rooms['P01']->id)->where('seat_code', 'K2')
            ->update(['pair_position' => 'left']);

        $this->get(route('user.bookings.selectSeat', $this->showtime))
            ->assertOk()
            ->assertSee('dữ liệu cặp ghế không hợp lệ, không khả dụng')
            ->assertSee('border-error/70', false);
    }

    public function test_legacy_forged_frontend_total_is_gone_and_creates_nothing(): void
    {
        Mail::fake();
        $seat = Seat::query()->where('room_id', $this->rooms['P01']->id)->where('seat_code', 'A1')->firstOrFail();

        $this->post(route('user.bookings.store'), [
            'showtime_id' => $this->showtime->id, 'seat_ids' => [$seat->id],
            'payment_method' => 'fake', 'customer_email' => 'guest@example.test',
            'checkout_token' => $this->checkoutToken,
            'total_amount' => 1, 'price' => 1, 'room_id' => $this->rooms['P02']->id, 'room_layout_id' => 999,
        ])->assertGone();

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_seats', 0);
        $this->assertDatabaseCount('payments', 0);
        Mail::assertNothingSent();
    }

    public function test_showtime_without_published_layout_cannot_open_seat_map(): void
    {
        $this->showtime->update(['room_layout_id' => null]);
        $this->get(route('user.bookings.selectSeat', $this->showtime))
            ->assertRedirect(route('user.movies.show', 'booking-movie'));
    }
}
