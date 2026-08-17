<?php
/**
 * Expects $navItems = [['icon' => 'bi-house', 'label' => 'Dashboard', 'href' => '...', 'active' => true], ...]
 * set by the calling view before including this file.
 */
$navItems = $navItems ?? [];
?>
<aside class="sidebar">
    <div class="sidebar-inner d-flex flex-column h-100">
        <div class="sidebar-brand d-flex align-items-center gap-2 px-3 py-3">
            <img src="<?= asset('images/logo.svg') ?>" alt="SDS logo" width="32" height="32" onerror="this.remove()">
            <span class="fw-bold sidebar-brand-text">SDS</span>
        </div>

        <?php
        $groups = [
            'Overview' => ['Dashboard'],
            'Management' => ['Users', 'Students', 'Houses', 'Rooms', 'Attendance', 'Visitors', 'Incidents'],
            'Administration' => ['Reports', 'Notifications', 'Activity Logs', 'Settings'],
        ];

        $groupedNavItems = [
            'Overview' => [],
            'Management' => [],
            'Administration' => [],
            'Other' => [],
        ];

        foreach ($navItems as $item) {
            $section = 'Other';
            foreach ($groups as $title => $labels) {
                if (in_array($item['label'], $labels, true)) {
                    $section = $title;
                    break;
                }
            }
            $groupedNavItems[$section][] = $item;
        }
        ?>

        <nav class="nav flex-column px-2 sidebar-nav flex-grow-1">
            <?php foreach ($groupedNavItems as $section => $items): ?>
                <?php if (empty($items)) continue; ?>
                <div class="sidebar-section">
                    <div class="sidebar-section-title"><?= e($section) ?></div>
                    <?php foreach ($items as $item): ?>
                        <a class="nav-link sidebar-link <?= !empty($item['active']) ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
                            <i class="bi <?= e($item['icon']) ?>"></i>
                            <span><?= e($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer px-3 py-3 mt-auto">
            <a class="btn btn-outline-light w-100" href="<?= url('logout.php') ?>">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</aside>
