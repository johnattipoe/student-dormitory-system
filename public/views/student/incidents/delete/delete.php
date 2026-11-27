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

$currentUser = current_user();
$studentId = $currentUser['studentId'] ?? $currentUser['uid'] ?? $currentUser['id'] ?? null;
$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$service = new IncidentService();
$incident = $service->findForStudent($id, $studentId);
if (!$incident) {
    flash('error', 'Incident not found.');
    redirect(url('views/student/incidents/index/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $service->deleteForStudent($id, $studentId);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    $returnUrl = url('views/student/incidents/index/index.php');
    if (!empty($_POST['returnToIncidentList'])) {
        $returnParams = [
            'search' => sanitize($_POST['returnSearch'] ?? ''),
            'dateFrom' => sanitize($_POST['returnDateFrom'] ?? ''),
            'dateTo' => sanitize($_POST['returnDateTo'] ?? ''),
            'severity' => sanitize($_POST['returnSeverity'] ?? ''),
            'status' => sanitize($_POST['returnStatus'] ?? ''),
            'page' => max(1, (int) ($_POST['returnPage'] ?? 1)),
            'limit' => max(1, min(150, (int) ($_POST['returnLimit'] ?? app_config()['pagination_per_page'] ?? 25))),
        ];
        $returnQuery = http_build_query(array_filter($returnParams, static fn($value): bool => $value !== ''));
        $returnUrl = url('views/student/incidents/index/index.php' . ($returnQuery !== '' ? '?' . $returnQuery : ''));
    }
    redirect($returnUrl);
}

$pageTitle = 'Delete Incident';
$navItems = [['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index/index.php'), 'active' => true]];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:700px">
            <h5 class="mb-3">Delete Incident</h5>
            <p>Delete <strong><?= e($incident['title'] ?? 'this incident') ?></strong>?</p>
            <form method="POST">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <button class="btn btn-danger">Delete incident</button>
                <a class="btn btn-outline-secondary" href="<?= url('views/student/incidents/view/view.php?id=' . urlencode($id)) ?>">Cancel</a>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
