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

$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT, ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\ExeatService;
use App\Services\HouseService;
use App\Services\StudentService;
use App\Services\BmsSmsService;

$role = current_role() ?? '';
$user = current_user() ?? [];
$userId = current_user_id();
$houseId = current_house_id();
$staffRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT];
$isStudent = $role === ROLE_STUDENT;
$isStaff = in_array($role, $staffRoles, true);
$canCreateForStudent = in_array($role, $staffRoles, true);
$service = new ExeatService();
$studentProfile = $isStudent ? $service->studentForUser($user) : null;
$studentId = $isStudent ? (string) ($studentProfile['id'] ?? '') : null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'The form expired. Please refresh the page and try again.');
        redirect(url('views/exeat/index.php'));
    }

    $action = sanitize($_POST['action'] ?? '');

    if (($isStudent || $canCreateForStudent) && in_array($action, ['request', 'create_internal', 'create_external'], true)) {
        $requestStudent = $studentProfile;
        if ($canCreateForStudent) {
            $selectedStudentId = sanitize($_POST['studentId'] ?? '');
            try {
                if ($role === ROLE_ADMIN) {
                    $allowedStudents = StudentService::all();
                } elseif ($houseId) {
                    $allowedStudents = StudentService::all($houseId);
                } else {
                    $allowedStudents = StudentService::all();
                }
            } catch (Throwable $e) {
                $allowedStudents = [];
            }
            foreach ($allowedStudents as $houseStudent) {
                if ((string) ($houseStudent['id'] ?? '') === $selectedStudentId) {
                    $requestStudent = $houseStudent;
                    break;
                }
            }
        }

        if (!$requestStudent) {
            flash('error', $canCreateForStudent ? 'Please select a valid student.' : 'Student profile was not found for this account.');
            redirect(url('views/exeat/index.php'));
        }

        $exeatType = sanitize($_POST['exeatType'] ?? ($action === 'create_internal' ? 'internal' : 'external'));

        $result = $service->create([
            'studentId' => (string) ($requestStudent['id'] ?? ''),
            'studentName' => trim(($requestStudent['firstName'] ?? '') . ' ' . ($requestStudent['lastName'] ?? '')),
            'houseId' => $requestStudent['houseId'] ?? null,
            'roomId' => $requestStudent['roomId'] ?? null,
            'guardianPhone' => $requestStudent['guardianPhone'] ?? '',
            'exeatType' => $exeatType,
            'startDate' => sanitize($_POST['startDate'] ?? $_POST['date'] ?? ''),
            'endDate' => sanitize($_POST['endDate'] ?? $_POST['date'] ?? ''),
            'startTime' => sanitize($_POST['startTime'] ?? ''),
            'closeTime' => sanitize($_POST['closeTime'] ?? ''),
            'destination' => sanitize($_POST['destination'] ?? ''),
            'reason' => sanitize($_POST['reason'] ?? ''),
            'requestedBy' => $userId,
            'createdByRole' => $role,
        ]);

        flash($result['success'] ? 'success' : 'error', $result['message']);
        
        // Send SMS notification for exeat creation
        if ($result['success']) {
            try {
                $guardianPhone = sanitize($_POST['guardianPhone'] ?? $requestStudent['guardianPhone'] ?? '');
                $appConfig = app_config();
                $smsEnabled = (string) ($appConfig['advanced']['sms_notifications'] ?? '0');
                
                // Log to file
                $logFile = APP_ROOT . '/storage/logs/sms-exeat-' . date('Y-m-d') . '.log';
                $logMsg = "[" . date('H:i:s') . "] [CREATE-INDEX] SMS enabled: {$smsEnabled}, Guardian phone: " . (!empty($guardianPhone) ? substr($guardianPhone, -4, 4) : 'EMPTY') . "\n";
                @file_put_contents($logFile, $logMsg, FILE_APPEND);
                
                if ($guardianPhone !== '' && $smsEnabled === '1') {
                    $smsService = new BmsSmsService();
                    $studentName = trim(($requestStudent['firstName'] ?? '') . ' ' . ($requestStudent['lastName'] ?? ''));
                    $startDate = sanitize($_POST['startDate'] ?? $_POST['date'] ?? '');
                    $endDate = sanitize($_POST['endDate'] ?? $startDate);
                    $destination = sanitize($_POST['destination'] ?? '');
                    $reason = sanitize($_POST['reason'] ?? '');
                    
                    // Determine sender role label
                    $roleSender = match($role) {
                        ROLE_ADMIN => 'Dormitory Administration',
                        ROLE_HOUSE_MASTER => 'House Master',
                        ROLE_HOUSE_MISTRESS => 'House Mistress',
                        ROLE_SENIOR_HOUSEPARENT => 'Senior Houseparent',
                        default => 'Dormitory Staff',
                    };
                    
                    if ($exeatType === 'internal') {
                        $reasonStr = !empty($reason) ? " for {$reason}" : '';
                        $smsMessage = "Your ward {$studentName} has been approved for internal exeat on {$startDate}{$reasonStr} by the {$roleSender}.";
                    } else {
                        $destStr = !empty($destination) ? " to {$destination}" : '';
                        $reasonStr = !empty($reason) ? " for {$reason}" : '';
                        $smsMessage = "Your ward {$studentName} has been approved for external exeat from {$startDate} to {$endDate}{$destStr}{$reasonStr} by the {$roleSender}.";
                    }
                    
                    $logMsg = "[" . date('H:i:s') . "] [CREATE-INDEX] Sending SMS to {$guardianPhone}, length: " . mb_strlen($smsMessage) . "\n";
                    @file_put_contents($logFile, $logMsg, FILE_APPEND);
                    
                    $smsResult = $smsService->send($guardianPhone, $smsMessage);
                    if ($smsResult['success']) {
                        $logMsg = "[" . date('H:i:s') . "] [CREATE-INDEX] ✓ SMS SENT to {$guardianPhone}\n";
                        @file_put_contents($logFile, $logMsg, FILE_APPEND);
                    } else {
                        $logMsg = "[" . date('H:i:s') . "] [CREATE-INDEX] ✗ SMS FAILED: " . ($smsResult['message'] ?? 'Unknown error') . " | Response: " . json_encode($smsResult['provider_response'] ?? []) . "\n";
                        @file_put_contents($logFile, $logMsg, FILE_APPEND);
                    }
                }
            } catch (Throwable $e) {
                $logFile = APP_ROOT . '/storage/logs/sms-exeat-' . date('Y-m-d') . '.log';
                $logMsg = "[" . date('H:i:s') . "] [CREATE-INDEX] ✗ ERROR: " . $e->getMessage() . "\n";
                @file_put_contents($logFile, $logMsg, FILE_APPEND);
            }
        }
        
        redirect(url('views/exeat/index.php'));
    }

    if ($action === 'edit') {
        $recordId = sanitize($_POST['recordId'] ?? '');
        $visibleIds = array_fill_keys(array_map(static fn(array $record): string => (string) ($record['id'] ?? ''), $service->visibleForRole($role, $userId, $houseId, $studentId)), true);

        if ($recordId === '' || !isset($visibleIds[$recordId])) {
            flash('error', 'Exeat request not found or permission denied.');
            redirect(url('views/exeat/index.php'));
        }

        $exeatType = sanitize($_POST['exeatType'] ?? 'external');

        $updateData = [
            'exeatType' => $exeatType,
            'startDate' => sanitize($_POST['startDate'] ?? $_POST['date'] ?? ''),
            'endDate' => sanitize($_POST['endDate'] ?? $_POST['date'] ?? ''),
            'startTime' => sanitize($_POST['startTime'] ?? ''),
            'closeTime' => sanitize($_POST['closeTime'] ?? ''),
            'destination' => sanitize($_POST['destination'] ?? ''),
            'reason' => sanitize($_POST['reason'] ?? ''),
        ];

        if ($isStaff && !empty($_POST['status'])) {
            $newStatus = sanitize($_POST['status']);
            if (in_array($newStatus, ['pending', 'approved', 'rejected', 'departed', 'returned'], true)) {
                $updateData['status'] = $newStatus;
            }
        }

        $result = $service->update($recordId, $updateData);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect(url('views/exeat/index.php'));
    }

    if ($action === 'delete') {
        $recordId = sanitize($_POST['recordId'] ?? '');
        $visibleIds = array_fill_keys(array_map(static fn(array $record): string => (string) ($record['id'] ?? ''), $service->visibleForRole($role, $userId, $houseId, $studentId)), true);

        if ($recordId === '' || !isset($visibleIds[$recordId])) {
            flash('error', 'Exeat request not found or permission denied.');
            redirect(url('views/exeat/index.php'));
        }

        $result = $service->delete($recordId);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect(url('views/exeat/index.php'));
    }

    if ($isStaff && in_array($action, ['approve', 'reject', 'depart', 'return'], true)) {
        $recordId = sanitize($_POST['recordId'] ?? '');
        $visibleIds = array_fill_keys(array_map(static fn(array $record): string => (string) ($record['id'] ?? ''), $service->visibleForRole($role, $userId, $houseId, $studentId)), true);

        if ($recordId === '' || !isset($visibleIds[$recordId])) {
            flash('error', 'Exeat request not found for your role.');
            redirect(url('views/exeat/index.php'));
        }

        $result = $service->updateStatus($recordId, $action, $userId);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        
        redirect(url('views/exeat/index.php'));
    }

    flash('error', 'Invalid exeat action for your role.');
    redirect(url('views/exeat/index.php'));
}

$records = $service->visibleForRole($role, $userId, $houseId, $studentId);
$allVisibleRecords = $records;
$studentMap = [];
$houseMap = [];

try {
    $studentSource = ($isStaff && $role !== ROLE_ADMIN && $houseId)
        ? StudentService::all($houseId)
        : StudentService::all();

    foreach ($studentSource as $student) {
        $id = (string) ($student['id'] ?? '');
        if ($id !== '') {
            $studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
            $studentMap[$id] = [
                'name' => $studentName !== '' ? $studentName : (string) ($student['name'] ?? 'Unnamed student'),
                'admissionNo' => $student['admissionNo'] ?? $student['studentId'] ?? $id,
                'houseId' => $student['houseId'] ?? '',
                'roomId' => $student['roomId'] ?? '',
                'phone' => $student['guardianPhone'] ?? $student['phone'] ?? '',
            ];
        }
    }
} catch (Throwable $e) {
    $studentMap = [];
}

try {
    foreach (HouseService::all() as $house) {
        $id = (string) ($house['id'] ?? '');
        if ($id !== '') {
            $houseMap[$id] = (string) ($house['name'] ?? $id);
        }
    }
} catch (Throwable $e) {
    $houseMap = [];
}

$statusFilter = strtolower(trim(sanitize($_GET['status'] ?? '')));
$typeFilter = strtolower(trim(sanitize($_GET['type'] ?? '')));
$fromDate = sanitize($_GET['from'] ?? '');
$toDate = sanitize($_GET['to'] ?? '');
$searchTerm = trim(sanitize($_GET['search'] ?? ''));

if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'approved', 'rejected', 'departed', 'returned'], true)) {
    $records = array_values(array_filter($records, static fn(array $record): bool => strtolower((string) ($record['status'] ?? 'pending')) === $statusFilter));
}
if ($typeFilter !== '' && in_array($typeFilter, ['internal', 'external'], true)) {
    $records = array_values(array_filter($records, static fn(array $record): bool => strtolower((string) ($record['exeatType'] ?? $record['type'] ?? 'external')) === $typeFilter));
}
if ($fromDate !== '') {
    $records = array_values(array_filter($records, static fn(array $record): bool => (string) ($record['endDate'] ?? $record['startDate'] ?? '') >= $fromDate));
}
if ($toDate !== '') {
    $records = array_values(array_filter($records, static fn(array $record): bool => (string) ($record['startDate'] ?? '') <= $toDate));
}
if ($searchTerm !== '') {
    $needle = strtolower($searchTerm);
    $records = array_values(array_filter($records, static function (array $record) use ($needle, $studentMap, $houseMap): bool {
        $studentId = (string) ($record['studentId'] ?? '');
        $houseId = (string) ($record['houseId'] ?? '');
        $haystack = strtolower(implode(' ', [
            $studentId,
            (string) ($record['studentName'] ?? ''),
            (string) ($studentMap[$studentId]['name'] ?? ''),
            (string) ($studentMap[$studentId]['admissionNo'] ?? ''),
            (string) ($houseMap[$houseId] ?? ''),
            (string) ($record['destination'] ?? ''),
            (string) ($record['reason'] ?? ''),
            (string) ($record['status'] ?? ''),
            (string) ($record['exeatType'] ?? $record['type'] ?? ''),
        ]));

        return str_contains($haystack, $needle);
    }));
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="exeat-requests-' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student', 'Admission No', 'House', 'Type', 'Start Date', 'End Date', 'Start Time', 'Close Time', 'Destination', 'Reason', 'Status', 'Reviewed At']);
    foreach ($records as $record) {
        $recordStudentId = (string) ($record['studentId'] ?? '');
        $recordHouseId = (string) ($record['houseId'] ?? $studentMap[$recordStudentId]['houseId'] ?? '');
        $recType = ($record['exeatType'] ?? $record['type'] ?? '') === 'internal' ? 'Internal' : 'External';
        fputcsv($output, [
            $studentMap[$recordStudentId]['name'] ?? $record['studentName'] ?? $recordStudentId,
            $studentMap[$recordStudentId]['admissionNo'] ?? '',
            $houseMap[$recordHouseId] ?? $recordHouseId,
            $recType,
            $record['startDate'] ?? '',
            $record['endDate'] ?? '',
            $record['startTime'] ?? '',
            $record['closeTime'] ?? $record['endTime'] ?? '',
            $record['destination'] ?? '',
            $record['reason'] ?? '',
            $record['status'] ?? 'pending',
            $record['reviewedAt'] ?? '',
        ]);
    }
    fclose($output);
    exit;
}

$counts = $service->statusCounts($allVisibleRecords);
$activeCount = ($counts['pending'] ?? 0) + ($counts['approved'] ?? 0) + ($counts['departed'] ?? 0);
$dashboardHref = match ($role) {
    ROLE_ADMIN => 'views/admin/dashboard.php',
    ROLE_STUDENT => 'views/student/dashboard/index.php',
    ROLE_SENIOR_HOUSEPARENT => 'views/senior-houseparent/dashboard/index.php',
    default => 'views/house-master/dashboard/index.php',
};
$statusClasses = [
    'pending' => 'warning text-dark',
    'approved' => 'success',
    'rejected' => 'danger',
    'departed' => 'info text-white',
    'returned' => 'secondary',
];

$studentLabel = static function (array $record) use ($studentMap): string {
    $recordStudentId = (string) ($record['studentId'] ?? '');
    if (isset($studentMap[$recordStudentId])) {
        return $studentMap[$recordStudentId]['name'] . ' (' . $studentMap[$recordStudentId]['admissionNo'] . ')';
    }

    $storedName = trim((string) ($record['studentName'] ?? ''));
    if ($storedName !== '') {
        return $storedName;
    }

    return $recordStudentId !== '' ? $recordStudentId : 'Unknown student';
};

$pageTitle = 'Exeat';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url($dashboardHref)],
    ['icon' => 'bi-calendar2-week', 'label' => 'Exeat', 'href' => url('views/exeat/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<style>
.no-caret::after { display: none !important; }
.table-responsive {
    overflow: visible !important;
    min-height: 220px;
}
.card.stat-card {
    overflow: visible !important;
}
.dropdown-menu {
    z-index: 1060 !important;
}
</style>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-calendar2-week text-primary me-2"></i>Exeat Management</h4>
                <p class="text-muted mb-0">
                    <?= $isStudent ? 'Request and track your internal and external permissions to leave dormitory premises.' : 'Review, approve, and track student internal and external exeat requests.' ?>
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($isStaff): ?>
                    <a class="btn btn-outline-success btn-sm" href="<?= url('views/exeat/index.php?export=csv&status=' . urlencode($statusFilter) . '&type=' . urlencode($typeFilter) . '&from=' . urlencode($fromDate) . '&to=' . urlencode($toDate) . '&search=' . urlencode($searchTerm)) ?>">
                        <i class="bi bi-filetype-csv me-1"></i>Export CSV
                    </a>
                <?php endif; ?>
                <span class="badge bg-primary d-inline-flex align-items-center px-3"><?= e((string) count($records)) ?> shown</span>
            </div>
        </div>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Pending</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) ($counts['pending'] ?? 0)) ?></h3>
                            <span class="small text-muted">Awaiting review</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-clock-history fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Approved</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) ($counts['approved'] ?? 0)) ?></h3>
                            <span class="small text-muted">Ready for departure</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-check2-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Active Trips</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) $activeCount) ?></h3>
                            <span class="small text-muted">Currently active</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-box-arrow-right fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-secondary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Returned</span>
                            <h3 class="fw-bold my-1 text-secondary"><?= e((string) ($counts['returned'] ?? 0)) ?></h3>
                            <span class="small text-muted">Completed safely</span>
                        </div>
                        <div class="rounded-3 bg-secondary bg-opacity-10 p-2 text-secondary"><i class="bi bi-box-arrow-in-left fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($isStudent || $canCreateForStudent): ?>
            <!-- Create / Request Exeat Action Banner Card -->
            <div class="card stat-card shadow-sm border-0 p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h6 class="mb-1 fw-bold"><i class="bi bi-send-plus me-2 text-primary"></i><?= $canCreateForStudent ? 'Create Exeat for Student' : 'Request Exeat Permission' ?></h6>
                        <p class="text-muted mb-0 small">
                            <?= $canCreateForStudent 
                                ? ($role === ROLE_ADMIN ? 'Issue an internal day/campus pass or external travel exeat for any student across all houses.' : 'Issue an internal day/campus pass or external travel exeat for dormitory residents.') 
                                : 'Choose internal (hours/day pass) or external (date-range travel) exeat.' ?>
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <a href="<?= url('/views/exeat/create/create.php') ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Open Full Page
                        </a>
                        <!-- Internal Exeat Button -->
                        <button type="button" class="btn btn-info btn-sm text-white" data-bs-toggle="modal" data-bs-target="#createInternalExeatModal">
                            <i class="bi bi-clock-history me-1"></i>Internal Exeat
                        </button>
                        <!-- External Exeat Button -->
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createExternalExeatModal">
                            <i class="bi bi-calendar2-range me-1"></i>External Exeat
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal: Internal Exeat (with Time to Start and Close) -->
            <div class="modal fade" id="createInternalExeatModal" tabindex="-1" aria-labelledby="createInternalExeatModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-info text-white">
                            <div>
                                <h5 class="modal-title text-white fw-bold" id="createInternalExeatModalLabel">
                                    <i class="bi bi-clock-history me-2"></i><?= $canCreateForStudent ? 'Create Internal Exeat' : 'Request Internal Exeat' ?>
                                </h5>
                                <p class="mb-0 small text-white-50">Single-day / Campus boundary pass with specific start and return times</p>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" action="<?= url('views/exeat/index.php') ?>">
                            <div class="modal-body p-4">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="create_internal">
                                <input type="hidden" name="exeatType" value="internal">

                                <div class="row g-3">
                                    <?php if ($canCreateForStudent): ?>
                                        <div class="col-md-12">
                                            <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                                <select name="studentId" class="form-select" required>
                                                    <option value="">Select student...</option>
                                                    <?php foreach ($studentMap as $mapStudentId => $mapStudent): ?>
                                                        <?php $stHouseName = !empty($mapStudent['houseId']) ? ($houseMap[$mapStudent['houseId']] ?? $mapStudent['houseId']) : ''; ?>
                                                        <option value="<?= e((string) $mapStudentId) ?>">
                                                            <?= e($mapStudent['name'] ?? 'Unnamed student') ?> (<?= e($mapStudent['admissionNo'] ?? $mapStudentId) ?>)<?= $stHouseName ? ' — ' . e($stHouseName) : '' ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Exeat Date <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="bi bi-calendar-event"></i></span>
                                            <input type="date" name="date" class="form-control" value="<?= e(date('Y-m-d')) ?>" min="<?= e(date('Y-m-d')) ?>" required>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Time to Start <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="bi bi-alarm"></i></span>
                                            <input type="time" name="startTime" class="form-control" value="08:00" required>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Time to Close / Return <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="bi bi-clock-fill text-danger"></i></span>
                                            <input type="time" name="closeTime" class="form-control" value="17:00" required>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Internal Destination / Area</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="bi bi-geo-alt"></i></span>
                                            <input name="destination" class="form-control" placeholder="e.g. School Clinic, Campus Sports Complex, Town Market, Library">
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                                        <textarea name="reason" class="form-control" rows="3" required placeholder="State purpose of internal exeat..."></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-info text-white">
                                    <i class="bi bi-check2-circle me-1"></i><?= $canCreateForStudent ? 'Create Internal Exeat' : 'Submit Internal Request' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal: External Exeat (with Start Date and End Date) -->
            <div class="modal fade" id="createExternalExeatModal" tabindex="-1" aria-labelledby="createExternalExeatModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-primary text-white">
                            <div>
                                <h5 class="modal-title text-white fw-bold" id="createExternalExeatModalLabel">
                                    <i class="bi bi-calendar2-range me-2"></i><?= $canCreateForStudent ? 'Create External Exeat' : 'Request External Exeat' ?>
                                </h5>
                                <p class="mb-0 small text-white-50">Multi-day / Off-campus home, holiday, hospital or travel exeat</p>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" action="<?= url('views/exeat/index.php') ?>">
                            <div class="modal-body p-4">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="create_external">
                                <input type="hidden" name="exeatType" value="external">

                                <div class="row g-3">
                                    <?php if ($canCreateForStudent): ?>
                                        <div class="col-md-12">
                                            <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                                <select name="studentId" class="form-select" required>
                                                    <option value="">Select student...</option>
                                                    <?php foreach ($studentMap as $mapStudentId => $mapStudent): ?>
                                                        <?php $stHouseName = !empty($mapStudent['houseId']) ? ($houseMap[$mapStudent['houseId']] ?? $mapStudent['houseId']) : ''; ?>
                                                        <option value="<?= e((string) $mapStudentId) ?>">
                                                            <?= e($mapStudent['name'] ?? 'Unnamed student') ?> (<?= e($mapStudent['admissionNo'] ?? $mapStudentId) ?>)<?= $stHouseName ? ' — ' . e($stHouseName) : '' ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="bi bi-calendar-check"></i></span>
                                            <input type="date" name="startDate" class="form-control" value="<?= e(date('Y-m-d')) ?>" min="<?= e(date('Y-m-d')) ?>" required>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="bi bi-calendar-x"></i></span>
                                            <input type="date" name="endDate" class="form-control" value="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" min="<?= e(date('Y-m-d')) ?>" required>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Destination Address / Home</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="bi bi-house-door"></i></span>
                                            <input name="destination" class="form-control" placeholder="e.g. Home (Accra), Regional Hospital, Guardian Residence">
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                                        <textarea name="reason" class="form-control" rows="3" required placeholder="Explain detailed reason for external exeat request..."></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send me-1"></i><?= $canCreateForStudent ? 'Create External Exeat' : 'Submit External Request' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm border-0 mb-4">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All statuses</option>
                            <?php foreach (['pending', 'approved', 'rejected', 'departed', 'returned'] as $st): ?>
                                <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Type</label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="">All types</option>
                            <option value="internal" <?= $typeFilter === 'internal' ? 'selected' : '' ?>>Internal Exeat</option>
                            <option value="external" <?= $typeFilter === 'external' ? 'selected' : '' ?>>External Exeat</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">From</label>
                        <input type="date" name="from" class="form-control form-control-sm" value="<?= e($fromDate) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">To</label>
                        <input type="date" name="to" class="form-control form-control-sm" value="<?= e($toDate) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Search</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="<?= $isStudent ? 'Destination or reason' : 'Student, house, destination' ?>" value="<?= e($searchTerm) ?>">
                    </div>
                    <div class="col-md-1 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel"></i></button>
                        <?php if ($statusFilter !== '' || $typeFilter !== '' || $searchTerm !== '' || $fromDate !== '' || $toDate !== ''): ?>
                            <a href="<?= url('views/exeat/index.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="bi bi-x"></i></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Data Table -->
        <div class="card stat-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-journal-text me-2 text-primary"></i>Exeat Applications &amp; Travel Pass Records</h6>
                <span class="badge bg-light text-dark border"><?= count($records) ?> records</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 w-100" data-no-data-table="true">
                        <thead class="table-light">
                            <tr>
                                <?php if (!$isStudent): ?>
                                    <th style="min-width: 140px;">Student</th>
                                <?php endif; ?>
                                <?php if ($role === ROLE_ADMIN): ?>
                                    <th>House</th>
                                <?php endif; ?>
                                <th style="min-width: 95px;">Type</th>
                                <th style="min-width: 120px;">Guardian Phone</th>
                                <th style="min-width: 160px;">Schedule &amp; Dates</th>
                                <th>Destination</th>
                                <th>Reason</th>
                                <th style="min-width: 90px;">Status</th>
                                <th style="min-width: 95px;">Reviewed</th>
                                <th class="text-end" style="min-width: 160px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="<?= e((string) ($isStudent ? 8 : ($role === ROLE_ADMIN ? 11 : 10))) ?>" class="text-center text-muted py-5">
                                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>No exeat requests found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($records as $idx => $record): ?>
                                    <?php
                                    $recId = (string) ($record['id'] ?? '');
                                    $recordStatus = strtolower((string) ($record['status'] ?? 'pending'));
                                    $recordStudentId = (string) ($record['studentId'] ?? '');
                                    $recordHouseId = (string) ($record['houseId'] ?? $studentMap[$recordStudentId]['houseId'] ?? '');
                                    $isInternal = ($record['exeatType'] ?? $record['type'] ?? '') === 'internal';
                                    $recStudentName = $studentLabel($record);
                                    ?>
                                    <tr>
                                        <?php if (!$isStudent): ?>
                                            <td>
                                                <strong class="text-dark d-block"><?= e($recStudentName) ?></strong>
                                                <small class="text-muted font-monospace"><?= e($recordStudentId ?: '') ?></small>
                                            </td>
                                        <?php endif; ?>
                                        <?php if ($role === ROLE_ADMIN): ?>
                                            <td><?= e($houseMap[$recordHouseId] ?? $recordHouseId ?: '-') ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <?php if ($isInternal): ?>
                                                <span class="badge bg-info-subtle text-info border"><i class="bi bi-clock me-1"></i>Internal</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary-subtle text-primary border"><i class="bi bi-calendar-range me-1"></i>External</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-nowrap small">
                                            <?= e($record['guardianPhone'] ?? $studentMap[$recordStudentId]['phone'] ?? '—') ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <?php if ($isInternal): ?>
                                                <div><strong><?= e($record['startDate'] ?? $record['date'] ?? '-') ?></strong></div>
                                                <small class="text-muted">
                                                    <i class="bi bi-clock me-1"></i><?= e($record['startTime'] ?? '--:--') ?> - <?= e($record['closeTime'] ?? $record['endTime'] ?? '--:--') ?>
                                                </small>
                                            <?php else: ?>
                                                <div><strong><?= e($record['startDate'] ?? '-') ?></strong> to <strong><?= e($record['endDate'] ?? '-') ?></strong></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($record['destination'] ?? '—') ?></td>
                                        <td class="small text-muted" style="max-width: 160px;"><?= e(mb_strimwidth((string)($record['reason'] ?? '—'), 0, 40, '...')) ?></td>
                                        <td><span class="badge bg-<?= e($statusClasses[$recordStatus] ?? 'secondary') ?>"><?= e(ucfirst($recordStatus)) ?></span></td>
                                        <td class="text-nowrap small text-muted"><?= e(!empty($record['reviewedAt']) ? date('M j, Y', strtotime((string) $record['reviewedAt'])) : '—') ?></td>
                                        <td class="text-end text-nowrap">
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <!-- Staff Primary Quick Action Button (if applicable) -->
                                                <?php if ($isStaff): ?>
                                                    <?php if ($recordStatus === 'pending'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="recordId" value="<?= e($recId) ?>">
                                                            <input type="hidden" name="action" value="approve">
                                                            <button type="submit" class="btn btn-sm btn-success" title="Approve Request">
                                                                <i class="bi bi-check-lg me-1"></i>Approve
                                                            </button>
                                                        </form>
                                                    <?php elseif ($recordStatus === 'approved'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="recordId" value="<?= e($recId) ?>">
                                                            <input type="hidden" name="action" value="depart">
                                                            <button type="submit" class="btn btn-sm btn-info text-white" title="Mark as Departed">
                                                                <i class="bi bi-box-arrow-right me-1"></i>Depart
                                                            </button>
                                                        </form>
                                                    <?php elseif ($recordStatus === 'departed'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="recordId" value="<?= e($recId) ?>">
                                                            <input type="hidden" name="action" value="return">
                                                            <button type="submit" class="btn btn-sm btn-secondary" title="Mark as Returned">
                                                                <i class="bi bi-box-arrow-in-left me-1"></i>Return
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <!-- Three Dots (⋮) Dropstart Menu -->
                                                <div class="dropdown dropstart">
                                                    <button class="btn btn-sm btn-light border dropdown-toggle no-caret px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="More Actions">
                                                        <i class="bi bi-three-dots-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu shadow border-0 py-2" style="min-width: 180px;">
                                                        <li>
                                                            <button type="button" class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#viewExeatModal_<?= $idx ?>">
                                                                <i class="bi bi-eye text-primary me-2"></i>View Details
                                                            </button>
                                                        </li>
                                                        <li>
                                                            <button type="button" class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#editExeatModal_<?= $idx ?>">
                                                                <i class="bi bi-pencil text-warning me-2"></i>Edit Exeat
                                                            </button>
                                                        </li>

                                                        <?php if ($isStaff && $recordStatus === 'pending'): ?>
                                                            <li><hr class="dropdown-divider my-1"></li>
                                                            <li>
                                                                <form method="POST" class="d-inline w-100">
                                                                    <?= csrf_field() ?>
                                                                    <input type="hidden" name="recordId" value="<?= e($recId) ?>">
                                                                    <input type="hidden" name="action" value="reject">
                                                                    <button type="submit" class="dropdown-item py-2 text-danger">
                                                                        <i class="bi bi-x-circle me-2"></i>Reject Request
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php endif; ?>

                                                        <li><hr class="dropdown-divider my-1"></li>
                                                        <li>
                                                            <button type="button" class="dropdown-item py-2 text-danger" data-bs-toggle="modal" data-bs-target="#deleteExeatModal_<?= $idx ?>">
                                                                <i class="bi bi-trash3 text-danger me-2"></i>Delete Pass
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- =========================================================================
             OUTSIDE-THE-TABLE MODALS: Clean root-level Bootstrap modals
             ========================================================================= -->
        <?php if (!empty($records)): ?>
            <?php foreach ($records as $idx => $record): ?>
                <?php
                $recId = (string) ($record['id'] ?? '');
                $recordStatus = strtolower((string) ($record['status'] ?? 'pending'));
                $recordStudentId = (string) ($record['studentId'] ?? '');
                $recordHouseId = (string) ($record['houseId'] ?? $studentMap[$recordStudentId]['houseId'] ?? '');
                $isInternal = ($record['exeatType'] ?? $record['type'] ?? '') === 'internal';
                $recStudentName = $studentLabel($record);
                ?>

                <!-- 1. VIEW MODAL -->
                <div class="modal fade" id="viewExeatModal_<?= $idx ?>" tabindex="-1" aria-labelledby="viewModalLabel_<?= $idx ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content border-0 shadow">
                            <div class="modal-header bg-primary text-white">
                                <div>
                                    <h5 class="modal-title fw-bold text-white mb-0" id="viewModalLabel_<?= $idx ?>">
                                        <i class="bi bi-file-earmark-person me-2"></i>Exeat Pass Details
                                    </h5>
                                    <small class="text-white-50">Resident: <?= e($recStudentName) ?></small>
                                </div>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded-3 border h-100">
                                            <span class="text-muted small d-block">Student</span>
                                            <h6 class="fw-bold mb-1 text-dark"><?= e($recStudentName) ?></h6>
                                            <small class="text-muted d-block">House: <?= e($houseMap[$recordHouseId] ?? $recordHouseId ?: 'Assigned House') ?></small>
                                            <small class="text-muted d-block">Guardian Phone: <?= e($record['guardianPhone'] ?? $studentMap[$recordStudentId]['phone'] ?? '—') ?></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded-3 border h-100">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted small">Status:</span>
                                                <span class="badge bg-<?= e($statusClasses[$recordStatus] ?? 'secondary') ?> px-2 py-1"><?= e(ucfirst($recordStatus)) ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted small">Exeat Type:</span>
                                                <span class="badge bg-<?= $isInternal ? 'info' : 'primary' ?>"><?= $isInternal ? 'Internal Pass' : 'External Pass' ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="text-muted small">Requested By:</span>
                                                <span class="badge bg-light text-dark border"><?= e(ucfirst((string)($record['createdByRole'] ?? 'User'))) ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="alert <?= $isInternal ? 'alert-info' : 'alert-primary' ?> mb-0">
                                            <h6 class="fw-bold mb-2"><i class="bi bi-calendar-event me-2"></i>Schedule &amp; Timings</h6>
                                            <?php if ($isInternal): ?>
                                                <div class="row g-2">
                                                    <div class="col-sm-4"><strong>Date:</strong> <?= e($record['startDate'] ?? $record['date'] ?? '—') ?></div>
                                                    <div class="col-sm-4 text-success"><strong>Start Time:</strong> <?= e($record['startTime'] ?? '—') ?></div>
                                                    <div class="col-sm-4 text-danger"><strong>Close Time:</strong> <?= e($record['closeTime'] ?? $record['endTime'] ?? '—') ?></div>
                                                </div>
                                            <?php else: ?>
                                                <div class="row g-2">
                                                    <div class="col-sm-6"><strong>Departure Date:</strong> <?= e($record['startDate'] ?? '—') ?></div>
                                                    <div class="col-sm-6 text-danger"><strong>Return Date:</strong> <?= e($record['endDate'] ?? '—') ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="text-muted small fw-semibold">Destination Address / Location</label>
                                        <div class="p-2 bg-light rounded border small">
                                            <i class="bi bi-geo-alt text-danger me-1"></i><?= e($record['destination'] ?? 'No specific destination stated') ?>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="text-muted small fw-semibold">Reason</label>
                                        <div class="p-3 bg-light rounded border small">
                                            <?= nl2br(e($record['reason'] ?? '—')) ?>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="row g-2 small text-muted pt-2 border-top">
                                            <div class="col-6 col-md-3"><strong>Created:</strong> <?= !empty($record['createdAt']) ? date('M j, H:i', strtotime((string)$record['createdAt'])) : '—' ?></div>
                                            <div class="col-6 col-md-3"><strong>Reviewed:</strong> <?= !empty($record['reviewedAt']) ? date('M j, H:i', strtotime((string)$record['reviewedAt'])) : '—' ?></div>
                                            <div class="col-6 col-md-3"><strong>Departed:</strong> <?= !empty($record['departedAt']) ? date('M j, H:i', strtotime((string)$record['departedAt'])) : '—' ?></div>
                                            <div class="col-6 col-md-3"><strong>Returned:</strong> <?= !empty($record['returnedAt']) ? date('M j, H:i', strtotime((string)$record['returnedAt'])) : '—' ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light">
                                <a href="<?= url('views/exeat/view/view.php?id=' . urlencode($recId)) ?>" class="btn btn-sm btn-outline-secondary me-auto">
                                    <i class="bi bi-printer me-1"></i>Print Pass / Full Page
                                </a>
                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. EDIT MODAL -->
                <div class="modal fade" id="editExeatModal_<?= $idx ?>" tabindex="-1" aria-labelledby="editModalLabel_<?= $idx ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content border-0 shadow">
                            <div class="modal-header bg-warning text-dark">
                                <div>
                                    <h5 class="modal-title fw-bold mb-0" id="editModalLabel_<?= $idx ?>">
                                        <i class="bi bi-pencil-square me-2"></i>Edit Exeat Pass
                                    </h5>
                                    <small>Resident: <?= e($recStudentName) ?></small>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="<?= url('views/exeat/index.php') ?>">
                                <div class="modal-body p-4">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="recordId" value="<?= e($recId) ?>">

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small">Pass Type</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="exeatType" id="edit_internal_<?= $idx ?>" value="internal" <?= $isInternal ? 'checked' : '' ?> onchange="toggleEditType('<?= $idx ?>', 'internal')">
                                                <label class="form-check-label" for="edit_internal_<?= $idx ?>">Internal (Hours Pass)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="exeatType" id="edit_external_<?= $idx ?>" value="external" <?= !$isInternal ? 'checked' : '' ?> onchange="toggleEditType('<?= $idx ?>', 'external')">
                                                <label class="form-check-label" for="edit_external_<?= $idx ?>">External (Date Range)</label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Internal Fields -->
                                    <div id="edit_internal_fields_<?= $idx ?>" class="<?= !$isInternal ? 'd-none' : '' ?>">
                                        <div class="row g-2 mb-3">
                                            <div class="col-md-4">
                                                <label class="form-label small fw-semibold">Pass Date</label>
                                                <input type="date" name="date" class="form-control form-control-sm" value="<?= e($record['startDate'] ?? $record['date'] ?? date('Y-m-d')) ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small fw-semibold">Time to Start</label>
                                                <input type="time" name="startTime" class="form-control form-control-sm" value="<?= e($record['startTime'] ?? '08:00') ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small fw-semibold">Time to Close</label>
                                                <input type="time" name="closeTime" class="form-control form-control-sm" value="<?= e($record['closeTime'] ?? $record['endTime'] ?? '17:00') ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- External Fields -->
                                    <div id="edit_external_fields_<?= $idx ?>" class="<?= $isInternal ? 'd-none' : '' ?>">
                                        <div class="row g-2 mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold">Start Date</label>
                                                <input type="date" name="startDate" class="form-control form-control-sm" value="<?= e($record['startDate'] ?? date('Y-m-d')) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold">End Date</label>
                                                <input type="date" name="endDate" class="form-control form-control-sm" value="<?= e($record['endDate'] ?? date('Y-m-d', strtotime('+1 day'))) ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Destination</label>
                                        <input name="destination" class="form-control form-control-sm" value="<?= e($record['destination'] ?? '') ?>" placeholder="Destination address">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Reason</label>
                                        <textarea name="reason" class="form-control form-control-sm" rows="3" required><?= e($record['reason'] ?? '') ?></textarea>
                                    </div>

                                    <?php if ($isStaff): ?>
                                        <div class="mb-0 p-2 bg-light rounded border">
                                            <label class="form-label small fw-semibold">Status Override</label>
                                            <select name="status" class="form-select form-select-sm">
                                                <?php foreach (['pending', 'approved', 'rejected', 'departed', 'returned'] as $st): ?>
                                                    <option value="<?= e($st) ?>" <?= $recordStatus === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 3. DELETE MODAL -->
                <div class="modal fade" id="deleteExeatModal_<?= $idx ?>" tabindex="-1" aria-labelledby="deleteModalLabel_<?= $idx ?>" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title fw-bold text-white" id="deleteModalLabel_<?= $idx ?>">
                                    <i class="bi bi-trash3 me-2"></i>Delete Exeat Pass
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="<?= url('views/exeat/index.php') ?>">
                                <div class="modal-body p-4 text-center">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="recordId" value="<?= e($recId) ?>">

                                    <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center mb-3" style="width:60px; height:60px;">
                                        <i class="bi bi-exclamation-triangle fs-2"></i>
                                    </div>
                                    <h6 class="fw-bold mb-2">Are you sure you want to delete this record?</h6>
                                    <p class="text-muted small mb-0">Exeat pass for <strong><?= e($recStudentName) ?></strong> will be permanently removed.</p>
                                </div>
                                <div class="modal-footer bg-light justify-content-center">
                                    <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-danger px-3"><i class="bi bi-trash me-1"></i>Yes, Delete</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleEditType(idx, type) {
    var internalDiv = document.getElementById('edit_internal_fields_' + idx);
    var externalDiv = document.getElementById('edit_external_fields_' + idx);
    if (!internalDiv || !externalDiv) return;
    
    if (type === 'internal') {
        internalDiv.classList.remove('d-none');
        externalDiv.classList.add('d-none');
    } else {
        internalDiv.classList.add('d-none');
        externalDiv.classList.remove('d-none');
    }
}
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
