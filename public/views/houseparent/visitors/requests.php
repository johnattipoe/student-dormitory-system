<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\StudentService;

$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) {
    $studentMap[(string) ($student['id'] ?? '')] = $student;
}

$visitorRequests = FirebaseService::getInstance()->getCollection(\COL_VISITOR_REQUESTS, [], 500);
$filteredRequests = [];
foreach ($visitorRequests as $request) {
    $studentId = (string) ($request['studentId'] ?? '');
    if ($houseId && !empty($studentMap[$studentId]) && ($studentMap[$studentId]['houseId'] ?? null) !== $houseId) {
        continue;
    }
    if (!$houseId || (($request['houseId'] ?? null) === $houseId) || !empty($studentMap[$studentId])) {
        $filteredRequests[] = $request;
    }
}

$pageTitle = 'Visitor Requests';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/houseparent/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/houseparent/attendance/index.php')],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/houseparent/rooms/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/houseparent/visitors/index.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/houseparent/incidents/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/houseparent/notifications/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-3">
            <h5 class="mb-3">Visitor Requests</h5>
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Visitor</th>
                        <th>Student</th>
                        <th>Visit Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filteredRequests)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">No visitor requests found for your house.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filteredRequests as $request): ?>
                            <?php $requestStudent = $studentMap[(string) ($request['studentId'] ?? '')] ?? null; ?>
                            <tr>
                                <td><?= e($request['visitorName'] ?? '—') ?></td>
                                <td><?= e(trim((($requestStudent['firstName'] ?? '') . ' ' . ($requestStudent['lastName'] ?? '')))) ?: e($request['studentId'] ?? '—') ?></td>
                                <td><?= e($request['requestedDate'] ?? ($request['visitDate'] ?? '—')) ?></td>
                                <td><span class="badge bg-<?= ($request['status'] ?? '') === 'approved' ? 'success' : (($request['status'] ?? '') === 'rejected' ? 'danger' : 'warning') ?>"><?= e($request['status'] ?? 'pending') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
