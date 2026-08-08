<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Services\ActivityLogger;
use App\Services\CinemaAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class DiscountController extends Controller
{
    public function __construct(private readonly CinemaAccessService $access, private readonly ActivityLogger $activity) {}

    public function index(Request $request): View
    {
        $discounts = DiscountCode::query()->with('cinemas')->withCount(['redemptions as active_usage_count' => fn ($q) => $q->whereIn('status', ['reserved', 'redeemed'])])
            ->orderByDesc('priority')->orderByDesc('id')->paginate(20);

        return view('admin.discounts.index', compact('discounts'));
    }

    public function create(Request $request): View
    {
        return $this->form($request, new DiscountCode);
    }

    public function edit(Request $request, DiscountCode $discount): View
    {
        $discount->load('cinemas');

        return $this->form($request, $discount);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $discount = DB::transaction(function () use ($request, $data): DiscountCode {
            $cinemas = $data['cinema_ids'] ?? [];
            unset($data['cinema_ids']);
            $discount = DiscountCode::query()->create([...$data, 'created_by_user_id' => $request->user()->id, 'updated_by_user_id' => $request->user()->id]);
            $discount->cinemas()->sync($cinemas);
            $this->activity->log('discount.created', $discount, after: $discount->only(['code', 'name', 'discount_type', 'discount_value']));

            return $discount;
        });

        return redirect()->route('admin.discounts.edit', $discount)->with('success', 'Đã tạo mã giảm giá.');
    }

    public function update(Request $request, DiscountCode $discount): RedirectResponse
    {
        $data = $this->validated($request, $discount);
        DB::transaction(function () use ($request, $discount, $data): void {
            $cinemas = $data['cinema_ids'] ?? [];
            unset($data['cinema_ids']);
            $before = $discount->only(['code', 'name', 'discount_type', 'discount_value', 'is_active']);
            $discount->update([...$data, 'updated_by_user_id' => $request->user()->id]);
            $discount->cinemas()->sync($cinemas);
            $this->activity->log('discount.updated', $discount, $before, $discount->only(array_keys($before)));
        });

        return back()->with('success', 'Đã cập nhật mã giảm giá.');
    }

    public function archive(Request $request, DiscountCode $discount): RedirectResponse
    {
        $discount->update(['is_active' => false, 'archived_at' => now(), 'updated_by_user_id' => $request->user()->id]);
        $this->activity->log('discount.archived', $discount);

        return back()->with('success', 'Đã lưu trữ mã; dữ liệu lịch sử được giữ nguyên.');
    }

    private function form(Request $request, DiscountCode $discount): View
    {
        return view('admin.discounts.form', ['discount' => $discount, 'cinemas' => $this->access->accessibleCinemas($request->user())->sortBy('name')->values()]);
    }

    private function validated(Request $request, ?DiscountCode $discount = null): array
    {
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('discount_codes')->ignore($discount?->id)],
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'],
            'discount_type' => ['required', Rule::in(['fixed', 'percent'])], 'discount_value' => ['required', 'integer', 'min:1'],
            'maximum_discount_amount' => ['nullable', 'integer', 'min:0'], 'minimum_order_amount' => ['required', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'], 'is_active' => ['required', 'boolean'],
            'total_quota' => ['nullable', 'integer', 'min:1'], 'per_user_quota' => ['nullable', 'integer', 'min:1'],
            'registered_users_only' => ['nullable', 'boolean'], 'first_order_only' => ['nullable', 'boolean'], 'can_combine' => ['nullable', 'boolean'],
            'priority' => ['required', 'integer', 'between:-10000,10000'], 'cinema_ids' => ['sometimes', 'array'], 'cinema_ids.*' => ['integer', Rule::in($this->access->accessibleCinemas($request->user())->pluck('id')->all())],
        ]);
        if ($data['discount_type'] === 'percent' && $data['discount_value'] > 100) {
            throw ValidationException::withMessages(['discount_value' => 'Phần trăm giảm phải từ 1 đến 100.']);
        }
        foreach (['registered_users_only', 'first_order_only', 'can_combine'] as $field) {
            $data[$field] = (bool) ($data[$field] ?? false);
        }

        return $data;
    }
}
