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

use App\Services\IncidentService;
use App\Services\StudentService;

$incidents = (new IncidentService())->all();
$students = [];
foreach (StudentService::all() as $st) {
    $stId = (string) ($st['id'] ?? '');
    if ($stId !== '') {
        $stName = trim(($st['firstName'] ?? '') . ' ' . ($st['lastName'] ?? ''));
        $students[$stId] = $stName !== '' ? $stName : ($st['admissionNo'] ?? $stId);
    }
}

$search = strtolower(sanitize($_GET['search'] ?? ''));
if ($search !== '') {
    $incidents = array_values(array_filter($incidents, function ($incident) use ($search, $students) {
        $stId = (string) ($incident['studentId'] ?? '');
        $stName = strtolower($students[$stId] ?? '');
        return str_contains(strtolower((string) ($incident['title'] ?? '')), $search)
            || str_contains(strtolower((string) ($incident['studentId'] ?? '')), $search)
            || str_contains($stName, $search);
    }));
}

$openCount = count(array_filter($incidents, fn($i) => ($i['status'] ?? 'open') === 'open'));
$resolvedCount = count(array_filter($incidents, fn($i) => ($i['status'] ?? '') === 'resolved'));
$highCount = count(array_filter($incidents, fn($i) => in_array(strtolower((string) ($i['priority'] ?? $i['severity'] ?? 'low')), ['high', 'critical', 'emergency'], true)));

$pageTitle = 'Security Incidents';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors/visitors.php')],
    ['icon' => 'bi-journal-text', 'label' => 'Visitor History', 'href' => url('views/security/visitor-history/visitor-history.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents/incidents.php'), 'active' => true],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/security/notifications/notifications.php')],
    ['icon' => 'bi-person-plus', 'label' => 'Register Visitor', 'href' => url('views/security/register-visitor/register-visitor.php')],
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
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-shield-exclamation text-danger me-2"></i>Security Incident Log</h4>
                <p class="text-muted mb-0">Record, investigate, and resolve safety and security infractions</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/security/report-incident/report-incident.php') ?>" class="btn btn-danger btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>Report Incident
                </a>
            </div>
        </div>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Incidents</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) count($incidents)) ?></h3>
                            <span class="small text-muted">All logged events</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-journal-text fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Open / Investigating</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $openCount) ?></h3>
                            <span class="small text-muted">Active investigations</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-hourglass-split fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">High Priority</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $highCount) ?></h3>
                            <span class="small text-muted">Critical security matters</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-exclamation-triangle fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-10">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input name="search" class="form-control form-control-sm" placeholder="Search title, student name, ID..." value="<?= e($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-fill" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a href="<?= url('views/security/incidents/incidents/incidents.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Incidents Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-shield-exclamation me-2"></i>Security Incidents</h6>
                <small class="text-muted">Showing <strong><?= count($incidents) ?></strong> record(s)</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 data-table w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Student</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($incidents)): ?>
                            <?php foreach ($incidents as $incident): ?>
                                <?php
                                $stId = (string) ($incident['studentId'] ?? '');
                                $stDisplay = $students[$stId] ?? ($stId !== '' ? $stId : '—');
                                $priority = strtolower((string) ($incident['priority'] ?? $incident['severity'] ?? 'medium'));
                                $status = strtolower((string) ($incident['status'] ?? 'open'));
                                ?>
                                <tr>
                                    <td class="fw-medium"><?= e($incident['title'] ?? 'Incident') ?></td>
                                    <td><?= e($stDisplay) ?></td>
                                    <td>
                                        <span class="badge bg-<?= match($priority) {
                                            'high', 'critical', 'emergency' => 'danger',
                                            'medium' => 'warning text-dark',
                                            'low' => 'info',
                                            default => 'secondary'
                                        } ?>">
                                            <?= e(ucfirst($priority)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= match($status) {
                                            'resolved', 'closed' => 'success',
                                            'investigating', 'in_progress' => 'warning text-dark',
                                            'open' => 'danger',
                                            default => 'secondary'
                                        } ?>">
                                            <?= e(ucfirst($status)) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= e(substr((string) ($incident['createdAt'] ?? '-'), 0, 10)) ?></td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('views/security/incidents/view/view.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>"><i class="bi bi-eye me-1"></i>View</a>
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/security/incidents/edit/edit.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
                                        <a class="btn btn-sm btn-outline-danger" href="<?= url('views/security/incidents/delete/delete.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No incidents recorded.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
