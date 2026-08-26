<?php

namespace Tests\Feature\Showtimes;

use App\Domain\Payments\VerifiedPaymentData;
use App\Models\BookingSeat;
use App\Models\Payment;
use App\Models\RefundCase;
use App\Models\ShowtimeCancellation;
use App\Models\ShowtimeCancellationImpact;
use App\Services\Payments\VerifiedPaymentService;
use App\Services\ShowtimeCancellationService;
use App\Services\Tickets\BookingTicketEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

final class ShowtimeCancellationDomainTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_mixed_cancellation_is_atomic_preserves_payment_truth_and_creates_one_impact_per_booking(): void
    {
        $scenario = $this->bookingScenario();
        $manager = $this->userWithRole('manager');
        $unpaid = $this->reserve($scenario, [$scenario['seats'][0]->id], $manager->id)->booking;
        $pendingPayment = $this->payment($unpaid, Payment::STATUS_PENDING);
        $paid = $this->reserve($scenario, [$scenario['seats'][2]->id, $scenario['seats'][3]->id], $manager->id)->booking;
        $paid->forceFill(['booking_status' => 'paid', 'payment_status' => 'paid', 'paid_at' => now()])->save();
        $successfulPayment = $this->payment($paid, Payment::STATUS_SUCCESS, ['verified_at' => now(), 'paid_at' => now()]);
        $printedTicket = $paid->admissionTickets()->oldest('id')->firstOrFail();
        $printedTicket->forceFill(['print_count' => 2, 'last_printed_at' => now()])->save();

        $this->actingAs($manager);
        $cancellation = app(ShowtimeCancellationService::class)->cancel(
            $scenario['showtime'],
            $manager,
            'technical_issue',
            'Máy chiếu không thể vận hành an toàn.',
        );

        $this->assertSame('cancelled', $scenario['showtime']->fresh()->status);
        $this->assertSame(ShowtimeCancellation::STATUS_OPEN, $cancellation->fresh()->status);
        $this->assertDatabaseCount('showtime_cancellation_impacts', 2);
        $this->assertDatabaseHas('showtime_cancellation_impacts', [
            'booking_id' => $unpaid->id,
            'outcome' => ShowtimeCancellationImpact::OUTCOME_UNPAID_CANCELLED,
            'authoritative_amount' => 0,
        ]);
        $this->assertDatabaseHas('showtime_cancellation_impacts', [
            'booking_id' => $paid->id,
            'outcome' => ShowtimeCancellationImpact::OUTCOME_REFUND_REQUIRED,
            'authoritative_amount' => $successfulPayment->amount,
        ]);
        $this->assertSame('cancelled', $unpaid->fresh()->booking_status);
        $this->assertSame('unpaid', $unpaid->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_PENDING, $pendingPayment->fresh()->status);
        $this->assertSame('cancelled', $paid->fresh()->booking_status);
        $this->assertSame('paid', $paid->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_SUCCESS, $successfulPayment->fresh()->status);
        $this->assertSame(2, $printedTicket->fresh()->print_count);
        $this->assertFalse(app(BookingTicketEligibility::class)->isUsable($paid->fresh(['payments'])));
        $this->assertFalse(app(BookingTicketEligibility::class)->isPrintable($paid->fresh(['payments'])));
        $this->assertSame(0, BookingSeat::query()->whereIn('booking_id', [$unpaid->id, $paid->id])->whereNotNull('active_lock_key')->count());
        $refund = RefundCase::query()->sole();
        $this->assertSame($paid->id, $refund->booking_id);
        $this->assertSame($successfulPayment->id, $refund->payment_id);
        $this->assertSame((int) $successfulPayment->amount, $refund->required_amount);

        $duplicate = app(ShowtimeCancellationService::class)->cancel($scenario['showtime'], $manager, 'other', 'duplicate');
        $this->assertSame($cancellation->id, $duplicate->id);
        $this->assertDatabaseCount('showtime_cancellations', 1);
        $this->assertDatabaseCount('refund_cases', 1);
    }

    public function test_late_authoritative_success_stays_cancelled_and_creates_an_idempotent_refund_obligation(): void
    {
        $scenario = $this->bookingScenario(false);
        $manager = $this->userWithRole('manager');
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id], $manager->id)->booking;
        $payment = $this->payment($booking, Payment::STATUS_PENDING);
        $this->actingAs($manager);
        app(ShowtimeCancellationService::class)->cancel($scenario['showtime'], $manager, 'safety_issue', null);

        $data = new VerifiedPaymentData(
            provider: 'vnpay',
            merchantReference: $payment->order_code,
            amount: (int) $payment->amount,
            providerTransactionId: 'VNP-LATE-10001',
            source: 'ipn',
            payloadHash: hash('sha256', 'late-success'),
            responseCode: '00',
            transactionStatus: '00',
        );
        $result = app(VerifiedPaymentService::class)->verify($payment, $data);

        $this->assertTrue($result->accepted);
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertSame('cancelled', $booking->fresh()->booking_status);
        $this->assertSame('paid', $booking->fresh()->payment_status);
        $this->assertDatabaseHas('showtime_cancellation_impacts', [
            'booking_id' => $booking->id,
            'outcome' => ShowtimeCancellationImpact::OUTCOME_REFUND_REQUIRED,
            'authoritative_amount' => $payment->amount,
        ]);
        $this->assertDatabaseHas('refund_cases', [
            'booking_id' => $booking->id,
            'payment_id' => $payment->id,
            'status' => RefundCase::STATUS_REQUIRED,
            'required_amount' => $payment->amount,
        ]);

        $duplicate = app(VerifiedPaymentService::class)->verify($payment->fresh(), $data);
        $this->assertTrue($duplicate->accepted);
        $this->assertFalse($duplicate->transitioned);
        $this->assertDatabaseCount('refund_cases', 1);
    }

    private function payment($booking, string $status, array $overrides = []): Payment
    {
        return Payment::createForProvider('vnpay', [
            'booking_id' => $booking->id,
            'payment_method' => 'vnpay',
            'order_code' => 'SHOW-CANCEL-'.str()->upper(str()->random(14)),
            'amount' => (int) $booking->total_amount,
            'currency' => 'VND',
            'status' => $status,
            'expires_at' => now()->addMinutes(15),
            'reconcile_until' => now()->addDay(),
            ...$overrides,
        ]);
    }
}
