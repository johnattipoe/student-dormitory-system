<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\NotificationService;

$pageTitle = 'Security Notifications';
$notifications = (new NotificationService())->all();
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors.php')],
    ['icon' => 'bi-journal-text', 'label' => 'Visitor History', 'href' => url('views/security/visitor-history/visitor-history.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/security/notifications/notifications.php'), 'active' => true],
    ['icon' => 'bi-person-plus', 'label' => 'Register Visitor', 'href' => url('views/security/register-visitor/register-visitor.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-3">
            <h5 class="mb-3">Notifications</h5>
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Read</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $note): ?>
                            <tr>
                                <td><?= e($note['title'] ?? '') ?></td>
                                <td><?= e($note['type'] ?? '') ?></td>
                                <td><?= !empty($note['read']) ? 'Yes' : 'No' ?></td>
                                <td><?= e($note['createdAt'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">No notifications available.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
