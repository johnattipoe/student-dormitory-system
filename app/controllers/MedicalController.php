<?php

namespace App\Controllers;

use App\Services\MedicalService;
use App\Services\StudentService;

class MedicalController
{
    private MedicalService $medicalService;
    private StudentService $studentService;

    public function __construct()
    {
        $this->medicalService = new MedicalService();
        $this->studentService = new StudentService();
    }

    public function index(): void
    {
        require_role(ROLE_ADMIN, ROLE_NURSE);

        $records = $this->medicalService->all();

        include __DIR__ . '/../../public/views/medical/index.php';
    }

    public function create(): void
    {
        require_role(ROLE_NURSE);

        $students = $this->studentService->all();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            include __DIR__ . '/../../public/views/medical/create.php';
            return;
        }

        $data = [
            'studentId' => sanitize($_POST['studentId'] ?? ''),
            'diagnosis' => sanitize($_POST['diagnosis'] ?? ''),
            'treatment' => sanitize($_POST['treatment'] ?? ''),
            'notes' => sanitize($_POST['notes'] ?? ''),
            'severity' => sanitize($_POST['severity'] ?? 'normal'),
            'recordedBy' => current_user()['uid'] ?? null,
        ];

        $result = $this->medicalService->create($data);

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            base_url('index.php?route=/views/medical/index.php')
        );
    }

    public function edit(): void
    {
        require_role(ROLE_NURSE);

        $id = sanitize($_GET['id'] ?? '');

        $record = $this->medicalService->find($id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $data = [
                'diagnosis' => sanitize($_POST['diagnosis'] ?? ''),
                'treatment' => sanitize($_POST['treatment'] ?? ''),
                'notes' => sanitize($_POST['notes'] ?? ''),
                'severity' => sanitize($_POST['severity'] ?? 'normal'),
            ];

            $result = $this->medicalService->update($id, $data);

            flash(
                $result['success'] ? 'success' : 'error',
                $result['message']
            );

            redirect(
                base_url('index.php?route=/views/medical/index.php')
            );
        }

        include __DIR__ . '/../../public/views/medical/edit.php';
    }

    public function view(): void
    {
        require_role(ROLE_ADMIN, ROLE_NURSE);

        $id = sanitize($_GET['id'] ?? '');

        $record = $this->medicalService->find($id);

        include __DIR__ . '/../../public/views/medical/view.php';
    }

    public function reports(): void
    {
        require_role(ROLE_ADMIN, ROLE_NURSE);

        $reports = $this->medicalService->reports();

        include __DIR__ . '/../../public/views/medical/reports.php';
    }
}