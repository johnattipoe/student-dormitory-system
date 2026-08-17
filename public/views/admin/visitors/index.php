<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\StudentService;
use App\Services\VisitorService;

$visitorService = new VisitorService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'register';

    if ($action === 'check_in') {
        $result = $visitorService->checkIn(sanitize($_POST['id'] ?? ''), current_user()['uid'] ?? 'admin');
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('index.php?route=/views/admin/visitors/index.php'));
    }

    if ($action === 'check_out') {
        $result = $visitorService->checkOut(sanitize($_POST['id'] ?? ''), current_user()['uid'] ?? 'admin');
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('index.php?route=/views/admin/visitors/index.php'));
    }

    $data = [
        'visitorName' => sanitize($_POST['visitorName'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'studentId' => sanitize($_POST['studentId'] ?? ''),
        'purpose' => sanitize($_POST['purpose'] ?? ''),
        'idType' => sanitize($_POST['idType'] ?? ''),
        'idNumber' => sanitize($_POST['idNumber'] ?? ''),
        'registeredBy' => current_user()['uid'] ?? 'admin',
    ];

    $result = $visitorService->register($data);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(base_url('index.php?route=/views/admin/visitors/index.php'));
}

$pageTitle = 'Visitors';
$visitors = $visitorService->all();
$students = StudentService::all();
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-person-badge', 'label' => 'Visitors', 'href' => url('views/admin/visitors/index.php'), 'active' => true],
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
            <a href="<?= url('views/admin/visitors/reports.php') ?>" class="btn btn-sm btn-outline-primary">Reports</a>
        </div>

        <div class="card stat-card p-4 mb-4">
            <h6 class="mb-3">Register visitor</h6>
            <form method="POST" action="<?= url('views/admin/visitors/index.php') ?>" class="row g-3">
                <input type="hidden" name="action" value="register">
                <div class="col-md-4">
                    <label class="form-label">Visitor name</label>
                    <input name="visitorName" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input name="phone" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Student</label>
                    <select name="studentId" class="form-select" required>
                        <option value="">Select student</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= e((string) ($student['id'] ?? '')) ?>"><?= e(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' (' . ($student['admissionNo'] ?? '') . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Purpose</label>
                    <input name="purpose" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">ID type</label>
                    <input name="idType" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">ID number</label>
                    <input name="idNumber" class="form-control">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary">Register visitor</button>
                </div>
            </form>
        </div>

        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Student</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($visitors as $visitor): ?>
                    <?php
                    $studentName = '-';
                    foreach ($students as $student) {
                        if (($student['id'] ?? '') === ($visitor['studentId'] ?? '')) {
                            $studentName = (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: ($visitor['studentId'] ?? '-');
                            break;
                        }
                    }
                    ?>
                    <tr>
                        <td><?= e($visitor['visitorName'] ?? '') ?></td>
                        <td><?= e($studentName) ?></td>
                        <td><?= e($visitor['purpose'] ?? '') ?></td>
                        <td><span class="badge bg-<?= ($visitor['status'] ?? '') === 'inside' ? 'success' : 'secondary' ?>"><?= e($visitor['status'] ?? 'registered') ?></span></td>
                        <td>
                            <?php if (($visitor['status'] ?? '') === 'inside'): ?>
                                <form method="POST" action="<?= url('views/admin/visitors/index.php') ?>" class="d-inline">
                                    <input type="hidden" name="action" value="check_out">
                                    <input type="hidden" name="id" value="<?= e((string) ($visitor['id'] ?? '')) ?>">
                                    <button class="btn btn-sm btn-outline-secondary">Check out</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="<?= url('views/admin/visitors/index.php') ?>" class="d-inline">
                                    <input type="hidden" name="action" value="check_in">
                                    <input type="hidden" name="id" value="<?= e((string) ($visitor['id'] ?? '')) ?>">
                                    <button class="btn btn-sm btn-primary">Check in</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>