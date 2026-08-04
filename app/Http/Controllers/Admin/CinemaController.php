<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CinemaContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CinemaController extends Controller
{
    public function __construct(private readonly CinemaContext $cinemaContext) {}

    public function show(): View
    {
        $cinema = $this->cinemaContext->current()->loadCount([
            'rooms',
            'rooms as active_rooms_count' => fn ($query) => $query->where('status', 'active'),
        ]);

        return view('admin.cinemas.show', compact('cinema'));
    }

    public function update(Request $request): RedirectResponse
    {
        $cinema = $this->cinemaContext->current();
        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'image' => ['nullable', 'image', 'max:4096'],
            'description' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('image')) {
            if ($cinema->image && Storage::disk('public')->exists($cinema->image)) {
                Storage::disk('public')->delete($cinema->image);
            }
            $validated['image'] = $request->file('image')->store('cinema_images', 'public');
        }

        $cinema->update($validated);

        return redirect()->route('admin.cinema.show')->with('success', 'Đã cập nhật thông tin rạp.');
    }
}
