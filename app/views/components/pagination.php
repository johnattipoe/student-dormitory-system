<?php
/** Pagination component with prev/next and ellipsis support
 * Params: $page (int), $totalPages (int), $baseUrl (string), $maxVisible (int, default 7)
 * Handles existing query strings and generates ellipsis for large page counts
 */
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$baseUrl = (string) ($baseUrl ?? '');
$maxVisible = max(3, (int) ($maxVisible ?? 7));

if ($totalPages <= 1) {
    return;
}

// Validate parameters
if ($page > $totalPages) $page = $totalPages;
if (empty($baseUrl)) {
    $baseUrl = $_SERVER['REQUEST_URI'];
    $baseUrl = preg_replace('/[?&]page=\d+/', '', $baseUrl);
}

$separator = str_contains($baseUrl, '?') ? '&' : '?';
$pageUrl = fn($p) => e($baseUrl . $separator . 'page=' . $p);

// Calculate visible page range with ellipsis
$pages = [];
if ($totalPages <= $maxVisible) {
    $pages = range(1, $totalPages);
} else {
    $half = intval($maxVisible / 2);
    if ($page <= $half + 1) {
        $pages = range(1, $maxVisible - 1);
        $pages[] = '...';
        $pages[] = $totalPages;
    } elseif ($page >= $totalPages - $half) {
        $pages[] = 1;
        $pages[] = '...';
        $pages = array_merge($pages, range($totalPages - $maxVisible + 2, $totalPages));
    } else {
        $pages[] = 1;
        $pages[] = '...';
        $pages = array_merge($pages, range($page - $half, $page + $half));
        $pages[] = '...';
        $pages[] = $totalPages;
    }
}
?>
<nav aria-label="Page navigation example" class="d-flex justify-content-center">
    <ul class="pagination">
        <!-- Previous -->
        <li class="page-item <?= $page === 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $page > 1 ? $pageUrl($page - 1) : '#' ?>" aria-label="Previous">← Previous</a>
        </li>
        
        <!-- Page numbers -->
        <?php foreach ($pages as $p): ?>
            <?php if ($p === '...'): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php else: ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $pageUrl($p) ?>" <?= $p === $page ? 'aria-current="page"' : '' ?>><?= $p ?></a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <!-- Next -->
        <li class="page-item <?= $page === $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $page < $totalPages ? $pageUrl($page + 1) : '#' ?>" aria-label="Next">Next →</a>
        </li>
    </ul>
</nav>
