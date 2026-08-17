<?php $user = current_user(); ?>
<nav class="topbar navbar navbar-expand navbar-light bg-white border-bottom px-3 w-100">
    <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <span class="fw-semibold ms-2"><?= e($pageTitle ?? '') ?></span>

    <div class="ms-auto d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-light position-relative" id="notifBell">
            <i class="bi bi-bell"></i>
            <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle" id="notifCount" style="display:none">0</span>
        </button>
        <div class="dropdown">
            <button class="btn btn-sm btn-light dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle"></i>
                <span><?= e($user['name'] ?? 'User') ?></span>
                <span class="badge bg-secondary text-uppercase"><?= e($user['role'] ?? '') ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= url('views/' . str_replace('_', '-', $user['role'] ?? '') . '/profile.php') ?>">My Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= url('logout.php') ?>">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>
<?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
