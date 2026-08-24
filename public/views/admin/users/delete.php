<?php
require_once dirname(__DIR__,3).'/bootstrap.php';$allowedRoles=[ROLE_ADMIN];require APP_ROOT.'/app/middleware/RoleMiddleware.php';use App\Services\UserService;
$id=sanitize($_GET['id']??$_POST['id']??'');$service=new UserService();$user=$id?$service->find($id):null;if(!$user){flash('error','User not found.');redirect(url('views/admin/users/index.php'));}if($_SERVER['REQUEST_METHOD']==='POST'){$result=$service->delete($id);flash($result['success']?'success':'error',$result['message']);redirect(url('views/admin/users/index.php'));}$pageTitle='Delete User';$navItems=[['icon'=>'bi-people','label'=>'Users','href'=>url('views/admin/users/index.php'),'active'=>true]];require APP_ROOT.'/app/views/components/header.php';require APP_ROOT.'/app/views/components/sidebar.php';?>
<div class="main-content">
    <?php require APP_ROOT.'/app/views/components/navbar.php';?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:600px">
            <h5>Delete <?=e($user['name']??$user['email']??'user')?>?</h5>
            <p class="text-muted">This action cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="id" value="<?=e($id)?>">
                <button class="btn btn-danger">Confirm delete</button>
                <a class="btn btn-outline-secondary" href="<?=url('views/admin/users/index.php')?>">Cancel</a>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT.'/app/views/components/footer.php';?>