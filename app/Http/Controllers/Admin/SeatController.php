<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoomLayoutRequest;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Seat;
use App\Services\ActivityLogger;
use App\Services\CinemaAccessService;
use App\Services\RoomLayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SeatController extends Controller
{
    public function __construct(
        private readonly CinemaAccessService $cinemaAccess,
        private readonly RoomLayoutService $layouts,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.rooms.index');
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
        $seatRelation = fn ($query) => $query->withCount('bookingSeats');
        $layout = $room->draftLayout()->with(['cells.seat' => $seatRelation])->first()
            ?? $room->latestPublishedLayout()->with(['cells.seat' => $seatRelation])->first();
        $roomSeatCodes = Seat::query()->where('room_id', $room->id)->pluck('seat_code')->values();
        $layoutSummary = $this->layoutSummary($layout);

        return view('admin.rooms.layout', compact('room', 'layout', 'roomSeatCodes', 'layoutSummary'));
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

        return redirect()->route('admin.rooms.layout.show', $room)->with('success', 'Đã tạo bản nháp sơ đồ ghế.');
    }

    public function saveDraft(SaveRoomLayoutRequest $request, Room $room)
    {
        $this->assertOperationalRoom($room);
        $draft = $room->draftLayout()->firstOrFail();
        $this->layouts->saveDraft($draft, $request->validated('layout'), Auth::id());

        return redirect()->route('admin.rooms.layout.show', $room)->with('success', 'Đã lưu bản nháp sơ đồ ghế.');
    }

    public function publish(Room $room)
    {
        $this->assertOperationalRoom($room);
        $draft = $room->draftLayout()->firstOrFail();
        $published = DB::transaction(function () use ($draft): RoomLayout {
            $published = $this->layouts->publish($draft, Auth::id());
            $this->activityLogger->log(
                'room_layout.published',
                $published,
                ['status' => 'draft', 'layout_version' => $draft->version],
                ['status' => $published->status, 'layout_version' => $published->version],
                [
                    'room_id' => $published->room_id,
                    'layout_id' => $published->id,
                    'seat_count' => $published->cells()->where('cell_type', 'seat')->count(),
                ],
            );

            return $published;
        });

        return redirect()->route('admin.rooms.layout.show', $room)
            ->with('success', "Đã phát hành sơ đồ ghế phiên bản {$published->version}.");
    }

    public function preview(Request $request, Room $room)
    {
        $this->assertManagedRoom($room);
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
            ->with('warning', 'Trình tạo ma trận ghế cũ đã ngừng sử dụng. Hãy dùng trình thiết kế sơ đồ ghế mới.');
    }

    private function assertOperationalRoom(Room $room): void
    {
        $this->cinemaAccess->authorizeCinema(auth()->user(), (int) $room->cinema_id);
        abort_unless($room->status === 'active', 404);
    }

    private function assertManagedRoom(Room $room): void
    {
        $this->cinemaAccess->authorizeCinema(auth()->user(), (int) $room->cinema_id);
    }

    private function layoutSummary(?RoomLayout $layout): array
    {
        if (! $layout) {
            return [];
        }

        $seats = $layout->cells->where('cell_type', 'seat')->pluck('seat')->filter();
        $used = $layout->cells->count();

        return [
            'rows' => $layout->rows,
            'columns' => $layout->columns,
            'used' => $used,
            'empty' => max(0, ($layout->rows * $layout->columns) - $used),
            'normal' => $seats->where('type', 'normal')->count(),
            'vip' => $seats->where('type', 'vip')->count(),
            'couple_pairs' => $seats->where('type', 'couple')->pluck('pair_code')->filter()->unique()->count(),
            'aisles' => $layout->cells->where('cell_type', 'aisle')->count(),
            'maintenance' => $seats->where('status', 'maintenance')->count(),
            'inactive' => $seats->whereIn('status', ['inactive', 'retired'])->count(),
            'capacity' => $seats->where('status', 'active')->count(),
        ];
    }
}
