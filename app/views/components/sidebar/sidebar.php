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
            <img src="<?= asset('images/mawuli-school-logo.png') ?>" alt="Mawuli School crest" width="42" height="42" class="rounded bg-white p-1" style="object-fit: contain" onerror="this.remove()">
            <span class="fw-bold sidebar-brand-text">STUDENT DORMITORY SYSTEM</span>
        </div>

        <?php
        $groups = [
            'Overview' => ['Dashboard'],
            'Management' => ['Users', 'Students', 'Classes', 'Class', 'Houses', 'Rooms', 'Beds', 'Attendance', 'Visitors', 'Incidents', 'Medical Records', 'Create Record', 'Emergency Cases', 'Medical Incidents', 'Exeat', 'Health Reports'],
            'Communication' => ['Notifications', 'Announcements', 'Message Parents', 'Visitor Requests', 'Emergency Alerts', 'Emergency Contacts'],
            'Administration' => ['Reports', 'Health Reports', 'Activity Logs', 'Audit Trail', 'Backup & Restore', 'Settings', 'Profile'],
        ];

        $groupedNavItems = [
            'Overview' => [],
            'Management' => [],
            'Communication' => [],
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

        $role = current_role();
        $bedRoutes = [
            ROLE_ADMIN => 'views/admin/beds/index/index.php',
            ROLE_HOUSE_MASTER => 'views/house-master/beds/index/index.php',
            ROLE_HOUSE_MISTRESS => 'views/house-master/beds/index/index.php',
            ROLE_SENIOR_HOUSEPARENT => 'views/senior-houseparent/beds/index/index.php',
            ROLE_STUDENT => 'views/student/beds/index.php',
        ];
        if (isset($bedRoutes[$role]) && !array_filter($navItems, static fn ($item) => ($item['label'] ?? '') === 'Beds')) {
            $groupedNavItems['Management'][] = [
                'icon' => 'bi-grid-3x3-gap',
                'label' => 'Beds',
                'href' => url($bedRoutes[$role]),
            ];
        }

        if (in_array($role, [ROLE_ADMIN, ROLE_HOUSE_MASTER], true)
            && !array_filter($navItems, static fn ($item) => in_array(($item['label'] ?? ''), ['Class', 'Classes'], true))) {
            $groupedNavItems['Management'][] = [
                'icon' => 'bi-layers',
                'label' => 'Classes',
                'href' => url('views/classes/index.php'),
                'active' => str_contains(str_replace('\\', '/', $_GET['route'] ?? $_SERVER['SCRIPT_NAME'] ?? ''), '/views/classes/'),
            ];
        }

        if ($role === ROLE_ADMIN) {
            $currentScript = str_replace('\\', '/', $_GET['route'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
            $adminFeatureItems = [
                [
                    'icon' => 'bi-heart-pulse',
                    'label' => 'Medical Records',
                    'href' => url('views/medical/index/index.php'),
                    'section' => 'Management',
                    'activePath' => '/views/medical/',
                ],
                [
                    'icon' => 'bi-megaphone',
                    'label' => 'Announcements',
                    'href' => url('views/admin/announcements/index.php'),
                    'section' => 'Communication',
                    'activePath' => '/views/admin/announcements/',
                ],
                [
                    'icon' => 'bi-telephone-inbound',
                    'label' => 'Emergency Contacts',
                    'href' => url('views/admin/emergency-contacts/index.php'),
                    'section' => 'Communication',
                    'activePath' => '/views/admin/emergency-contacts/',
                ],
                [
                    'icon' => 'bi-database-down',
                    'label' => 'Backup & Restore',
                    'href' => url('views/admin/backup-restore/index.php'),
                    'section' => 'Administration',
                    'activePath' => '/views/admin/backup-restore/',
                ],
            ];

            foreach ($adminFeatureItems as $featureItem) {
                if (array_filter($navItems, static fn ($item) => ($item['label'] ?? '') === $featureItem['label'])) {
                    continue;
                }

                $section = $featureItem['section'];
                $groupedNavItems[$section][] = [
                    'icon' => $featureItem['icon'],
                    'label' => $featureItem['label'],
                    'href' => $featureItem['href'],
                    'active' => str_contains($currentScript, $featureItem['activePath']),
                ];
            }
        }

        if (in_array($role, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true)) {
            $houseMasterFeatureItems = [
                ['icon' => 'bi-heart-pulse', 'label' => 'Health Reports', 'href' => 'views/house-master/health-reports/index.php', 'section' => 'Management'],
                ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => 'views/house-master/notifications/index/index.php', 'section' => 'Communication'],
                ['icon' => 'bi-chat-left-text', 'label' => 'Message Parents', 'href' => 'views/parent-messages/create/create.php', 'section' => 'Communication'],
                ['icon' => 'bi-megaphone', 'label' => 'Announcements', 'href' => 'views/house-master/announcements/index.php', 'section' => 'Communication'],
                ['icon' => 'bi-telephone-inbound', 'label' => 'Emergency Alerts', 'href' => 'views/house-master/emergency-alerts/index.php', 'section' => 'Communication'],
                ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => 'views/house-master/reports/index/index.php', 'section' => 'Administration'],
                ['icon' => 'bi-clock-history', 'label' => 'Activity Logs', 'href' => 'views/house-master/activity-logs/index.php', 'section' => 'Administration'],
                ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => 'views/house-master/settings/index.php', 'section' => 'Administration'],
                ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => 'views/house-master/profile.php', 'section' => 'Administration'],
            ];

            $currentScript = str_replace('\\', '/', $_GET['route'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
            foreach ($houseMasterFeatureItems as $featureItem) {
                if (array_filter($navItems, static fn ($item) => ($item['label'] ?? '') === $featureItem['label'])) {
                    continue;
                }

                $section = $featureItem['section'] ?? 'Administration';
                $groupedNavItems[$section][] = [
                    'icon' => $featureItem['icon'],
                    'label' => $featureItem['label'],
                    'href' => url($featureItem['href']),
                    'active' => str_contains($currentScript, '/' . trim($featureItem['href'], '/')),
                ];
            }
        }

        if ($role === ROLE_SENIOR_HOUSEPARENT) {
            $seniorHouseparentFeatureItems = [
                ['icon' => 'bi-heart-pulse', 'label' => 'Medical Records', 'href' => 'views/senior-houseparent/medical/index/index.php', 'section' => 'Management'],
                ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => 'views/senior-houseparent/notifications/index/index.php', 'section' => 'Communication'],
                ['icon' => 'bi-chat-left-text', 'label' => 'Message Parents', 'href' => 'views/parent-messages/create/create.php', 'section' => 'Communication'],
                ['icon' => 'bi-megaphone', 'label' => 'Announcements', 'href' => 'views/senior-houseparent/announcements/index.php', 'section' => 'Communication'],
                ['icon' => 'bi-person-check', 'label' => 'Visitor Requests', 'href' => 'views/senior-houseparent/visitors/requests/requests.php', 'section' => 'Communication'],
                ['icon' => 'bi-telephone-inbound', 'label' => 'Emergency Alerts', 'href' => 'views/senior-houseparent/emergency-alerts/index.php', 'section' => 'Communication'],
                ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => 'views/senior-houseparent/reports/index/index.php', 'section' => 'Administration'],
                ['icon' => 'bi-clock-history', 'label' => 'Activity Logs', 'href' => 'views/senior-houseparent/activity-logs/index.php', 'section' => 'Administration'],
                ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => 'views/senior-houseparent/settings/index.php', 'section' => 'Administration'],
                ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => 'views/senior-houseparent/profile.php', 'section' => 'Administration'],
            ];

            $currentScript = str_replace('\\', '/', $_GET['route'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
            foreach ($seniorHouseparentFeatureItems as $featureItem) {
                if (array_filter($navItems, static fn ($item) => ($item['label'] ?? '') === $featureItem['label'])) {
                    continue;
                }

                $section = $featureItem['section'] ?? 'Administration';
                $groupedNavItems[$section][] = [
                    'icon' => $featureItem['icon'],
                    'label' => $featureItem['label'],
                    'href' => url($featureItem['href']),
                    'active' => str_contains($currentScript, '/' . trim($featureItem['href'], '/')),
                ];
            }
        }

        if ($role === ROLE_NURSE) {
            $nurseFeatureItems = [
                ['icon' => 'bi-people', 'label' => 'Students', 'href' => 'views/nurse/students/students.php', 'section' => 'Management'],
                ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => 'views/nurse/medical-records/medical-records.php', 'section' => 'Management'],
                ['icon' => 'bi-plus-circle', 'label' => 'Create Record', 'href' => 'views/nurse/create-record/create-record.php', 'section' => 'Management'],
                ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => 'views/nurse/emergency-cases/emergency-cases.php', 'section' => 'Management'],
                ['icon' => 'bi-clipboard2-pulse', 'label' => 'Medical Incidents', 'href' => 'views/nurse/medical-incidents/medical-incidents.php', 'section' => 'Management'],
                ['icon' => 'bi-file-earmark-medical', 'label' => 'Health Reports', 'href' => 'views/nurse/health-reports/health-reports.php', 'section' => 'Administration'],
                ['icon' => 'bi-clock-history', 'label' => 'Audit Trail', 'href' => 'views/nurse/activity-logs/index.php', 'section' => 'Administration'],
                ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => 'views/nurse/notifications/notifications.php', 'section' => 'Administration'],
                ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => 'views/nurse/settings/index.php', 'section' => 'Administration'],
                ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => 'views/nurse/profile.php', 'section' => 'Administration'],
            ];

            $currentScript = str_replace('\\', '/', $_GET['route'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
            foreach ($nurseFeatureItems as $featureItem) {
                if (array_filter($navItems, static fn ($item) => ($item['label'] ?? '') === $featureItem['label'])) {
                    continue;
                }

                $groupedNavItems[$featureItem['section']][] = [
                    'icon' => $featureItem['icon'],
                    'label' => $featureItem['label'],
                    'href' => url($featureItem['href']),
                    'active' => str_contains($currentScript, '/' . trim($featureItem['href'], '/')),
                ];
            }
        }

        if ($role === ROLE_SECURITY) {
            $securityFeatureItems = [
                ['icon' => 'bi-person-check', 'label' => 'Visitors', 'href' => 'views/security/visitors/visitors/visitors.php', 'section' => 'Management'],
                ['icon' => 'bi-box-arrow-in-right', 'label' => 'Check-In', 'href' => 'views/security/visitor-check-in/visitor-check-in.php', 'section' => 'Management'],
                ['icon' => 'bi-box-arrow-right', 'label' => 'Check-Out', 'href' => 'views/security/visitor-check-out/visitor-check-out.php', 'section' => 'Management'],
                ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => 'views/security/incidents/incidents/incidents.php', 'section' => 'Management'],
                ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => 'views/security/notifications/notifications.php', 'section' => 'Communication'],
                ['icon' => 'bi-telephone-inbound', 'label' => 'Emergency Alerts', 'href' => 'views/security/emergency-alerts/index.php', 'section' => 'Communication'],
                ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => 'views/security/reports/index.php', 'section' => 'Administration'],
                ['icon' => 'bi-clock-history', 'label' => 'Activity Logs', 'href' => 'views/security/activity-logs/index.php', 'section' => 'Administration'],
                ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => 'views/security/settings/index.php', 'section' => 'Administration'],
                ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => 'views/security/profile.php', 'section' => 'Administration'],
            ];

            $currentScript = str_replace('\\', '/', $_GET['route'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
            foreach ($securityFeatureItems as $featureItem) {
                if (array_filter($navItems, static fn ($item) => ($item['label'] ?? '') === $featureItem['label'])) {
                    continue;
                }

                $groupedNavItems[$featureItem['section']][] = [
                    'icon' => $featureItem['icon'],
                    'label' => $featureItem['label'],
                    'href' => url($featureItem['href']),
                    'active' => str_contains($currentScript, '/' . trim($featureItem['href'], '/')),
                ];
            }
        }

        if ($role === ROLE_STUDENT) {
            $studentFeatureItems = [
                ['icon' => 'bi-megaphone', 'label' => 'Announcements', 'href' => 'views/student/announcements/index.php', 'section' => 'Communication'],
                ['icon' => 'bi-clock-history', 'label' => 'Activity History', 'href' => 'views/student/activity-logs/index.php', 'section' => 'Administration'],
                ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => 'views/student/reports/index.php', 'section' => 'Administration'],
                ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => 'views/student/settings/index/index.php', 'section' => 'Administration'],
                ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => 'views/student/profile/index/index.php', 'section' => 'Administration'],
            ];

            $currentScript = str_replace('\\', '/', $_GET['route'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
            foreach ($studentFeatureItems as $featureItem) {
                if (array_filter($navItems, static fn ($item) => ($item['label'] ?? '') === $featureItem['label'])) {
                    continue;
                }

                $groupedNavItems[$featureItem['section']][] = [
                    'icon' => $featureItem['icon'],
                    'label' => $featureItem['label'],
                    'href' => url($featureItem['href']),
                    'active' => str_contains($currentScript, '/' . trim($featureItem['href'], '/')),
                ];
            }
        }

        if (in_array($role, [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT, ROLE_STUDENT], true)
            && !array_filter($navItems, static fn ($item) => ($item['label'] ?? '') === 'Exeat')) {
            $groupedNavItems['Management'][] = [
                'icon' => 'bi-calendar2-week',
                'label' => 'Exeat',
                'href' => url('views/exeat/index.php'),
                'active' => str_contains(str_replace('\\', '/', $_GET['route'] ?? $_SERVER['SCRIPT_NAME'] ?? ''), '/views/exeat/'),
            ];
        }

        // Universal deduplicator for every section
        $globalSeen = [];
        foreach ($groupedNavItems as $sec => $items) {
            $deduped = [];
            foreach ($items as $it) {
                $lbl = strtolower(trim((string)($it['label'] ?? '')));
                if ($lbl !== '' && !isset($globalSeen[$lbl])) {
                    $globalSeen[$lbl] = true;
                    $deduped[] = $it;
                }
            }
            $groupedNavItems[$sec] = $deduped;
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
