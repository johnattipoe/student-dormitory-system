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
use App\Services\IncidentService;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_incident') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'The form expired. Please refresh the page and try again.');
        redirect(url('views/incidents/index/index.php'));
    }

    $result = (new IncidentService())->create([
        'title' => sanitize($_POST['title'] ?? ''),
        'description' => sanitize($_POST['description'] ?? ''),
        'studentId' => sanitize($_POST['studentId'] ?? ''),
        'priority' => in_array($_POST['priority'] ?? '', ['low', 'medium', 'high', 'critical'], true)
            ? $_POST['priority']
            : 'medium',
        'status' => 'open',
        'reportedBy' => current_user()['uid'] ?? null,
        'reportedAt' => date('c'),
    ]);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(url('views/incidents/index/index.php'));
}

$pageTitle = 'Incidents';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
$firebase = FirebaseService::getInstance();
$totalIncidents = $firebase->count(COL_INCIDENTS);
$totalPages = max(1, (int) ceil($totalIncidents / $limit));
$page = min($page, $totalPages);
$incidents = $firebase->getCollection(
    COL_INCIDENTS,
    [],
    $limit,
    ($page - 1) * $limit,
    false,
    [['createdAt', 'DESC']]
);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/incidents/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Incidents</h5>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reportIncidentModal">Report Incident</button>
        </div>

        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                <tr>
                    <th>Title</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Student</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($incidents as $incident): ?>
                    <?php
                        $incidentId = (string) ($incident['id'] ?? '');
                        if ($incidentId === '') {
                            $incidentId = (string) (($incident['studentId'] ?? '') . '-' . ($incident['title'] ?? ''));
                        }
                    ?>
                    <tr>
                        <td><?= e($incident['title'] ?? '') ?></td>
                        <td><?= e($incident['priority'] ?? '-') ?></td>
                        <td><span class="badge bg-<?= ($incident['status'] ?? '') === 'open' ? 'danger' : 'success' ?>"><?= e($incident['status'] ?? '-') ?></span></td>
                        <td><?= e($incident['studentId'] ?? '-') ?></td>
                        <td class="text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#incidentViewModal-<?= e($incidentId) ?>"><i class="bi bi-eye"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php $paginationBaseUrl = url('views/incidents/index/index.php'); require APP_ROOT . '/app/views/components/pagination/pagination.php'; ?>
        </div>
    </div>
</div>
<div class="modal fade" id="reportIncidentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <form method="POST" action="<?= url('views/incidents/index/index.php') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_incident">
            <div class="modal-header"><h5 class="modal-title">Report Incident</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label">Title</label><input name="title" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4" required></textarea></div>
                    <div class="col-md-6"><label class="form-label">Priority</label><select name="priority" class="form-select"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="critical">Critical</option></select></div>
                    <div class="col-md-6"><label class="form-label">Related Student ID</label><input name="studentId" class="form-control" placeholder="Optional"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Incident</button></div>
        </form>
    </div></div>
</div>
<?php foreach ($incidents as $incident): ?>
    <?php
        $incidentId = (string) ($incident['id'] ?? '');
        if ($incidentId === '') {
            $incidentId = (string) (($incident['studentId'] ?? '') . '-' . ($incident['title'] ?? ''));
        }
    ?>
    <div class="modal fade" id="incidentViewModal-<?= e($incidentId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Incident Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Title</dt>
                        <dd class="col-sm-8"><?= e($incident['title'] ?? '') ?></dd>
                        <dt class="col-sm-4">Priority</dt>
                        <dd class="col-sm-8"><?= e($incident['priority'] ?? '-') ?></dd>
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8"><span class="badge bg-<?= ($incident['status'] ?? '') === 'open' ? 'danger' : 'success' ?>"><?= e($incident['status'] ?? '-') ?></span></dd>
                        <dt class="col-sm-4">Student</dt>
                        <dd class="col-sm-8"><?= e($incident['studentId'] ?? '-') ?></dd>
                        <dt class="col-sm-4">Description</dt>
                        <dd class="col-sm-8"><?= nl2br(e($incident['description'] ?? '')) ?></dd>
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
