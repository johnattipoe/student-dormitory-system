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
$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\VisitorService;

$visitors = (new VisitorService())->all();
$search = strtolower(sanitize($_GET['search'] ?? ''));
$statusFilter = sanitize($_GET['status'] ?? '');
$visitors = array_values(array_filter($visitors, function ($visitor) use ($search, $statusFilter) {
    return ($search === '' || str_contains(strtolower((string) ($visitor['visitorName'] ?? '')), $search) || str_contains(strtolower((string) ($visitor['studentId'] ?? '')), $search))
        && ($statusFilter === '' || ($visitor['status'] ?? '') === $statusFilter);
}));
$pageTitle = 'Security Visitors';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors.php'), 'active' => true],
    ['icon' => 'bi-journal-text', 'label' => 'Visitor History', 'href' => url('views/security/visitor-history/visitor-history.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/security/notifications/notifications.php')],
    ['icon' => 'bi-person-plus', 'label' => 'Register Visitor', 'href' => url('views/security/register-visitor/register-visitor.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Visitors</h5>
            <div><a href="<?= url('views/security/visitor-history/visitor-history.php') ?>" class="btn btn-outline-primary btn-sm">History</a> <a href="<?= url('views/security/register-visitor/register-visitor.php') ?>" class="btn btn-primary btn-sm">Register Visitor</a></div>
        </div>
        <div class="card stat-card p-3 mb-3"><form method="GET" class="row g-2"><div class="col-md-7"><input name="search" class="form-control form-control-sm" placeholder="Search visitor or student" value="<?= e($search) ?>"></div><div class="col-md-3"><select name="status" class="form-select form-select-sm"><option value="">All statuses</option><option value="inside" <?= $statusFilter === 'inside' ? 'selected' : '' ?>>Inside</option><option value="checked_out" <?= $statusFilter === 'checked_out' ? 'selected' : '' ?>>Checked out</option><option value="registered" <?= $statusFilter === 'registered' ? 'selected' : '' ?>>Registered</option></select></div><div class="col-md-2"><button class="btn btn-primary btn-sm">Filter</button></div></form></div>
        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Student</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($visitors)): ?>
                        <?php foreach ($visitors as $visitor): ?>
                            <tr>
                                <td><?= e($visitor['visitorName'] ?? '') ?></td>
                                <td><?= e($visitor['studentId'] ?? '—') ?></td>
                                <td><?= e($visitor['purpose'] ?? '—') ?></td>
                                <td><span class="badge bg-<?= ($visitor['status'] ?? '') === 'inside' ? 'success' : 'secondary' ?>"><?= e($visitor['status'] ?? 'pending') ?></span></td>
                                <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= url('views/security/visitors/view.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>">View</a> <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/security/visitors/edit.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="<?= url('views/security/visitors/delete.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No visitors found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
