<?php

namespace App\Controllers;

use App\Services\VisitorService;
use App\Services\IncidentService;

class SecurityController
{
    private VisitorService $visitorService;
    private IncidentService $incidentService;

    public function __construct()
    {
        $this->visitorService = new VisitorService();
        $this->incidentService = new IncidentService();
    }

    public function dashboard(): void
    {
        require_role(ROLE_SECURITY);

        $stats = [
            'todayVisitors' => $this->visitorService->todayCount(),
            'insideVisitors' => $this->visitorService->currentlyInside(),
            'pendingVisitors' => $this->visitorService->pendingCount(),
            'openIncidents' => $this->incidentService->openCount(),
        ];

        include __DIR__ . '/../../../public/views/security/dashboard/dashboard.php';
    }

    public function visitors(): void
    {
        require_role(ROLE_SECURITY);

        $visitors = $this->visitorService->all();

        include __DIR__ . '/../../../public/views/security/visitors/visitors/visitors.php';
    }

    public function registerVisitor(): void
    {
        require_role(ROLE_SECURITY);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            include __DIR__ . '/../../../public/views/security/register-visitor/register-visitor.php';
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

        $result = $this->visitorService->register($data);

        if (!$result['success']) {
            flash('error', $result['message']);
            redirect(base_url('index.php?route=/views/security/register-visitor/register-visitor.php'));
        }

        flash('success', 'Visitor registered successfully.');

        redirect(
            base_url('index.php?route=/views/security/visitors/visitors/visitors.php')
        );
    }

    public function checkIn(): void
    {
        require_role(ROLE_SECURITY);

        $visitorId = sanitize($_POST['visitorId'] ?? '');

        if (!$visitorId) {
            flash('error', 'Visitor ID is required.');
            redirect(base_url('index.php?route=/views/security/visitors/visitors/visitors.php'));
        }

        $result = $this->visitorService->checkIn(
            $visitorId,
            current_user()['uid'] ?? null
        );

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            base_url('index.php?route=/views/security/visitors/visitors/visitors.php')
        );
    }

    public function checkOut(): void
    {
        require_role(ROLE_SECURITY);

        $visitorId = sanitize($_POST['visitorId'] ?? '');

        if (!$visitorId) {
            flash('error', 'Visitor ID is required.');
            redirect(base_url('index.php?route=/views/security/visitors/visitors/visitors.php'));
        }

        $result = $this->visitorService->checkOut(
            $visitorId,
            current_user()['uid'] ?? null
        );

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            base_url('index.php?route=/views/security/visitors/visitors/visitors.php')
        );
    }

    public function incidents(): void
    {
        require_role(ROLE_SECURITY);

        $incidents = $this->incidentService->all();

        include __DIR__ . '/../../../public/views/security/incidents/incidents/incidents.php';
    }

    public function reportIncident(): void
    {
        require_role(ROLE_SECURITY);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            include __DIR__ . '/../../../public/views/security/report-incident/report-incident.php';
            return;
        }

        $data = [
            'title' => sanitize($_POST['title'] ?? ''),
            'description' => sanitize($_POST['description'] ?? ''),
            'studentId' => sanitize($_POST['studentId'] ?? ''),
            'priority' => sanitize($_POST['priority'] ?? 'medium'),
            'reportedBy' => current_user()['uid'] ?? null,
            'reportedAt' => date('Y-m-d H:i:s'),
        ];

        $result = $this->incidentService->create($data);

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            base_url('index.php?route=/views/security/incidents/incidents/incidents.php')
        );
    }
}
