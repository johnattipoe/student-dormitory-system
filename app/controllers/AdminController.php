<?php

namespace App\Controllers;

use App\Services\UserService;
use App\Services\StudentService;
use App\Services\RoomService;
use App\Services\ReportService;

class AdminController
{
    private UserService $userService;
    private StudentService $studentService;
    private RoomService $roomService;
    private ReportService $reportService;

    public function __construct()
    {
        $this->userService = new UserService();
        $this->studentService = new StudentService();
        $this->roomService = new RoomService();
        $this->reportService = new ReportService();
    }

    public function dashboard(): void
    {
        require_role(ROLE_ADMIN);

        $stats = [
            'users' => $this->userService->count(),
            'students' => $this->studentService->count(),
            'rooms' => $this->roomService->count(),
            'occupancy' => $this->roomService->occupancyRate(),
        ];

        include __DIR__ . '/../../public/views/admin/dashboard.php';
    }

    public function users(): void
    {
        require_role(ROLE_ADMIN);

        $users = $this->userService->all();

        include __DIR__ . '/../../public/views/admin/users/index.php';
    }

    public function students(): void
    {
        require_role(ROLE_ADMIN);

        $students = $this->studentService->all();

        include __DIR__ . '/../../public/views/admin/students/index.php';
    }

    public function rooms(): void
    {
        require_role(ROLE_ADMIN);

        $rooms = $this->roomService->all();

        include __DIR__ . '/../../public/views/admin/rooms/index.php';
    }

    public function reports(): void
    {
        require_role(ROLE_ADMIN);

        $reports = $this->reportService->dashboard();

        include __DIR__ . '/../../public/views/reports/dashboard.php';
    }
}