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
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT, ROLE_SECURITY, ROLE_NURSE, ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$pageTitle = 'Attendance';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
$allRecords = FirebaseService::getInstance()->getCollection(COL_ATTENDANCE, [], 200);
$totalRecords = count($allRecords);
$totalPages = max(1, (int) ceil($totalRecords / $limit));
$records = array_slice($allRecords, ($page - 1) * $limit, $limit);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/attendance/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Attendance</h5>
            <a href="<?= url('views/attendance/mark/mark.php') ?>" class="btn btn-primary btn-sm">Mark Attendance</a>
        </div>

        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                <tr>
                    <th>Student</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Marked By</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $r): ?>
                    <?php
                        $attendanceId = (string) ($r['id'] ?? '');
                        if ($attendanceId === '') {
                            $attendanceId = (string) (($r['studentId'] ?? '') . '-' . ($r['date'] ?? ''));
                        }
                    ?>
                    <tr>
                        <td><?= e($r['studentId'] ?? '-') ?></td>
                        <td><?= e($r['date'] ?? '-') ?></td>
                        <td><span class="badge bg-<?= ($r['status'] ?? '') === 'present' ? 'success' : 'warning' ?>"><?= e($r['status'] ?? '-') ?></span></td>
                        <td><?= e($r['markedBy'] ?? '-') ?></td>
                        <td class="text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#attendanceViewModal-<?= e($attendanceId) ?>"><i class="bi bi-eye"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php $paginationBaseUrl = url('views/attendance/index/index.php'); require APP_ROOT . '/app/views/components/pagination/pagination.php'; ?>
        </div>
    </div>
</div>
<?php foreach ($records as $r): ?>
    <?php
        $attendanceId = (string) ($r['id'] ?? '');
        if ($attendanceId === '') {
            $attendanceId = (string) (($r['studentId'] ?? '') . '-' . ($r['date'] ?? ''));
        }
    ?>
    <div class="modal fade" id="attendanceViewModal-<?= e($attendanceId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Attendance Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Student</dt>
                        <dd class="col-sm-8"><?= e($r['studentId'] ?? '-') ?></dd>
                        <dt class="col-sm-4">Date</dt>
                        <dd class="col-sm-8"><?= e($r['date'] ?? '-') ?></dd>
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8"><span class="badge bg-<?= ($r['status'] ?? '') === 'present' ? 'success' : 'warning' ?>"><?= e($r['status'] ?? '-') ?></span></dd>
                        <dt class="col-sm-4">Marked By</dt>
                        <dd class="col-sm-8"><?= e($r['markedBy'] ?? '-') ?></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
