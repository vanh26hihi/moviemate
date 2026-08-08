<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CinemaAccessService;
use Illuminate\Http\Request;

class FoodOrderController extends Controller
{
    public function __construct(private readonly CinemaAccessService $cinemaAccess) {}

    public function index(Request $request)
    {
        $query = Order::query()->with(['items.food', 'pickupCinema']);
        $cinemaId = $this->cinemaAccess->currentCinemaId($request->user());
        if ($cinemaId !== null) {
            $query->where(fn ($query) => $query->where('pickup_cinema_id', $cinemaId)
                ->orWhereHas('booking', fn ($booking) => $booking->where('cinema_id', $cinemaId)));
        } elseif (! $this->cinemaAccess->hasGlobalAccess($request->user())) {
            $query->whereRaw('1 = 0');
        }
        $orders = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.food-orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load('items.food');

        return view('admin.food-orders.show', compact('order'));
    }
}
