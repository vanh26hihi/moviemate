<?php

namespace Tests\Feature\Showtimes;

use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\RefundCase;
use App\Models\ShowtimeCancellation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

final class ShowtimeCancellationAdminWorkflowTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_manager_reviews_impact_and_cancels_a_booked_showtime_with_required_confirmation(): void
    {
        $scenario = $this->bookingScenario(false);
        $manager = $this->userWithRole('manager');
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id], $manager->id)->booking;
        $booking->forceFill(['booking_status' => 'paid', 'payment_status' => 'paid', 'paid_at' => now()])->save();
        $payment = $this->successfulPayment($booking);

        $this->actingAs($manager)->get(route('admin.showtimes.index'))
            ->assertOk()
            ->assertSee('Xử lý hủy')
            ->assertDontSee('action="'.route('admin.showtimes.destroy', $scenario['showtime']).'"', false);
        $this->actingAs($manager)->get(route('admin.showtimes.cancellation', $scenario['showtime']))
            ->assertOk()
            ->assertSee('Tác động được tính lại từ dữ liệu hiện tại')
            ->assertSee(number_format((int) $payment->amount, 0, ',', '.'))
            ->assertSee('Payment gốc được giữ nguyên');

        $this->actingAs($manager)->delete(route('admin.showtimes.destroy', $scenario['showtime']), [
            'reason_code' => 'technical_issue',
        ])->assertSessionHasErrors('confirm_cancellation');
        $this->assertSame('active', $scenario['showtime']->fresh()->status);

        $this->actingAs($manager)->delete(route('admin.showtimes.destroy', $scenario['showtime']), [
            'reason_code' => 'technical_issue',
            'reason_note' => 'Máy chiếu hỏng nguồn.',
            'confirm_cancellation' => '1',
        ])->assertRedirect(route('admin.showtimes.show', $scenario['showtime']))
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $scenario['showtime']->fresh()->status);
        $this->assertSame('cancelled', $booking->fresh()->booking_status);
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertDatabaseHas('refund_cases', [
            'booking_id' => $booking->id,
            'payment_id' => $payment->id,
            'status' => RefundCase::STATUS_REQUIRED,
        ]);
    }

    public function test_refund_resolution_requires_exact_evidence_is_idempotent_and_never_mutates_payment(): void
    {
        $scenario = $this->bookingScenario(false);
        $manager = $this->userWithRole('manager');
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id], $manager->id)->booking;
        $booking->forceFill(['booking_status' => 'paid', 'payment_status' => 'paid', 'paid_at' => now()])->save();
        $payment = $this->successfulPayment($booking);
        $paymentSnapshot = $this->paymentSnapshot($payment->fresh());
        $this->actingAs($manager)->delete(route('admin.showtimes.destroy', $scenario['showtime']), [
            'reason_code' => 'safety_issue',
            'confirm_cancellation' => '1',
        ])->assertRedirect();
        $case = RefundCase::query()->sole();

        $this->actingAs($manager)->get(route('admin.refunds.index'))
            ->assertOk()
            ->assertSee($booking->booking_code)
            ->assertSee('Cần xử lý hoàn tiền')
            ->assertSee('Ghi nhận hoàn tiền')
            ->assertSee('không tự chuyển tiền');

        $evidence = [
            'resolved_amount' => $case->required_amount - 1,
            'resolution_method' => 'bank_transfer',
            'resolution_reference' => 'BANK-REF-20260819-001',
            'resolution_note' => 'Đã kiểm tra sao kê.',
            'confirm_resolution' => '1',
        ];
        $this->actingAs($manager)->patch(route('admin.refunds.update', $case), $evidence)
            ->assertSessionHasErrors('resolved_amount');
        $this->assertSame(RefundCase::STATUS_REQUIRED, $case->fresh()->status);

        $evidence['resolved_amount'] = $case->required_amount;
        $this->actingAs($manager)->patch(route('admin.refunds.update', $case), $evidence)
            ->assertRedirect(route('admin.refunds.index'))
            ->assertSessionHas('success');
        $this->assertSame(RefundCase::STATUS_RESOLVED, $case->fresh()->status);
        $this->assertSame(ShowtimeCancellation::STATUS_RESOLVED, $case->cancellation->fresh()->status);
        $this->assertSame($paymentSnapshot, $this->paymentSnapshot($payment->fresh()));
        $this->assertSame(1, ActivityLog::query()->where('action', 'refund_case.manual_resolution_recorded')->count());

        $this->actingAs($manager)->patch(route('admin.refunds.update', $case), $evidence)
            ->assertSessionHas('warning');
        $this->assertSame(1, ActivityLog::query()->where('action', 'refund_case.manual_resolution_recorded')->count());
        $this->assertSame($paymentSnapshot, $this->paymentSnapshot($payment->fresh()));
    }

    public function test_staff_cannot_cancel_showtimes_or_view_and_resolve_refunds(): void
    {
        $scenario = $this->bookingScenario(false);
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('admin.showtimes.cancellation', $scenario['showtime']))->assertForbidden();
        $this->actingAs($staff)->delete(route('admin.showtimes.destroy', $scenario['showtime']), [
            'reason_code' => 'technical_issue',
            'confirm_cancellation' => '1',
        ])->assertForbidden();
        $this->actingAs($staff)->get(route('admin.refunds.index'))->assertForbidden();
        $this->assertSame('active', $scenario['showtime']->fresh()->status);
    }

    public function test_customer_history_and_detail_show_honest_refund_states_without_a_usable_qr(): void
    {
        $scenario = $this->bookingScenario(false);
        $customer = $this->userWithRole('user');
        $manager = $this->userWithRole('manager');
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id], $customer->id)->booking;
        $booking->forceFill(['booking_status' => 'paid', 'payment_status' => 'paid', 'paid_at' => now()])->save();
        $this->successfulPayment($booking);
        $this->actingAs($manager)->delete(route('admin.showtimes.destroy', $scenario['showtime']), [
            'reason_code' => 'facility_issue',
            'confirm_cancellation' => '1',
        ])->assertRedirect();

        $this->actingAs($customer)->get(route('user.bookings.history'))
            ->assertOk()
            ->assertSee('Suất chiếu đã bị rạp hủy')
            ->assertSee('Cần xử lý hoàn tiền')
            ->assertSee('Xem lịch sử đơn');
        $this->actingAs($customer)->get(route('user.bookings.ticket', $booking))
            ->assertOk()
            ->assertSee('chỉ dùng để tra cứu')
            ->assertSee('QR đơn đặt vé đã chuyển sang trạng thái lịch sử')
            ->assertDontSee('data-qr-value', false);
        $this->actingAs($customer)->get(route('user.bookings.success', $booking))
            ->assertOk()
            ->assertSee('Suất chiếu đã bị rạp hủy')
            ->assertSee('Cần xử lý hoàn tiền');

        $case = RefundCase::query()->sole();
        $this->actingAs($manager)->patch(route('admin.refunds.update', $case), [
            'resolved_amount' => $case->required_amount,
            'resolution_method' => 'bank_transfer',
            'resolution_reference' => 'BANK-CUSTOMER-STATE-001',
            'confirm_resolution' => '1',
        ])->assertSessionHas('success');
        $this->actingAs($customer)->get(route('user.bookings.history'))
            ->assertOk()
            ->assertSee('Đã ghi nhận hoàn tiền');

        $unpaidScenario = $this->bookingScenario(false);
        $unpaid = $this->reserve($unpaidScenario, [$unpaidScenario['seats'][0]->id], $customer->id)->booking;
        $this->actingAs($manager)->delete(route('admin.showtimes.destroy', $unpaidScenario['showtime']), [
            'reason_code' => 'schedule_change',
            'confirm_cancellation' => '1',
        ])->assertRedirect();
        $this->actingAs($customer)->get(route('user.bookings.ticket', $unpaid))
            ->assertOk()
            ->assertSee('Bạn chưa có khoản thanh toán cần hoàn.');
    }

    private function successfulPayment($booking): Payment
    {
        return Payment::createForProvider('vnpay', [
            'booking_id' => $booking->id,
            'payment_method' => 'vnpay',
            'order_code' => 'ADMIN-CANCEL-'.str()->upper(str()->random(12)),
            'amount' => (int) $booking->total_amount,
            'currency' => 'VND',
            'status' => Payment::STATUS_SUCCESS,
            'verified_at' => now(),
            'paid_at' => now(),
            'transaction_id' => 'VNP-'.str()->upper(str()->random(12)),
        ]);
    }

    private function paymentSnapshot(Payment $payment): array
    {
        return collect([
            'id', 'booking_id', 'provider', 'status', 'amount', 'currency', 'order_code',
            'transaction_id', 'verified_at', 'paid_at', 'settled_at', 'settled_by_user_id',
        ])->mapWithKeys(fn (string $key): array => [$key => $payment->getRawOriginal($key)])->all();
    }
}
