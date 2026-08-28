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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\RoomService;
use App\Services\StudentService;

$id = sanitize($_GET['id'] ?? '');
$houseId = current_user()['houseId'] ?? null;
$room = $id ? RoomService::find($id) : null;

if (!$room || ($houseId && ($room['houseId'] ?? null) !== $houseId)) {
    flash('error', 'Room not found.');
    redirect(url('views/senior-houseparent/rooms/index/index.php'));
}

$students = array_values(array_filter(StudentService::all($houseId), fn($student) => ($student['roomId'] ?? '') === $id));
$pageTitle = 'Room Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/senior-houseparent/rooms/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
	<?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
	<?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
	<div class="content-wrapper">
		<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
			<div>
				<div class="text-muted small text-uppercase fw-semibold">Room details</div>
				<h5 class="mb-1">Room <?= e($room['roomNumber'] ?? '') ?></h5>
				<p class="text-muted mb-0">Capacity and current occupants.</p>
			</div>
			<a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/rooms/index/index.php') ?>">
				<i class="bi bi-arrow-left me-1"></i>Back to Rooms
			</a>
		</div>

		<div class="row g-3 mb-4">
			<div class="col-sm-6 col-lg-4">
				<div class="card stat-card h-100 p-3">
					<small class="text-muted">Capacity</small>
					<strong class="fs-2 mt-1"><?= e((string) ($room['capacity'] ?? 0)) ?></strong>
					<span class="text-muted small">Total beds</span>
				</div>
			</div>
			<div class="col-sm-6 col-lg-4">
				<div class="card stat-card h-100 p-3">
					<small class="text-muted">Occupied</small>
					<strong class="fs-2 mt-1"><?= e((string) ($room['occupied'] ?? $room['occupancy'] ?? 0)) ?></strong>
					<span class="text-muted small">Assigned students</span>
				</div>
			</div>
			<div class="col-sm-6 col-lg-4">
				<div class="card stat-card h-100 p-3">
					<small class="text-muted">Status</small>
					<strong class="fs-4 mt-2 text-capitalize"><?= e($room['status'] ?? 'unknown') ?></strong>
					<span class="text-muted small">Current room status</span>
				</div>
			</div>
		</div>

		<div class="card stat-card p-3">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<div>
					<h6 class="mb-1">Occupants</h6>
					<p class="text-muted small mb-0"><?= e((string) count($students)) ?> students assigned to this room</p>
				</div>
				<i class="bi bi-people fs-4 text-primary" aria-hidden="true"></i>
			</div>
			<?php foreach ($students as $student): ?>
				<a class="d-flex align-items-center justify-content-between py-3 border-top text-decoration-none" href="<?= url('views/senior-houseparent/students/profile/profile.php?studentId=' . urlencode((string) ($student['id'] ?? ''))) ?>">
					<span><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?> (<?= e($student['admissionNo'] ?? '') ?>)</span>
					<i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
				</a>
			<?php endforeach; ?>
			<?php if (!$students): ?>
				<p class="text-muted mb-0 pt-3 border-top">No occupants assigned.</p>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>