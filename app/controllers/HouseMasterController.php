<?php

namespace App\Controllers;

use App\Services\StudentService;
use App\Services\RoomService;
use App\Services\AttendanceService;
use App\Services\VisitorService;
use App\Services\IncidentService;

class HouseMasterController
{
    private StudentService $studentService;
    private RoomService $roomService;
    private AttendanceService $attendanceService;
    private VisitorService $visitorService;
    private IncidentService $incidentService;

    public function __construct()
    {
        $this->studentService = new StudentService();
        $this->roomService = new RoomService();
        $this->attendanceService = new AttendanceService();
        $this->visitorService = new VisitorService();
        $this->incidentService = new IncidentService();
    }

    public function dashboard(): void
    {
        require_any_role([ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS]);

        $houseId = current_house_id();
        if ($houseId === null || $houseId === '') {
            access_denied();
        }
        require_house_access($houseId);

        $stats = [
            'students' => $this->studentService->countByHouse($houseId),
            'rooms' => $this->roomService->countByHouse($houseId),
            'attendance' => $this->attendanceService->todayByHouse($houseId),
            'visitors' => $this->visitorService->todayByHouse($houseId),
            'incidents' => $this->incidentService->openByHouse($houseId),
        ];

        include __DIR__ . '/../../public/views/house-master/dashboard/index.php';
    }

    public function students(): void
    {
        require_any_role([ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS]);

        $houseId = current_house_id();
        if ($houseId === null || $houseId === '') {
            access_denied();
        }
        require_house_access($houseId);

        $students = $this->studentService->byHouse($houseId);

        include __DIR__ . '/../../public/views/house-master/students/index.php';
    }

    public function attendance(): void
    {
        require_any_role([ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS]);

        $houseId = current_house_id();
        if ($houseId === null || $houseId === '') {
            access_denied();
        }
        require_house_access($houseId);

        $attendance = $this->attendanceService->byHouse($houseId);

        include __DIR__ . '/../../public/views/house-master/attendance/index.php';
    }

    public function rooms(): void
    {
        require_any_role([ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS]);

        $houseId = current_house_id();
        if ($houseId === null || $houseId === '') {
            access_denied();
        }
        require_house_access($houseId);

        $rooms = $this->roomService->byHouse($houseId);

        include __DIR__ . '/../../public/views/house-master/rooms/index.php';
    }

    public function visitors(): void
    {
        require_any_role([ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS]);

        $houseId = current_house_id();
        if ($houseId === null || $houseId === '') {
            access_denied();
        }
        require_house_access($houseId);

        $visitors = $this->visitorService->byHouse($houseId);

        include __DIR__ . '/../../public/views/house-master/visitors/index.php';
    }

    public function incidents(): void
    {
        require_any_role([ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS]);

        $houseId = current_house_id();
        if ($houseId === null || $houseId === '') {
            access_denied();
        }
        require_house_access($houseId);

        $incidents = $this->incidentService->byHouse($houseId);

        include __DIR__ . '/../../public/views/house-master/incidents/index.php';
    }
}
