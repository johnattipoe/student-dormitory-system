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

use App\Services\UserService;
use App\Services\VisitorService;
use App\Services\IncidentService;

$user = current_user() ?? [];
$userId = current_user_id();
$userService = new UserService();
$profile = $userId ? $userService->find($userId) : null;
if (is_array($profile)) {
    $user = array_merge($user, $profile);
}

$visitors = (new VisitorService())->all();
$totalVisitors = count($visitors);
$activeVisitors = count(array_filter($visitors, fn($v) => ($v['status'] ?? '') === 'checked_in' || ($v['status'] ?? '') === 'inside'));

$incidents = (new IncidentService())->all();
$totalIncidents = count($incidents);

$fullName = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')))) ?: 'Security Officer';
$initials = strtoupper(substr($fullName, 0, 1) . (str_contains($fullName, ' ') ? substr(explode(' ', $fullName)[1], 0, 1) : 'S'));

$pageTitle = 'Security Profile';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/security/profile/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center fw-bold fs-2 shadow-sm" style="width: 72px; height: 72px;">
                    <?= e($initials) ?>
                </div>
                <div>
                    <h4 class="mb-1 fw-bold text-dark"><?= e($fullName) ?></h4>
                    <p class="text-muted mb-0">
                        <i class="bi bi-shield-check me-1 text-primary"></i>Campus Security Officer &bull; Main Gate Control Station
                    </p>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-primary btn-sm" href="<?= url('views/security/profile/edit/edit.php') ?>">
                    <i class="bi bi-pencil me-1"></i>Edit Profile
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/security/profile/security/security.php') ?>">
                    <i class="bi bi-shield-lock me-1"></i>Security
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/security/settings/index.php') ?>">
                    <i class="bi bi-gear me-1"></i>Gate Settings
                </a>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Visitors Processed</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $totalVisitors) ?></h3>
                            <span class="small text-muted">Gate log entries</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-people fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Active On Campus</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $activeVisitors) ?></h3>
                            <span class="small text-muted">Currently checked-in</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-person-check fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Security Incidents</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $totalIncidents) ?></h3>
                            <span class="small text-muted">Recorded breaches & reports</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-shield-exclamation fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Details -->
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card stat-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Officer Details & Station</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Full Name</span>
                                <strong><?= e($fullName) ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Email Address</span>
                                <strong><?= e($user['email'] ?? '—') ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Primary Contact</span>
                                <strong><?= e($user['phone'] ?? 'Not provided') ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Guard Post Station</span>
                                <strong><?= e($user['officeRoom'] ?? 'Main Gate Security Booth') ?></strong>
                            </div>
                            <div class="col-12">
                                <span class="text-muted small d-block">Duty Bio / Notes</span>
                                <p class="text-muted small mb-0"><?= e($user['bio'] ?? 'Dedicated to perimeter defense, visitor verification, and campus curfew enforcement.') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card stat-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-gear me-2 text-primary"></i>Quick Shortcuts</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <a href="<?= url('views/security/profile/edit/edit.php') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                                <div><i class="bi bi-pencil-square me-2 text-primary"></i> <strong>Edit Contact Info</strong></div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>
                            <a href="<?= url('views/security/profile/security/security.php') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                                <div><i class="bi bi-shield-lock me-2 text-success"></i> <strong>Password & Security</strong></div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>
                            <a href="<?= url('views/security/emergency-alerts/index.php') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                                <div><i class="bi bi-shield-exclamation me-2 text-danger"></i> <strong>Emergency & Lockdown Protocols</strong></div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
