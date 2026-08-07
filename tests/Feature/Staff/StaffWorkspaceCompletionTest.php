<?php

namespace Tests\Feature\Staff;

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

    public function test_staff_workspace_navigation_and_operational_histories_are_available_without_admin_access(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('staff.dashboard'))
            ->assertOk()->assertSee('Bàn làm việc hôm nay')->assertSee('Giao dịch hôm nay');
        $this->get(route('staff.sales.index'))->assertOk()->assertSee('Giao dịch tại chi nhánh');
        $this->get(route('staff.prints.index'))->assertOk()->assertSee('Vé cần in');
        $this->get(route('staff.checkins.index'))->assertOk()->assertSee('Lịch sử soát vé');
        $this->actingAs($this->userWithRole('user'))->get(route('staff.sales.index'))->assertForbidden();

        $this->assertFalse($staff->hasPermission('admin.access'));
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

    public function test_representative_staff_pages_keep_bounded_query_counts(): void
    {
        $staff = $this->userWithRole('staff');

        foreach ([
            'dashboard' => route('staff.dashboard'),
            'counter' => route('staff.counter.index'),
            'sales' => route('staff.sales.index'),
            'print_queue' => route('staff.prints.index'),
            'checkin_history' => route('staff.checkins.index'),
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
