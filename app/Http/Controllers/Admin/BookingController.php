<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexBookingRequest;
use App\Models\Booking;
use App\Services\Admin\AdminBookingDetailService;
use App\Services\Admin\AdminBookingQuery;
use Illuminate\View\View;

final class BookingController extends Controller
{
    public function index(IndexBookingRequest $request, AdminBookingQuery $bookings): View
    {
        $filters = $request->validated();

        return view('admin.bookings.index', [
            'bookings' => $bookings->paginate($filters),
            'summary' => $bookings->summary($filters),
            'filters' => $filters,
            ...$bookings->filterOptions(),
        ]);
    }

    public function show(Booking $booking, AdminBookingDetailService $details): View
    {
        return view('admin.bookings.show', $details->get(
            $booking,
            request()->user()?->can('activity_logs.view') === true,
        ));
    }
}
