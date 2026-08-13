<?php

namespace Database\Seeders;

use App\Models\PriceBook;
use App\Models\PriceBookVersion;
use App\Models\SeatType;
use App\Services\PriceBookVersionService;
use Illuminate\Database\Seeder;
use RuntimeException;

final class PriceBookSeeder extends Seeder
{
    public function run(PriceBookVersionService $versions): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Bỏ qua PriceBook demo ngoài môi trường local/testing.');

            return;
        }

        $book = PriceBook::query()->firstOrCreate(
            ['code' => PriceBook::CHAIN_CODE],
            ['name' => 'Bảng giá chuỗi MovieMate'],
        );
        if (PriceBook::query()->whereKeyNot($book->id)->exists()) {
            throw new RuntimeException('PriceBook seeding requires exactly one chain authority.');
        }

        $existing = $book->versions()->where('version_number', 1)->with('adjustments')->first();
        if ($existing) {
            $this->assertCoherent($existing);

            return;
        }

        $seatTypes = SeatType::query()->whereIn('code', ['normal', 'vip', 'couple'])->pluck('id', 'code');
        foreach (['normal', 'vip', 'couple'] as $code) {
            if (! $seatTypes->has($code)) {
                throw new RuntimeException("Missing seeded SeatType [{$code}] for PriceBook.");
            }
        }

        $version = $versions->createDraft($book, [
            'base_price_vnd' => 80_000,
            'effective_from' => '2026-01-01',
            'effective_until' => '2030-01-01',
        ]);
        $versions->replaceAdjustments($version, [
            [
                'dimension' => 'seat_type',
                'label' => 'Phụ thu VIP',
                'seat_type_id' => $seatTypes['vip'],
                'amount_vnd' => 30_000,
            ],
            [
                'dimension' => 'seat_type',
                'label' => 'Giá ghế đôi theo một cặp logic',
                'seat_type_id' => $seatTypes['couple'],
                'amount_vnd' => 80_000,
            ],
            [
                'dimension' => 'time_window',
                'label' => 'Phụ thu suất tối',
                'time_start' => '18:00',
                'time_end' => '22:00',
                'amount_vnd' => 15_000,
            ],
            [
                'dimension' => 'weekend',
                'label' => 'Phụ thu cuối tuần',
                'weekend_days' => [6, 7],
                'amount_vnd' => 10_000,
            ],
            [
                'dimension' => 'holiday',
                'label' => 'Ngày hội MovieMate 2026',
                'holiday_date_from' => '2026-09-01',
                'holiday_date_until' => '2026-09-02',
                'amount_vnd' => 20_000,
            ],
        ]);
        $versions->publish($version);
    }

    private function assertCoherent(PriceBookVersion $version): void
    {
        $expected = [
            'status' => PriceBookVersion::STATUS_PUBLISHED,
            'base_price_vnd' => 80_000,
            'effective_from' => '2026-01-01',
            'effective_until' => '2030-01-01',
        ];
        foreach ($expected as $field => $value) {
            $actual = $version->{$field};
            $actual = is_object($actual) && method_exists($actual, 'toDateString')
                ? $actual->toDateString()
                : $actual;
            if ($actual !== $value) {
                throw new RuntimeException("Existing immutable PriceBook seed is incoherent at [{$field}].");
            }
        }
        if ($version->adjustments->count() !== 5) {
            throw new RuntimeException('Existing immutable PriceBook seed has an unexpected adjustment set.');
        }
    }
}
