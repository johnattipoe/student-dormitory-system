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
use App\Services\AttendanceService; use App\Services\BedService; use App\Services\StudentService;
$id = sanitize($_GET['id'] ?? $_POST['id'] ?? ''); $entry = null;
foreach (AttendanceService::all() as $record) if (($record['id'] ?? '') === $id) { $entry = $record; break; }
if (!$entry) { flash('error', 'Attendance record not found.'); redirect(url('views/admin/attendance/index/index.php')); }
$student = StudentService::find((string) ($entry['studentId'] ?? '')); $bed = null;
foreach (BedService::all() as $candidate) if ((string) ($candidate['studentId'] ?? '') === (string) ($entry['studentId'] ?? '')) { $bed = $candidate; break; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') { AttendanceService::update($id, ['date'=>sanitize($_POST['date'] ?? ($entry['date'] ?? date('Y-m-d'))), 'status'=>sanitize($_POST['status'] ?? 'present')]); flash('success', 'Attendance updated.'); redirect(url('views/admin/attendance/view/view.php?id=' . urlencode($id))); }
$pageTitle = 'Edit Attendance'; $navItems = [['icon'=>'bi-calendar-check','label'=>'Attendance','href'=>url('views/admin/attendance/index/index.php'),'active'=>true]];
require APP_ROOT . '/app/views/components/header/header.php'; require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content"><?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?><?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?><div class="content-wrapper"><div class="card stat-card p-4" style="max-width:600px"><h5 class="mb-3">Edit Attendance</h5><div class="mb-3 p-3 bg-light rounded"><div><strong>Student:</strong> <?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></div><div><strong>Bed:</strong> <?= e($bed['bedNumber'] ?? '—') ?></div></div><form method="POST"><input type="hidden" name="id" value="<?= e($id) ?>"><label class="form-label">Date</label><input type="date" name="date" class="form-control mb-3" value="<?= e($entry['date'] ?? date('Y-m-d')) ?>"><label class="form-label">Status</label><select name="status" class="form-select"><option value="present" <?= ($entry['status'] ?? '') === 'present' ? 'selected' : '' ?>>Present</option><option value="absent" <?= ($entry['status'] ?? '') === 'absent' ? 'selected' : '' ?>>Absent</option><option value="late" <?= ($entry['status'] ?? '') === 'late' ? 'selected' : '' ?>>Late</option><option value="excused" <?= ($entry['status'] ?? '') === 'excused' ? 'selected' : '' ?>>Excused</option></select><div class="mt-4"><button class="btn btn-primary">Save</button> <a class="btn btn-outline-secondary" href="<?= url('views/admin/attendance/view/view.php?id=' . urlencode($id)) ?>">Cancel</a></div></form></div></div></div><?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
