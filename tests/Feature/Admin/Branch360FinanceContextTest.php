<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Cinema;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Showtime;
use App\Models\User;
use App\Models\UserCinemaAssignment;
use App\Services\Admin\Branch360ReadModel;
use App\Services\CinemaAccessService;
use App\Services\Reports\AdminReportingService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Payments\PaymentTestCase;

final class Branch360FinanceContextTest extends PaymentTestCase
{
    private array $scenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->startSession();
        request()->setLaravelSession($this->app['session']->driver());
        $this->seedRbac();
        $now = CarbonImmutable::parse('2026-08-13 12:00:00', 'Asia/Ho_Chi_Minh')->utc();
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
        $this->scenario = $this->bookingScenario(false);
        $this->scenario['cinema']->update(['timezone' => 'Asia/Ho_Chi_Minh']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_finance_contract_counts_authoritative_bookings_and_rejects_non_authoritative_attempts(): void
    {
        $manager = $this->userWithRole('manager');
        $settler = $this->userWithRole('staff');
        $snapshot = $this->snapshot($manager);
        $empty = $snapshot['finance'];

        $this->assertSame(0, $empty['collectedAmount']);
        $this->assertSame(0, $empty['paidBookingCount']);
        $this->assertSame('2026-08-13', $empty['localDate']);
        $this->assertEquals($empty['generatedAt'], $snapshot['header']['generatedAt']);
        $this->assertSame(
            route('admin.reports.index', ['from' => '2026-08-13', 'to' => '2026-08-13', 'cinema' => $this->scenario['cinema']->id]),
            $empty['reportUrl'],
        );

        $online = $this->paidBooking($this->scenario, 100_000);
        $cash = $this->paidBooking($this->scenario, 50_000, Booking::SALES_CHANNEL_COUNTER, $settler);
        $zero = $this->paidBooking($this->scenario, 0);
        $this->payment($online, 'vnpay', Payment::STATUS_FAILED, 100_000, verifiedAt: now()->subMinute());
        $this->payment($online, 'vnpay', Payment::STATUS_SUCCESS, 100_000, verifiedAt: now());
        $this->payment($cash, Payment::PROVIDER_COUNTER_CASH, Payment::STATUS_SUCCESS, 50_000, settledAt: now(), settler: $settler);
        $this->payment($zero, Payment::PROVIDER_INTERNAL_ZERO, Payment::STATUS_SUCCESS, 0, verifiedAt: now());

        $invalid = [
            $this->payment($this->paidBooking($this->scenario, 80_000), 'vnpay', Payment::STATUS_SUCCESS, 80_000),
            $this->payment($this->paidBooking($this->scenario, 60_000, Booking::SALES_CHANNEL_COUNTER, $settler), Payment::PROVIDER_COUNTER_CASH, Payment::STATUS_SUCCESS, 60_000),
            $this->payment($this->paidBooking($this->scenario, 0), Payment::PROVIDER_INTERNAL_ZERO, Payment::STATUS_SUCCESS, 0),
            $this->payment($this->paidBooking($this->scenario, 0), Payment::PROVIDER_INTERNAL_ZERO, Payment::STATUS_FAILED, 0, verifiedAt: now()),
            $this->payment($this->paidBooking($this->scenario, 0), Payment::PROVIDER_INTERNAL_ZERO, Payment::STATUS_PROCESSING, 0, verifiedAt: now()),
            $this->payment($this->paidBooking($this->scenario, 0), Payment::PROVIDER_INTERNAL_ZERO, Payment::STATUS_REVIEW, 0, verifiedAt: now()),
            $this->payment($this->paidBooking($this->scenario, 0), Payment::PROVIDER_INTERNAL_ZERO, Payment::STATUS_UNRESOLVED, 0, verifiedAt: now()),
        ];

        $finance = $this->snapshot($manager)['finance'];

        $this->assertSame(3, $finance['paidBookingCount']);
        $this->assertSame(150_000, $finance['collectedAmount']);
        $this->assertSame(
            ['localDate', 'collectedAmount', 'paidBookingCount', 'reportUrl', 'generatedAt'],
            array_keys($finance),
        );
        $this->assertCount(7, $invalid);
        $encoded = json_encode($finance, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('customer', $encoded);
        $this->assertStringNotContainsString('provider', $encoded);
        $this->assertStringNotContainsString('payment', $encoded);
    }

    public function test_internal_zero_only_is_visible_and_refunded_semantics_remain_the_frozen_reporting_behavior(): void
    {
        $manager = $this->userWithRole('manager');
        $zero = $this->paidBooking($this->scenario, 0);
        $this->payment($zero, Payment::PROVIDER_INTERNAL_ZERO, Payment::STATUS_SUCCESS, 0, verifiedAt: now());

        $finance = $this->snapshot($manager)['finance'];
        $this->assertSame(1, $finance['paidBookingCount']);
        $this->assertSame(0, $finance['collectedAmount']);

        $refunded = $this->paidBooking($this->scenario, 70_000);
        $this->payment($refunded, 'zalopay', Payment::STATUS_SUCCESS, 70_000, verifiedAt: now());
        $refunded->forceFill(['payment_status' => 'refunded'])->save();

        $finance = $this->snapshot($manager)['finance'];
        $this->assertSame(2, $finance['paidBookingCount']);
        $this->assertSame(70_000, $finance['collectedAmount']);
    }

    public function test_cinema_local_half_open_day_includes_start_and_excludes_next_midnight(): void
    {
        $manager = $this->userWithRole('manager');
        $start = CarbonImmutable::parse('2026-08-13 00:00:00', 'Asia/Ho_Chi_Minh')->utc();
        $nextStart = CarbonImmutable::parse('2026-08-14 00:00:00', 'Asia/Ho_Chi_Minh')->utc();

        foreach ([
            [$start, 10_000],
            [$nextStart->subSecond(), 20_000],
            [$start->subSecond(), 40_000],
            [$nextStart, 80_000],
        ] as [$verifiedAt, $amount]) {
            $booking = $this->paidBooking($this->scenario, $amount);
            $this->payment($booking, 'vnpay', Payment::STATUS_SUCCESS, $amount, verifiedAt: $verifiedAt);
        }

        $finance = $this->snapshot($manager)['finance'];

        $this->assertSame('Asia/Ho_Chi_Minh', $finance['generatedAt']->timezoneName);
        $this->assertSame('2026-08-13', $finance['localDate']);
        $this->assertSame(2, $finance['paidBookingCount']);
        $this->assertSame(30_000, $finance['collectedAmount']);
    }

    public function test_finance_is_branch_scoped_permission_safe_and_links_to_the_existing_filtered_report(): void
    {
        $manager = $this->userWithRole('manager');
        $admin = $this->userWithRole('admin');
        $primary = $this->paidBooking($this->scenario, 100_000);
        $this->payment($primary, 'vnpay', Payment::STATUS_SUCCESS, 100_000, verifiedAt: now());
        $foreign = $this->foreignScenario($this->scenario);
        UserCinemaAssignment::query()->create([
            'user_id' => $manager->id,
            'cinema_id' => $foreign['cinema']->id,
            'status' => UserCinemaAssignment::STATUS_ACTIVE,
            'assigned_at' => now(),
        ]);
        $foreignPaid = $this->paidBooking($foreign, 200_000);
        $foreignZero = $this->paidBooking($foreign, 0);
        $this->payment($foreignPaid, 'payos', Payment::STATUS_SUCCESS, 200_000, verifiedAt: now());
        $this->payment($foreignZero, Payment::PROVIDER_INTERNAL_ZERO, Payment::STATUS_SUCCESS, 0, verifiedAt: now());

        $primaryFinance = $this->snapshot($manager, $this->scenario['cinema'])['finance'];
        $this->assertSame(1, $primaryFinance['paidBookingCount']);
        $this->assertSame(100_000, $primaryFinance['collectedAmount']);

        $primaryResponse = $this->actingAs($manager)
            ->withSession([CinemaAccessService::SESSION_KEY => $this->scenario['cinema']->id])
            ->get(route('admin.cinemas.show', $this->scenario['cinema']))
            ->assertOk()
            ->assertSee('Tài chính hôm nay')
            ->assertSee('Tiền đã xác minh/thu hôm nay')
            ->assertSee('Đơn thanh toán hợp lệ hôm nay')
            ->assertSee('100.000 ₫')
            ->assertSee('1 đơn')
            ->assertSee($primaryFinance['reportUrl']);
        $html = mb_strtolower($primaryResponse->getContent());
        foreach (['doanh thu hôm nay', 'doanh thu suất chiếu', 'tỷ lệ lấp đầy', 'hoàn tiền hôm nay', 'vnpay share', 'payos share', 'zalopay share', 'setinterval', 'fetch(', 'type="date"'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }
        $this->actingAs($manager)->withSession([CinemaAccessService::SESSION_KEY => $this->scenario['cinema']->id])
            ->get($primaryFinance['reportUrl'])->assertOk();

        $this->actingAs($manager)->withSession([CinemaAccessService::SESSION_KEY => $foreign['cinema']->id])
            ->get(route('admin.cinemas.show', $foreign['cinema']))
            ->assertOk()->assertSee('200.000 ₫')->assertSee('2 đơn');
        $this->actingAs($admin)->withSession([CinemaAccessService::SESSION_KEY => $this->scenario['cinema']->id])
            ->get(route('admin.cinemas.show', $this->scenario['cinema']))
            ->assertOk()->assertSee('Tiền đã xác minh/thu hôm nay');

        $manager->role->permissions()->detach(Permission::query()->where('slug', 'reports.view')->value('id'));
        $manager->unsetRelation('role');
        $this->assertArrayNotHasKey('finance', $this->snapshot($manager, $this->scenario['cinema']));
        $this->actingAs($manager)->withSession([CinemaAccessService::SESSION_KEY => $this->scenario['cinema']->id])
            ->get(route('admin.cinemas.show', $this->scenario['cinema']))
            ->assertOk()->assertDontSee('Tiền đã xác minh/thu hôm nay')->assertDontSee(route('admin.reports.index'));

        $this->actingAs($this->userWithRole('staff'))->get(route('admin.cinemas.show', $this->scenario['cinema']))->assertForbidden();
        $this->actingAs($this->userWithRole('user'))->get(route('admin.cinemas.show', $this->scenario['cinema']))->assertForbidden();
    }

    public function test_finance_query_is_select_only_bounded_and_does_not_load_full_reporting_analytics(): void
    {
        $manager = $this->userWithRole('manager');
        $this->app->bind(AdminReportingService::class, fn (): never => throw new RuntimeException('Full reporting payload must not be resolved.'));
        $this->snapshot($manager);
        $zero = $this->queryCount(fn (): array => $this->snapshot($manager));
        $zeroRequest = $this->requestQueryCount($manager);

        $first = $this->paidBooking($this->scenario, 1_000);
        $this->payment($first, 'vnpay', Payment::STATUS_SUCCESS, 1_000, verifiedAt: now());
        $one = $this->queryCount(fn (): array => $this->snapshot($manager));
        $oneRequest = $this->requestQueryCount($manager);
        foreach (range(2, 100) as $index) {
            $booking = $this->paidBooking($this->scenario, $index * 1_000);
            $this->payment($booking, 'vnpay', Payment::STATUS_SUCCESS, $index * 1_000, verifiedAt: now());
        }
        $hundred = $this->queryCount(fn (): array => $this->snapshot($manager));
        $hundredRequest = $this->requestQueryCount($manager);
        foreach (range(1, 25) as $index) {
            $this->payment($first, 'vnpay', Payment::STATUS_FAILED, 1_000, verifiedAt: now()->addSeconds($index));
        }
        $attempts = $this->queryCount(fn (): array => $this->snapshot($manager));
        $attemptsRequest = $this->requestQueryCount($manager);

        $this->assertSame($zero, $one, "zero={$zero}; one={$one}; hundred={$hundred}; attempts={$attempts}");
        $this->assertSame($one, $hundred, "zero={$zero}; one={$one}; hundred={$hundred}; attempts={$attempts}");
        $this->assertSame($hundred, $attempts, "zero={$zero}; one={$one}; hundred={$hundred}; attempts={$attempts}");
        $this->assertSame($zeroRequest, $oneRequest, "zero={$zeroRequest}; one={$oneRequest}; hundred={$hundredRequest}; attempts={$attemptsRequest}");
        $this->assertSame($oneRequest, $hundredRequest, "zero={$zeroRequest}; one={$oneRequest}; hundred={$hundredRequest}; attempts={$attemptsRequest}");
        $this->assertSame($hundredRequest, $attemptsRequest, "zero={$zeroRequest}; one={$oneRequest}; hundred={$hundredRequest}; attempts={$attemptsRequest}");
        $this->assertLessThanOrEqual(27, $attemptsRequest);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->snapshot($manager);
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();
        $financeQueries = $queries->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'row_number() over')
            && str_contains(strtolower($query['query']), 'sum('));
        $this->assertCount(1, $financeQueries);
        $financeSql = strtolower((string) $financeQueries->first()['query']);
        $this->assertStringContainsString('count(*)', $financeSql);
        $this->assertStringContainsString('coalesce(sum(', $financeSql);
        foreach (['customer_email', 'customer_phone', 'movie_genre', 'genres', 'seat_incident', 'provider_response'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $financeSql);
        }
        foreach ($queries as $query) {
            $this->assertStringStartsWith('select', strtolower(ltrim($query['query'])));
        }

        if (env('REPORT_QUERY_COUNTS')) {
            fwrite(STDOUT, "PHASE5E_QUERY_COUNTS=zero:{$zero},one:{$one},hundred:{$hundred},attempts:{$attempts}".PHP_EOL);
            fwrite(STDOUT, "PHASE5E_REQUEST_QUERY_COUNTS=zero:{$zeroRequest},one:{$oneRequest},hundred:{$hundredRequest},attempts:{$attemptsRequest}".PHP_EOL);
        }
    }

    private function paidBooking(array $scenario, int $amount, string $channel = Booking::SALES_CHANNEL_ONLINE, ?User $creator = null): Booking
    {
        $booking = new Booking;
        $booking->forceFill([
            'showtime_id' => $scenario['showtime']->id,
            'booking_code' => 'B360-FIN-'.str()->upper(str()->random(12)),
            'sales_channel' => $channel,
            'created_by_staff_id' => $creator?->id,
            'customer_name' => 'Private Finance Customer',
            'customer_email' => 'private-finance@example.test',
            'customer_phone' => '0900000000',
            'total_amount' => $amount,
            'seat_subtotal' => $amount,
            'food_subtotal' => 0,
            'gross_amount' => $amount,
            'promotion_discount_amount' => 0,
            'currency' => 'VND',
            'payment_status' => 'paid',
            'booking_status' => 'paid',
            'paid_at' => now(),
            'expires_at' => null,
        ])->save();

        return $booking->fresh();
    }

    private function payment(
        Booking $booking,
        string $provider,
        string $status,
        int $amount,
        mixed $verifiedAt = null,
        mixed $settledAt = null,
        ?User $settler = null,
    ): Payment {
        $payment = new Payment;
        $payment->forceFill([
            'booking_id' => $booking->id,
            'provider' => $provider,
            'payment_method' => $provider,
            'order_code' => 'B360-FIN-'.str()->upper(str()->random(16)),
            'amount' => $amount,
            'currency' => 'VND',
            'status' => $status,
            'verified_at' => $verifiedAt,
            'settled_at' => $settledAt,
            'settled_by_user_id' => $settler?->id,
            'paid_at' => $verifiedAt ?? $settledAt,
        ])->save();

        return $payment;
    }

    private function foreignScenario(array $source): array
    {
        $cinema = Cinema::factory()->create([
            'timezone' => 'Asia/Ho_Chi_Minh',
            'status' => 'active',
            'archived_at' => null,
        ]);
        $room = Room::factory()->create(['cinema_id' => $cinema->id]);
        $format = $source['showtime']->presentationFormat()->firstOrFail();
        $room->presentationCapabilities()->attach($format);
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id,
            'version' => 1,
            'name' => 'Branch finance foreign layout',
            'rows' => 1,
            'columns' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $showtime = Showtime::query()->create([
            'movie_id' => $source['movie']->id,
            'cinema_id' => $cinema->id,
            'room_id' => $room->id,
            'room_layout_id' => $layout->id,
            'presentation_format_id' => $format->id,
            'show_date' => now()->addDays(5)->toDateString(),
            'show_time' => '21:00:00',
            'price' => 50_000,
            'vip_price' => 70_000,
            'pricing_version' => 'branch-finance-v1',
            'status' => 'active',
        ]);

        return ['cinema' => $cinema, 'room' => $room, 'movie' => $source['movie'], 'showtime' => $showtime];
    }

    private function snapshot(User $actor, ?Cinema $cinema = null): array
    {
        $cinema ??= $this->scenario['cinema'];
        $this->actingAs($actor);
        request()->session()->put(CinemaAccessService::SESSION_KEY, $cinema->id);

        return app(Branch360ReadModel::class)->snapshot($cinema->fresh(), $actor);
    }

    private function queryCount(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function requestQueryCount(User $actor): int
    {
        return $this->queryCount(fn () => $this->actingAs($actor)
            ->withSession([CinemaAccessService::SESSION_KEY => $this->scenario['cinema']->id])
            ->get(route('admin.cinemas.show', $this->scenario['cinema']))
            ->assertOk());
    }
}
