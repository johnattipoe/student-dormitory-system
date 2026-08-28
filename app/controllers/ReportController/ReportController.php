<?php

namespace App\Controllers;

use App\Services\ReportService;

class ReportController
{
    private ReportService $reportService;

    public function __construct()
    {
        $this->reportService = new ReportService();
    }

    public function dashboard(): void
    {
        require_any_role([
            ROLE_ADMIN,
            ROLE_HOUSE_MASTER,
            ROLE_HOUSE_MISTRESS,
            ROLE_SENIOR_HOUSEPARENT,
        ]);

        $reports = $this->reportService->dashboard();

        include __DIR__ . '/../../../public/views/reports/dashboard/dashboard.php';
    }

    public function attendance(): void
    {
        require_any_role([
            ROLE_ADMIN,
            ROLE_HOUSE_MASTER,
            ROLE_HOUSE_MISTRESS,
            ROLE_SENIOR_HOUSEPARENT,
        ]);

        $report = $this->reportService->attendance();

        include __DIR__ . '/../../../public/views/reports/attendance/attendance.php';
    }

    public function occupancy(): void
    {
        require_any_role([
            ROLE_ADMIN,
            ROLE_HOUSE_MASTER,
            ROLE_HOUSE_MISTRESS,
            ROLE_SENIOR_HOUSEPARENT,
        ]);

        $report = $this->reportService->occupancy();

        include __DIR__ . '/../../../public/views/reports/occupancy/occupancy.php';
    }

    public function students(): void
    {
        require_any_role([ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS]);

        $report = $this->reportService->students();

        include __DIR__ . '/../../../public/views/reports/students/students.php';
    }

    public function visitors(): void
    {
        require_any_role([
            ROLE_ADMIN,
            ROLE_HOUSE_MASTER,
            ROLE_HOUSE_MISTRESS,
            ROLE_SENIOR_HOUSEPARENT,
            ROLE_SECURITY,
        ]);

        $report = $this->reportService->visitors();

        include __DIR__ . '/../../../public/views/reports/visitors/visitors.php';
    }

    public function incidents(): void
    {
        require_any_role([
            ROLE_ADMIN,
            ROLE_HOUSE_MASTER,
            ROLE_HOUSE_MISTRESS,
            ROLE_SENIOR_HOUSEPARENT,
            ROLE_SECURITY,
            ROLE_NURSE,
        ]);

        $report = $this->reportService->incidents();

        include __DIR__ . '/../../../public/views/reports/incidents/incidents.php';
    }

    public function medical(): void
    {
        require_any_role([ROLE_ADMIN, ROLE_NURSE]);

        $report = $this->reportService->medical();

        include __DIR__ . '/../../../public/views/reports/medical/medical.php';
    }
}