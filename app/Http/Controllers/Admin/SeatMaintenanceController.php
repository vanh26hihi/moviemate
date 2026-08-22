<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkSeatMaintenanceRequest;
use App\Http\Requests\Admin\IndexSeatMaintenanceRequest;
use App\Http\Requests\Admin\UpdateSeatMaintenanceRequest;
use App\Models\Room;
use App\Models\Seat;
use App\Models\SeatIncident;
use App\Services\Admin\AdminSeatMaintenanceQuery;
use App\Services\CinemaAccessService;
use App\Services\Seats\SeatIncidentImpactClassifier;
use App\Services\Seats\SeatMaintenanceService;
use App\Services\Seats\SeatRelocationCandidateService;
use App\Support\StatusLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class SeatMaintenanceController extends Controller
{
    public function index(
        IndexSeatMaintenanceRequest $request,
        Room $room,
        AdminSeatMaintenanceQuery $query,
    ): View {
        return view('admin.rooms.seat-maintenance', $query->get($room, $request->validated()));
    }

    public function update(
        UpdateSeatMaintenanceRequest $request,
        Room $room,
        Seat $seat,
        SeatMaintenanceService $maintenance,
    ): RedirectResponse {
        $result = $maintenance->update(
            $room,
            $seat,
            $request->validated('status'),
            $request->validated('reason', SeatIncident::REASON_MAINTENANCE),
            $request->validated('note'),
        );

        if ($result->incidentId !== null) {
            return redirect()->route('admin.rooms.seat-incidents.show', [$room, $result->incidentId])
                ->with('success', 'Đã tạo sự cố và ngừng bán ghế '.$result->unitLabels[0].'.');
        }

        return back()->with($result->changed ? 'success' : 'warning', $result->changed
            ? 'Đã cập nhật '.$result->unitLabels[0].' sang '.mb_strtolower(StatusLabel::for('seat', $result->status)).'.'
            : $result->unitLabels[0].' đã ở trạng thái yêu cầu; không có thay đổi mới.');
    }

    public function showIncident(
        Room $room,
        SeatIncident $incident,
        CinemaAccessService $cinemaAccess,
        SeatIncidentImpactClassifier $classifier,
        SeatRelocationCandidateService $candidates,
    ): View {
        $cinemaAccess->authorizeCinema(auth()->user(), (int) $room->cinema_id);
        abort_unless((int) $incident->room_id === (int) $room->id
            && (int) $incident->cinema_id === (int) $room->cinema_id, 404);

        $incident->load([
            'cinema', 'room', 'reportedBy', 'incidentSeats.seat',
            'impacts.bookingSeat.seat', 'impacts.bookingSeat.showtime.movie',
            'impacts.bookingSeat.booking.user', 'impacts.bookingSeat.booking.payments',
            'impacts.bookingSeat.admissionTicket.printState',
        ]);
        $groups = $incident->impacts->groupBy(fn ($impact) => $impact->bookingSeat->booking_id)
            ->map(function ($impacts) use ($classifier): array {
                $booking = $impacts->first()->bookingSeat->booking;

                return [
                    'booking' => $booking,
                    'impacts' => $impacts,
                    'classification' => $impacts->every(fn ($impact): bool => $impact->resolution_status === 'resolved'
                        && $impact->detected_classification === 'ordinary_hold')
                        ? 'ordinary_hold'
                        : $classifier->classify($booking),
                ];
            })->values();
        if ($groups->contains(fn (array $group): bool => $group['classification'] === 'paid')) {
            $incident->impacts->load(['resolution.originalSeat', 'resolution.replacementSeat']);
        } else {
            $incident->impacts->each(fn ($impact) => $impact->setRelation('resolution', null));
        }
        $relocationOptions = $candidates->forIncident($incident);

        return view('admin.rooms.seat-incident-show', compact('room', 'incident', 'groups', 'relocationOptions'));
    }

    public function bulk(
        BulkSeatMaintenanceRequest $request,
        Room $room,
        SeatMaintenanceService $maintenance,
    ): RedirectResponse {
        $result = $maintenance->bulk($room, $request->validated('seat_ids'), $request->validated('status'));

        return back()->with($result->changed ? 'success' : 'warning', $result->changed
            ? 'Đã cập nhật '.count($result->unitLabels).' đơn vị ghế sang '.mb_strtolower(StatusLabel::for('seat', $result->status)).'.'
            : 'Các ghế đã ở trạng thái yêu cầu; không có thay đổi mới.');
    }
}
