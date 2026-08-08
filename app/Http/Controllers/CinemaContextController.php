<?php

namespace App\Http\Controllers;

use App\Models\Cinema;
use App\Services\CinemaContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CinemaContextController extends Controller
{
    public function __invoke(Request $request, CinemaContext $context): RedirectResponse
    {
        if (! $request->has('cinema') && $request->has('cinema_id')) {
            $legacy = $request->validate(['cinema_id' => ['required', 'integer', 'exists:cinemas,id']]);
            $cinema = Cinema::query()->active()->findOrFail($legacy['cinema_id']);
            $context->select($cinema);

            return back()->with('success', 'Đã chọn '.$cinema->name.'.');
        }
        $validated = $request->validate(['cinema' => ['required', 'string', 'max:32', 'regex:/^(all|[A-Za-z0-9-]+)$/']]);
        if ($validated['cinema'] === 'all') {
            $context->clearPreference();

            return back()->with('success', 'Đang hiển thị lịch chiếu của tất cả rạp.');
        }
        $cinema = Cinema::query()->active()->where('code', mb_strtoupper($validated['cinema']))->firstOrFail();
        $context->select($cinema);

        return back()->with('success', 'Đã chọn '.$cinema->name.'.');
    }
}
