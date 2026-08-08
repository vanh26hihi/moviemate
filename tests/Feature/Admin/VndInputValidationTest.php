<?php

namespace Tests\Feature\Admin;

use App\Models\FoodItem;
use App\Models\Showtime;
use App\Services\TicketPricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Showtimes\ShowtimeTestCase;
use Throwable;

class VndInputValidationTest extends ShowtimeTestCase
{
    public function test_showtime_ignores_browser_prices_and_stores_server_calculated_integer_previews(): void
    {
        $movie = $this->movie();
        $this->actingAs($this->userWithRole('admin'))->post(
            route('admin.showtimes.store'),
            $this->payload($movie, $this->rooms['P01'], ['price' => 1, 'vip_price' => 2]),
        )->assertRedirect(route('admin.showtimes.index'))->assertSessionHasNoErrors();

        $showtime = Showtime::query()->with(['cinema', 'room'])->sole();
        $prices = app(TicketPricingService::class)->calculateSeatTypes($showtime);
        $this->assertSame(80_000, (int) $showtime->price);
        $this->assertSame(110_000, (int) $showtime->vip_price);
        $this->assertSame(160_000, $prices['couple']->finalAmount);
        $this->assertSame('cinema-pricing-v1', $showtime->pricing_version);
    }

    public function test_showtime_forms_do_not_render_authoritative_price_inputs(): void
    {
        $movie = $this->movie();
        $showtime = $this->existing($movie, $this->rooms['P01']);
        $admin = $this->userWithRole('admin');
        foreach ([route('admin.showtimes.create'), route('admin.showtimes.edit', $showtime)] as $route) {
            $response = $this->actingAs($admin)->get($route)->assertOk();
            $response->assertDontSee('name="price"', false)->assertDontSee('name="vip_price"', false);
            $response->assertSee('Giá vé được tính từ Bảng giá vé');
        }
    }

    public function test_food_create_accepts_integer_and_update_normalizes_numeric_string(): void
    {
        $admin = $this->userWithRole('admin');
        $created = null;
        $updated = null;
        FoodItem::creating(function (FoodItem $model) use (&$created): void {
            $created = $model->getAttributes()['price'];
        });
        FoodItem::updating(function (FoodItem $model) use (&$updated): void {
            $updated = $model->getAttributes()['price'];
        });

        $this->actingAs($admin)->post(route('admin.foods.store'), ['name' => 'Bắp rang', 'price' => 50_000, 'active' => '1'])
            ->assertRedirect(route('admin.foods.index'))->assertSessionHasNoErrors();
        $food = FoodItem::query()->sole();
        $this->assertSame(50_000, $created);
        $this->actingAs($admin)->put(route('admin.foods.update', $food), ['name' => 'Bắp rang lớn', 'price' => '50000', 'active' => '1'])
            ->assertRedirect(route('admin.foods.index'))->assertSessionHasNoErrors();
        $this->assertSame(50_000, $updated);
        $this->assertSame(50_000, (int) $food->fresh()->price);
    }

    #[DataProvider('invalidVndInputs')]
    public function test_food_create_and_update_reject_noncanonical_or_out_of_range_vnd(mixed $value): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->post(route('admin.foods.store'), ['name' => 'Món thử', 'price' => $value, 'active' => '1'])
            ->assertSessionHasErrors('price');
        $food = FoodItem::query()->create(['name' => 'Món cũ', 'price' => 50_000, 'active' => true]);
        $this->actingAs($admin)->put(route('admin.foods.update', $food), ['name' => 'Món mới', 'price' => $value, 'active' => '1'])
            ->assertSessionHasErrors('price');
        $this->assertSame('50000.00', $food->fresh()->price);
    }

    public function test_food_forms_use_canonical_integer_strings_and_fail_closed_on_fractional_storage(): void
    {
        $food = FoodItem::query()->create(['name' => 'Bắp rang', 'price' => 35_000, 'active' => true]);
        $admin = $this->userWithRole('admin');
        $this->assertInputValue($this->actingAs($admin)->get(route('admin.foods.edit', $food))->assertOk(), 'price', '35000');
        $this->assertInputValue($this->get(route('admin.foods.create'))->assertOk(), 'price', '');

        DB::table('food_items')->where('id', $food->id)->update(['price' => '35000.50']);
        $this->withoutExceptionHandling();
        try {
            $this->get(route('admin.foods.edit', $food));
            $this->fail('Fractional stored VND unexpectedly rendered.');
        } catch (Throwable $exception) {
            $messages = $exception->getMessage();
            while ($exception = $exception->getPrevious()) {
                $messages .= ' '.$exception->getMessage();
            }
            $this->assertStringContainsString('VND', $messages);
            $this->assertStringContainsString('database amount', $messages);
        }
    }

    public static function invalidVndInputs(): array
    {
        return [
            'whole decimal string' => ['50000.00'], 'fractional decimal string' => ['50000.5'],
            'comma decimal' => ['50000,5'], 'float' => [50_000.0], 'scientific' => ['5e4'],
            'negative integer' => [-1], 'negative string' => ['-1'], 'plus sign' => ['+50000'],
            'leading zero' => ['050000'], 'NaN' => ['NaN'], 'infinity' => ['INF'],
            'database overflow' => [(string) (FoodItem::MAX_PRICE + 1)],
            'application overflow' => ['999999999999999999999999999999999999'],
        ];
    }

    private function assertInputValue(TestResponse $response, string $name, string $value): void
    {
        $this->assertMatchesRegularExpression('/<input\b[^>]*\bname="'.preg_quote($name, '/').'"[^>]*\bvalue="'.preg_quote($value, '/').'"[^>]*>/i', (string) $response->getContent());
    }
}
