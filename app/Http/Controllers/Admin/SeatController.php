<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Seat;
use Illuminate\Http\Request;

class SeatController extends Controller
{
    public function index(Request $request)
    {
        $query = Seat::with(['room.cinema']);

        if ($roomId = $request->query('room_id')) {
            $query->where('room_id', $roomId);
        }

        $seats = $query
            ->orderBy('room_id')
            ->orderBy('row')
            ->orderBy('number')
            ->paginate(30)
            ->withQueryString();

        $rooms = Room::with('cinema')->orderBy('name')->get();

        return view('admin.seats.index', compact('seats', 'rooms', 'roomId'));
    }

    public function manage(Room $room)
    {
        $room->load('cinema');
        $seats = $room->seats()
            ->orderBy('row')
            ->orderBy('number')
            ->get();

        return view('admin.seats.manage', compact('room', 'seats'));
    }

    public function generate(Request $request, Room $room)
    {
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
            ->map(fn ($row) => trim($row))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $created = 0;

        for ($rowOrd = ord($startRow); $rowOrd <= ord($endRow); $rowOrd++) {
            $row = chr($rowOrd);

            for ($number = 1; $number <= $validated['seats_per_row']; $number++) {
                $seatCode = $row . $number;

                $exists = Seat::where('room_id', $room->id)
                    ->where('seat_code', $seatCode)
                    ->exists();

                if ($exists) {
                    continue;
                }

                Seat::create([
                    'room_id' => $room->id,
                    'row' => $row,
                    'number' => $number,
                    'seat_code' => $seatCode,
                    'type' => in_array($row, $vipRows, true) ? 'vip' : 'normal',
                    'status' => 'active',
                ]);

                $created++;
            }
        }

        $room->update([
            'total_seats' => $room->seats()->count(),
        ]);

        return redirect()
            ->route('admin.seats.manage', $room)
            ->with('success', "Đã tạo thêm {$created} ghế cho phòng {$room->name}.");
    }

    public function update(Request $request, Seat $seat)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:normal,vip,couple'],
            'status' => ['required', 'in:active,maintenance'],
        ]);

        $seat->update($validated);

        return back()->with('success', 'Cập nhật ghế thành công.');
    }
}
