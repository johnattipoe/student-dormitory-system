<?php
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

use App\Services\MedicalService;
use App\Services\StudentService;

$records = (new MedicalService())->incidents();
$students = [];
foreach (StudentService::all() as $student) {
    $studentId = (string) ($student['id'] ?? '');
    if ($studentId !== '') {
        $studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
        $students[$studentId] = [
            'name' => $studentName !== '' ? $studentName : 'Unnamed student',
            'admissionNo' => $student['admissionNo'] ?? $student['studentId'] ?? $studentId,
        ];
    }
}

$search = strtolower(trim(sanitize($_GET['search'] ?? '')));
$severityFilter = strtolower(trim(sanitize($_GET['severity'] ?? 'all')));
$filteredRecords = array_values(array_filter($records, static function (array $record) use ($search, $severityFilter, $students): bool {
    $severity = strtolower((string) ($record['severity'] ?? 'normal'));
    $studentId = (string) ($record['studentId'] ?? '');
    $studentName = $students[$studentId]['name'] ?? '';
    $haystack = strtolower(implode(' ', [
        $studentId,
        $studentName,
        (string) ($record['diagnosis'] ?? ''),
        (string) ($record['treatment'] ?? ''),
        (string) ($record['notes'] ?? ''),
        (string) ($record['type'] ?? ''),
    ]));

    return ($severityFilter === 'all' || $severity === $severityFilter)
        && ($search === '' || str_contains($haystack, $search));
}));

$studentLabel = static function (array $record) use ($students): string {
    $studentId = (string) ($record['studentId'] ?? '');
    if ($studentId !== '' && isset($students[$studentId])) {
        return $students[$studentId]['name'] . ' (' . $students[$studentId]['admissionNo'] . ')';
    }

    return $studentId !== '' ? $studentId : 'Not linked';
};

$openCount = count(array_filter($records, static fn(array $record): bool => strtolower((string) ($record['caseStatus'] ?? 'open')) !== 'reviewed'));
$emergencyCount = count(array_filter($records, static fn(array $record): bool => strtolower((string) ($record['severity'] ?? '')) === 'emergency'));
$criticalCount = count(array_filter($records, static fn(array $record): bool => in_array(strtolower((string) ($record['severity'] ?? '')), ['severe', 'critical'], true)));

$pageTitle = 'Medical Incidents';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Students', 'href' => url('views/nurse/students/students.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => url('views/nurse/emergency-cases/emergency-cases.php')],
    ['icon' => 'bi-clipboard2-pulse', 'label' => 'Medical Incidents', 'href' => url('views/nurse/medical-incidents/medical-incidents.php'), 'active' => true],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/nurse/notifications/notifications.php')],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-clipboard2-pulse text-danger me-2"></i>Medical Incidents Watchlist</h4>
                <p class="text-muted mb-0">Track severe, critical, emergency, and explicitly flagged health incidents</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-danger btn-sm" href="<?= url('views/nurse/create-record/create-record.php') ?>">
                    <i class="bi bi-plus-circle me-1"></i>New Medical Record
                </a>
            </div>
        </div>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Open Cases</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $openCount) ?></h3>
                            <span class="small text-muted">Awaiting full review</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-exclamation-octagon fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Severe / Critical</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $criticalCount) ?></h3>
                            <span class="small text-muted">High attention required</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-heart-pulse fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Emergency Alerts</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $emergencyCount) ?></h3>
                            <span class="small text-muted">Immediate action cases</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-lightning-charge fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold">Search Incidents</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Search student, diagnosis, treatment, notes...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Severity</label>
                        <select name="severity" class="form-select form-select-sm">
                            <option value="all">All severity levels</option>
                            <?php foreach (['severe', 'critical', 'emergency'] as $severityOption): ?>
                                <option value="<?= e($severityOption) ?>" <?= $severityFilter === $severityOption ? 'selected' : '' ?>><?= e(ucfirst($severityOption)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-fill" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a href="<?= url('views/nurse/medical-incidents/medical-incidents.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Medical Incidents Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clipboard2-pulse me-2"></i>Flagged Medical Cases</h6>
                <small class="text-muted">Showing <strong><?= count($filteredRecords) ?></strong> incident(s)</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 data-table w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Diagnosis</th>
                            <th>Treatment / Notes</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($filteredRecords)): ?>
                            <?php foreach ($filteredRecords as $record): ?>
                                <?php
                                $severity = strtolower((string) ($record['severity'] ?? 'critical'));
                                $reviewed = strtolower((string) ($record['caseStatus'] ?? 'open')) === 'reviewed';
                                ?>
                                <tr>
                                    <td class="fw-medium"><?= e($studentLabel($record)) ?></td>
                                    <td><?= e($record['diagnosis'] ?? 'Unspecified') ?></td>
                                    <td class="small"><?= e(trim(($record['treatment'] ?? '') . ' ' . ($record['notes'] ?? '')) ?: 'No treatment notes') ?></td>
                                    <td>
                                        <span class="badge bg-<?= match($severity) {
                                            'emergency' => 'danger',
                                            'critical', 'severe' => 'warning text-dark',
                                            default => 'danger'
                                        } ?>">
                                            <?= e(ucfirst($severity)) ?>
                                        </span>
                                    </td>
                                    <td><span class="badge <?= $reviewed ? 'bg-success' : 'bg-danger' ?>"><?= $reviewed ? 'Reviewed' : 'Open' ?></span></td>
                                    <td class="small text-muted"><?= e(substr((string) ($record['createdAt'] ?? '-'), 0, 10)) ?></td>
                                    <td class="text-end text-nowrap">
                                        <?php if (!empty($record['id'])): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="<?= url('views/nurse/edit-record/edit-record.php?id=' . urlencode((string) $record['id'])) ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No medical incidents match this view.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
