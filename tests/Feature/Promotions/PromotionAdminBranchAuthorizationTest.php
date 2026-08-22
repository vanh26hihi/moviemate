<?php

namespace Tests\Feature\Promotions;

use App\Models\BookingPromotion;
use App\Models\Cinema;
use App\Models\Promotion;
use App\Models\UserCinemaAssignment;
use App\Services\CinemaAccessService;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

final class PromotionAdminBranchAuthorizationTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    private Cinema $cinemaA;

    private Cinema $cinemaB;

    private Cinema $cinemaC;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
        $this->cinemaA = Cinema::query()->active()->primary()->firstOrFail();
        $this->cinemaB = Cinema::factory()->create([
            'code' => 'PROMO-B', 'name' => 'Promotion Branch B', 'status' => 'active', 'archived_at' => null,
        ]);
        $this->cinemaC = Cinema::factory()->create([
            'code' => 'PROMO-C', 'name' => 'Promotion Branch C', 'status' => 'active', 'archived_at' => null,
        ]);
    }

    public function test_manager_index_is_server_scoped_and_read_only_actions_are_truthful(): void
    {
        $manager = $this->userWithRole('manager');
        $global = $this->discount('GLOBAL-READ');
        $own = $this->discount('OWN-READ', [$this->cinemaA->id]);
        $foreign = $this->discount('FOREIGN-SECRET', [$this->cinemaB->id]);
        $mixed = $this->discount('MIXED-READ', [$this->cinemaA->id, $this->cinemaB->id]);

        $response = $this->actingAs($manager)
            ->withSession([CinemaAccessService::SESSION_KEY => $this->cinemaA->id])
            ->get(route('admin.discounts.index'))
            ->assertOk()
            ->assertSee($global->code)
            ->assertSee($own->code)
            ->assertSee($mixed->code)
            ->assertDontSee($foreign->code)
            ->assertDontSee($this->cinemaB->name)
            ->assertSee('Chỉ xem');

        $response->assertSee(route('admin.discounts.edit', $own), false);
        $response->assertDontSee(route('admin.discounts.edit', $global), false);
        $response->assertDontSee(route('admin.discounts.edit', $mixed), false);
    }

    public function test_manager_create_requires_exact_current_branch_scope(): void
    {
        $manager = $this->userWithRole('manager');
        $session = [CinemaAccessService::SESSION_KEY => $this->cinemaA->id];

        $this->actingAs($manager)->withSession($session)
            ->post(route('admin.discounts.store'), $this->payload('CREATE-OWN', [$this->cinemaA->id]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $own = Promotion::query()->where('code', 'CREATE-OWN')->firstOrFail();
        $this->assertSame([$this->cinemaA->id], $own->cinemas()->pluck('cinemas.id')->all());

        foreach ([
            'CREATE-GLOBAL' => [[], 'cinema_ids'],
            'CREATE-FOREIGN' => [[$this->cinemaB->id], 'cinema_ids.0'],
            'CREATE-MIXED' => [[$this->cinemaA->id, $this->cinemaB->id], 'cinema_ids.1'],
        ] as $code => [$cinemaIds, $errorKey]) {
            $this->actingAs($manager)->withSession($session)
                ->post(route('admin.discounts.store'), $this->payload($code, $cinemaIds))
                ->assertSessionHasErrors($errorKey);
            $this->assertDatabaseMissing('promotions', ['code' => $code]);
        }
    }

    public function test_manager_edit_pages_require_full_current_scope(): void
    {
        $manager = $this->userWithRole('manager');
        $global = $this->discount('EDIT-GLOBAL');
        $own = $this->discount('EDIT-OWN', [$this->cinemaA->id]);
        $foreign = $this->discount('EDIT-FOREIGN', [$this->cinemaB->id]);
        $mixed = $this->discount('EDIT-MIXED', [$this->cinemaA->id, $this->cinemaB->id]);
        $session = [CinemaAccessService::SESSION_KEY => $this->cinemaA->id];

        $this->actingAs($manager)->withSession($session)->get(route('admin.discounts.edit', $own))->assertOk();
        foreach ([$global, $foreign, $mixed] as $readOnly) {
            $this->actingAs($manager)->withSession($session)
                ->get(route('admin.discounts.edit', $readOnly))->assertNotFound();
        }
    }

    public function test_manager_updates_own_promotion_but_cannot_make_it_global_or_add_foreign_scope(): void
    {
        $manager = $this->userWithRole('manager');
        $own = $this->discount('UPDATE-OWN', [$this->cinemaA->id]);
        $session = [CinemaAccessService::SESSION_KEY => $this->cinemaA->id];

        $this->actingAs($manager)->withSession($session)
            ->put(route('admin.discounts.update', $own), $this->payload('UPDATE-OWN', [$this->cinemaA->id], 'Authorized update'))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Authorized update', $own->fresh()->name);

        $this->actingAs($manager)->withSession($session)
            ->put(route('admin.discounts.update', $own), $this->payload('UPDATE-OWN', [], 'Must not become global'))
            ->assertSessionHasErrors('cinema_ids');
        $this->actingAs($manager)->withSession($session)
            ->put(route('admin.discounts.update', $own), $this->payload('UPDATE-OWN', [$this->cinemaA->id, $this->cinemaB->id], 'Must not add foreign'))
            ->assertSessionHasErrors('cinema_ids.1');

        $own->refresh()->load('cinemas');
        $this->assertSame('Authorized update', $own->name);
        $this->assertSame([$this->cinemaA->id], $own->cinemas->pluck('id')->all());
    }

    public function test_manager_cannot_take_over_global_or_foreign_promotion_by_rescoping(): void
    {
        $manager = $this->userWithRole('manager');
        $global = $this->discount('TAKEOVER-GLOBAL');
        $foreign = $this->discount('TAKEOVER-FOREIGN', [$this->cinemaB->id]);
        $mixed = $this->discount('TAKEOVER-MIXED', [$this->cinemaA->id, $this->cinemaB->id]);
        $session = [CinemaAccessService::SESSION_KEY => $this->cinemaA->id];

        foreach ([$global, $foreign, $mixed] as $protected) {
            $before = $protected->only(['code', 'name', 'discount_amount_vnd', 'is_active', 'archived_at']);
            $beforeScope = $protected->cinemas()->pluck('cinemas.id')->all();
            $this->actingAs($manager)->withSession($session)
                ->put(route('admin.discounts.update', $protected), $this->payload($protected->code, [$this->cinemaA->id], 'Takeover'))
                ->assertNotFound();
            $protected->refresh();
            $this->assertSame($before, $protected->only(array_keys($before)));
            $this->assertSame($beforeScope, $protected->cinemas()->pluck('cinemas.id')->all());
        }
    }

    public function test_manager_archive_authority_matches_record_mutation_authority(): void
    {
        $manager = $this->userWithRole('manager');
        $own = $this->discount('ARCHIVE-OWN', [$this->cinemaA->id]);
        $global = $this->discount('ARCHIVE-GLOBAL');
        $foreign = $this->discount('ARCHIVE-FOREIGN', [$this->cinemaB->id]);
        $mixed = $this->discount('ARCHIVE-MIXED', [$this->cinemaA->id, $this->cinemaB->id]);
        $session = [CinemaAccessService::SESSION_KEY => $this->cinemaA->id];

        $this->actingAs($manager)->withSession($session)
            ->patch(route('admin.discounts.archive', $own))->assertRedirect();
        $this->assertNotNull($own->fresh()->archived_at);

        foreach ([$global, $foreign, $mixed] as $protected) {
            $this->actingAs($manager)->withSession($session)
                ->patch(route('admin.discounts.archive', $protected))->assertNotFound();
            $this->assertNull($protected->fresh()->archived_at);
            $this->assertTrue($protected->fresh()->is_active);
        }
    }

    public function test_used_branch_promotion_lifecycle_updates_preserve_scope_exactly(): void
    {
        $manager = $this->userWithRole('manager');
        $promotion = $this->discount('USED-BRANCH', [$this->cinemaA->id]);
        $this->markUsed($promotion);
        $session = [CinemaAccessService::SESSION_KEY => $this->cinemaA->id];
        $businessBefore = $this->businessSnapshot($promotion);

        $this->actingAs($manager)->withSession($session)
            ->put(route('admin.discounts.update', $promotion), ['is_active' => false])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($promotion->fresh()->is_active);
        $this->assertSame([$this->cinemaA->id], $this->scopeIds($promotion));

        $this->put(route('admin.discounts.update', $promotion), ['is_active' => true])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($promotion->fresh()->is_active);
        $this->assertSame([$this->cinemaA->id], $this->scopeIds($promotion));
        $this->assertSame($businessBefore, $this->businessSnapshot($promotion));
    }

    public function test_used_mixed_promotion_lifecycle_is_global_admin_only_and_preserves_both_scopes(): void
    {
        $promotion = $this->discount('USED-MIXED', [$this->cinemaA->id, $this->cinemaB->id]);
        $this->markUsed($promotion);
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->withSession([CinemaAccessService::SESSION_KEY => $this->cinemaA->id])
            ->put(route('admin.discounts.update', $promotion), ['is_active' => false])
            ->assertNotFound();
        $this->assertTrue($promotion->fresh()->is_active);

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('admin.discounts.update', $promotion), ['is_active' => false])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($promotion->fresh()->is_active);
        $this->assertSame([$this->cinemaA->id, $this->cinemaB->id], $this->scopeIds($promotion));
    }

    public function test_released_used_promotion_lifecycle_succeeds_but_economic_and_scope_tampering_are_rejected(): void
    {
        $promotion = $this->discount('USED-RELEASED', [$this->cinemaA->id]);
        $this->markUsed($promotion, released: true);
        $manager = $this->userWithRole('manager');
        $session = [CinemaAccessService::SESSION_KEY => $this->cinemaA->id];
        $businessBefore = $this->businessSnapshot($promotion);

        $this->actingAs($manager)->withSession($session)
            ->put(route('admin.discounts.update', $promotion), ['is_active' => false])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('admin.discounts.update', $promotion), ['is_active' => true])
            ->assertRedirect()->assertSessionHasNoErrors();

        foreach ([
            ['is_active' => false, 'discount_amount_vnd' => 1],
            ['is_active' => false, 'cinema_ids' => []],
            ['is_active' => false, 'cinema_ids' => [$this->cinemaB->id]],
        ] as $tampering) {
            $this->put(route('admin.discounts.update', $promotion), $tampering)
                ->assertSessionHasErrors('promotion');
        }

        $this->assertTrue($promotion->fresh()->is_active);
        $this->assertSame([$this->cinemaA->id], $this->scopeIds($promotion));
        $this->assertSame($businessBefore, $this->businessSnapshot($promotion));
        $this->assertSame(
            BookingPromotion::STATUS_RELEASED,
            $promotion->usages()->sole()->status,
        );
    }

    public function test_used_global_promotion_lifecycle_succeeds_and_remains_global(): void
    {
        $promotion = $this->discount('USED-GLOBAL');
        $this->markUsed($promotion);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->put(route('admin.discounts.update', $promotion), ['is_active' => false])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('admin.discounts.update', $promotion), ['is_active' => true])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue($promotion->fresh()->is_active);
        $this->assertSame([], $this->scopeIds($promotion));
    }

    public function test_global_admin_retains_global_branch_and_multi_branch_authority(): void
    {
        $admin = $this->userWithRole('admin');
        $existingGlobal = $this->discount('ADMIN-EXISTING-GLOBAL');
        $existingBranch = $this->discount('ADMIN-EXISTING-BRANCH', [$this->cinemaB->id]);

        $this->actingAs($admin)->get(route('admin.discounts.index'))->assertOk()
            ->assertSee($existingGlobal->code)->assertSee($existingBranch->code);
        $this->get(route('admin.discounts.edit', $existingGlobal))->assertOk();
        $this->get(route('admin.discounts.edit', $existingBranch))->assertOk();

        foreach ([
            'ADMIN-CREATE-GLOBAL' => [],
            'ADMIN-CREATE-BRANCH' => [$this->cinemaA->id],
            'ADMIN-CREATE-MULTI' => [$this->cinemaA->id, $this->cinemaB->id],
        ] as $code => $cinemaIds) {
            $this->post(route('admin.discounts.store'), $this->payload($code, $cinemaIds))
                ->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->put(route('admin.discounts.update', $existingGlobal), $this->payload(
            $existingGlobal->code,
            [$this->cinemaC->id],
            'Global to branch',
        ))->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('admin.discounts.update', $existingGlobal), $this->payload(
            $existingGlobal->code,
            [],
            'Branch to global',
        ))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($existingGlobal->fresh()->cinemas()->doesntExist());

        $this->put(route('admin.discounts.update', $existingBranch), $this->payload(
            $existingBranch->code,
            [$this->cinemaA->id, $this->cinemaB->id],
            'Branch update',
        ))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(
            [$this->cinemaA->id, $this->cinemaB->id],
            $existingBranch->fresh()->cinemas()->orderBy('cinemas.id')->pluck('cinemas.id')->all(),
        );

        $this->patch(route('admin.discounts.archive', $existingBranch))->assertRedirect();
        $this->assertNotNull($existingBranch->fresh()->archived_at);
    }

    public function test_multi_branch_manager_is_restricted_to_the_selected_active_context(): void
    {
        $manager = $this->userWithRole('manager');
        UserCinemaAssignment::query()->create([
            'user_id' => $manager->id,
            'cinema_id' => $this->cinemaB->id,
            'status' => UserCinemaAssignment::STATUS_ACTIVE,
            'assigned_at' => now(),
        ]);
        $global = $this->discount('CONTEXT-GLOBAL');
        $promotionA = $this->discount('CONTEXT-ONLY-A', [$this->cinemaA->id]);
        $promotionB = $this->discount('CONTEXT-ONLY-B', [$this->cinemaB->id]);
        $promotionAB = $this->discount('CONTEXT-MIXED-AB', [$this->cinemaA->id, $this->cinemaB->id]);
        $promotionC = $this->discount('CONTEXT-C', [$this->cinemaC->id]);

        $contextA = $this->actingAs($manager)->withSession([CinemaAccessService::SESSION_KEY => $this->cinemaA->id])
            ->get(route('admin.discounts.index'))->assertOk();
        $contextA->assertSee($global->code)->assertSee($promotionA->code)->assertSee($promotionAB->code)
            ->assertDontSee($promotionB->code)->assertDontSee($promotionC->code)
            ->assertSee(route('admin.discounts.edit', $promotionA), false)
            ->assertDontSee(route('admin.discounts.edit', $promotionAB), false);

        $contextB = $this->withSession([CinemaAccessService::SESSION_KEY => $this->cinemaB->id])
            ->get(route('admin.discounts.index'))->assertOk();
        $contextB->assertSee($global->code)->assertSee($promotionB->code)->assertSee($promotionAB->code)
            ->assertDontSee($promotionA->code)->assertDontSee($promotionC->code)
            ->assertSee(route('admin.discounts.edit', $promotionB), false)
            ->assertDontSee(route('admin.discounts.edit', $promotionAB), false);

        $this->withSession([CinemaAccessService::SESSION_KEY => $this->cinemaA->id])
            ->get(route('admin.discounts.edit', $promotionAB))->assertNotFound();
    }

    public function test_manager_and_global_admin_forms_expose_only_truthful_scope_options(): void
    {
        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->withSession([CinemaAccessService::SESSION_KEY => $this->cinemaA->id])
            ->get(route('admin.discounts.create'))->assertOk()
            ->assertSee($this->cinemaA->name)
            ->assertDontSee($this->cinemaB->name)
            ->assertSee('Chi nhánh áp dụng (bắt buộc)')
            ->assertDontSee('để trống = toàn hệ thống');

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->get(route('admin.discounts.create'))->assertOk()
            ->assertSee($this->cinemaA->name)
            ->assertSee($this->cinemaB->name)
            ->assertSee($this->cinemaC->name)
            ->assertSee('để trống = toàn hệ thống');
    }

    public function test_promotion_admin_indexes_remain_query_bounded_without_n_plus_one(): void
    {
        $manager = $this->userWithRole('manager');
        $admin = $this->userWithRole('admin');
        $this->discount('QUERY-ONE', [$this->cinemaA->id]);
        $managerOne = $this->countQueries(fn () => $this->actingAs($manager)
            ->withSession([CinemaAccessService::SESSION_KEY => $this->cinemaA->id])
            ->get(route('admin.discounts.index'))->assertOk());

        foreach (range(1, 30) as $index) {
            $scope = match ($index % 4) {
                0 => [],
                1 => [$this->cinemaA->id],
                2 => [$this->cinemaB->id],
                default => [$this->cinemaA->id, $this->cinemaB->id],
            };
            $this->discount('QUERY-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), $scope);
        }

        $managerMany = $this->countQueries(fn () => $this->actingAs($manager)
            ->withSession([CinemaAccessService::SESSION_KEY => $this->cinemaA->id])
            ->get(route('admin.discounts.index'))->assertOk());
        $adminMany = $this->countQueries(fn () => $this->actingAs($admin)
            ->withSession([CinemaAccessService::SESSION_KEY => 'all'])
            ->get(route('admin.discounts.index'))->assertOk());

        $this->assertLessThanOrEqual(30, $managerOne);
        $this->assertLessThanOrEqual(30, $managerMany);
        $this->assertLessThanOrEqual(30, $adminMany);
        $this->assertLessThanOrEqual(2, $managerMany - $managerOne, 'Manager promotion index introduced per-row queries.');

        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, 'PROMOTION_ADMIN_AUTH_QUERY_COUNTS='.json_encode([
                'manager_one' => $managerOne,
                'manager_many' => $managerMany,
                'admin_many' => $adminMany,
            ], JSON_THROW_ON_ERROR).PHP_EOL);
        }
    }

    /** @param list<int> $cinemaIds */
    private function discount(string $code, array $cinemaIds = []): Promotion
    {
        $discount = Promotion::query()->create([
            'code' => $code,
            'name' => 'Promotion '.$code,
            'type' => Promotion::TYPE_FIXED,
            'discount_amount_vnd' => 10_000,
            'minimum_order_vnd' => 0,
            'is_active' => true,
        ]);
        $discount->cinemas()->sync($cinemaIds);

        return $discount;
    }

    /** @param list<int> $cinemaIds */
    private function payload(string $code, array $cinemaIds, ?string $name = null): array
    {
        return [
            'code' => $code,
            'name' => $name ?? 'Promotion '.$code,
            'type' => Promotion::TYPE_FIXED,
            'discount_amount_vnd' => 10_000,
            'discount_percent' => null,
            'maximum_discount_vnd' => null,
            'minimum_order_vnd' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
            'global_usage_limit' => null,
            'per_user_usage_limit' => null,
            'cinema_ids' => $cinemaIds,
        ];
    }

    private function markUsed(Promotion $promotion, bool $released = false): void
    {
        $scenario = $this->bookingScenario(false);
        $booking = $this->bookingForScenario($scenario);
        DB::transaction(fn () => app(PromotionService::class)->reserveForBooking(
            $booking,
            $promotion->code,
            100_000,
        ));
        if ($released) {
            app(PromotionService::class)->release($booking);
        }
    }

    /** @return list<int> */
    private function scopeIds(Promotion $promotion): array
    {
        return $promotion->cinemas()->orderBy('cinemas.id')->pluck('cinemas.id')->map(fn ($id): int => (int) $id)->all();
    }

    private function businessSnapshot(Promotion $promotion): array
    {
        return $promotion->fresh()->only([
            'id', 'code', 'name', 'description', 'type', 'discount_amount_vnd', 'discount_percent',
            'maximum_discount_vnd', 'minimum_order_vnd', 'starts_at', 'ends_at',
            'global_usage_limit', 'per_user_usage_limit', 'registered_users_only', 'first_order_only',
        ]);
    }

    private function countQueries(callable $operation): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $operation();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
}
