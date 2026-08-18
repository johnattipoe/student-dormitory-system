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

$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\FirebaseService;

// Handle batch submission
$errors = [];
$successCount = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    
    if ($action === 'submit_batch_requests') {
        $visitors = $_POST['visitors'] ?? [];
        $studentId = current_user()['uid'];
        
        if (empty($visitors)) {
            $errors[] = 'Please add at least one visitor request';
        } else {
            try {
                $firebaseService = FirebaseService::getInstance();
                
                foreach ($visitors as $visitor) {
                    $visitorName = sanitize($visitor['visitorName'] ?? '');
                    $relationship = sanitize($visitor['relationship'] ?? '');
                    $visitDate = sanitize($visitor['visitDate'] ?? '');
                    $purpose = sanitize($visitor['purpose'] ?? '');
                    
                    if (!empty($visitorName) && !empty($visitDate)) {
                        $firebaseService->addDocument(COL_VISITOR_REQUESTS, [
                            'studentId' => $studentId,
                            'visitorName' => $visitorName,
                            'relationship' => $relationship,
                            'visitDate' => $visitDate,
                            'purpose' => $purpose,
                            'status' => 'pending',
                            'createdAt' => date('Y-m-d H:i:s'),
                        ]);
                        $successCount++;
                    }
                }
                
                if ($successCount > 0) {
                    flash('success', "Submitted $successCount visitor request(s)");
                    redirect(url('views/student/visitors/requests.php'));
                } else {
                    $errors[] = 'No valid requests to submit';
                }
            } catch (Exception $e) {
                $errors[] = 'Failed to submit requests: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Batch Visitor Requests';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index.php')],
    ['icon' => 'bi-plus-circle', 'label' => 'Batch Requests', 'href' => url('views/student/visitors/batch-requests.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width: 900px;">
            <h5 class="mb-3">Submit Multiple Visitor Requests at Once</h5>
            <p class="text-muted small mb-4">Submit multiple visitor requests in a single form. All requests will be sent for approval.</p>

            <form method="POST" id="batchForm">
                <input type="hidden" name="action" value="submit_batch_requests">

                <div id="visitorsContainer">
                    <!-- Visitor entries will be added here -->
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-auto">
                        <button type="button" class="btn btn-secondary btn-sm" id="addVisitorBtn">
                            <i class="bi bi-plus-lg"></i> Add Visitor
                        </button>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-send"></i> Submit All Requests
                        </button>
                    </div>
                </div>

                <div class="alert alert-info small" id="summaryAlert" style="display: none;">
                    <strong>Ready to submit:</strong> <span id="visitorCount">0</span> visitor request(s)
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let visitorCount = 0;
    const minDate = new Date().toISOString().split('T')[0];

    function addVisitorForm() {
        const container = document.getElementById('visitorsContainer');
        visitorCount++;
        
        const html = `
            <div class="card stat-card p-3 mb-3" id="visitor-${visitorCount}">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Visitor #${visitorCount}</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVisitor(${visitorCount})">
                        <i class="bi bi-trash"></i> Remove
                    </button>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Visitor Name *</label>
                        <input type="text" name="visitors[${visitorCount}][visitorName]" class="form-control form-control-sm" 
                               placeholder="Full name" required onchange="updateSummary()">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small">Relationship *</label>
                        <select name="visitors[${visitorCount}][relationship]" class="form-select form-select-sm" onchange="updateSummary()">
                            <option value="">Select relationship</option>
                            <option value="parent">Parent/Guardian</option>
                            <option value="sibling">Sibling</option>
                            <option value="friend">Friend</option>
                            <option value="relative">Relative</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small">Visit Date *</label>
                        <input type="date" name="visitors[${visitorCount}][visitDate]" class="form-control form-control-sm" 
                               min="${minDate}" required onchange="updateSummary()">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small">Purpose (Optional)</label>
                        <input type="text" name="visitors[${visitorCount}][purpose]" class="form-control form-control-sm" 
                               placeholder="Reason for visit">
                    </div>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', html);
        updateSummary();
    }

    function removeVisitor(id) {
        const element = document.getElementById(`visitor-${id}`);
        if (element) {
            element.remove();
            updateSummary();
        }
    }

    function updateSummary() {
        const container = document.getElementById('visitorsContainer');
        const count = container.querySelectorAll('.card').length;
        document.getElementById('visitorCount').textContent = count;
        document.getElementById('summaryAlert').style.display = count > 0 ? 'block' : 'none';
    }

    // Add first visitor form on load
    document.addEventListener('DOMContentLoaded', function() {
        addVisitorForm();
    });

    document.getElementById('addVisitorBtn').addEventListener('click', addVisitorForm);

    // Form validation
    document.getElementById('batchForm').addEventListener('submit', function(e) {
        const visitors = document.querySelectorAll('#visitorsContainer .card');
        if (visitors.length === 0) {
            e.preventDefault();
            alert('Please add at least one visitor');
        }
    });
</script>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
