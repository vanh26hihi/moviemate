<?php

namespace Tests\Feature\Staff;

use App\Models\Booking;
use App\Models\BookingTicketPrint;
use App\Models\Cinema;
use App\Models\Payment;
use App\Models\UserCinemaAssignment;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Payments\PaymentTestCase;

final class StaffWorkspaceCompletionTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_staff_print_workspace_is_available_without_admin_or_checkin_access(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('staff.dashboard'))
            ->assertOk()->assertSee('Bàn làm việc hôm nay')->assertSee('Giao dịch hôm nay');
        $this->get(route('staff.sales.index'))->assertOk()->assertSee('Giao dịch tại chi nhánh');
        $this->get(route('staff.prints.index'))->assertOk()->assertSee('Vé cần in');
        $this->get(route('staff.tickets.index'))->assertOk()->assertSee('Tra cứu & in đơn');
        $this->actingAs($this->userWithRole('user'))->get(route('staff.sales.index'))->assertForbidden();

        $this->assertFalse($staff->hasPermission('admin.access'));
        $this->assertFalse(app('router')->has('staff.checkins.index'));
    }

    public function test_unassigned_staff_receives_safe_empty_workspace_without_branch_data(): void
    {
        $staff = $this->userWithRole('staff');
        UserCinemaAssignment::query()->where('user_id', $staff->id)->delete();

        $this->actingAs($staff)->get(route('staff.dashboard'))
            ->assertOk()->assertSee('Bạn chưa được phân công chi nhánh');
        $this->get(route('staff.counter.index'))
            ->assertOk()->assertSee('Bạn chưa được phân công chi nhánh');
        $this->get(route('staff.sales.index'))
            ->assertOk()->assertSee('Bạn chưa được phân công chi nhánh');
    }

    public function test_dashboard_presents_actionable_paid_print_and_personal_hold_work(): void
    {
        $staff = $this->userWithRole('staff');
        $scenario = $this->bookingScenario(false);
        $scenario['showtime']->forceFill([
            'show_date' => now('Asia/Ho_Chi_Minh')->toDateString(),
            'show_time' => now('Asia/Ho_Chi_Minh')->addHours(2)->format('H:i:s'),
        ])->save();

        $counterBooking = new Booking([
            'user_id' => null,
            'customer_email' => 'counter-customer@example.test',
            'showtime_id' => $scenario['showtime']->id,
            'booking_code' => 'MMT-COUNTER-DASHBOARD-TASK',
            'total_amount' => 50000,
            'payment_status' => 'unpaid',
            'booking_status' => 'pending_payment',
            'expires_at' => now()->addMinutes(8),
        ]);
        $counterBooking->forceFill([
            'sales_channel' => Booking::SALES_CHANNEL_COUNTER,
            'created_by_staff_id' => $staff->id,
        ]);
        $counterBooking->save();

        $otherStaff = $this->userWithRole('staff');
        $otherStaffBooking = $counterBooking->replicate();
        $otherStaffBooking->forceFill([
            'booking_code' => 'MMT-OTHER-STAFF-DASHBOARD-TASK',
            'created_by_staff_id' => $otherStaff->id,
        ])->save();

        $paid = $this->reserve($scenario, [$scenario['seats'][0]->id])->booking;
        $paid->forceFill([
            'payment_status' => 'paid',
            'booking_status' => 'paid',
            'paid_at' => now(),
        ])->save();
        $payment = $this->pendingPayment($paid, [
            'status' => Payment::STATUS_SUCCESS,
            'verified_at' => now(),
            'paid_at' => now(),
        ]);
        $ticket = $paid->admissionTickets()->sole();
        BookingTicketPrint::query()->create([
            'admission_ticket_id' => $ticket->id,
            'booking_id' => $paid->id,
            'status' => BookingTicketPrint::STATUS_RETRY_ALLOWED,
            'attempts_count' => 1,
        ]);

        $response = $this->actingAs($staff)->get(route('staff.dashboard'));

        $response->assertOk()
            ->assertSee('Việc cần xử lý ngay')
            ->assertSee('Đơn đã thanh toán hôm nay')
            ->assertSee('1 vé vật lý đã phát hành')
            ->assertSee('Đơn quầy đang giữ ghế')
            ->assertSee($counterBooking->booking_code)
            ->assertSee('Lần in cần xử lý')
            ->assertSee($paid->booking_code)
            ->assertSee(route('staff.counter.review', $counterBooking), false)
            ->assertSee(route('staff.tickets.operations', $paid), false)
            ->assertSee('Còn bán')
            ->assertDontSee('Vé bán hôm nay')
            ->assertDontSee($otherStaffBooking->booking_code);

        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertSame('pending_payment', $counterBooking->fresh()->booking_status);
        $this->assertSame('pending_payment', $otherStaffBooking->fresh()->booking_status);
        $this->assertSame(0, $ticket->fresh()->print_count);
    }

    public function test_dashboard_does_not_treat_an_unverified_success_marker_as_paid_authority(): void
    {
        $staff = $this->userWithRole('staff');
        $scenario = $this->bookingScenario(false);
        $paidWithoutEvidence = $this->reserve($scenario, [$scenario['seats'][0]->id])->booking;
        $paidWithoutEvidence->forceFill([
            'booking_code' => 'MMT-DASHBOARD-NO-AUTHORITY',
            'payment_status' => 'paid',
            'booking_status' => 'paid',
            'paid_at' => now(),
        ])->save();
        $payment = $this->pendingPayment($paidWithoutEvidence, [
            'status' => Payment::STATUS_SUCCESS,
            'verified_at' => null,
            'settled_at' => null,
            'paid_at' => now(),
        ]);

        $this->actingAs($staff)->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('0 vé vật lý đã phát hành')
            ->assertDontSee($paidWithoutEvidence->booking_code);

        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertSame(0, $paidWithoutEvidence->admissionTickets()->sole()->print_count);
    }

    public function test_dashboard_ignores_foreign_branch_query_parameters_and_data(): void
    {
        $staff = $this->userWithRole('staff');
        $scenario = $this->bookingScenario(false);
        $foreignCinema = Cinema::query()->create([
            'code' => 'STAFF-FOREIGN',
            'name' => 'Foreign Staff Cinema',
            'address' => '99 Foreign Street',
            'city' => 'Ha Noi',
            'canonical_key' => 'foreign-staff-cinema',
            'status' => 'active',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);
        $scenario['room']->forceFill(['cinema_id' => $foreignCinema->id])->save();
        $scenario['showtime']->forceFill([
            'cinema_id' => $foreignCinema->id,
            'show_date' => now('Asia/Ho_Chi_Minh')->toDateString(),
            'show_time' => now('Asia/Ho_Chi_Minh')->addHours(2)->format('H:i:s'),
        ])->save();
        $foreignBooking = $this->bookingForScenario($scenario, [
            'booking_code' => 'MMT-FOREIGN-DASHBOARD-TASK',
            'sales_channel' => Booking::SALES_CHANNEL_COUNTER,
            'created_by_staff_id' => $staff->id,
        ]);

        $this->actingAs($staff)->get(route('staff.dashboard', ['cinema_id' => $foreignCinema->id]))
            ->assertOk()
            ->assertSee($scenario['cinema']->name)
            ->assertDontSee($foreignCinema->name)
            ->assertDontSee($scenario['movie']->title)
            ->assertDontSee($foreignBooking->booking_code);

        $this->assertSame('pending_payment', $foreignBooking->fresh()->booking_status);
    }

    public function test_representative_staff_pages_keep_bounded_query_counts(): void
    {
        $staff = $this->userWithRole('staff');

        foreach ([
            'dashboard' => route('staff.dashboard'),
            'counter' => route('staff.counter.index'),
            'sales' => route('staff.sales.index'),
            'print_queue' => route('staff.prints.index'),
            'ticket_lookup' => route('staff.tickets.index'),
        ] as $surface => $url) {
            $count = $this->queryCount(fn () => $this->actingAs($staff)->get($url)->assertOk());
            $this->assertLessThanOrEqual(30, $count, $surface.' has an unexpected query count.');
        }
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
}
