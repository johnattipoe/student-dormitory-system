<?php
// Ensure bootstrap is loaded
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
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\VisitorService;
use App\Services\FirebaseService;
use App\Services\IncidentService;

// Handle bulk operations
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $visitorIds = (array) ($_POST['visitorIds'] ?? []);
    $knownVisitorIds = array_map(fn($visitor) => (string) ($visitor['id'] ?? ''), (new VisitorService())->all());
    $visitorIds = array_values(array_intersect(array_map('strval', $visitorIds), $knownVisitorIds));
    
    if ($action === 'bulk_check_in' && !empty($visitorIds)) {
        try {
            $firebaseService = FirebaseService::getInstance();
            foreach ($visitorIds as $vId) {
                $firebaseService->updateDocument(COL_VISITORS, $vId, [
                    'status' => 'inside',
                    'checkInTime' => date('Y-m-d H:i:s'),
                    'checkedInBy' => current_user()['uid'],
                ]);
            }
            flash('success', 'Checked in ' . count($visitorIds) . ' visitor(s)');
            redirect(url('views/security/visitors/bulk-management/bulk-management.php'));
        } catch (Exception $e) {
            $errors[] = 'Failed: ' . $e->getMessage();
        }
    }
    
    elseif ($action === 'bulk_check_out' && !empty($visitorIds)) {
        try {
            $firebaseService = FirebaseService::getInstance();
            foreach ($visitorIds as $vId) {
                $firebaseService->updateDocument(COL_VISITORS, $vId, [
                    'status' => 'checked_out',
                    'checkOutTime' => date('Y-m-d H:i:s'),
                    'checkedOutBy' => current_user()['uid'],
                ]);
            }
            flash('success', 'Checked out ' . count($visitorIds) . ' visitor(s)');
            redirect(url('views/security/visitors/bulk-management/bulk-management.php'));
        } catch (Exception $e) {
            $errors[] = 'Failed: ' . $e->getMessage();
        }
    }
    
    elseif ($action === 'bulk_reject' && !empty($visitorIds)) {
        try {
            $firebaseService = FirebaseService::getInstance();
            $reason = sanitize($_POST['reject_reason'] ?? 'Rejected by security');
            
            foreach ($visitorIds as $vId) {
                $firebaseService->updateDocument(COL_VISITORS, $vId, [
                    'status' => 'rejected',
                    'rejectionReason' => $reason,
                    'rejectedAt' => date('Y-m-d H:i:s'),
                    'rejectedBy' => current_user()['uid'],
                ]);
            }
            flash('success', 'Rejected ' . count($visitorIds) . ' visitor request(s)');
            redirect(url('views/security/visitors/bulk-management/bulk-management.php'));
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$visitorService = new VisitorService();
$allVisitors = $visitorService->all();
$pendingVisitors = array_filter($allVisitors, fn($v) => ($v['status'] ?? '') === 'pending');
$insideVisitors = array_filter($allVisitors, fn($v) => ($v['status'] ?? '') === 'inside');

$pageTitle = 'Bulk Visitor Management';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors/visitors.php')],
    ['icon' => 'bi-arrow-repeat', 'label' => 'Bulk Management', 'href' => url('views/security/visitors/bulk-management/bulk-management.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper security-portal">
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-mode" type="button">
                    <i class="bi bi-hourglass-split"></i> Process Pending (<?= count($pendingVisitors) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="inside-tab" data-bs-toggle="tab" data-bs-target="#inside-mode" type="button">
                    <i class="bi bi-box-arrow-right"></i> Check Out (<?= count($insideVisitors) ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Pending Visitors Tab -->
            <div class="tab-pane fade show active" id="pending-mode">
                <div class="card stat-card p-3">
                    <h5 class="mb-3">Process Pending Visitor Requests</h5>

                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <strong id="selectedCountPending">0</strong> visitor(s) selected
                            <a href="#" id="selectAllPendingLink" class="ms-2 small">Select all</a> | 
                            <a href="#" id="clearAllPendingLink" class="ms-2 small">Clear all</a>
                        </div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-hover data-table w-100">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="selectAllCheckboxPending" class="form-check-input">
                                    </th>
                                    <th>Visitor Name</th>
                                    <th>Relationship</th>
                                    <th>Student</th>
                                    <th>Visit Date</th>
                                    <th>Purpose</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($pendingVisitors)): ?>
                                    <?php foreach ($pendingVisitors as $visitor): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input visitor-checkbox-pending" 
                                                       data-visitor-id="<?= e((string) ($visitor['id'] ?? '')) ?>">
                                            </td>
                                            <td><?= e($visitor['visitorName'] ?? '') ?></td>
                                            <td><?= e($visitor['relationship'] ?? '—') ?></td>
                                            <td><?= e($visitor['studentId'] ?? '—') ?></td>
                                            <td><?= e($visitor['visitDate'] ?? '') ?></td>
                                            <td><?= e(substr($visitor['purpose'] ?? '', 0, 30)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No pending visitor requests.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <form method="POST" id="pendingForm">
                        <input type="hidden" name="action" value="bulk_check_in">
                        
                        <div class="row g-2">
                            <div class="col-auto">
                                <button type="submit" class="btn btn-success" id="checkInBtn" disabled formaction="?">
                                    <i class="bi bi-check-circle"></i> Check In
                                </button>
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-danger" id="rejectBtn" disabled data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="bi bi-x-circle"></i> Reject
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Inside Visitors Tab -->
            <div class="tab-pane fade" id="inside-mode">
                <div class="card stat-card p-3">
                    <h5 class="mb-3">Check Out Visitors</h5>

                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <strong id="selectedCountInside">0</strong> visitor(s) selected
                            <a href="#" id="selectAllInsideLink" class="ms-2 small">Select all</a> | 
                            <a href="#" id="clearAllInsideLink" class="ms-2 small">Clear all</a>
                        </div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-hover data-table w-100">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="selectAllCheckboxInside" class="form-check-input">
                                    </th>
                                    <th>Visitor Name</th>
                                    <th>Student</th>
                                    <th>Check In Time</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($insideVisitors)): ?>
                                    <?php foreach ($insideVisitors as $visitor): ?>
                                        <?php
                                        $checkInTime = strtotime($visitor['checkInTime'] ?? '');
                                        $duration = $checkInTime ? round((time() - $checkInTime) / 3600) : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input visitor-checkbox-inside" 
                                                       data-visitor-id="<?= e((string) ($visitor['id'] ?? '')) ?>">
                                            </td>
                                            <td><?= e($visitor['visitorName'] ?? '') ?></td>
                                            <td><?= e($visitor['studentId'] ?? '—') ?></td>
                                            <td><?= e($visitor['checkInTime'] ?? '') ?></td>
                                            <td><?= e($duration) ?> hours</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No visitors currently inside.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <form method="POST" id="insideForm">
                        <input type="hidden" name="action" value="bulk_check_out">
                        
                        <button type="submit" class="btn btn-primary" id="checkOutBtn" disabled>
                            <i class="bi bi-box-arrow-right"></i> Check Out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Visitor Requests</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="rejectForm">
                <input type="hidden" name="action" value="bulk_reject">
                <div class="modal-body">
                    <label class="form-label">Rejection Reason</label>
                    <textarea name="reject_reason" class="form-control" rows="3" placeholder="Enter reason for rejection..." required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Pending visitors
    const selectedPending = new Set();
    const selectedInside = new Set();

    document.getElementById('selectAllCheckboxPending')?.addEventListener('change', function() {
        document.querySelectorAll('.visitor-checkbox-pending').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updatePendingSelection();
    });

    document.querySelectorAll('.visitor-checkbox-pending').forEach(checkbox => {
        checkbox.addEventListener('change', updatePendingSelection);
    });

    document.getElementById('selectAllPendingLink')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.visitor-checkbox-pending').forEach(checkbox => {
            checkbox.checked = true;
        });
        updatePendingSelection();
    });

    document.getElementById('clearAllPendingLink')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.visitor-checkbox-pending').forEach(checkbox => {
            checkbox.checked = false;
        });
        updatePendingSelection();
    });

    function updatePendingSelection() {
        selectedPending.clear();
        const pendingForm = document.getElementById('pendingForm');
        const rejectForm = document.getElementById('rejectForm');
        let html = '';

        document.querySelectorAll('.visitor-checkbox-pending:checked').forEach(checkbox => {
            selectedPending.add(checkbox.getAttribute('data-visitor-id'));
            html += '<input type="hidden" name="visitorIds[]" value="' + checkbox.getAttribute('data-visitor-id') + '">';
        });

        pendingForm.querySelectorAll('input[name="visitorIds[]"]').forEach(input => input.remove());
        rejectForm.querySelectorAll('input[name="visitorIds[]"]').forEach(input => input.remove());
        pendingForm.insertAdjacentHTML('afterbegin', html);
        rejectForm.insertAdjacentHTML('afterbegin', html);

        document.getElementById('selectedCountPending').textContent = selectedPending.size;
        document.getElementById('checkInBtn').disabled = selectedPending.size === 0;
        document.getElementById('rejectBtn').disabled = selectedPending.size === 0;
    }

    // Inside visitors
    document.getElementById('selectAllCheckboxInside')?.addEventListener('change', function() {
        document.querySelectorAll('.visitor-checkbox-inside').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateInsideSelection();
    });

    document.querySelectorAll('.visitor-checkbox-inside').forEach(checkbox => {
        checkbox.addEventListener('change', updateInsideSelection);
    });

    document.getElementById('selectAllInsideLink')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.visitor-checkbox-inside').forEach(checkbox => {
            checkbox.checked = true;
        });
        updateInsideSelection();
    });

    document.getElementById('clearAllInsideLink')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.visitor-checkbox-inside').forEach(checkbox => {
            checkbox.checked = false;
        });
        updateInsideSelection();
    });

    function updateInsideSelection() {
        selectedInside.clear();
        const form = document.getElementById('insideForm');
        let html = '';

        document.querySelectorAll('.visitor-checkbox-inside:checked').forEach(checkbox => {
            selectedInside.add(checkbox.getAttribute('data-visitor-id'));
            html += '<input type="hidden" name="visitorIds[]" value="' + checkbox.getAttribute('data-visitor-id') + '">';
        });

        form.querySelectorAll('input[name="visitorIds[]"]').forEach(input => input.remove());
        form.insertAdjacentHTML('afterbegin', html);

        document.getElementById('selectedCountInside').textContent = selectedInside.size;
        document.getElementById('checkOutBtn').disabled = selectedInside.size === 0;
    }

    updatePendingSelection();
    updateInsideSelection();
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
