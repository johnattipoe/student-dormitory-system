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
$allowedRoles = [ROLE_ADMIN, ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$pageTitle = 'Medical Reports';
$records = FirebaseService::getInstance()->getCollection('medical_records', [], 200);
$normal = count(array_filter($records, fn($r) => strtolower((string) ($r['severity'] ?? 'normal')) === 'normal'));
$moderate = count(array_filter($records, fn($r) => strtolower((string) ($r['severity'] ?? '')) === 'moderate'));
$severe = count(array_filter($records, fn($r) => strtolower((string) ($r['severity'] ?? '')) === 'severe'));
$emergency = count(array_filter($records, fn($r) => strtolower((string) ($r['severity'] ?? '')) === 'emergency'));

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-bar-chart', 'label' => 'Medical Reports', 'href' => url('views/medical/reports/reports.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <h5 class="mb-3">Medical Report Summary</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Normal</div><div class="fs-3 fw-bold"><?= e((string) $normal) ?></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Moderate</div><div class="fs-3 fw-bold"><?= e((string) $moderate) ?></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Severe</div><div class="fs-3 fw-bold"><?= e((string) $severe) ?></div></div></div>
            <div class="col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Emergency</div><div class="fs-3 fw-bold"><?= e((string) $emergency) ?></div></div></div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
