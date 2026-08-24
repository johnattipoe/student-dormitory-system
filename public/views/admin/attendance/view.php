<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
$allowedRoles = [ROLE_ADMIN]; require APP_ROOT . '/app/middleware/RoleMiddleware.php';
use App\Services\AttendanceService; use App\Services\BedService; use App\Services\StudentService;
$id = sanitize($_GET['id'] ?? ''); $entry = null;
foreach (AttendanceService::all() as $record) if (($record['id'] ?? '') === $id) { $entry = $record; break; }
if (!$entry) { flash('error', 'Attendance record not found.'); redirect(url('views/admin/attendance/index.php')); }
$student = StudentService::find((string) ($entry['studentId'] ?? '')); $bed = null;
foreach (BedService::all() as $candidate) if ((string) ($candidate['studentId'] ?? '') === (string) ($entry['studentId'] ?? '')) { $bed = $candidate; break; }
$pageTitle = 'Attendance Details'; $navItems = [['icon'=>'bi-calendar-check','label'=>'Attendance','href'=>url('views/admin/attendance/index.php'),'active'=>true]];
require APP_ROOT . '/app/views/components/header.php'; require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content"><?php require APP_ROOT . '/app/views/components/navbar.php'; ?><?php require APP_ROOT . '/app/views/components/alerts.php'; ?><div class="content-wrapper"><div class="card stat-card p-4"><div class="d-flex justify-content-between"><h5>Attendance Details</h5><a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/attendance/index.php') ?>">Back</a></div><dl class="row mt-4"><dt class="col-sm-4">Student</dt><dd class="col-sm-8"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: ($entry['studentId'] ?? '-')) ?></dd><dt class="col-sm-4">Bed</dt><dd class="col-sm-8"><?= e($bed['bedNumber'] ?? '—') ?></dd><dt class="col-sm-4">Date</dt><dd class="col-sm-8"><?= e($entry['date'] ?? '') ?></dd><dt class="col-sm-4">Status</dt><dd class="col-sm-8"><?= e($entry['status'] ?? '') ?></dd></dl><a class="btn btn-primary" href="<?= url('views/admin/attendance/edit.php?id=' . urlencode($id)) ?>">Edit record</a></div></div></div><?php require APP_ROOT . '/app/views/components/footer.php'; ?>
