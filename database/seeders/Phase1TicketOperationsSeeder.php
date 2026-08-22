<?php

namespace Database\Seeders;

use App\Domain\Payments\VerifiedPaymentData;
use App\Models\Booking;
use App\Models\FoodItem;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use App\Services\BookingCheckoutService;
use App\Services\BookingFoodService;
use App\Services\BookingTokenService;
use App\Services\Payments\VerifiedPaymentService;
use App\Services\PromotionService;
use App\Services\Tickets\TicketArtifactProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class Phase1TicketOperationsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $room = Room::query()->where('code', 'DEMO')->firstOrFail();
        $showtimes = Showtime::query()
            ->where('room_id', $room->id)
            ->where('status', 'active')
            ->whereDate('show_date', '>=', today())
            ->orderBy('show_date')
            ->orderBy('show_time')
            ->limit(4)
            ->get();
        if ($showtimes->count() !== 4) {
            throw new RuntimeException('Phase 1 demo fixtures require four active DEMO-room showtimes.');
        }

        $seats = Seat::query()->where('room_id', $room->id)
            ->whereIn('seat_code', ['A1', 'A2', 'C1', 'C2', 'D1'])
            ->get()
            ->keyBy('seat_code');
        foreach (['A1', 'A2', 'C1', 'C2', 'D1'] as $seatCode) {
            if (! $seats->has($seatCode)) {
                throw new RuntimeException("Phase 1 demo seat {$seatCode} is missing.");
            }
        }

        $customer = User::query()->where('email', 'customer@moviemate.test')->firstOrFail();
        $staff = User::query()
            ->where('email', 'staff.'.mb_strtolower($room->cinema->code).'@moviemate.test')
            ->whereHas('cinemaAssignments', fn ($query) => $query
                ->where('cinema_id', $room->cinema_id)
                ->where('status', 'active'))
            ->firstOrFail();
        $food = FoodItem::query()->where('active', true)->orderBy('id')->firstOrFail();
        $checkout = app(BookingCheckoutService::class);
        $tokens = app(BookingTokenService::class);

        $this->createPaidFixture(
            $checkout,
            $tokens,
            $showtimes[0],
            [$seats['A1']->id],
            'MMT-'.now()->format('Y').'-0000000000000001',
            null,
            Booking::SALES_CHANNEL_COUNTER,
            $staff,
        );
        $this->createPaidFixture(
            $checkout,
            $tokens,
            $showtimes[1],
            [$seats['A1']->id, $seats['A2']->id],
            'MMT-'.now()->format('Y').'-0000000000000002',
            $customer,
        );
        $this->createPaidFixture(
            $checkout,
            $tokens,
            $showtimes[2],
            [$seats['C1']->id, $seats['C2']->id],
            'MMT-'.now()->format('Y').'-0000000000000003',
            $customer,
        );
        $this->createPaidFixture(
            $checkout,
            $tokens,
            $showtimes[3],
            [$seats['D1']->id],
            'MMT-'.now()->format('Y').'-0000000000000004',
            $customer,
            foodSelection: [['food_id' => $food->id, 'quantity' => 2]],
            promotionCode: 'MOVIEMATE10',
        );
    }

    /** @param list<int> $seatIds */
    private function createPaidFixture(
        BookingCheckoutService $checkout,
        BookingTokenService $tokens,
        Showtime $showtime,
        array $seatIds,
        string $bookingCode,
        ?User $customer,
        string $salesChannel = Booking::SALES_CHANNEL_ONLINE,
        ?User $counterActor = null,
        ?array $foodSelection = null,
        ?string $promotionCode = null,
    ): void {
        $booking = $checkout->createPendingBooking(
            $showtime->id,
            $seatIds,
            $customer?->id,
            $customer?->email ?? 'counter-demo@moviemate.test',
            $tokens->issueCheckoutToken(),
            null,
            $salesChannel,
            $counterActor,
            $customer?->name ?? 'Khách tại quầy Phase 1',
            '0901000001',
            null,
        )->booking;
        $booking->forceFill([
            'booking_code' => $bookingCode,
            'cinema_id' => $showtime->cinema_id,
        ])->save();
        if ($foodSelection !== null) {
            $food = app(BookingFoodService::class);
            $foodBreakdown = $food->calculate($foodSelection, (int) $showtime->cinema_id);
            $food->persist($foodBreakdown, [
                'booking_id' => $booking->id,
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
            ]);
            $gross = (int) $booking->seat_subtotal + $foodBreakdown->foodSubtotal;
            $booking->forceFill([
                'food_subtotal' => $foodBreakdown->foodSubtotal,
                'gross_amount' => $gross,
                'total_amount' => $gross,
            ])->save();
        }
        if ($promotionCode !== null) {
            DB::transaction(function () use ($booking, $promotionCode): void {
                $quote = app(PromotionService::class)->reserveForBooking(
                    $booking,
                    $promotionCode,
                    (int) $booking->gross_amount,
                );
                $booking->forceFill([
                    'promotion_discount_amount' => $quote->discountAmount,
                    'total_amount' => $quote->finalAmount,
                ])->save();
            });
        }

        if ($salesChannel === Booking::SALES_CHANNEL_COUNTER) {
            $payment = new Payment;
            $payment->forceFill([
                'booking_id' => $booking->id,
                'provider' => Payment::PROVIDER_COUNTER_CASH,
                'payment_method' => Payment::PROVIDER_COUNTER_CASH,
                'amount' => (int) $booking->total_amount,
                'currency' => 'VND',
                'status' => Payment::STATUS_SUCCESS,
                'settled_by_user_id' => $counterActor?->id,
                'settled_at' => now(),
                'paid_at' => now(),
            ])->save();
            $booking->forceFill([
                'payment_method' => Payment::PROVIDER_COUNTER_CASH,
                'payment_status' => 'paid',
                'booking_status' => 'paid',
                'paid_at' => now(),
                'expires_at' => null,
            ])->save();
        } else {
            $payment = Payment::createForProvider('vnpay', [
                'booking_id' => $booking->id,
                'payment_method' => 'vnpay',
                'order_code' => 'SEED-'.$bookingCode,
                'amount' => (int) $booking->total_amount,
                'currency' => 'VND',
                'status' => Payment::STATUS_PENDING,
            ]);
            $verified = app(VerifiedPaymentService::class)->verify($payment, new VerifiedPaymentData(
                provider: 'vnpay',
                merchantReference: 'SEED-'.$bookingCode,
                amount: (int) $booking->total_amount,
                providerTransactionId: 'SEED-TXN-'.$bookingCode,
                source: 'query',
                payloadHash: hash('sha256', 'seed-query-'.$bookingCode),
                responseCode: '00',
                transactionStatus: '00',
                providerPaidAt: now(),
            ));
            if (! $verified->accepted || ! $verified->transitioned) {
                throw new RuntimeException("Phase 1 demo payment verification failed for {$bookingCode}: {$verified->message}");
            }
        }

        app(TicketArtifactProvisioner::class)->provision($booking->fresh());
    }
}
