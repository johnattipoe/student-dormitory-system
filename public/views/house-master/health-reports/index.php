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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\StudentService;
use App\Services\MedicalService;
use App\Services\UserService;

$houseId = (string) (current_user()['houseId'] ?? current_user()['house_id'] ?? '');
$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) {
    $studentMap[(string) ($student['id'] ?? '')] = $student;
}
$studentIds = array_fill_keys(array_keys($studentMap), true);

$medicalService = new MedicalService();

// User resolution map for "Recorded By"
$userMap = [];
try {
    foreach ((new UserService())->all() as $u) {
        $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
        if ($name === '') $name = $u['displayName'] ?? $u['username'] ?? $u['email'] ?? null;
        if ($name) {
            $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
            $displayName = $name . $roleLabel;
            foreach ([$u['id'] ?? null, $u['uid'] ?? null, $u['userId'] ?? null, $u['email'] ?? null] as $key) {
                if ($key !== null && $key !== '') {
                    $userMap[(string) $key] = $displayName;
                }
            }
        }
    }
} catch (\Throwable $e) {}

$getRecorderName = function (array $record) use (&$userMap): string {
    if (!empty($record['recordedByName']) && trim((string) $record['recordedByName']) !== '' && !str_starts_with((string) $record['recordedByName'], 'Staff/User')) {
        return (string) $record['recordedByName'];
    }
    $rawId = trim((string) ($record['recordedBy'] ?? ''));
    if ($rawId !== '' && isset($userMap[$rawId])) {
        return $userMap[$rawId];
    }
    if ($rawId !== '') {
        try {
            $u = FirebaseService::getInstance()->getDocument('users', $rawId);
            if ($u) {
                $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
                if ($name === '') $name = $u['displayName'] ?? $u['username'] ?? $u['email'] ?? '';
                if ($name !== '') {
                    $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
                    $userMap[$rawId] = $name . $roleLabel;
                    return $userMap[$rawId];
                }
            }
        } catch (\Throwable $e) {}
        return $rawId;
    }
    return 'Clinic Staff';
};

// Fetch all medical records and filter to current house
$allRecords = $medicalService->all();
$records = array_values(array_filter($allRecords, function (array $record) use ($studentIds, $houseId): bool {
    if (!empty($record['houseId']) && $record['houseId'] === $houseId) {
        return true;
    }
    return isset($studentIds[(string) ($record['studentId'] ?? '')]);
}));

$counts = ['normal' => 0, 'moderate' => 0, 'severe' => 0, 'emergency' => 0];
foreach ($records as $record) {
    $sev = strtolower((string) ($record['severity'] ?? 'normal'));
    if (isset($counts[$sev])) {
        $counts[$sev]++;
    } elseif ($sev === 'critical') {
        $counts['severe']++;
    }
}

// Filter and Search
$searchStudent = sanitize($_GET['studentId'] ?? '');
$searchSeverity = sanitize($_GET['severity'] ?? '');
$keyword = strtolower(sanitize($_GET['search'] ?? ''));

if ($searchStudent !== '' || $searchSeverity !== '' || $keyword !== '') {
    $records = array_values(array_filter($records, function ($record) use ($searchStudent, $searchSeverity, $keyword, $studentMap) {
        $matchesStudent = $searchStudent === '' || ($record['studentId'] ?? '') === $searchStudent;
        $matchesSeverity = $searchSeverity === '' || strtolower((string) ($record['severity'] ?? '')) === strtolower($searchSeverity);
        
        $matchesKeyword = true;
        if ($keyword !== '') {
            $st = $studentMap[(string) ($record['studentId'] ?? '')] ?? [];
            $stName = strtolower(trim(($st['firstName'] ?? '') . ' ' . ($st['lastName'] ?? '') . ' ' . ($st['admissionNo'] ?? '')));
            $diag = strtolower((string) ($record['diagnosis'] ?? ''));
            $treat = strtolower((string) ($record['treatment'] ?? ''));
            $notes = strtolower((string) ($record['notes'] ?? ''));
            $matchesKeyword = str_contains($stName, $keyword) || str_contains($diag, $keyword) || str_contains($treat, $keyword) || str_contains($notes, $keyword);
        }

        return $matchesStudent && $matchesSeverity && $matchesKeyword;
    }));
}

$displayDate = static function (?string $value): string {
    if (!$value) return '—';
    $timestamp = strtotime($value);
    return $timestamp ? date('M d, Y H:i', $timestamp) : $value;
};

$pageTitle = 'Health Reports';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index/index.php')],
    ['icon' => 'bi-heart-pulse', 'label' => 'Health Reports', 'href' => url('views/house-master/health-reports/index.php'), 'active' => true],
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
                <h5 class="mb-1">Medical & Health Reports</h5>
                <p class="text-muted mb-0">Health records from the school clinic/nurse and house medical activity.</p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Total Records</div>
                    <div class="fs-3 fw-bold"><?= e((string) count($records)) ?></div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Normal / Routine</div>
                    <div class="fs-3 fw-bold text-success"><?= e((string) $counts['normal']) ?></div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Moderate Concern</div>
                    <div class="fs-3 fw-bold text-warning"><?= e((string) $counts['moderate']) ?></div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Severe / Emergency</div>
                    <div class="fs-3 fw-bold text-danger"><?= e((string) ($counts['severe'] + $counts['emergency'])) ?></div>
                </div>
            </div>
        </div>

        <div class="card stat-card p-3 mb-3">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <input name="search" class="form-control form-control-sm" placeholder="Search diagnosis, notes, treatment..." value="<?= e($keyword) ?>">
                </div>
                <div class="col-md-3">
                    <select name="studentId" class="form-select form-select-sm">
                        <option value="">All students</option>
                        <?php foreach ($students as $st): ?>
                            <option value="<?= e((string) ($st['id'] ?? '')) ?>" <?= $searchStudent === ($st['id'] ?? '') ? 'selected' : '' ?>>
                                <?= e(trim(($st['firstName'] ?? '') . ' ' . ($st['lastName'] ?? ''))) ?> (<?= e($st['admissionNo'] ?? '') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="severity" class="form-select form-select-sm">
                        <option value="">All severities</option>
                        <option value="normal" <?= $searchSeverity === 'normal' ? 'selected' : '' ?>>Normal</option>
                        <option value="moderate" <?= $searchSeverity === 'moderate' ? 'selected' : '' ?>>Moderate</option>
                        <option value="severe" <?= $searchSeverity === 'severe' ? 'selected' : '' ?>>Severe</option>
                        <option value="emergency" <?= $searchSeverity === 'emergency' ? 'selected' : '' ?>>Emergency</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button class="btn btn-primary btn-sm flex-fill">Filter</button>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/health-reports/index.php') ?>">Reset</a>
                </div>
            </form>
        </div>

        <div class="card stat-card p-3">
            <div class="table-responsive">
                <table class="table table-hover data-table w-100">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Diagnosis</th>
                            <th>Severity</th>
                            <th>Recorded By</th>
                            <th>Date</th>
                            <th>View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No medical records found for your house.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $record): ?>
                                <?php 
                                    $st = $studentMap[(string) ($record['studentId'] ?? '')] ?? null;
                                    $stName = $st ? trim(($st['firstName'] ?? '') . ' ' . ($st['lastName'] ?? '')) : ($record['studentName'] ?? $record['studentId'] ?? '—');
                                    $adm = ($st && !empty($st['admissionNo'])) ? ' [' . $st['admissionNo'] . ']' : '';
                                    $severity = strtolower((string) ($record['severity'] ?? 'normal'));
                                    $recorderName = $getRecorderName($record);
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= e($stName) ?></strong>
                                        <?php if ($adm): ?>
                                            <div class="small text-muted"><?= e($adm) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= e($record['diagnosis'] ?? '—') ?></strong>
                                        <?php if (!empty($record['treatment'])): ?>
                                            <div class="small text-muted"><?= e(mb_strimwidth((string)$record['treatment'], 0, 45, '...')) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= ($severity === 'emergency' || $severity === 'severe' || $severity === 'critical') ? 'danger' : ($severity === 'moderate' ? 'warning text-dark' : 'success') ?>">
                                            <?= e(ucfirst($severity)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-person me-1"></i><?= e($recorderName) ?>
                                        </span>
                                    </td>
                                    <td class="text-nowrap"><span class="small text-muted"><?= e($displayDate($record['createdAt'] ?? null)) ?></span></td>
                                    <td class="text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick='viewRecord(<?= json_encode($record, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= json_encode($stName . $adm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= json_encode($recorderName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View Record Details Modal -->
    <div class="modal fade" id="viewRecordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-info-circle text-primary me-2"></i>Health Report Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Student:</dt>
                        <dd class="col-sm-8" id="view_student"></dd>

                        <dt class="col-sm-4">Diagnosis:</dt>
                        <dd class="col-sm-8" id="view_diagnosis"></dd>

                        <dt class="col-sm-4">Severity:</dt>
                        <dd class="col-sm-8" id="view_severity"></dd>

                        <dt class="col-sm-4">Recorded By:</dt>
                        <dd class="col-sm-8" id="view_recorder"></dd>

                        <dt class="col-sm-4">Treatment:</dt>
                        <dd class="col-sm-8" id="view_treatment"></dd>

                        <dt class="col-sm-4">Notes:</dt>
                        <dd class="col-sm-8" id="view_notes"></dd>

                        <dt class="col-sm-4">Date Recorded:</dt>
                        <dd class="col-sm-8" id="view_date"></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewRecord(rec, studentName, recorderName) {
    document.getElementById('view_student').textContent = studentName || rec.studentName || rec.studentId || '—';
    document.getElementById('view_diagnosis').textContent = rec.diagnosis || '—';
    const sev = (rec.severity || 'normal').toLowerCase();
    const badgeClass = (sev === 'emergency' || sev === 'severe' || sev === 'critical') ? 'danger' : (sev === 'moderate' ? 'warning text-dark' : 'success');
    document.getElementById('view_severity').innerHTML = '<span class="badge bg-' + badgeClass + '">' + sev.toUpperCase() + '</span>';
    document.getElementById('view_recorder').textContent = recorderName || rec.recordedByName || 'Clinic Staff';
    document.getElementById('view_treatment').textContent = rec.treatment || '—';
    document.getElementById('view_notes').textContent = rec.notes || '—';
    document.getElementById('view_date').textContent = rec.createdAt ? new Date(rec.createdAt).toLocaleString() : '—';
    new bootstrap.Modal(document.getElementById('viewRecordModal')).show();
}

</script>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>