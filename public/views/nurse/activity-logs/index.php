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
$allowedRoles = [ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\StudentService;
use App\Services\UserService;

$pageTitle = 'Medical Activity Logs & Audit Trail';

// Fetch raw medical logs and general clinic activity logs
$firebase = FirebaseService::getInstance();
$allLogs = $firebase->getCollection(COL_ACTIVITY_LOGS, [], 500);
$medicalRecords = $firebase->getCollection('medical_records', [], 500);

// Combine medical logs and activity logs related to nurse / clinic
$combinedLogs = [];
foreach ($allLogs as $l) {
    $action = strtolower((string)($l['event'] ?? $l['action'] ?? $l['type'] ?? ''));
    if (str_contains($action, 'medic') || str_contains($action, 'clinic') || str_contains($action, 'health') || str_contains($action, 'nurse') || ($l['role'] ?? '') === ROLE_NURSE || ($l['performedByRole'] ?? '') === ROLE_NURSE) {
        $combinedLogs[] = $l;
    }
}

// Convert recent medical records into audit trail items if not present
foreach ($medicalRecords as $mr) {
    $combinedLogs[] = [
        'id' => $mr['id'] ?? null,
        'event' => 'Clinic Consultation',
        'action' => 'Medical Treatment',
        'type' => 'medical_record',
        'details' => ($mr['diagnosis'] ?? $mr['condition'] ?? 'Consultation') . ' — ' . ($mr['treatment'] ?? 'Medication administered'),
        'studentId' => $mr['studentId'] ?? null,
        'studentName' => $mr['studentName'] ?? null,
        'performedByName' => $mr['recordedByName'] ?? $mr['nurseName'] ?? 'Nurse',
        'severity' => $mr['severity'] ?? 'normal',
        'timestamp' => $mr['createdAt'] ?? $mr['date'] ?? date(DATE_ATOM),
        'ip' => '127.0.0.1',
    ];
}

// Deduplicate and sort newest first
usort($combinedLogs, function ($a, $b) {
    $tA = strtotime((string) ($a['timestamp'] ?? $a['createdAt'] ?? ''));
    $tB = strtotime((string) ($b['timestamp'] ?? $b['createdAt'] ?? ''));
    return $tB <=> $tA;
});

// Map students
$students = StudentService::all();
$studentMap = [];
foreach ($students as $st) {
    $stId = (string) ($st['id'] ?? '');
    if ($stId !== '') {
        $studentMap[$stId] = trim(($st['firstName'] ?? '') . ' ' . ($st['lastName'] ?? ''));
    }
}

// Filter params
$search = strtolower(sanitize($_GET['search'] ?? ''));
$categoryFilter = sanitize($_GET['category'] ?? '');
$fromDate = sanitize($_GET['from_date'] ?? '');
$toDate = sanitize($_GET['to_date'] ?? '');

$logs = array_values(array_filter($combinedLogs, function ($log) use ($search, $categoryFilter, $fromDate, $toDate, $studentMap) {
    $event = strtolower((string) ($log['event'] ?? $log['action'] ?? $log['type'] ?? ''));
    $details = strtolower((string) ($log['details'] ?? $log['description'] ?? ''));
    $nurse = strtolower((string) ($log['performedByName'] ?? $log['userName'] ?? ''));
    $stName = strtolower((string) ($log['studentName'] ?? ($studentMap[$log['studentId'] ?? ''] ?? '')));

    if ($categoryFilter !== '') {
        if (!str_contains($event, $categoryFilter) && !str_contains($details, $categoryFilter)) {
            return false;
        }
    }

    $rawTime = (string) ($log['timestamp'] ?? $log['createdAt'] ?? '');
    $logDate = substr($rawTime, 0, 10);
    if ($fromDate !== '' && $logDate < $fromDate) return false;
    if ($toDate !== '' && $logDate > $toDate) return false;

    if ($search !== '') {
        return str_contains($event, $search) || str_contains($details, $search) || str_contains($nurse, $search) || str_contains($stName, $search);
    }
    return true;
}));

// Metrics
$totalCount = count($combinedLogs);
$todayDate = date('Y-m-d');
$todayCount = count(array_filter($combinedLogs, fn($l) => str_starts_with((string)($l['timestamp'] ?? $l['createdAt'] ?? ''), $todayDate)));
$emergencyCount = count(array_filter($combinedLogs, fn($l) => in_array(strtolower((string)($l['severity'] ?? $l['priority'] ?? '')), ['emergency', 'urgent', 'critical'], true)));

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Students', 'href' => url('views/nurse/students/students.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-clock-history', 'label' => 'Audit Trail', 'href' => url('views/nurse/activity-logs/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Clinic Activity Logs & Medical Audit Trail</h5>
                <p class="text-muted mb-0">Complete audit trail of clinic consultations, prescriptions, and medical observations.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-primary btn-sm" href="<?= url('views/nurse/activity-logs/create/create.php') ?>">
                    <i class="bi bi-plus-circle me-1"></i> Log Clinical Note
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/nurse/activity-logs/export/export.php') ?>">
                    <i class="bi bi-download me-1"></i> Export CSV
                </a>
            </div>
        </div>

        <!-- KPI Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-primary">
                    <small class="text-muted">Total Clinical Audit Records</small>
                    <strong class="fs-2 text-primary my-1"><?= e((string) $totalCount) ?></strong>
                    <span class="small text-muted">Infirmary consultation history</span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-success">
                    <small class="text-muted">Clinic Visits Today</small>
                    <strong class="fs-2 text-success my-1"><?= e((string) $todayCount) ?></strong>
                    <span class="small text-muted"><?= e(date('F d, Y')) ?></span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-danger">
                    <small class="text-muted">Emergency & Critical Cases</small>
                    <strong class="fs-2 text-danger my-1"><?= e((string) $emergencyCount) ?></strong>
                    <span class="small text-muted">High priority consultations</span>
                </div>
            </div>
        </div>

        <!-- Filters Form -->
        <div class="card stat-card p-3 mb-4">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Search Keywords</label>
                    <input name="search" class="form-control form-control-sm" placeholder="Search student name, diagnosis, nurse..." value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <option value="consultation" <?= $categoryFilter === 'consultation' ? 'selected' : '' ?>>Consultation & Checkup</option>
                        <option value="treatment" <?= $categoryFilter === 'treatment' ? 'selected' : '' ?>>Medication / Treatment</option>
                        <option value="emergency" <?= $categoryFilter === 'emergency' ? 'selected' : '' ?>>Emergency & Referral</option>
                        <option value="observation" <?= $categoryFilter === 'observation' ? 'selected' : '' ?>>Clinical Observation</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">From Date</label>
                    <input type="date" name="from_date" class="form-control form-control-sm" value="<?= e($fromDate) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">To Date</label>
                    <input type="date" name="to_date" class="form-control form-control-sm" value="<?= e($toDate) ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end gap-1">
                    <button class="btn btn-primary btn-sm flex-fill" title="Filter"><i class="bi bi-funnel"></i></button>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/nurse/activity-logs/index.php') ?>" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="card stat-card p-3">
            <div class="table-responsive">
                <table class="table table-hover data-table w-100 align-middle">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Student Patient</th>
                            <th>Clinical Event</th>
                            <th>Details & Diagnosis</th>
                            <th>Attending Nurse</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                    $rawTime = (string) ($log['timestamp'] ?? $log['createdAt'] ?? $log['time'] ?? '');
                                    $formattedTime = $rawTime !== '' ? (date('M d, Y H:i', strtotime($rawTime)) ?: $rawTime) : '—';
                                    $stName = $log['studentName'] ?? ($studentMap[$log['studentId'] ?? ''] ?? 'General Patient');
                                    $event = (string) ($log['event'] ?? $log['action'] ?? 'Clinical Activity');
                                    $details = (string) ($log['details'] ?? $log['description'] ?? '—');
                                    $nurse = (string) ($log['performedByName'] ?? $log['userName'] ?? 'Staff Nurse');
                                    $logId = (string) ($log['id'] ?? '');

                                    $sev = strtolower((string)($log['severity'] ?? $log['priority'] ?? 'normal'));
                                    $badgeColor = match($sev) {
                                        'emergency', 'critical', 'urgent' => 'danger',
                                        'high', 'warning' => 'warning',
                                        default => 'success'
                                    };
                                ?>
                                <tr>
                                    <td class="text-nowrap small text-muted"><i class="bi bi-clock me-1"></i><?= e($formattedTime) ?></td>
                                    <td><strong><?= e($stName) ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?= e($badgeColor) ?>-subtle text-<?= e($badgeColor) ?> border">
                                            <?= e(ucwords(str_replace(['_', '-'], ' ', $event))) ?>
                                        </span>
                                    </td>
                                    <td><?= e(mb_strimwidth($details, 0, 75, '...')) ?></td>
                                    <td><?= e($nurse) ?></td>
                                    <td class="text-end">
                                        <?php if ($logId !== ''): ?>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/nurse/activity-logs/view/view.php?id=' . urlencode($logId)) ?>">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No clinical activity logs found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

