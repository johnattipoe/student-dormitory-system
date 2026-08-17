<?php
/** Expects $page (int), $totalPages (int), $baseUrl (string, optional existing query string allowed) */
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$baseUrl = (string) ($baseUrl ?? '');

if ($totalPages <= 1) {
    return;
}

$separator = str_contains($baseUrl, '?') ? '&' : '?';
?>
<nav aria-label="Page navigation">
    <ul class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= e($baseUrl . $separator . 'page=' . $i) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
