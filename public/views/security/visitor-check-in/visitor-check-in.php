<?php
// Ensure bootstrap is loaded (safe at any view nesting depth)
if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/bootstrap.php')) {
            require $dir . '/bootstrap.php';
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
}
$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\VisitorService;
use App\Services\FirebaseService;

$eligibleVisitors = array_values(array_filter((new VisitorService())->all(), static fn(array $visitor): bool => in_array(($visitor['status'] ?? ''), ['registered', 'approved'], true)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitorId = sanitize($_POST['visitorId'] ?? '');
    if ($visitorId === '') {
        flash('error', 'Visitor ID is required.');
        redirect(base_url('index.php?route=/views/security/visitor-check-in/visitor-check-in.php'));
    }

    $visitor = FirebaseService::getInstance()->getDocument(COL_VISITORS, $visitorId);
    if (!$visitor || in_array(($visitor['status'] ?? ''), ['inside', 'checked_out'], true)) {
        flash('error', 'Visitor was not found or is not eligible for check-in.');
        redirect(base_url('index.php?route=/views/security/visitor-check-in/visitor-check-in.php'));
    }

    $result = (new VisitorService())->checkIn($visitorId, current_user()['uid'] ?? current_user()['id'] ?? null);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(base_url('index.php?route=/views/security/visitors/visitors/visitors.php'));
}

$pageTitle = 'Visitor Check-In';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors/visitors.php')],
    ['icon' => 'bi-journal-text', 'label' => 'Visitor History', 'href' => url('views/security/visitor-history/visitor-history.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents/incidents.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/security/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper security-portal">
        <section class="security-hero mb-4"><div class="security-hero-icon"><i class="bi bi-box-arrow-in-right"></i></div><div><span class="security-kicker">Gate movement</span><h1>Check in visitor</h1><p>Confirm an approved visitor’s arrival and start their on-premises visit.</p></div><span class="badge bg-success"><?= e((string) count($eligibleVisitors)) ?> eligible</span></section>
        <div class="security-card">
            <h5 class="mb-3">Check In Visitor</h5>
            <form method="POST" action="<?= url('views/security/visitor-check-in/visitor-check-in.php') ?>">
                <div class="mb-3">
                    <label class="form-label">Visitor ID</label>
                    <select name="visitorId" class="form-select" required><option value="">Select approved visitor</option><?php foreach ($eligibleVisitors as $visitor): ?><option value="<?= e((string) ($visitor['id'] ?? '')) ?>"><?= e($visitor['visitorName'] ?? 'Visitor') ?>, host <?= e($visitor['studentId'] ?? '—') ?></option><?php endforeach; ?></select>
                </div>
                <button type="submit" class="btn btn-success"><i class="bi bi-box-arrow-in-right me-1"></i>Confirm check-in</button>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
