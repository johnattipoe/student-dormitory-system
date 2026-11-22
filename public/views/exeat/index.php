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

    if (($isStudent || $canCreateForStudent) && in_array($action, ['request', 'create_internal', 'create_external', 'create_vacation'], true)) {
        $requestStudent = $studentProfile;
        if ($canCreateForStudent) {
            $selectedStudentId = sanitize($_POST['studentId'] ?? '');
            try {
                $requestStudent = $selectedStudentId !== ''
                    ? StudentService::find($selectedStudentId)
                    : null;
                if ($requestStudent && $role !== ROLE_ADMIN && $houseId
                    && (string) ($requestStudent['houseId'] ?? '') !== (string) $houseId) {
                    $requestStudent = null;
                }
            } catch (Throwable $e) {
                $requestStudent = null;
            }
        }

        if (!$requestStudent) {
            flash('error', $canCreateForStudent ? 'Please select a valid student.' : 'Student profile was not found for this account.');
            redirect(url('views/exeat/index.php'));
        }

        $defaultType = match ($action) {
            'create_internal' => 'internal',
            'create_vacation' => 'vacation',
            default => 'external',
        };
        $exeatType = sanitize($_POST['exeatType'] ?? $defaultType);

        $result = $service->create([
            'studentId' => (string) ($requestStudent['id'] ?? ''),
            'studentName' => trim(($requestStudent['firstName'] ?? '') . ' ' . ($requestStudent['lastName'] ?? '')),
            'houseId' => $requestStudent['houseId'] ?? null,
            'roomId' => $requestStudent['roomId'] ?? null,
            'guardianPhone' => sanitize($_POST['guardianPhone'] ?? $requestStudent['guardianPhone'] ?? ''),
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

        if (!$result['success']) {
            flash('error', $result['message']);
        } elseif ($exeatType !== 'vacation') {
            flash('success', $result['message']);
        }
        
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
                    } elseif ($exeatType === 'vacation') {
                        $smsMessage = $canCreateForStudent
                            ? "Your ward {$studentName} has been approved for vacation from {$startDate} to {$endDate} by the {$roleSender}."
                            : "Your ward {$studentName} has submitted a vacation request from {$startDate} to {$endDate} for review.";
                    } else {
                        $destStr = !empty($destination) ? " to {$destination}" : '';
                        $reasonStr = !empty($reason) ? " for {$reason}" : '';
                        $smsMessage = "Your ward {$studentName} has been approved for external exeat from {$startDate} to {$endDate}{$destStr}{$reasonStr} by the {$roleSender}.";
                    }
                    
                    $phoneSuffix = substr(preg_replace('/\D+/', '', $guardianPhone), -4);
                    $logMsg = "[" . date('H:i:s') . "] [CREATE-INDEX] Sending SMS to phone ending {$phoneSuffix}, length: " . mb_strlen($smsMessage) . "\n";
                    @file_put_contents($logFile, $logMsg, FILE_APPEND);
                    
                    $smsResult = $smsService->send($guardianPhone, $smsMessage);
                    if ($smsResult['success']) {
                        $campaignId = (string) ($smsResult['campaign_id'] ?? '');
                        $logMsg = "[" . date('H:i:s') . "] [CREATE-INDEX] SMS ACCEPTED BY PROVIDER; campaign ID: " . ($campaignId !== '' ? $campaignId : 'not returned') . "; delivery not confirmed\n";
                        @file_put_contents($logFile, $logMsg, FILE_APPEND);
                        if ($exeatType === 'vacation') {
                            $confirmationMessage = $canCreateForStudent
                                ? 'Vacation created successfully. The SMS provider accepted the notification request, but delivery to the parent/guardian is not confirmed.'
                                : 'Vacation request submitted successfully. The SMS provider accepted the notification request, but delivery to the parent/guardian is not confirmed.';
                            if ($campaignId !== '') {
                                $confirmationMessage .= ' SMS campaign ID: ' . $campaignId . '; check its delivery report with BMS Africa.';
                            }
                            flash('info', $confirmationMessage);
                        }
                    } else {
                        $logMsg = "[" . date('H:i:s') . "] [CREATE-INDEX] SMS RESULT UNKNOWN/FAILED: " . ($smsResult['message'] ?? 'Unknown error') . "\n";
                        @file_put_contents($logFile, $logMsg, FILE_APPEND);
                        if ($exeatType === 'vacation') {
                            flash('warning', ($canCreateForStudent
                                ? 'Vacation created successfully'
                                : 'Vacation request submitted successfully')
                                . ', but SMS delivery could not be confirmed: ' . ($smsResult['message'] ?? 'Unknown error') . ' Do not submit again just to retry the SMS; that may create a duplicate vacation record.');
                        }
                    }
                } elseif ($exeatType === 'vacation' && $smsEnabled === '1') {
                    flash('info', 'Vacation created successfully, but no parent/guardian phone number is on file, so no SMS was sent.');
                } elseif ($exeatType === 'vacation') {
                    flash('info', 'Vacation created successfully, but SMS notifications are disabled in app settings, so no message was sent to the parent/guardian.');
                }
            } catch (Throwable $e) {
                $logFile = APP_ROOT . '/storage/logs/sms-exeat-' . date('Y-m-d') . '.log';
                $logMsg = "[" . date('H:i:s') . "] [CREATE-INDEX] ✗ ERROR: " . $e->getMessage() . "\n";
                @file_put_contents($logFile, $logMsg, FILE_APPEND);
                if ($exeatType === 'vacation') {
                    flash('warning', ($canCreateForStudent
                        ? 'Vacation created successfully'
                        : 'Vacation request submitted successfully')
                        . ', but SMS delivery could not be confirmed: ' . $e->getMessage() . ' Do not submit again just to retry the SMS; that may create a duplicate vacation record.');
                }
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

    if ($isStaff && in_array($action, ['reject', 'return'], true)) {
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
        ? StudentService::allRecords($houseId)
        : StudentService::allRecords();

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

$exeatModalStudents = [];
if ($canCreateForStudent) {
    foreach ($studentMap as $id => $student) {
        $exeatModalStudents[$id] = [
            'name' => (string) ($student['name'] ?? ''),
            'phone' => (string) ($student['phone'] ?? ''),
        ];
    }
}

$hideExeatFilters = in_array($role, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT], true);
$statusFilter = $hideExeatFilters ? '' : strtolower(trim(sanitize($_GET['status'] ?? '')));
$typeFilter = $hideExeatFilters ? '' : strtolower(trim(sanitize($_GET['type'] ?? '')));
$fromDate = $hideExeatFilters ? '' : sanitize($_GET['from'] ?? '');
$toDate = $hideExeatFilters ? '' : sanitize($_GET['to'] ?? '');
$searchTerm = $hideExeatFilters ? '' : trim(sanitize($_GET['search'] ?? ''));

if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'approved', 'rejected', 'departed', 'returned'], true)) {
    $records = array_values(array_filter($records, static fn(array $record): bool => strtolower((string) ($record['status'] ?? 'pending')) === $statusFilter));
}
if ($typeFilter !== '' && in_array($typeFilter, ['internal', 'external', 'vacation'], true)) {
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
        $recordType = strtolower((string) ($record['exeatType'] ?? $record['type'] ?? 'external'));
        $recType = match ($recordType) {
            'internal' => 'Internal',
            'vacation' => 'Vacation',
            default => 'External',
        };
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
    overflow-x: auto !important;
    overflow-y: visible;
    min-height: 220px;
    -webkit-overflow-scrolling: touch;
}
.card.stat-card {
    overflow: visible !important;
}
.exeat-table {
    min-width: 1100px;
}
.exeat-table th,
.exeat-table td {
    white-space: nowrap;
}
.exeat-table th:last-child,
.exeat-table td:last-child {
    position: sticky;
    right: 0;
}
.exeat-table th:nth-last-child(2),
.exeat-table td:nth-last-child(2) {
    position: sticky;
    right: 160px;
}
.exeat-table th:last-child {
    z-index: 3;
    background-color: var(--bs-table-bg, #fff);
    box-shadow: -6px 0 8px rgba(15, 23, 42, 0.08);
}
.exeat-table td:last-child {
    z-index: 2;
    background-color: var(--bs-table-bg, #fff);
    box-shadow: -6px 0 8px rgba(15, 23, 42, 0.06);
}
.exeat-table td:last-child:has(.dropdown-menu.show) {
    z-index: 4;
}
.exeat-table th:nth-last-child(2) {
    z-index: 3;
    background-color: var(--bs-table-bg, #fff);
    box-shadow: -6px 0 8px rgba(15, 23, 42, 0.08);
}
.exeat-table td:nth-last-child(2) {
    z-index: 2;
    background-color: var(--bs-table-bg, #fff);
    box-shadow: -6px 0 8px rgba(15, 23, 42, 0.06);
}
.exeat-table td:nth-child(6) {
    white-space: normal;
    min-width: 150px;
}
.exeat-table td {
    padding-top: 0.55rem;
    padding-bottom: 0.55rem;
    vertical-align: middle;
}
.exeat-table .table-cell-stack {
    line-height: 1.2;
}
.dropdown-menu {
    z-index: 1060 !important;
}
.modal-header.bg-danger {
    background: linear-gradient(135deg, #b91c1c, #ef4444) !important;
}
.exeat-action-bar {
    padding: 0.9rem 1rem;
}
.exeat-action-bar .btn {
    min-height: 38px;
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
}
.exeat-filter-row .form-label {
    margin-bottom: 0.35rem;
}
.exeat-filter-row .form-control,
.exeat-filter-row .form-select {
    min-height: 38px;
    height: 38px;
}
.exeat-filter-wrapper {
    padding: 0.9rem 1rem !important;
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
            <div class="card stat-card shadow-sm border-0 exeat-action-bar mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h6 class="mb-1 fw-bold"><i class="bi bi-send-plus me-2 text-primary"></i><?= $canCreateForStudent ? 'Create Exeat for Student' : 'Request Exeat Permission' ?></h6>
                        <p class="text-muted mb-0 small">
                            <?= $canCreateForStudent
                                ? ($role === ROLE_ADMIN ? 'Create an internal pass, external exeat, or vacation for any student across all houses.' : 'Create an internal pass, external exeat, or vacation for dormitory residents.')
                                : 'Choose an internal, external, or vacation request.' ?>
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createExeatModal" data-exeat-type="internal">
                            <i class="bi bi-clock-history me-1"></i>Internal
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createExeatModal" data-exeat-type="external">
                            <i class="bi bi-calendar2-range me-1"></i>External
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#createExeatModal" data-exeat-type="vacation">
                            <i class="bi bi-sun me-1"></i>Vacation
                        </button>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="createExeatModal" tabindex="-1" aria-labelledby="createExeatModalTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                    <form method="POST" action="<?= url('views/exeat/index.php') ?>" class="modal-content" id="createExeatModalForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" id="createExeatAction" value="create_internal">
                        <input type="hidden" name="exeatType" id="createExeatType" value="internal">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createExeatModalTitle">Create Internal Exeat</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                            <div class="modal-body">
                                <?php if ($canCreateForStudent): ?>
                                    <div class="mb-3">
                                        <label for="createExeatStudentSearch" class="form-label fw-semibold">Select Student <span class="text-danger">*</span></label>
                                        <input type="text" id="createExeatStudentSearch" class="form-control" list="createExeatStudentOptions" placeholder="Search by name or admission number..." autocomplete="off" required <?= empty($studentMap) ? 'disabled' : '' ?>>
                                        <datalist id="createExeatStudentOptions">
                                            <?php foreach ($studentMap as $optionStudentId => $student): ?>
                                                <option value="<?= e($student['name'] . ' (' . $student['admissionNo'] . ')') ?>" data-student-id="<?= e((string) $optionStudentId) ?>"></option>
                                            <?php endforeach; ?>
                                        </datalist>
                                        <input type="hidden" name="studentId" id="createExeatStudentId">
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-light border py-2 mb-3">
                                        Requesting for <strong><?= e($studentMap[$studentId]['name'] ?? trim(($studentProfile['firstName'] ?? '') . ' ' . ($studentProfile['lastName'] ?? ''))) ?></strong>
                                    </div>
                                <?php endif; ?>

                                <div id="createExeatInternalFields">
                                    <div class="alert alert-info small py-2">Internal exeat is a same-day pass with a start and return time.</div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="createExeatDate" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                                            <input type="date" name="date" id="createExeatDate" class="form-control" value="<?= e(date('Y-m-d')) ?>" min="<?= e(date('Y-m-d')) ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="createExeatStartTime" class="form-label fw-semibold">Time to Start <span class="text-danger">*</span></label>
                                            <input type="time" name="startTime" id="createExeatStartTime" class="form-control" value="08:00" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="createExeatCloseTime" class="form-label fw-semibold">Time to Close / Return <span class="text-danger">*</span></label>
                                            <input type="time" name="closeTime" id="createExeatCloseTime" class="form-control" value="17:00" required>
                                        </div>
                                    </div>
                                </div>

                                <div id="createExeatDateRangeFields" class="d-none">
                                    <div class="alert alert-primary small py-2" id="createExeatTypeDescription"></div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="createExeatStartDate" class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                                            <input type="date" name="startDate" id="createExeatStartDate" class="form-control" value="<?= e(date('Y-m-d')) ?>" min="<?= e(date('Y-m-d')) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="createExeatEndDate" class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                                            <input type="date" name="endDate" id="createExeatEndDate" class="form-control" value="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" min="<?= e(date('Y-m-d')) ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label for="createExeatDestination" class="form-label fw-semibold">Destination Address / Location</label>
                                    <input type="text" name="destination" id="createExeatDestination" class="form-control" placeholder="Where will the student be going?">
                                </div>
                                <div class="mt-3 d-none" id="createExeatGuardianSection">
                                    <label for="createExeatGuardianPhone" class="form-label fw-semibold">Parent / Guardian Phone Number <span class="text-danger">*</span></label>
                                    <input type="tel" name="guardianPhone" id="createExeatGuardianPhone" class="form-control" value="<?= e($canCreateForStudent ? '' : ($studentProfile['guardianPhone'] ?? '')) ?>" placeholder="e.g. +233 24 000 0000">
                                    <small class="text-muted" id="createExeatGuardianHint">Enter the parent or guardian number for the SMS notification.</small>
                                </div>
                                <div class="mt-3">
                                    <label for="createExeatReason" class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                                    <textarea name="reason" id="createExeatReason" class="form-control" rows="3" required placeholder="Provide a clear explanation for the exeat request..."></textarea>
                                    <small class="text-muted" id="createExeatReasonHint">Provide a clear explanation for the exeat request.</small>
                                </div>
                            </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="createExeatSubmit" <?= ($canCreateForStudent && empty($studentMap)) ? 'disabled' : '' ?>>
                                <i class="bi bi-send me-1"></i><span id="createExeatSubmitLabel">Create Internal Exeat</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$hideExeatFilters): ?>
            <!-- Filter Bar -->
            <div class="card stat-card shadow-sm border-0 mb-4">
                <div class="card-body exeat-filter-wrapper">
                    <form method="GET" class="row g-2 align-items-end exeat-filter-row">
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
                            <option value="vacation" <?= $typeFilter === 'vacation' ? 'selected' : '' ?>>Vacation</option>
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
        <?php endif; ?>

        <!-- Data Table -->
        <div class="card stat-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-journal-text me-2 text-primary"></i>Exeat Applications &amp; Travel Pass Records</h6>
                <div class="d-flex align-items-center gap-2">
                    <?php if (!$hideExeatFilters || in_array($role, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true)): ?>
                        <input type="search" class="form-control form-control-sm" data-ts-table-filter placeholder="Filter shown records..." aria-label="Filter shown records" style="max-width: 220px;">
                    <?php endif; ?>
                    <span class="badge bg-light text-dark border" data-ts-filter-count><?= count($records) ?> shown</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 w-100 exeat-table" data-no-data-table="true" data-ts-filter-table>
                        <thead class="table-light">
                            <tr>
                                <?php if (!$isStudent): ?>
                                    <th style="min-width: 140px;">Student</th>
                                <?php endif; ?>
                                <?php if ($role === ROLE_ADMIN): ?>
                                    <th>House</th>
                                <?php endif; ?>
                                <th style="min-width: 95px;">Type</th>
                                <th style="min-width: 160px;">Schedule &amp; Dates</th>
                                <th>Destination</th>
                                <th>Reason</th>
                                <th style="min-width: 90px;">Status</th>
                                <th style="min-width: 120px;">Reviewed Date</th>
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
                                    $recordType = strtolower((string) ($record['exeatType'] ?? $record['type'] ?? 'external'));
                                    $isInternal = $recordType === 'internal';
                                    $recStudentName = $studentMap[$recordStudentId]['name'] ?? (string) ($record['studentName'] ?? $recordStudentId);
                                    $recStudentAdmissionNo = (string) ($studentMap[$recordStudentId]['admissionNo'] ?? '');
                                    ?>
                                    <tr>
                                        <?php if (!$isStudent): ?>
                                            <td>
                                                <div class="d-flex flex-column align-items-start gap-0 table-cell-stack">
                                                    <strong class="text-dark d-block"><?= e($recStudentName) ?></strong>
                                                    <?php if ($recStudentAdmissionNo !== ''): ?>
                                                        <small class="text-muted d-block">(<?= e($recStudentAdmissionNo) ?>)</small>
                                                    <?php endif; ?>
                                                    <small class="text-muted d-block"><span class="fw-semibold">Parent:</span> <?= e($record['guardianPhone'] ?? $studentMap[$recordStudentId]['phone'] ?? '—') ?></small>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                        <?php if ($role === ROLE_ADMIN): ?>
                                            <td><?= e($houseMap[$recordHouseId] ?? $recordHouseId ?: '-') ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <?php if ($isInternal): ?>
                                                <span class="badge bg-info-subtle text-info border"><i class="bi bi-clock me-1"></i>Internal</span>
                                            <?php elseif ($recordType === 'vacation'): ?>
                                                <span class="badge bg-success-subtle text-success border"><i class="bi bi-sun me-1"></i>Vacation</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary-subtle text-primary border"><i class="bi bi-calendar-range me-1"></i>External</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <?php if ($isInternal): ?>
                                                <div class="d-flex flex-column align-items-start gap-1 table-cell-stack">
                                                    <strong class="text-dark d-block"><?= e($record['startDate'] ?? $record['date'] ?? '-') ?></strong>
                                                    <small class="text-muted d-block">
                                                        <i class="bi bi-clock me-1"></i><?= e($record['startTime'] ?? '--:--') ?> - <?= e($record['closeTime'] ?? $record['endTime'] ?? '--:--') ?>
                                                    </small>
                                                </div>
                                            <?php else: ?>
                                                <div class="d-flex flex-column align-items-start gap-1 table-cell-stack">
                                                    <div class="text-dark d-block"><strong><?= e($record['startDate'] ?? '-') ?></strong></div>
                                                    <small class="text-muted d-block">to <?= e($record['endDate'] ?? '-') ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($record['destination'] ?? '—') ?></td>
                                        <td class="small text-muted" style="max-width: 160px;"><?= e(mb_strimwidth((string)($record['reason'] ?? '—'), 0, 40, '...')) ?></td>
                                        <td><span class="badge bg-<?= e($statusClasses[$recordStatus] ?? 'secondary') ?>"><?= e(ucfirst($recordStatus)) ?></span></td>
                                        <td class="text-nowrap small text-muted">
                                            <?php if (!empty($record['reviewedAt'])): ?>
                                                <?php $reviewDate = strtotime((string) $record['reviewedAt']); ?>
                                                <div class="d-flex flex-column align-items-start gap-1 table-cell-stack">
                                                    <span class="text-dark fw-semibold"><?= date('j', $reviewDate) ?></span>
                                                    <small class="text-muted d-block"><?= date('M, Y', $reviewDate) ?></small>
                                                </div>
                                            <?php else: ?>
                                                <span>—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <!-- A staff-issued exeat starts as departed; keep only its return action here. -->
                                                <?php if ($isStaff && $recordStatus === 'departed'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="recordId" value="<?= e($recId) ?>">
                                                        <input type="hidden" name="action" value="return">
                                                        <button type="submit" class="btn btn-sm btn-secondary" title="Mark as Returned">
                                                            <i class="bi bi-box-arrow-in-left me-1"></i>Return
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- Three Dots (⋮) Action Menu -->
                                                <div class="dropdown">
                                                    <button class="btn btn-xs btn-light border dropdown-toggle no-caret px-2 py-1 lh-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="More Actions" style="min-width: 32px; height: 30px; line-height: 1; display: inline-flex; align-items: center; justify-content: center; margin-top: 1px;">
                                                        <i class="bi bi-three-dots-vertical fs-6"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-2" style="min-width: 180px;">
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
                <div class="exeat-pagination d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 px-3 py-2 border-top d-none"
                     data-ts-pagination
                     data-page-length="<?= e((string) max(1, (int) (app_config()['pagination_per_page'] ?? 25))) ?>">
                    <small class="text-muted" data-ts-page-info></small>
                    <nav aria-label="Exeat table pages">
                        <ul class="pagination pagination-sm mb-0" data-ts-pagination-pages></ul>
                    </nav>
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
                $recordType = strtolower((string) ($record['exeatType'] ?? $record['type'] ?? 'external'));
                $isInternal = $recordType === 'internal';
                $typeLabel = match ($recordType) {
                    'internal' => 'Internal Pass',
                    'vacation' => 'Vacation',
                    default => 'External Pass',
                };
                $typeBadgeClass = $isInternal ? 'info' : ($recordType === 'vacation' ? 'success' : 'primary');
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
                                                <span class="badge bg-<?= e($typeBadgeClass) ?>"><?= e($typeLabel) ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="text-muted small">Requested By:</span>
                                                <span class="badge bg-light text-dark border"><?= e(ucfirst((string)($record['createdByRole'] ?? 'User'))) ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="alert <?= $isInternal ? 'alert-info' : ($recordType === 'vacation' ? 'alert-success' : 'alert-primary') ?> mb-0">
                                            <h6 class="fw-bold mb-2"><i class="bi bi-calendar-event me-2"></i>Schedule &amp; Timings</h6>
                                            <?php if ($isInternal): ?>
                                                <div class="row g-2">
                                                    <div class="col-sm-4"><strong>Date:</strong> <?= e($record['startDate'] ?? $record['date'] ?? '—') ?></div>
                                                    <div class="col-sm-4 text-success"><strong>Start Time:</strong> <?= e($record['startTime'] ?? '—') ?></div>
                                                    <div class="col-sm-4 text-danger"><strong>Close Time:</strong> <?= e($record['closeTime'] ?? $record['endTime'] ?? '—') ?></div>
                                                </div>
                                            <?php else: ?>
                                                <div class="row g-2">
                                                    <div class="col-sm-6"><strong><?= $recordType === 'vacation' ? 'Vacation Start Date' : 'Departure Date' ?>:</strong> <?= e($record['startDate'] ?? '—') ?></div>
                                                    <div class="col-sm-6 text-danger"><strong><?= $recordType === 'vacation' ? 'Vacation End Date' : 'Return Date' ?>:</strong> <?= e($record['endDate'] ?? '—') ?></div>
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
                            <form method="POST" action="<?= url('views/exeat/edit/edit.php?id=' . urlencode($recId)) ?>">
                                <div class="modal-body p-4">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e($recId) ?>">

                                    <?php if ($isStaff): ?>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold small">Status</label>
                                            <select name="status" class="form-select">
                                                <?php foreach (['pending', 'approved', 'rejected', 'departed', 'returned'] as $editStatus): ?>
                                                    <option value="<?= e($editStatus) ?>" <?= $recordStatus === $editStatus ? 'selected' : '' ?>><?= ucfirst($editStatus) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                    <input type="hidden" name="exeatType" id="edit_exeat_type_<?= $idx ?>" value="<?= e($recordType) ?>">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small">Pass Type</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="exeatTypeChoice_<?= $idx ?>" id="edit_internal_<?= $idx ?>" value="internal" <?= $recordType === 'internal' ? 'checked' : '' ?> onchange="toggleEditType('<?= $idx ?>', 'internal')">
                                                <label class="form-check-label" for="edit_internal_<?= $idx ?>">Internal (Hours Pass)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="exeatTypeChoice_<?= $idx ?>" id="edit_external_<?= $idx ?>" value="external" <?= $recordType === 'external' ? 'checked' : '' ?> onchange="toggleEditType('<?= $idx ?>', 'external')">
                                                <label class="form-check-label" for="edit_external_<?= $idx ?>">External (Date Range)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="exeatTypeChoice_<?= $idx ?>" id="edit_vacation_<?= $idx ?>" value="vacation" <?= $recordType === 'vacation' ? 'checked' : '' ?> onchange="toggleEditType('<?= $idx ?>', 'vacation')">
                                                <label class="form-check-label" for="edit_vacation_<?= $idx ?>">Vacation</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="edit_internal_fields_<?= $idx ?>" class="<?= $recordType !== 'internal' ? 'd-none' : '' ?> row g-3">
                                        <div class="col-md-4"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="<?= e($record['startDate'] ?? $record['date'] ?? date('Y-m-d')) ?>"></div>
                                        <div class="col-md-4"><label class="form-label">Start Time</label><input type="time" name="startTime" class="form-control" value="<?= e($record['startTime'] ?? '08:00') ?>"></div>
                                        <div class="col-md-4"><label class="form-label">Close Time</label><input type="time" name="closeTime" class="form-control" value="<?= e($record['closeTime'] ?? $record['endTime'] ?? '17:00') ?>"></div>
                                    </div>
                                    <div id="edit_external_fields_<?= $idx ?>" class="<?= $recordType === 'internal' ? 'd-none' : '' ?> row g-3">
                                        <div class="col-md-6"><label class="form-label">Start Date</label><input type="date" name="startDate" class="form-control" value="<?= e($record['startDate'] ?? date('Y-m-d')) ?>"></div>
                                        <div class="col-md-6"><label class="form-label">End Date</label><input type="date" name="endDate" class="form-control" value="<?= e($record['endDate'] ?? date('Y-m-d', strtotime('+1 day'))) ?>"></div>
                                    </div>
                                    <div class="mt-3"><label class="form-label">Destination</label><input name="destination" class="form-control" value="<?= e($record['destination'] ?? '') ?>"></div>
                                    <div class="mt-3"><label class="form-label">Parent / Guardian Phone</label><input name="guardianPhone" type="tel" class="form-control" value="<?= e($record['guardianPhone'] ?? $studentMap[$recordStudentId]['phone'] ?? '') ?>"></div>
                                    <div class="mt-3"><label class="form-label">Reason</label><textarea name="reason" id="edit_reason_<?= $idx ?>" class="form-control" rows="3" required><?= $recordType === 'vacation' ? 'Vacation' : e($record['reason'] ?? '') ?></textarea><small class="text-muted" id="edit_reason_hint_<?= $idx ?>"></small></div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
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
const exeatModalStudentData = <?= json_encode($exeatModalStudents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const canCreateExeatForStudent = <?= $canCreateForStudent ? 'true' : 'false' ?>;

window.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('createExeatModal');
    const form = document.getElementById('createExeatModalForm');
    if (!modal || !form) return;

    const typeInput = document.getElementById('createExeatType');
    const actionInput = document.getElementById('createExeatAction');
    const title = document.getElementById('createExeatModalTitle');
    const submitLabel = document.getElementById('createExeatSubmitLabel');
    const internalFields = document.getElementById('createExeatInternalFields');
    const dateRangeFields = document.getElementById('createExeatDateRangeFields');
    const guardianSection = document.getElementById('createExeatGuardianSection');
    const guardianPhone = document.getElementById('createExeatGuardianPhone');
    const guardianHint = document.getElementById('createExeatGuardianHint');
    const typeDescription = document.getElementById('createExeatTypeDescription');
    const reason = document.getElementById('createExeatReason');
    const reasonHint = document.getElementById('createExeatReasonHint');
    const studentSearch = document.getElementById('createExeatStudentSearch');
    const studentId = document.getElementById('createExeatStudentId');
    const studentOptions = Array.from(document.querySelectorAll('#createExeatStudentOptions option'));
    const submitButton = document.getElementById('createExeatSubmit');
    const submitText = <?= json_encode($canCreateForStudent ? 'Create' : 'Request') ?>;

    function setType(type) {
        if (!['internal', 'external', 'vacation'].includes(type)) type = 'internal';
        typeInput.value = type;
        actionInput.value = 'create_' + type;

        const isInternal = type === 'internal';
        internalFields.classList.toggle('d-none', !isInternal);
        dateRangeFields.classList.toggle('d-none', isInternal);
        guardianSection.classList.toggle('d-none', isInternal);
        internalFields.querySelectorAll('input').forEach(input => input.required = isInternal);
        dateRangeFields.querySelectorAll('input').forEach(input => input.required = !isInternal);
        guardianPhone.required = !isInternal;

        if (type === 'vacation') {
            if (!reason.readOnly) reason.dataset.previousReason = reason.value;
            reason.value = 'Vacation';
            reason.readOnly = true;
            reasonHint.textContent = 'Vacation reason is fixed.';
            typeDescription.textContent = 'Vacation is a multi-day request with a start date and expected return date.';
        } else {
            if (reason.readOnly) {
                reason.value = reason.dataset.previousReason || '';
                delete reason.dataset.previousReason;
            }
            reason.readOnly = false;
            reasonHint.textContent = 'Provide a clear explanation for the exeat request.';
            typeDescription.textContent = 'External exeat is a multi-day off-campus pass with start and end dates.';
        }

        const typeLabel = type === 'vacation' ? 'Vacation' : (type === 'internal' ? 'Internal Exeat' : 'External Exeat');
        title.textContent = submitText + ' ' + typeLabel;
        submitLabel.textContent = submitText + ' ' + typeLabel;
    }

    function updateSelectedStudent() {
        if (!canCreateExeatForStudent || !studentSearch || !studentId) return;
        const selectedOption = studentOptions.find(option => option.value === studentSearch.value);
        const selectedId = selectedOption ? selectedOption.dataset.studentId : '';
        studentSearch.setCustomValidity(studentSearch.value && !selectedId ? 'Select a student from the list.' : '');

        if (studentId.value !== selectedId) {
            studentId.value = selectedId;
            guardianPhone.value = selectedId ? (exeatModalStudentData[selectedId]?.phone || '') : '';
            guardianHint.textContent = guardianPhone.value
                ? 'Using the selected student\'s registered parent/guardian number. You can edit it if needed.'
                : 'No registered number found. Enter the parent or guardian number for the SMS notification.';
        }
    }

    modal.addEventListener('show.bs.modal', function (event) {
        setType(event.relatedTarget?.dataset.exeatType || 'internal');
    });

    if (studentSearch) {
        studentSearch.addEventListener('input', updateSelectedStudent);
        studentSearch.addEventListener('change', updateSelectedStudent);
    }

    form.addEventListener('submit', function (event) {
        updateSelectedStudent();
        if (canCreateExeatForStudent && !studentId.value) {
            event.preventDefault();
            studentSearch.reportValidity();
            return;
        }

        submitButton.disabled = true;
        submitLabel.textContent = 'Submitting...';
    });
});

function toggleEditType(idx, type) {
    var internalDiv = document.getElementById('edit_internal_fields_' + idx);
    var externalDiv = document.getElementById('edit_external_fields_' + idx);
    var typeInput = document.getElementById('edit_exeat_type_' + idx);
    var reason = document.getElementById('edit_reason_' + idx);
    var reasonHint = document.getElementById('edit_reason_hint_' + idx);
    if (!internalDiv || !externalDiv || !typeInput || !reason || !reasonHint) return;
    var isInternal = type === 'internal';
    var isVacation = type === 'vacation';
    typeInput.value = type;
    internalDiv.classList.toggle('d-none', !isInternal);
    externalDiv.classList.toggle('d-none', isInternal);
    internalDiv.querySelectorAll('input').forEach(function (input) {
        input.disabled = !isInternal;
        input.required = isInternal;
    });
    externalDiv.querySelectorAll('input').forEach(function (input) {
        input.disabled = isInternal;
        input.required = !isInternal;
    });
    if (isVacation) {
        reason.dataset.previousReason = reason.value;
        reason.value = 'Vacation';
        reason.readOnly = true;
        reasonHint.textContent = 'Vacation reason is fixed.';
    } else {
        if (reason.readOnly) reason.value = reason.dataset.previousReason || '';
        reason.readOnly = false;
        reasonHint.textContent = 'Provide a clear explanation for the exeat request.';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[id^="edit_internal_"]').forEach(function (radio) {
        if (radio.checked) toggleEditType(radio.id.replace('edit_internal_', ''), 'internal');
    });
    document.querySelectorAll('[id^="edit_external_"]').forEach(function (radio) {
        if (radio.checked) toggleEditType(radio.id.replace('edit_external_', ''), 'external');
    });
    document.querySelectorAll('[id^="edit_vacation_"]').forEach(function (radio) {
        if (radio.checked) toggleEditType(radio.id.replace('edit_vacation_', ''), 'vacation');
    });
});
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
