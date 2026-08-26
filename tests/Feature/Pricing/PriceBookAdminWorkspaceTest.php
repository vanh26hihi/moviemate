<?php

namespace Tests\Feature\Pricing;

use App\Models\PriceBookVersion;
use App\Models\UserCinemaAssignment;
use App\Services\CinemaAccessService;
use App\Services\PriceBookVersionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesPriceBookFixtures;
use Tests\TestCase;

final class PriceBookAdminWorkspaceTest extends TestCase
{
    use CreatesPriceBookFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-08-14 00:00:00');
        $this->withoutVite();
        $this->seedRbac();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_global_admin_manages_draft_through_authoritative_lifecycle_routes(): void
    {
        $admin = $this->userWithRole('admin');
        $published = $this->publishedVersion();

        $this->actingAs($admin)->get(route('admin.price-books.index'))
            ->assertOk()->assertSee('Bảng giá')->assertSee('Đã phát hành');
        $this->get(route('admin.price-books.versions.show', $published))
            ->assertOk()->assertDontSee('name="base_price_vnd"', false);

        $this->post(route('admin.price-books.versions.copy', $published), [
            'effective_from' => '2027-01-01',
            'effective_until' => '2028-01-01',
        ])->assertRedirect();

        $draft = PriceBookVersion::query()->where('status', PriceBookVersion::STATUS_DRAFT)->firstOrFail();
        $this->get(route('admin.price-books.versions.show', $draft))
            ->assertOk()->assertSee('name="base_price_vnd"', false)
            ->assertSee('Sao chép độc lập từ phiên bản đã phát hành');

        $this->patch(route('admin.price-books.versions.update', $draft), [
            'base_price_vnd' => 85_000,
            'effective_from' => '2027-01-01',
            'effective_until' => '2028-01-01',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(85_000, $draft->fresh()->base_price_vnd);

        $vip = $this->seatType('vip');
        $this->post(route('admin.price-books.versions.adjustments.store', $draft), [
            'dimension' => 'seat_type', 'label' => 'Điều chỉnh VIP mới',
            'amount_vnd' => 35_000, 'seat_type_id' => $vip->id,
        ])->assertSessionHasErrors('adjustment');

        $adjustment = $draft->adjustments()->where('dimension', 'seat_type')->firstOrFail();
        $this->patch(route('admin.price-books.versions.adjustments.update', [$draft, $adjustment]), [
            'dimension' => 'seat_type', 'label' => 'Điều chỉnh VIP',
            'amount_vnd' => 35_000, 'seat_type_id' => $adjustment->seat_type_id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(35_000, (int) $draft->adjustments()->where('dimension', 'seat_type')
            ->where('seat_type_id', $adjustment->seat_type_id)->value('amount_vnd'));
    }

    public function test_global_admin_can_publish_and_retire_but_published_definition_is_read_only(): void
    {
        $admin = $this->userWithRole('admin');
        $draft = $this->priceBookDraft([
            'effective_from' => '2028-01-01', 'effective_until' => '2029-01-01',
        ]);

        $this->actingAs($admin)->post(route('admin.price-books.versions.publish', $draft))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(PriceBookVersion::STATUS_PUBLISHED, $draft->fresh()->status);

        $this->patch(route('admin.price-books.versions.update', $draft), [
            'base_price_vnd' => 1, 'effective_from' => '2028-01-01',
        ])->assertForbidden();

        $this->post(route('admin.price-books.versions.retire', $draft))->assertRedirect();
        $this->assertSame(PriceBookVersion::STATUS_RETIRED, $draft->fresh()->status);
        $this->get(route('admin.price-books.versions.show', $draft))
            ->assertOk()->assertSee('Đã ngừng sử dụng')
            ->assertDontSee('name="base_price_vnd"', false)
            ->assertDontSee('Sao chép thành bản nháp mới');
        $this->post(route('admin.price-books.versions.copy', $draft))->assertForbidden();
    }

    public function test_manager_sees_only_published_current_branch_pricing_and_no_mutation_controls(): void
    {
        [$cinemaA] = $this->pricingContext('A');
        [$cinemaB] = $this->pricingContext('B');
        $published = $this->publishedVersion($cinemaA->id, $cinemaB->id);
        $future = $this->priceBookDraft([
            'effective_from' => '2027-01-01', 'effective_until' => '2028-01-01',
        ]);
        app(PriceBookVersionService::class)->replaceAdjustments($future, $this->standardAdjustments());
        app(PriceBookVersionService::class)->publish($future);
        $draft = app(PriceBookVersionService::class)->copyToDraft($published, '2027-01-01', '2028-01-01');
        $manager = $this->managerFor($cinemaA->id);
        $session = [CinemaAccessService::SESSION_KEY => $cinemaA->id];

        $response = $this->actingAs($manager)->withSession($session)
            ->get(route('admin.price-books.index'))->assertOk()
            ->assertSee('Chỉ xem')->assertSee('Đã phát hành')
            ->assertSee(route('admin.price-books.versions.show', $future), false)
            ->assertDontSee(route('admin.price-books.versions.show', $draft), false)
            ->assertDontSee('Sao chép thành bản nháp mới');

        $response = $this->get(route('admin.price-books.versions.show', $published))->assertOk()
            ->assertSee($cinemaA->name)->assertDontSee($cinemaB->name)
            ->assertDontSee('name="base_price_vnd"', false)
            ->assertDontSee('Phát hành phiên bản');
        $this->get(route('admin.price-books.versions.show', $future))->assertOk();
        $this->get(route('admin.price-books.versions.show', $draft))->assertNotFound();
    }

    public function test_manager_direct_mutation_requests_are_rejected_even_with_pricing_manage_permission(): void
    {
        [$cinema] = $this->pricingContext('AUTH');
        $published = $this->publishedVersion($cinema->id);
        $draft = app(PriceBookVersionService::class)->copyToDraft($published, '2027-01-01', '2028-01-01');
        $adjustment = $draft->adjustments()->firstOrFail();
        $manager = $this->managerFor($cinema->id);
        $session = [CinemaAccessService::SESSION_KEY => $cinema->id];

        $requests = [
            fn () => $this->post(route('admin.price-books.versions.copy', $published)),
            fn () => $this->patch(route('admin.price-books.versions.update', $draft), []),
            fn () => $this->post(route('admin.price-books.versions.adjustments.store', $draft), []),
            fn () => $this->patch(route('admin.price-books.versions.adjustments.update', [$draft, $adjustment]), []),
            fn () => $this->delete(route('admin.price-books.versions.adjustments.destroy', [$draft, $adjustment])),
            fn () => $this->post(route('admin.price-books.versions.publish', $draft)),
            fn () => $this->post(route('admin.price-books.versions.retire', $published)),
        ];

        $this->actingAs($manager)->withSession($session);
        foreach ($requests as $request) {
            $request()->assertForbidden();
        }
        $this->assertSame(PriceBookVersion::STATUS_DRAFT, $draft->fresh()->status);
        $this->assertDatabaseHas('price_book_adjustments', ['id' => $adjustment->id]);
    }

    public function test_authoritative_preview_orders_components_and_replaces_weekend_with_holiday(): void
    {
        [$cinema, $roomType, $room] = $this->pricingContext('PREVIEW');
        $vip = $this->seatType('vip');
        $draft = $this->priceBookDraft(['effective_from' => '2026-01-01', 'effective_until' => '2027-01-01']);
        app(PriceBookVersionService::class)->replaceAdjustments($draft, [
            ['dimension' => 'seat_type', 'label' => 'VIP', 'seat_type_id' => $vip->id, 'amount_vnd' => 30_000],
            ['dimension' => 'room_type', 'label' => 'Loại phòng', 'room_type_id' => $roomType->id, 'amount_vnd' => 5_000],
            ['dimension' => 'time_window', 'label' => 'Buổi tối', 'time_start' => '18:00', 'time_end' => '22:00', 'amount_vnd' => 15_000],
            ['dimension' => 'weekend', 'label' => 'Cuối tuần', 'weekend_days' => [6, 7], 'amount_vnd' => 10_000],
            ['dimension' => 'holiday', 'label' => 'Ngày lễ', 'holiday_date_from' => '2026-08-15', 'holiday_date_until' => '2026-08-16', 'amount_vnd' => 20_000],
            ['dimension' => 'cinema', 'label' => 'Chi nhánh', 'cinema_id' => $cinema->id, 'amount_vnd' => -2_000],
            ['dimension' => 'room', 'label' => 'Phòng', 'room_id' => $room->id, 'amount_vnd' => 3_000],
        ]);
        app(PriceBookVersionService::class)->publish($draft);

        $response = $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.price-books.preview'), [
                'cinema_id' => $cinema->id, 'room_id' => $room->id,
                'seat_type_id' => $vip->id, 'showtime_local_start' => '2026-08-15 20:15',
            ])->assertOk()->assertSee('Giá vé đã tính')->assertSee('151.000 ₫')
            ->assertSee('data-preview-dimension="holiday"', false)
            ->assertDontSee('data-preview-dimension="weekend"', false)
            ->assertDontSee('presentation_format_id', false);

        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'data-preview-dimension="room_type"'), strpos($html, 'data-preview-dimension="seat_type"'));
        $this->assertLessThan(strpos($html, 'data-preview-dimension="time_window"'), strpos($html, 'data-preview-dimension="room_type"'));
        $this->assertLessThan(strpos($html, 'data-preview-dimension="cinema"'), strpos($html, 'data-preview-dimension="holiday"'));
        $this->assertLessThan(strpos($html, 'data-preview-dimension="room"'), strpos($html, 'data-preview-dimension="cinema"'));
    }

    public function test_preview_get_recovers_safely_without_changing_the_authoritative_post_endpoint(): void
    {
        [$cinema] = $this->pricingContext('PREVIEW-GET');
        $admin = $this->userWithRole('admin');
        $manager = $this->managerFor($cinema->id);
        $session = [CinemaAccessService::SESSION_KEY => $cinema->id];

        $this->get(route('admin.price-books.preview.redirect'))
            ->assertRedirect(route('login'));

        $this->actingAs($admin)
            ->get(route('admin.price-books.preview.redirect'))
            ->assertRedirect(route('admin.price-books.index'));

        $this->actingAs($manager)->withSession($session)
            ->get(route('admin.price-books.preview.redirect'))
            ->assertRedirect(route('admin.price-books.index'));

        $this->assertSame(
            route('admin.price-books.preview'),
            route('admin.price-books.preview.redirect'),
        );
    }

    public function test_authoritative_preview_prices_vip_and_couple_as_logical_units(): void
    {
        [$cinema, , $room] = $this->pricingContext('LOGICAL');
        $this->publishedVersion();
        $admin = $this->userWithRole('admin');
        $payload = [
            'cinema_id' => $cinema->id,
            'room_id' => $room->id,
            'showtime_local_start' => '2026-08-15 20:15',
        ];

        $this->actingAs($admin)->post(route('admin.price-books.preview'), [
            ...$payload, 'seat_type_id' => $this->seatType('vip')->id,
        ])->assertOk()
            ->assertSee('135.000 ₫')
            ->assertSee('data-preview-dimension="weekend"', false)
            ->assertDontSee('data-preview-dimension="holiday"', false);

        $this->post(route('admin.price-books.preview'), [
            ...$payload, 'seat_type_id' => $this->seatType('couple', true)->id,
        ])->assertOk()
            ->assertSee('185.000 ₫')
            ->assertSee('Giá cho một cặp ghế đôi')
            ->assertSee('Một cặp ghế đôi (tính một lần)');
    }

    public function test_manager_preview_is_locked_to_current_cinema_and_rejects_foreign_room(): void
    {
        [$cinemaA, , $roomA] = $this->pricingContext('OWN');
        [$cinemaB, , $roomB] = $this->pricingContext('FOREIGN');
        $this->publishedVersion($cinemaA->id, $cinemaB->id);
        $manager = $this->managerFor($cinemaA->id);
        $session = [CinemaAccessService::SESSION_KEY => $cinemaA->id];
        $payload = [
            'cinema_id' => $cinemaA->id, 'room_id' => $roomA->id,
            'seat_type_id' => $this->seatType('vip')->id,
            'showtime_local_start' => '2026-08-15 20:15',
        ];

        $this->actingAs($manager)->withSession($session)
            ->post(route('admin.price-books.preview'), $payload)->assertOk()->assertSee($cinemaA->name);
        $this->post(route('admin.price-books.preview'), [...$payload, 'cinema_id' => $cinemaB->id, 'room_id' => $roomB->id])
            ->assertNotFound();
        $this->post(route('admin.price-books.preview'), [...$payload, 'room_id' => $roomB->id])
            ->assertNotFound();
    }

    public function test_price_book_admin_surfaces_stay_within_query_budgets(): void
    {
        [$cinema, , $room] = $this->pricingContext('QUERY');
        $published = $this->publishedVersion($cinema->id);
        $admin = $this->userWithRole('admin');
        $manager = $this->managerFor($cinema->id);
        $session = [CinemaAccessService::SESSION_KEY => $cinema->id];
        $preview = [
            'cinema_id' => $cinema->id,
            'room_id' => $room->id,
            'seat_type_id' => $this->seatType('vip')->id,
            'showtime_local_start' => '2026-08-15 20:15',
        ];

        $counts = [
            'global_workspace' => $this->countQueries(fn () => $this->actingAs($admin)
                ->get(route('admin.price-books.index'))->assertOk()),
            'global_version_detail' => $this->countQueries(fn () => $this->actingAs($admin)
                ->get(route('admin.price-books.versions.show', $published))->assertOk()),
            'manager_workspace' => $this->countQueries(fn () => $this->actingAs($manager)->withSession($session)
                ->get(route('admin.price-books.index'))->assertOk()),
            'manager_version_detail' => $this->countQueries(fn () => $this->actingAs($manager)->withSession($session)
                ->get(route('admin.price-books.versions.show', $published))->assertOk()),
            'authoritative_preview' => $this->countQueries(fn () => $this->actingAs($manager)->withSession($session)
                ->post(route('admin.price-books.preview'), $preview)->assertOk()),
        ];

        foreach ($counts as $operation => $count) {
            $this->assertLessThanOrEqual(30, $count, "{$operation} query budget exceeded: ".json_encode($counts));
        }
        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, 'PRICE_BOOK_ADMIN_QUERY_COUNTS='.json_encode($counts, JSON_THROW_ON_ERROR).PHP_EOL);
        }
    }

    private function publishedVersion(?int $cinemaA = null, ?int $cinemaB = null): PriceBookVersion
    {
        $draft = $this->priceBookDraft();
        $adjustments = $this->standardAdjustments();
        foreach (array_filter([$cinemaA, $cinemaB]) as $index => $cinemaId) {
            $adjustments[] = [
                'dimension' => 'cinema', 'label' => $index === 0 ? 'Chi nhánh A' : 'Chi nhánh B',
                'cinema_id' => $cinemaId, 'amount_vnd' => 1_000 + $index,
            ];
        }
        app(PriceBookVersionService::class)->replaceAdjustments($draft, $adjustments);
        app(PriceBookVersionService::class)->publish($draft);

        return $draft->refresh();
    }

    private function managerFor(int $cinemaId)
    {
        $manager = $this->userWithRole('manager');
        UserCinemaAssignment::query()->updateOrCreate(
            ['user_id' => $manager->id, 'cinema_id' => $cinemaId],
            ['status' => UserCinemaAssignment::STATUS_ACTIVE, 'assigned_at' => now()],
        );

        return $manager;
    }

    private function countQueries(callable $operation): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $operation();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
}
