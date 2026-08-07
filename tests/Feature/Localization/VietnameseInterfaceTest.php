<?php

namespace Tests\Feature\Localization;

use App\Models\Room;
use App\Services\CinemaContext;
use App\Support\StatusLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class VietnameseInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        app()->setLocale('vi');
    }

    public function test_representative_customer_admin_manager_and_staff_pages_are_vietnamese(): void
    {
        $room = Room::factory()->create(['cinema_id' => app(CinemaContext::class)->id()]);

        $this->get(route('home'))->assertOk()->assertSee('Đang chiếu')->assertSee('Sắp chiếu');
        $this->get(route('login'))->assertOk()->assertSee('Đăng nhập')->assertSee('Mật khẩu');
        $this->get(route('user.movies.index'))->assertOk()->assertSee('Phim');

        $this->actingAs($this->userWithRole('admin'))->get(route('admin.dashboard'))
            ->assertOk()->assertSee('Tổng quan');
        $this->actingAs($this->userWithRole('admin'))->get(route('admin.rooms.index'))
            ->assertOk()->assertSee('Quản lý phòng chiếu')->assertSee($room->status_label);
        $this->actingAs($this->userWithRole('admin'))->get(route('admin.movies.index'))
            ->assertOk()->assertSee('Quản lý phim');
        $this->actingAs($this->userWithRole('admin'))->get(route('admin.showtimes.index'))
            ->assertOk()->assertSee('Quản lý suất chiếu');

        $this->actingAs($this->userWithRole('manager'))->get(route('manager.dashboard'))
            ->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->userWithRole('staff'))->get(route('staff.dashboard'))
            ->assertOk()->assertSee('Bàn làm việc hôm nay');
        $this->actingAs($this->userWithRole('staff'))->get(route('staff.tickets.check'))
            ->assertOk()->assertSee('Soát vé');
    }

    public function test_framework_translation_and_status_presenter_never_expose_internal_values(): void
    {
        $this->assertSame('Trường tên phòng là bắt buộc.', trans('validation.required', ['attribute' => 'tên phòng']));
        $this->assertSame('Thông tin đăng nhập không chính xác.', trans('auth.failed'));
        $this->assertSame('Chờ thanh toán', StatusLabel::for('booking', 'pending_payment'));
        $this->assertSame('Đang chiếu', StatusLabel::for('movie', 'now_showing'));
        $this->assertSame('Đã gửi', StatusLabel::for('ticket_delivery', 'sent'));
        $this->assertSame('Chưa xác định', StatusLabel::for('payment', 'smtp_authentication_failed'));
    }

    public function test_high_confidence_user_visible_english_detector_is_clean(): void
    {
        $roots = [
            resource_path('views'),
            resource_path('js'),
            app_path('Http/Controllers'),
            app_path('Http/Requests'),
            app_path('Mail'),
        ];
        $forbidden = [
            'Now Showing', 'Coming Soon', 'Payment review', 'Payments requiring an operator decision',
            'Query existing order', 'No payments are in review', 'Cinema rooms', 'Seat map',
            'Dynamic Layout Editor', 'Published layout', 'Preview layout', 'Admin Login',
            'Staff Panel', 'Backend TEAM', 'backend TEAM', 'Food created', 'Food updated',
            'Food deleted', 'Movie created successfully', 'Movie updated successfully',
            'Movie deleted successfully', 'Genre created successfully', 'Genre updated successfully',
            'Genre deleted successfully', 'Booking này', 'Mã booking',
        ];
        $findings = [];

        foreach ($roots as $root) {
            if (! File::isDirectory($root)) {
                continue;
            }
            foreach (File::allFiles($root) as $file) {
                if (! in_array($file->getExtension(), ['php', 'js', 'ts'], true)) {
                    continue;
                }
                $contents = File::get($file->getPathname());
                foreach ($forbidden as $phrase) {
                    if (str_contains($contents, $phrase)) {
                        $findings[] = $file->getRelativePathname().": {$phrase}";
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($findings)), "Chuỗi tiếng Anh hiển thị cần xem lại:\n".implode("\n", $findings));
    }

    public function test_error_pages_and_ticket_templates_use_safe_vietnamese_copy(): void
    {
        $expectations = [
            'errors/403.blade.php' => 'Bạn không có quyền truy cập nội dung này.',
            'errors/404.blade.php' => 'Không tìm thấy trang',
            'errors/419.blade.php' => 'Phiên làm việc đã hết hạn',
            'errors/422.blade.php' => 'Không thể xử lý yêu cầu',
            'errors/429.blade.php' => 'Thao tác quá nhanh',
            'errors/500.blade.php' => 'Hệ thống tạm gián đoạn',
            'errors/503.blade.php' => 'Hệ thống đang bảo trì',
            'emails/booking-ticket.blade.php' => 'Mã vé',
            'user/bookings/ticket.blade.php' => 'Vé điện tử',
        ];

        foreach ($expectations as $view => $copy) {
            $this->assertStringContainsString($copy, File::get(resource_path("views/{$view}")), $view);
        }
    }
}
