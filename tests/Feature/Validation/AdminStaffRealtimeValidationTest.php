<?php

namespace Tests\Feature\Validation;

use App\Models\Cinema;
use App\Models\Promotion;
use App\Models\Room;
use App\Services\CinemaAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\Rule;
use Tests\TestCase;

final class AdminStaffRealtimeValidationTest extends TestCase
{
    use RefreshDatabase;

    private Cinema $primary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
        $this->primary = Cinema::query()->active()->primary()->firstOrFail();
    }

    public function test_promotion_submit_rejects_duplicate_code_with_promotion_terminology(): void
    {
        $admin = $this->userWithRole('admin');
        $this->promotion('WELCOME20K');

        $this->actingAs($admin)
            ->post(route('admin.discounts.store'), $this->promotionPayload('WELCOME20K'))
            ->assertSessionHasErrors([
                'code' => 'Mã khuyến mãi này đã tồn tại.',
            ]);

        $this->assertSame(1, Promotion::query()->where('code', 'WELCOME20K')->count());
    }

    public function test_promotion_realtime_validation_handles_duplicate_unique_and_edit_ignore(): void
    {
        $admin = $this->userWithRole('admin');
        $promotion = $this->promotion('MOVIEMATE10');

        $this->actingAs($admin)->postJson(route('admin.validation.field'), [
            'rule' => 'promotion.code',
            'value' => 'moviemate10',
        ])->assertUnprocessable()
            ->assertExactJson(['valid' => false, 'message' => 'Mã khuyến mãi này đã tồn tại.']);

        $this->postJson(route('admin.validation.field'), [
            'rule' => 'promotion.code',
            'value' => 'DEFENSE_UNIQUE',
        ])->assertOk()->assertExactJson(['valid' => true]);

        $this->postJson(route('admin.validation.field'), [
            'rule' => 'promotion.code',
            'value' => 'MOVIEMATE10',
            'record_id' => $promotion->id,
        ])->assertOk()->assertExactJson(['valid' => true]);
    }

    public function test_promotion_form_exposes_inline_realtime_contract(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.discounts.create'))
            ->assertOk()
            ->assertSee('Mã khuyến mãi')
            ->assertSee('data-validation-rule="promotion.code"', false)
            ->assertSee('data-validation-url="'.route('admin.validation.field').'"', false)
            ->assertSee('pattern="[A-Za-z0-9_-]+"', false);
    }

    public function test_manager_validation_is_exact_branch_scoped_and_foreign_promotion_is_hidden(): void
    {
        $foreignCinema = Cinema::factory()->create([
            'code' => 'FOREIGN',
            'status' => 'active',
            'archived_at' => null,
        ]);
        Room::factory()->create(['cinema_id' => $this->primary->id, 'code' => 'OWN-ROOM']);
        Room::factory()->create(['cinema_id' => $foreignCinema->id, 'code' => 'FOREIGN-ROOM']);
        $ownPromotion = $this->promotion('OWN-PROMO', [$this->primary->id]);
        $foreignPromotion = $this->promotion('FOREIGN-PROMO', [$foreignCinema->id]);
        $manager = $this->userWithRole('manager');
        $session = [CinemaAccessService::SESSION_KEY => $this->primary->id];

        $this->actingAs($manager)->withSession($session)
            ->postJson(route('admin.validation.field'), [
                'rule' => 'room.code',
                'value' => 'FOREIGN-ROOM',
                'cinema_id' => $foreignCinema->id,
            ])->assertOk()->assertExactJson(['valid' => true]);

        $this->withSession($session)->postJson(route('admin.validation.field'), [
            'rule' => 'room.code',
            'value' => 'OWN-ROOM',
            'cinema_id' => $foreignCinema->id,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Mã phòng này đã tồn tại trong chi nhánh đã chọn.');

        $this->withSession($session)->postJson(route('admin.validation.field'), [
            'rule' => 'promotion.code',
            'value' => $ownPromotion->code,
            'record_id' => $ownPromotion->id,
        ])->assertOk();

        $this->withSession($session)->postJson(route('admin.validation.field'), [
            'rule' => 'promotion.code',
            'value' => $foreignPromotion->code,
            'record_id' => $foreignPromotion->id,
        ])->assertNotFound();
    }

    public function test_realtime_endpoint_requires_authentication_permission_and_whitelisted_rule(): void
    {
        $payload = ['rule' => 'promotion.code', 'value' => 'ANY-CODE'];
        $this->postJson(route('admin.validation.field'), $payload)->assertUnauthorized();

        $this->actingAs($this->userWithRole('staff'))
            ->postJson(route('admin.validation.field'), $payload)
            ->assertForbidden();

        $this->actingAs($this->userWithRole('admin'))
            ->postJson(route('admin.validation.field'), [
                'rule' => 'users.email',
                'value' => 'admin@example.test',
                'table' => 'users',
                'column' => 'email',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('rule');
    }

    public function test_global_cinema_unique_validation_uses_branch_terminology(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->postJson(route('admin.validation.field'), [
                'rule' => 'cinema.code',
                'value' => $this->primary->code,
            ])->assertUnprocessable()
            ->assertExactJson(['valid' => false, 'message' => 'Mã chi nhánh này đã tồn tại.']);
    }

    public function test_staff_lookup_keeps_authoritative_validation_and_inline_compatible_markup(): void
    {
        $staff = $this->userWithRole('staff');
        $index = route('staff.tickets.index');

        $this->actingAs($staff)->get($index)
            ->assertOk()
            ->assertSee('Mã đơn đặt vé hoặc QR đơn đặt vé')
            ->assertSee('name="ticket"', false)
            ->assertSee('required', false)
            ->assertSee('maxlength="512"', false);

        $response = $this->from($index)->post(route('staff.tickets.resolve'), ['ticket' => '']);
        $response->assertRedirect($index)->assertSessionHasErrors([
            'ticket' => 'Vui lòng nhập mã đơn đặt vé hoặc QR đơn đặt vé.',
        ]);

        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag([
            'ticket' => ['Vui lòng nhập mã đơn đặt vé hoặc QR đơn đặt vé.'],
        ]));
        $state = view('components.form-validation-state', ['errors' => $errors])->render();
        $this->assertStringContainsString('data-form-validation-errors', $state);
        $this->assertStringContainsString('data-validation-encoding="base64"', $state);
        $this->assertStringNotContainsString('Vui lòng nhập mã đơn đặt vé hoặc QR đơn đặt vé.', $state);
    }

    public function test_generic_code_translation_is_not_globally_room_specific(): void
    {
        $this->promotion('LOCALIZATION-CODE');
        $validator = Validator::make(
            ['code' => 'LOCALIZATION-CODE'],
            ['code' => [Rule::unique('promotions', 'code')]],
        );

        $this->assertTrue($validator->fails());
        $this->assertStringNotContainsString('mã phòng', $validator->errors()->first('code'));
    }

    /** @param array<int, int> $cinemaIds */
    private function promotion(string $code, array $cinemaIds = []): Promotion
    {
        $promotion = Promotion::query()->create([
            'code' => $code,
            'name' => 'Promotion '.$code,
            'type' => Promotion::TYPE_FIXED,
            'discount_amount_vnd' => 20_000,
            'discount_percent' => null,
            'maximum_discount_vnd' => null,
            'minimum_order_vnd' => 0,
            'is_active' => true,
            'registered_users_only' => false,
            'first_order_only' => false,
        ]);
        $promotion->cinemas()->sync($cinemaIds);

        return $promotion;
    }

    /** @return array<string, mixed> */
    private function promotionPayload(string $code): array
    {
        return [
            'code' => $code,
            'name' => 'Duplicate promotion',
            'type' => Promotion::TYPE_FIXED,
            'discount_amount_vnd' => 20_000,
            'minimum_order_vnd' => 0,
            'is_active' => 1,
            'cinema_ids' => [],
        ];
    }
}
