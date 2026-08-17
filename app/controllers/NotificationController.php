<?php

namespace App\Controllers;

use App\Services\NotificationService;

class NotificationController
{
    private NotificationService $notificationService;

    public function __construct()
    {
        $this->notificationService = new NotificationService();
    }

    public function index(): void
    {
        require_login();

        $user = current_user();

        $notifications = $this->notificationService->forUser(
            $user['uid'] ?? null
        );

        include __DIR__ . '/../../public/views/dashboard/dashboard.php';
    }

    public function markAsRead(): void
    {
        require_login();

        $id = sanitize($_POST['id'] ?? '');

        $result = $this->notificationService->markAsRead(
            $id,
            current_user()['uid'] ?? null
        );

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            $_SERVER['HTTP_REFERER'] ?? base_url('index.php')
        );
    }

    public function markAllAsRead(): void
    {
        require_login();

        $result = $this->notificationService->markAllAsRead(
            current_user()['uid'] ?? null
        );

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            $_SERVER['HTTP_REFERER'] ?? base_url('index.php')
        );
    }
}