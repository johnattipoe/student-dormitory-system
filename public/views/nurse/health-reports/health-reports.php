<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\MedicalService;

$report = (new MedicalService())->reports();

$pageTitle = 'Health Reports';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Students', 'href' => url('views/nurse/students/students.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => url('views/nurse/emergency-cases/emergency-cases.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/nurse/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4">
            <h5 class="mb-3">Health Reports</h5>
            <div class="row g-3">
                <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Total Records</div><h4 class="mb-0"><?= e((string) ($report['total'] ?? 0)) ?></h4></div></div>
                <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Normal</div><h4 class="mb-0"><?= e((string) ($report['normal'] ?? 0)) ?></h4></div></div>
                <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Emergency</div><h4 class="mb-0"><?= e((string) ($report['emergency'] ?? 0)) ?></h4></div></div>
                <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Critical</div><h4 class="mb-0"><?= e((string) ($report['critical'] ?? 0)) ?></h4></div></div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
