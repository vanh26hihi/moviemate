<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(AdminDashboardService $dashboard): View
    {
        return view('admin.dashboard', $dashboard->overview());
    }
}
