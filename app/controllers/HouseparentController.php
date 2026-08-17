<?php

namespace App\Controllers;

use App\Services\StudentService;
use App\Services\AttendanceService;
use App\Services\RoomService;
use App\Services\VisitorService;
use App\Services\IncidentService;

class HouseparentController
{
    private StudentService $studentService;
    private AttendanceService $attendanceService;
    private RoomService $roomService;
    private VisitorService $visitorService;
    private IncidentService $incidentService;

    public function __construct()
    {
        $this->studentService = new StudentService();
        $this->attendanceService = new AttendanceService();
        $this->roomService = new RoomService();
        $this->visitorService = new VisitorService();
        $this->incidentService = new IncidentService();
    }

    public function dashboard(): void
    {
        require_role(ROLE_HOUSEPARENT);

        $houseId = current_house_id();
        if ($houseId === null || $houseId === '') {
            access_denied();
        }
        require_house_access($houseId);

        $stats = [
            'students' => $this->studentService->countByHouse($houseId),
            'attendance' => $this->attendanceService->todayByHouse($houseId),
            'rooms' => $this->roomService->countByHouse($houseId),
            'visitors' => $this->visitorService->todayByHouse($houseId),
            'incidents' => $this->incidentService->openByHouse($houseId),
        ];

        include __DIR__ . '/../../public/views/houseparent/dashboard/index.php';
    }

    public function students(): void
    {
        require_role(ROLE_HOUSEPARENT);

        $houseId = current_house_id();
        if ($houseId === null || $houseId === '') {
            access_denied();
        }
        require_house_access($houseId);

        $students = $this->studentService->byHouse($houseId);

        include __DIR__ . '/../../public/views/houseparent/students/index.php';
    }

    public function attendance(): void
    {
        require_role(ROLE_HOUSEPARENT);

        $houseId = current_house_id();
        if ($houseId === null || $houseId === '') {
            access_denied();
        }
        require_house_access($houseId);

        $attendance = $this->attendanceService->byHouse($houseId);

        include __DIR__ . '/../../public/views/houseparent/attendance/index.php';
    }

    public function markAttendance(): void
    {
        require_role(ROLE_HOUSEPARENT);

        $houseId = current_house_id();
        if ($houseId === null || $houseId === '') {
            access_denied();
        }
        require_house_access($houseId);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            include __DIR__ . '/../../public/views/houseparent/attendance/mark-attendance.php';
            return;
        }

        $user = current_user();

        $data = [
            'studentId' => sanitize($_POST['studentId'] ?? ''),
            'status' => sanitize($_POST['status'] ?? ''),
            'date' => sanitize($_POST['date'] ?? date('Y-m-d')),
            'markedBy' => $user['uid'] ?? null,
            'houseId' => $houseId,
        ];

        $result = $this->attendanceService->mark($data);

        if (!$result['success']) {
            flash('error', $result['message']);
            redirect(base_url('index.php?route=/views/houseparent/attendance/mark-attendance.php'));
        }

        flash('success', 'Attendance marked successfully.');

        redirect(
            base_url('index.php?route=/views/houseparent/attendance/index.php')
        );
    }

    public function rooms(): void
    {
        require_role(ROLE_HOUSEPARENT);

        $houseId = current_house_id();
        if ($houseId === null || $houseId === '') {
            access_denied();
        }
        require_house_access($houseId);

        $rooms = $this->roomService->byHouse($houseId);

        include __DIR__ . '/../../public/views/houseparent/rooms/index.php';
    }

    public function visitors(): void
    {
        require_role(ROLE_HOUSEPARENT);

        $houseId = current_house_id();
        if ($houseId === null || $houseId === '') {
            access_denied();
        }
        require_house_access($houseId);

        $visitors = $this->visitorService->byHouse($houseId);

        include __DIR__ . '/../../public/views/houseparent/visitors/index.php';
    }

    public function incidents(): void
    {
        require_role(ROLE_HOUSEPARENT);

        $houseId = current_house_id();
        if ($houseId === null || $houseId === '') {
            access_denied();
        }
        require_house_access($houseId);

        $incidents = $this->incidentService->byHouse($houseId);

        include __DIR__ . '/../../public/views/houseparent/incidents/index.php';
    }
}
