<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Seat;
use App\Services\CinemaContext;
use Illuminate\Http\Request;

class SeatController extends Controller
{
    public function __construct(private readonly CinemaContext $cinemaContext) {}

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
        $room->load('cinema');
        $seats = $room->seats()->orderBy('row')->orderBy('number')->get();

        return view('admin.seats.manage', compact('room', 'seats'));
    }

    public function generate(Request $request, Room $room)
    {
        $this->assertOperationalRoom($room);
        $validated = $request->validate([
            'rows' => ['required', 'regex:/^[A-Z]-[A-Z]$/'],
            'seats_per_row' => ['required', 'integer', 'min:1', 'max:50'],
            'vip_rows' => ['nullable', 'string', 'max:100'],
        ]);
        [$startRow, $endRow] = explode('-', strtoupper($validated['rows']));
        if (ord($startRow) > ord($endRow)) {
            return back()->with('error', 'Khoảng hàng không hợp lệ. Ví dụ đúng: A-H.');
        }

        $vipRows = collect(explode(',', strtoupper($validated['vip_rows'] ?? '')))
            ->map(fn ($row) => trim($row))->filter()->unique()->values()->all();
        $created = 0;
        for ($rowOrd = ord($startRow); $rowOrd <= ord($endRow); $rowOrd++) {
            $row = chr($rowOrd);
            for ($number = 1; $number <= $validated['seats_per_row']; $number++) {
                $seatCode = $row.$number;
                if (Seat::query()->where('room_id', $room->id)->where('seat_code', $seatCode)->exists()) {
                    continue;
                }
                Seat::query()->create([
                    'room_id' => $room->id, 'row' => $row, 'number' => $number,
                    'seat_code' => $seatCode, 'type' => in_array($row, $vipRows, true) ? 'vip' : 'normal',
                    'status' => 'active',
                ]);
                $created++;
            }
        }
        $room->update(['total_seats' => $room->seats()->count()]);

        return redirect()->route('admin.seats.manage', $room)->with('success', "Đã tạo thêm {$created} ghế cho phòng {$room->name}.");
    }

    public function update(Request $request, Seat $seat)
    {
        $seat->loadMissing('room');
        $this->assertOperationalRoom($seat->room);
        $validated = $request->validate([
            'type' => ['required', 'in:normal,vip,couple'],
            'status' => ['required', 'in:active,maintenance'],
        ]);
        $seat->update($validated);

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
}
