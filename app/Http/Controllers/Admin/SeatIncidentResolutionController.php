<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RelocateSeatIncidentImpactRequest;
use App\Models\Room;
use App\Models\SeatIncident;
use App\Models\SeatIncidentImpact;
use App\Services\CinemaAccessService;
use App\Services\Seats\SeatIncidentResolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SeatIncidentResolutionController extends Controller
{
    public function relocate(
        RelocateSeatIncidentImpactRequest $request,
        Room $room,
        SeatIncident $incident,
        SeatIncidentImpact $impact,
        CinemaAccessService $cinemaAccess,
        SeatIncidentResolutionService $resolutions,
    ): RedirectResponse {
        $this->authorizeScope($request, $room, $incident, $impact, $cinemaAccess);
        $resolutions->relocate($incident, $impact, $request->integer('replacement_seat_id'), $request->user());

        return back()->with('success', 'Đã đổi ghế. Khách hàng không phải thanh toán thêm.');
    }

    public function requireRefund(
        Request $request,
        Room $room,
        SeatIncident $incident,
        SeatIncidentImpact $impact,
        CinemaAccessService $cinemaAccess,
        SeatIncidentResolutionService $resolutions,
    ): RedirectResponse {
        $this->authorizeScope($request, $room, $incident, $impact, $cinemaAccess);
        $resolutions->requireRefund($incident, $impact, $request->user());

        return back()->with('warning', 'Đã chuyển sang trạng thái cần xử lý hoàn tiền. Chưa thực hiện hoàn tiền.');
    }

    private function authorizeScope(
        Request $request,
        Room $room,
        SeatIncident $incident,
        SeatIncidentImpact $impact,
        CinemaAccessService $cinemaAccess,
    ): void {
        $cinemaAccess->authorizeCinema($request->user(), (int) $room->cinema_id);
        abort_unless((int) $incident->room_id === (int) $room->id
            && (int) $incident->cinema_id === (int) $room->cinema_id
            && (int) $impact->seat_incident_id === (int) $incident->id, 404);
    }
}
