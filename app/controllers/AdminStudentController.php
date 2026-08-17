<?php

namespace App\Controllers;

use App\Services\StudentService;

class AdminStudentController
{
    private StudentService $studentService;

    public function __construct()
    {
        $this->studentService = new StudentService();
    }

    public function index(): void
    {
        require_role(ROLE_ADMIN);

        $students = $this->studentService->all();

        include __DIR__ . '/../../public/views/admin/students/index.php';
    }

    public function create(): void
    {
        require_role(ROLE_ADMIN);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            include __DIR__ . '/../../public/views/admin/students/create.php';
            return;
        }

        $data = [
            'studentId' => sanitize($_POST['studentId'] ?? ''),
            'firstName' => sanitize($_POST['firstName'] ?? ''),
            'lastName' => sanitize($_POST['lastName'] ?? ''),
            'gender' => sanitize($_POST['gender'] ?? ''),
            'dateOfBirth' => sanitize($_POST['dateOfBirth'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'houseId' => sanitize($_POST['houseId'] ?? ''),
            'status' => 'active',
        ];

        $errors = validate_required($data, ['firstName', 'lastName', 'email']);
        if (!empty($data['email']) && !validate_email($data['email'])) {
            $errors['email'] = 'Email is invalid.';
        }

        if (!empty($errors)) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old'] = $data;
            flash('error', 'Please fix the highlighted fields.');
            redirect(
                base_url('index.php?route=/views/admin/students/create.php')
            );
        }

        $result = $this->studentService->create($data);

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            base_url('index.php?route=/views/admin/students/index.php')
        );
    }

    public function edit(): void
    {
        require_role(ROLE_ADMIN);

        $id = sanitize($_GET['id'] ?? '');

        $student = $this->studentService->find($id);

        if (!$student) {
            flash('error', 'Student not found.');
            redirect(
                base_url('index.php?route=/views/admin/students/index.php')
            );
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $data = [
                'firstName' => sanitize($_POST['firstName'] ?? ''),
                'lastName' => sanitize($_POST['lastName'] ?? ''),
                'gender' => sanitize($_POST['gender'] ?? ''),
                'dateOfBirth' => sanitize($_POST['dateOfBirth'] ?? ''),
                'email' => sanitize($_POST['email'] ?? ''),
                'phone' => sanitize($_POST['phone'] ?? ''),
                'houseId' => sanitize($_POST['houseId'] ?? ''),
                'status' => sanitize($_POST['status'] ?? 'active'),
            ];

            $errors = validate_required($data, ['firstName', 'lastName', 'email']);
            if (!empty($data['email']) && !validate_email($data['email'])) {
                $errors['email'] = 'Email is invalid.';
            }

            if (!empty($errors)) {
                $_SESSION['_errors'] = $errors;
                $_SESSION['_old'] = $data;
                flash('error', 'Please fix the highlighted fields.');
                redirect(
                    base_url('index.php?route=/views/admin/students/edit.php?id=' . urlencode($id))
                );
            }

            $result = $this->studentService->update($id, $data);

            flash(
                $result['success'] ? 'success' : 'error',
                $result['message']
            );

            redirect(
                base_url('index.php?route=/views/admin/students/index.php')
            );
        }

        include __DIR__ . '/../../public/views/admin/students/edit.php';
    }

    public function view(): void
    {
        require_role(ROLE_ADMIN);

        $id = sanitize($_GET['id'] ?? '');

        $student = $this->studentService->find($id);

        if (!$student) {
            flash('error', 'Student not found.');
            redirect(
                base_url('index.php?route=/views/admin/students/index.php')
            );
        }

        include __DIR__ . '/../../public/views/admin/students/view.php';
    }

    public function delete(): void
    {
        require_role(ROLE_ADMIN);

        $id = sanitize($_POST['id'] ?? '');

        $result = $this->studentService->delete($id);

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            base_url('index.php?route=/views/admin/students/index.php')
        );
    }
}