<?php

namespace Tests\Feature\Showtimes;

use App\Exceptions\PaymentInitiationException;
use App\Models\BookingPromotion;
use App\Models\BookingTicketPrint;
use App\Models\BookingTicketPrintEvent;
use App\Models\Cinema;
use App\Models\FoodItem;
use App\Models\FoodPickupVoucherPrintEvent;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\RefundCase;
use App\Models\ShowtimeCancellation;
use App\Services\BookingCheckoutService;
use App\Services\BookingTokenService;
use App\Services\Payments\PaymentResumeService;
use App\Services\PromotionService;
use App\Services\ShowtimeCancellationPreviewService;
use App\Services\Tickets\FoodPickupVoucherPrintService;
use App\Services\Tickets\TicketArtifactProvisioner;
use App\Services\Tickets\TicketPrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

final class ShowtimeCancellationSafetyMatrixTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_lifecycle_and_branch_authorization_cover_no_booking_completed_foreign_and_global_cases(): void
    {
        $admin = $this->userWithRole('admin');
        $empty = $this->bookingScenario(false);
        $this->actingAs($admin)->delete(route('admin.showtimes.destroy', $empty['showtime']), [
            'reason_code' => 'schedule_change',
            'confirm_cancellation' => '1',
        ])->assertRedirect();
        $this->assertSame('cancelled', $empty['showtime']->fresh()->status);
        $this->assertSame(ShowtimeCancellation::STATUS_RESOLVED, $empty['showtime']->cancellation->status);
        $this->assertDatabaseCount('showtime_cancellation_impacts', 0);

        $completed = $this->bookingScenario(false);
        $completed['showtime']->forceFill(['show_date' => now()->subDays(5)->toDateString()])->save();
        $this->actingAs($admin)->delete(route('admin.showtimes.destroy', $completed['showtime']), [
            'reason_code' => 'technical_issue',
            'confirm_cancellation' => '1',
        ])->assertSessionHasErrors('showtime');
        $this->assertSame('active', $completed['showtime']->fresh()->status);

        $foreign = $this->bookingScenario(false);
        $foreignCinema = Cinema::factory()->create([
            'code' => 'FOREIGN-CANCEL',
            'name' => 'Foreign cancellation branch',
            'canonical_key' => null,
            'is_primary' => false,
            'status' => 'active',
        ]);
        $foreignBooking = $this->reserve($foreign, [$foreign['seats'][0]->id])->booking;
        $foreign['room']->forceFill(['cinema_id' => $foreignCinema->id])->save();
        $foreign['showtime']->forceFill(['cinema_id' => $foreignCinema->id])->save();
        $foreignBooking->forceFill(['cinema_id' => $foreignCinema->id, 'booking_status' => 'paid', 'payment_status' => 'paid', 'paid_at' => now()])->save();
        $this->pay($foreignBooking);
        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->get(route('admin.showtimes.cancellation', $foreign['showtime']))->assertNotFound();
        $this->actingAs($manager)->delete(route('admin.showtimes.destroy', $foreign['showtime']), [
            'reason_code' => 'technical_issue',
            'confirm_cancellation' => '1',
        ])->assertNotFound();
        $this->flushSession();
        $this->actingAs($admin)->delete(route('admin.showtimes.destroy', $foreign['showtime']), [
            'reason_code' => 'technical_issue',
            'confirm_cancellation' => '1',
        ])->assertRedirect();
        $this->assertSame('cancelled', $foreign['showtime']->fresh()->status);
        $foreignCase = RefundCase::query()->where('booking_id', $foreignBooking->id)->sole();
        $this->flushSession();
        $this->actingAs($manager)->get(route('admin.refunds.index'))
            ->assertOk()
            ->assertDontSee($foreignBooking->booking_code);
        $this->actingAs($manager)->patch(route('admin.refunds.update', $foreignCase), [
            'resolved_amount' => $foreignCase->required_amount,
            'resolution_method' => 'bank_transfer',
            'resolution_reference' => 'FOREIGN-DENIED',
            'confirm_resolution' => '1',
        ])->assertNotFound();
    }

    public function test_paid_food_ticket_voucher_print_and_couple_history_are_preserved_but_all_future_operations_are_blocked(): void
    {
        $scenario = $this->bookingScenario();
        $admin = $this->userWithRole('admin');
        $food = FoodItem::query()->create(['name' => 'Combo preserved', 'price' => 45_000, 'active' => true]);
        $booking = app(BookingCheckoutService::class)->createPendingBooking(
            $scenario['showtime']->id,
            [$scenario['seats'][2]->id, $scenario['seats'][3]->id],
            $admin->id,
            $admin->email,
            app(BookingTokenService::class)->issueCheckoutToken(),
            [['food_id' => $food->id, 'quantity' => 2]],
        )->booking;
        $booking->forceFill(['booking_status' => 'paid', 'payment_status' => 'paid', 'paid_at' => now()])->save();
        $payment = $this->pay($booking);
        $booking->foodOrder->forceFill(['status' => 'paid'])->save();
        app(TicketArtifactProvisioner::class)->provision($booking->fresh());
        $tickets = $booking->admissionTickets()->orderBy('id')->get();
        $this->assertCount(2, $tickets);
        $ticket = $tickets->first();
        $ticket->forceFill(['print_count' => 1, 'last_printed_at' => now(), 'last_printed_by_user_id' => $admin->id])->save();
        $print = BookingTicketPrint::query()->create([
            'admission_ticket_id' => $ticket->id,
            'booking_id' => $booking->id,
            'status' => BookingTicketPrint::STATUS_PRINTED,
            'attempts_count' => 1,
            'printed_by_user_id' => $admin->id,
            'printed_at' => now(),
        ]);
        BookingTicketPrintEvent::query()->create([
            'booking_ticket_print_id' => $print->id,
            'admission_ticket_id' => $ticket->id,
            'booking_id' => $booking->id,
            'actor_user_id' => $admin->id,
            'event_type' => 'print_succeeded',
            'attempt_number' => 1,
            'request_id' => 'matrix-ticket-print',
        ]);
        $voucher = $booking->foodPickupVoucher()->sole();
        $voucher->forceFill(['print_count' => 1, 'last_printed_at' => now(), 'last_printed_by_user_id' => $admin->id])->save();
        FoodPickupVoucherPrintEvent::query()->create([
            'food_pickup_voucher_id' => $voucher->id,
            'actor_user_id' => $admin->id,
            'print_number' => 1,
            'reason' => null,
            'printed_at' => now(),
        ]);
        $preview = app(ShowtimeCancellationPreviewService::class)->summarize($scenario['showtime']);
        $this->assertSame(2, $preview['admission_ticket_count']);
        $this->assertSame(1, $preview['printed_ticket_count']);
        $this->assertSame(1, $preview['food_booking_count']);
        $this->assertSame(1, $preview['printed_voucher_count']);

        $this->actingAs($admin)->delete(route('admin.showtimes.destroy', $scenario['showtime']), [
            'reason_code' => 'facility_issue',
            'confirm_cancellation' => '1',
        ])->assertRedirect();

        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertDatabaseCount('admission_tickets', 2);
        $this->assertDatabaseHas('booking_ticket_print_events', ['id' => 1, 'event_type' => 'print_succeeded']);
        $this->assertDatabaseHas('food_pickup_vouchers', ['id' => $voucher->id, 'print_count' => 1]);
        $this->assertDatabaseHas('food_pickup_voucher_print_events', ['food_pickup_voucher_id' => $voucher->id, 'print_number' => 1]);
        $this->assertDatabaseHas('orders', ['id' => $booking->foodOrder->id, 'status' => 'paid']);
        $this->assertDatabaseCount('order_items', 1);
        $impact = $booking->showtimeCancellationImpact()->sole();
        $this->assertTrue($impact->audit_snapshot['had_food']);
        $this->assertSame(2, $impact->audit_snapshot['admission_ticket_count']);
        $this->assertSame(1, $impact->audit_snapshot['printed_ticket_count']);

        $this->assertHttpConflict(fn () => app(TicketPrintService::class)->start($ticket, $admin, (string) Str::uuid(), Str::random(64)));
        $this->assertHttpConflict(fn () => app(TicketPrintService::class)->reprint($ticket, $admin, (string) Str::uuid(), Str::random(64), 'customer_request', null));
        $this->assertHttpConflict(fn () => app(FoodPickupVoucherPrintService::class)->record($voucher, $admin, 'In lại'));
    }

    public function test_authoritative_discounted_amount_and_redeemed_promotion_snapshot_are_preserved(): void
    {
        $scenario = $this->bookingScenario(false);
        $admin = $this->userWithRole('admin');
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id], $admin->id)->booking;
        $booking->forceFill([
            'gross_amount' => 50_000,
            'promotion_discount_amount' => 10_000,
            'total_amount' => 40_000,
            'booking_status' => 'paid',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ])->save();
        $promotion = Promotion::query()->create([
            'code' => 'FROZEN10K',
            'name' => 'Frozen snapshot',
            'type' => Promotion::TYPE_FIXED,
            'discount_amount_vnd' => 10_000,
            'minimum_order_vnd' => 0,
            'is_active' => true,
        ]);
        $usage = BookingPromotion::query()->create([
            'booking_id' => $booking->id,
            'promotion_id' => $promotion->id,
            'user_id' => $admin->id,
            'code_snapshot' => 'FROZEN10K',
            'name_snapshot' => 'Frozen snapshot',
            'type_snapshot' => Promotion::TYPE_FIXED,
            'discount_amount_vnd_snapshot' => 10_000,
            'discount_percent_snapshot' => null,
            'maximum_discount_vnd_snapshot' => null,
            'minimum_order_vnd_snapshot' => 0,
            'scope_kind_snapshot' => 'global',
            'booking_cinema_id_snapshot' => $scenario['cinema']->id,
            'booking_cinema_code_snapshot' => $scenario['cinema']->code,
            'booking_cinema_name_snapshot' => $scenario['cinema']->name,
            'eligible_cinemas_snapshot' => null,
            'registered_users_only_snapshot' => false,
            'first_order_only_snapshot' => false,
            'global_usage_limit_snapshot' => null,
            'per_user_usage_limit_snapshot' => null,
            'applied_discount_vnd' => 10_000,
            'gross_before_vnd' => 50_000,
            'final_after_vnd' => 40_000,
            'status' => BookingPromotion::STATUS_RESERVED,
            'reserved_at' => now(),
        ]);
        app(PromotionService::class)->redeem($booking);
        $snapshot = $usage->fresh()->getRawOriginal();
        $payment = $this->pay($booking);

        $this->actingAs($admin)->delete(route('admin.showtimes.destroy', $scenario['showtime']), [
            'reason_code' => 'technical_issue',
            'confirm_cancellation' => '1',
        ])->assertRedirect();
        $refund = RefundCase::query()->sole();
        $this->assertSame(40_000, $refund->required_amount);
        $this->assertSame((int) $payment->amount, $refund->required_amount);
        $this->assertSame(BookingPromotion::STATUS_REDEEMED, $usage->fresh()->status);
        foreach (['code_snapshot', 'name_snapshot', 'applied_discount_vnd', 'gross_before_vnd', 'final_after_vnd', 'reserved_at'] as $field) {
            $this->assertSame($snapshot[$field], $usage->fresh()->getRawOriginal($field));
        }
    }

    public function test_confirmation_recomputes_new_impact_blocks_checkout_and_resume_and_parent_waits_for_every_refund(): void
    {
        $scenario = $this->bookingScenario();
        $admin = $this->userWithRole('admin');
        $first = $this->reserve($scenario, [$scenario['seats'][0]->id], $admin->id)->booking;
        $first->forceFill(['booking_status' => 'paid', 'payment_status' => 'paid', 'paid_at' => now()])->save();
        $this->pay($first);
        $preview = app(ShowtimeCancellationPreviewService::class)->summarize($scenario['showtime']);
        $this->assertSame(1, $preview['booking_count']);
        $second = $this->reserve($scenario, [$scenario['seats'][2]->id, $scenario['seats'][3]->id], $admin->id)->booking;
        $second->forceFill(['booking_status' => 'paid', 'payment_status' => 'paid', 'paid_at' => now()])->save();
        $this->pay($second);

        $this->actingAs($admin)->delete(route('admin.showtimes.destroy', $scenario['showtime']), [
            'reason_code' => 'technical_issue',
            'confirm_cancellation' => '1',
        ])->assertRedirect();
        $this->assertDatabaseCount('showtime_cancellation_impacts', 2);
        $this->assertDatabaseCount('refund_cases', 2);
        $cases = RefundCase::query()->orderBy('id')->get();
        $this->resolveCase($admin, $cases[0]);
        $this->assertSame(ShowtimeCancellation::STATUS_OPEN, $scenario['showtime']->cancellation->fresh()->status);
        $this->resolveCase($admin, $cases[1]);
        $this->assertSame(ShowtimeCancellation::STATUS_RESOLVED, $scenario['showtime']->cancellation->fresh()->status);

        $pendingScenario = $this->bookingScenario(false);
        $pending = $this->reserve($pendingScenario, [$pendingScenario['seats'][0]->id], $admin->id)->booking;
        Payment::createForProvider('vnpay', [
            'booking_id' => $pending->id,
            'payment_method' => 'vnpay',
            'provider' => 'vnpay',
            'order_code' => 'RESUME-BLOCK-'.str()->upper(str()->random(10)),
            'amount' => $pending->total_amount,
            'currency' => 'VND',
            'status' => Payment::STATUS_PENDING,
            'expires_at' => now()->addMinutes(10),
        ]);
        $this->actingAs($admin)->delete(route('admin.showtimes.destroy', $pendingScenario['showtime']), [
            'reason_code' => 'schedule_change',
            'confirm_cancellation' => '1',
        ])->assertRedirect();
        try {
            app(PaymentResumeService::class)->resume($pending, '127.0.0.1');
            $this->fail('Cancelled showtime booking must never resume payment.');
        } catch (PaymentInitiationException) {
            $this->addToAssertionCount(1);
        }
        $this->actingAs($admin)->get(route('user.bookings.selectSeat', $pendingScenario['showtime']))
            ->assertRedirect();
        $this->assertDatabaseMissing('refund_cases', ['booking_id' => $pending->id]);
    }

    private function pay($booking): Payment
    {
        return Payment::createForProvider('vnpay', [
            'booking_id' => $booking->id,
            'payment_method' => 'vnpay',
            'order_code' => 'MATRIX-'.str()->upper(str()->random(16)),
            'amount' => (int) $booking->total_amount,
            'currency' => 'VND',
            'status' => Payment::STATUS_SUCCESS,
            'verified_at' => now(),
            'paid_at' => now(),
            'transaction_id' => 'TX-'.str()->upper(str()->random(16)),
        ]);
    }

    private function resolveCase($actor, RefundCase $case): void
    {
        $this->actingAs($actor)->patch(route('admin.refunds.update', $case), [
            'resolved_amount' => $case->required_amount,
            'resolution_method' => 'bank_transfer',
            'resolution_reference' => 'MATRIX-REF-'.$case->id,
            'confirm_resolution' => '1',
        ])->assertSessionHas('success');
    }

    private function assertHttpConflict(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Cancelled showtime artifact operation must be blocked.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }
}
