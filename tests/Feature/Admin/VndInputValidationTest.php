<?php

namespace Tests\Feature\Admin;

use App\Models\FoodItem;
use App\Models\Showtime;
use App\Services\ShowtimeScheduleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Showtimes\ShowtimeTestCase;
use Throwable;

class VndInputValidationTest extends ShowtimeTestCase
{
    public function test_showtime_create_accepts_integer_and_stores_it_without_floating_point(): void
    {
        $movie = $this->movie();

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.showtimes.store'), $this->payload($movie, $this->rooms['P01'], [
                'price' => 50_000,
                'vip_price' => 70_000,
            ]))
            ->assertRedirect(route('admin.showtimes.index'))
            ->assertSessionHasNoErrors();

        $showtime = Showtime::query()->sole();
        $this->assertSame(50_000, $showtime->priceForSeatType('normal'));
        $this->assertSame(70_000, $showtime->priceForSeatType('vip'));
        $this->assertSame(100_000, $showtime->priceForSeatType('couple'));
    }

    public function test_showtime_update_normalizes_canonical_numeric_strings_to_integers(): void
    {
        $movie = $this->movie();
        $room = $this->rooms['P01'];
        $showtime = $this->existing($movie, $room);
        $priceBeforePersistence = null;
        $vipPriceBeforePersistence = null;
        Showtime::updating(function (Showtime $updating) use (&$priceBeforePersistence, &$vipPriceBeforePersistence): void {
            $priceBeforePersistence = $updating->getAttributes()['price'];
            $vipPriceBeforePersistence = $updating->getAttributes()['vip_price'];
        });

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('admin.showtimes.update', $showtime), $this->payload($movie, $room, [
                'price' => '50000',
                'vip_price' => '70000',
            ]))
            ->assertRedirect(route('admin.showtimes.index'))
            ->assertSessionHasNoErrors();

        $showtime->refresh();
        $this->assertSame(50_000, $priceBeforePersistence);
        $this->assertSame(70_000, $vipPriceBeforePersistence);
        $this->assertSame(50_000, $showtime->priceForSeatType('normal'));
        $this->assertSame(70_000, $showtime->priceForSeatType('vip'));
    }

    #[DataProvider('invalidShowtimeVndInputs')]
    public function test_showtime_create_rejects_noncanonical_or_out_of_range_vnd(mixed $value): void
    {
        $movie = $this->movie();

        $response = $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.showtimes.store'), $this->payload($movie, $this->rooms['P01'], [
                'price' => $value,
            ]));

        $response->assertSessionHasErrors('price');
        $this->assertStringContainsString('Giá vé thường', session('errors')->first('price'));
        $this->assertDatabaseCount('showtimes', 0);
    }

    #[DataProvider('invalidShowtimeVndInputs')]
    public function test_showtime_update_rejects_noncanonical_or_out_of_range_vnd(mixed $value): void
    {
        $movie = $this->movie();
        $room = $this->rooms['P01'];
        $showtime = $this->existing($movie, $room);

        $response = $this->actingAs($this->userWithRole('admin'))
            ->put(route('admin.showtimes.update', $showtime), $this->payload($movie, $room, [
                'vip_price' => $value,
            ]));

        $response->assertSessionHasErrors('vip_price');
        $this->assertStringContainsString('Giá vé VIP', session('errors')->first('vip_price'));
        $this->assertSame('110000.00', $showtime->fresh()->vip_price);
    }

    public function test_showtime_service_rejects_fractional_values_when_called_without_http_validation(): void
    {
        $movie = $this->movie();

        $this->expectException(InvalidArgumentException::class);

        app(ShowtimeScheduleService::class)->schedule($this->payload($movie, $this->rooms['P01'], [
            'price' => '50000.00',
        ]));
    }

    public function test_food_create_accepts_integer_and_food_update_normalizes_numeric_string(): void
    {
        $admin = $this->userWithRole('admin');
        $createdPriceBeforePersistence = null;
        $updatedPriceBeforePersistence = null;
        FoodItem::creating(function (FoodItem $creating) use (&$createdPriceBeforePersistence): void {
            $createdPriceBeforePersistence = $creating->getAttributes()['price'];
        });
        FoodItem::updating(function (FoodItem $updating) use (&$updatedPriceBeforePersistence): void {
            $updatedPriceBeforePersistence = $updating->getAttributes()['price'];
        });

        $this->actingAs($admin)->post(route('admin.foods.store'), [
            'name' => 'Bắp rang',
            'price' => 50_000,
            'active' => '1',
        ])->assertRedirect(route('admin.foods.index'))->assertSessionHasNoErrors();

        $food = FoodItem::query()->sole();
        $this->assertSame(50_000, $createdPriceBeforePersistence);
        $this->assertSame(50_000, (int) $food->price);

        $this->actingAs($admin)->put(route('admin.foods.update', $food), [
            'name' => 'Bắp rang lớn',
            'price' => '50000',
            'active' => '1',
        ])->assertRedirect(route('admin.foods.index'))->assertSessionHasNoErrors();

        $this->assertSame(50_000, $updatedPriceBeforePersistence);
        $this->assertSame(50_000, (int) $food->fresh()->price);
    }

    #[DataProvider('invalidFoodVndInputs')]
    public function test_food_create_rejects_noncanonical_or_out_of_range_vnd(mixed $value): void
    {
        $response = $this->actingAs($this->userWithRole('admin'))->post(route('admin.foods.store'), [
            'name' => 'Món thử',
            'price' => $value,
            'active' => '1',
        ]);

        $response->assertSessionHasErrors('price');
        $this->assertStringContainsString('Giá món ăn', session('errors')->first('price'));
        $this->assertDatabaseCount('food_items', 0);
    }

    #[DataProvider('invalidFoodVndInputs')]
    public function test_food_update_rejects_noncanonical_or_out_of_range_vnd(mixed $value): void
    {
        $food = FoodItem::query()->create(['name' => 'Món cũ', 'price' => 50_000, 'active' => true]);

        $response = $this->actingAs($this->userWithRole('admin'))
            ->put(route('admin.foods.update', $food), [
                'name' => 'Món mới',
                'price' => $value,
                'active' => '1',
            ]);

        $response->assertSessionHasErrors('price');
        $this->assertStringContainsString('Giá món ăn', session('errors')->first('price'));
        $this->assertSame('50000.00', $food->fresh()->price);
    }

    public function test_edit_forms_render_whole_decimal_database_prices_as_canonical_integer_strings(): void
    {
        $movie = $this->movie();
        $showtime = $this->existing($movie, $this->rooms['P01'], [
            'price' => 50_000,
            'vip_price' => 75_000,
        ]);
        $food = FoodItem::query()->create([
            'name' => 'Bắp rang',
            'price' => 35_000,
            'active' => true,
        ]);
        $admin = $this->userWithRole('admin');

        $showtimeResponse = $this->actingAs($admin)->get(route('admin.showtimes.edit', $showtime));
        $showtimeResponse->assertOk();
        $this->assertInputValue($showtimeResponse, 'price', '50000');
        $this->assertInputValue($showtimeResponse, 'vip_price', '75000');

        $foodResponse = $this->get(route('admin.foods.edit', $food));
        $foodResponse->assertOk();
        $this->assertInputValue($foodResponse, 'price', '35000');
    }

    public function test_unchanged_showtime_edit_and_food_non_price_edit_succeed_with_canonical_form_values(): void
    {
        $movie = $this->movie();
        $room = $this->rooms['P01'];
        $showtime = $this->existing($movie, $room, [
            'price' => 50_000,
            'vip_price' => 75_000,
        ]);
        $food = FoodItem::query()->create([
            'name' => 'Bắp rang',
            'price' => 35_000,
            'active' => true,
        ]);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->put(route('admin.showtimes.update', $showtime), $this->payload($movie, $room, [
                'price' => '50000',
                'vip_price' => '75000',
            ]))
            ->assertRedirect(route('admin.showtimes.index'))
            ->assertSessionHasNoErrors();

        $this->put(route('admin.foods.update', $food), [
            'name' => 'Bắp rang bơ',
            'price' => '35000',
            'active' => '1',
        ])->assertRedirect(route('admin.foods.index'))->assertSessionHasNoErrors();

        $this->assertSame('50000.00', $showtime->fresh()->price);
        $this->assertSame('75000.00', $showtime->fresh()->vip_price);
        $this->assertSame('Bắp rang bơ', $food->fresh()->name);
        $this->assertSame('35000.00', $food->fresh()->price);
    }

    public function test_old_invalid_price_takes_precedence_and_remains_visible_after_validation_failure(): void
    {
        $movie = $this->movie();
        $room = $this->rooms['P01'];
        $showtime = $this->existing($movie, $room, [
            'price' => 50_000,
            'vip_price' => 75_000,
        ]);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->from(route('admin.showtimes.edit', $showtime))
            ->put(route('admin.showtimes.update', $showtime), $this->payload($movie, $room, [
                'price' => '50000.5',
                'vip_price' => '75000',
            ]))
            ->assertRedirect(route('admin.showtimes.edit', $showtime))
            ->assertSessionHasErrors('price');

        $response = $this->get(route('admin.showtimes.edit', $showtime));
        $response->assertOk();
        $this->assertInputValue($response, 'price', '50000.5');
    }

    public function test_fractional_stored_values_fail_closed_instead_of_rendering_lower_integers(): void
    {
        $movie = $this->movie();
        $showtime = $this->existing($movie, $this->rooms['P01']);
        $food = FoodItem::query()->create([
            'name' => 'Bắp rang',
            'price' => 35_000,
            'active' => true,
        ]);
        DB::table('showtimes')->where('id', $showtime->id)->update(['price' => '50000.50']);
        DB::table('food_items')->where('id', $food->id)->update(['price' => '35000.50']);
        $this->actingAs($this->userWithRole('admin'))->withoutExceptionHandling();

        foreach ([
            route('admin.showtimes.edit', $showtime),
            route('admin.foods.edit', $food),
        ] as $uri) {
            try {
                $this->get($uri);
                $this->fail("Fractional stored VND unexpectedly rendered at {$uri}.");
            } catch (Throwable $exception) {
                $messages = $exception->getMessage();
                while ($exception = $exception->getPrevious()) {
                    $messages .= ' '.$exception->getMessage();
                }

                $this->assertStringContainsString('VND', $messages);
                $this->assertStringContainsString('database amount', $messages);
            }
        }
    }

    public function test_create_forms_keep_price_inputs_blank(): void
    {
        $admin = $this->userWithRole('admin');

        $showtimeResponse = $this->actingAs($admin)->get(route('admin.showtimes.create'));
        $showtimeResponse->assertOk();
        $this->assertInputValue($showtimeResponse, 'price', '');
        $this->assertInputValue($showtimeResponse, 'vip_price', '');

        $foodResponse = $this->get(route('admin.foods.create'));
        $foodResponse->assertOk();
        $this->assertInputValue($foodResponse, 'price', '');
    }

    public static function invalidShowtimeVndInputs(): array
    {
        return [
            'whole decimal string' => ['50000.00'],
            'fractional decimal string' => ['50000.5'],
            'comma decimal' => ['50000,5'],
            'float' => [50_000.0],
            'scientific notation string' => ['5e4'],
            'negative integer' => [-1],
            'negative string' => ['-1'],
            'explicit plus sign' => ['+50000'],
            'leading zero string' => ['050000'],
            'NaN' => ['NaN'],
            'infinity' => ['INF'],
            'database overflow' => [(string) (Showtime::MAX_PRICE + 1)],
            'application overflow' => ['999999999999999999999999999999999999'],
        ];
    }

    public static function invalidFoodVndInputs(): array
    {
        return [
            ...self::invalidShowtimeVndInputs(),
            'food database overflow' => [(string) (FoodItem::MAX_PRICE + 1)],
        ];
    }

    private function assertInputValue(TestResponse $response, string $name, string $value): void
    {
        $this->assertMatchesRegularExpression(
            '/<input\b[^>]*\bname="'.preg_quote($name, '/').'"[^>]*\bvalue="'.preg_quote($value, '/').'"[^>]*>/i',
            (string) $response->getContent()
        );
    }
}
