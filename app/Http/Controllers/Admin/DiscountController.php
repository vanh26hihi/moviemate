<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\ActivityLogger;
use App\Services\Admin\PromotionAdminAccess;
use App\Services\CinemaAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class DiscountController extends Controller
{
    public function __construct(
        private readonly CinemaAccessService $access,
        private readonly PromotionAdminAccess $promotionAccess,
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): View
    {
        $query = $this->promotionAccess->visibleQuery(Promotion::query(), $request->user());
        $discounts = $query->with('cinemas')
            ->withExists('usages')
            ->withCount(['usages as active_usage_count' => fn ($q) => $q->whereIn('status', ['reserved', 'redeemed'])])
            ->orderByDesc('id')->paginate(20);
        $discounts->getCollection()->each(fn (Promotion $discount) => $discount->setAttribute(
            'admin_can_manage', $this->promotionAccess->canManage($request->user(), $discount),
        ));

        return view('admin.discounts.index', [
            'discounts' => $discounts,
            'hasGlobalPromotionAccess' => $this->access->hasGlobalAccess($request->user()),
            'promotionAdminCinemaId' => $this->access->currentCinemaId($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, new Promotion);
    }

    public function edit(Request $request, Promotion $discount): View
    {
        $discount->load('cinemas')->loadExists('usages');
        $this->promotionAccess->authorizeManage($request->user(), $discount);

        return $this->form($request, $discount);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $discount = DB::transaction(function () use ($request, $data): Promotion {
            $cinemas = $data['cinema_ids'] ?? [];
            unset($data['cinema_ids']);
            $discount = Promotion::query()->create([
                ...$data, 'created_by_user_id' => $request->user()->id, 'updated_by_user_id' => $request->user()->id,
            ]);
            $discount->cinemas()->sync($cinemas);
            $this->activity->log('discount.created', $discount, after: $discount->only([
                'code', 'name', 'type', 'discount_amount_vnd', 'discount_percent',
            ]));

            return $discount;
        });

        return redirect()->route('admin.discounts.edit', $discount)->with('success', 'Đã tạo mã giảm giá.');
    }

    public function update(Request $request, Promotion $discount): RedirectResponse
    {
        $discount->load('cinemas');
        $this->promotionAccess->authorizeManage($request->user(), $discount);
        $hasEverBeenUsed = $discount->usages()->exists();
        if ($hasEverBeenUsed) {
            $businessFields = [
                'code', 'name', 'description', 'type', 'discount_amount_vnd', 'discount_percent',
                'maximum_discount_vnd', 'minimum_order_vnd', 'starts_at', 'ends_at',
                'global_usage_limit', 'per_user_usage_limit', 'registered_users_only',
                'first_order_only', 'cinema_ids',
            ];
            if (array_intersect($businessFields, array_keys($request->all())) !== []) {
                throw ValidationException::withMessages([
                    'promotion' => 'Định nghĩa business của khuyến mãi đã dùng là bất biến.',
                ]);
            }
            $data = $request->validate(['is_active' => ['required', 'boolean']]);
        } else {
            $data = $this->validated($request, $discount);
        }
        DB::transaction(function () use ($request, $discount, $data): void {
            $discount = Promotion::query()->lockForUpdate()->findOrFail($discount->id);
            $hasEverBeenUsed = $discount->usages()->exists();
            $cinemas = $data['cinema_ids'] ?? [];
            unset($data['cinema_ids']);
            $before = $discount->only(['code', 'name', 'type', 'discount_amount_vnd', 'discount_percent', 'is_active']);
            $discount->update([...$data, 'updated_by_user_id' => $request->user()->id]);
            if (! $hasEverBeenUsed) {
                $discount->cinemas()->sync($cinemas);
            }
            $this->activity->log('discount.updated', $discount, $before, $discount->only(array_keys($before)));
        });

        return back()->with('success', 'Đã cập nhật mã giảm giá.');
    }

    public function archive(Request $request, Promotion $discount): RedirectResponse
    {
        $discount->load('cinemas');
        $this->promotionAccess->authorizeManage($request->user(), $discount);
        DB::transaction(function () use ($request, $discount): void {
            $discount = Promotion::query()->lockForUpdate()->findOrFail($discount->id);
            $discount->update(['is_active' => false, 'archived_at' => now(), 'updated_by_user_id' => $request->user()->id]);
            $this->activity->log('discount.archived', $discount);
        });

        return back()->with('success', 'Đã lưu trữ mã; dữ liệu lịch sử được giữ nguyên.');
    }

    private function form(Request $request, Promotion $discount): View
    {
        return view('admin.discounts.form', [
            'discount' => $discount,
            'cinemas' => $this->promotionAccess->mutationCinemas($request->user()),
            'canCreateGlobalPromotion' => $this->access->hasGlobalAccess($request->user()),
        ]);
    }

    private function validated(Request $request, ?Promotion $discount = null): array
    {
        $globalAccess = $this->access->hasGlobalAccess($request->user());
        $cinemaScopeRules = $globalAccess ? ['nullable', 'array'] : ['required', 'array', 'min:1'];
        $allowedCinemaIds = $this->promotionAccess->mutationCinemaIds($request->user())->all();
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('promotions')->ignore($discount?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(Promotion::TYPES)],
            'discount_amount_vnd' => ['nullable', 'integer', 'min:1'],
            'discount_percent' => ['nullable', 'integer', 'between:1,100'],
            'maximum_discount_vnd' => ['nullable', 'integer', 'min:1'],
            'minimum_order_vnd' => ['required', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['required', 'boolean'],
            'global_usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_usage_limit' => ['nullable', 'integer', 'min:1'],
            'registered_users_only' => ['nullable', 'boolean'],
            'first_order_only' => ['nullable', 'boolean'],
            'cinema_ids' => $cinemaScopeRules,
            'cinema_ids.*' => ['integer', 'distinct', Rule::exists('cinemas', 'id'), Rule::in($allowedCinemaIds)],
        ]);

        if ($data['type'] === Promotion::TYPE_FIXED) {
            if (($data['discount_amount_vnd'] ?? null) === null) {
                throw ValidationException::withMessages(['discount_amount_vnd' => 'Khuyến mãi cố định cần số tiền giảm dương.']);
            }
            if (($data['discount_percent'] ?? null) !== null || ($data['maximum_discount_vnd'] ?? null) !== null) {
                throw ValidationException::withMessages(['type' => 'Khuyến mãi cố định không được có tỷ lệ hoặc mức giảm tối đa.']);
            }
            $data['discount_percent'] = null;
            $data['maximum_discount_vnd'] = null;
        } else {
            if (($data['discount_percent'] ?? null) === null) {
                throw ValidationException::withMessages(['discount_percent' => 'Khuyến mãi phần trăm cần tỷ lệ từ 1 đến 100.']);
            }
            if (($data['discount_amount_vnd'] ?? null) !== null) {
                throw ValidationException::withMessages(['type' => 'Khuyến mãi phần trăm không được có số tiền giảm cố định.']);
            }
            $data['discount_amount_vnd'] = null;
        }
        foreach (['registered_users_only', 'first_order_only'] as $field) {
            $data[$field] = (bool) ($data[$field] ?? false);
        }

        return $data;
    }
}
