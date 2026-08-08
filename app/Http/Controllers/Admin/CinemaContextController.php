<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CinemaAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CinemaContextController extends Controller
{
    public function __invoke(Request $request, CinemaAccessService $access): RedirectResponse
    {
        $validated = $request->validate(['cinema_id' => ['required', 'string']]);
        $cinemaId = $validated['cinema_id'] === 'all' ? null : filter_var($validated['cinema_id'], FILTER_VALIDATE_INT);
        abort_if($cinemaId === false, 422);
        $access->select($request->user(), $cinemaId);

        return back()->with('success', 'Đã chuyển ngữ cảnh quản trị.');
    }
}
