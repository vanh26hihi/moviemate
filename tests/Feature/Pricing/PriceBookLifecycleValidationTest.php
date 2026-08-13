<?php

namespace Tests\Feature\Pricing;

use App\Exceptions\PriceBookException;
use App\Models\PriceBook;
use App\Models\PriceBookVersion;
use App\Services\PriceBookVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesPriceBookFixtures;
use Tests\TestCase;

class PriceBookLifecycleValidationTest extends TestCase
{
    use CreatesPriceBookFixtures;
    use RefreshDatabase;

    public function test_version_numbers_are_monotonic_and_singleton_is_enforced_under_parent_lock(): void
    {
        $service = app(PriceBookVersionService::class);
        $book = $this->chainPriceBook();
        $this->assertSame(1, $service->createDraft($book)->version_number);
        $this->assertSame(2, $service->createDraft($book)->version_number);

        PriceBook::query()->create(['code' => 'COMPETING', 'name' => 'Competing']);
        $this->expectException(PriceBookException::class);
        $service->createDraft($book);
    }

    public function test_published_periods_reject_overlap_allow_adjacency_and_allow_gaps(): void
    {
        $service = app(PriceBookVersionService::class);
        $v1 = $service->publish($this->priceBookDraft([
            'effective_from' => '2026-01-01', 'effective_until' => '2026-09-01',
        ]));
        $adjacent = $service->publish($this->priceBookDraft([
            'effective_from' => '2026-09-01', 'effective_until' => '2026-10-01',
        ]));
        $gap = $service->publish($this->priceBookDraft([
            'effective_from' => '2026-11-01', 'effective_until' => null,
        ]));

        $this->assertSame('published', $v1->status);
        $this->assertSame('published', $adjacent->status);
        $this->assertSame('published', $gap->status);

        $overlap = $this->priceBookDraft([
            'effective_from' => '2026-08-01', 'effective_until' => null,
        ]);
        try {
            $service->publish($overlap);
            $this->fail('Overlapping version must not publish.');
        } catch (PriceBookException $exception) {
            $this->assertSame(PriceBookException::VERSION_OVERLAP, $exception->domainCode);
        }
        $this->assertSame('draft', $overlap->refresh()->status);
    }

    public function test_open_ended_version_blocks_future_overlap_without_mutating_its_period(): void
    {
        $service = app(PriceBookVersionService::class);
        $open = $service->publish($this->priceBookDraft([
            'effective_from' => '2026-01-01', 'effective_until' => null,
        ]));
        $future = $this->priceBookDraft([
            'effective_from' => '2027-01-01', 'effective_until' => null,
        ]);

        $this->expectException(PriceBookException::class);
        try {
            $service->publish($future);
        } finally {
            $this->assertNull($open->refresh()->effective_until);
        }
    }

    public function test_invalid_shapes_and_zero_adjustments_are_rejected_before_write(): void
    {
        $service = app(PriceBookVersionService::class);
        $invalid = [
            ['dimension' => 'movie', 'label' => 'No', 'amount_vnd' => 1],
            ['dimension' => 'seat_type', 'label' => 'No ref', 'amount_vnd' => 1],
            ['dimension' => 'cinema', 'label' => 'Contradiction', 'cinema_id' => 1, 'room_id' => 2, 'amount_vnd' => 1],
            ['dimension' => 'weekend', 'label' => 'Zero', 'weekend_days' => [6, 7], 'amount_vnd' => 0],
            ['dimension' => 'time_window', 'label' => 'Equal', 'time_start' => '18:00', 'time_end' => '18:00', 'amount_vnd' => 1],
        ];

        foreach ($invalid as $definition) {
            $draft = $this->priceBookDraft();
            try {
                $service->replaceAdjustments($draft, [$definition]);
                $this->fail('Invalid adjustment shape must be rejected.');
            } catch (PriceBookException) {
                $this->addToAssertionCount(1);
            }
            $this->assertDatabaseCount('price_book_adjustments', 0);
        }
    }

    public function test_duplicate_entity_scopes_are_rejected_without_priority_winner(): void
    {
        $service = app(PriceBookVersionService::class);
        [$cinema, $roomType, $room] = $this->pricingContext();
        $vip = $this->seatType('vip');
        $cases = [
            [
                ['dimension' => 'seat_type', 'label' => 'A', 'seat_type_id' => $vip->id, 'amount_vnd' => 1],
                ['dimension' => 'seat_type', 'label' => 'B', 'seat_type_id' => $vip->id, 'amount_vnd' => 2],
            ],
            [
                ['dimension' => 'room_type', 'label' => 'A', 'room_type_id' => $roomType->id, 'amount_vnd' => 1],
                ['dimension' => 'room_type', 'label' => 'B', 'room_type_id' => $roomType->id, 'amount_vnd' => 2],
            ],
            [
                ['dimension' => 'cinema', 'label' => 'A', 'cinema_id' => $cinema->id, 'amount_vnd' => 1],
                ['dimension' => 'cinema', 'label' => 'B', 'cinema_id' => $cinema->id, 'amount_vnd' => 2],
            ],
            [
                ['dimension' => 'room', 'label' => 'A', 'room_id' => $room->id, 'amount_vnd' => 1],
                ['dimension' => 'room', 'label' => 'B', 'room_id' => $room->id, 'amount_vnd' => 2],
            ],
        ];

        foreach ($cases as $definitions) {
            try {
                $service->replaceAdjustments($this->priceBookDraft(), $definitions);
                $this->fail('Duplicate scope must be rejected.');
            } catch (PriceBookException $exception) {
                $this->assertSame(PriceBookException::AMBIGUOUS_ADJUSTMENT, $exception->domainCode);
            }
        }
    }

    public function test_time_windows_handle_adjacency_overlap_and_cross_midnight(): void
    {
        $service = app(PriceBookVersionService::class);
        $allowed = $this->priceBookDraft();
        $service->replaceAdjustments($allowed, [
            ['dimension' => 'time_window', 'label' => 'Evening', 'time_start' => '18:00', 'time_end' => '22:00', 'amount_vnd' => 1],
            ['dimension' => 'time_window', 'label' => 'Night', 'time_start' => '22:00', 'time_end' => '02:00', 'amount_vnd' => 2],
        ]);
        $this->assertDatabaseCount('price_book_adjustments', 2);

        foreach ([
            [['18:00', '22:00'], ['20:00', '23:00']],
            [['22:00', '02:00'], ['01:00', '03:00']],
        ] as $windows) {
            $definitions = collect($windows)->map(fn (array $window, int $index): array => [
                'dimension' => 'time_window', 'label' => 'Window '.$index,
                'time_start' => $window[0], 'time_end' => $window[1], 'amount_vnd' => $index + 1,
            ])->all();
            try {
                $service->replaceAdjustments($this->priceBookDraft(), $definitions);
                $this->fail('Overlapping clock intervals must be rejected.');
            } catch (PriceBookException $exception) {
                $this->assertSame(PriceBookException::AMBIGUOUS_ADJUSTMENT, $exception->domainCode);
            }
        }
    }

    public function test_holiday_ranges_handle_adjacency_and_reject_overlap(): void
    {
        $service = app(PriceBookVersionService::class);
        $service->replaceAdjustments($this->priceBookDraft(), [
            ['dimension' => 'holiday', 'label' => 'A', 'holiday_date_from' => '2026-09-01', 'holiday_date_until' => '2026-09-02', 'amount_vnd' => 1],
            ['dimension' => 'holiday', 'label' => 'B', 'holiday_date_from' => '2026-09-02', 'holiday_date_until' => '2026-09-03', 'amount_vnd' => 2],
        ]);
        $this->assertDatabaseCount('price_book_adjustments', 2);

        $this->expectException(PriceBookException::class);
        $service->replaceAdjustments($this->priceBookDraft(), [
            ['dimension' => 'holiday', 'label' => 'A', 'holiday_date_from' => '2026-09-01', 'holiday_date_until' => '2026-09-03', 'amount_vnd' => 1],
            ['dimension' => 'holiday', 'label' => 'B', 'holiday_date_from' => '2026-09-02', 'holiday_date_until' => '2026-09-04', 'amount_vnd' => 2],
        ]);
    }

    public function test_copy_creates_independent_draft_without_effective_dates(): void
    {
        $service = app(PriceBookVersionService::class);
        $source = $this->priceBookDraft();
        $service->replaceAdjustments($source, [[
            'dimension' => 'seat_type', 'label' => 'VIP',
            'seat_type_id' => $this->seatType('vip')->id, 'amount_vnd' => 30_000,
        ]]);
        $source = $service->publish($source);
        $copy = $service->copyToDraft($source);
        $service->updateDraft($copy, ['base_price_vnd' => 90_000]);
        $copy->adjustments->first()->update(['amount_vnd' => 20_000]);

        $this->assertSame('draft', $copy->status);
        $this->assertNull($copy->effective_from);
        $this->assertNull($copy->effective_until);
        $this->assertSame(80_000, $source->refresh()->base_price_vnd);
        $this->assertSame(30_000, $source->adjustments()->first()->amount_vnd);
        $this->assertSame(20_000, $copy->adjustments()->first()->amount_vnd);
        $this->assertNotSame($source->adjustments()->first()->id, $copy->adjustments()->first()->id);
    }

    public function test_publish_rejects_any_context_that_can_be_zero_or_exceed_supported_money(): void
    {
        $service = app(PriceBookVersionService::class);
        foreach ([-80_000, 99_999_999] as $amount) {
            $draft = $this->priceBookDraft();
            $service->replaceAdjustments($draft, [[
                'dimension' => 'room', 'label' => 'Boundary',
                'room_id' => $this->pricingContext((string) abs($amount))[2]->id,
                'amount_vnd' => $amount,
            ]]);
            try {
                $service->publish($draft);
                $this->fail('Unsupported final amount must not publish.');
            } catch (PriceBookException) {
                $this->addToAssertionCount(1);
            }
            $this->assertSame(PriceBookVersion::STATUS_DRAFT, $draft->refresh()->status);
        }
    }
}
