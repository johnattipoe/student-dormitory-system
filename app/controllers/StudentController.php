<?php

namespace App\Controllers;

use App\Services\StudentService;
use App\Services\RoomService;
use App\Services\AttendanceService;
use App\Services\VisitorService;
use App\Services\IncidentService;

class StudentController
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

    private function studentId(): ?string
    {
        return current_user()['studentId'] ?? null;
    }

    public function dashboard(): void
    {
        require_role(ROLE_STUDENT);

        $studentId = $this->studentId();

        $student = $this->studentService->find($studentId);

        $stats = [
            'attendance' => $this->attendanceService->studentSummary($studentId),
            'visitors' => $this->visitorService->studentVisitors($studentId),
            'incidents' => $this->incidentService->studentIncidents($studentId),
        ];

        include __DIR__ . '/../../public/views/student/dashboard.php';
    }

    public function profile(): void
    {
        require_role(ROLE_STUDENT);

        $student = $this->studentService->find(
            $this->studentId()
        );

        include __DIR__ . '/../../public/views/student/profile.php';
    }

    public function editProfile(): void
    {
        require_role(ROLE_STUDENT);

        $studentId = $this->studentId();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $student = $this->studentService->find($studentId);

            include __DIR__ . '/../../public/views/student/edit-profile.php';
            return;
        }

        $data = [
            'phone' => sanitize($_POST['phone'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'emergencyContact' => sanitize(
                $_POST['emergencyContact'] ?? ''
            ),
        ];

        $result = $this->studentService->update(
            $studentId,
            $data
        );

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            base_url('index.php?route=/views/student/profile.php')
        );
    }

    public function room(): void
    {
        require_role(ROLE_STUDENT);

        $room = $this->roomService->studentRoom(
            $this->studentId()
        );

        include __DIR__ . '/../../public/views/student/room.php';
    }

    public function attendance(): void
    {
        require_role(ROLE_STUDENT);

        $attendance = $this->attendanceService->studentAttendance(
            $this->studentId()
        );

        include __DIR__ . '/../../public/views/student/attendance.php';
    }

    public function attendanceHistory(): void
    {
        require_role(ROLE_STUDENT);

        $attendance = $this->attendanceService->history(
            $this->studentId()
        );

        include __DIR__ . '/../../public/views/student/attendance-history.php';
    }

    public function visitorRequest(): void
    {
        require_role(ROLE_STUDENT);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            include __DIR__ . '/../../public/views/student/visitor-request.php';
            return;
        }

        $data = [
            'studentId' => $this->studentId(),
            'visitorName' => sanitize($_POST['visitorName'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'relationship' => sanitize($_POST['relationship'] ?? ''),
            'visitDate' => sanitize($_POST['visitDate'] ?? ''),
            'purpose' => sanitize($_POST['purpose'] ?? ''),
            'status' => 'pending',
        ];

        $result = $this->visitorService->request($data);

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            base_url('index.php?route=/views/student/visitors.php')
        );
    }

    public function visitors(): void
    {
        require_role(ROLE_STUDENT);

        $visitors = $this->visitorService->studentVisitors(
            $this->studentId()
        );

        include __DIR__ . '/../../public/views/student/visitors.php';
    }

    public function incidents(): void
    {
        require_role(ROLE_STUDENT);

        $incidents = $this->incidentService->studentIncidents(
            $this->studentId()
        );

        include __DIR__ . '/../../public/views/student/incidents.php';
    }
}