<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyAccount;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class LoyaltyController extends Controller
{
    public function __invoke(Request $request): View
    {
        LoyaltyAccount::query()->insertOrIgnore(['user_id' => $request->user()->id, 'points_balance' => 0, 'lifetime_earned' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $account = LoyaltyAccount::query()->where('user_id', $request->user()->id)->firstOrFail();
        $transactions = $account->transactions()->latest()->paginate(20);

        return view('user.loyalty.history', compact('account', 'transactions'));
    }
}
