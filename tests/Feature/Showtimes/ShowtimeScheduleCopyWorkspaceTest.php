<?php

namespace Tests\Feature\Showtimes;

use App\Models\Cinema;
use App\Models\Room;

class ShowtimeScheduleCopyWorkspaceTest extends ShowtimeTestCase
{
    public function test_workspace_rbac_and_branch_scoping_expose_both_copy_modes_without_a_publish_action(): void
    {
        $otherCinema = Cinema::factory()->create([
            'code' => 'FOREIGN',
            'name' => 'Chi nhánh ngoài quyền',
            'status' => 'active',
            'archived_at' => null,
        ]);
        Room::factory()->create(['cinema_id' => $otherCinema->id, 'code' => 'FOREIGN-ROOM']);

        $this->actingAs($this->userWithRole('manager'))->get(route('admin.showtimes.copy.index'))
            ->assertOk()
            ->assertSee('data-showtime-schedule-copy', false)
            ->assertSee('value="room"', false)
            ->assertSee('value="cinema"', false)
            ->assertSee('name="source_date"', false)
            ->assertSee('name="target_date"', false)
            ->assertSee('data-copy-room', false)
            ->assertSee('P01')
            ->assertDontSee('FOREIGN-ROOM')
            ->assertSee(route('admin.showtimes.copy.generate'), false)
            ->assertDontSee('data-publish-endpoint', false);

        foreach (['staff', 'user'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('admin.showtimes.copy.index'))->assertForbidden();
        }
    }

    public function test_global_admin_selects_one_cinema_and_showtime_index_links_to_copy_workspace(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('admin.showtimes.copy.index'))
            ->assertOk()
            ->assertSee('data-copy-cinema', false)
            ->assertSee($this->cinema->name);
        $this->actingAs($admin)->get(route('admin.showtimes.index'))
            ->assertOk()
            ->assertSee(route('admin.showtimes.copy.index'), false)
            ->assertSee('Sao chép lịch');
    }

    public function test_form_generation_redirects_exact_intent_rows_into_existing_bulk_workspace_without_publishing(): void
    {
        $movie = $this->movie(90, ['title' => 'Phim nguồn sao chép']);
        $room = $this->rooms['P01'];
        $this->existing($movie, $room, [
            'show_date' => '2025-08-12',
            'show_time' => '09:30:00',
        ]);
        $manager = $this->userWithRole('manager');

        $response = $this->actingAs($manager)->post(route('admin.showtimes.copy.generate'), [
            'scope' => 'room',
            'cinema_id' => $this->cinema->id,
            'room_id' => $room->id,
            'source_date' => '2025-08-12',
            'target_date' => '2030-08-19',
        ])->assertRedirect(route('admin.showtimes.bulk.index'));

        $response->assertSessionHas('bulk_showtime_rows', [[
            'row_key' => 'copy-1',
            'movie_id' => $movie->id,
            'room_id' => $room->id,
            'show_date' => '2030-08-19',
            'show_time' => '09:30',
        ]]);
        $this->actingAs($manager)->get(route('admin.showtimes.bulk.index'))
            ->assertOk()
            ->assertSee('data-bulk-initial-rows', false)
            ->assertSee('"row_key":"copy-1"', false)
            ->assertSee('"show_date":"2030-08-19"', false)
            ->assertSee('Phim nguồn sao chép');
        $this->assertDatabaseCount('showtimes', 1);
        $this->assertDatabaseMissing('showtimes', ['show_date' => '2030-08-19 00:00:00']);
    }

    public function test_bulk_handoff_keeps_now_unavailable_source_identity_visible_for_authoritative_preview(): void
    {
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $movie->update(['status' => 'ended']);
        $room->update(['status' => 'maintenance']);

        $this->actingAs($this->userWithRole('manager'))
            ->withSession(['bulk_showtime_rows' => [[
                'row_key' => 'copy-1',
                'movie_id' => $movie->id,
                'room_id' => $room->id,
                'show_date' => '2030-08-19',
                'show_time' => '09:30',
            ]]])
            ->get(route('admin.showtimes.bulk.index'))
            ->assertOk()
            ->assertSee('value="'.$movie->id.'"', false)
            ->assertSee('value="'.$room->id.'"', false)
            ->assertSee('không còn khả dụng');
    }

    public function test_copy_frontend_only_toggles_local_controls_and_bulk_handoff_starts_unpreviewed(): void
    {
        $copyJavascript = file_get_contents(resource_path('js/showtime-schedule-copy.js'));
        $bulkJavascript = file_get_contents(resource_path('js/bulk-showtime-scheduling.js'));

        $this->assertIsString($copyJavascript);
        $this->assertStringContainsString("input.addEventListener('change', sync)", $copyJavascript);
        $this->assertStringContainsString("cinema?.addEventListener('change', sync)", $copyJavascript);
        $this->assertStringNotContainsString('new Date(', $copyJavascript);
        $this->assertStringNotContainsString('fetch(', $copyJavascript);
        $this->assertStringContainsString('initialRows.forEach((row) => addRow(row))', $bulkJavascript);
        $this->assertStringContainsString('publishButton.disabled = true', $bulkJavascript);
        $this->assertStringNotContainsString('preview().then(publish', $bulkJavascript);
    }
}
