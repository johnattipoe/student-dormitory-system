<?php

namespace App\Controllers;

use App\Services\AttendanceService;
use App\Services\StudentService;

class AttendanceController
{
    private AttendanceService $attendanceService;
    private StudentService $studentService;

    public function __construct()
    {
        $this->attendanceService = new AttendanceService();
        $this->studentService = new StudentService();
    }

    public function index(): void
    {
        require_role(
            ROLE_ADMIN,
            ROLE_HOUSE_MASTER,
            ROLE_HOUSE_MISTRESS,
            ROLE_SENIOR_HOUSEPARENT
        );

        $attendance = $this->attendanceService->all();

        include __DIR__ . '/../../../public/views/attendance/index/index.php';
    }

    public function mark(): void
    {
        require_role(
            ROLE_ADMIN,
            ROLE_HOUSE_MASTER,
            ROLE_HOUSE_MISTRESS,
            ROLE_SENIOR_HOUSEPARENT
        );

        $students = $this->studentService->all();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            include __DIR__ . '/../../../public/views/attendance/mark/mark.php';
            return;
        }

        $data = [
            'studentId' => sanitize($_POST['studentId'] ?? ''),
            'status' => sanitize($_POST['status'] ?? ''),
            'date' => sanitize($_POST['date'] ?? date('Y-m-d')),
            'markedBy' => current_user()['uid'] ?? null,
        ];

        $result = $this->attendanceService->mark($data);

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            base_url('index.php?route=/views/attendance/index/index.php')
        );
    }

    public function history(): void
    {
        require_role(
            ROLE_ADMIN,
            ROLE_HOUSE_MASTER,
            ROLE_HOUSE_MISTRESS,
            ROLE_SENIOR_HOUSEPARENT
        );

        $attendance = $this->attendanceService->history();

        include __DIR__ . '/../../../public/views/attendance/history/history.php';
    }

    public function reports(): void
    {
        require_role(
            ROLE_ADMIN,
            ROLE_HOUSE_MASTER,
            ROLE_HOUSE_MISTRESS,
            ROLE_SENIOR_HOUSEPARENT
        );

        $report = $this->attendanceService->report();

        include __DIR__ . '/../../../public/views/attendance/reports/reports.php';
    }
}