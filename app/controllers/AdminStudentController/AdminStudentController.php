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

        $limit = max(1, min(150, (int) ($_GET['limit'] ?? 150)));
        $students = $this->studentService->all(null, $limit);

        include __DIR__ . '/../../../public/views/admin/students/index/index.php';
    }

    public function create(): void
    {
        require_role(ROLE_ADMIN);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            include __DIR__ . '/../../../public/views/admin/students/create/create.php';
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
                base_url('index.php?route=/views/admin/students/create/create.php')
            );
        }

        $result = $this->studentService->create($data);
        $status = (bool) ($result['success'] ?? false);
        $message = $result['message'] ?? 'Operation failed.';

        flash(
            $status ? 'success' : 'error',
            $message
        );

        redirect(
            base_url('index.php?route=/views/admin/students/index/index.php')
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
                base_url('index.php?route=/views/admin/students/index/index.php')
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
                    base_url('index.php?route=/views/admin/students/edit/edit.php?id=' . urlencode($id))
                );
            }

            $this->studentService->update($id, $data);
            $status = true;
            $message = 'Student updated successfully.';

            flash(
                $status ? 'success' : 'error',
                $message
            );

            redirect(
                base_url('index.php?route=/views/admin/students/index/index.php')
            );
        }

        include __DIR__ . '/../../../public/views/admin/students/edit/edit.php';
    }

    public function view(): void
    {
        require_role(ROLE_ADMIN);

        $id = sanitize($_GET['id'] ?? '');

        $student = $this->studentService->find($id);

        if (!$student) {
            flash('error', 'Student not found.');
            redirect(
                base_url('index.php?route=/views/admin/students/index/index.php')
            );
        }

        include __DIR__ . '/../../../public/views/admin/students/view/view.php';
    }

    public function delete(): void
    {
        require_role(ROLE_ADMIN);

        $id = sanitize($_POST['id'] ?? '');

        $this->studentService->delete($id);

        flash('success', 'Student deleted successfully.');

        redirect(
            base_url('index.php?route=/views/admin/students/index/index.php')
        );
    }
}