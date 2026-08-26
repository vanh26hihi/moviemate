<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListFoodOrdersRequest;
use App\Models\Order;
use App\Services\Admin\AdminFoodOrderQuery;
use Illuminate\View\View;

class FoodOrderController extends Controller
{
    public function __construct(private readonly AdminFoodOrderQuery $foodOrders) {}

    public function index(ListFoodOrdersRequest $request): View
    {
        $filters = $request->validated();
        $orders = $this->foodOrders->paginate($filters);
        $summary = $this->foodOrders->summary($filters);

        return view('admin.food-orders.index', compact('orders', 'summary', 'filters'));
    }

    public function show(Order $order): View
    {
        $order = $this->foodOrders->findSuccessful((int) $order->getKey());

        return view('admin.food-orders.show', compact('order'));
    }
}
