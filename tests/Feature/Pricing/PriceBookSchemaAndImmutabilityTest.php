<?php

namespace Tests\Feature\Pricing;

use App\Exceptions\PriceBookException;
use App\Models\PriceBookAdjustment;
use App\Models\PriceBookVersion;
use App\Services\PriceBookVersionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesPriceBookFixtures;
use Tests\TestCase;

class PriceBookSchemaAndImmutabilityTest extends TestCase
{
    use CreatesPriceBookFixtures;
    use RefreshDatabase;

    public function test_schema_is_normalized_integer_money_and_format_neutral(): void
    {
        $this->assertTrue(Schema::hasColumns('price_books', ['id', 'code', 'name']));
        $this->assertTrue(Schema::hasColumns('price_book_versions', [
            'price_book_id', 'version_number', 'status', 'base_price_vnd',
            'effective_from', 'effective_until', 'published_at', 'retired_at',
            'created_by_user_id', 'updated_by_user_id',
        ]));
        $this->assertTrue(Schema::hasColumns('price_book_adjustments', [
            'price_book_version_id', 'dimension', 'amount_vnd', 'seat_type_id',
            'room_type_id', 'cinema_id', 'room_id', 'time_start', 'time_end',
            'holiday_date_from', 'holiday_date_until', 'weekend_days',
        ]));
        $this->assertFalse(Schema::hasColumn('price_book_adjustments', 'presentation_format_id'));
        $this->assertFalse(Schema::hasColumn('price_book_adjustments', 'priority'));
        $this->assertFalse(Schema::hasColumn('price_book_adjustments', 'base_price_vnd'));
        $this->assertSame('integer', collect(Schema::getColumns('price_book_versions'))->firstWhere('name', 'base_price_vnd')['type_name']);
        $this->assertSame('integer', collect(Schema::getColumns('price_book_adjustments'))->firstWhere('name', 'amount_vnd')['type_name']);
        $this->assertTrue(Schema::hasTable('cinema_pricing_rules'));
        $this->assertTrue(Schema::hasColumn('seat_types', 'price_modifier'));
    }

    public function test_database_constraints_reject_invalid_money_periods_and_foreign_keys(): void
    {
        $book = $this->chainPriceBook();
        foreach ([
            fn () => DB::table('price_book_versions')->insert([
                'price_book_id' => $book->id, 'version_number' => 1, 'status' => 'draft',
                'base_price_vnd' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]),
            fn () => DB::table('price_book_versions')->insert([
                'price_book_id' => $book->id, 'version_number' => 2, 'status' => 'draft',
                'base_price_vnd' => 80_000, 'effective_from' => '2026-01-01',
                'effective_until' => '2026-01-01', 'created_at' => now(), 'updated_at' => now(),
            ]),
        ] as $write) {
            try {
                $write();
                $this->fail('Database constraint should reject the invalid row.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_database_and_application_block_all_published_adjustment_mutations(): void
    {
        $version = $this->priceBookDraft();
        app(PriceBookVersionService::class)->replaceAdjustments($version, [[
            'dimension' => 'seat_type', 'label' => 'VIP',
            'seat_type_id' => $this->seatType('vip')->id, 'amount_vnd' => 30_000,
        ]]);
        $version = app(PriceBookVersionService::class)->publish($version);
        $adjustment = $version->adjustments->first();

        $writes = [
            fn () => DB::table('price_book_versions')->where('id', $version->id)->update(['base_price_vnd' => 90_000]),
            fn () => DB::table('price_book_versions')->where('id', $version->id)->update(['effective_until' => '2028-01-01']),
            fn () => DB::table('price_book_adjustments')->insert([
                'price_book_version_id' => $version->id, 'dimension' => 'weekend', 'label' => 'No',
                'amount_vnd' => 1, 'weekend_days' => json_encode([7]), 'created_at' => now(), 'updated_at' => now(),
            ]),
            fn () => DB::table('price_book_adjustments')->where('id', $adjustment->id)->update(['amount_vnd' => 1]),
            fn () => DB::table('price_book_adjustments')->where('id', $adjustment->id)->delete(),
            fn () => DB::table('price_book_versions')->where('id', $version->id)->delete(),
        ];
        foreach ($writes as $write) {
            try {
                $write();
                $this->fail('Published financial authority must be immutable in the database.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(PriceBookException::class);
        PriceBookAdjustment::query()->findOrFail($adjustment->id)->update(['amount_vnd' => 2]);
    }

    public function test_retired_definitions_remain_immutable_while_drafts_are_editable_and_deletable(): void
    {
        $service = app(PriceBookVersionService::class);
        $published = $service->publish($this->priceBookDraft());
        $retired = $service->retire($published);
        $this->assertSame(PriceBookVersion::STATUS_RETIRED, $retired->status);

        try {
            DB::table('price_book_versions')->where('id', $retired->id)->update(['status' => 'draft']);
            $this->fail('Retired version must never reopen.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $draft = $this->priceBookDraft(['effective_from' => null, 'effective_until' => null]);
        $service->updateDraft($draft, ['base_price_vnd' => 90_000]);
        $this->assertSame(90_000, $draft->refresh()->base_price_vnd);
        $service->deleteDraft($draft);
        $this->assertDatabaseMissing('price_book_versions', ['id' => $draft->id]);
    }
}
