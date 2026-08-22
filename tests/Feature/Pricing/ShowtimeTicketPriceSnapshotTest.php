<?php

namespace Tests\Feature\Pricing;

use App\Models\BookingSeat;
use App\Models\PriceBookVersion;
use App\Models\Seat;
use App\Services\BookingCheckoutService;
use App\Services\BookingPricingService;
use App\Services\BookingTokenService;
use App\Services\PriceBookVersionService;
use App\Services\ShowtimeScheduleService;
use App\Services\ShowtimeTicketPriceService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

final class ShowtimeTicketPriceSnapshotTest extends TestCase
{
    use CreatesBookingFixtures, RefreshDatabase;

    public function test_schema_has_authoritative_keys_and_removes_all_legacy_authorities(): void
    {
        $this->assertTrue(Schema::hasTable('showtime_ticket_prices'));
        foreach ([
            'showtime_id', 'seat_type_id', 'price_book_version_id', 'base_price_vnd',
            'adjustment_total_vnd', 'final_unit_amount_vnd', 'breakdown_json', 'pricing_fingerprint',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('showtime_ticket_prices', $column));
        }
        $this->assertTrue(Schema::hasColumn('booking_seats', 'showtime_ticket_price_id'));
        $this->assertFalse(Schema::hasColumn('showtimes', 'price'));
        $this->assertFalse(Schema::hasColumn('showtimes', 'vip_price'));
        $this->assertFalse(Schema::hasColumn('showtimes', 'pricing_version'));
        $this->assertFalse(Schema::hasColumn('seat_types', 'price_modifier'));
        $this->assertFalse(Schema::hasTable('cinema_pricing_rules'));
        $this->assertFalse(Route::has('admin.pricing-rules.index'));
        $this->assertFalse(Route::has('admin.pricing-rules.preview'));
    }

    public function test_snapshot_grain_is_exact_structural_logical_seat_types_including_maintenance(): void
    {
        $scenario = $this->bookingScenario(
            extraLayoutCells: [['x_position' => 6, 'y_position' => 2, 'cell_type' => 'blocked']],
            layoutColumns: 6,
            extraSeats: [[
                'row' => 'C', 'number' => 1, 'seat_code' => 'C1',
                'type' => 'vip', 'status' => Seat::STATUS_MAINTENANCE,
            ]],
        );
        $snapshots = $scenario['showtime']->ticketPrices()->with('seatType')->get();

        $this->assertSame(['couple', 'normal', 'vip'], $snapshots->pluck('seatType.code')->sort()->values()->all());
        $this->assertSame(3, $snapshots->count());
        $this->assertSame(1, $snapshots->pluck('price_book_version_id')->unique()->count());
        $this->assertSame(3, DB::table('room_layout_cells')->where('room_layout_id', $scenario['layout']->id)
            ->where('cell_type', 'seat')->join('seats', 'seats.id', '=', 'room_layout_cells.seat_id')
            ->distinct()->count('seats.seat_type_id'));
    }

    public function test_snapshot_rows_are_immutable_in_model_and_database(): void
    {
        $snapshot = $this->bookingScenario(false)['showtime']->ticketPrices()->sole();

        try {
            $snapshot->update(['final_unit_amount_vnd' => 1]);
            $this->fail('Eloquent must reject an immutable snapshot update.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);
        DB::table('showtime_ticket_prices')->where('id', $snapshot->id)->update(['final_unit_amount_vnd' => 1]);
    }

    public function test_existing_showtime_price_survives_version_retirement_and_new_publication(): void
    {
        $scenario = $this->bookingScenario(false);
        $before = $scenario['showtime']->ticketPrices()->sole();
        $versions = app(PriceBookVersionService::class);
        $versions->retire($before->priceBookVersion()->sole());
        $draft = $versions->createDraft($this->chainPriceBook(), [
            'base_price_vnd' => 99_000,
            'effective_from' => now()->subDay()->toDateString(),
            'effective_until' => now()->addYear()->toDateString(),
        ]);
        $versions->publish($draft);

        $after = $before->fresh();
        $this->assertSame(50_000, (int) $after->final_unit_amount_vnd);
        $this->assertSame(PriceBookVersion::STATUS_RETIRED, $after->priceBookVersion()->value('status'));
        $this->assertNotSame($draft->id, $after->price_book_version_id);
    }

    public function test_review_quote_and_confirm_do_not_drift_after_new_price_book_publication(): void
    {
        $scenario = $this->bookingScenario(false);
        $seat = $scenario['seats']->firstWhere('status', Seat::STATUS_ACTIVE);
        $quote = app(BookingPricingService::class)->calculate(
            $scenario['showtime']->fresh(['ticketPrices.seatType']),
            collect([$seat]),
        );
        $sourceId = $scenario['showtime']->ticketPrices()->sole()->id;
        $versions = app(PriceBookVersionService::class);
        $versions->retire(PriceBookVersion::query()->where('status', PriceBookVersion::STATUS_PUBLISHED)->sole());
        $next = $versions->createDraft($this->chainPriceBook(), [
            'base_price_vnd' => 90_000,
            'effective_from' => now()->subYear()->toDateString(),
            'effective_until' => now()->addYear()->toDateString(),
        ]);
        $versions->publish($next);

        $booking = app(BookingCheckoutService::class)->createPendingBooking(
            $scenario['showtime']->id,
            [$seat->id],
            null,
            'review-confirm@example.test',
            app(BookingTokenService::class)->issueCheckoutToken(),
        )->booking->fresh('bookingSeats');

        $this->assertSame(50_000, $quote->seatSubtotal);
        $this->assertSame(50_000, (int) $booking->seat_subtotal);
        $this->assertSame($sourceId, $booking->bookingSeats->sole()->showtime_ticket_price_id);
        $this->assertNotSame($next->id, $scenario['showtime']->ticketPrices()->sole()->price_book_version_id);
    }

    public function test_couple_physical_seats_reference_one_logical_snapshot_and_charge_once(): void
    {
        $scenario = $this->bookingScenario(true);
        $pair = $scenario['seats']->where('type', 'couple');
        $booking = app(BookingCheckoutService::class)->createPendingBooking(
            $scenario['showtime']->id,
            $pair->pluck('id')->all(),
            null,
            'couple@example.test',
            app(BookingTokenService::class)->issueCheckoutToken(),
        )->booking;
        $bookingSeats = BookingSeat::query()->where('booking_id', $booking->id)->orderBy('seat_id')->get();

        $this->assertCount(2, $bookingSeats);
        $this->assertSame(1, $bookingSeats->pluck('showtime_ticket_price_id')->unique()->count());
        $this->assertSame(100_000, (int) $booking->seat_subtotal);
        $this->assertSame([50_000, 50_000], $bookingSeats->pluck('price')->map(fn ($value): int => (int) $value)->all());
        $this->assertSame([100_000], $bookingSeats->pluck('final_unit_amount')->map(fn ($value): int => (int) $value)->unique()->all());
    }

    public function test_booking_history_prevents_snapshot_delete_at_both_layers(): void
    {
        $scenario = $this->bookingScenario(false);
        $seat = $scenario['seats']->firstWhere('status', Seat::STATUS_ACTIVE);
        app(BookingCheckoutService::class)->createPendingBooking(
            $scenario['showtime']->id,
            [$seat->id],
            null,
            'history@example.test',
            app(BookingTokenService::class)->issueCheckoutToken(),
        );
        $snapshot = $scenario['showtime']->ticketPrices()->sole();

        try {
            $snapshot->delete();
            $this->fail('Eloquent must protect a snapshot referenced by booking history.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);
        DB::table('showtime_ticket_prices')->where('id', $snapshot->id)->delete();
    }

    public function test_booking_seat_source_must_match_showtime_and_logical_seat_type(): void
    {
        $scenario = $this->bookingScenario(true);
        $normal = $scenario['seats']->firstWhere('type', 'normal');
        $wrong = $scenario['showtime']->ticketPrices()->whereHas(
            'seatType', fn ($query) => $query->where('code', 'couple'),
        )->sole();
        $booking = $this->bookingForScenario($scenario);

        $this->expectException(LogicException::class);
        BookingSeat::query()->create([
            'booking_id' => $booking->id,
            'showtime_id' => $scenario['showtime']->id,
            'seat_id' => $normal->id,
            'showtime_ticket_price_id' => $wrong->id,
            'price' => 1,
        ]);
    }

    public function test_seat_map_snapshot_queries_do_not_scale_with_physical_seat_count(): void
    {
        $small = $this->bookingScenario(false);
        $extraSeats = collect(range(3, 100))->map(fn (int $number): array => [
            'row' => 'A',
            'number' => $number,
            'seat_code' => 'A'.$number,
            'type' => $number % 5 === 0 ? 'vip' : 'normal',
        ])->all();
        $large = $this->bookingScenario(false, layoutRows: 1, layoutColumns: 100, extraSeats: $extraSeats);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('user.bookings.selectSeat', $small['showtime']))->assertOk();
        $smallQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->get(route('user.bookings.selectSeat', $large['showtime']))->assertOk();
        $largeQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(2, abs($largeQueries - $smallQueries));
        $this->assertLessThanOrEqual(30, $largeQueries);
        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, "PHASE7C_SEAT_MAP_QUERIES small={$smallQueries} large={$largeQueries}".PHP_EOL);
        }
    }

    public function test_snapshot_resolution_queries_scale_by_logical_type_not_physical_seat_count(): void
    {
        $small = $this->bookingScenario(false);
        $extraSeats = collect(range(3, 100))->map(fn (int $number): array => [
            'row' => 'A',
            'number' => $number,
            'seat_code' => 'A'.$number,
            'type' => 'normal',
        ])->all();
        $large = $this->bookingScenario(false, layoutRows: 1, layoutColumns: 100, extraSeats: $extraSeats);
        $pricing = app(ShowtimeTicketPriceService::class);
        $schedule = app(ShowtimeScheduleService::class);
        $counts = [];
        $warmShowtime = $small['showtime']->fresh();
        $pricing->preview(
            $warmShowtime->room()->firstOrFail(),
            $warmShowtime->roomLayout()->firstOrFail(),
            $schedule->windowFor($warmShowtime),
        );

        foreach (['small' => $small, 'large' => $large] as $key => $scenario) {
            $showtime = $scenario['showtime']->fresh();
            $room = $showtime->room()->firstOrFail();
            $layout = $showtime->roomLayout()->firstOrFail();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $snapshots = $pricing->preview($room, $layout, $schedule->windowFor($showtime));
            $counts[$key] = count(DB::getQueryLog());
            DB::disableQueryLog();
            $this->assertCount(1, $snapshots);
        }

        $this->assertSame($counts['small'], $counts['large']);
        $this->assertLessThanOrEqual(15, $counts['large']);
        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, "PHASE7C_SNAPSHOT_QUERIES small={$counts['small']} large={$counts['large']}".PHP_EOL);
        }
    }
}
