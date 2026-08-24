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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\MedicalService;
use App\Services\StudentService;

$medicalService = new MedicalService();
$allRecords = $medicalService->all();
$csrfToken = $_SESSION['nurse_emergency_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['nurse_emergency_csrf'] = $csrfToken;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        flash('error', 'The form expired. Please refresh the page and try again.');
    } elseif (($_POST['action'] ?? '') === 'mark_reviewed') {
        $recordId = sanitize($_POST['id'] ?? '');
        $result = $recordId !== ''
            ? $medicalService->update($recordId, ['caseStatus' => 'reviewed', 'reviewedAt' => date(DATE_ATOM), 'reviewedBy' => current_user_id()])
            : ['success' => false, 'message' => 'Medical record ID is required.'];
        flash($result['success'] ? 'success' : 'error', $result['message']);
    }
    redirect(url('views/nurse/emergency-cases/emergency-cases.php'));
}

$cases = array_values(array_filter($allRecords, function ($record) {
    $severity = strtolower((string) ($record['severity'] ?? ''));
    return in_array($severity, ['emergency', 'critical'], true);
}));
$studentNames = [];
foreach (StudentService::all() as $student) {
    $studentNames[(string) ($student['id'] ?? '')] = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
}
$filter = strtolower(sanitize($_GET['filter'] ?? 'all'));
$search = strtolower(trim(sanitize($_GET['search'] ?? '')));
$cases = array_values(array_filter($cases, static function (array $case) use ($filter, $search): bool {
    $status = strtolower((string) ($case['caseStatus'] ?? 'open'));
    $haystack = strtolower(implode(' ', [(string) ($case['studentId'] ?? ''), (string) ($case['diagnosis'] ?? ''), (string) ($case['treatment'] ?? '')]));
    return ($filter === 'all' || ($filter === 'open' && $status !== 'reviewed') || ($filter === 'reviewed' && $status === 'reviewed'))
        && ($search === '' || str_contains($haystack, $search));
}));
usort($cases, static function (array $first, array $second): int {
    $firstSeverity = strtolower((string) ($first['severity'] ?? '')) === 'emergency' ? 0 : 1;
    $secondSeverity = strtolower((string) ($second['severity'] ?? '')) === 'emergency' ? 0 : 1;
    return [$firstSeverity, (string) ($second['createdAt'] ?? '')] <=> [$secondSeverity, (string) ($first['createdAt'] ?? '')];
});
$totalCases = count(array_filter($allRecords, static fn(array $record): bool => in_array(strtolower((string) ($record['severity'] ?? '')), ['emergency', 'critical'], true)));
$emergencyCount = count(array_filter($allRecords, static fn(array $record): bool => strtolower((string) ($record['severity'] ?? '')) === 'emergency'));
$criticalCount = count(array_filter($allRecords, static fn(array $record): bool => strtolower((string) ($record['severity'] ?? '')) === 'critical'));
$openCount = count(array_filter($allRecords, static fn(array $record): bool => in_array(strtolower((string) ($record['severity'] ?? '')), ['emergency', 'critical'], true) && strtolower((string) ($record['caseStatus'] ?? 'open')) !== 'reviewed'));

$pageTitle = 'Emergency Cases';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Students', 'href' => url('views/nurse/students/students.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => url('views/nurse/emergency-cases/emergency-cases.php'), 'active' => true],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/nurse/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper nurse-portal">
        <section class="nurse-hero mb-4 emergency-hero">
            <div class="nurse-hero-icon"><i class="bi bi-exclamation-octagon"></i></div>
            <div>
                <span class="nurse-kicker">Priority response</span>
                <h1>Emergency cases</h1>
                <p>Review critical student health cases and keep urgent care actions visible.</p>
                <div class="nurse-badges"><span class="badge bg-danger"><i class="bi bi-lightning-charge me-1"></i><?= e((string) $openCount) ?> open</span><span class="badge bg-warning text-dark"><i class="bi bi-heart-pulse me-1"></i><?= e((string) $totalCases) ?> critical records</span></div>
            </div>
            <a class="btn btn-light" href="<?= url('views/nurse/create-record/create-record.php') ?>"><i class="bi bi-plus-circle me-1"></i>New record</a>
        </section>

        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="nurse-stat"><span class="nurse-stat-icon red"><i class="bi bi-exclamation-octagon"></i></span><div><small>Open cases</small><strong><?= e((string) $openCount) ?></strong></div></div></div>
            <div class="col-md-3"><div class="nurse-stat"><span class="nurse-stat-icon orange"><i class="bi bi-lightning-charge"></i></span><div><small>Emergency</small><strong><?= e((string) $emergencyCount) ?></strong></div></div></div>
            <div class="col-md-3"><div class="nurse-stat"><span class="nurse-stat-icon red"><i class="bi bi-heart-pulse"></i></span><div><small>Critical</small><strong><?= e((string) $criticalCount) ?></strong></div></div></div>
            <div class="col-md-3"><div class="nurse-stat"><span class="nurse-stat-icon blue"><i class="bi bi-check2-circle"></i></span><div><small>Reviewed</small><strong><?= e((string) ($totalCases - $openCount)) ?></strong></div></div></div>
        </div>

        <section class="nurse-card-panel">
            <div class="nurse-card-header">
                <div><span class="nurse-kicker">Triage queue</span><h2>Priority cases</h2><p>Emergency cases are shown before critical cases.</p></div>
                <form method="GET" class="nurse-filter-bar">
                    <input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Search cases" aria-label="Search emergency cases">
                    <select name="filter" class="form-select form-select-sm" aria-label="Case status filter"><option value="all">All cases</option><option value="open" <?= $filter === 'open' ? 'selected' : '' ?>>Open</option><option value="reviewed" <?= $filter === 'reviewed' ? 'selected' : '' ?>>Reviewed</option></select>
                    <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search"></i><span class="visually-hidden">Search</span></button>
                </form>
            </div>
            <div class="table-responsive"><table class="table table-hover align-middle nurse-data-table w-100">
                <thead>
                    <tr>
                        <th>Student</th><th>Clinical case</th><th>Treatment / notes</th>
                        <th>Severity</th>
                        <th>Status</th><th>Reported</th><th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($cases)): ?>
                        <?php foreach ($cases as $case): ?>
                            <?php $severity = strtolower((string) ($case['severity'] ?? 'critical')); $reviewed = strtolower((string) ($case['caseStatus'] ?? 'open')) === 'reviewed'; $severityClass = $severity === 'emergency' ? 'danger' : 'warning'; ?>
                            <tr class="<?= $reviewed ? 'case-reviewed' : 'case-open' ?>">
                                <td><strong><?= e($studentNames[(string) ($case['studentId'] ?? '')] ?? 'Student ' . ($case['studentId'] ?? '—')) ?></strong><small class="d-block text-muted"><?= e($case['studentId'] ?? 'No ID') ?></small></td>
                                <td><strong><?= e($case['diagnosis'] ?? $case['title'] ?? 'Unspecified case') ?></strong><small class="d-block text-muted"><?= e($case['notes'] ?? 'No additional notes.') ?></small></td>
                                <td><?= e($case['treatment'] ?? 'No treatment recorded.') ?></td>
                                <td><span class="badge bg-<?= $severityClass ?>"><?= e(ucfirst($severity)) ?></span></td>
                                <td><span class="badge <?= $reviewed ? 'bg-success' : 'bg-danger' ?>"><?= $reviewed ? 'Reviewed' : 'Open' ?></span></td>
                                <td class="text-nowrap"><?= e($case['createdAt'] ?? '—') ?></td>
                                <td class="text-end"><?php if (!$reviewed && !empty($case['id'])): ?><form method="POST"><input type="hidden" name="action" value="mark_reviewed"><input type="hidden" name="id" value="<?= e($case['id']) ?>"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-check2-circle me-1"></i>Review</button></form><?php else: ?><span class="text-muted small"><i class="bi bi-check2-all me-1"></i>Complete</span><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5"><i class="bi bi-shield-check fs-3 d-block mb-2"></i>No emergency cases match this view.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table></div>
        </section>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
