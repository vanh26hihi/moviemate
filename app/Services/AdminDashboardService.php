<?php

namespace App\Services;

use App\Services\Reports\AdminReportingService;
use App\Services\Reports\ReportScope;

final class AdminDashboardService
{
    public function __construct(private readonly AdminReportingService $reports) {}

    /** @return array<string, mixed> */
    public function overview(ReportScope $scope): array
    {
        return $this->reports->report($scope);
    }
}
