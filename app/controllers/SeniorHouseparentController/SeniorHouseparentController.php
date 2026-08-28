<?php

namespace App\Controllers;

use App\Services\StudentService;
use App\Services\AttendanceService;
use App\Services\RoomService;
use App\Services\VisitorService;
use App\Services\IncidentService;

class SeniorHouseparentController
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
        require_role(ROLE_SENIOR_HOUSEPARENT);

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

        include __DIR__ . '/../../../public/views/senior-houseparent/dashboard/index.php';
    }

    public function students(): void
    {
        require_role(ROLE_SENIOR_HOUSEPARENT);

        $houseId = current_house_id();
        if ($houseId === null || $houseId === '') {
            access_denied();
        }
        require_house_access($houseId);

        $students = $this->studentService->byHouse($houseId);

        include __DIR__ . '/../../../public/views/senior-houseparent/students/index/index.php';
    }

    public function attendance(): void
    {
        require_role(ROLE_SENIOR_HOUSEPARENT);

        $houseId = current_house_id();
        if ($houseId === null || $houseId === '') {
            access_denied();
        }
        require_house_access($houseId);

        $attendance = $this->attendanceService->byHouse($houseId);

        include __DIR__ . '/../../../public/views/senior-houseparent/attendance/index/index.php';
    }

    public function rooms(): void
    {
        require_role(ROLE_SENIOR_HOUSEPARENT);

        $houseId = current_house_id();
        if ($houseId === null || $houseId === '') {
            access_denied();
        }
        require_house_access($houseId);

        $rooms = $this->roomService->byHouse($houseId);

        include __DIR__ . '/../../../public/views/senior-houseparent/rooms/index/index.php';
    }

    public function visitors(): void
    {
        require_role(ROLE_SENIOR_HOUSEPARENT);

        $houseId = current_house_id();
        if ($houseId === null || $houseId === '') {
            access_denied();
        }
        require_house_access($houseId);

        $visitors = $this->visitorService->byHouse($houseId);

        include __DIR__ . '/../../../public/views/senior-houseparent/visitors/index/index.php';
    }

    public function incidents(): void
    {
        require_role(ROLE_SENIOR_HOUSEPARENT);

        $houseId = current_house_id();
        if ($houseId === null || $houseId === '') {
            access_denied();
        }
        require_house_access($houseId);

        $incidents = $this->incidentService->byHouse($houseId);

        include __DIR__ . '/../../../public/views/senior-houseparent/incidents/index/index.php';
    }
}
