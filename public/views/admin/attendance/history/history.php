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
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\AttendanceService;
use App\Services\StudentService;
use App\Services\UserService;

$pageTitle = 'Attendance History';
$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$records = AttendanceService::forDate($date);

$studentMap = [];
$markedByMap = [];
$userService = new UserService();
$studentLookupCache = [];

$getStudent = function (string $studentId) use (&$studentLookupCache, &$studentMap, &$markedByMap): ?array {
    $studentId = trim($studentId);
    if ($studentId === '') {
        return null;
    }

    if (!array_key_exists($studentId, $studentLookupCache)) {
        $student = StudentService::findByIdentifier($studentId);
        $studentLookupCache[$studentId] = $student;
        if ($student !== null) {
            $name = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
            $admissionNumber = !empty($student['admissionNo']) ? ' [' . $student['admissionNo'] . ']' : '';
            $displayName = $name . $admissionNumber . ' (Student)';
            foreach ([$student['id'] ?? null, $student['studentId'] ?? null, $student['admissionNo'] ?? null, $student['userId'] ?? null, $student['uid'] ?? null] as $key) {
                if ($key !== null && $key !== '') {
                    $markedByMap[(string) $key] = $displayName;
                }
            }
            $studentMap[$studentId] = $student;
        }
    }

    return $studentLookupCache[$studentId];
};

foreach ($records as $record) {
    $getStudent((string) ($record['studentId'] ?? ''));
}

$getMarkedByName = function (array $entry) use (&$markedByMap, $getStudent, $userService): string {
    if (!empty($entry['markedByName']) && trim((string) $entry['markedByName']) !== '' && !str_starts_with((string) $entry['markedByName'], 'Staff/User')) {
        return (string) $entry['markedByName'];
    }
    $rawId = trim((string) ($entry['markedBy'] ?? ''));
    if ($rawId === '') return '—';
    if (isset($markedByMap[$rawId])) return $markedByMap[$rawId];

    $user = $userService->find($rawId);
    if ($user) {
        $name = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''))));
        if ($name === '') $name = $user['displayName'] ?? $user['username'] ?? $user['email'] ?? '';
        if ($name !== '') {
            $roleLabel = !empty($user['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $user['role'])) . ')' : '';
            $markedByMap[$rawId] = $name . $roleLabel;
            return $markedByMap[$rawId];
        }
    }

    $student = $getStudent($rawId);
    if ($student !== null) {
        $name = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
        if ($name !== '') {
            $admissionNumber = !empty($student['admissionNo']) ? ' [' . $student['admissionNo'] . ']' : '';
            $markedByMap[$rawId] = $name . $admissionNumber . ' (Student)';
            return $markedByMap[$rawId];
        }
    }

    $markedByMap[$rawId] = $rawId;
    return $rawId;
};

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-calendar2-check', 'label' => 'Attendance', 'href' => url('views/admin/attendance/index/index.php')],
    ['icon' => 'bi-clock-history', 'label' => 'History', 'href' => url('views/admin/attendance/history/history.php?date=' . urlencode($date)), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-0">Attendance History</h5>
                <p class="text-muted mb-0">Date: <?= e($date) ?></p>
            </div>
            <div class="d-flex gap-2">
                <form method="GET" class="d-flex gap-2">
                    <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>">
                    <button class="btn btn-primary btn-sm">Filter</button>
                </form>
                <a href="<?= url('views/admin/attendance/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
            </div>
        </div>
        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Status</th>
                        <th>Marked By</th>
                        <th>Recorded At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($records)): ?>
                        <?php foreach ($records as $record): ?>
                            <?php 
                                $student = $studentMap[(string) ($record['studentId'] ?? '')] ?? null;
                                $studentName = $student ? trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) : ($record['studentName'] ?? $record['studentId'] ?? '—');
                                $markedByName = $getMarkedByName($record);
                            ?>
                            <tr>
                                <td><strong><?= e($studentName) ?></strong></td>
                                <td>
                                    <span class="badge bg-<?= ($record['status'] ?? '') === 'present' ? 'success' : (($record['status'] ?? '') === 'absent' ? 'danger' : 'warning') ?>">
                                        <?= e(ucfirst($record['status'] ?? 'unknown')) ?>
                                    </span>
                                </td>
                                <td><?= e($markedByName) ?></td>
                                <td><span class="small text-muted"><?= e(substr((string) ($record['recordedAt'] ?? $record['createdAt'] ?? $record['date'] ?? ''), 0, 19)) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">No attendance records found for this date.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>