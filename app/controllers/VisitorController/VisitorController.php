<?php

namespace App\Controllers;

use App\Services\VisitorService;

class VisitorController
{
    private VisitorService $visitorService;

    public function __construct()
    {
        $this->visitorService = new VisitorService();
    }

    public function index(): void
    {
        require_any_role([
            ROLE_ADMIN,
            ROLE_HOUSE_MASTER,
            ROLE_HOUSE_MISTRESS,
            ROLE_SENIOR_HOUSEPARENT,
            ROLE_SECURITY
        ]);

        $visitors = $this->visitorService->all();

        include __DIR__ . '/../../../public/views/visitors/index/index.php';
    }

    public function register(): void
    {
        require_any_role([ROLE_SECURITY, ROLE_SENIOR_HOUSEPARENT, ROLE_HOUSE_MISTRESS]);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            include __DIR__ . '/../../../public/views/visitors/register/register.php';
            return;
        }

        $data = [
            'visitorName' => sanitize($_POST['visitorName'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'studentId' => sanitize($_POST['studentId'] ?? ''),
            'purpose' => sanitize($_POST['purpose'] ?? ''),
            'idType' => sanitize($_POST['idType'] ?? ''),
            'idNumber' => sanitize($_POST['idNumber'] ?? ''),
            'registeredBy' => current_user()['uid'] ?? null,
        ];

        $errors = validate_required($data, ['visitorName', 'studentId']);

        if (!empty($errors)) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old'] = $data;
            flash('error', 'Please fix the highlighted fields.');
            redirect(
                base_url('index.php?route=/views/visitors/register/register.php')
            );
        }

        $result = $this->visitorService->register($data);

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            base_url('index.php?route=/views/visitors/index/index.php')
        );
    }

    public function request(): void
    {
        require_role(ROLE_STUDENT);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            include __DIR__ . '/../../../public/views/visitors/request/request.php';
            return;
        }

        $data = [
            'studentId' => current_user()['studentId'] ?? null,
            'visitorName' => sanitize($_POST['visitorName'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'relationship' => sanitize($_POST['relationship'] ?? ''),
            'visitDate' => sanitize($_POST['visitDate'] ?? ''),
            'purpose' => sanitize($_POST['purpose'] ?? ''),
            'status' => 'pending',
        ];

        $errors = validate_required($data, ['visitorName']);

        if (!empty($errors)) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old'] = $data;
            flash('error', 'Please fix the highlighted fields.');
            redirect(
                base_url('index.php?route=/views/visitors/request/request.php')
            );
        }

        $result = $this->visitorService->request($data);

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            base_url('index.php?route=/views/student/visitors/index/index.php')
        );
    }

    public function checkIn(): void
    {
        require_role(ROLE_SECURITY);

        $id = sanitize($_POST['id'] ?? '');

        $result = $this->visitorService->checkIn(
            $id,
            current_user()['uid'] ?? null
        );

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            base_url('index.php?route=/views/visitors/index/index.php')
        );
    }

    public function checkOut(): void
    {
        require_role(ROLE_SECURITY);

        $id = sanitize($_POST['id'] ?? '');

        $result = $this->visitorService->checkOut(
            $id,
            current_user()['uid'] ?? null
        );

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            base_url('index.php?route=/views/visitors/index/index.php')
        );
    }

    public function history(): void
    {
        require_any_role([
            ROLE_ADMIN,
            ROLE_HOUSE_MASTER,
            ROLE_HOUSE_MISTRESS,
            ROLE_SENIOR_HOUSEPARENT,
            ROLE_SECURITY
        ]);

        $visitors = $this->visitorService->history();

        include __DIR__ . '/../../../public/views/visitors/history/history.php';
    }
}