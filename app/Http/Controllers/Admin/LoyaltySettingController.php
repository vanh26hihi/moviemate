<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoyaltySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class LoyaltySettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.loyalty.settings', ['settings' => LoyaltySetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['review_reward_points' => ['required', 'integer', 'min:0', 'max:1000000'], 'point_value_vnd' => ['required', 'integer', 'min:1', 'max:1000000'], 'max_points_discount_percent' => ['required', 'integer', 'between:1,100'], 'minimum_points_redemption' => ['required', 'integer', 'min:1'], 'max_discount_codes_per_booking' => ['required', 'integer', 'between:1,10']]);
        $settings = LoyaltySetting::current();
        $settings->update([...$data, 'updated_by_user_id' => $request->user()->id]);

        return back()->with('success', 'Đã cập nhật cấu hình điểm thưởng.');
    }
}
