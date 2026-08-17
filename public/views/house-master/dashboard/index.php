<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\AttendanceService;
use App\Services\IncidentService;
use App\Services\RoomService;
use App\Services\StudentService;
use App\Services\VisitorService;

$houseId = current_user()['houseId'] ?? null;
$stats = [
    'students' => StudentService::count($houseId),
    'rooms' => RoomService::count($houseId),
    'attendance' => count(AttendanceService::todayByHouse($houseId)),
    'visitors' => count((new VisitorService())->todayByHouse($houseId)),
    'incidents' => (new IncidentService())->openByHouse($houseId),
];

$pageTitle = 'House Master Dashboard';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php'), 'active' => true],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/house-master/reports/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/house-master/notifications/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="mb-4">
            <h5 class="mb-1">Welcome, <?= e(current_user()['name'] ?? '') ?></h5>
            <p class="text-muted mb-0">Live overview for your assigned house.</p>
        </div>

        <div class="row g-3">
            <div class="col-md">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Students</div>
                    <div class="fs-3 fw-bold"><?= e((string) $stats['students']) ?></div>
                </div>
            </div>
            <div class="col-md">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Rooms</div>
                    <div class="fs-3 fw-bold"><?= e((string) $stats['rooms']) ?></div>
                </div>
            </div>
            <div class="col-md">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Today's Attendance</div>
                    <div class="fs-3 fw-bold"><?= e((string) $stats['attendance']) ?></div>
                </div>
            </div>
            <div class="col-md">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Today's Visitors</div>
                    <div class="fs-3 fw-bold"><?= e((string) $stats['visitors']) ?></div>
                </div>
            </div>
            <div class="col-md">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Open Incidents</div>
                    <div class="fs-3 fw-bold"><?= e((string) $stats['incidents']) ?></div>
                </div>
            </div>
        </div>

        <br>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Today's Attendance</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">        
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Room</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (AttendanceService::todayByHouse($houseId) as $attendance):   ?>
                                        <tr>
                                            <td><?= e($attendance['studentName'] ?? $attendance['studentId'] ?? '-') ?></td>
                                            <td><?= e($attendance['roomNumber'] ?? '-') ?></td>
                                            <td><?= e($attendance['status'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Today's Visitors</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">        
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Room</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ((new VisitorService())->todayByHouse($houseId) as $visitor):   ?>
                                        <tr>
                                            <td><?= e($visitor['name'] ?? $visitor['visitorName'] ?? '-') ?></td>
                                            <td><?= e($visitor['roomNumber'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <br>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Open Incidents</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">        
                                <thead>
                                    <tr>
                                        <th>Incident</th>
                                        <th>Room</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ((new IncidentService())->byHouse($houseId, true) as $incident):   ?>
                                        <tr>
                                            <td><?= e($incident['title'] ?? 'Incident') ?></td>
                                            <td><?= e($incident['roomNumber'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
