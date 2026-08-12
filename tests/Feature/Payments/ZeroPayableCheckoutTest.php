<?php

namespace Tests\Feature\Payments;

use App\Models\DiscountCode;
use App\Models\Payment;
use App\Services\BookingTokenService;
use App\Services\UnifiedBookingCheckoutService;
use Illuminate\Support\Facades\Http;

final class ZeroPayableCheckoutTest extends PaymentTestCase
{
    public function test_full_promotion_settles_internally_without_calling_an_external_provider(): void
    {
        Http::fake();
        $scenario = $this->bookingScenario(false);
        DiscountCode::query()->create([
            'code' => 'FREEORDER',
            'name' => 'Full promotion',
            'discount_type' => 'fixed',
            'discount_value' => 1_000_000,
        ]);
        $draft = [
            'showtime_id' => $scenario['showtime']->id,
            'seat_ids' => [$scenario['seats'][0]->id],
            'customer_email' => 'zero@example.test',
            'checkout_token' => app(BookingTokenService::class)->issueCheckoutToken(),
            'food_items' => [],
            'discount_codes' => ['FREEORDER'],
        ];

        $result = app(UnifiedBookingCheckoutService::class)->confirm($draft, null, 'vnpay');
        $booking = $result->checkout->booking->fresh();

        $this->assertSame(0, (int) $booking->total_amount);
        $this->assertSame((int) $booking->gross_amount, $booking->promotion_discount_amount);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('paid', $booking->booking_status);
        $this->assertSame(Payment::PROVIDER_INTERNAL_ZERO, $result->payment?->provider);
        $this->assertSame(Payment::STATUS_SUCCESS, $result->payment?->status);
        $this->assertSame(0, $result->payment?->amount);
        $this->assertNull($result->orderUrl);
        $this->assertDatabaseMissing('payments', ['booking_id' => $booking->id, 'provider' => 'vnpay']);
        Http::assertNothingSent();
    }
}
