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

use App\Services\FirebaseService;

$user = current_user() ?? [];
$houseId = (string) ($user['houseId'] ?? $user['house_id'] ?? '');

$firebase = FirebaseService::getInstance();
$allAnnouncements = $firebase->getCollection('announcements', [], 500);

$announcements = array_values(array_filter($allAnnouncements, function ($ann) use ($houseId) {
    if (($ann['status'] ?? 'published') !== 'published') return false;
    $aud = (string) ($ann['audience'] ?? 'all');
    $targetRole = (string) ($ann['targetRole'] ?? '');
    $targetHouse = (string) ($ann['targetHouseId'] ?? '');
    if ($aud === 'all') return true;
    if ($aud === 'role' && ($targetRole === ROLE_STUDENT || $targetRole === 'student')) return true;
    if ($aud === 'house' && $targetHouse !== '' && $targetHouse === $houseId) return true;
    return false;
}));

usort($announcements, function ($a, $b) {
    $urgentA = !empty($a['isUrgent']) ? 1 : 0;
    $urgentB = !empty($b['isUrgent']) ? 1 : 0;
    if ($urgentA !== $urgentB) return $urgentB <=> $urgentA;
    $tA = strtotime((string) ($a['publishedAt'] ?? $a['createdAt'] ?? ''));
    $tB = strtotime((string) ($b['publishedAt'] ?? $b['createdAt'] ?? ''));
    return $tB <=> $tA;
});

$search = strtolower(sanitize($_GET['search'] ?? ''));
$typeFilter = sanitize($_GET['type'] ?? '');

$filtered = array_values(array_filter($announcements, function ($ann) use ($search, $typeFilter) {
    if ($typeFilter !== '' && ($ann['type'] ?? 'info') !== $typeFilter) return false;
    if ($search !== '') {
        $title = strtolower((string) ($ann['title'] ?? ''));
        $msg = strtolower((string) ($ann['message'] ?? ''));
        return str_contains($title, $search) || str_contains($msg, $search);
    }
    return true;
}));

$urgentCount = count(array_filter($announcements, fn($a) => !empty($a['isUrgent'])));

$pageTitle = 'Campus & Dormitory Announcements';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-megaphone', 'label' => 'Announcements', 'href' => url('views/student/announcements/index.php'), 'active' => true],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index/index.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-megaphone-fill text-primary me-2"></i>Notice Board</h4>
                <p class="text-muted mb-0">Official updates, inspection notices, curfew reminders, and health advisories</p>
            </div>
            <span class="badge bg-primary fs-6"><?= count($announcements) ?> Active Notices</span>
        </div>

        <!-- KPI Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Notices</span>
                            <h3 class="fw-bold my-1 text-primary"><?= count($announcements) ?></h3>
                            <span class="small text-muted">Published</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-megaphone fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Urgent</span>
                            <h3 class="fw-bold my-1 text-danger"><?= $urgentCount ?></h3>
                            <span class="small text-muted">Needs attention</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-exclamation-triangle fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Filtered</span>
                            <h3 class="fw-bold my-1 text-success"><?= count($filtered) ?></h3>
                            <span class="small text-muted">Matching results</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-funnel fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-7">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input name="search" class="form-control form-control-sm" placeholder="Search notices..." value="<?= e($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="type" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                            <option value="danger" <?= $typeFilter === 'danger' ? 'selected' : '' ?>>Urgent Notices</option>
                            <option value="warning" <?= $typeFilter === 'warning' ? 'selected' : '' ?>>Advisories</option>
                            <option value="info" <?= $typeFilter === 'info' ? 'selected' : '' ?>>General Info</option>
                            <option value="success" <?= $typeFilter === 'success' ? 'selected' : '' ?>>Success</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/student/announcements/index.php') ?>"><i class="bi bi-x-lg"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Announcements Feed -->
        <?php if (empty($filtered)): ?>
            <div class="card stat-card shadow-sm border-0 p-5 text-center text-muted">
                <i class="bi bi-megaphone fs-1 text-secondary mb-2"></i>
                <h6>No announcements found.</h6>
                <p class="small mb-0">Check back later for new dormitory notices and bulletins.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($filtered as $ann): ?>
                    <?php 
                        $type = $ann['type'] ?? 'info';
                        $borderClass = match($type) {
                            'danger' => 'border-danger',
                            'warning' => 'border-warning',
                            'success' => 'border-success',
                            default => 'border-primary',
                        };
                        $badgeClass = match($type) {
                            'danger' => 'bg-danger',
                            'warning' => 'bg-warning text-dark',
                            'success' => 'bg-success',
                            default => 'bg-primary',
                        };
                        $author = (string) ($ann['createdByName'] ?? 'Dormitory Staff');
                        if ($author === 'default-admin') $author = 'Administrator (Admin)';
                        $annId = (string) ($ann['id'] ?? '');
                        $rawDate = (string) ($ann['publishedAt'] ?? $ann['createdAt'] ?? '');
                        $formattedDate = $rawDate !== '' ? (date('M d, Y h:i A', strtotime($rawDate)) ?: $rawDate) : 'Recently';
                    ?>
                    <div class="col-12">
                        <div class="card stat-card shadow-sm p-4 border-start border-4 <?= $borderClass ?> border-0">
                            <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge <?= $badgeClass ?>"><?= ucfirst(e($type)) ?></span>
                                        <?php if (!empty($ann['isUrgent'])): ?>
                                            <span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Urgent</span>
                                        <?php endif; ?>
                                        <span class="small text-muted"><i class="bi bi-clock me-1"></i><?= e($formattedDate) ?></span>
                                    </div>
                                    <h5 class="fw-bold mb-1"><?= e($ann['title'] ?? 'Notice') ?></h5>
                                    <div class="small text-muted mb-3">
                                        <i class="bi bi-person me-1"></i>Posted by: <strong><?= e($author) ?></strong>
                                    </div>
                                </div>
                                <?php if ($annId !== ''): ?>
                                    <a class="btn btn-outline-primary btn-sm" href="<?= url('views/student/announcements/view/view.php?id=' . urlencode($annId)) ?>">
                                        Read Full Notice <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <p class="text-muted mb-0" style="line-height: 1.6;">
                                <?= e(mb_strimwidth((string)($ann['message'] ?? $ann['content'] ?? ''), 0, 220, '...')) ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
