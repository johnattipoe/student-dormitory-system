<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\VisitorService;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitorId = sanitize($_POST['visitorId'] ?? '');
    if ($visitorId === '') {
        flash('error', 'Visitor ID is required.');
        redirect(base_url('index.php?route=/views/security/visitor-check-in/visitor-check-in.php'));
    }

    $result = (new VisitorService())->checkIn($visitorId, current_user()['uid'] ?? current_user()['id'] ?? null);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(base_url('index.php?route=/views/security/visitors/visitors.php'));
}

$pageTitle = 'Visitor Check-In';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors.php')],
    ['icon' => 'bi-journal-text', 'label' => 'Visitor History', 'href' => url('views/security/visitor-history/visitor-history.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/security/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4">
            <h5 class="mb-3">Check In Visitor</h5>
            <form method="POST" action="<?= url('views/security/visitor-check-in/visitor-check-in.php') ?>">
                <div class="mb-3">
                    <label class="form-label">Visitor ID</label>
                    <input type="text" name="visitorId" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Check In</button>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
