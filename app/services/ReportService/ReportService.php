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
                'total' => count($visitorService->all()),
                'today' => $visitorService->todayCount(),
                'inside' => $visitorService->currentlyInside()
            ],

            'incidents' => [
                'total' => count($incidentService->all()),
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

        $students = $service->all();

        $result = [
            'total' => count($students),
            'active' => 0,
            'inactive' => 0,
            'male' => 0,
            'female' => 0
        ];

        foreach ($students as $student) {

            if (($student['status'] ?? '') === 'active') {
                $result['active']++;
            } else {
                $result['inactive']++;
            }

            $gender = strtolower(
                $student['gender'] ?? ''
            );

            if ($gender === 'male') {
                $result['male']++;
            }

            if ($gender === 'female') {
                $result['female']++;
            }
        }

        return $result;
    }

    public function visitors(): array
    {
        $service = new VisitorService();

        $visitors = $service->all();

        return [
            'total' => count($visitors),
            'today' => $service->todayCount(),
            'inside' => $service->currentlyInside(),
            'pending' => $service->pendingCount()
        ];
    }

    public function incidents(): array
    {
        $service = new IncidentService();

        $incidents = $service->all();

        $result = [
            'total' => count($incidents),
            'open' => 0,
            'resolved' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0
        ];

        foreach ($incidents as $incident) {

            $status = strtolower(
                $incident['status'] ?? ''
            );

            if ($status === 'open') {
                $result['open']++;
            }

            if ($status === 'resolved') {
                $result['resolved']++;
            }

            $priority = strtolower(
                $incident['priority'] ?? ''
            );

            if (isset($result[$priority])) {
                $result[$priority]++;
            }
        }

        return $result;
    }

    public function medical(): array
    {
        $service = new MedicalService();

        return $service->reports();
    }
}