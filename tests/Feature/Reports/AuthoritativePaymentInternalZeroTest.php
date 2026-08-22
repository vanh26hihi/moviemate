<?php

namespace Tests\Feature\Reports;

use App\Models\Booking;
use App\Models\Cinema;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Showtime;
use App\Models\User;
use App\Services\Admin\AdminBookingQuery;
use App\Services\Admin\AdminPaymentQuery;
use App\Services\BookingTokenService;
use App\Services\Reports\AdminReportingService;
use App\Services\Reports\AuthoritativePaymentQuery;
use App\Services\Reports\ReportScopeFactory;
use App\Services\UnifiedBookingCheckoutService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Payments\PaymentTestCase;

final class AuthoritativePaymentInternalZeroTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
        $now = CarbonImmutable::parse('2026-08-06 17:30:00', 'UTC');
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_authoritative_selection_matches_the_frozen_paid_evidence_matrix(): void
    {
        $scenario = $this->bookingScenario(false);
        $settler = $this->userWithRole('staff');
        $online = $this->paidBooking($scenario, 100_000);
        $cash = $this->paidBooking($scenario, 50_000, Booking::SALES_CHANNEL_COUNTER, $settler);
        $zero = $this->paidBooking($scenario, 0);

        $onlineEvidence = $this->payment($online, 'vnpay', Payment::STATUS_SUCCESS, 100_000, verifiedAt: now()->subMinutes(3));
        $duplicate = $this->payment($online, 'payos', Payment::STATUS_SUCCESS, 999_999, verifiedAt: now()->subMinute());
        $cashEvidence = $this->payment($cash, Payment::PROVIDER_COUNTER_CASH, Payment::STATUS_SUCCESS, 50_000, settledAt: now(), settler: $settler);
        $zeroEvidence = $this->payment($zero, Payment::PROVIDER_INTERNAL_ZERO, Payment::STATUS_SUCCESS, 0, verifiedAt: now());

        $invalid = [
            $this->payment($this->paidBooking($scenario, 0), Payment::PROVIDER_INTERNAL_ZERO, Payment::STATUS_SUCCESS, 0),
            $this->payment($this->paidBooking($scenario, 80_000), 'vnpay', Payment::STATUS_SUCCESS, 80_000),
            $this->payment($this->paidBooking($scenario, 60_000, Booking::SALES_CHANNEL_COUNTER, $settler), Payment::PROVIDER_COUNTER_CASH, Payment::STATUS_SUCCESS, 60_000),
            $this->payment($this->paidBooking($scenario, 0), Payment::PROVIDER_INTERNAL_ZERO, Payment::STATUS_PENDING, 0, verifiedAt: now()),
            $this->payment($this->paidBooking($scenario, 0), Payment::PROVIDER_INTERNAL_ZERO, Payment::STATUS_FAILED, 0, verifiedAt: now()),
            $this->payment($this->paidBooking($scenario, 0), Payment::PROVIDER_INTERNAL_ZERO, Payment::STATUS_EXPIRED, 0, verifiedAt: now()),
            $this->payment($this->paidBooking($scenario, 0), Payment::PROVIDER_INTERNAL_ZERO, Payment::STATUS_PROCESSING, 0, verifiedAt: now()),
            $this->payment($this->paidBooking($scenario, 0), Payment::PROVIDER_INTERNAL_ZERO, Payment::STATUS_REVIEW, 0, verifiedAt: now()),
            $this->payment($this->paidBooking($scenario, 0), Payment::PROVIDER_INTERNAL_ZERO, Payment::STATUS_UNRESOLVED, 0, verifiedAt: now()),
        ];

        $refundedBooking = $this->paidBooking($scenario, 40_000);
        $refundedEvidence = $this->payment($refundedBooking, 'zalopay', Payment::STATUS_SUCCESS, 40_000, verifiedAt: now());
        $refundedBooking->forceFill(['payment_status' => 'refunded'])->save();

        $selected = app(AuthoritativePaymentQuery::class)->authoritative()
            ->orderBy('payment_id')->pluck('payment_id')->map(fn ($id): int => (int) $id)->all();

        $this->assertEqualsCanonicalizing(
            [$onlineEvidence->id, $cashEvidence->id, $zeroEvidence->id, $refundedEvidence->id],
            $selected,
        );
        $this->assertNotContains($duplicate->id, $selected);
        foreach ($invalid as $payment) {
            $this->assertNotContains($payment->id, $selected);
        }
    }

    public function test_paid_counts_include_internal_zero_once_while_money_and_provider_charts_stay_exact(): void
    {
        $scenario = $this->bookingScenario(false);
        $admin = $this->userWithRole('admin');
        $settler = $this->userWithRole('staff');
        $paidAt = CarbonImmutable::parse('2026-08-03 03:00:00', 'UTC');

        $online = $this->paidBooking($scenario, 100_000);
        $cash = $this->paidBooking($scenario, 50_000, Booking::SALES_CHANNEL_COUNTER, $settler);
        $zero = $this->paidBooking($scenario, 0);
        $this->payment($online, 'vnpay', Payment::STATUS_SUCCESS, 100_000, verifiedAt: $paidAt);
        $this->payment($online, 'payos', Payment::STATUS_FAILED, 100_000, verifiedAt: $paidAt->addMinute());
        $this->payment($cash, Payment::PROVIDER_COUNTER_CASH, Payment::STATUS_SUCCESS, 50_000, settledAt: $paidAt, settler: $settler);
        $this->payment($zero, Payment::PROVIDER_INTERNAL_ZERO, Payment::STATUS_SUCCESS, 0, verifiedAt: $paidAt);

        $report = $this->report($admin, '2026-08-03', '2026-08-03', (string) $scenario['cinema']->id);
        $providers = collect($report['paymentMethods'])->keyBy('key');

        $this->assertSame(3, $report['summary']['paidBookings']);
        $this->assertSame(150_000, $report['summary']['revenue']);
        $this->assertSame(3, $report['revenueSeries'][0]['transactions']);
        $this->assertSame(150_000, $report['revenueSeries'][0]['revenue']);
        $this->assertSame(1, $providers['vnpay']['transactions']);
        $this->assertSame(1, $providers[Payment::PROVIDER_COUNTER_CASH]['transactions']);
        $this->assertSame(0, $providers['payos']['transactions']);
        $this->assertSame(0, $providers['zalopay']['transactions']);
        $this->assertArrayNotHasKey(Payment::PROVIDER_INTERNAL_ZERO, $providers->all());
    }

    public function test_official_zero_payable_flow_uses_verified_at_local_date_and_respects_branch_scope(): void
    {
        Http::fake();
        $scenario = $this->bookingScenario(false);
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('manager');
        Promotion::query()->create([
            'code' => 'REPORTFREE',
            'name' => 'Reporting full promotion',
            'type' => Promotion::TYPE_FIXED,
            'discount_amount_vnd' => 1_000_000,
            'minimum_order_vnd' => 0,
        ]);
        $result = app(UnifiedBookingCheckoutService::class)->confirm([
            'showtime_id' => $scenario['showtime']->id,
            'seat_ids' => [$scenario['seats'][0]->id],
            'customer_email' => 'report-zero@example.test',
            'checkout_token' => app(BookingTokenService::class)->issueCheckoutToken(),
            'food_items' => [],
            'promotion_code' => 'REPORTFREE',
        ], null, 'vnpay');
        $payment = $result->payment?->fresh();

        $foreign = $this->foreignScenario($scenario);
        $foreignZero = $this->paidBooking($foreign, 0);
        $this->payment($foreignZero, Payment::PROVIDER_INTERNAL_ZERO, Payment::STATUS_SUCCESS, 0, verifiedAt: now());

        $primary = $this->report($admin, '2026-08-07', '2026-08-07', (string) $scenario['cinema']->id);
        $all = $this->report($admin, '2026-08-07', '2026-08-07', 'all');
        $previousDay = $this->report($admin, '2026-08-06', '2026-08-06', (string) $scenario['cinema']->id);

        $this->assertSame(Payment::PROVIDER_INTERNAL_ZERO, $payment?->provider);
        $this->assertSame(Payment::STATUS_SUCCESS, $payment?->status);
        $this->assertSame(0, $payment?->amount);
        $this->assertNotNull($payment?->verified_at);
        $this->assertSame('paid', $result->checkout->booking->fresh()->payment_status);
        $this->assertSame('paid', $result->checkout->booking->fresh()->booking_status);
        $this->assertSame(1, $primary['summary']['paidBookings']);
        $this->assertSame(0, $primary['summary']['revenue']);
        $this->assertSame(1, $primary['revenueSeries'][0]['transactions']);
        $this->assertSame(0, $primary['revenueSeries'][0]['revenue']);
        $this->assertSame(0, $previousDay['summary']['paidBookings']);
        $this->assertSame(2, $all['summary']['paidBookings']);

        $this->actingAs($manager);
        $session = app('session')->driver();
        $session->start();
        request()->setLaravelSession($session);
        $this->assertSame(['total' => 1, 'revenue' => 0, 'seats' => 1], app(AdminBookingQuery::class)->summary([]));
        $this->assertSame(['total' => 1, 'revenue' => 0, 'online' => 1, 'counter' => 0], app(AdminPaymentQuery::class)->summary([]));
        $showDate = substr((string) $scenario['showtime']->show_date, 0, 10);
        $this->assertSame(1, $this->report($manager, $showDate, $showDate, 'all')['ticketOperations']['eligible']);
        Http::assertNothingSent();
    }

    /** @param array<string, mixed> $scenario */
    private function paidBooking(array $scenario, int $amount, string $channel = Booking::SALES_CHANNEL_ONLINE, ?User $creator = null): Booking
    {
        $booking = new Booking;
        $booking->forceFill([
            'showtime_id' => $scenario['showtime']->id,
            'booking_code' => 'R5E0-'.str()->upper(str()->random(12)),
            'sales_channel' => $channel,
            'created_by_staff_id' => $creator?->id,
            'customer_email' => 'phase5e0@example.test',
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
            'order_code' => 'R5E0-'.str()->upper(str()->random(16)),
            'amount' => $amount,
            'currency' => 'VND',
            'status' => $status,
            'verified_at' => $verifiedAt,
            'paid_at' => $verifiedAt ?? $settledAt,
            'settled_at' => $settledAt,
            'settled_by_user_id' => $settler?->id,
        ])->save();

        return $payment;
    }

    /** @param array<string, mixed> $source */
    private function foreignScenario(array $source): array
    {
        $cinema = Cinema::factory()->create(['timezone' => 'Asia/Ho_Chi_Minh']);
        $room = Room::factory()->create(['cinema_id' => $cinema->id]);
        $format = $source['showtime']->presentationFormat;
        $room->presentationCapabilities()->attach($format);
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id,
            'version' => 1,
            'name' => 'Phase 5E0 foreign layout',
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
            'pricing_version' => 'phase5e0-v1',
            'status' => 'active',
        ]);

        return ['cinema' => $cinema, 'room' => $room, 'movie' => $source['movie'], 'showtime' => $showtime];
    }

    /** @return array<string, mixed> */
    private function report(User $user, string $from, string $to, string $cinema): array
    {
        $this->actingAs($user);
        $scope = app(ReportScopeFactory::class)->forUser($user, compact('from', 'to', 'cinema'));

        return app(AdminReportingService::class)->report($scope);
    }
}
