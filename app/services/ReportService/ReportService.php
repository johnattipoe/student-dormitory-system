<?php

namespace App\Services;

class ReportService
{
    private FirebaseService $firebase;

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    public function dashboard(): array
    {
        $studentService = new StudentService();
        $roomService = new RoomService();
        $attendanceService = new AttendanceService();
        $visitorService = new VisitorService();
        $incidentService = new IncidentService();
        $medicalService = new MedicalService();

        return [
            'students' => $studentService->count(),

            'rooms' => $roomService->count(),

            'occupancy' => $roomService->occupancy(),

            'attendance' => $attendanceService->report(),

            'visitors' => [
                'total' => $visitorService->count(),
                'today' => $visitorService->todayCount(),
                'inside' => $visitorService->currentlyInside()
            ],

            'incidents' => [
                'total' => $incidentService->count(),
                'open' => $incidentService->openCount()
            ],

            'medical' => $medicalService->reports()
        ];
    }

    public function attendance(): array
    {
        $service = new AttendanceService();

        return $service->report();
    }

    public function occupancy(): array
    {
        $service = new RoomService();

        return $service->occupancy();
    }

    public function students(): array
    {
        $service = new StudentService();

        return $service->stats();
    }

    public function visitors(): array
    {
        $service = new VisitorService();

        return [
            'total' => $service->count(),
            'today' => $service->todayCount(),
            'inside' => $service->currentlyInside(),
            'pending' => $service->pendingCount()
        ];
    }

    public function incidents(): array
    {
        $service = new IncidentService();

        return $service->countSummary();
    }

    public function medical(): array
    {
        $service = new MedicalService();

        return $service->reports();
    }
}