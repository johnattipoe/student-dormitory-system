<?php
require_once dirname(__DIR__,4).'/bootstrap.php';
$allowedRoles=[ROLE_ADMIN];require APP_ROOT.'/app/middleware/RoleMiddleware/RoleMiddleware.php';use App\Services\HouseService;
if(isset($_GET['download_template'])){header('Content-Type:text/csv');header('Content-Disposition:attachment; filename="house_import_template.csv"');$out=fopen('php://output','w');fputcsv($out,['Name','Gender','Capacity','Location','Status','HouseMasterId','HouseMistressId']);fputcsv($out,['House A','male',100,'North Block','active','','']);fclose($out);exit;}
$errors=[];$success=0;if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='import_houses'&&!empty($_FILES['houses_file'])){$file=$_FILES['houses_file'];$extension=strtolower(pathinfo($file['name']??'',PATHINFO_EXTENSION));if($file['error']!==UPLOAD_ERR_OK||!in_array($extension,['csv','xlsx'],true)){$errors[]='Upload a valid CSV or XLSX file.';}else{try{$rows=[];if($extension==='csv'){$handle=fopen($file['tmp_name'],'r');if($handle===false){throw new RuntimeException('Unable to read uploaded CSV file.');}while(($row=fgetcsv($handle))!==false){$rows[]=$row;}fclose($handle);}else{$spreadsheetClass='PhpOffice\\PhpSpreadsheet\\IOFactory';if(!class_exists($spreadsheetClass,true)){throw new RuntimeException('XLSX import requires the PhpSpreadsheet package to be installed.');}$rows=$spreadsheetClass::load($file['tmp_name'])->getActiveSheet()->toArray(null,true,true,false);}$header=array_map(fn($value)=>strtolower(preg_replace('/[^a-z0-9]/i','',(string)$value)),array_shift($rows)?:[]);$map=[];foreach($header as $index=>$name)$map[$name]=$index;foreach(array_slice($rows,0,1000) as $row){$value=fn($name,$fallback=-1)=>$row[$map[strtolower(preg_replace('/[^a-z0-9]/i','',$name))]??$fallback]??'';$name=sanitize($value('name',0));if($name===''){continue;}HouseService::create(['name'=>$name,'gender'=>sanitize($value('gender',1)),'capacity'=>max(1,(int)$value('capacity',2)),'location'=>sanitize($value('location',3)),'status'=>sanitize($value('status',4)?:'active'),'houseMasterId'=>sanitize($value('housemasterid',5)),'houseMistressId'=>sanitize($value('housemistressid',6))]);$success++;}flash('success',"Imported {$success} house(s) successfully.");redirect(url('views/admin/houses/bulk-import/bulk-import.php'));}catch(Throwable $e){$errors[]='Import failed: '.$e->getMessage();}}}
$pageTitle='Bulk House Import';$navItems=[['icon'=>'bi-speedometer2','label'=>'Dashboard','href'=>url('views/admin/dashboard.php')],['icon'=>'bi-building','label'=>'Houses','href'=>url('views/admin/houses/index/index.php')],['icon'=>'bi-upload','label'=>'Import Houses','href'=>url('views/admin/houses/bulk-import/bulk-import.php'),'active'=>true]];require APP_ROOT.'/app/views/components/header/header.php';require APP_ROOT.'/app/views/components/sidebar/sidebar.php';?>
<div class="main-content">
    <?php require APP_ROOT.'/app/views/components/navbar/navbar.php';?>
    <?php require APP_ROOT.'/app/views/components/alerts/alerts.php';?>
    <div class="content-wrapper">
        <div class="admin-settings-hero mb-4">
            <div class="admin-settings-icon"><i class="bi bi-file-earmark-arrow-up"></i></div>
            <div>
                <h1>Bulk House Import</h1>
                <p>Upload a CSV or XLSX file to add up to 1,000 houses at once.</p>
            </div>
            <div class="admin-settings-hero-actions">
                <a class="btn btn-outline-secondary btn-sm" href="<?=url('views/admin/houses/index/index.php')?>">
                    <i class="bi bi-arrow-left me-1"></i> Back to Houses
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0 p-4 w-100">
            <div class="admin-settings-card-heading">
                <div class="admin-settings-card-icon blue"><i class="bi bi-upload"></i></div>
                <div>
                    <h2>Import House Records</h2>
                    <p>Select a file, then upload it to add the houses.</p>
                </div>
            </div>
            <?php foreach($errors as $error):?>
                <div class="alert alert-danger"><?=e($error)?></div>
                <?php endforeach;?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="import_houses">
                    <label class="form-label">CSV or XLSX file</label>
                    <input type="file" name="houses_file" class="form-control mb-3" accept=".csv,.xlsx" required>
                    <p class="small text-muted">Headers: Name, Gender, Capacity, Location, Status, HouseMasterId, HouseMistressId. Maximum 1,000 rows.</p>
                    <button class="btn btn-primary">Upload and import</button>
                    <a class="btn btn-outline-secondary" href="<?=url('views/admin/houses/bulk-import/bulk-import.php?download_template=1')?>">Download template</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT.'/app/views/components/footer/footer.php';?>