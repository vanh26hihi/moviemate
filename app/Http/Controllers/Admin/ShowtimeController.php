<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use App\Services\CinemaContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShowtimeController extends Controller
{
    public function __construct(private readonly CinemaContext $cinemaContext) {}

    public function index(Request $request)
    {
        $query = Showtime::query()->with(['movie', 'cinema', 'room'])
            ->where('cinema_id', $this->cinemaContext->id());

        foreach (['movie_id', 'status'] as $filter) {
            if ($value = $request->query($filter)) {
                $query->where($filter, $value);
            }
        }
        if ($date = $request->query('show_date')) {
            $query->whereDate('show_date', $date);
        }

        $showtimes = $query->orderByDesc('show_date')->orderBy('show_time')->paginate(15)->withQueryString();
        $movies = Movie::all();

        return view('admin.showtimes.index', compact('showtimes', 'movies'));
    }

    public function create()
    {
        return view('admin.showtimes.create', [
            'movies' => Movie::query()->where('status', '!=', 'stopped')->get(),
            'rooms' => $this->operationalRooms(),
            'cinema' => $this->cinemaContext->current(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedData($request);
        $validated['show_time'] .= ':00';
        $validated['cinema_id'] = $this->cinemaContext->id();

        if (Movie::query()->findOrFail($validated['movie_id'])->status === 'stopped') {
            return back()->withErrors(['movie_id' => 'Phim đã ngừng chiếu không thể tạo suất chiếu.'])->withInput();
        }
        if ($this->hasConflict($validated)) {
            return back()->withErrors(['show_time' => 'Đã có suất chiếu ở cùng phòng, ngày và giờ.'])->withInput();
        }

        Showtime::query()->create($validated);

        return redirect()->route('admin.showtimes.index')->with('success', 'Suất chiếu đã được tạo thành công.');
    }

    public function edit(Showtime $showtime)
    {
        $this->assertOperationalShowtime($showtime);

        return view('admin.showtimes.edit', [
            'showtime' => $showtime,
            'movies' => Movie::query()->where('status', '!=', 'stopped')->get(),
            'rooms' => $this->operationalRooms(),
            'cinema' => $this->cinemaContext->current(),
        ]);
    }

    public function update(Request $request, Showtime $showtime)
    {
        $this->assertOperationalShowtime($showtime);
        $validated = $this->validatedData($request);
        $validated['show_time'] .= ':00';
        $validated['cinema_id'] = $this->cinemaContext->id();

        if (Movie::query()->findOrFail($validated['movie_id'])->status === 'stopped') {
            return back()->withErrors(['movie_id' => 'Phim đã ngừng chiếu không thể cập nhật suất chiếu.'])->withInput();
        }
        if ($this->hasConflict($validated, $showtime->id)) {
            return back()->withErrors(['show_time' => 'Đã có suất chiếu ở cùng phòng, ngày và giờ.'])->withInput();
        }

        $showtime->update($validated);

        return redirect()->route('admin.showtimes.index')->with('success', 'Suất chiếu đã được cập nhật.');
    }

    public function destroy(Showtime $showtime)
    {
        $this->assertOperationalShowtime($showtime);
        $showtime->delete();

        return redirect()->route('admin.showtimes.index')->with('success', 'Suất chiếu đã được xóa.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'movie_id' => ['required', 'exists:movies,id'],
            'room_id' => ['required', Rule::exists('rooms', 'id')->where(fn ($query) => $query->where('cinema_id', $this->cinemaContext->id())->where('status', 'active'))],
            'show_date' => ['required', 'date'],
            'show_time' => ['required', 'date_format:H:i'],
            'price' => ['required', 'numeric', 'min:0'],
            'vip_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'cancelled', 'finished'])],
        ]);
    }

    private function operationalRooms()
    {
        return Room::query()->where('cinema_id', $this->cinemaContext->id())
            ->operational()->orderBy('code')->get();
    }

    private function hasConflict(array $data, ?int $exceptId = null): bool
    {
        return Showtime::query()->where('cinema_id', $this->cinemaContext->id())
            ->where('room_id', $data['room_id'])->whereDate('show_date', $data['show_date'])
            ->where('show_time', $data['show_time'])->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))->exists();
    }

    private function assertOperationalShowtime(Showtime $showtime): void
    {
        $showtime->loadMissing('room');
        abort_unless(
            $showtime->cinema_id === $this->cinemaContext->id()
            && $showtime->room?->cinema_id === $this->cinemaContext->id()
            && $showtime->room?->status === 'active',
            404
        );
    }
}
