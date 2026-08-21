<?php

namespace Tests\Feature\Payments;

use App\Exceptions\PaymentInitiationException;
use App\Models\ActivityLog;
use App\Models\AdmissionTicket;
use App\Models\Booking;
use App\Models\BookingTicketDelivery;
use App\Models\BookingTicketPrint;
use App\Models\BookingTicketPrintEvent;
use App\Models\FoodPickupVoucher;
use App\Models\Payment;
use App\Models\RefundCase;
use App\Services\ActivityLogger;
use App\Services\Payments\PaymentResumeService;
use App\Services\Payments\VnpayStoredQueryEvidenceService;
use App\Support\PaymentPresentation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ViewErrorBag;
use LogicException;

final class VnpayStoredQueryEvidenceTest extends VnpayPaymentTestCase
{
    private const EVIDENCE_TXN_REF_PREFIX = 'MM260821143022EVIDENCEB';

    public function test_authenticated_stored_expiration_closes_review_and_expired_booking_without_fulfilment(): void
    {
        $payment = $this->storedReviewPayment();
        $booking = $payment->booking;
        $seat = $booking->bookingSeats()->sole();
        $before = $this->artifactCounts($booking);
        Http::fake();

        $this->artisan('payments:reconcile-vnpay-stored', ['payment' => $payment->id])
            ->expectsOutputToContain("Payment {$payment->id}: failed")
            ->assertSuccessful();

        $payment->refresh();
        $booking->refresh();
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
        $this->assertSame('vnpay_terminal_expired', $payment->failure_reason);
        $this->assertNotNull($payment->failed_at);
        $this->assertSame('00', $payment->response_code);
        $this->assertSame('08', $payment->transaction_status);
        $this->assertSame('expired', $booking->booking_status);
        $this->assertSame('unpaid', $booking->payment_status);
        $this->assertNull($seat->fresh()->active_lock_key);
        $this->assertSame([$seat->id], $booking->bookingSeats()->pluck('id')->all());
        $this->assertSame($before, $this->artifactCounts($booking));
        $this->assertSame(0, AdmissionTicket::query()->where('booking_id', $booking->id)->sum('print_count'));
        $this->assertSame(1, ActivityLog::query()
            ->where('action', 'payment.vnpay_stored_evidence_applied')
            ->where('subject_id', (string) $payment->id)
            ->count());
        $this->assertSame('Lần thanh toán VNPAY đã hết thời gian', PaymentPresentation::reason($payment->failure_reason));
        Http::assertNothingSent();
        $this->expectException(PaymentInitiationException::class);
        app(PaymentResumeService::class)->resume($booking, '127.0.0.1');
    }

    public function test_repeated_stored_reconciliation_is_idempotent(): void
    {
        $payment = $this->storedReviewPayment();
        $service = app(VnpayStoredQueryEvidenceService::class);

        $this->assertSame(Payment::STATUS_FAILED, $service->reconcileTerminalExpiration($payment));
        $booking = $payment->booking->fresh();
        $seatIds = $booking->bookingSeats()->pluck('id')->all();
        $artifactCounts = $this->artifactCounts($booking);
        $bookingUpdatedAt = $booking->updated_at;

        $this->assertSame(Payment::STATUS_FAILED, $service->reconcileTerminalExpiration($payment->fresh()));

        $this->assertSame('expired', $booking->fresh()->booking_status);
        $this->assertTrue($bookingUpdatedAt->equalTo($booking->fresh()->updated_at));
        $this->assertSame($seatIds, $booking->bookingSeats()->pluck('id')->all());
        $this->assertSame(0, $booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        $this->assertSame($artifactCounts, $this->artifactCounts($booking));
        $this->assertSame(1, ActivityLog::query()
            ->where('action', 'payment.vnpay_stored_evidence_applied')
            ->where('subject_id', (string) $payment->id)
            ->count());
    }

    public function test_future_booking_uses_existing_terminal_cancellation_policy(): void
    {
        $booking = $this->payableBooking(['expires_at' => now()->addMinutes(5)]);
        $payment = $this->storedReviewPayment($booking);

        app(VnpayStoredQueryEvidenceService::class)->reconcileTerminalExpiration($payment);

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame('cancelled', $booking->fresh()->booking_status);
        $this->assertSame(0, $booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        $this->assertSame(1, ActivityLog::query()->where('action', 'booking.payment_cancelled')->count());
    }

    public function test_stored_evidence_fixture_reference_is_deterministic_and_preserved_exactly(): void
    {
        $payment = $this->storedReviewPayment();
        $event = ActivityLog::query()
            ->where('action', 'payment.vnpay_query_attempted')
            ->where('subject_id', (string) $payment->id)
            ->sole();

        $this->assertSame(self::EVIDENCE_TXN_REF_PREFIX.$payment->booking_id, $payment->order_code);
        $this->assertSame($payment->order_code, $event->context['txn_ref']);
        $this->assertStringNotContainsString('[số điện thoại đã ẩn]', $event->context['txn_ref']);
    }

    public function test_missing_or_tampered_evidence_is_rejected_without_mutation(): void
    {
        $payment = $this->storedReviewPayment(recordEvidence: false);
        $booking = $payment->booking;

        try {
            app(VnpayStoredQueryEvidenceService::class)->reconcileTerminalExpiration($payment);
            $this->fail('Missing authenticated evidence must be rejected.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertSame('pending_payment', $booking->fresh()->booking_status);
        $this->assertSame(1, $booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        $this->assertDatabaseMissing('activity_logs', ['action' => 'payment.vnpay_stored_evidence_applied']);
    }

    public function test_stored_pending_status_remains_review_and_cannot_use_terminal_expiration_entry_point(): void
    {
        $payment = $this->storedReviewPayment(transactionStatus: '01');

        $this->artisan('payments:reconcile-vnpay-stored', ['payment' => $payment->id])
            ->expectsOutput('Stored VNPAY evidence did not pass the reconciliation guards.')
            ->assertFailed();

        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertSame('pending_payment', $payment->booking->fresh()->booking_status);
    }

    public function test_customer_booking_page_shows_terminal_expiry_instead_of_processing(): void
    {
        $payment = $this->storedReviewPayment();
        app(VnpayStoredQueryEvidenceService::class)->reconcileTerminalExpiration($payment);
        $booking = $payment->booking->fresh(['payment', 'bookingSeats.seat', 'admissionTickets', 'foodOrder.items']);
        $this->withoutVite();

        $html = view('user.bookings.success', [
            'booking' => $booking,
            'isUsable' => false,
            'verifiedPayment' => null,
            'mailDeliveryReady' => false,
            'paymentAction' => ['can_resume' => false],
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertStringContainsString('Đơn đặt vé đã hết hạn', $html);
        $this->assertStringNotContainsString('Đang xác minh kết quả thanh toán', $html);

        $returnHtml = view('payments.return', [
            'payment' => $payment->fresh(),
            'booking' => $booking,
            'integrityVerified' => true,
            'canViewTicket' => false,
            'canViewBooking' => true,
            'cancelRequested' => false,
            'errors' => new ViewErrorBag,
        ])->render();
        $this->assertStringContainsString('Lần thanh toán đã hết hạn', $returnHtml);
        $this->assertStringNotContainsString('Đang xử lý trạng thái', $returnHtml);
    }

    private function storedReviewPayment(
        ?Booking $booking = null,
        string $transactionStatus = '08',
        bool $recordEvidence = true,
    ): Payment {
        $booking ??= $this->payableBooking(['expires_at' => now()->subMinute()]);
        $payment = $this->vnpayPayment($booking, [
            'order_code' => self::EVIDENCE_TXN_REF_PREFIX.$booking->id,
            'status' => Payment::STATUS_REVIEW,
            'response_code' => '00',
            'transaction_status' => $transactionStatus,
            'query_response_hash' => str_repeat('a', 64),
            'failure_reason' => 'query_unknown_status',
        ]);

        if ($recordEvidence) {
            app(ActivityLogger::class)->log('payment.vnpay_query_attempted', $payment, context: [
                'payment_id' => $payment->id,
                'booking_id' => $booking->id,
                'provider' => 'vnpay',
                'txn_ref' => $payment->order_code,
                'http_status' => 200,
                'provider_response_code' => '00',
                'provider_transaction_status' => $transactionStatus,
                'checksum_verification' => 'match',
                'response_has_checksum' => true,
                'error_category' => 'provider_query_success',
            ]);
        }

        return $payment->load('booking.user');
    }

    /** @return array<string, int> */
    private function artifactCounts(Booking $booking): array
    {
        return [
            'admission_tickets' => AdmissionTicket::query()->where('booking_id', $booking->id)->count(),
            'ticket_prints' => BookingTicketPrint::query()->where('booking_id', $booking->id)->count(),
            'ticket_print_events' => BookingTicketPrintEvent::query()->where('booking_id', $booking->id)->count(),
            'ticket_deliveries' => BookingTicketDelivery::query()->where('booking_id', $booking->id)->count(),
            'food_vouchers' => FoodPickupVoucher::query()->where('booking_id', $booking->id)->count(),
            'refund_cases' => RefundCase::query()->where('booking_id', $booking->id)->count(),
        ];
    }
}
