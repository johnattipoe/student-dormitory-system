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
$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\IncidentService;
use App\Services\StudentService;

$currentUser = current_user();
$studentId = $currentUser['studentId'] ?? $currentUser['uid'] ?? $currentUser['id'] ?? null;
if (!$studentId) {
    flash('error', 'Student profile not found.');
    redirect(url('views/student/incidents/index/index.php'));
}

$student = StudentService::find((string) $studentId);
if (!$student && !empty($currentUser['email'])) {
    foreach (StudentService::all() as $candidate) {
        if ((string) ($candidate['email'] ?? '') === (string) $currentUser['email']) {
            $student = $candidate;
            $studentId = $candidate['id'];
            break;
        }
    }
}

$returnRoutes = [
    'incidents' => 'views/student/incidents/index/index.php',
    'dashboard' => 'views/student/dashboard/index.php',
    'room' => 'views/student/room/index.php',
    'beds' => 'views/student/beds/index.php',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnTo = sanitize($_POST['returnToStudentPage'] ?? '');
    $returnPage = $returnRoutes[$returnTo] ?? null;
    $studentName = $student ? trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) : ($currentUser['name'] ?? 'Student');
    $adm = !empty($student['admissionNo']) ? ' [' . $student['admissionNo'] . ']' : '';
    
    $result = (new IncidentService())->create([
        'title' => sanitize($_POST['title'] ?? ''),
        'description' => sanitize($_POST['description'] ?? ''),
        'type' => sanitize($_POST['type'] ?? 'other'),
        'priority' => sanitize($_POST['priority'] ?? 'medium'),
        'studentId' => (string) $studentId,
        'reportedBy' => current_user()['uid'] ?? current_user()['id'] ?? (string) $studentId,
        'reportedByName' => $studentName . $adm . ' (Student)',
        'houseId' => (string) ($student['houseId'] ?? ''),
    ]);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    if ($result['success']) {
        redirect(url($returnPage ?? 'views/student/incidents/index/index.php'));
    }
    if ($returnPage !== null) {
        $_SESSION['_student_incident_report_old'] = [
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'type' => $_POST['type'] ?? 'other',
            'priority' => $_POST['priority'] ?? 'medium',
        ];
        $_SESSION['_student_incident_report_errors'] = ['general' => $result['message']];
        redirect(url($returnPage . '?openIncidentReport=1'));
    }
}

$pageTitle = 'Report Incident';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:760px">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Report an Incident</h5>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/student/incidents/index/index.php') ?>">Back</a>
            </div>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Subject / Title <span class="text-danger">*</span></label>
                    <input name="title" class="form-control" placeholder="Brief summary of the issue" value="<?= e($_POST['title'] ?? '') ?>" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <select name="type" class="form-select">
                            <option value="disciplinary">Disciplinary</option>
                            <option value="maintenance">Maintenance / Facility</option>
                            <option value="medical">Medical / Health</option>
                            <option value="theft">Lost / Stolen Item</option>
                            <option value="conflict">Roommate / Peer Conflict</option>
                            <option value="other" selected>Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Urgency / Severity</label>
                        <select name="priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Detailed Description <span class="text-danger">*</span></label>
                    <textarea name="description" class="form-control" rows="5" placeholder="Describe what happened, where, and when..." required><?= e($_POST['description'] ?? '') ?></textarea>
                </div>
                <div class="mt-4">
                    <button class="btn btn-primary"><i class="bi bi-send me-1"></i> Submit Report</button>
                    <a class="btn btn-outline-secondary ms-1" href="<?= url('views/student/incidents/index/index.php') ?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
