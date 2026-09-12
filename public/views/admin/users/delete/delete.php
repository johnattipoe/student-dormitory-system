<?php
require_once dirname(__DIR__,4).'/bootstrap.php';$allowedRoles=[ROLE_ADMIN];require APP_ROOT.'/app/middleware/RoleMiddleware/RoleMiddleware.php';use App\Services\UserService;
$id=sanitize($_GET['id']??$_POST['id']??'');$service=new UserService();$user=$id?$service->find($id):null;if(!$user){flash('error','User not found.');redirect(url('views/admin/users/index/index.php'));}if($_SERVER['REQUEST_METHOD']==='POST'){$result=$service->delete($id);flash($result['success']?'success':'error',$result['message']);redirect(url('views/admin/users/index/index.php'));}$pageTitle='Delete User';$navItems=[['icon'=>'bi-people','label'=>'Users','href'=>url('views/admin/users/index/index.php'),'active'=>true]];require APP_ROOT.'/app/views/components/header/header.php';require APP_ROOT.'/app/views/components/sidebar/sidebar.php';?>
<div class="main-content">
    <?php require APP_ROOT.'/app/views/components/navbar/navbar.php';?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-person-x-fill text-danger me-2"></i>Delete User
                </h4>
                <p class="text-muted mb-0">Permanently remove a user account from the system</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/admin/users/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to Users
                </a>
            </div>
        </div>

        <!-- Delete Confirmation Card -->
        <div class="card stat-card shadow-sm border-0 border-top border-4 border-danger" style="max-width:600px;">
            <div class="card-body p-4 text-center">
                <div class="mb-3">
                    <div class="rounded-circle bg-danger bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width:80px;height:80px;">
                        <i class="bi bi-exclamation-triangle-fill text-danger fs-1"></i>
                    </div>
                </div>
                <h5 class="fw-bold text-dark mb-2">Delete <?= e($user['name'] ?? $user['email'] ?? 'user') ?>?</h5>
                <p class="text-muted mb-1">You are about to permanently delete this user account.</p>
                <?php if (!empty($user['email'])): ?>
                    <p class="text-muted small mb-3"><i class="bi bi-envelope me-1"></i><?= e($user['email']) ?></p>
                <?php endif; ?>
                <div class="alert alert-danger d-flex align-items-center text-start small mb-4">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <span>This action cannot be undone. All data associated with this user will be permanently removed.</span>
                </div>
                <form method="POST">
                    <input type="hidden" name="id" value="<?= e($id) ?>">
                    <div class="d-flex gap-2 justify-content-center">
                        <button class="btn btn-danger"><i class="bi bi-trash me-1"></i>Confirm Delete</button>
                        <a class="btn btn-outline-secondary" href="<?= url('views/admin/users/index/index.php') ?>"><i class="bi bi-x-lg me-1"></i>Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT.'/app/views/components/footer/footer.php';?>