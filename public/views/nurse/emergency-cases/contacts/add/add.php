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

$allowedRoles = [ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\EmergencyContactService;

$pageTitle = 'Add Emergency Contact';
$contactService = new EmergencyContactService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $relationship = trim((string) ($_POST['relationship'] ?? ''));
    $role = trim((string) ($_POST['role'] ?? ''));

    $result = $contactService->create([
        'name' => $name,
        'phone' => $phone,
        'relationship' => $relationship,
        'role' => $role,
        'createdBy' => current_user_id(),
    ]);
    flash($result['success'] ? 'success' : 'error', $result['message']);

    redirect(url('views/nurse/emergency-cases/contacts/add/add.php'));
}

$contacts = $contactService->all();

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Students', 'href' => url('views/nurse/students/students.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => url('views/nurse/emergency-cases/emergency-cases.php'), 'active' => true],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/nurse/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-person-plus-fill text-success me-2"></i>Add Emergency Contact</h4>
                <p class="text-muted mb-0">Keep parent and support contact details ready for urgent response.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/nurse/emergency-cases/emergency-cases.php') ?>">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>

        <div class="card stat-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill me-2 text-success"></i>Contact details</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact name</label>
                            <input type="text" name="name" class="form-control" placeholder="Jane Doe" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone number</label>
                            <input type="tel" name="phone" class="form-control" placeholder="+233 24 000 0000" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Relationship</label>
                            <input type="text" name="relationship" class="form-control" placeholder="Mother / Guardian / Sponsor">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role</label>
                            <input type="text" name="role" class="form-control" placeholder="Emergency contact / support">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save me-1"></i>Save Contact
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2 text-primary"></i>Saved contacts</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Phone</th>
                                <th>Relationship</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($contacts)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No emergency contacts saved yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($contacts as $contact): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= e((string) ($contact['name'] ?? '')) ?></td>
                                        <td><?= e((string) ($contact['role'] ?? 'Support')) ?></td>
                                        <td><?= e((string) ($contact['phone'] ?? '')) ?></td>
                                        <td><?= e((string) ($contact['relationship'] ?? 'Emergency contact')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
