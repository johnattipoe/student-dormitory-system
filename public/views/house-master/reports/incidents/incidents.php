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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\IncidentService;
use App\Services\StudentService;
use App\Services\UserService;
use App\Services\HouseService;
use App\Services\FirebaseService;

$houseId = (string) (current_user()['houseId'] ?? current_user()['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');

$incidents = (new IncidentService())->byHouse($houseId);
$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) {
    $studentMap[(string) ($student['id'] ?? '')] = $student;
}

$statusCounts = ['open' => 0, 'under_review' => 0, 'resolved' => 0, 'closed' => 0];
$priorityCounts = ['high' => 0, 'medium' => 0, 'low' => 0];

foreach ($incidents as $incident) {
    $status = strtolower((string) ($incident['status'] ?? 'open'));
    $priority = strtolower((string) ($incident['priority'] ?? $incident['severity'] ?? 'low'));
    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    } else {
        $statusCounts[$status] = 1;
    }
    if (isset($priorityCounts[$priority])) {
        $priorityCounts[$priority]++;
    } else {
        $priorityCounts[$priority] = 1;
    }
}

// User map for resolving reportedBy
$userMap = [];
try {
    foreach ((new UserService())->all() as $u) {
        $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
        if ($name === '') $name = $u['displayName'] ?? $u['username'] ?? $u['email'] ?? '';
        if ($name !== '') {
            $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
            $displayName = $name . $roleLabel;
            foreach ([$u['id'] ?? null, $u['uid'] ?? null, $u['userId'] ?? null, $u['firebaseUid'] ?? null, $u['email'] ?? null] as $key) {
                if ($key !== null && (string)$key !== '') {
                    $userMap[(string)$key] = $displayName;
                }
            }
        }
    }
} catch (\Throwable $e) {}

$getReporterName = function (array $incident) use (&$userMap): string {
    if (!empty($incident['reportedByName']) && !str_starts_with((string)$incident['reportedByName'], 'Staff/User')) {
        return (string) $incident['reportedByName'];
    }
    $raw = (string) ($incident['reportedBy'] ?? $incident['reported_by'] ?? $incident['userId'] ?? '');
    if ($raw === '') return 'System';
    if (isset($userMap[$raw])) return $userMap[$raw];

    try {
        $u = FirebaseService::getInstance()->getDocument('users', $raw);
        if ($u) {
            $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
            if ($name === '') $name = $u['displayName'] ?? $u['username'] ?? '';
            if ($name !== '') {
                $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
                $userMap[$raw] = $name . $roleLabel;
                return $userMap[$raw];
            }
        }
        $s = FirebaseService::getInstance()->getDocument('students', $raw);
        if ($s) {
            $sName = trim(($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? ''));
            $adm = !empty($s['admissionNo']) ? ' [' . $s['admissionNo'] . ']' : '';
            $userMap[$raw] = ($sName ?: 'Student') . $adm . ' (Student)';
            return $userMap[$raw];
        }
    } catch (\Throwable $e) {}

    return $raw;
};

$pageTitle = 'Incidents & Discipline Report';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/house-master/reports/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Incidents & Discipline Report (<?= e($houseName) ?>)</h5>
                <p class="text-muted mb-0">Overview of disciplinary records, misconduct, and incident statuses.</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-success btn-sm" href="<?= url('views/house-master/reports/export/export.php?type=incidents') ?>">
                    <i class="bi bi-filetype-csv me-1"></i> Export Incidents CSV
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/reports/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i> Reports Overview
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card stat-card p-4 h-100">
                    <h6 class="fw-bold mb-3">Status Breakdown</h6>
                    <div class="row g-2">
                        <?php foreach ([
                            'open' => ['Open', 'danger'],
                            'under_review' => ['Under Review', 'warning'],
                            'resolved' => ['Resolved', 'success'],
                            'closed' => ['Closed', 'secondary'],
                        ] as $stKey => [$stLabel, $stColor]): ?>
                            <div class="col-6">
                                <div class="border rounded p-2 text-center">
                                    <small class="text-muted d-block"><?= e($stLabel) ?></small>
                                    <strong class="fs-4 text-<?= e($stColor) ?>"><?= e((string) ($statusCounts[$stKey] ?? 0)) ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card stat-card p-4 h-100">
                    <h6 class="fw-bold mb-3">Priority Breakdown</h6>
                    <div class="row g-2">
                        <?php foreach ([
                            'high' => ['High Priority', 'danger'],
                            'medium' => ['Medium Priority', 'warning'],
                            'low' => ['Low Priority', 'info'],
                        ] as $prKey => [$prLabel, $prColor]): ?>
                            <div class="col-4">
                                <div class="border rounded p-2 text-center">
                                    <small class="text-muted d-block"><?= e($prLabel) ?></small>
                                    <strong class="fs-4 text-<?= e($prColor) ?>"><?= e((string) ($priorityCounts[$prKey] ?? 0)) ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-bold">Incident Log Records</h6>
                <div class="d-flex gap-2 print-hide">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                    <a class="btn btn-sm btn-primary" href="<?= url('views/house-master/incidents/index/index.php') ?>">
                        <i class="bi bi-flag me-1"></i> Manage Incidents
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover data-table w-100">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Incident Title</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Reported By</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($incidents)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No incident records found for this house.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($incidents as $incident): ?>
                                <?php 
                                    $student = $studentMap[(string) ($incident['studentId'] ?? '')] ?? [];
                                    $sName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: ($incident['studentName'] ?? $incident['studentId'] ?? 'Student');
                                    $priority = strtolower((string) ($incident['priority'] ?? $incident['severity'] ?? 'low'));
                                    $status = strtolower((string) ($incident['status'] ?? 'open'));
                                    $reporterName = $getReporterName($incident);
                                    $dateStr = substr((string) ($incident['reportedAt'] ?? $incident['createdAt'] ?? '—'), 0, 10);
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= e($sName) ?></strong>
                                        <?php if (!empty($student['admissionNo'])): ?>
                                            <div class="small text-muted">[<?= e($student['admissionNo']) ?>]</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= e($incident['title'] ?? $incident['type'] ?? 'Incident') ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?= $priority === 'high' ? 'danger' : ($priority === 'medium' ? 'warning' : 'secondary') ?>">
                                            <?= e(ucfirst($priority)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $status === 'resolved' ? 'success' : ($status === 'under_review' ? 'warning' : 'danger') ?>">
                                            <?= e(ucfirst(str_replace('_', ' ', $status))) ?>
                                        </span>
                                    </td>
                                    <td><?= e($reporterName) ?></td>
                                    <td class="text-nowrap small text-muted"><?= e($dateStr) ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/incidents/view/view.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>">
                                            <i class="bi bi-eye"></i> Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<style>
@media print {
    .print-hide,
    .navbar,
    .sidebar,
    .page-header,
    .main-content > .navbar,
    .main-content > .alerts,
    .btn,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_paginate,
    .dataTables_wrapper .dataTables_info {
        display: none !important;
    }

    body {
        background: #fff !important;
    }

    .main-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }

    .content-wrapper {
        padding: 0 !important;
        margin: 0 !important;
    }

    .card.stat-card {
        box-shadow: none !important;
        border: 1px solid #dee2e6 !important;
    }

    table {
        font-size: 12px;
    }
}
</style>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>