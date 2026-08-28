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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-people-fill text-primary me-2"></i>Student Care Directory</h4>
                <p class="text-muted mb-0">Search students, view medical profiles, and check residence details</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-primary btn-sm" href="<?= url('views/nurse/medical-records/medical-records.php') ?>">
                    <i class="bi bi-journal-medical me-1"></i>Medical Records
                </a>
            </div>
        </div>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Students</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) count($students)) ?></h3>
                            <span class="small text-muted">All enrolled students</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-people fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Active</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $activeCount) ?></h3>
                            <span class="small text-muted">In residence</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-person-check fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Room Assigned</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) $assignedCount) ?></h3>
                            <span class="small text-muted">Allocated beds</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-door-open fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Academic Programs</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $courseCount) ?></h3>
                            <span class="small text-muted">Distinct class codes</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-mortarboard fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold">Search</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Name, ID, admission number, course...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="all">All statuses</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-fill" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a href="<?= url('views/nurse/students/students.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Student Records Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Student Records</h6>
                <small class="text-muted">Showing <strong><?= count($filteredStudents) ?></strong> student(s)</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 data-table w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Contact</th>
                            <th>Academic Profile</th>
                            <th>Room</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($filteredStudents)): ?>
                            <?php foreach ($filteredStudents as $student): ?>
                                <?php
                                $studentStatus = strtolower((string) ($student['status'] ?? 'active'));
                                $statusClass = $studentStatus === 'active' ? 'success' : ($studentStatus === 'suspended' ? 'danger' : 'secondary');
                                $studentId = (string) ($student['id'] ?? $student['studentId'] ?? '');
                                ?>
                                <tr>
                                    <td>
                                        <strong class="text-dark d-block"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></strong>
                                        <small class="text-muted">ID: <?= e($student['admissionNo'] ?? $student['studentId'] ?? $student['id'] ?? 'Not assigned') ?></small>
                                    </td>
                                    <td>
                                        <span class="d-block small"><?= e($student['email'] ?? 'No email') ?></span>
                                        <small class="text-muted"><?= e($student['phone'] ?? 'No phone') ?></small>
                                    </td>
                                    <td>
                                        <span class="d-block small fw-semibold"><?= e($student['course'] ?? 'Class code not specified') ?></span>
                                        <small class="text-muted">Level <?= e($student['level'] ?? '—') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= e($student['roomId'] ?? 'Unassigned') ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= e($statusClass) ?> text-capitalize"><?= e($studentStatus) ?></span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('views/nurse/student-profile/student-profile.php?id=' . urlencode($studentId)) ?>">
                                            <i class="bi bi-person-vcard me-1"></i>Profile
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5"><i class="bi bi-person-x fs-3 d-block mb-2"></i>No students match this view.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
