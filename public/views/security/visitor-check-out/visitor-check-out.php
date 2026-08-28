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

$insideVisitors = array_values(array_filter((new VisitorService())->all(), static fn(array $visitor): bool => ($visitor['status'] ?? '') === 'inside'));
$longestVisit = 0;
$longestVisitor = null;
foreach ($insideVisitors as $visitor) {
    $checkIn = strtotime((string) ($visitor['checkInTime'] ?? ''));
    $hoursInside = $checkIn ? max(0, round((time() - $checkIn) / 3600, 1)) : 0;
    if ($hoursInside > $longestVisit) {
        $longestVisit = $hoursInside;
        $longestVisitor = $visitor;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitorId = sanitize($_POST['visitorId'] ?? '');
    if ($visitorId === '') {
        flash('error', 'Visitor ID is required.');
        redirect(base_url('index.php?route=/views/security/visitor-check-out/visitor-check-out.php'));
    }

    $visitor = FirebaseService::getInstance()->getDocument(COL_VISITORS, $visitorId);
    if (!$visitor || ($visitor['status'] ?? '') !== 'inside') {
        flash('error', 'Visitor was not found or is not currently inside.');
        redirect(base_url('index.php?route=/views/security/visitor-check-out/visitor-check-out.php'));
    }

    $result = (new VisitorService())->checkOut($visitorId, current_user()['uid'] ?? current_user()['id'] ?? null);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(base_url('index.php?route=/views/security/visitors/visitors/visitors.php'));
}

$pageTitle = 'Visitor Check-Out';
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
        <section class="security-hero mb-4"><div class="security-hero-icon"><i class="bi bi-box-arrow-right"></i></div><div><span class="security-kicker">Gate movement</span><h1>Check out visitor</h1><p>Close an active visit and keep the on-premises board accurate.</p></div><span class="badge bg-success"><?= e((string) count($insideVisitors)) ?> inside</span></section>
        <div class="row g-4">
            <div class="col-xl-7">
                <section class="security-card h-100">
                    <div class="security-card-header"><div><span class="security-kicker">Departure action</span><h2>Confirm visitor exit</h2><p>Review the active visit before closing the gate record.</p></div><i class="bi bi-box-arrow-right security-profile-icon"></i></div>
                    <form method="POST" action="<?= url('views/security/visitor-check-out/visitor-check-out.php') ?>" id="checkout-form">
                <div class="mb-3">
                    <label class="form-label" for="visitor-id">Visitor currently inside</label>
                    <select name="visitorId" id="visitor-id" class="form-select" required><option value="">Select visitor inside</option><?php foreach ($insideVisitors as $visitor): ?><?php $checkIn = strtotime((string) ($visitor['checkInTime'] ?? '')); $hoursInside = $checkIn ? max(0, round((time() - $checkIn) / 3600, 1)) : 0; ?><option value="<?= e((string) ($visitor['id'] ?? '')) ?>" data-name="<?= e($visitor['visitorName'] ?? 'Visitor') ?>" data-host="<?= e($visitor['studentId'] ?? '—') ?>" data-phone="<?= e($visitor['phone'] ?? 'Not provided') ?>" data-checkin="<?= e($visitor['checkInTime'] ?? 'Not recorded') ?>" data-duration="<?= e((string) $hoursInside) ?>"><?= e($visitor['visitorName'] ?? 'Visitor') ?>, host <?= e($visitor['studentId'] ?? '—') ?> (<?= e((string) $hoursInside) ?> hrs)</option><?php endforeach; ?></select>
                </div>
                <div id="checkout-preview" class="security-selection-preview" hidden><div><small>Visitor</small><strong data-preview="name">—</strong></div><div><small>Host student</small><strong data-preview="host">—</strong></div><div><small>Phone</small><strong data-preview="phone">—</strong></div><div><small>Inside for</small><strong data-preview="duration">—</strong></div><div class="security-preview-wide"><small>Checked in</small><strong data-preview="checkin">—</strong></div></div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="confirm-exit" required><label class="form-check-label" for="confirm-exit">I have verified that this visitor is leaving the residence.</label></div>
                <button type="submit" class="btn btn-dark"><i class="bi bi-box-arrow-right me-1"></i>Confirm check-out</button>
                    </form>
                </section>
            </div>
            <div class="col-xl-5">
                <aside class="security-side-card h-100"><span class="security-kicker">On-premises queue</span><h2>Visitors inside</h2><div class="security-queue-list"><?php foreach ($insideVisitors as $visitor): ?><?php $checkIn = strtotime((string) ($visitor['checkInTime'] ?? '')); $hoursInside = $checkIn ? max(0, round((time() - $checkIn) / 3600, 1)) : 0; ?><div><span><i class="bi bi-person-check"></i><?= e($visitor['visitorName'] ?? 'Visitor') ?></span><strong><?= e((string) $hoursInside) ?> hrs</strong><small>Host: <?= e($visitor['studentId'] ?? '—') ?></small></div><?php endforeach; if (!$insideVisitors): ?><div><i class="bi bi-shield-check"></i><span>No visitors currently inside.</span></div><?php endif; ?></div></aside>
            </div>
        </div>
        <div class="row g-3 mt-1"><div class="col-md-4"><div class="security-stat"><span class="security-stat-icon green"><i class="bi bi-person-check"></i></span><div><small>Inside now</small><strong><?= e((string) count($insideVisitors)) ?></strong></div></div></div><div class="col-md-4"><div class="security-stat"><span class="security-stat-icon orange"><i class="bi bi-clock-history"></i></span><div><small>Longest active visit</small><strong><?= e($longestVisitor ? $longestVisit . ' hrs' : '—') ?></strong></div></div></div><div class="col-md-4"><div class="security-stat"><span class="security-stat-icon blue"><i class="bi bi-arrow-left-right"></i></span><div><small>Action</small><strong>Exit logging</strong></div></div></div></div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const select = document.getElementById('visitor-id');
                const preview = document.getElementById('checkout-preview');
                function updatePreview() {
                    const option = select.options[select.selectedIndex];
                    const hasVisitor = select.value !== '';
                    preview.hidden = !hasVisitor;
                    if (!hasVisitor) return;
                    preview.querySelector('[data-preview="name"]').textContent = option.dataset.name || '—';
                    preview.querySelector('[data-preview="host"]').textContent = option.dataset.host || '—';
                    preview.querySelector('[data-preview="phone"]').textContent = option.dataset.phone || '—';
                    preview.querySelector('[data-preview="duration"]').textContent = (option.dataset.duration || '0') + ' hrs';
                    preview.querySelector('[data-preview="checkin"]').textContent = option.dataset.checkin || '—';
                }
                select.addEventListener('change', updatePreview);
            });
        </script>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
