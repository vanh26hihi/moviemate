<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoomLayoutRequest;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Seat;
use App\Services\ActivityLogger;
use App\Services\CinemaContext;
use App\Services\RoomLayoutService;
use App\Support\SeatPresentation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeatController extends Controller
{
    public function __construct(
        private readonly CinemaContext $cinemaContext,
        private readonly RoomLayoutService $layouts,
        private readonly ActivityLogger $activityLogger,
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

    public function update(Request $request, Seat $seat)
    {
        $seat->loadMissing('room');
        $this->assertOperationalRoom($seat->room);
        $validated = $request->validate([
            'type' => ['required', 'in:normal,vip,couple'],
            'status' => ['required', 'in:active,maintenance,inactive,retired'],
        ]);
        DB::transaction(function () use ($seat, $validated): void {
            $locked = Seat::query()->whereKey($seat->id)->lockForUpdate()->firstOrFail();
            $before = ['status' => $locked->status, 'seat_type' => $locked->type];
            $affectedCount = 1;
            if ($locked->type !== 'couple' && $validated['type'] === 'couple') {
                throw ValidationException::withMessages([
                    'seat' => 'Hãy tạo ghế đôi trong trình thiết kế để hệ thống ghép đủ hai vị trí liền nhau.',
                ]);
            }

            if ($locked->type === 'couple') {
                $pair = Seat::query()
                    ->where('room_id', $locked->room_id)
                    ->where('pair_code', $locked->pair_code)
                    ->lockForUpdate()
                    ->get();
                if (! SeatPresentation::isValidCouple($pair)) {
                    throw ValidationException::withMessages([
                        'seat' => 'Dữ liệu cặp ghế này không đồng nhất. Hãy sửa trong trình thiết kế sơ đồ ghế.',
                    ]);
                }
                if ($validated['type'] !== 'couple' && $pair->contains(fn (Seat $member): bool => $member->bookingSeats()->exists())) {
                    throw ValidationException::withMessages([
                        'seat' => 'Không thể tách ghế đôi đã có lịch sử đặt vé.',
                    ]);
                }

                foreach ($pair as $member) {
                    $member->update([
                        'type' => $validated['type'],
                        'status' => $validated['status'],
                        'pair_code' => $validated['type'] === 'couple' ? $member->pair_code : null,
                        'pair_position' => $validated['type'] === 'couple' ? $member->pair_position : null,
                    ]);
                }
                $affectedCount = $pair->count();
            } else {
                $locked->update($validated);
            }

            if ($before !== ['status' => $validated['status'], 'seat_type' => $validated['type']]) {
                $this->activityLogger->log(
                    'seat.maintenance_updated',
                    $locked,
                    $before,
                    ['status' => $validated['status'], 'seat_type' => $validated['type']],
                    [
                        'room_id' => $locked->room_id,
                        'seat_id' => $locked->id,
                        'seat_code' => $locked->seat_code,
                        'count' => $affectedCount,
                    ],
                );
            }
        });

        return back()->with('success', 'Cập nhật ghế thành công.');
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

    private function assertManagedRoom(Room $room): void
    {
        abort_unless($room->cinema_id === $this->cinemaContext->id(), 404);
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
