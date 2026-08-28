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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS]; 
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php'; 
 
use App\Services\FirebaseService; 
use App\Services\UserService;

$role = current_role(); 
$records = FirebaseService::getInstance()->getCollection('announcements', [], 500); 

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

$getAuthorName = function (array $announcement) use (&$userMap): string {
    $authorName = (string) ($announcement['createdByName'] ?? '');
    if ($authorName !== '' && $authorName !== 'default-admin' && !str_starts_with($authorName, 'Staff/User')) {
        return $authorName;
    }
    $raw = (string) ($announcement['createdBy'] ?? '');
    if ($raw === '' || $raw === 'system') return 'System';
    if ($raw === 'default-admin') return 'Administrator (Admin)';
    if (isset($userMap[$raw])) return $userMap[$raw];

    try {
        $u = FirebaseService::getInstance()->getDocument('users', $raw);
        if ($u) {
            $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
            if ($name !== '') {
                $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
                $userMap[$raw] = $name . $roleLabel;
                return $userMap[$raw];
            }
        }
    } catch (\Throwable $e) {}

    return $raw;
};

$announcements = array_values(array_filter($records, static function (array $item) use ($role): bool { 
    if (!empty($item['createdBy']) && $item['createdBy'] === current_user_id()) {
        return true;
    }
    $audience = (string) ($item['audience'] ?? 'all'); 
    $targetRole = (string) ($item['targetRole'] ?? ''); 
    return ($item['status'] ?? 'published') === 'published' 
        && ($audience === 'all' || $targetRole === '' || $targetRole === $role); 
})); 

usort( 
    $announcements, 
    static fn(array $a, array $b): int => strcmp( 
        (string) ($b['publishedAt'] ?? $b['createdAt'] ?? ''), 
        (string) ($a['publishedAt'] ?? $a['createdAt'] ?? '') 
    ) 
); 

$displayDate = static function (?string $value): string { 
    if (!$value) { 
        return 'Not recorded'; 
    } 
    $timestamp = strtotime($value); 
    return $timestamp ? date('M d, Y H:i', $timestamp) : $value; 
}; 

$latestAnnouncement = $announcements[0] ?? null;
$urgentCount = count(array_filter($announcements, static fn(array $announcement): bool => !empty($announcement['isUrgent'])));

$pageTitle = 'Announcements'; 
$navItems = [ 
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')], 
    ['icon' => 'bi-megaphone', 'label' => 'Announcements', 'href' => url('views/house-master/announcements/index.php'), 'active' => true], 
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-megaphone-fill text-primary me-2"></i>Announcements & Bulletins</h4>
                <p class="text-muted mb-0">Published notices and official communications for your house</p>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <span class="badge bg-primary"><i class="bi bi-megaphone me-1"></i><?= e((string) count($announcements)) ?> notices</span>
                <a href="<?= url('views/house-master/announcements/create/create.php') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-send me-1"></i>Post Announcement
                </a>
            </div>
        </div>

        <!-- KPI Row -->
        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Latest Notice</span>
                            <h5 class="mt-2 mb-2 fw-bold"><?= e($latestAnnouncement['title'] ?? 'No announcements yet') ?></h5>
                            <?php if ($latestAnnouncement): ?>
                                <p class="text-muted mb-2"><?= e((string) ($latestAnnouncement['message'] ?? '')) ?></p>
                                <div class="small text-secondary">
                                    <span><i class="bi bi-person me-1"></i>Posted by: <strong><?= e($getAuthorName($latestAnnouncement)) ?></strong></span>
                                    <span class="mx-2">•</span>
                                    <span><i class="bi bi-clock me-1"></i><?= e($displayDate($latestAnnouncement['publishedAt'] ?? $latestAnnouncement['createdAt'] ?? null)) ?></span>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">Published notices for your dormitory role will appear here.</p>
                            <?php endif; ?>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-megaphone fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">All Notices</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) count($announcements)) ?></h3>
                            <span class="small text-muted">Published</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-collection fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Urgent</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $urgentCount) ?></h3>
                            <span class="small text-muted">Needs attention</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-exclamation-triangle fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Announcements Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2"></i>All Announcements</h6>
                <small class="text-muted">Showing <?= e((string) count($announcements)) ?> records</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 data-table w-100"> 
                        <thead class="table-light"> 
                            <tr> 
                                <th>Title & Subject</th> 
                                <th>Category</th>
                                <th>Target Audience</th>
                                <th>Posted By</th> 
                                <th>Date Posted</th> 
                                <th class="text-end">Actions</th>
                            </tr> 
                        </thead> 
                        <tbody> 
                            <?php if (!$announcements): ?> 
                                <tr> 
                                    <td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No announcements available.</td> 
                                </tr> 
                            <?php else: ?> 
                                <?php foreach ($announcements as $announcement): ?> 
                                    <?php 
                                        $type = strtolower((string) ($announcement['type'] ?? 'info'));
                                        $typeBadge = match($type) {
                                            'danger' => 'danger',
                                            'warning' => 'warning',
                                            'success' => 'success',
                                            default => 'info'
                                        };
                                        $audience = (string) ($announcement['audience'] ?? 'all');
                                        $author = $getAuthorName($announcement);
                                        $annId = (string) ($announcement['id'] ?? '');
                                    ?>
                                    <tr> 
                                        <td> 
                                            <strong><?= e($announcement['title'] ?? 'Untitled') ?></strong> 
                                            <?php if (!empty($announcement['isUrgent'])): ?> 
                                                <span class="badge bg-danger ms-2">Urgent</span> 
                                            <?php endif; ?> 
                                            <div class="small text-muted"><?= e(mb_strimwidth((string)($announcement['message'] ?? ''), 0, 70, '...')) ?></div>
                                        </td> 
                                        <td>
                                            <span class="badge bg-<?= e($typeBadge) ?>">
                                                <?= e(ucfirst($type)) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary border">
                                                <?= e($audience === 'all' ? 'All Dormitory' : ucfirst(str_replace('_', ' ', (string)($announcement['targetRole'] ?? $audience)))) ?>
                                            </span>
                                        </td>
                                        <td class="fw-medium"><?= e($author) ?></td>
                                        <td class="text-nowrap small text-muted"><?= e($displayDate($announcement['publishedAt'] ?? $announcement['createdAt'] ?? null)) ?></td> 
                                        <td class="text-end text-nowrap">
                                            <?php if ($annId !== ''): ?>
                                                <a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/announcements/view/view.php?id=' . urlencode($annId)) ?>" title="View"><i class="bi bi-eye"></i></a>
                                                <a class="btn btn-sm btn-outline-warning" href="<?= url('views/house-master/announcements/edit/edit.php?id=' . urlencode($annId)) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                                                <a class="btn btn-sm btn-outline-danger" href="<?= url('views/house-master/announcements/delete/delete.php?id=' . urlencode($annId)) ?>" title="Delete"><i class="bi bi-trash"></i></a>
                                            <?php endif; ?>
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
</div> 
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
