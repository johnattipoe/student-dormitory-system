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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\FirebaseService;

// Handle bulk cancellation
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $requestIds = (array) ($_POST['requestIds'] ?? []);
    
    if ($action === 'bulk_cancel' && !empty($requestIds)) {
        try {
            $firebaseService = FirebaseService::getInstance();
            foreach ($requestIds as $rId) {
                $firebaseService->updateDocument(COL_VISITOR_REQUESTS, $rId, [
                    'status' => 'cancelled',
                    'cancelledAt' => date('Y-m-d H:i:s'),
                    'cancelledBy' => current_user()['uid'],
                ]);
            }
            flash('success', 'Cancelled ' . count($requestIds) . ' visitor request(s)');
            redirect(url('views/student/visitors/requests.php'));
        } catch (Exception $e) {
            $errors[] = 'Failed to cancel requests: ' . $e->getMessage();
        }
    }
}

$studentId = current_user()['studentId'] ?? current_user()['uid'] ?? null;
$firebaseService = FirebaseService::getInstance();
$allRequests = $firebaseService->getCollection(COL_VISITOR_REQUESTS) ?? [];
$visitorRequests = $studentId ? array_values(array_filter($allRequests, fn($r) => ((string) ($r['studentId'] ?? '')) === ((string) $studentId))) : [];
$pendingRequests = array_filter($visitorRequests, fn($r) => ($r['status'] ?? 'pending') === 'pending');
$approvedRequests = array_filter($visitorRequests, fn($r) => ($r['status'] ?? '') === 'approved');
$rejectedRequests = array_filter($visitorRequests, fn($r) => ($r['status'] ?? '') === 'rejected');
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index.php'), 'active' => true],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/student/notifications/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index.php')],
    ['icon' => 'bi-house-door', 'label' => 'Room', 'href' => url('views/student/room/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/student/settings/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-0">Visitor Requests</h5>
                <small class="text-muted">
                    Pending: <strong><?= count($pendingRequests) ?></strong> | 
                    Approved: <strong><?= count($approvedRequests) ?></strong> | 
                    Rejected: <strong><?= count($rejectedRequests) ?></strong>
                </small>
            </div>
            <a href="<?= url('views/student/visitors/index.php') ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-plus-lg"></i> New Request
            </a>
        </div>

        <div class="card stat-card p-3">
            <div class="mb-3 d-flex justify-content-between align-items-center">
                <div>
                    <strong id="selectedCount">0</strong> request(s) selected
                    <a href="#" id="selectAllLink" class="ms-2 small">Select all</a> | 
                    <a href="#" id="clearAllLink" class="ms-2 small">Clear all</a>
                </div>
                <button class="btn btn-danger btn-sm" id="cancelSelectedBtn" data-bs-toggle="modal" data-bs-target="#cancelModal" disabled>
                    <i class="bi bi-trash"></i> Cancel Selected
                </button>
            </div>

            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="selectAllCheckbox" class="form-check-input">
                        </th>
                        <th>Name</th>
                        <th>Relationship</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($visitorRequests)): ?>
                        <?php foreach ($visitorRequests as $request): ?>
                            <tr>
                                <td>
                                    <?php if (($request['status'] ?? 'pending') === 'pending'): ?>
                                        <input type="checkbox" class="form-check-input request-checkbox" data-request-id="<?= e((string) ($request['id'] ?? '')) ?>">
                                    <?php else: ?>
                                        <input type="checkbox" class="form-check-input request-checkbox" data-request-id="<?= e((string) ($request['id'] ?? '')) ?>" disabled>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($request['visitorName'] ?? '') ?></td>
                                <td><?= e($request['relationship'] ?? '—') ?></td>
                                <td><?= e($request['visitDate'] ?? '') ?></td>
                                <td>
                                    <?php if (($request['status'] ?? 'pending') === 'approved'): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php elseif (($request['status'] ?? 'pending') === 'rejected'): ?>
                                        <span class="badge bg-danger">Rejected</span>
                                    <?php elseif (($request['status'] ?? 'pending') === 'cancelled'): ?>
                                        <span class="badge bg-secondary">Cancelled</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No visitor requests yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bulk Cancel Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Visitor Requests</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="bulk_cancel">
                        <p class="text-muted">Are you sure you want to cancel <strong id="confirmCount">0</strong> visitor request(s)?</p>
                        <div class="alert alert-info small">
                            <i class="bi bi-info-circle"></i> Cancelled requests can be resubmitted later.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">No, Keep Them</button>
                        <button type="submit" class="btn btn-danger">Yes, Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const selectedRequests = new Set();

        // Select all checkbox
        document.getElementById('selectAllCheckbox')?.addEventListener('change', function() {
            document.querySelectorAll('.request-checkbox:not(:disabled)').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelection();
        });

        // Individual checkboxes
        document.querySelectorAll('.request-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelection);
        });

        // Select all link
        document.getElementById('selectAllLink')?.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.request-checkbox:not(:disabled)').forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelection();
        });

        // Clear all link
        document.getElementById('clearAllLink')?.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.request-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelection();
        });

        function updateSelection() {
            selectedRequests.clear();
            const form = document.querySelector('#cancelModal form');
            let html = '';

            document.querySelectorAll('.request-checkbox:checked').forEach(checkbox => {
                selectedRequests.add(checkbox.getAttribute('data-request-id'));
                html += '<input type="hidden" name="requestIds[]" value="' + checkbox.getAttribute('data-request-id') + '">';
            });

            form.querySelectorAll('input[name="requestIds[]"]').forEach(input => input.remove());
            form.insertAdjacentHTML('afterbegin', html);

            document.getElementById('selectedCount').textContent = selectedRequests.size;
            document.getElementById('confirmCount').textContent = selectedRequests.size;
            document.getElementById('cancelSelectedBtn').disabled = selectedRequests.size === 0;
        }

        updateSelection();
    </script>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
