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

$medicalService = new MedicalService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recordId = sanitize($_POST['recordId'] ?? '');
    $caseStatus = sanitize($_POST['caseStatus'] ?? '');

    if ($recordId === '' || !in_array($caseStatus, ['open', 'reviewed'], true)) {
        flash('error', 'Unable to update the emergency case.');
    } else {
        $result = $medicalService->update($recordId, [
            'caseStatus' => $caseStatus,
            'updatedBy' => current_user_id(),
        ]);
        flash($result['success'] ? 'success' : 'error', $result['success'] ? 'Emergency case status updated.' : $result['message']);
    }

    redirect(url('views/nurse/emergency-cases/emergency-cases.php'));
}

$students = [];
foreach (StudentService::all() as $student) {
    $studentId = (string) ($student['id'] ?? '');
    if ($studentId === '') continue;

    $name = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
    $students[$studentId] = [
        'name' => $name !== '' ? $name : 'Unnamed student',
        'admissionNo' => $student['admissionNo'] ?? $student['studentId'] ?? $studentId,
        'houseId' => $student['houseId'] ?? '',
    ];
}

$emergencySeverities = ['severe', 'critical', 'emergency'];
$records = array_values(array_filter($medicalService->all(), static function (array $record) use ($emergencySeverities): bool {
    return in_array(strtolower((string) ($record['severity'] ?? '')), $emergencySeverities, true);
}));

$search = strtolower(trim(sanitize($_GET['search'] ?? '')));
$severityFilter = strtolower(trim(sanitize($_GET['severity'] ?? 'all')));
$statusFilter = strtolower(trim(sanitize($_GET['status'] ?? 'all')));

if (!in_array($severityFilter, array_merge(['all'], $emergencySeverities), true)) $severityFilter = 'all';
if (!in_array($statusFilter, ['all', 'open', 'reviewed'], true)) $statusFilter = 'all';

$studentLabel = static function (array $record) use ($students): string {
    $studentId = (string) ($record['studentId'] ?? '');
    if ($studentId !== '' && isset($students[$studentId])) {
        return $students[$studentId]['name'] . ' (' . $students[$studentId]['admissionNo'] . ')';
    }
    return $record['studentName'] ?? ($studentId !== '' ? $studentId : 'Not linked');
};

$filteredRecords = array_values(array_filter($records, static function (array $record) use ($search, $severityFilter, $statusFilter, $studentLabel): bool {
    $severity = strtolower((string) ($record['severity'] ?? ''));
    $caseStatus = strtolower((string) ($record['caseStatus'] ?? 'open'));
    $haystack = strtolower(implode(' ', [
        $studentLabel($record),
        (string) ($record['diagnosis'] ?? ''),
        (string) ($record['treatment'] ?? ''),
        (string) ($record['notes'] ?? ''),
    ]));

    return ($severityFilter === 'all' || $severity === $severityFilter)
        && ($statusFilter === 'all' || $caseStatus === $statusFilter)
        && ($search === '' || str_contains($haystack, $search));
}));

$openCases = count(array_filter($records, static fn(array $record): bool => strtolower((string) ($record['caseStatus'] ?? 'open')) !== 'reviewed'));
$criticalCases = count(array_filter($records, static fn(array $record): bool => strtolower((string) ($record['severity'] ?? '')) === 'critical'));
$emergencyCases = count(array_filter($records, static fn(array $record): bool => strtolower((string) ($record['severity'] ?? '')) === 'emergency'));
$reviewedCases = count($records) - $openCases;

$pageTitle = 'Emergency Cases';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Students', 'href' => url('views/nurse/students/students.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => url('views/nurse/emergency-cases/emergency-cases.php'), 'active' => true],
    ['icon' => 'bi-clipboard2-pulse', 'label' => 'Medical Incidents', 'href' => url('views/nurse/medical-incidents/medical-incidents.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/nurse/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-exclamation-octagon-fill text-danger me-2"></i>Emergency Cases</h4>
                <p class="text-muted mb-0">Prioritise severe, critical, and emergency medical records requiring clinical follow-up.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-primary btn-sm" href="<?= url('views/nurse/emergency-cases/broadcast/broadcast.php') ?>">
                    <i class="bi bi-megaphone me-1"></i>Broadcast
                </a>
                <a class="btn btn-outline-success btn-sm" href="<?= url('views/nurse/emergency-cases/contacts/add/add.php') ?>">
                    <i class="bi bi-person-plus me-1"></i>Add Contact
                </a>
                <a class="btn btn-outline-warning btn-sm" href="<?= url('views/nurse/emergency-cases/referral/create.php') ?>">
                    <i class="bi bi-file-medical me-1"></i>Referral
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/nurse/emergency-cases/export/export.php') ?>">
                    <i class="bi bi-download me-1"></i>Export
                </a>
                <a class="btn btn-danger btn-sm" href="<?= url('views/nurse/create-record/create-record.php') ?>">
                    <i class="bi bi-plus-circle me-1"></i>Log Emergency Record
                </a>
            </div>
        </div>

        <div class="alert alert-danger border-0 shadow-sm d-flex align-items-start gap-3 mb-4" role="alert">
            <i class="bi bi-lightning-charge-fill fs-4"></i>
            <div><strong>Clinical escalation view.</strong> Review urgent cases promptly and use the record notes to document treatment and referrals.</div>
        </div>

        <div class="row g-3 mb-4">
            <?php foreach ([
                ['Open cases', $openCases, 'danger', 'bi-exclamation-octagon', 'Awaiting clinical review'],
                ['Critical cases', $criticalCases, 'warning', 'bi-heart-pulse', 'High-risk medical cases'],
                ['Emergency cases', $emergencyCases, 'danger', 'bi-lightning-charge', 'Immediate response required'],
                ['Reviewed cases', $reviewedCases, 'success', 'bi-check2-circle', 'Follow-up status recorded'],
            ] as [$label, $count, $colour, $icon, $description]): ?>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card h-100 p-3 border-start border-4 border-<?= $colour ?> shadow-sm">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="text-muted small text-uppercase fw-semibold"><?= e($label) ?></span><h3 class="my-1 fw-bold text-<?= $colour ?>"><?= e((string) $count) ?></h3><small class="text-muted"><?= e($description) ?></small></div>
                            <span class="rounded-3 bg-<?= $colour ?> bg-opacity-10 text-<?= $colour ?> p-2"><i class="bi <?= e($icon) ?> fs-4"></i></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card stat-card shadow-sm border-0 mb-4">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-lg-5"><label class="form-label small fw-semibold">Search cases</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Student, diagnosis, treatment, or notes"></div>
                    <div class="col-sm-4 col-lg-2"><label class="form-label small fw-semibold">Severity</label><select name="severity" class="form-select form-select-sm"><option value="all">All urgent levels</option><?php foreach ($emergencySeverities as $option): ?><option value="<?= e($option) ?>" <?= $severityFilter === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?></select></div>
                    <div class="col-sm-4 col-lg-2"><label class="form-label small fw-semibold">Review status</label><select name="status" class="form-select form-select-sm"><option value="all">All cases</option><option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option><option value="reviewed" <?= $statusFilter === 'reviewed' ? 'selected' : '' ?>>Reviewed</option></select></div>
                    <div class="col-sm-4 col-lg-3 d-flex gap-2"><button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-funnel me-1"></i>Apply filters</button><a href="<?= url('views/nurse/emergency-cases/emergency-cases.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a></div>
                </form>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center"><h6 class="mb-0 fw-bold"><i class="bi bi-clipboard2-pulse me-2 text-danger"></i>Urgent medical queue</h6><small class="text-muted"><?= count($filteredRecords) ?> case(s) shown</small></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table w-100">
                <thead class="table-light"><tr><th>Student</th><th>Diagnosis</th><th>Treatment / Notes</th><th>Severity</th><th>Case status</th><th>Response due</th><th>Logged</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php if ($filteredRecords): foreach ($filteredRecords as $record): ?>
                    <?php $severity = strtolower((string) ($record['severity'] ?? 'emergency')); $caseStatus = strtolower((string) ($record['caseStatus'] ?? 'open')); $recordId = (string) ($record['id'] ?? ''); ?>
                    <tr>
                        <td class="fw-semibold"><?= e($studentLabel($record)) ?></td><td><?= e($record['diagnosis'] ?? 'Not recorded') ?></td><td class="small text-muted"><?= e(trim(($record['treatment'] ?? '') . ' ' . ($record['notes'] ?? '')) ?: 'No treatment notes') ?></td>
                        <td><span class="badge bg-<?= $severity === 'critical' ? 'warning text-dark' : 'danger' ?>"><?= e(ucfirst($severity)) ?></span></td><td><span class="badge <?= $caseStatus === 'reviewed' ? 'bg-success' : 'bg-danger' ?>"><?= $caseStatus === 'reviewed' ? 'Reviewed' : 'Open' ?></span></td><td class="small text-muted"><?= e(substr((string) ($record['responseDueAt'] ?? 'Not set'), 0, 16)) ?></td><td class="small text-muted"><?= e(substr((string) ($record['createdAt'] ?? 'Not recorded'), 0, 16)) ?></td>
                        <td class="text-end text-nowrap"><?php if ($recordId !== ''): ?><a class="btn btn-sm btn-outline-primary" href="<?= url('views/nurse/edit-record/edit-record.php?id=' . urlencode($recordId)) ?>" title="Edit medical record"><i class="bi bi-pencil"></i></a><form class="d-inline" method="POST"><input type="hidden" name="recordId" value="<?= e($recordId) ?>"><input type="hidden" name="caseStatus" value="<?= $caseStatus === 'reviewed' ? 'open' : 'reviewed' ?>"><button class="btn btn-sm btn-outline-<?= $caseStatus === 'reviewed' ? 'secondary' : 'success' ?>" type="submit" title="<?= $caseStatus === 'reviewed' ? 'Reopen case' : 'Mark as reviewed' ?>"><i class="bi bi-<?= $caseStatus === 'reviewed' ? 'arrow-counterclockwise' : 'check2' ?>"></i></button></form><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-shield-check fs-3 d-block mb-2 text-success"></i>No emergency cases match the selected filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table></div></div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
