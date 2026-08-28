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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\IncidentService;
use App\Services\StudentService;
use App\Services\UserService;

$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$studentMap = [];
$reporterMap = [];

foreach ($students as $student) {
    $sName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
    $adm = !empty($student['admissionNo']) ? ' [' . $student['admissionNo'] . ']' : '';
    $displayName = $sName . $adm;
    $studentMap[(string) ($student['id'] ?? '')] = $student;
    foreach ([$student['id'] ?? null, $student['studentId'] ?? null, $student['admissionNo'] ?? null, $student['userId'] ?? null, $student['uid'] ?? null] as $key) {
        if ($key !== null && $key !== '') {
            $reporterMap[(string) $key] = $displayName . ' (Student)';
        }
    }
}

try {
    foreach ((new UserService())->all() as $user) {
        $name = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''))));
        if ($name === '') $name = $user['displayName'] ?? $user['username'] ?? $user['email'] ?? null;
        if ($name) {
            $roleLabel = !empty($user['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $user['role'])) . ')' : '';
            $displayName = $name . $roleLabel;
            foreach ([$user['id'] ?? null, $user['uid'] ?? null, $user['userId'] ?? null, $user['email'] ?? null] as $key) {
                if ($key !== null && $key !== '') {
                    $reporterMap[(string) $key] = $displayName;
                }
            }
        }
    }
} catch (\Throwable $e) {}

$getReporterName = function (array $incident) use (&$reporterMap, $studentMap): string {
    if (!empty($incident['reportedByName']) && trim((string) $incident['reportedByName']) !== '' && !str_starts_with((string) $incident['reportedByName'], 'Staff/User')) {
        return (string) $incident['reportedByName'];
    }
    $rawId = trim((string) ($incident['reportedBy'] ?? ''));
    $studentId = trim((string) ($incident['studentId'] ?? ''));
    if ($rawId !== '' && isset($reporterMap[$rawId])) return $reporterMap[$rawId];
    if ($rawId !== '' && $studentId !== '' && ($rawId === $studentId || isset($studentMap[$rawId]))) {
        $s = $studentMap[$rawId] ?? $studentMap[$studentId] ?? [];
        $sName = trim(($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? ''));
        return ($sName ?: 'Student') . ' (Student)';
    }
    if ($studentId !== '' && isset($studentMap[$studentId])) {
        $s = $studentMap[$studentId];
        $sName = trim(($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? ''));
        return ($sName ?: 'Student') . ' (Student)';
    }
    return $rawId !== '' ? $rawId : '—';
};

$incidents = (new IncidentService())->byHouse($houseId);
$openIncidents = array_values(array_filter($incidents, fn($incident) => ($incident['status'] ?? 'open') === 'open'));
$resolvedIncidents = array_values(array_filter($incidents, fn($incident) => ($incident['status'] ?? '') === 'resolved'));

$pageTitle = 'Senior Houseparent Incidents';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/senior-houseparent/attendance/index/index.php')],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/senior-houseparent/rooms/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/senior-houseparent/visitors/index/index.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/senior-houseparent/incidents/index/index.php'), 'active' => true],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/senior-houseparent/notifications/index/index.php')],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Incident Reports</h4>
                <p class="text-muted mb-0">Track and manage incidents reported in your assigned house</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-danger btn-sm" href="<?= url('views/senior-houseparent/incidents/create/create.php') ?>">
                    <i class="bi bi-plus-circle me-1"></i>Report Incident
                </a>
            </div>
        </div>

        <!-- KPI Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Incidents</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) count($incidents)) ?></h3>
                            <span class="small text-muted">All records</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-flag fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Open</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) count($openIncidents)) ?></h3>
                            <span class="small text-muted">Require attention</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-exclamation-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Resolved</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) count($resolvedIncidents)) ?></h3>
                            <span class="small text-muted">Successfully closed</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-check-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Incidents Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-flag me-2"></i>Incident Records</h6>
                <small class="text-muted">Showing <?= e((string) count($incidents)) ?> records</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 data-table w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Involved Student</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Reported By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($incidents)): ?>
                            <?php foreach ($incidents as $incident): ?>
                                <?php 
                                    $incidentStudent = $studentMap[(string) ($incident['studentId'] ?? '')] ?? null; 
                                    $studentName = $incidentStudent ? trim((($incidentStudent['firstName'] ?? '') . ' ' . ($incidentStudent['lastName'] ?? ''))) : ($incident['studentId'] ?? '—');
                                    $priority = $incident['priority'] ?? $incident['severity'] ?? 'medium';
                                    $reporterName = $getReporterName($incident);
                                ?>
                                <tr>
                                    <td class="fw-medium"><?= e($incident['title'] ?? $incident['type'] ?? 'Incident') ?></td>
                                    <td><?= e($studentName) ?></td>
                                    <td>
                                        <span class="badge bg-<?= ($priority === 'high' ? 'danger' : ($priority === 'medium' ? 'warning text-dark' : 'secondary')) ?>">
                                            <?= e(ucfirst((string) $priority)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= ($incident['status'] ?? 'open') === 'resolved' ? 'success' : (($incident['status'] ?? '') === 'investigating' ? 'warning text-dark' : 'danger') ?>">
                                            <?= e(ucfirst((string) ($incident['status'] ?? 'open'))) ?>
                                        </span>
                                    </td>
                                    <td class="small"><?= e($reporterName) ?></td>
                                    <td><span class="small text-muted"><?= e(substr((string) ($incident['createdAt'] ?? $incident['reportedAt'] ?? ''), 0, 10)) ?></span></td>
                                    <td class="text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('views/senior-houseparent/incidents/view/view.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>"><i class="bi bi-eye"></i></a> 
                                        <a class="btn btn-sm btn-outline-warning" href="<?= url('views/senior-houseparent/incidents/edit/edit.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>"><i class="bi bi-pencil"></i></a> 
                                        <a class="btn btn-sm btn-outline-danger" href="<?= url('views/senior-houseparent/incidents/delete/delete.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No incidents found for your house.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>