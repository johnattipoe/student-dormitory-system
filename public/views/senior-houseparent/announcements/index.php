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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\UserService;

$role = current_role();
$allRecords = FirebaseService::getInstance()->getCollection('announcements', [], 500);

// Preload user map for author name resolution
$userMap = [
    'default-admin' => 'Administrator (Admin)',
    'system' => 'System',
];
try {
    foreach ((new UserService())->all() as $u) {
        $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
        if ($name === '') $name = $u['displayName'] ?? $u['username'] ?? $u['email'] ?? '';
        if ($name !== '') {
            $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
            $displayName = $name . $roleLabel;
            foreach ([$u['id'] ?? null, $u['uid'] ?? null, $u['userId'] ?? null, $u['firebaseUid'] ?? null] as $key) {
                if ($key !== null && (string)$key !== '') {
                    $userMap[(string)$key] = $displayName;
                }
            }
        }
    }
} catch (\Throwable $e) {}

$getAuthorName = function (?string $raw) use (&$userMap): string {
    $raw = (string) $raw;
    if ($raw === '' || $raw === 'system') return 'System';
    if ($raw === 'default-admin') return 'Administrator (Admin)';
    if (isset($userMap[$raw])) return $userMap[$raw];
    try {
        $u = FirebaseService::getInstance()->getDocument('users', $raw);
        if ($u) {
            $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
            if ($name === '') $name = $u['displayName'] ?? $u['username'] ?? '';
            if ($name !== '') {
                $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
                $userMap[$raw] = $name . $roleLabel;
                return $userMap[$raw];
            }
        }
    } catch (\Throwable $e) {}
    return $raw;
};

// Filter based on audience visibility
$announcements = array_values(array_filter($allRecords, static function (array $item) use ($role): bool {
    $audience = (string) ($item['audience'] ?? 'all');
    $targetRole = (string) ($item['targetRole'] ?? '');
    
    // Senior houseparent can see their own created announcements, public all, role-specific, or senior houseparent target
    if (!empty($item['createdBy']) && $item['createdBy'] === current_user_id()) {
        return true;
    }

    return ($item['status'] ?? 'published') === 'published'
        && ($audience === 'all' || $targetRole === '' || $targetRole === $role || $targetRole === ROLE_HOUSE_MASTER);
}));

usort($announcements, static fn(array $a, array $b): int => strcmp(
    (string) ($b['publishedAt'] ?? $b['createdAt'] ?? ''),
    (string) ($a['publishedAt'] ?? $a['createdAt'] ?? '')
));

// Search & Type Filters
$search = strtolower(sanitize($_GET['search'] ?? ''));
$typeFilter = sanitize($_GET['type'] ?? '');
$audienceFilter = sanitize($_GET['audience'] ?? '');

$filtered = array_values(array_filter($announcements, function ($item) use ($search, $typeFilter, $audienceFilter) {
    if ($typeFilter !== '' && ($item['type'] ?? 'info') !== $typeFilter) {
        return false;
    }
    if ($audienceFilter !== '' && ($item['audience'] ?? 'all') !== $audienceFilter) {
        return false;
    }
    if ($search !== '') {
        $title = strtolower((string) ($item['title'] ?? ''));
        $msg = strtolower((string) ($item['message'] ?? ''));
        if (!str_contains($title, $search) && !str_contains($msg, $search)) {
            return false;
        }
    }
    return true;
}));

$publishedCount = count(array_filter($announcements, fn($a) => ($a['status'] ?? 'published') === 'published'));
$urgentCount = count(array_filter($announcements, fn($a) => !empty($a['isUrgent'])));

$pageTitle = 'Announcements';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php')],
    ['icon' => 'bi-megaphone', 'label' => 'Announcements', 'href' => url('views/senior-houseparent/announcements/index.php'), 'active' => true],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/senior-houseparent/reports/index/index.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">House Announcements & Bulletins</h5>
                <p class="text-muted mb-0">Broadcast house notices, curfew updates, and emergency bulletins.</p>
            </div>
            <a class="btn btn-primary btn-sm" href="<?= url('views/senior-houseparent/announcements/create/create.php') ?>">
                <i class="bi bi-plus-circle me-1"></i> New Announcement
            </a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-8">
                <div class="card stat-card p-4 h-100 border-start border-4 border-primary">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-uppercase text-primary small fw-bold">Latest Bulletin</span>
                        <?php if (!empty($announcements[0]['isUrgent'])): ?>
                            <span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Urgent</span>
                        <?php endif; ?>
                    </div>
                    <h6 class="mb-2 fw-bold"><?= e($announcements[0]['title'] ?? 'No announcements posted yet') ?></h6>
                    <p class="text-muted mb-2"><?= e(mb_strimwidth((string)($announcements[0]['message'] ?? 'Published bulletins will appear here.'), 0, 160, '...')) ?></p>
                    <?php if (!empty($announcements[0]['id'])): ?>
                        <a href="<?= url('views/senior-houseparent/announcements/view/view.php?id=' . urlencode((string)$announcements[0]['id'])) ?>" class="small fw-semibold text-decoration-none">Read full bulletin &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Total Notices</small>
                    <strong class="fs-2 text-primary mt-2"><?= e((string) count($announcements)) ?></strong>
                    <span class="small text-muted mt-1"><?= e((string) $publishedCount) ?> active</span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Urgent Notices</small>
                    <strong class="fs-2 text-danger mt-2"><?= e((string) $urgentCount) ?></strong>
                    <span class="small text-muted mt-1">High priority</span>
                </div>
            </div>
        </div>

        <div class="card stat-card p-3 mb-3">
            <form method="GET" class="row g-2">
                <div class="col-md-6">
                    <input name="search" class="form-control form-control-sm" placeholder="Search by title or content..." value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <option value="info" <?= $typeFilter === 'info' ? 'selected' : '' ?>>General Info</option>
                        <option value="warning" <?= $typeFilter === 'warning' ? 'selected' : '' ?>>Warning / Inspection</option>
                        <option value="danger" <?= $typeFilter === 'danger' ? 'selected' : '' ?>>Urgent / Emergency</option>
                        <option value="success" <?= $typeFilter === 'success' ? 'selected' : '' ?>>Success / Commendation</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-1">
                    <button class="btn btn-primary btn-sm flex-fill">Filter</button>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/announcements/index.php') ?>">Reset</a>
                </div>
            </form>
        </div>

        <div class="card stat-card p-3">
            <div class="table-responsive">
                <table class="table table-hover data-table w-100">
                    <thead>
                        <tr>
                            <th>Title & Subject</th>
                            <th>Category</th>
                            <th>Target Audience</th>
                            <th>Posted By</th>
                            <th>Date Posted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($filtered)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No announcements found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($filtered as $item): ?>
                                <?php 
                                    $type = strtolower((string) ($item['type'] ?? 'info'));
                                    $typeBadge = match($type) {
                                        'danger' => 'danger',
                                        'warning' => 'warning',
                                        'success' => 'success',
                                        default => 'info'
                                    };
                                    $audience = (string) ($item['audience'] ?? 'all');
                                    $author = $getAuthorName($item['createdBy'] ?? '');
                                    $dateStr = substr((string) ($item['publishedAt'] ?? $item['createdAt'] ?? '—'), 0, 16);
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <strong><?= e($item['title'] ?? 'Announcement') ?></strong>
                                            <?php if (!empty($item['isUrgent'])): ?>
                                                <span class="badge bg-danger">Urgent</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-muted"><?= e(mb_strimwidth((string)($item['message'] ?? ''), 0, 70, '...')) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= e($typeBadge) ?>">
                                            <?= e(ucfirst($type)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary border">
                                            <?= e($audience === 'all' ? 'All Dormitory' : ucfirst(str_replace('_', ' ', (string)($item['targetRole'] ?? $audience)))) ?>
                                        </span>
                                    </td>
                                    <td><?= e($author) ?></td>
                                    <td class="text-nowrap small text-muted"><?= e($dateStr) ?></td>
                                    <td class="text-nowrap">
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/senior-houseparent/announcements/view/view.php?id=' . urlencode((string) ($item['id'] ?? ''))) ?>" title="View Bulletin"><i class="bi bi-eye"></i></a>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('views/senior-houseparent/announcements/edit/edit.php?id=' . urlencode((string) ($item['id'] ?? ''))) ?>" title="Edit Bulletin"><i class="bi bi-pencil"></i></a>
                                        <a class="btn btn-sm btn-outline-danger" href="<?= url('views/senior-houseparent/announcements/delete/delete.php?id=' . urlencode((string) ($item['id'] ?? ''))) ?>" title="Delete Bulletin"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
