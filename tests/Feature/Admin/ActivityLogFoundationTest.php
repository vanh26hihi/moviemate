<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Movie;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\ActivityLogger;
use App\Services\ActivityLogSanitizer;
use App\Services\CinemaContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class ActivityLogFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_schema_and_admin_routes_are_read_only(): void
    {
        $this->assertTrue(Schema::hasColumns('activity_logs', [
            'id', 'actor_user_id', 'actor_role_snapshot', 'action', 'subject_type', 'subject_id',
            'subject_label', 'request_id', 'route_name', 'method', 'safe_ip_hash',
            'user_agent_summary', 'before_data', 'after_data', 'context', 'created_at',
        ]));

        foreach (['admin.activity-logs.index', 'admin.activity-logs.show'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertSame(['GET', 'HEAD'], $route->methods());
        }

        $activityRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (IlluminateRoute $route): bool => str_starts_with($route->uri(), 'admin/activity-logs'));
        $this->assertCount(2, $activityRoutes);
        $this->assertTrue($activityRoutes->every(
            fn (IlluminateRoute $route): bool => array_diff($route->methods(), ['GET', 'HEAD']) === []
        ));
    }

    public function test_activity_log_rejects_model_and_database_updates_and_deletes(): void
    {
        $log = ActivityLog::query()->create([
            'action' => 'security.tested',
            'subject_type' => Room::class,
            'subject_id' => '1',
        ]);

        try {
            $log->update(['action' => 'security.changed']);
            $this->fail('Model update should have been rejected.');
        } catch (LogicException) {
            $this->assertSame('security.tested', $log->fresh()->action);
        }

        try {
            DB::table('activity_logs')->where('id', $log->id)->update(['action' => 'security.changed']);
            $this->fail('Database update should have been rejected.');
        } catch (QueryException) {
            $this->assertDatabaseHas('activity_logs', ['id' => $log->id, 'action' => 'security.tested']);
        }

        try {
            DB::table('activity_logs')->where('id', $log->id)->delete();
            $this->fail('Database delete should have been rejected.');
        } catch (QueryException) {
            $this->assertDatabaseHas('activity_logs', ['id' => $log->id]);
        }
    }

    public function test_logger_whitelists_context_masks_personal_data_and_never_stores_secrets(): void
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
        $admin = $this->userWithRole('admin');
        $room = Room::factory()->create(['cinema_id' => app(CinemaContext::class)->id(), 'code' => 'AUD-01']);
        $ip = '203.0.113.42';
        $request = Request::create('/admin/rooms/'.$room->id.'/status', 'PATCH', server: [
            'REMOTE_ADDR' => $ip,
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/126.0.0.0 session-private-value',
            'HTTP_X_REQUEST_ID' => 'audit-request-0001',
        ]);
        $request->setUserResolver(fn () => $admin);
        $route = new IlluminateRoute(['PATCH'], 'admin/rooms/{room}/status', fn () => null);
        $route->name('admin.rooms.status.update');
        $request->setRouteResolver(fn () => $route);

        $log = (new ActivityLogger($request, new ActivityLogSanitizer))->log(
            'room.status_changed',
            $room,
            [
                'status' => 'active',
                'password' => 'password-secret',
                'smtp_password' => 'smtp-secret',
                'vnp_SecureHash' => 'provider-secret',
            ],
            [
                'status' => 'inactive',
                'guest_booking_token' => 'guest-secret',
                'ticket_email_token' => 'ticket-secret',
                'signed_payment_url' => 'https://pay.example.test/signed?token=secret',
            ],
            [
                'source' => 'admin',
                'reason' => 'Liên hệ audit@example.test hoặc 0901234567',
                'raw_provider_payload' => ['token' => 'payload-secret'],
                'authorization' => 'Bearer credential-secret',
            ],
        );

        $stored = json_encode($log->fresh()->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        foreach ([
            'password-secret', 'smtp-secret', 'provider-secret', 'guest-secret', 'ticket-secret',
            'pay.example.test', 'payload-secret', 'credential-secret', 'audit@example.test', '0901234567',
            'session-private-value', $ip,
        ] as $sensitiveValue) {
            $this->assertStringNotContainsString($sensitiveValue, $stored);
        }

        $this->assertSame(['status' => 'active'], $log->before_data);
        $this->assertSame(['status' => 'inactive'], $log->after_data);
        $this->assertSame('audit-request-0001', $log->request_id);
        $this->assertSame('admin.rooms.status.update', $log->route_name);
        $this->assertSame('PATCH', $log->method);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $log->safe_ip_hash);
        $this->assertSame('Chrome / Windows', $log->user_agent_summary);
    }

    public function test_only_authorized_admin_can_view_and_filter_safe_activity_history(): void
    {
        $admin = $this->userWithRole('admin');
        $log = ActivityLog::query()->create([
            'actor_user_id' => $admin->id,
            'actor_role_snapshot' => 'admin',
            'action' => 'room.status_changed',
            'subject_type' => Room::class,
            'subject_id' => '7',
            'subject_label' => 'Phòng AUD-07',
            'request_id' => 'request-filter-0001',
            'route_name' => 'admin.rooms.status.update',
            'method' => 'PATCH',
            'before_data' => ['status' => 'active'],
            'after_data' => ['status' => 'inactive'],
        ]);

        $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.activity-logs.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.activity-logs.index', [
                'actor' => (string) $admin->id,
                'action' => $log->action,
                'subject_type' => $log->subject_type,
                'route' => $log->route_name,
                'request_id' => $log->request_id,
                'date_from' => now()->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee($log->action)
            ->assertSee($log->request_id);

        $this->actingAs($admin)
            ->get(route('admin.activity-logs.show', $log))
            ->assertOk()
            ->assertSee('room.status_changed')
            ->assertSee('active')
            ->assertSee('inactive');

        $this->actingAs($admin)
            ->get(route('admin.activity-logs.index', ['date_to' => now()->toDateString()]))
            ->assertOk()
            ->assertSee($log->action);
    }

    public function test_successful_room_status_change_creates_one_event_and_one_notification(): void
    {
        $admin = $this->userWithRole('admin');
        $room = Room::factory()->create(['cinema_id' => app(CinemaContext::class)->id(), 'status' => 'active']);
        $message = 'Đã ngừng hoạt động phòng chiếu. Sơ đồ ghế và lịch sử được giữ nguyên.';

        $html = $this->from(route('admin.rooms.show', $room))
            ->actingAs($admin)
            ->followingRedirects()
            ->patch(route('admin.rooms.status.update', $room), ['status' => 'inactive'])
            ->assertOk()
            ->getContent();

        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseHas('activity_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'room.status_changed',
            'subject_type' => Room::class,
            'subject_id' => (string) $room->id,
        ]);
        $this->assertSame(1, substr_count(strip_tags($html), $message));
    }

    public function test_failed_sensitive_action_does_not_create_false_success_event(): void
    {
        $admin = $this->userWithRole('admin');
        $room = Room::factory()->create(['cinema_id' => app(CinemaContext::class)->id(), 'status' => 'active']);
        $movie = Movie::query()->create(['title' => 'Phim audit', 'slug' => 'phim-audit']);
        Showtime::query()->create([
            'movie_id' => $movie->id,
            'cinema_id' => $room->cinema_id,
            'room_id' => $room->id,
            'presentation_format_id' => $this->presentationFormatFixture($movie, $room)->id,
            'show_date' => now()->addDay()->toDateString(),
            'show_time' => '20:00:00',
            'price' => 80000,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.rooms.status.update', $room), ['status' => 'inactive'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertSame('active', $room->fresh()->status);
    }

    public function test_seat_maintenance_update_creates_one_event(): void
    {
        $admin = $this->userWithRole('admin');
        $room = Room::factory()->create(['cinema_id' => app(CinemaContext::class)->id(), 'status' => 'active']);
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
            'name' => 'Sơ đồ hiện hành',
            'rows' => 1,
            'columns' => 1,
            'screen_position' => 'top',
            'status' => 'published',
            'published_at' => now(),
        ]);
        RoomLayoutCell::query()->create([
            'room_layout_id' => $layout->id,
            'x_position' => 1,
            'y_position' => 1,
            'cell_type' => 'seat',
            'seat_id' => $seat->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $seat]), ['status' => 'maintenance'])
            ->assertSessionHas('success');

        $this->assertSame('maintenance', $seat->fresh()->status);
        $this->assertSame(1, ActivityLog::query()->where('action', 'seat.maintenance_updated')->count());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'seat.maintenance_updated',
            'subject_id' => (string) $seat->id,
            'actor_user_id' => $admin->id,
        ]);
    }
}
