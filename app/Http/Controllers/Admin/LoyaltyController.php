<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class LoyaltyController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:30'],
        ]);
        $transactions = LoyaltyTransaction::query()->with('account.user')
            ->when(trim((string) ($filters['search'] ?? '')), fn ($query, $search) => $query->whereHas('account.user', fn ($query) => $query
                ->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->latest()->paginate(30)->withQueryString();

        return view('admin.loyalty.index', [
            'transactions' => $transactions,
            'filters' => $filters,
            'types' => LoyaltyTransaction::query()->distinct()->orderBy('type')->pluck('type'),
        ]);
    }
}
