<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Cinema;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    /**
     * Display a listing of rooms.
     */
    public function index(Request $request)
    {
        $query = Room::with('cinema');

        $search = $request->query('search');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $rooms = $query->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.rooms.index', compact('rooms', 'search'));
    }

    /**
     * Show the form for creating a new room.
     */
    public function create()
    {
        $cinemas = Cinema::orderBy('name')->get();

        return view('admin.rooms.create', compact('cinemas'));
    }

    /**
     * Store a newly created room.
     */
    public function store(Request $request)
    {
        $request->merge([
            'room_type' => $this->normalizeRoomType($request->input('room_type')),
        ]);

        $validated = $request->validate([
            'cinema_id'   => ['required', 'exists:cinemas,id'],
            'name'        => ['required', 'string', 'max:255'],
            'room_type'   => ['required', Rule::in(['2D', '3D', 'IMAX'])],
            'total_seats' => ['required', 'integer', 'min:0'],
            'status'      => ['required', 'in:active,inactive'],
        ]);

        Room::create($validated);

        return redirect()
            ->route('admin.rooms.index')
            ->with('success', 'Thêm phòng chiếu thành công.');
    }

    /**
     * Display the specified room.
     */
    public function show(Room $room)
    {
        $room->load(['cinema', 'seats']);

        return view('admin.rooms.show', compact('room'));
    }

    /**
     * Show the form for editing the specified room.
     */
    public function edit(Room $room)
    {
        $cinemas = Cinema::orderBy('name')->get();

        return view('admin.rooms.edit', compact('room', 'cinemas'));
    }

    /**
     * Update the specified room.
     */
    public function update(Request $request, Room $room)
    {
        $request->merge([
            'room_type' => $this->normalizeRoomType($request->input('room_type')),
        ]);

        $validated = $request->validate([
            'cinema_id'   => ['required', 'exists:cinemas,id'],
            'name'        => ['required', 'string', 'max:255'],
            'room_type'   => ['required', Rule::in(['2D', '3D', 'IMAX'])],
            'total_seats' => ['required', 'integer', 'min:0'],
            'status'      => ['required', 'in:active,inactive'],
        ]);

        $room->update($validated);

        return redirect()
            ->route('admin.rooms.index')
            ->with('success', 'Cập nhật phòng chiếu thành công.');
    }

    /**
     * Remove the specified room.
     */
    public function destroy(Room $room)
    {
        $room->seats()->delete();

        $room->delete();

        return redirect()
            ->route('admin.rooms.index')
            ->with('success', 'Xóa phòng chiếu thành công.');
    }

    private function normalizeRoomType(?string $roomType): ?string
    {
        if ($roomType === null) {
            return null;
        }

        $value = trim($roomType);
        $upper = mb_strtoupper($value, 'UTF-8');

        if (str_starts_with($upper, '2D')) {
            return '2D';
        }

        if (str_starts_with($upper, '3D')) {
            return '3D';
        }

        if (str_contains($upper, 'IMAX')) {
            return 'IMAX';
        }

        return $value;
    }
}
