<?php

namespace Tests\Feature\Pricing;

use App\Exceptions\PriceBookException;
use App\Models\PriceBookVersion;
use App\Models\SeatType;
use App\Models\Showtime;
use App\Models\UserCinemaAssignment;
use App\Services\CinemaAccessService;
use App\Services\PriceBookScheduleChangeService;
use App\Services\PriceBookVersionService;
use App\Services\VersionedTicketPricingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesPriceBookFixtures;
use Tests\TestCase;

final class PriceBookScheduleChangeTest extends TestCase
{
    use CreatesPriceBookFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-08-16 12:00:00');
        $this->withoutVite();
        $this->seedRbac();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_published_workspace_offers_plain_language_safe_change_workflow(): void
    {
        $published = $this->publishedVersion();

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.price-books.versions.show', $published))
            ->assertOk()
            ->assertSee('Bạn muốn giá thay đổi khi nào?')
            ->assertSee('Đổi giá từ một ngày trở đi')
            ->assertSee('Đặt giá đặc biệt cho một ngày')
            ->assertSee('Xem lịch giá trước khi áp dụng')
            ->assertSee('Bước này chỉ xem trước, chưa thay đổi dữ liệu.')
            ->assertSee('step="1"', false)
            ->assertDontSee('step="1000"', false);
    }

    public function test_preview_is_authoritative_read_only_and_refresh_safe(): void
    {
        $published = $this->publishedVersion();

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.price-books.versions.schedule-change.preview', $published), [
                'change_kind' => PriceBookScheduleChangeService::KIND_FROM_DATE,
                'change_date' => '2027-01-01',
                'ticket_prices' => $this->newPrices(),
            ])
            ->assertOk()
            ->assertSee('Không chồng lấn · Không ngày trống')
            ->assertSeeInOrder(['01/01/2026', '31/12/2026'])
            ->assertSeeInOrder(['01/01/2027', '31/12/2029'])
            ->assertSee('90.000 ₫')
            ->assertSee('125.000 ₫')
            ->assertSee('190.000 ₫');

        $this->assertSame(PriceBookVersion::STATUS_PUBLISHED, $published->fresh()->status);
        $this->assertDatabaseCount('price_book_versions', 1);

        $this->get(route('admin.price-books.versions.schedule-change.preview.redirect', $published))
            ->assertRedirect(route('admin.price-books.versions.show', $published));
    }

    public function test_change_from_date_atomically_replaces_source_with_adjacent_published_periods(): void
    {
        $published = $this->publishedVersion();
        $existingDraft = app(PriceBookVersionService::class)->copyToDraft(
            $published,
            '2026-08-16',
            '2026-08-30',
        );
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.price-books.versions.schedule-change.apply', $published), [
                'change_kind' => PriceBookScheduleChangeService::KIND_FROM_DATE,
                'change_date' => '2027-01-01',
                'ticket_prices' => $this->newPrices(),
            ])
            ->assertRedirect(route('admin.price-books.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(PriceBookVersion::STATUS_RETIRED, $published->fresh()->status);
        $replacements = PriceBookVersion::query()->where('status', PriceBookVersion::STATUS_PUBLISHED)
            ->with('adjustments')->orderBy('effective_from')->get();
        $this->assertCount(2, $replacements);
        $this->assertSame('2026-01-01', $replacements[0]->effective_from->toDateString());
        $this->assertSame('2027-01-01', $replacements[0]->effective_until->toDateString());
        $this->assertSame('2027-01-01', $replacements[1]->effective_from->toDateString());
        $this->assertSame('2030-01-01', $replacements[1]->effective_until->toDateString());
        $this->assertSame($published->adjustments()->count(), $replacements[0]->adjustments->count());
        $this->assertSame(3, $replacements[1]->adjustments->where('dimension', '!=', 'seat_type')->count());
        $this->assertSame($this->newPrices(), $this->seatPrices($replacements[1]));
        $this->assertSame($admin->id, $replacements[1]->created_by_user_id);
        $this->assertSame(PriceBookVersion::STATUS_DRAFT, $existingDraft->fresh()->status);
    }

    public function test_single_day_change_creates_exact_prices_then_restores_the_previous_rules(): void
    {
        $published = $this->publishedVersion();
        [$cinema, $roomType, $room] = $this->pricingContext('ONE_DAY');

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.price-books.versions.schedule-change.apply', $published), [
                'change_kind' => PriceBookScheduleChangeService::KIND_SINGLE_DAY,
                'change_date' => '2026-08-20',
                'ticket_prices' => $this->newPrices(),
            ])
            ->assertRedirect(route('admin.price-books.index'))
            ->assertSessionHasNoErrors();

        $replacements = PriceBookVersion::query()->where('status', PriceBookVersion::STATUS_PUBLISHED)
            ->with('adjustments')->orderBy('effective_from')->get();
        $this->assertCount(3, $replacements);
        $this->assertSame('2026-08-20', $replacements[0]->effective_until->toDateString());
        $this->assertSame('2026-08-20', $replacements[1]->effective_from->toDateString());
        $this->assertSame('2026-08-21', $replacements[1]->effective_until->toDateString());
        $this->assertSame('2026-08-21', $replacements[2]->effective_from->toDateString());
        $this->assertSame(3, $replacements[1]->adjustments->where('dimension', '!=', 'seat_type')->count());
        $this->assertSame($this->newPrices(), $this->seatPrices($replacements[1]));

        $pricing = app(VersionedTicketPricingService::class);
        $normal = $this->seatType('normal');
        $vip = $this->seatType('vip');
        $couple = $this->seatType('couple', true);
        $this->assertSame(95_000, $pricing->resolve(
            $cinema, $room, $roomType, $normal, CarbonImmutable::parse('2026-08-19 20:00', $cinema->timezone),
        )->finalUnitAmountVnd);
        $this->assertSame(105_000, $pricing->resolve(
            $cinema, $room, $roomType, $normal, CarbonImmutable::parse('2026-08-20 20:00', $cinema->timezone),
        )->finalUnitAmountVnd);
        $this->assertSame(140_000, $pricing->resolve(
            $cinema, $room, $roomType, $vip, CarbonImmutable::parse('2026-08-20 20:00', $cinema->timezone),
        )->finalUnitAmountVnd);
        $this->assertSame(205_000, $pricing->resolve(
            $cinema, $room, $roomType, $couple, CarbonImmutable::parse('2026-08-20 20:00', $cinema->timezone),
        )->finalUnitAmountVnd);
        $this->assertSame(95_000, $pricing->resolve(
            $cinema, $room, $roomType, $normal, CarbonImmutable::parse('2026-08-21 20:00', $cinema->timezone),
        )->finalUnitAmountVnd);
    }

    public function test_validation_and_global_authorization_reject_invalid_schedule_changes_without_mutation(): void
    {
        [$cinema] = $this->pricingContext('SCHEDULE_AUTH');
        $published = $this->publishedVersion();
        $manager = $this->userWithRole('manager');
        UserCinemaAssignment::query()->updateOrCreate(
            ['user_id' => $manager->id, 'cinema_id' => $cinema->id],
            ['status' => UserCinemaAssignment::STATUS_ACTIVE, 'assigned_at' => now()],
        );
        $payload = [
            'change_kind' => PriceBookScheduleChangeService::KIND_SINGLE_DAY,
            'change_date' => '2026-08-20',
            'ticket_prices' => $this->newPrices(),
        ];

        $this->post(route('admin.price-books.versions.schedule-change.preview', $published), $payload)
            ->assertRedirect(route('login'));
        $this->post(route('admin.price-books.versions.schedule-change.apply', $published), $payload)
            ->assertRedirect(route('login'));

        $this->actingAs($manager)->withSession([CinemaAccessService::SESSION_KEY => $cinema->id])
            ->post(route('admin.price-books.versions.schedule-change.preview', $published), $payload)
            ->assertForbidden();
        $this->post(route('admin.price-books.versions.schedule-change.apply', $published), $payload)
            ->assertForbidden();

        $this->actingAs($this->userWithRole('admin'))
            ->from(route('admin.price-books.versions.show', $published))
            ->post(route('admin.price-books.versions.schedule-change.apply', $published), [
                ...$payload,
                'change_date' => '2030-01-01',
            ])
            ->assertRedirect(route('admin.price-books.versions.show', $published))
            ->assertSessionHasErrors('change_date');

        $this->assertSame(PriceBookVersion::STATUS_PUBLISHED, $published->fresh()->status);
        $this->assertDatabaseCount('price_book_versions', 1);
    }

    public function test_mid_replacement_failure_rolls_back_retirement_and_every_created_period(): void
    {
        $published = $this->publishedVersion();
        $maximumPrices = collect($this->newPrices())->map(fn (): int => Showtime::MAX_PRICE)->all();

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.price-books.versions.schedule-change.apply', $published), [
                'change_kind' => PriceBookScheduleChangeService::KIND_FROM_DATE,
                'change_date' => '2027-01-01',
                'ticket_prices' => $maximumPrices,
            ])
            ->assertRedirect(route('admin.price-books.versions.show', $published))
            ->assertSessionHasErrors('schedule_change');

        $this->assertSame(PriceBookVersion::STATUS_PUBLISHED, $published->fresh()->status);
        $this->assertDatabaseCount('price_book_versions', 1);
        $this->assertDatabaseCount('price_book_adjustments', 5);
    }

    public function test_stale_second_apply_is_rejected_after_the_source_is_retired(): void
    {
        $published = $this->publishedVersion();
        $service = app(PriceBookScheduleChangeService::class);
        $service->apply(
            $published,
            PriceBookScheduleChangeService::KIND_FROM_DATE,
            '2027-01-01',
            $this->newPrices(),
        );

        try {
            $service->apply(
                $published,
                PriceBookScheduleChangeService::KIND_FROM_DATE,
                '2027-06-01',
                $this->newPrices(),
            );
            $this->fail('A stale schedule replacement must not run twice.');
        } catch (PriceBookException $exception) {
            $this->assertSame(PriceBookException::INVALID_TRANSITION, $exception->domainCode);
        }

        $this->assertSame(PriceBookVersion::STATUS_RETIRED, $published->fresh()->status);
        $this->assertSame(2, PriceBookVersion::query()->where('status', PriceBookVersion::STATUS_PUBLISHED)->count());
    }

    private function publishedVersion(): PriceBookVersion
    {
        $this->seatType('normal');
        $draft = $this->priceBookDraft([
            'effective_from' => '2026-01-01',
            'effective_until' => '2030-01-01',
        ]);
        app(PriceBookVersionService::class)->replaceAdjustments($draft, $this->standardAdjustments());
        app(PriceBookVersionService::class)->publish($draft);

        return $draft->refresh();
    }

    /** @return array<int, int> */
    private function newPrices(): array
    {
        return [
            $this->seatType('normal')->id => 90_000,
            $this->seatType('vip')->id => 125_000,
            $this->seatType('couple', true)->id => 190_000,
        ];
    }

    /** @return array<int, int> */
    private function seatPrices(PriceBookVersion $version): array
    {
        $adjustments = $version->adjustments->where('dimension', 'seat_type')
            ->keyBy(fn ($adjustment): int => (int) $adjustment->seat_type_id);

        return SeatType::query()->where('status', true)->orderBy('id')->get()
            ->mapWithKeys(fn (SeatType $seatType): array => [
                (int) $seatType->id => (int) $version->base_price_vnd
                    + (int) ($adjustments->get((int) $seatType->id)?->amount_vnd ?? 0),
            ])->all();
    }
}
