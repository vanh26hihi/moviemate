<?php

namespace Tests\Unit\Services;

use App\Models\FoodItem;
use App\Services\BookingFoodService;
use App\Services\CinemaContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BookingFoodServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_selection_has_zero_subtotal_without_resolving_pickup(): void
    {
        $cinema = $this->createMock(CinemaContext::class);
        $cinema->expects($this->never())->method('id');

        $result = (new BookingFoodService($cinema))->calculate([]);

        $this->assertSame(0, $result->foodSubtotal);
        $this->assertSame([], $result->lines);
        $this->assertNull($result->pickupCinemaId);
    }

    public function test_zero_quantity_is_removed_from_selection(): void
    {
        $result = $this->service()->calculate([999999 => 0]);

        $this->assertTrue($result->isEmpty());
        $this->assertSame(0, $result->foodSubtotal);
    }

    public function test_negative_quantity_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->calculate([1 => -1]);
    }

    public function test_quantity_over_twenty_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->calculate([1 => 21]);
    }

    public function test_non_integer_quantity_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->calculate([1 => 1.5]);
    }

    public function test_duplicate_food_ids_in_list_payload_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->calculate([
            ['food_id' => 1, 'quantity' => 1],
            ['food_id' => 1, 'quantity' => 2],
        ]);
    }

    public function test_missing_food_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->calculate([999999 => 1]);
    }

    public function test_inactive_food_is_rejected(): void
    {
        $food = $this->food('Inactive', 10_000, false);
        $this->expectException(InvalidArgumentException::class);

        $this->service()->calculate([$food->id => 1]);
    }

    public function test_client_prices_are_ignored_and_database_price_is_snapshotted(): void
    {
        $food = $this->food('Bắp rang', 50_000);

        $result = $this->service()->calculate([
            ['food_id' => $food->id, 'quantity' => 2, 'unit_price' => 1, 'line_total' => 2],
        ]);
        $line = $result->lines[0];

        $this->assertSame('Bắp rang', $line->snapshotName);
        $this->assertSame(50_000, $line->unitPrice);
        $this->assertSame(100_000, $line->lineTotal);
        $this->assertSame(100_000, $result->foodSubtotal);
    }

    public function test_multiple_food_lines_have_integer_subtotal_and_canonical_pickup(): void
    {
        $popcorn = $this->food('Popcorn', 45_000);
        $drink = $this->food('Drink', 20_000);

        $result = $this->service()->calculate([$popcorn->id => 2, $drink->id => 3]);

        $this->assertSame(150_000, $result->foodSubtotal);
        $this->assertCount(2, $result->lines);
        $this->assertSame(app(CinemaContext::class)->id(), $result->pickupCinemaId);
        $this->assertSame('VND', $result->currency);
    }

    private function service(): BookingFoodService
    {
        return app(BookingFoodService::class);
    }

    private function food(string $name, int $price, bool $active = true): FoodItem
    {
        return FoodItem::query()->create(compact('name', 'price', 'active'));
    }
}
