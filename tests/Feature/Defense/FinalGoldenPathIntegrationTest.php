<?php

namespace Tests\Feature\Defense;

use App\Domain\Payments\VerifiedPaymentData;
use App\Models\BookingPromotion;
use App\Models\FoodItem;
use App\Models\Movie;
use App\Models\Payment;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\Seat;
use App\Models\User;
use App\Services\BookingCheckoutService;
use App\Services\BookingTokenService;
use App\Services\Payments\VerifiedPaymentService;
use App\Services\Reports\AdminReportingService;
use App\Services\Reports\AuthoritativePaymentQuery;
use App\Services\Reports\ReportScopeFactory;
use App\Services\ShowtimeScheduleService;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class FinalGoldenPathIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authoritative_cross_phase_handoff_needs_no_live_payment_provider(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-15 01:00:00', 'UTC'));
        Http::fake();
        $this->seed(DatabaseSeeder::class);

        $room = Room::query()->with(['latestPublishedLayout', 'presentationCapabilities'])
            ->where('code', 'DEMO')->sole();
        $movie = Movie::query()->where('slug', 'the-odyssey')->sole();
        $format = PresentationFormat::query()->where('code', '3D')->sole();
        $this->assertTrue($movie->supportedPresentationFormats()->whereKey($format->id)->exists());
        $this->assertTrue($room->presentationCapabilities->contains('id', $format->id));

        $showtime = app(ShowtimeScheduleService::class)->schedule([
            'movie_id' => $movie->id,
            'room_id' => $room->id,
            'presentation_format_id' => $format->id,
            'show_date' => now($room->cinema->timezone)->addDays(11)->toDateString(),
            'show_time' => '12:45',
            'status' => 'active',
        ])->load(['roomLayout', 'presentationFormat', 'ticketPrices.seatType']);

        $this->assertSame($room->latestPublishedLayout->id, $showtime->room_layout_id);
        $this->assertSame('published', $showtime->roomLayout->status);
        $this->assertSame('3D', $showtime->presentationFormat->code);
        $this->assertSame('STANDARD', $room->room_type);
        $this->assertCount(3, $showtime->ticketPrices);

        $seat = Seat::query()->where('room_id', $room->id)->where('seat_code', 'D1')->where('status', 'active')->sole();
        $food = FoodItem::query()->where('name', 'Bắp rang bơ')->where('active', true)->sole();
        $customer = User::query()->where('email', 'customer@moviemate.test')->sole();
        $booking = app(BookingCheckoutService::class)->createPendingBooking(
            $showtime->id,
            [$seat->id],
            $customer->id,
            $customer->email,
            app(BookingTokenService::class)->issueCheckoutToken(),
            [['food_id' => $food->id, 'quantity' => 1]],
            customerName: $customer->name,
            customerPhone: '0901000001',
            promotionCode: 'MOVIEMATE10',
        )->booking->load(['bookingSeats', 'foodOrder.items', 'promotionUsage']);

        $priceSnapshot = $showtime->ticketPrices->firstWhere('seat_type_id', $seat->seat_type_id);
        $bookingSeat = $booking->bookingSeats->sole();
        $this->assertSame($priceSnapshot->id, $bookingSeat->showtime_ticket_price_id);
        $this->assertSame($priceSnapshot->final_unit_amount_vnd, (int) $bookingSeat->final_unit_amount);
        $this->assertSame((int) $bookingSeat->price, (int) $booking->seat_subtotal);
        $this->assertSame(45_000, $booking->food_subtotal);
        $this->assertSame('Bắp rang bơ', $booking->foodOrder->items->sole()->snapshot_name);
        $this->assertSame('MOVIEMATE10', $booking->promotionUsage?->code_snapshot);
        $this->assertSame(BookingPromotion::STATUS_RESERVED, $booking->promotionUsage?->status);
        $this->assertSame((int) $booking->gross_amount - (int) $booking->promotion_discount_amount, (int) $booking->total_amount);

        $payment = Payment::createForProvider('vnpay', [
            'booking_id' => $booking->id,
            'payment_method' => 'vnpay',
            'order_code' => 'GOLDEN-'.$booking->booking_code,
            'amount' => (int) $booking->total_amount,
            'currency' => 'VND',
            'status' => Payment::STATUS_PENDING,
        ]);
        $result = app(VerifiedPaymentService::class)->verify($payment, new VerifiedPaymentData(
            provider: 'vnpay',
            merchantReference: $payment->order_code,
            amount: (int) $booking->total_amount,
            providerTransactionId: 'GOLDEN-VERIFIED-TRANSACTION',
            source: 'query',
            payloadHash: hash('sha256', 'golden-path-provider-query'),
            responseCode: '00',
            transactionStatus: '00',
            providerPaidAt: now(),
        ));

        $this->assertTrue($result->accepted);
        $this->assertTrue($result->transitioned);
        $booking->refresh()->load([
            'payments', 'promotionUsage', 'admissionTickets', 'foodOrder.items', 'foodPickupVoucher',
        ]);
        $payment->refresh();
        $this->assertSame('paid', $booking->booking_status);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame(BookingPromotion::STATUS_REDEEMED, $booking->promotionUsage?->status);
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->status);
        $this->assertNotNull($payment->verified_at);
        $this->assertNull($payment->settled_at);
        $this->assertSame((int) $booking->total_amount, $payment->amount);
        $this->assertCount(1, $booking->admissionTickets);
        $this->assertSame(0, $booking->admissionTickets->sole()->print_count);
        $this->assertNotNull($booking->foodPickupVoucher);
        $this->assertSame(0, $booking->foodPickupVoucher->print_count);

        $authoritative = app(AuthoritativePaymentQuery::class)->authoritative()
            ->where('payment_id', $payment->id)->sole();
        $this->assertSame($booking->id, $authoritative->booking_id);
        $admin = User::query()->where('email', 'admin@moviemate.test')->sole();
        $reportDate = $payment->verified_at->setTimezone($room->cinema->timezone)->toDateString();
        $scope = app(ReportScopeFactory::class)->forUser($admin, [
            'from' => $reportDate,
            'to' => $reportDate,
            'cinema' => 'CG',
            'provider' => 'vnpay',
        ]);
        $report = app(AdminReportingService::class)->report($scope);
        $this->assertGreaterThanOrEqual($payment->amount, $report['summary']['revenue']);
        $this->assertGreaterThanOrEqual(1, $report['summary']['paidBookings']);
        $this->assertGreaterThanOrEqual($payment->amount, collect($report['paymentMethods'])->firstWhere('key', 'vnpay')['revenue']);

        Http::assertNothingSent();
    }
}
