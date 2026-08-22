<?php

namespace Tests\Feature\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class NotificationRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_component_renders_each_canonical_channel_once_with_accessible_bundled_icons(): void
    {
        $channels = [
            'error' => ['Lỗi kiểm thử duy nhất.', 'role="alert"', 'ph-warning-octagon'],
            'warning' => ['Cảnh báo kiểm thử duy nhất.', 'role="alert"', 'ph-warning-circle'],
            'success' => ['Thành công kiểm thử duy nhất.', 'role="status"', 'ph-check-circle'],
            'info' => ['Thông tin kiểm thử duy nhất.', 'role="status"', 'ph-info'],
            'status' => ['Trạng thái kiểm thử duy nhất.', 'role="status"', 'ph-info'],
        ];

        foreach ($channels as $channel => [$message, $role, $icon]) {
            session()->flush();
            session()->flash($channel, $message);
            $html = Blade::render('<x-flash-messages />');

            $this->assertSame(1, substr_count(strip_tags($html), $message), "Kênh {$channel} bị render lặp.");
            $this->assertStringContainsString($role, $html);
            $this->assertStringContainsString($icon, $html);
            $this->assertStringContainsString('aria-label="Đóng thông báo"', $html);
            $this->assertStringNotContainsString('http://', $html);
            $this->assertStringNotContainsString('https://', $html);
        }
    }

    public function test_shared_component_deduplicates_normalized_exact_messages_and_keeps_distinct_messages(): void
    {
        session()->flash('error', '  Mã ghế H13 đã tồn tại.  ');
        session()->flash('warning', 'Mã ghế H13   đã tồn tại.');
        session()->flash('success', 'Bản nháp khác đã được lưu.');

        $html = Blade::render('<x-flash-messages />');
        $text = preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '';

        $this->assertSame(1, substr_count($text, 'Mã ghế H13 đã tồn tại.'));
        $this->assertSame(1, substr_count($text, 'Bản nháp khác đã được lưu.'));
        $this->assertSame(2, substr_count($html, 'data-flash-banner'));
    }

    public function test_shared_component_deduplicates_the_same_flash_and_validation_message(): void
    {
        $duplicate = 'Không thể lưu thay đổi hiện tại.';
        $distinct = 'Mã ghế A7 cần được kiểm tra.';
        session()->flash('error', "  {$duplicate}  ");
        $errors = (new ViewErrorBag)->put('default', new MessageBag([
            'operation' => ['Không thể   lưu thay đổi hiện tại.'],
            'cells.6.seat_code' => [$distinct],
        ]));

        $html = Blade::render('<x-flash-messages :error-bag="$errors" />', compact('errors'));
        $text = preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '';

        $this->assertSame(1, substr_count($text, $duplicate));
        $this->assertSame(1, substr_count($text, $distinct));
        $this->assertSame(1, substr_count($html, 'data-flash-banner'));
    }

    public function test_error_bag_deduplication_is_presentation_only_and_preserves_field_associations(): void
    {
        $message = 'Mã ghế A1 không hợp lệ.';
        $messageBag = new MessageBag([
            'first_seat' => [$message],
            'second_seat' => ['  Mã ghế A1   không hợp lệ.  '],
            'room' => ['Phòng chiếu không hợp lệ.'],
        ]);
        $errors = (new ViewErrorBag)->put('default', $messageBag);

        $html = Blade::render('<x-validation-summary :errors="$errors" />', compact('errors'));
        $text = preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '';

        $this->assertSame(1, substr_count($text, $message));
        $this->assertSame(1, substr_count($text, 'Phòng chiếu không hợp lệ.'));
        $this->assertSame($message, $messageBag->first('first_seat'));
        $this->assertSame('  Mã ghế A1   không hợp lệ.  ', $messageBag->first('second_seat'));
    }

    public function test_user_admin_and_staff_layouts_render_one_shared_flash_instance(): void
    {
        $this->seedRbac();
        $message = 'Thông báo layout chỉ xuất hiện một lần.';

        $userResponse = $this->withSession(['success' => $message])->get(route('login'))->assertOk();
        $this->assertRenderedOnce($userResponse->getContent(), $message);

        $manager = $this->userWithRole('manager');
        $adminResponse = $this->actingAs($manager)->withSession(['success' => $message])
            ->get(route('admin.rooms.index'))->assertOk();
        $this->assertRenderedOnce($adminResponse->getContent(), $message);

        $staff = $this->userWithRole('staff');
        $staffResponse = $this->actingAs($staff)->withSession(['success' => $message])
            ->get(route('staff.dashboard'))->assertOk();
        $this->assertRenderedOnce($staffResponse->getContent(), $message);
    }

    public function test_field_level_form_errors_are_not_repeated_by_the_layout_summary(): void
    {
        $this->seedRbac();
        $message = 'Trường mã phòng là bắt buộc.';
        $manager = $this->userWithRole('manager');
        $response = $this->followingRedirects()->actingAs($manager)
            ->from(route('admin.rooms.create'))
            ->post(route('admin.rooms.store'), [
                'code' => '',
                'name' => 'Phòng kiểm thử thông báo',
                'room_type' => '2D',
                'status' => 'active',
            ])
            ->assertOk()
            ->assertSee('id="code-error"', false);

        $this->assertSame(1, substr_count(strip_tags($response->getContent()), $message));
        $this->assertSame(0, substr_count($response->getContent(), 'data-flash-banner'));
    }

    public function test_representative_operation_messages_render_once_across_application_layouts(): void
    {
        $this->seedRbac();
        $admin = $this->userWithRole('admin');
        $adminPages = [
            [route('admin.movies.index'), 'Đã cập nhật phim thành công.'],
            [route('admin.rooms.index'), 'Đã cập nhật phòng chiếu. Sơ đồ ghế và lịch sử đặt vé được giữ nguyên.'],
            [route('admin.rooms.index'), 'Đã ngừng hoạt động phòng chiếu. Sơ đồ ghế và lịch sử được giữ nguyên.'],
            [route('admin.rooms.index'), 'Đã xóa phòng chiếu chưa từng được sử dụng.'],
            [route('admin.showtimes.index'), 'Suất chiếu đã được cập nhật.'],
            [route('admin.foods.index'), 'Đã cập nhật món ăn.'],
            [route('admin.users.index'), 'Đã cập nhật trạng thái người dùng.'],
            [route('admin.payment-reviews.index'), 'Đã hoàn tất đối soát giao dịch.'],
        ];

        foreach ($adminPages as [$url, $message]) {
            $response = $this->actingAs($admin)->withSession(['success' => $message])->get($url)->assertOk();
            $this->assertRenderedOnce($response->getContent(), $message);
        }

        $customer = $this->userWithRole('user');
        foreach (['Đơn đặt vé đã được hủy.', 'Yêu cầu gửi lại tài liệu nhận vé đã được ghi nhận.'] as $message) {
            $response = $this->actingAs($customer)->withSession(['success' => $message])
                ->get(route('user.bookings.history'))->assertOk();
            $this->assertRenderedOnce($response->getContent(), $message);
        }

        $checkoutFailure = 'Không thể khởi tạo thanh toán lúc này.';
        $customerResponse = $this->actingAs($customer)->withSession(['error' => $checkoutFailure])
            ->get(route('user.movies.index'))->assertOk();
        $this->assertRenderedOnce($customerResponse->getContent(), $checkoutFailure);

        $staffMessage = 'Kết quả tra cứu đơn đã được ghi nhận.';
        $staffResponse = $this->actingAs($this->userWithRole('staff'))->withSession(['success' => $staffMessage])
            ->get(route('staff.tickets.index'))->assertOk();
        $this->assertRenderedOnce($staffResponse->getContent(), $staffMessage);
    }

    public function test_authorization_failure_does_not_add_a_notification_channel(): void
    {
        $this->seedRbac();

        $response = $this->actingAs($this->userWithRole('user'))
            ->get(route('admin.rooms.index'))
            ->assertForbidden();

        $this->assertSame(0, substr_count($response->getContent(), 'data-flash-banner'));
        $response->assertSessionMissing(['success', 'error', 'warning', 'info', 'status']);
    }

    public function test_source_audit_finds_one_layout_component_and_no_page_local_canonical_flash_blocks(): void
    {
        $layoutRoot = resource_path('views/layouts');
        foreach (['admin.blade.php', 'user.blade.php', 'staff.blade.php'] as $layout) {
            $source = File::get($layoutRoot.DIRECTORY_SEPARATOR.$layout);
            $this->assertSame(1, substr_count($source, '<x-flash-messages'), "Layout {$layout} phải include flash đúng một lần.");
            $this->assertSame(1, substr_count($source, 'resources/js/app.js'), "Layout {$layout} chỉ được nạp một Vite entry JavaScript.");
        }
        $this->assertStringNotContainsString('<x-flash-messages', File::get($layoutRoot.DIRECTORY_SEPARATOR.'app.blade.php'));

        $violations = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (str_contains($path, '/views/layouts/') || str_contains($path, '/views/components/')) {
                continue;
            }
            if (preg_match("/session\\('(success|error|warning|info|status)'\\)/", $file->getContents())) {
                $violations[] = $path;
            }
        }
        $this->assertSame([], $violations, 'Page còn render canonical flash cục bộ: '.implode(', ', $violations));

        $dualChannelControllers = [];
        foreach (File::allFiles(app_path('Http/Controllers')) as $file) {
            foreach (preg_split('/;/', $file->getContents()) ?: [] as $statement) {
                if (str_contains($statement, 'withErrors(') && str_contains($statement, "with('error'")) {
                    $dualChannelControllers[] = $file->getPathname();
                    break;
                }
            }
        }
        $this->assertSame([], $dualChannelControllers, 'Controller ghi cùng failure vào error bag và flash: '.implode(', ', $dualChannelControllers));
    }

    public function test_flash_dismissal_is_idempotent_and_no_server_flash_toast_bridge_exists(): void
    {
        $source = File::get(resource_path('js/app.js'));

        $this->assertSame(2, substr_count($source, 'flashDismissInitialized'));
        $this->assertSame(1, substr_count($source, "closest('[data-dismiss-flash]')"));
        $this->assertStringNotContainsString('MutationObserver', $source);
        $this->assertDoesNotMatchRegularExpression('/toast/i', $source);
    }

    public function test_notification_styles_cover_dark_and_light_themes_with_semantic_colours(): void
    {
        $component = File::get(resource_path('views/components/flash-messages.blade.php'));
        $css = File::get(resource_path('css/app.css'));

        foreach (['error', 'warning', 'success', 'info'] as $type) {
            $this->assertStringContainsString("flash-banner-{$type}", $component);
            $this->assertStringContainsString(".flash-banner-{$type}", $css);
            $this->assertStringContainsString("html.light .flash-banner-{$type}", $css);
        }
    }

    private function assertRenderedOnce(string $html, string $message): void
    {
        $this->assertSame(1, substr_count(strip_tags($html), $message));
        $this->assertSame(1, substr_count($html, 'data-flash-messages'));
    }
}
