<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        AdminDashboardService $dashboard,
    ): View {
        return view('admin.dashboard', $dashboard->overview($request->user()));
    }
}
