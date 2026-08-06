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
        $validated = $request->validate(['cinema_id' => ['required', 'integer', 'exists:cinemas,id']]);
        $cinema = Cinema::query()->active()->findOrFail($validated['cinema_id']);
        $context->select($cinema);

        return back()->with('success', 'Đã chọn '.$cinema->name.'.');
    }
}
