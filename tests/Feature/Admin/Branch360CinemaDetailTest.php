<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use App\Services\CinemaAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class Branch360CinemaDetailTest extends TestCase
{
    use RefreshDatabase;

    private Cinema $cinema;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
        $this->cinema = Cinema::query()->active()->primary()->firstOrFail();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-13 00:30:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_existing_cinema_detail_becomes_branch_360_without_losing_configuration_workflows(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)
            ->withSession([CinemaAccessService::SESSION_KEY => $this->cinema->id])
            ->get(route('admin.cinemas.show', $this->cinema))
            ->assertOk()
            ->assertSee('Branch 360')
            ->assertSee('Giờ chi nhánh')
            ->assertSee('Cần xử lý')
            ->assertSee('Hiện không có việc khẩn cấp cần xử lý.')
            ->assertSee('Chưa cấu hình giờ hoạt động')
            ->assertSee(route('admin.cinemas.edit', $this->cinema))
            ->assertSee(route('admin.rooms.create'))
            ->assertSee(route('admin.cinemas.operating-hours.update', $this->cinema))
            ->assertSee('Manager và Staff');

        $html = $response->getContent();
        foreach (['Doanh thu hôm nay', 'Tỷ lệ lấp đầy', 'Đang chiếu', 'Đang vệ sinh', 'Sẵn sàng',
            'checked in', 'Đã check-in', 'attendance', 'đã quét vé', 'food redeemed', 'nhân viên đang làm'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }
    }

    public function test_authorization_matrix_preserves_global_manager_foreign_staff_and_customer_boundaries(): void
    {
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('manager');
        $staff = $this->userWithRole('staff');
        $customer = $this->userWithRole('user');
        $foreign = Cinema::factory()->create(['status' => 'active', 'archived_at' => null]);

        $this->actingAs($admin)->get(route('admin.cinemas.show', $foreign))->assertOk()->assertSee('Branch 360');
        $this->actingAs($manager)->withSession([CinemaAccessService::SESSION_KEY => $this->cinema->id])
            ->get(route('admin.cinemas.show', $this->cinema))->assertOk();
        $this->get(route('admin.cinemas.show', $foreign))->assertNotFound();
        $this->actingAs($staff)->get(route('admin.cinemas.show', $this->cinema))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.cinemas.show', $this->cinema))->assertForbidden();
    }

    public function test_payment_action_is_privacy_safe_and_points_to_an_accessible_existing_workflow(): void
    {
        $manager = $this->userWithRole('manager');
        $payment = $this->payment(Payment::STATUS_REVIEW);

        $response = $this->actingAs($manager)
            ->withSession([CinemaAccessService::SESSION_KEY => $this->cinema->id])
            ->get(route('admin.cinemas.show', $this->cinema))
            ->assertOk()
            ->assertSee($payment->booking->booking_code)
            ->assertSee('Mở đối soát')
            ->assertSee(route('admin.payment-reconciliation.index'))
            ->assertDontSee($payment->booking->customer_email)
            ->assertDontSee($payment->booking->customer_phone)
            ->assertDontSee($payment->order_code);

        $this->get(route('admin.payment-reconciliation.index'))->assertOk();
        $this->assertStringNotContainsString('gateway_payload', $response->getContent());
    }

    public function test_branch_360_request_query_count_is_bounded_when_task_rows_grow(): void
    {
        $manager = $this->userWithRole('manager');
        $session = [CinemaAccessService::SESSION_KEY => $this->cinema->id];
        $zero = $this->requestQueryCount($manager, $session);
        $this->payment(Payment::STATUS_REVIEW);
        $small = $this->requestQueryCount($manager, $session);
        foreach (range(1, 10) as $index) {
            $this->payment($index % 2 === 0 ? Payment::STATUS_REVIEW : Payment::STATUS_UNRESOLVED);
        }
        $many = $this->requestQueryCount($manager, $session);

        $this->assertSame($small, $many, "zero={$zero}; small={$small}; many={$many}");
        $this->assertLessThanOrEqual(17, $many, "zero={$zero}; small={$small}; many={$many}");

        if (env('REPORT_QUERY_COUNTS')) {
            fwrite(STDOUT, "PHASE5B_QUERY_COUNTS=zero:{$zero},small:{$small},many:{$many}".PHP_EOL);
        }
    }

    private function payment(string $status): Payment
    {
        $scenario = $this->showtimeScenario();
        $booking = Booking::query()->create([
            'showtime_id' => $scenario['showtime']->id,
            'booking_code' => 'B360-PAY-'.str_pad((string) $this->sequence, 3, '0', STR_PAD_LEFT),
            'customer_name' => 'Private Person',
            'customer_email' => 'private-'.$this->sequence.'@example.test',
            'customer_phone' => '0900000'.$this->sequence,
            'total_amount' => 50_000,
            'payment_status' => 'unpaid',
            'booking_status' => 'pending_payment',
        ]);

        return Payment::createForProvider('vnpay', [
            'booking_id' => $booking->id,
            'payment_method' => 'vnpay',
            'order_code' => 'PRIVATE-GATEWAY-'.$this->sequence,
            'amount' => 50_000,
            'currency' => 'VND',
            'status' => $status,
        ])->load('booking');
    }

    /** @return array<string, mixed> */
    private function showtimeScenario(): array
    {
        $this->sequence++;
        $room = Room::query()->create([
            'cinema_id' => $this->cinema->id,
            'code' => 'UI'.str_pad((string) $this->sequence, 2, '0', STR_PAD_LEFT),
            'name' => 'UI Room '.$this->sequence,
            'room_type' => '2D',
            'total_seats' => 1,
            'status' => 'active',
        ]);
        $seat = Seat::query()->create([
            'room_id' => $room->id,
            'row' => 'A',
            'number' => 1,
            'seat_code' => 'A1',
            'type' => 'normal',
            'status' => 'active',
        ]);
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id,
            'version' => 1,
            'name' => 'UI Layout '.$this->sequence,
            'rows' => 1,
            'columns' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $layout->cells()->create([
            'x_position' => 1,
            'y_position' => 1,
            'cell_type' => 'seat',
            'seat_id' => $seat->id,
        ]);
        $movie = Movie::query()->create([
            'title' => 'UI Movie '.$this->sequence,
            'slug' => 'ui-movie-'.$this->sequence,
            'duration' => 90,
            'status' => 'now_showing',
        ]);
        $showtime = Showtime::query()->create([
            'movie_id' => $movie->id,
            'cinema_id' => $this->cinema->id,
            'room_id' => $room->id,
            'room_layout_id' => $layout->id,
            'presentation_format_id' => $this->presentationFormatFixture($movie, $room)->id,
            'show_date' => '2026-08-14',
            'show_time' => '20:00:00',
            'price' => 50_000,
            'status' => 'active',
        ]);

        return compact('room', 'seat', 'layout', 'movie', 'showtime');
    }

    /** @param array<string, mixed> $session */
    private function requestQueryCount(User $actor, array $session): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($actor)->withSession($session)
            ->get(route('admin.cinemas.show', $this->cinema))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
