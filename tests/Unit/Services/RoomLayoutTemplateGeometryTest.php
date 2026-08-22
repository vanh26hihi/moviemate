<?php

namespace Tests\Unit\Services;

use App\Services\RoomLayoutTemplateGeometry;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RoomLayoutTemplateGeometryTest extends TestCase
{
    public function test_valid_couple_uses_exact_horizontal_sequential_labels_and_derives_sides(): void
    {
        $normalized = app(RoomLayoutTemplateGeometry::class)->normalize([
            'rows' => 3,
            'columns' => 3,
            'screen_position' => 'top',
            'cells' => [
                $this->couple(1, 3, 'C1'),
                $this->couple(2, 3, 'C2'),
                ['x' => 3, 'y' => 3, 'cell_type' => 'blocked'],
            ],
        ]);

        $this->assertSame(['left', 'right'], collect($normalized['cells'])
            ->where('seat_type', 'couple')->pluck('metadata.pair_position')->all());
        $this->assertSame('blocked', $normalized['cells'][2]['cell_type']);
        $this->assertNull($normalized['cells'][2]['seat_type']);
        $this->assertNull($normalized['cells'][2]['pair_key']);
    }

    #[DataProvider('invalidCoupleProvider')]
    public function test_invalid_template_couple_geometry_is_rejected(array $cells): void
    {
        $this->expectException(ValidationException::class);

        app(RoomLayoutTemplateGeometry::class)->normalize([
            'rows' => 3,
            'columns' => 4,
            'screen_position' => 'top',
            'cells' => $cells,
        ]);
    }

    public static function invalidCoupleProvider(): array
    {
        $couple = static fn (int $x, int $y, string $label): array => [
            'x' => $x, 'y' => $y, 'cell_type' => 'seat', 'seat_type' => 'couple',
            'seat_label' => $label, 'pair_key' => 'PAIR-1',
        ];

        return [
            'non-sequential C1/C3' => [[$couple(1, 3, 'C1'), $couple(2, 3, 'C3')]],
            'reversed C2/C1' => [[$couple(1, 3, 'C2'), $couple(2, 3, 'C1')]],
            'vertical pair' => [[$couple(1, 1, 'A1'), $couple(1, 2, 'B2')]],
            'gapped pair' => [[$couple(1, 3, 'C1'), $couple(3, 3, 'C2')]],
            'triple pair' => [[$couple(1, 3, 'C1'), $couple(2, 3, 'C2'), $couple(3, 3, 'C3')]],
            'orphan pair' => [[$couple(1, 3, 'C1')]],
            'seat plus blocked pair' => [[
                $couple(1, 3, 'C1'),
                ['x' => 2, 'y' => 3, 'cell_type' => 'blocked', 'pair_key' => 'PAIR-1'],
            ]],
            'seat plus aisle pair' => [[
                $couple(1, 3, 'C1'),
                ['x' => 2, 'y' => 3, 'cell_type' => 'aisle', 'pair_key' => 'PAIR-1'],
            ]],
        ];
    }

    public function test_unknown_type_and_structural_seat_metadata_are_rejected(): void
    {
        foreach ([
            [['x' => 1, 'y' => 1, 'cell_type' => 'pillar']],
            [['x' => 1, 'y' => 1, 'cell_type' => 'blocked', 'seat_label' => 'A1']],
            [['x' => 1, 'y' => 1, 'cell_type' => 'blocked', 'seat_type' => 'vip']],
            [['x' => 1, 'y' => 1, 'cell_type' => 'aisle', 'metadata' => ['row' => 'A']]],
        ] as $cells) {
            try {
                app(RoomLayoutTemplateGeometry::class)->normalize([
                    'rows' => 1, 'columns' => 1, 'screen_position' => 'top', 'cells' => $cells,
                ]);
                $this->fail('Malformed template structural cell must be rejected.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    private function couple(int $x, int $y, string $label): array
    {
        return [
            'x' => $x, 'y' => $y, 'cell_type' => 'seat', 'seat_type' => 'couple',
            'seat_label' => $label, 'pair_key' => 'PAIR-1',
        ];
    }
}
