<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportRequest;
use App\Services\Reports\AdminReportingService;
use App\Services\Reports\ReportScopeFactory;
use Illuminate\View\View;

final class ReportController extends Controller
{
    public function __invoke(
        ReportRequest $request,
        ReportScopeFactory $scopes,
        AdminReportingService $reports,
    ): View {
        $scope = $scopes->forUser($request->user(), $request->validated());

        return view('admin.reports.index', $reports->report($scope));
    }
}
