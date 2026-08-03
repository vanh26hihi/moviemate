<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoomLayoutRequest;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Seat;
use App\Services\CinemaContext;
use App\Services\RoomLayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SeatController extends Controller
{
    public function __construct(
        private readonly CinemaContext $cinemaContext,
        private readonly RoomLayoutService $layouts
    ) {}

    public function index(Request $request)
    {
        $rooms = $this->operationalRooms();
        $roomId = $request->query('room_id');
        $query = Seat::query()->with(['room.cinema'])->whereHas('room', fn ($query) => $query
            ->where('cinema_id', $this->cinemaContext->id())->where('status', 'active'));

        if ($roomId && $rooms->contains('id', (int) $roomId)) {
            $query->where('room_id', $roomId);
        }

        $seats = $query->orderBy('room_id')->orderBy('row')->orderBy('number')->paginate(30)->withQueryString();

        return view('admin.seats.index', compact('seats', 'rooms', 'roomId'));
    }

    public function manage(Room $room)
    {
        $this->assertOperationalRoom($room);

        return redirect()->route('admin.rooms.layout.show', $room);
    }

    public function layout(Room $room)
    {
        $this->assertOperationalRoom($room);
        $room->load('cinema');
        $layout = $room->draftLayout()->with('cells.seat')->first()
            ?? $room->latestPublishedLayout()->with('cells.seat')->first();

        return view('admin.rooms.layout', compact('room', 'layout'));
    }

    public function createDraft(Request $request, Room $room)
    {
        $this->assertOperationalRoom($room);
        $validated = $request->validate([
            'rows' => ['nullable', 'integer', 'min:1', 'max:30'],
            'columns' => ['nullable', 'integer', 'min:1', 'max:40'],
            'screen_position' => ['nullable', 'in:top,bottom'],
        ]);

        if ($this->layouts->latestPublishedFor($room)) {
            $this->layouts->clonePublishedToDraft($room, Auth::id());
        } else {
            $this->layouts->createBlankDraft(
                $room,
                Auth::id(),
                (int) ($validated['rows'] ?? 10),
                (int) ($validated['columns'] ?? 12),
                (string) ($validated['screen_position'] ?? 'top')
            );
        }

        return redirect()->route('admin.rooms.layout.show', $room)->with('success', 'Đã tạo layout nháp.');
    }

    public function saveDraft(SaveRoomLayoutRequest $request, Room $room)
    {
        $this->assertOperationalRoom($room);
        $draft = $room->draftLayout()->firstOrFail();
        $this->layouts->saveDraft($draft, $request->validated('layout'), Auth::id());

        return redirect()->route('admin.rooms.layout.show', $room)->with('success', 'Đã lưu layout nháp.');
    }

    public function publish(Room $room)
    {
        $this->assertOperationalRoom($room);
        $draft = $room->draftLayout()->firstOrFail();
        $published = $this->layouts->publish($draft, Auth::id());

        return redirect()->route('admin.rooms.layout.show', $room)
            ->with('success', "Đã publish layout v{$published->version}.");
    }

    public function preview(Request $request, Room $room)
    {
        $this->assertOperationalRoom($room);
        $layout = RoomLayout::query()->with('cells.seat')
            ->where('room_id', $room->id)
            ->when($request->integer('version'), fn ($query, $version) => $query->where('version', $version))
            ->orderByRaw("case when status = 'draft' then 0 else 1 end")
            ->orderByDesc('version')
            ->firstOrFail();

        return view('admin.rooms.layout-preview', compact('room', 'layout'));
    }

    public function generate(Request $request, Room $room)
    {
        $this->assertOperationalRoom($room);

        return redirect()->route('admin.rooms.layout.show', $room)
            ->with('warning', 'Trình tạo ma trận ghế cũ đã ngừng sử dụng. Hãy dùng Dynamic Layout Editor.');
    }

    public function update(Request $request, Seat $seat)
    {
        $seat->loadMissing('room');
        $this->assertOperationalRoom($seat->room);
        $validated = $request->validate([
            'type' => ['required', 'in:normal,vip,couple'],
            'status' => ['required', 'in:active,maintenance,inactive,retired'],
        ]);
        $seat->update($validated);

        return back()->with('success', 'Cập nhật trạng thái ghế thành công.');
    }

    private function operationalRooms()
    {
        return Room::query()->with('cinema')->where('cinema_id', $this->cinemaContext->id())
            ->operational()->orderBy('code')->get();
    }

    private function assertOperationalRoom(Room $room): void
    {
        abort_unless($room->cinema_id === $this->cinemaContext->id() && $room->status === 'active', 404);
    }
}
