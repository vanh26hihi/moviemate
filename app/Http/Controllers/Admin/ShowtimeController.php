<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Showtime;
use App\Models\Movie;
use App\Models\Cinema;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShowtimeController extends Controller
{
    /**
     * Display a listing of the showtimes with filters.
     */
    public function index(Request $request)
    {
        $query = Showtime::with(['movie', 'cinema', 'room']);

        // Filters
        if ($movieId = $request->query('movie_id')) {
            $query->where('movie_id', $movieId);
        }

        if ($cinemaId = $request->query('cinema_id')) {
            $query->where('cinema_id', $cinemaId);
        }

        if ($date = $request->query('show_date')) {
            $query->whereDate('show_date', $date);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $showtimes = $query->orderByDesc('show_date')
                           ->orderBy('show_time')
                           ->paginate(15)
                           ->withQueryString();

        $movies   = Movie::all();
        $cinemas  = Cinema::all();

        return view('admin.showtimes.index', compact('showtimes', 'movies', 'cinemas'));
    }

    /**
     * Show the form for creating a new showtime.
     */
    public function create()
    {
        $movies  = Movie::where('status', '!=', 'stopped')->get();
        $cinemas = Cinema::all();

        return view('admin.showtimes.create', compact('movies', 'cinemas'));
    }

    /**
     * Store a newly created showtime in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'movie_id'   => ['required', 'exists:movies,id'],
            'cinema_id'  => ['required', 'exists:cinemas,id'],
            'room_id'    => ['required', 'exists:rooms,id'],
            'show_date'  => ['required', 'date'],
            'show_time'  => ['required', 'date_format:H:i'],
            'price'      => ['required', 'numeric', 'min:0'],
            'vip_price'  => ['nullable', 'numeric', 'min:0'],
            'status'     => ['required', Rule::in(['active', 'cancelled', 'finished'])],
        ]);

        // Normalize show_time to HH:MM:SS for DB consistency
        $validated['show_time'] = $validated['show_time'] . ':00';

        // Ensure the selected movie is not stopped
        $movie = Movie::findOrFail($validated['movie_id']);
        if ($movie->status === 'stopped') {
            return back()->withErrors(['movie_id' => 'Phim đã ngừng chiếu không thể tạo suất chiếu.'])
                         ->withInput();
        }

        // Ensure room belongs to selected cinema
        $room = Room::findOrFail($validated['room_id']);
        if ($room->cinema_id != $validated['cinema_id']) {
            return back()->withErrors(['room_id' => 'Phòng không thuộc rạp đã chọn.'])
                         ->withInput();
        }

        // Conflict check: same cinema, room, date & time
        $conflict = Showtime::where('cinema_id', $validated['cinema_id'])
            ->where('room_id', $validated['room_id'])
            ->whereDate('show_date', $validated['show_date'])
            ->where('show_time', $validated['show_time'])
            ->exists();

        if ($conflict) {
            return back()->withErrors(['show_time' => 'Đã có suất chiếu ở cùng phòng, ngày và giờ.'])
                         ->withInput();
        }

        Showtime::create($validated);

        return redirect()
            ->route('admin.showtimes.index')
            ->with('success', 'Suất chiếu đã được tạo thành công.');
    }

    /**
     * Show the form for editing the specified showtime.
     */
    public function edit(Showtime $showtime)
    {
        $movies  = Movie::where('status', '!=', 'stopped')->get();
        $cinemas = Cinema::all();

        // Load rooms for the cinema of this showtime (for the edit form)
        $rooms = Room::where('cinema_id', $showtime->cinema_id)->get();

        return view('admin.showtimes.edit', compact('showtime', 'movies', 'cinemas', 'rooms'));
    }

    /**
     * Update the specified showtime in storage.
     */
    public function update(Request $request, Showtime $showtime)
    {
        $validated = $request->validate([
            'movie_id'   => ['required', 'exists:movies,id'],
            'cinema_id'  => ['required', 'exists:cinemas,id'],
            'room_id'    => ['required', 'exists:rooms,id'],
            'show_date'  => ['required', 'date'],
            'show_time'  => ['required', 'date_format:H:i'],
            'price'      => ['required', 'numeric', 'min:0'],
            'vip_price'  => ['nullable', 'numeric', 'min:0'],
            'status'     => ['required', Rule::in(['active', 'cancelled', 'finished'])],
        ]);

        // Normalize show_time to HH:MM:SS for DB consistency
        $validated['show_time'] = $validated['show_time'] . ':00';

        // Ensure the selected movie is not stopped
        $movie = Movie::findOrFail($validated['movie_id']);
        if ($movie->status === 'stopped') {
            return back()->withErrors(['movie_id' => 'Phim đã ngừng chiếu không thể cập nhật suất chiếu.'])
                         ->withInput();
        }

        // Ensure room belongs to selected cinema
        $room = Room::findOrFail($validated['room_id']);
        if ($room->cinema_id != $validated['cinema_id']) {
            return back()->withErrors(['room_id' => 'Phòng không thuộc rạp đã chọn.'])
                         ->withInput();
        }

        // Conflict check, exclude current record
        $conflict = Showtime::where('cinema_id', $validated['cinema_id'])
            ->where('room_id', $validated['room_id'])
            ->whereDate('show_date', $validated['show_date'])
            ->where('show_time', $validated['show_time'])
            ->where('id', '!=', $showtime->id)
            ->exists();

        if ($conflict) {
            return back()->withErrors(['show_time' => 'Đã có suất chiếu ở cùng phòng, ngày và giờ.'])
                         ->withInput();
        }

        $showtime->update($validated);

        return redirect()
            ->route('admin.showtimes.index')
            ->with('success', 'Suất chiếu đã được cập nhật.');
    }

    /**
     * Remove the specified showtime from storage.
     */
    public function destroy(Showtime $showtime)
    {
        $showtime->delete();

        return redirect()
            ->route('admin.showtimes.index')
            ->with('success', 'Suất chiếu đã được xóa.');
    }
}