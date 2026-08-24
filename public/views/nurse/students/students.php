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

use App\Services\StudentService;

$students = StudentService::all();
$search = strtolower(trim(sanitize($_GET['search'] ?? '')));
$statusFilter = strtolower(trim(sanitize($_GET['status'] ?? 'all')));
$filteredStudents = array_values(array_filter($students, static function (array $student) use ($search, $statusFilter): bool {
    $status = strtolower((string) ($student['status'] ?? 'active'));
    $haystack = strtolower(implode(' ', [
        (string) ($student['firstName'] ?? ''),
        (string) ($student['lastName'] ?? ''),
        (string) ($student['studentId'] ?? $student['id'] ?? ''),
        (string) ($student['admissionNo'] ?? ''),
        (string) ($student['course'] ?? ''),
    ]));
    return ($statusFilter === 'all' || $status === $statusFilter)
        && ($search === '' || str_contains($haystack, $search));
}));
usort($filteredStudents, static fn(array $first, array $second): int => strcasecmp(
    trim(($first['lastName'] ?? '') . ' ' . ($first['firstName'] ?? '')),
    trim(($second['lastName'] ?? '') . ' ' . ($second['firstName'] ?? ''))
));
$activeCount = count(array_filter($students, static fn(array $student): bool => strtolower((string) ($student['status'] ?? 'active')) === 'active'));
$assignedCount = count(array_filter($students, static fn(array $student): bool => !empty($student['roomId'])));
$courseCount = count(array_unique(array_filter(array_map(static fn(array $student): string => trim((string) ($student['course'] ?? '')), $students))));

$pageTitle = 'Nurse Students';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Students', 'href' => url('views/nurse/students/students.php'), 'active' => true],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => url('views/nurse/emergency-cases/emergency-cases.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/nurse/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper nurse-portal">
        <section class="nurse-hero mb-4">
            <div class="nurse-hero-icon"><i class="bi bi-people"></i></div>
            <div>
                <span class="nurse-kicker">Care directory</span>
                <h1>Students</h1>
                <p>Find student records quickly and keep a clear view of the community you support.</p>
                <div class="nurse-badges"><span class="badge bg-success"><i class="bi bi-person-check me-1"></i><?= e((string) $activeCount) ?> active</span><span class="badge bg-info"><i class="bi bi-door-open me-1"></i><?= e((string) $assignedCount) ?> room assignments</span></div>
            </div>
            <a class="btn btn-light" href="<?= url('views/nurse/medical-records/medical-records.php') ?>"><i class="bi bi-journal-medical me-1"></i>Medical records</a>
        </section>

        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="nurse-stat"><span class="nurse-stat-icon green"><i class="bi bi-people"></i></span><div><small>Total students</small><strong><?= e((string) count($students)) ?></strong></div></div></div>
            <div class="col-md-3"><div class="nurse-stat"><span class="nurse-stat-icon blue"><i class="bi bi-person-check"></i></span><div><small>Active students</small><strong><?= e((string) $activeCount) ?></strong></div></div></div>
            <div class="col-md-3"><div class="nurse-stat"><span class="nurse-stat-icon orange"><i class="bi bi-door-open"></i></span><div><small>Room assigned</small><strong><?= e((string) $assignedCount) ?></strong></div></div></div>
            <div class="col-md-3"><div class="nurse-stat"><span class="nurse-stat-icon red"><i class="bi bi-book"></i></span><div><small>Courses</small><strong><?= e((string) $courseCount) ?></strong></div></div></div>
        </div>

        <section class="nurse-card-panel">
            <div class="nurse-card-header">
                <div><span class="nurse-kicker">Directory</span><h2>Student records</h2><p><?= e((string) count($filteredStudents)) ?> matching student<?= count($filteredStudents) === 1 ? '' : 's' ?>.</p></div>
                <form method="GET" class="nurse-filter-bar">
                    <input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Search students" aria-label="Search students">
                    <select name="status" class="form-select form-select-sm" aria-label="Student status filter"><option value="all">All statuses</option><option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option><option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option></select>
                    <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search"></i><span class="visually-hidden">Search</span></button>
                </form>
            </div>
            <div class="table-responsive"><table class="table table-hover align-middle nurse-data-table w-100">
                <thead>
                    <tr>
                        <th>Student</th><th>Contact</th><th>Academic profile</th><th>Room</th><th>Status</th><th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($filteredStudents)): ?>
                        <?php foreach ($filteredStudents as $student): ?>
                            <?php $studentStatus = strtolower((string) ($student['status'] ?? 'active')); $statusClass = $studentStatus === 'active' ? 'success' : ($studentStatus === 'suspended' ? 'danger' : 'secondary'); $studentId = (string) ($student['id'] ?? $student['studentId'] ?? ''); ?>
                            <tr>
                                <td><strong><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></strong><small class="d-block text-muted">Student ID: <?= e($student['admissionNo'] ?? $student['studentId'] ?? $student['id'] ?? 'Not assigned') ?></small></td>
                                <td><span class="d-block"><?= e($student['email'] ?? 'No email') ?></span><small class="text-muted"><?= e($student['phone'] ?? 'No phone') ?></small></td>
                                <td><span class="d-block"><?= e($student['course'] ?? 'Course not specified') ?></span><small class="text-muted">Level <?= e($student['level'] ?? '—') ?></small></td>
                                <td><?= e($student['roomId'] ?? 'Not assigned') ?></td>
                                <td><span class="badge bg-<?= e($statusClass) ?> text-capitalize"><?= e($studentStatus) ?></span></td>
                                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url('views/nurse/student-profile/student-profile.php?id=' . urlencode($studentId)) ?>"><i class="bi bi-person-vcard me-1"></i>View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5"><i class="bi bi-person-x fs-3 d-block mb-2"></i>No students match this view.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table></div>
        </section>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
