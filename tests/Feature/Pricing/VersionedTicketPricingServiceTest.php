<?php

namespace Tests\Feature\Pricing;

use App\Exceptions\PriceBookException;
use App\Models\PresentationFormat;
use App\Models\PriceBookVersion;
use App\Services\PriceBookVersionService;
use App\Services\VersionedTicketPricingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesPriceBookFixtures;
use Tests\TestCase;

class VersionedTicketPricingServiceTest extends TestCase
{
    use CreatesPriceBookFixtures;
    use RefreshDatabase;

    public function test_seed_equivalent_weekday_weekend_evening_couple_and_holiday_prices(): void
    {
        [$cinema, $roomType, $room] = $this->pricingContext();
        $normal = $this->seatType();
        $vip = $this->seatType('vip');
        $couple = $this->seatType('couple', true);
        $this->publishStandard();
        $resolver = app(VersionedTicketPricingService::class);

        $weekday = CarbonImmutable::parse('2026-08-17 14:00', 'Asia/Ho_Chi_Minh');
        $weekendEvening = CarbonImmutable::parse('2026-08-22 19:00', 'Asia/Ho_Chi_Minh');
        $holidayEvening = CarbonImmutable::parse('2026-09-01 19:00', 'Asia/Ho_Chi_Minh');

        $this->assertSame(80_000, $resolver->resolve($cinema, $room, $roomType, $normal, $weekday)->finalUnitAmountVnd);
        $this->assertSame(110_000, $resolver->resolve($cinema, $room, $roomType, $vip, $weekday)->finalUnitAmountVnd);
        $this->assertSame(160_000, $resolver->resolve($cinema, $room, $roomType, $couple, $weekday)->finalUnitAmountVnd);
        $this->assertSame(105_000, $resolver->resolve($cinema, $room, $roomType, $normal, $weekendEvening)->finalUnitAmountVnd);
        $this->assertSame(135_000, $resolver->resolve($cinema, $room, $roomType, $vip, $weekendEvening)->finalUnitAmountVnd);
        $this->assertSame(185_000, $resolver->resolve($cinema, $room, $roomType, $couple, $weekendEvening)->finalUnitAmountVnd);

        $holiday = $resolver->resolve($cinema, $room, $roomType, $normal, $holidayEvening);
        $this->assertSame(115_000, $holiday->finalUnitAmountVnd);
        $this->assertSame(['time_window', 'holiday'], array_column($holiday->adjustments, 'dimension'));
        $this->assertNotContains('weekend', array_column($holiday->adjustments, 'dimension'));
    }

    public function test_room_type_cinema_and_room_adjustments_stack_while_negative_is_supported(): void
    {
        [$cinema, $roomType, $room] = $this->pricingContext();
        $normal = $this->seatType();
        $service = app(PriceBookVersionService::class);
        $draft = $this->priceBookDraft();
        $service->replaceAdjustments($draft, [
            ['dimension' => 'room_type', 'label' => 'Type', 'room_type_id' => $roomType->id, 'amount_vnd' => 20_000],
            ['dimension' => 'cinema', 'label' => 'Cinema', 'cinema_id' => $cinema->id, 'amount_vnd' => 5_000],
            ['dimension' => 'room', 'label' => 'Room', 'room_id' => $room->id, 'amount_vnd' => -10_000],
        ]);
        $service->publish($draft);

        $result = app(VersionedTicketPricingService::class)->resolve(
            $cinema, $room, $roomType, $normal,
            CarbonImmutable::parse('2026-08-17 14:00', 'Asia/Ho_Chi_Minh'),
        );
        $this->assertSame(95_000, $result->finalUnitAmountVnd);
        $this->assertSame(['room_type', 'cinema', 'room'], array_column($result->adjustments, 'dimension'));
    }

    public function test_cross_midnight_time_window_uses_local_start_clock_and_half_open_boundaries(): void
    {
        [$cinema, $roomType, $room] = $this->pricingContext();
        $normal = $this->seatType();
        $service = app(PriceBookVersionService::class);
        $draft = $this->priceBookDraft();
        $service->replaceAdjustments($draft, [[
            'dimension' => 'time_window', 'label' => 'Late',
            'time_start' => '22:00', 'time_end' => '02:00', 'amount_vnd' => 10_000,
        ]]);
        $service->publish($draft);
        $resolver = app(VersionedTicketPricingService::class);

        foreach (['2026-08-17 22:00' => 90_000, '2026-08-18 01:59' => 90_000, '2026-08-18 02:00' => 80_000] as $time => $amount) {
            $result = $resolver->resolve($cinema, $room, $roomType, $normal, CarbonImmutable::parse($time, 'Asia/Ho_Chi_Minh'));
            $this->assertSame($amount, $result->finalUnitAmountVnd);
        }
    }

    public function test_holiday_on_weekend_replaces_weekend_instead_of_stacking(): void
    {
        [$cinema, $roomType, $room] = $this->pricingContext();
        $normal = $this->seatType();
        $service = app(PriceBookVersionService::class);
        $draft = $this->priceBookDraft();
        $service->replaceAdjustments($draft, [
            ['dimension' => 'weekend', 'label' => 'Weekend', 'weekend_days' => [6, 7], 'amount_vnd' => 10_000],
            ['dimension' => 'holiday', 'label' => 'Saturday holiday', 'holiday_date_from' => '2026-08-22', 'holiday_date_until' => '2026-08-23', 'amount_vnd' => 20_000],
        ]);
        $service->publish($draft);

        $result = app(VersionedTicketPricingService::class)->resolve(
            $cinema, $room, $roomType, $normal,
            CarbonImmutable::parse('2026-08-22 14:00', 'Asia/Ho_Chi_Minh'),
        );
        $this->assertSame(100_000, $result->finalUnitAmountVnd);
        $this->assertSame(['holiday'], array_column($result->adjustments, 'dimension'));
    }

    public function test_gap_and_retired_versions_fail_without_fallback(): void
    {
        [$cinema, $roomType, $room] = $this->pricingContext();
        $normal = $this->seatType();
        $service = app(PriceBookVersionService::class);
        $service->publish($this->priceBookDraft([
            'effective_from' => '2026-01-01', 'effective_until' => '2026-09-01',
        ]));
        $future = $service->publish($this->priceBookDraft([
            'effective_from' => '2026-10-01', 'effective_until' => '2027-01-01',
        ]));
        $service->retire($future);

        try {
            app(VersionedTicketPricingService::class)->resolve(
                $cinema, $room, $roomType, $normal,
                CarbonImmutable::parse('2026-09-15 14:00', 'Asia/Ho_Chi_Minh'),
            );
            $this->fail('Resolver must not fallback through a period gap or retired version.');
        } catch (PriceBookException $exception) {
            $this->assertSame(PriceBookException::VERSION_NOT_FOUND, $exception->domainCode);
        }
    }

    public function test_corrupt_multiple_matching_versions_and_adjustments_fail_defensively(): void
    {
        [$cinema, $roomType, $room] = $this->pricingContext();
        $normal = $this->seatType();
        $first = $this->priceBookDraft();
        app(PriceBookVersionService::class)->publish($first);

        DB::statement('DROP TRIGGER price_book_versions_prevent_immutable_update');
        PriceBookVersion::query()->create([
            'price_book_id' => $first->price_book_id,
            'version_number' => 2,
            'status' => PriceBookVersion::STATUS_PUBLISHED,
            'base_price_vnd' => 80_000,
            'effective_from' => '2026-01-01',
            'effective_until' => '2027-01-01',
        ]);

        $this->expectException(PriceBookException::class);
        app(VersionedTicketPricingService::class)->resolve(
            $cinema, $room, $roomType, $normal,
            CarbonImmutable::parse('2026-08-17 14:00', 'Asia/Ho_Chi_Minh'),
        );
    }

    public function test_presentation_format_is_not_an_input_and_does_not_change_resolution(): void
    {
        [$cinema, $roomType, $room] = $this->pricingContext();
        $normal = $this->seatType();
        $this->publishStandard();
        $format2d = PresentationFormat::query()->create(['code' => 'PB_2D', 'name' => '2D', 'is_active' => true]);
        $format3d = PresentationFormat::query()->create(['code' => 'PB_3D', 'name' => '3D', 'is_active' => true]);
        $room->presentationCapabilities()->sync([$format2d->id, $format3d->id]);
        $start = CarbonImmutable::parse('2026-08-17 14:00', 'Asia/Ho_Chi_Minh');
        $resolver = app(VersionedTicketPricingService::class);

        $first = $resolver->resolve($cinema, $room, $roomType, $normal, $start);
        $room->presentationCapabilities()->sync([$format3d->id]);
        $second = $resolver->resolve($cinema, $room, $roomType, $normal, $start);
        $this->assertSame($first->finalUnitAmountVnd, $second->finalUnitAmountVnd);
        $this->assertSame($first->fingerprint, $second->fingerprint);
    }

    public function test_resolver_returns_snapshot_ready_breakdown_and_stable_identity(): void
    {
        [$cinema, $roomType, $room] = $this->pricingContext();
        $vip = $this->seatType('vip');
        $version = $this->publishStandard();
        $result = app(VersionedTicketPricingService::class)->resolve(
            $cinema, $room, $roomType, $vip,
            CarbonImmutable::parse('2026-08-22 19:00', 'Asia/Ho_Chi_Minh'),
        );
        $breakdown = $result->breakdown();

        $this->assertSame($version->id, $breakdown['price_book_version_id']);
        $this->assertSame(1, $breakdown['version_number']);
        $this->assertSame(80_000, $breakdown['base']['amount_vnd']);
        $this->assertSame(135_000, $breakdown['final_unit_amount_vnd']);
        $this->assertSame(64, strlen($breakdown['fingerprint']));
        $this->assertContainsOnly('array', $breakdown['adjustments']);
    }

    public function test_one_and_many_contexts_use_three_bounded_queries_without_dimension_n_plus_one(): void
    {
        [$cinema, $roomType, $room] = $this->pricingContext();
        $normal = $this->seatType();
        $vip = $this->seatType('vip');
        $couple = $this->seatType('couple', true);
        $this->publishStandard();
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_contains($query->sql, 'price_book')) {
                $queries[] = $query->sql;
            }
        });
        $resolver = app(VersionedTicketPricingService::class);
        $start = CarbonImmutable::parse('2026-08-22 19:00', 'Asia/Ho_Chi_Minh');

        $resolver->resolve($cinema, $room, $roomType, $normal, $start);
        $one = count($queries);
        foreach ([$vip, $couple, $normal, $vip, $couple] as $seatType) {
            $resolver->resolve($cinema, $room, $roomType, $seatType, $start);
        }

        $this->assertSame(3, $one);
        $this->assertSame(3, count($queries));
    }

    private function publishStandard(): PriceBookVersion
    {
        $service = app(PriceBookVersionService::class);
        $draft = $this->priceBookDraft();
        $service->replaceAdjustments($draft, $this->standardAdjustments());

        return $service->publish($draft);
    }
}
