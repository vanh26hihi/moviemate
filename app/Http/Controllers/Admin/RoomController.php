<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\CinemaContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoomController extends Controller
{
    public function __construct(private readonly CinemaContext $cinemaContext) {}

    public function index(Request $request)
    {
        $search = $request->query('search');
        $rooms = Room::query()->with(['cinema', 'latestPublishedLayout.cells.seat', 'draftLayout'])
            ->where('cinema_id', $this->cinemaContext->id())
            ->operational()
            ->when($search, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('code')->paginate(15)->withQueryString();

        return view('admin.rooms.index', compact('rooms', 'search'));
    }

    public function create()
    {
        $cinema = $this->cinemaContext->current();

        return view('admin.rooms.create', compact('cinema'));
    }

    public function store(Request $request)
    {
        $this->normalizeRoomInput($request);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('rooms', 'code')->where('cinema_id', $this->cinemaContext->id())],
            'name' => ['required', 'string', 'max:255'],
            'room_type' => ['required', Rule::in(['2D', '3D', 'IMAX'])],
            'status' => ['required', 'in:active,inactive'],
        ]);
        $this->ensureOperationalNameIsUnique($validated);

        $room = Room::query()->create([...$validated, 'total_seats' => 0, 'cinema_id' => $this->cinemaContext->id()]);

        return redirect()->route('admin.rooms.layout.show', $room)
            ->with('success', 'Đã tạo phòng. Hãy thiết kế và publish layout trước khi tạo suất chiếu.');
    }

    public function edit(Room $room)
    {
        $this->assertOperationalRoom($room);
        $cinema = $this->cinemaContext->current();

        return view('admin.rooms.edit', compact('room', 'cinema'));
    }

    public function update(Request $request, Room $room)
    {
        $this->assertOperationalRoom($room);
        $this->normalizeRoomInput($request);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('rooms', 'code')->where('cinema_id', $this->cinemaContext->id())->ignore($room->id)],
            'name' => ['required', 'string', 'max:255'],
            'room_type' => ['required', Rule::in(['2D', '3D', 'IMAX'])],
            'status' => ['required', 'in:active,inactive'],
        ]);
        $this->ensureOperationalNameIsUnique($validated, $room->id);

        $room->update([...$validated, 'cinema_id' => $this->cinemaContext->id()]);

        return redirect()->route('admin.rooms.index')->with('success', 'Cập nhật phòng chiếu thành công.');
    }

    public function destroy(Room $room)
    {
        $this->assertOperationalRoom($room);
        abort_if($room->showtimes()->exists(), 409, 'Không thể xóa phòng đã có lịch sử suất chiếu.');
        DB::transaction(function () use ($room): void {
            DB::table('room_layout_cells')->whereIn(
                'room_layout_id',
                DB::table('room_layouts')->where('room_id', $room->id)->select('id')
            )->delete();
            DB::table('room_layouts')->where('room_id', $room->id)->delete();
            $room->seats()->delete();
            $room->delete();
        });

        return redirect()->route('admin.rooms.index')->with('success', 'Xóa phòng chiếu thành công.');
    }

    private function assertOperationalRoom(Room $room): void
    {
        abort_unless($room->cinema_id === $this->cinemaContext->id() && $room->status === 'active', 404);
    }

    private function normalizeRoomType(?string $roomType): ?string
    {
        if ($roomType === null) {
            return null;
        }
        $upper = mb_strtoupper(trim($roomType), 'UTF-8');

        return match (true) {
            str_starts_with($upper, '2D') => '2D',
            str_starts_with($upper, '3D') => '3D',
            str_contains($upper, 'IMAX') => 'IMAX',
            default => trim($roomType),
        };
    }

    private function normalizeRoomInput(Request $request): void
    {
        $request->merge([
            'code' => is_string($request->input('code')) ? mb_strtoupper(trim($request->input('code'))) : $request->input('code'),
            'name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name'),
            'room_type' => $this->normalizeRoomType($request->input('room_type')),
        ]);
    }

    private function ensureOperationalNameIsUnique(array $data, ?int $exceptId = null): void
    {
        if ($data['status'] !== 'active') {
            return;
        }

        $normalizedName = mb_strtolower(trim($data['name']));
        $exists = Room::query()->where('cinema_id', $this->cinemaContext->id())
            ->where('status', 'active')
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->pluck('name')->contains(fn ($name) => mb_strtolower(trim($name)) === $normalizedName);

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Tên phòng đang hoạt động không được trùng trong cùng cơ sở.',
            ]);
        }
    }
}
