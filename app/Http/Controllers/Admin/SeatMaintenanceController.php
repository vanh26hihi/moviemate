<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkSeatMaintenanceRequest;
use App\Http\Requests\Admin\IndexSeatMaintenanceRequest;
use App\Http\Requests\Admin\UpdateSeatMaintenanceRequest;
use App\Models\Room;
use App\Models\Seat;
use App\Services\Admin\AdminSeatMaintenanceQuery;
use App\Services\Seats\SeatMaintenanceService;
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
        $result = $maintenance->update($room, $seat, $request->validated('status'));

        return back()->with($result->changed ? 'success' : 'warning', $result->changed
            ? 'Đã cập nhật '.$result->unitLabels[0].' sang '.mb_strtolower(StatusLabel::for('seat', $result->status)).'.'
            : $result->unitLabels[0].' đã ở trạng thái yêu cầu; không có thay đổi mới.');
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
