<?php

namespace Tests\Feature\Counter;

use App\Domain\Payments\PayOsSigner;
use App\Domain\Payments\VerifiedPaymentData;
use App\Exceptions\ZaloPayTransportException;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\BookingTicketDelivery;
use App\Models\BookingTicketPrint;
use App\Models\Cinema;
use App\Models\FoodItem;
use App\Models\Movie;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomType;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use App\Services\Admin\AdminPaymentQuery;
use App\Services\BookingTokenService;
use App\Services\Payments\PaymentReconciliationService;
use App\Services\Payments\PayOsPaymentStateService;
use App\Services\Payments\VerifiedPaymentService;
use App\Services\Payments\VnpayExplicitCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

final class CounterSalesR8Test extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_schema_and_online_defaults_are_backward_compatible(): void
    {
        $this->assertTrue(Schema::hasColumns('bookings', [
            'sales_channel', 'created_by_staff_id', 'customer_name', 'customer_phone',
        ]));
        $this->assertTrue(Schema::hasColumns('payments', ['settled_by_user_id', 'settled_at']));

        $scenario = $this->bookingScenario(false);
        $online = $this->reserve($scenario, [$scenario['seats'][0]->id])->booking;

        $this->assertSame(Booking::SALES_CHANNEL_ONLINE, $online->sales_channel);
        $this->assertNull($online->created_by_staff_id);
        $this->assertNull($online->createdByStaff);
    }

    public function test_counter_workspace_permissions_and_inactive_actor_are_enforced(): void
    {
        $staff = $this->userWithRole('staff');
        $customer = $this->userWithRole('user');

        $this->actingAs($staff)->get(route('staff.counter.index'))->assertOk()->assertSee('Bán vé tại quầy');
        $this->actingAs($customer)->get(route('staff.counter.index'))->assertForbidden();
        $staff->forceFill(['status' => 'inactive'])->save();
        $this->flushSession();
        $this->actingAs($staff->fresh())->get(route('staff.counter.index'))->assertRedirect(route('login'));
    }

    public function test_counter_seat_summary_groups_a_couple_pair_as_one_pricing_unit(): void
    {
        $scenario = $this->bookingScenario(true);
        $staff = $this->userWithRole('staff');

        $response = $this->actingAs($staff)
            ->get(route('staff.counter.seats', $scenario['showtime']))
            ->assertOk()
            ->assertSee('Ghế đã chọn')
            ->assertSee('Ghế đôi gồm 2 chỗ ngồi nhưng chỉ tính giá một cặp.')
            ->assertSee('aria-live="polite"', false)
            ->assertSee('data-unit-key="seat:'.$scenario['seats'][0]->id.'"', false)
            ->assertSee('data-unit-key="couple:B-PAIR-1"', false)
            ->assertSee('data-seat-label="B1"', false)
            ->assertSee('data-seat-label="B2"', false)
            ->assertSee('Máy chủ sẽ kiểm tra lại ghế giữ, ghế đôi, khoảng trống một ghế và giá chính thức.');

        $this->assertSame(2, substr_count($response->getContent(), 'data-unit-key="couple:B-PAIR-1"'));
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_hold_derives_channel_creator_and_totals_and_rejects_forged_authority(): void
    {
        $scenario = $this->bookingScenario(false);
        $staff = $this->userWithRole('staff');
        $token = app(BookingTokenService::class)->issueCheckoutToken();

        $this->actingAs($staff)->post(route('staff.counter.hold', $scenario['showtime']), [
            'seat_ids' => [$scenario['seats'][0]->id],
            'checkout_token' => $token,
            'sales_channel' => 'online',
            'created_by_staff_id' => $this->userWithRole('staff')->id,
            'staff_id' => 999,
            'total_amount' => 1,
        ])->assertSessionHasErrors(['sales_channel', 'created_by_staff_id', 'staff_id', 'total_amount']);
        $this->assertDatabaseCount('bookings', 0);

        $this->post(route('staff.counter.hold', $scenario['showtime']), [
            'seat_ids' => [$scenario['seats'][0]->id],
            'checkout_token' => $token,
            'customer_name' => 'Khách tại quầy',
            'customer_phone' => '090 123-4567',
        ])->assertRedirect();

        $booking = Booking::query()->sole();
        $this->assertSame(Booking::SALES_CHANNEL_COUNTER, $booking->sales_channel);
        $this->assertSame($staff->id, $booking->created_by_staff_id);
        $this->assertNull($booking->user_id);
        $this->assertNull($booking->guest_access_token_hash);
        $this->assertSame('0901234567', $booking->customer_phone);
        $this->assertSame(50000, (int) $booking->seat_subtotal);
        $this->assertSame(50000, (int) $booking->total_amount);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'counter.booking_created', 'actor_user_id' => $staff->id,
        ]);
    }

    public function test_food_is_optional_branch_scoped_and_browser_totals_are_prohibited(): void
    {
        [$booking, $scenario, $staff] = $this->counterHold();
        $globalFood = FoodItem::query()->create(['name' => 'Nước', 'price' => 25000, 'active' => true]);
        $otherCinema = Cinema::factory()->create([
            'status' => 'active', 'archived_at' => null, 'is_primary' => false,
        ]);
        $otherFood = FoodItem::query()->create([
            'cinema_id' => $otherCinema->id, 'name' => 'Sai chi nhánh', 'price' => 1000, 'active' => true,
        ]);

        $this->actingAs($staff)->post(route('staff.counter.food.update', $booking), [
            'food_items' => [['food_id' => $otherFood->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('food_items');
        $this->post(route('staff.counter.food.update', $booking), [
            'food_items' => [['food_id' => $globalFood->id, 'quantity' => 2]],
            'total_amount' => 1,
        ])->assertSessionHasErrors('total_amount');
        $this->post(route('staff.counter.food.update', $booking), [
            'food_items' => [['food_id' => $globalFood->id, 'quantity' => 2]],
        ])->assertRedirect(route('staff.counter.review', $booking));
        $booking->refresh();
        $this->assertSame(50000, (int) $booking->food_subtotal);
        $this->assertSame(100000, (int) $booking->total_amount);

        $this->post(route('staff.counter.food.update', $booking), ['food_items' => []])->assertRedirect();
        $this->assertSame(0, (int) $booking->fresh()->food_subtotal);
        $this->assertNull($booking->fresh()->foodOrder);
    }

    public function test_counter_hold_reuses_gap_couple_and_competing_hold_guards(): void
    {
        $staff = $this->userWithRole('staff');
        $gapScenario = $this->normalRowScenario(Cinema::query()->active()->primary()->firstOrFail(), 4);
        $token = app(BookingTokenService::class)->issueCheckoutToken();
        $this->actingAs($staff)->post(route('staff.counter.hold', $gapScenario['showtime']), [
            'seat_ids' => [$gapScenario['seats'][1]->id], 'checkout_token' => $token,
        ])->assertSessionHasErrors('seat_ids');

        $coupleScenario = $this->bookingScenario(true);
        $this->post(route('staff.counter.hold', $coupleScenario['showtime']), [
            'seat_ids' => [$coupleScenario['seats'][2]->id],
            'checkout_token' => app(BookingTokenService::class)->issueCheckoutToken(),
        ])->assertSessionHasErrors('seat_ids');

        $competitorScenario = $this->bookingScenario(false);
        $this->reserve($competitorScenario, [$competitorScenario['seats'][0]->id]);
        $this->post(route('staff.counter.hold', $competitorScenario['showtime']), [
            'seat_ids' => [$competitorScenario['seats'][0]->id],
            'checkout_token' => app(BookingTokenService::class)->issueCheckoutToken(),
        ])->assertSessionHasErrors('seat_ids');
    }

    public function test_cash_settlement_uses_actual_settler_exact_amount_and_is_idempotent_without_http(): void
    {
        [$booking, , $creator] = $this->counterHold();
        $settler = $this->userWithRole('staff');
        Http::fake(fn () => throw new \RuntimeException('Counter cash must not call an external provider.'));

        $this->actingAs($settler)->post(route('staff.counter.cash', $booking), [
            'amount' => 1,
            'settled_by_user_id' => $creator->id,
        ])->assertSessionHasErrors(['amount', 'settled_by_user_id']);
        $this->assertSame('unpaid', $booking->fresh()->payment_status);

        $this->post(route('staff.counter.cash', $booking))->assertRedirect(route('staff.counter.payment-result', $booking));
        $payment = Payment::query()->sole();
        $this->assertSame(Payment::PROVIDER_COUNTER_CASH, $payment->provider);
        $this->assertSame($settler->id, $payment->settled_by_user_id);
        $this->assertSame((int) $booking->total_amount, $payment->amount);
        $this->assertNull($payment->verified_at);
        $this->assertNotNull($payment->settled_at);
        $this->assertSame('COUNTER-'.$payment->id, $payment->transaction_code);
        $this->assertSame('paid', $booking->fresh()->booking_status);
        $this->assertSame($creator->id, $booking->fresh()->created_by_staff_id);
        $this->assertDatabaseCount('booking_ticket_deliveries', 0);
        Http::assertNothingSent();

        $this->post(route('staff.counter.cash', $booking))->assertRedirect();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('activity_logs', 2);
    }

    public function test_email_delivery_is_enqueued_exactly_once_only_when_recipient_exists(): void
    {
        Mail::fake();
        [$booking, , $staff] = $this->counterHold('counter@example.test');
        $this->actingAs($staff)->post(route('staff.counter.cash', $booking))->assertRedirect();
        $this->post(route('staff.counter.cash', $booking))->assertRedirect();

        $this->assertDatabaseCount('booking_ticket_deliveries', 1);
        $summary = app(AdminPaymentQuery::class)->summary([]);
        $this->assertSame(0, $summary['online']);
        $this->assertSame(1, $summary['counter']);
        $this->assertDatabaseHas('booking_ticket_deliveries', [
            'booking_id' => $booking->id, 'status' => BookingTicketDelivery::STATUS_SENT,
        ]);
    }

    public function test_counter_endpoint_cannot_settle_online_or_provider_attempt_bookings(): void
    {
        $staff = $this->userWithRole('staff');
        $onlineScenario = $this->bookingScenario(false);
        $online = $this->reserve($onlineScenario, [$onlineScenario['seats'][0]->id])->booking;
        Payment::createForProvider('vnpay', [
            'booking_id' => $online->id, 'payment_method' => 'vnpay',
            'order_code' => 'ONLINE-R8', 'amount' => (int) $online->total_amount,
            'currency' => 'VND', 'status' => Payment::STATUS_PENDING,
        ]);

        $this->actingAs($staff)->post(route('staff.counter.cash', $online))->assertNotFound();
        $this->assertSame('unpaid', $online->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_PENDING, Payment::query()->sole()->status);

        [$counter] = $this->counterHold();
        Payment::createForProvider('payos', [
            'booking_id' => $counter->id, 'payment_method' => 'payos',
            'order_code' => 'FORGED-R8', 'amount' => (int) $counter->total_amount,
            'currency' => 'VND', 'status' => Payment::STATUS_PENDING,
        ]);
        $this->actingAs($staff)->post(route('staff.counter.cash', $counter))->assertSessionHasErrors('booking');
        $this->assertSame('unpaid', $counter->fresh()->payment_status);
    }

    public function test_unpaid_counter_cancellation_releases_seat_but_paid_booking_cannot_cancel(): void
    {
        [$booking, $scenario, $staff] = $this->counterHold();
        $this->actingAs($staff)->post(route('staff.counter.cancel', $booking))->assertRedirect(route('staff.counter.index'));
        $this->assertSame('cancelled', $booking->fresh()->booking_status);
        $this->assertDatabaseMissing('booking_seats', [
            'booking_id' => $booking->id, 'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
        ]);
        $this->post(route('staff.counter.cancel', $booking))->assertRedirect();

        $replacement = $this->counterHoldForScenario($scenario, $staff);
        $this->post(route('staff.counter.cash', $replacement))->assertRedirect();
        $this->post(route('staff.counter.cancel', $replacement))->assertSessionHasErrors('booking');
        $this->assertSame('paid', $replacement->fresh()->booking_status);
        $this->assertDatabaseHas('booking_seats', [
            'booking_id' => $replacement->id, 'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
        ]);
    }

    public function test_creator_settler_and_printer_actors_remain_distinct(): void
    {
        [$booking, , $creator] = $this->counterHold();
        $settler = $this->userWithRole('staff');
        $printer = $this->userWithRole('staff');

        $this->actingAs($settler)->post(route('staff.counter.cash', $booking))->assertRedirect();
        $this->actingAs($printer)->post(route('staff.tickets.print.start', $booking))->assertRedirect();
        $this->post(route('staff.tickets.print.succeed', $booking))->assertRedirect();

        $this->assertSame($creator->id, $booking->fresh()->created_by_staff_id);
        $this->assertSame($settler->id, Payment::query()->sole()->settled_by_user_id);
        $this->assertSame($printer->id, BookingTicketPrint::query()->sole()->printed_by_user_id);
        $this->assertSame('paid', $booking->fresh()->booking_status);

        $creator->forceFill(['status' => 'inactive'])->save();
        $this->assertSame($creator->name, $booking->fresh()->createdByStaff?->name);
    }

    public function test_cross_branch_counter_urls_are_denied_and_global_admin_is_allowed(): void
    {
        $other = Cinema::factory()->create([
            'code' => 'HD-R8', 'name' => 'Hà Đông R8', 'status' => 'active',
            'archived_at' => null, 'is_primary' => false,
        ]);
        $scenario = $this->normalRowScenario($other, 2);
        $staff = $this->userWithRole('staff');
        $manager = $this->userWithRole('manager');
        $payload = [
            'seat_ids' => $scenario['seats']->pluck('id')->all(),
            'checkout_token' => app(BookingTokenService::class)->issueCheckoutToken(),
        ];

        $this->actingAs($staff)->get(route('staff.counter.seats', $scenario['showtime']))->assertNotFound();
        $this->post(route('staff.counter.hold', $scenario['showtime']), $payload)->assertNotFound();
        $this->actingAs($manager)->post(route('staff.counter.hold', $scenario['showtime']), $payload)->assertNotFound();

        $admin = $this->userWithRole('admin');
        $payload['checkout_token'] = app(BookingTokenService::class)->issueCheckoutToken();
        $this->actingAs($admin)->post(route('staff.counter.hold', $scenario['showtime']), $payload)->assertRedirect();
        $booking = Booking::query()->where('sales_channel', 'counter')->sole();

        $this->actingAs($staff)->get(route('staff.counter.review', $booking))->assertNotFound();
        $this->post(route('staff.counter.cash', $booking))->assertNotFound();
        $this->post(route('staff.counter.cancel', $booking))->assertNotFound();
        $this->actingAs($admin)->post(route('staff.counter.cash', $booking))->assertRedirect();
        $this->assertSame('paid', $booking->fresh()->booking_status);
        $this->assertSame($other->id, $booking->fresh()->cinema_id);
    }

    public function test_counter_vnpay_keeps_counter_identity_uses_provider_evidence_and_transitions_to_print(): void
    {
        $this->configureVnpay();
        [$booking, , $staff] = $this->counterHold('counter-vnpay@example.test');

        $this->actingAs($staff)->post(route('staff.counter.payments.initiate', [$booking, 'vnpay']))
            ->assertRedirectContains('sandbox.vnpayment.vn/paymentv2/vpcpay.html');

        $payment = $booking->payments()->sole();
        $this->assertSame('vnpay', $payment->provider);
        $this->assertSame((int) $booking->total_amount, $payment->amount);
        $this->assertSame(Booking::SALES_CHANNEL_COUNTER, $booking->fresh()->sales_channel);
        $this->assertSame($staff->id, $booking->fresh()->created_by_staff_id);
        $this->assertNull($payment->settled_at);
        $this->assertNull($payment->settled_by_user_id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'counter.provider_payment_initiated']);
        $returnedUrl = route('staff.counter.payment-result', ['booking' => $booking, 'returned' => 1]);
        $this->get(route('payments.vnpay.return', ['ref' => $payment->order_code]))
            ->assertRedirect($returnedUrl);
        $this->get($returnedUrl)->assertOk()->assertSee('Đang xác minh thanh toán VNPAY');

        $this->verifyProviderPayment($payment, 'VNPAY-TXN-COUNTER');
        $payment->refresh();
        $this->assertNotNull($payment->verified_at);
        $this->assertNull($payment->settled_at);
        $this->assertSame('paid', $booking->fresh()->booking_status);
        $this->assertDatabaseCount('booking_ticket_deliveries', 1);

        $this->get(route('staff.counter.payment-result', $booking))
            ->assertOk()->assertSee('Thanh toán thành công')->assertSee('VNPAY')
            ->assertSee('data-auto-print-all', false);
        $this->get(route('payments.vnpay.return', ['ref' => $payment->order_code]))
            ->assertRedirect(route('staff.counter.payment-result', ['booking' => $booking, 'returned' => 1]));
        $this->post(route('staff.tickets.print-all', $booking))->assertOk();
        $this->assertSame(1, BookingTicketPrint::query()->sole()->attempts_count);
    }

    public function test_counter_payos_keeps_provider_identity_reuses_checkout_and_transitions_to_print(): void
    {
        $this->configurePayOs();
        Http::fake(function (Request $request) {
            $requestData = $request->data();
            $data = [
                'orderCode' => $requestData['orderCode'],
                'amount' => $requestData['amount'],
                'currency' => 'VND',
                'paymentLinkId' => '124c33293c43417ab7879e14c8d9eb18',
                'status' => 'PENDING',
                'checkoutUrl' => 'https://pay.payos.vn/web/124c33293c43417ab7879e14c8d9eb18',
            ];

            return Http::response([
                'code' => '00',
                'data' => $data,
                'signature' => app(PayOsSigner::class)->signData($data, 'payos-counter-checksum-key'),
            ]);
        });
        [$booking, , $staff] = $this->counterHold();

        $this->actingAs($staff)->post(route('staff.counter.payments.initiate', [$booking, 'payos']))
            ->assertRedirect('https://pay.payos.vn/web/124c33293c43417ab7879e14c8d9eb18');
        $payment = $booking->payments()->sole();
        $this->assertSame('payos', $payment->provider);
        $this->assertSame((int) $booking->total_amount, $payment->amount);
        $this->assertNotNull($payment->transaction_code);
        $this->assertNull($payment->settled_at);
        $this->assertSame($staff->id, $booking->fresh()->created_by_staff_id);

        $this->actingAs($staff)->post(route('staff.counter.payments.initiate', [$booking, 'payos']))
            ->assertRedirect('https://pay.payos.vn/web/124c33293c43417ab7879e14c8d9eb18');
        $this->assertDatabaseCount('payments', 1);
        Http::assertSentCount(1);

        $this->verifyProviderPayment($payment, 'PAYOS-TXN-COUNTER');
        $payment->refresh();
        $this->assertNotNull($payment->verified_at);
        $this->assertNull($payment->settled_at);
        $this->assertSame('paid', $booking->fresh()->booking_status);
        $this->get(route('staff.counter.payment-result', $booking))
            ->assertOk()->assertSee('Thanh toán thành công')->assertSee('payOS')
            ->assertSee('data-auto-print-all', false);
        $this->get(route('payments.payos.return', ['attempt' => $payment->id]))
            ->assertRedirect(route('staff.counter.payment-result', ['booking' => $booking, 'returned' => 1]));
    }

    public function test_paid_counter_booking_auto_prints_every_seat_ticket_and_food_voucher(): void
    {
        $cinema = Cinema::query()->active()->primary()->firstOrFail();
        $scenario = $this->normalRowScenario($cinema, 2);
        $staff = $this->userWithRole('staff');
        $token = app(BookingTokenService::class)->issueCheckoutToken();

        $this->actingAs($staff)->post(route('staff.counter.hold', $scenario['showtime']), [
            'seat_ids' => $scenario['seats']->pluck('id')->all(),
            'checkout_token' => $token,
        ])->assertRedirect();

        $booking = Booking::query()
            ->where('checkout_idempotency_key_hash', hash('sha256', $token))
            ->sole();
        $food = FoodItem::query()->create([
            'name' => 'Combo in toàn bộ', 'price' => 35_000, 'active' => true,
        ]);
        $this->post(route('staff.counter.food.update', $booking), [
            'food_items' => [['food_id' => $food->id, 'quantity' => 1]],
        ])->assertRedirect(route('staff.counter.review', $booking));
        $this->post(route('staff.counter.cash', $booking))
            ->assertRedirect(route('staff.counter.payment-result', $booking));

        $booking->refresh();
        $this->assertSame(2, $booking->admissionTickets()->count());
        $this->assertSame(1, $booking->foodPickupVoucher()->count());

        $singlePrintAction = 'action="'.route('staff.tickets.print.start', $booking).'"';
        $printAllAction = 'action="'.route('staff.tickets.print-all', $booking).'"';
        $this->get(route('staff.counter.payment-result', $booking))
            ->assertOk()
            ->assertSee('Đang chuyển sang in toàn bộ')
            ->assertSee('data-auto-print-all', false)
            ->assertSee($printAllAction, false)
            ->assertDontSee('data-auto-print-start', false)
            ->assertDontSee($singlePrintAction, false);

        $response = $this->post(route('staff.tickets.print-all', $booking))->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'data-print-artifact="ticket"'));
        $this->assertSame(1, substr_count($response->getContent(), 'data-print-artifact="food-voucher"'));
        $this->assertSame([1, 1], $booking->admissionTickets()->orderBy('id')->pluck('print_count')->all());
        $this->assertSame(1, $booking->foodPickupVoucher()->value('print_count'));
        $this->assertDatabaseCount('booking_ticket_prints', 2);
        $this->assertDatabaseCount('food_pickup_voucher_print_events', 1);
        $this->post(route('staff.tickets.print-all', $booking))->assertStatus(409);
    }

    public function test_cross_branch_counter_provider_payment_result_resume_and_print_are_denied(): void
    {
        $other = Cinema::factory()->create([
            'code' => 'HD-PROVIDER', 'name' => 'Hà Đông Provider', 'status' => 'active',
            'archived_at' => null, 'is_primary' => false,
        ]);
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');
        $booking = $this->counterHoldForScenario($this->normalRowScenario($other, 1), $admin);
        Payment::createForProvider('vnpay', [
            'booking_id' => $booking->id,
            'payment_method' => 'vnpay',
            'order_code' => 'MMCOUNTERWRONGBRANCH',
            'amount' => (int) $booking->total_amount,
            'currency' => 'VND',
            'status' => Payment::STATUS_PENDING,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->actingAs($staff)->post(route('staff.counter.payments.initiate', [$booking, 'vnpay']))->assertNotFound();
        $this->post(route('staff.counter.payment.resume', $booking))->assertNotFound();
        $this->post(route('staff.counter.payment.reconcile', $booking))->assertNotFound();
        $this->post(route('staff.counter.payment.cancel-payos-attempt', $booking))->assertNotFound();
        $this->get(route('staff.counter.payment-result', $booking))->assertNotFound();
        $this->post(route('staff.tickets.print.start', $booking))->assertNotFound();
    }

    public function test_foreign_branch_cannot_trigger_counter_expiry_mutation(): void
    {
        $other = Cinema::factory()->create([
            'code' => 'HD-EXPIRY', 'name' => 'Ha Dong Expiry', 'status' => 'active',
            'archived_at' => null, 'is_primary' => false,
        ]);
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');
        $booking = $this->counterHoldForScenario($this->normalRowScenario($other, 1), $admin);
        $booking->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->actingAs($staff)->get(route('staff.counter.review', $booking))->assertNotFound();
        $this->get(route('staff.counter.payment-result', $booking))->assertNotFound();

        $this->assertSame('pending_payment', $booking->fresh()->booking_status);
        $this->assertSame(1, $booking->bookingSeats()->whereNotNull('active_lock_key')->count());
    }

    public function test_zalopay_reconciliation_transport_failure_keeps_counter_hold_safe(): void
    {
        [$booking, , $staff] = $this->counterHold();
        $payment = Payment::createForProvider('zalopay', [
            'booking_id' => $booking->id,
            'payment_method' => 'zalopay',
            'app_id' => 2554,
            'app_trans_id' => '260818_COUNTER_RECOVERY',
            'amount' => (int) $booking->total_amount,
            'currency' => 'VND',
            'status' => Payment::STATUS_PENDING,
            'expires_at' => now()->addMinutes(10),
            'reconcile_until' => now()->addHour(),
        ]);
        $this->mock(PaymentReconciliationService::class, function ($mock): void {
            $mock->shouldReceive('reconcile')
                ->once()
                ->andThrow(new ZaloPayTransportException('Provider unavailable.'));
        });

        $this->actingAs($staff)
            ->post(route('staff.counter.payment.reconcile', $booking))
            ->assertRedirect(route('staff.counter.payment-result', $booking))
            ->assertSessionHas('warning');

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('pending_payment', $booking->fresh()->booking_status);
        $this->assertSame(1, $booking->bookingSeats()->whereNotNull('active_lock_key')->count());
    }

    public function test_authoritative_counter_provider_failure_retains_hold_for_retry_while_processing_stays_protected(): void
    {
        $this->configureVnpay();
        [$vnpayBooking] = $this->counterHold();
        $vnpay = Payment::createForProvider('vnpay', [
            'booking_id' => $vnpayBooking->id,
            'payment_method' => 'vnpay',
            'order_code' => 'MMCOUNTERCANCELVNPAY',
            'amount' => (int) $vnpayBooking->total_amount,
            'currency' => 'VND',
            'status' => Payment::STATUS_PENDING,
        ]);
        app(VnpayExplicitCancellationService::class)->finalizeVerified($vnpay, [
            'vnp_TmnCode' => 'MOVIE123',
            'vnp_TxnRef' => $vnpay->order_code,
            'vnp_Amount' => (string) ($vnpay->amount * 100),
            'vnp_ResponseCode' => '24',
            'vnp_TransactionStatus' => '02',
        ], 'return');
        $this->assertSame(Payment::STATUS_FAILED, $vnpay->fresh()->status);
        $this->assertSame('pending_payment', $vnpayBooking->fresh()->booking_status);
        $this->assertSame(1, $vnpayBooking->bookingSeats()->whereNotNull('active_lock_key')->count());

        [$payOsBooking] = $this->counterHold();
        $payOs = Payment::createForProvider('payos', [
            'booking_id' => $payOsBooking->id,
            'payment_method' => 'payos',
            'order_code' => '7654321',
            'transaction_code' => 'counterPayOsLink123',
            'amount' => (int) $payOsBooking->total_amount,
            'currency' => 'VND',
            'status' => Payment::STATUS_PENDING,
        ]);
        $cancelled = [
            'orderCode' => 7654321,
            'amount' => $payOs->amount,
            'currency' => 'VND',
            'paymentLinkId' => $payOs->transaction_code,
            'status' => 'CANCELLED',
        ];
        app(PayOsPaymentStateService::class)->apply($payOs, $cancelled, 'query', hash('sha256', 'cancelled'));
        $this->assertSame(Payment::STATUS_FAILED, $payOs->fresh()->status);
        $this->assertSame('pending_payment', $payOsBooking->fresh()->booking_status);
        $this->assertSame(1, $payOsBooking->bookingSeats()->whereNotNull('active_lock_key')->count());

        [$processingBooking] = $this->counterHold();
        $processing = Payment::createForProvider('payos', [
            'booking_id' => $processingBooking->id,
            'payment_method' => 'payos',
            'order_code' => '7654322',
            'transaction_code' => 'counterPayOsLink124',
            'amount' => (int) $processingBooking->total_amount,
            'currency' => 'VND',
            'status' => Payment::STATUS_PENDING,
        ]);
        app(PayOsPaymentStateService::class)->apply($processing, [
            'orderCode' => 7654322,
            'amount' => $processing->amount,
            'currency' => 'VND',
            'paymentLinkId' => $processing->transaction_code,
            'status' => 'PROCESSING',
        ], 'query', hash('sha256', 'processing'));
        $this->assertSame(Payment::STATUS_PROCESSING, $processing->fresh()->status);
        $this->assertSame('pending_payment', $processingBooking->fresh()->booking_status);
        $this->assertSame(1, $processingBooking->bookingSeats()->whereNotNull('active_lock_key')->count());
        $this->assertDatabaseCount('booking_ticket_prints', 0);
    }

    public function test_browser_back_cannot_create_parallel_provider_attempt_and_staff_can_cancel_to_release_seats(): void
    {
        $this->configureVnpay();
        $this->configurePayOs();
        [$booking, , $staff] = $this->counterHold();

        $this->actingAs($staff)
            ->post(route('staff.counter.payments.initiate', [$booking, 'vnpay']))
            ->assertRedirect();
        $vnpay = $booking->payments()->sole();

        $this->post(route('staff.counter.payments.initiate', [$booking, 'payos']))
            ->assertRedirect(route('staff.counter.payment-result', $booking))
            ->assertSessionHas('warning');
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(Payment::STATUS_PENDING, $vnpay->fresh()->status);
        $this->assertSame(1, $booking->bookingSeats()->whereNotNull('active_lock_key')->count());

        $this->get(route('staff.counter.payment-result', $booking))
            ->assertOk()
            ->assertSee('Tiếp tục thanh toán VNPAY')
            ->assertSee('Kiểm tra trạng thái với nhà cung cấp')
            ->assertSee('Hủy đơn và giải phóng ghế')
            ->assertDontSee('Chọn phương thức thanh toán khác');

        $this->post(route('staff.counter.cancel', $booking))
            ->assertRedirect(route('staff.counter.index'));
        $this->assertSame('cancelled', $booking->fresh()->booking_status);
        $this->assertSame(Payment::STATUS_FAILED, $vnpay->fresh()->status);
        $this->assertSame(0, $booking->bookingSeats()->whereNotNull('active_lock_key')->count());
    }

    public function test_terminal_provider_attempt_can_switch_safely_and_payos_cancel_confirmation_retains_the_hold(): void
    {
        $this->configureVnpay();
        $this->configurePayOs();
        [$booking, , $staff] = $this->counterHold();
        $vnpay = Payment::createForProvider('vnpay', [
            'booking_id' => $booking->id,
            'payment_method' => 'vnpay',
            'order_code' => 'MMCOUNTERSWITCHVNPAY',
            'amount' => (int) $booking->total_amount,
            'currency' => 'VND',
            'status' => Payment::STATUS_PENDING,
        ]);
        app(VnpayExplicitCancellationService::class)->finalizeVerified($vnpay, [
            'vnp_TmnCode' => 'MOVIE123',
            'vnp_TxnRef' => $vnpay->order_code,
            'vnp_Amount' => (string) ($vnpay->amount * 100),
            'vnp_ResponseCode' => '24',
            'vnp_TransactionStatus' => '02',
        ], 'return');

        Http::fake(function (Request $request) use ($booking) {
            $requestData = $request->data();
            if (array_key_exists('cancellationReason', $requestData)) {
                preg_match('~/v2/payment-requests/([0-9]+)/cancel$~', $request->url(), $matches);
                $data = [
                    'orderCode' => (int) ($matches[1] ?? 0),
                    'amount' => (int) $booking->total_amount,
                    'currency' => 'VND',
                    'paymentLinkId' => 'counterSwitchPayOs123',
                    'status' => 'CANCELLED',
                ];

                return Http::response([
                    'code' => '00',
                    'data' => $data,
                    'signature' => app(PayOsSigner::class)->signData($data, 'payos-counter-checksum-key'),
                ]);
            }

            $data = [
                'orderCode' => $requestData['orderCode'],
                'amount' => $requestData['amount'],
                'currency' => 'VND',
                'paymentLinkId' => 'counterSwitchPayOs123',
                'status' => 'PENDING',
                'checkoutUrl' => 'https://pay.payos.vn/web/counterSwitchPayOs123',
            ];

            return Http::response([
                'code' => '00',
                'data' => $data,
                'signature' => app(PayOsSigner::class)->signData($data, 'payos-counter-checksum-key'),
            ]);
        });

        $this->actingAs($staff)->get(route('staff.counter.review', $booking))
            ->assertOk()
            ->assertSee('Lần thanh toán VNPAY trước đã kết thúc không thành công')
            ->assertSee('Phương thức thanh toán');
        $this->post(route('staff.counter.payments.initiate', [$booking, 'payos']))
            ->assertRedirect('https://pay.payos.vn/web/counterSwitchPayOs123');

        $payOs = $booking->payments()->where('provider', 'payos')->sole();
        $this->assertSame([Payment::STATUS_FAILED, Payment::STATUS_PENDING], $booking->payments()->orderBy('id')->pluck('status')->all());
        $this->assertSame(1, $booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        $this->get(route('staff.tickets.operations', $booking))
            ->assertOk()->assertSee('Tiếp tục xử lý thanh toán');

        $this->post(route('staff.counter.payment.cancel-payos-attempt', $booking))
            ->assertRedirect(route('staff.counter.review', $booking))
            ->assertSessionHas('success');
        $this->assertSame(Payment::STATUS_FAILED, $payOs->fresh()->status);
        $this->assertSame('pending_payment', $booking->fresh()->booking_status);
        $this->assertSame(1, $booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        $this->assertSame(0, $booking->payments()->whereIn('status', Payment::UNSAFE_RETRY_STATUSES)->count());

        $this->post(route('staff.counter.cancel', $booking))
            ->assertRedirect(route('staff.counter.index'));
        $this->assertSame('cancelled', $booking->fresh()->booking_status);
        $this->assertSame(0, $booking->bookingSeats()->whereNotNull('active_lock_key')->count());
    }

    public function test_opening_counter_payment_result_expires_abandoned_hold_and_releases_seat(): void
    {
        [$booking, , $staff] = $this->counterHold();
        $this->travelTo($booking->expires_at->copy()->addSecond());

        $this->actingAs($staff)->get(route('staff.counter.payment-result', $booking))
            ->assertOk()
            ->assertSee('Quay lại quầy bán vé')
            ->assertDontSee('Tiếp tục xử lý thanh toán');

        $this->assertSame('expired', $booking->fresh()->booking_status);
        $this->assertSame(0, $booking->bookingSeats()->whereNotNull('active_lock_key')->count());
    }

    public function test_counter_payment_and_print_surfaces_have_bounded_query_counts(): void
    {
        $this->configureVnpay();
        [$booking, , $staff] = $this->counterHold();
        $counts = [];
        $counts['counter_review'] = $this->queryCount(fn () => $this->actingAs($staff)
            ->get(route('staff.counter.review', $booking))->assertOk());
        $counts['vnpay_initiation'] = $this->queryCount(fn () => $this->post(
            route('staff.counter.payments.initiate', [$booking, 'vnpay']),
        )->assertRedirect());
        $payment = $booking->payments()->sole();
        $counts['counter_payment_status'] = $this->queryCount(fn () => $this->get(
            route('staff.counter.payment-result', $booking),
        )->assertOk());
        $counts['counter_payment_resume'] = $this->queryCount(fn () => $this->post(
            route('staff.counter.payment.resume', $booking),
        )->assertRedirect());
        $this->verifyProviderPayment($payment, 'VNPAY-QUERY-BUDGET');
        $counts['post_payment_print_transition'] = $this->queryCount(fn () => $this->get(
            route('staff.counter.payment-result', $booking),
        )->assertOk());
        $this->post(route('staff.tickets.print.start', $booking))->assertRedirect();
        $counts['print_page'] = $this->queryCount(fn () => $this->get(
            route('staff.tickets.print.show', $booking),
        )->assertOk());
        $this->travel(11)->minutes();
        $counts['print_recovery'] = $this->queryCount(fn () => $this->get(
            route('staff.tickets.print.show', $booking),
        )->assertRedirect());
        foreach ($counts as $surface => $count) {
            $this->assertLessThanOrEqual(30, $count, $surface.' has an unexpected query count: '.$count);
        }
    }

    public function test_admin_filters_and_displays_channel_and_independent_actors(): void
    {
        [$booking, , $creator] = $this->counterHold();
        $settler = $this->userWithRole('staff');
        $this->actingAs($settler)->post(route('staff.counter.cash', $booking));
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('admin.bookings.index', ['sales_channel' => 'counter']))
            ->assertOk()->assertSee($booking->booking_code)->assertSee('Tại quầy');
        $this->get(route('admin.bookings.index', ['sales_channel' => 'online']))
            ->assertOk()->assertDontSee($booking->booking_code);
        $this->get(route('admin.bookings.show', $booking))->assertOk()
            ->assertSee('Thông tin bán vé')->assertSee($creator->name)->assertSee($settler->name)
            ->assertSee('Tiền mặt tại quầy');
        $payment = Payment::query()->where('booking_id', $booking->id)->sole();
        $this->get(route('admin.payments.index', ['provider' => Payment::PROVIDER_COUNTER_CASH]))
            ->assertOk()->assertSee($booking->booking_code)->assertSee('Tiền mặt tại quầy');
        $this->get(route('admin.payments.show', $payment))->assertOk()->assertSee($settler->name);
        $this->get(route('admin.payment-reconciliation.index'))->assertOk()->assertDontSee($booking->booking_code);
    }

    /** @return array{0: Booking, 1: array<string,mixed>, 2: User} */
    private function counterHold(?string $email = null): array
    {
        $scenario = $this->bookingScenario(false);
        $staff = $this->userWithRole('staff');

        return [$this->counterHoldForScenario($scenario, $staff, $email), $scenario, $staff];
    }

    private function counterHoldForScenario(array $scenario, User $staff, ?string $email = null): Booking
    {
        $token = app(BookingTokenService::class)->issueCheckoutToken();
        $this->actingAs($staff)->post(route('staff.counter.hold', $scenario['showtime']), [
            'seat_ids' => [$scenario['seats'][0]->id],
            'checkout_token' => $token,
            'customer_email' => $email,
        ])->assertRedirect();

        return Booking::query()->where('checkout_idempotency_key_hash', hash('sha256', $token))->sole();
    }

    private function verifyProviderPayment(Payment $payment, string $transactionId): void
    {
        app(VerifiedPaymentService::class)->verify($payment, new VerifiedPaymentData(
            provider: $payment->provider,
            merchantReference: $payment->order_code,
            amount: $payment->amount,
            providerTransactionId: $transactionId,
            source: $payment->provider === 'vnpay' ? 'ipn' : 'callback',
            payloadHash: hash('sha256', $transactionId),
            responseCode: '00',
            transactionStatus: '00',
            providerPaidAt: now(),
        ));
    }

    private function configureVnpay(): void
    {
        $this->app['url']->forceRootUrl('https://merchant.example.test');
        $this->app['url']->forceScheme('https');
        config([
            'app.url' => 'https://merchant.example.test',
            'payment.public_hosts' => ['merchant.example.test'],
            'payment.vnpay.environment' => 'sandbox',
            'payment.vnpay.tmn_code' => 'MOVIE123',
            'payment.vnpay.hash_secret' => str_repeat('sandbox-secret-', 4),
            'payment.vnpay.payment_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'payment.vnpay.query_url' => 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction',
            'payment.vnpay.bank_code' => 'VNPAYQR',
            'payment.vnpay.locale' => 'vn',
            'payment.vnpay.order_type' => 'other',
            'payment.vnpay.payment_ttl_minutes' => 15,
            'payment.vnpay.http_timeout_seconds' => 10,
            'payment.vnpay.query_interval_seconds' => 60,
            'payment.vnpay.query_ip' => '127.0.0.1',
        ]);
    }

    private function configurePayOs(): void
    {
        config([
            'services.payos.client_id' => 'payos-counter-client-id',
            'services.payos.api_key' => 'payos-counter-api-key',
            'services.payos.checksum_key' => 'payos-counter-checksum-key',
            'services.payos.base_url' => 'https://api-merchant.payos.vn',
            'services.payos.connect_timeout_seconds' => 3,
            'services.payos.request_timeout_seconds' => 10,
        ]);
    }

    private function queryCount(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /** @return array{cinema:Cinema,room:Room,movie:Movie,seats:Collection,layout:RoomLayout,showtime:Showtime} */
    private function normalRowScenario(Cinema $cinema, int $seatCount): array
    {
        foreach (range(1, 7) as $day) {
            $cinema->operatingHours()->updateOrCreate(['day_of_week' => $day], [
                'opens_at' => '08:00:00', 'latest_show_start_at' => '23:00:00', 'is_closed' => false,
            ]);
        }
        $roomType = RoomType::query()->firstOrCreate(['code' => '2D'], [
            'name' => '2D', 'slug' => '2d', 'is_active' => true, 'status' => true, 'sort_order' => 1,
        ]);
        $room = Room::query()->create([
            'cinema_id' => $cinema->id, 'code' => 'R8'.str()->upper(str()->random(6)),
            'name' => 'R8 room', 'room_type' => '2D', 'room_type_id' => $roomType->id,
            'width_mm' => 8_000, 'length_mm' => 10_000, 'status' => 'active',
        ]);
        $seats = collect();
        for ($number = 1; $number <= $seatCount; $number++) {
            $seats->push(Seat::query()->create([
                'room_id' => $room->id, 'row' => 'A', 'number' => $number,
                'seat_code' => 'A'.$number, 'type' => 'normal', 'status' => 'active',
            ]));
        }
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id, 'version' => 1, 'name' => 'R8 layout',
            'rows' => 1, 'columns' => $seatCount, 'status' => 'draft',
        ]);
        foreach ($seats as $index => $seat) {
            $layout->cells()->create([
                'x_position' => $index + 1, 'y_position' => 1,
                'cell_type' => 'seat', 'seat_id' => $seat->id,
            ]);
        }
        $seats->each(fn (Seat $seat) => $this->assignLogicalSeatType($seat));
        $layout->update(['status' => 'published', 'published_at' => now()]);
        $movie = Movie::query()->create([
            'title' => 'R8 movie '.str()->random(5), 'slug' => 'r8-'.str()->lower(str()->random(10)),
            'duration' => 90, 'status' => 'now_showing',
        ]);
        $this->ensurePublishedPriceBook(60_000);
        $showtime = Showtime::query()->create([
            'movie_id' => $movie->id, 'cinema_id' => $cinema->id, 'room_id' => $room->id,
            'presentation_format_id' => $this->presentationFormatFixture($movie, $room)->id,
            'room_layout_id' => $layout->id, 'show_date' => now()->addDays(3)->toDateString(),
            'show_time' => '19:00:00', 'status' => 'active',
        ]);
        $this->snapshotShowtime($showtime);

        return compact('cinema', 'room', 'movie', 'seats', 'layout', 'showtime');
    }
}
