<?php

namespace Tests\Feature\Showtimes;

class BulkShowtimeWorkspaceTest extends ShowtimeTestCase
{
    public function test_workspace_is_available_to_admin_and_manager_but_not_staff_or_customer(): void
    {
        foreach (['admin', 'manager'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('admin.showtimes.bulk.index'))
                ->assertOk()
                ->assertSee('data-bulk-showtime-workspace', false)
                ->assertSee('data-preview-endpoint="'.route('admin.showtimes.bulk.preview').'"', false)
                ->assertSee('data-publish-endpoint="'.route('admin.showtimes.bulk.store').'"', false)
                ->assertSee('data-bulk-add-row', false)
                ->assertSee('data-bulk-remove-row', false)
                ->assertSee('data-bulk-preview', false)
                ->assertSee('data-bulk-publish', false)
                ->assertSee('Tổng số suất')
                ->assertSee('Hợp lệ')
                ->assertSee('Không hợp lệ');
        }

        foreach (['staff', 'user'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('admin.showtimes.bulk.index'))->assertForbidden();
        }
    }

    public function test_showtime_index_links_to_bulk_workspace_only_for_create_permission(): void
    {
        $this->actingAs($this->userWithRole('manager'))->get(route('admin.showtimes.index'))
            ->assertOk()->assertSee(route('admin.showtimes.bulk.index'), false)->assertSee('Tạo nhiều suất');
        $this->actingAs($this->userWithRole('staff'))->get(route('admin.showtimes.index'))
            ->assertForbidden();
    }

    public function test_frontend_contract_invalidates_stale_preview_and_never_auto_publishes(): void
    {
        $javascript = file_get_contents(resource_path('js/bulk-showtime-scheduling.js'));
        $this->assertIsString($javascript);
        $this->assertStringContainsString("input.addEventListener('change', invalidatePreview)", $javascript);
        $this->assertStringContainsString('row.remove()', $javascript);
        $this->assertStringContainsString('addButton.addEventListener', $javascript);
        $this->assertStringContainsString('publishButton.disabled = true', $javascript);
        $this->assertStringContainsString('publishButton.disabled = !data.valid', $javascript);
        $this->assertStringContainsString('expectedVersion !== version', $javascript);
        $this->assertStringContainsString('new AbortController()', $javascript);
        $this->assertStringContainsString("form.addEventListener('submit', publish)", $javascript);
        $this->assertStringNotContainsString('preview().then(publish', $javascript);
        $this->assertStringNotContainsString('new Date(', $javascript);
    }
}
