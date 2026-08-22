<?php

namespace Tests\Feature\Admin;

use App\Models\PriceBookAdjustment;
use App\Models\PriceBookVersion;
use App\Models\RoomType;
use App\Services\PriceBookVersionService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesPriceBookFixtures;
use Tests\TestCase;

final class RoomTypePriceBookDependencyHotfixTest extends TestCase
{
    use CreatesPriceBookFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_reverse_dependency_includes_draft_published_and_retired_adjustments(): void
    {
        $roomType = $this->roomType('DEPENDENCY_ALL');
        $versions = app(PriceBookVersionService::class);

        $retired = $this->versionWithRoomTypeAdjustment($roomType, '2024-01-01', '2025-01-01');
        $retired = $versions->retire($versions->publish($retired));

        $published = $this->versionWithRoomTypeAdjustment($roomType, '2025-01-01', '2026-01-01');
        $published = $versions->publish($published);

        $draft = $this->versionWithRoomTypeAdjustment($roomType, '2026-01-01', '2027-01-01');

        $relation = $roomType->pricingRules();
        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(PriceBookAdjustment::class, $relation->getRelated());
        $this->assertSame('price_book_adjustments.room_type_id', $relation->getQualifiedForeignKeyName());

        $adjustments = $relation->with('version')->orderBy('id')->get();
        $this->assertCount(3, $adjustments);
        $this->assertEqualsCanonicalizing(
            [PriceBookVersion::STATUS_DRAFT, PriceBookVersion::STATUS_PUBLISHED, PriceBookVersion::STATUS_RETIRED],
            $adjustments->pluck('version.status')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$draft->id, $published->id, $retired->id],
            $adjustments->pluck('price_book_version_id')->all(),
        );
    }

    public function test_empty_reverse_dependency_has_zero_rows(): void
    {
        $roomType = $this->roomType('DEPENDENCY_EMPTY');

        $this->assertSame(0, $roomType->pricingRules()->count());
        $this->assertSame(0, $roomType->loadCount('pricingRules')->pricing_rules_count);
    }

    public function test_global_admin_room_type_index_renders_truthful_all_reference_count(): void
    {
        $roomType = $this->roomType('DEPENDENCY_INDEX');
        $versions = app(PriceBookVersionService::class);
        $retired = $this->versionWithRoomTypeAdjustment($roomType, '2024-01-01', '2025-01-01');
        $versions->retire($versions->publish($retired));
        $published = $this->versionWithRoomTypeAdjustment($roomType, '2025-01-01', '2026-01-01');
        $versions->publish($published);
        $this->versionWithRoomTypeAdjustment($roomType, '2026-01-01', '2027-01-01');

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.room-types.index'))
            ->assertOk()
            ->assertSee($roomType->name)
            ->assertSee('3 quy tắc giá')
            ->assertDontSee('quy tắc đang áp dụng');
    }

    public function test_global_admin_edit_renders_for_empty_and_referenced_room_types(): void
    {
        $admin = $this->userWithRole('admin');
        $empty = $this->roomType('DEPENDENCY_EDIT_EMPTY');
        $referenced = $this->roomType('DEPENDENCY_EDIT_USED');
        $this->versionWithRoomTypeAdjustment($referenced, null, null);

        $emptyHtml = $this->actingAs($admin)
            ->get(route('admin.room-types.edit', $empty))
            ->assertOk()
            ->assertSee($empty->name)
            ->getContent();
        $referencedHtml = $this->get(route('admin.room-types.edit', $referenced))
            ->assertOk()
            ->assertSee($referenced->name)
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/<input[^>]+name="code"[^>]+readonly/s', $emptyHtml);
        $this->assertMatchesRegularExpression('/<input[^>]+name="code"[^>]+readonly/s', $referencedHtml);
    }

    public function test_existing_foreign_key_guard_blocks_deleting_a_referenced_room_type(): void
    {
        $roomType = $this->roomType('DEPENDENCY_DELETE_GUARD');
        $this->versionWithRoomTypeAdjustment($roomType, null, null);

        try {
            $roomType->delete();
            $this->fail('A referenced RoomType must retain its PriceBook adjustment dependency.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseHas('room_types', ['id' => $roomType->id]);
        $this->assertSame(1, $roomType->pricingRules()->count());
    }

    private function roomType(string $code): RoomType
    {
        return RoomType::query()->create([
            'code' => $code,
            'name' => str_replace('_', ' ', $code),
            'is_active' => true,
            'sort_order' => 10,
        ]);
    }

    private function versionWithRoomTypeAdjustment(
        RoomType $roomType,
        ?string $effectiveFrom,
        ?string $effectiveUntil,
    ): PriceBookVersion {
        $versions = app(PriceBookVersionService::class);
        $version = $versions->createDraft($this->chainPriceBook(), [
            'base_price_vnd' => 80_000,
            'effective_from' => $effectiveFrom,
            'effective_until' => $effectiveUntil,
        ]);

        return $versions->replaceAdjustments($version, [[
            'dimension' => 'room_type',
            'label' => 'Room type dependency',
            'room_type_id' => $roomType->id,
            'amount_vnd' => 10_000,
        ]]);
    }
}
