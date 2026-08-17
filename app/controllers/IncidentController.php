<?php

namespace App\Controllers;

use App\Services\IncidentService;

class IncidentController
{
    private IncidentService $incidentService;

    public function __construct()
    {
        $this->incidentService = new IncidentService();
    }

    public function index(): void
    {
        require_role(
            ROLE_ADMIN,
            ROLE_HOUSE_MASTER,
            ROLE_HOUSE_MISTRESS,
            ROLE_HOUSEPARENT,
            ROLE_SECURITY,
            ROLE_NURSE
        );

        $incidents = $this->incidentService->all();

        include __DIR__ . '/../../public/views/incidents/index.php';
    }

    public function create(): void
    {
        require_role(
            ROLE_ADMIN,
            ROLE_HOUSE_MASTER,
            ROLE_HOUSE_MISTRESS,
            ROLE_HOUSEPARENT,
            ROLE_SECURITY,
            ROLE_NURSE,
            ROLE_STUDENT
        );

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            include __DIR__ . '/../../public/views/incidents/create.php';
            return;
        }

        $data = [
            'title' => sanitize($_POST['title'] ?? ''),
            'description' => sanitize($_POST['description'] ?? ''),
            'studentId' => sanitize($_POST['studentId'] ?? ''),
            'priority' => sanitize($_POST['priority'] ?? 'medium'),
            'status' => 'open',
            'reportedBy' => current_user()['uid'] ?? null,
            'createdAt' => date('Y-m-d H:i:s'),
        ];

        $result = $this->incidentService->create($data);

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            base_url('index.php?route=/views/incidents/index.php')
        );
    }

    public function view(): void
    {
        require_role(
            ROLE_ADMIN,
            ROLE_HOUSE_MASTER,
            ROLE_HOUSE_MISTRESS,
            ROLE_HOUSEPARENT,
            ROLE_SECURITY,
            ROLE_NURSE
        );

        $id = sanitize($_GET['id'] ?? '');

        $incident = $this->incidentService->find($id);

        include __DIR__ . '/../../public/views/incidents/view.php';
    }

    public function history(): void
    {
        require_role(
            ROLE_ADMIN,
            ROLE_HOUSE_MASTER,
            ROLE_HOUSE_MISTRESS,
            ROLE_HOUSEPARENT,
            ROLE_SECURITY,
            ROLE_NURSE
        );

        $incidents = $this->incidentService->history();

        include __DIR__ . '/../../public/views/incidents/history.php';
    }
}