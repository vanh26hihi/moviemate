<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportRequest;
use App\Services\AdminDashboardService;
use App\Services\Reports\ReportScopeFactory;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(
        ReportRequest $request,
        ReportScopeFactory $scopes,
        AdminDashboardService $dashboard,
    ): View {
        return view('admin.dashboard', $dashboard->overview(
            $scopes->forUser($request->user(), $request->validated()),
        ));
    }
}
