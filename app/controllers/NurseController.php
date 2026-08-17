<?php

namespace App\Controllers;

use App\Services\StudentService;
use App\Services\MedicalService;

class NurseController
{
    private StudentService $studentService;
    private MedicalService $medicalService;

    public function __construct()
    {
        $this->studentService = new StudentService();
        $this->medicalService = new MedicalService();
    }

    public function dashboard(): void
    {
        require_role(ROLE_NURSE);

        $stats = [
            'students' => $this->studentService->count(),
            'medicalRecords' => $this->medicalService->count(),
            'todayCases' => $this->medicalService->todayCases(),
            'emergencyCases' => $this->medicalService->emergencyCases(),
        ];

        include __DIR__ . '/../../public/views/nurse/dashboard/dashboard.php';
    }

    public function students(): void
    {
        require_role(ROLE_NURSE);

        $students = $this->studentService->all();

        include __DIR__ . '/../../public/views/nurse/students/students.php';
    }

    public function medicalRecords(): void
    {
        require_role(ROLE_NURSE);

        $records = $this->medicalService->all();

        include __DIR__ . '/../../public/views/nurse/medical-records/medical-records.php';
    }

    public function createRecord(): void
    {
        require_role(ROLE_NURSE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            include __DIR__ . '/../../public/views/nurse/create-record/create-record.php';
            return;
        }

        $data = [
            'studentId' => sanitize($_POST['studentId'] ?? ''),
            'diagnosis' => sanitize($_POST['diagnosis'] ?? ''),
            'treatment' => sanitize($_POST['treatment'] ?? ''),
            'notes' => sanitize($_POST['notes'] ?? ''),
            'severity' => sanitize($_POST['severity'] ?? 'normal'),
            'recordedBy' => current_user()['uid'] ?? null,
            'createdAt' => date('Y-m-d H:i:s'),
        ];

        $result = $this->medicalService->create($data);

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            base_url('index.php?route=/views/nurse/medical-records/medical-records.php')
        );
    }

    public function medicalIncidents(): void
    {
        require_role(ROLE_NURSE);

        $records = $this->medicalService->incidents();

        include __DIR__ . '/../../public/views/nurse/medical-incidents/medical-incidents.php';
    }

    public function emergencyCases(): void
    {
        require_role(ROLE_NURSE);

        $cases = $this->medicalService->emergencyCases();

        include __DIR__ . '/../../public/views/nurse/emergency-cases/emergency-cases.php';
    }
}
