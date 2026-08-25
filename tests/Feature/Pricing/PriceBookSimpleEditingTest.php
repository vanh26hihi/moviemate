<?php

namespace Tests\Feature\Pricing;

use App\Models\PriceBookVersion;
use App\Models\UserCinemaAssignment;
use App\Services\CinemaAccessService;
use App\Services\PriceBookVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesPriceBookFixtures;
use Tests\TestCase;

final class PriceBookSimpleEditingTest extends TestCase
{
    use CreatesPriceBookFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_published_workspace_shows_direct_customer_prices_by_logical_ticket_unit(): void
    {
        $published = $this->publishedVersion();

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.price-books.versions.show', $published))
            ->assertOk()
            ->assertSee('Giá bán theo loại vé')
            ->assertSee('80.000 ₫')
            ->assertSee('110.000 ₫')
            ->assertSee('160.000 ₫')
            ->assertSee('Một cặp hai ghế · tính một lần')
            ->assertSee('Khi nào giá vé thay đổi?')
            ->assertSee('3 phụ thu hoặc giảm giá');
    }

    public function test_global_admin_saves_direct_ticket_prices_as_normalized_base_and_adjustments_atomically(): void
    {
        $published = $this->publishedVersion();
        $draft = app(PriceBookVersionService::class)->copyToDraft(
            $published,
            '2027-01-01',
            '2028-01-01',
        );
        $normal = $this->seatType('normal');
        $vip = $this->seatType('vip');
        $couple = $this->seatType('couple', true);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.price-books.versions.show', $draft))
            ->assertOk()
            ->assertSee('step="1"', false)
            ->assertDontSee('step="1000"', false);

        $this->actingAs($admin)
            ->patch(route('admin.price-books.versions.simple-prices.update', $draft), [
                'effective_from' => '2031-01-01',
                'effective_end_date' => '2031-12-31',
                'ticket_prices' => [
                    $normal->id => 90_000,
                    $vip->id => 125_000,
                    $couple->id => 190_000,
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $draft->refresh();
        $this->assertSame(90_000, $draft->base_price_vnd);
        $this->assertSame('2031-01-01', $draft->effective_from->toDateString());
        $this->assertSame('2032-01-01', $draft->effective_until->toDateString());
        $this->assertDatabaseMissing('price_book_adjustments', [
            'price_book_version_id' => $draft->id,
            'dimension' => 'seat_type',
            'seat_type_id' => $normal->id,
        ]);
        $this->assertDatabaseHas('price_book_adjustments', [
            'price_book_version_id' => $draft->id,
            'dimension' => 'seat_type',
            'seat_type_id' => $vip->id,
            'amount_vnd' => 35_000,
        ]);
        $this->assertDatabaseHas('price_book_adjustments', [
            'price_book_version_id' => $draft->id,
            'dimension' => 'seat_type',
            'seat_type_id' => $couple->id,
            'amount_vnd' => 100_000,
        ]);
        $this->assertSame(3, $draft->adjustments()->where('dimension', '!=', 'seat_type')->count());
    }

    public function test_simple_price_validation_rejects_incomplete_rows_without_changing_draft(): void
    {
        $published = $this->publishedVersion();
        $draft = app(PriceBookVersionService::class)->copyToDraft($published, '2027-01-01', '2028-01-01');
        $before = $draft->adjustments()->orderBy('id')->get(['dimension', 'amount_vnd', 'seat_type_id'])->toArray();

        $this->actingAs($this->userWithRole('admin'))
            ->from(route('admin.price-books.versions.show', $draft))
            ->patch(route('admin.price-books.versions.simple-prices.update', $draft), [
                'effective_from' => '2031-01-01',
                'effective_end_date' => '2031-12-31',
                'ticket_prices' => [$this->seatType('normal')->id => 90_000],
            ])
            ->assertRedirect(route('admin.price-books.versions.show', $draft))
            ->assertSessionHasErrors('ticket_prices');

        $draft->refresh();
        $this->assertSame(80_000, $draft->base_price_vnd);
        $this->assertSame('2027-01-01', $draft->effective_from->toDateString());
        $this->assertSame($before, $draft->adjustments()->orderBy('id')->get(['dimension', 'amount_vnd', 'seat_type_id'])->toArray());
    }

    public function test_simple_price_mutation_is_global_admin_only_and_published_versions_remain_immutable(): void
    {
        [$cinema] = $this->pricingContext('SIMPLE_AUTH');
        $published = $this->publishedVersion();
        $draft = app(PriceBookVersionService::class)->copyToDraft($published, '2027-01-01', '2028-01-01');
        $manager = $this->userWithRole('manager');
        UserCinemaAssignment::query()->create([
            'user_id' => $manager->id,
            'cinema_id' => $cinema->id,
            'status' => UserCinemaAssignment::STATUS_ACTIVE,
            'assigned_at' => now(),
        ]);
        $payload = $this->simplePricePayload();

        $this->actingAs($manager)
            ->withSession([CinemaAccessService::SESSION_KEY => $cinema->id])
            ->patch(route('admin.price-books.versions.simple-prices.update', $draft), $payload)
            ->assertForbidden();

        $this->actingAs($this->userWithRole('admin'))
            ->patch(route('admin.price-books.versions.simple-prices.update', $published), $payload)
            ->assertForbidden();

        $this->assertSame(80_000, $draft->fresh()->base_price_vnd);
        $this->assertSame(PriceBookVersion::STATUS_PUBLISHED, $published->fresh()->status);
    }

    public function test_copy_form_accepts_an_inclusive_last_day_and_stores_exclusive_boundary(): void
    {
        $published = $this->publishedVersion();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.price-books.versions.copy', $published), [
                'effective_from' => '2031-01-01',
                'effective_until' => '2032-01-01',
                'effective_end_date' => '2031-12-31',
            ])
            ->assertSessionHasErrors('effective_end_date');
        $this->assertDatabaseMissing('price_book_versions', ['status' => PriceBookVersion::STATUS_DRAFT]);

        $this->actingAs($admin)
            ->post(route('admin.price-books.versions.copy', $published), [
                'effective_from' => '2031-01-01',
                'effective_end_date' => '2031-12-31',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $draft = PriceBookVersion::query()->where('status', PriceBookVersion::STATUS_DRAFT)->sole();
        $this->assertSame('2031-01-01', $draft->effective_from->toDateString());
        $this->assertSame('2032-01-01', $draft->effective_until->toDateString());
    }

    private function publishedVersion(): PriceBookVersion
    {
        $this->seatType('normal');
        $draft = $this->priceBookDraft();
        app(PriceBookVersionService::class)->replaceAdjustments($draft, $this->standardAdjustments());
        app(PriceBookVersionService::class)->publish($draft);

        return $draft->refresh();
    }

    private function simplePricePayload(): array
    {
        return [
            'effective_from' => '2031-01-01',
            'effective_end_date' => '2031-12-31',
            'ticket_prices' => [
                $this->seatType('normal')->id => 90_000,
                $this->seatType('vip')->id => 125_000,
                $this->seatType('couple', true)->id => 190_000,
            ],
        ];
    }
}
