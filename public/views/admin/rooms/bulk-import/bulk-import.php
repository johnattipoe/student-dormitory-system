<?php
require_once dirname(__DIR__,4).'/bootstrap.php';
$allowedRoles=[ROLE_ADMIN];require APP_ROOT.'/app/middleware/RoleMiddleware/RoleMiddleware.php';
use App\Services\FirebaseService;use App\Services\HouseService;use App\Services\RoomService;
if(isset($_GET['download_template'])){header('Content-Type:text/csv');header('Content-Disposition:attachment; filename="room_import_template.csv"');$out=fopen('php://output','w');fputcsv($out,['RoomNumber','HouseId','Capacity','Type','Status']);fputcsv($out,['A-101','HOUSE_ID',4,'standard','available']);fclose($out);exit;}
$errors=[];$success=0;if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='import_rooms'&&!empty($_FILES['rooms_file'])){$file=$_FILES['rooms_file'];$extension=strtolower(pathinfo($file['name']??'',PATHINFO_EXTENSION));if($file['error']!==UPLOAD_ERR_OK||!in_array($extension,['csv','xlsx'],true)){$errors[]='Upload a valid CSV or XLSX file.';}else{try{$spreadsheetFactoryClass='PhpOffice\\PhpSpreadsheet\\IOFactory';if($extension==='csv'){$rows=[];$handle=fopen($file['tmp_name'],'r');if($handle===false){throw new RuntimeException('Unable to read uploaded CSV file.');}while(($row=fgetcsv($handle))!==false){$rows[]=$row;}fclose($handle);}elseif(class_exists($spreadsheetFactoryClass)){$rows=$spreadsheetFactoryClass::load($file['tmp_name'])->getActiveSheet()->toArray(null,true,true,false);}else{throw new RuntimeException('Spreadsheet support is unavailable on this server. Please upload a CSV file.');}$header=array_map(fn($value)=>strtolower(preg_replace('/[^a-z0-9]/i','',(string)$value)),array_shift($rows)?:[]);$map=[];foreach($header as $index=>$name)$map[$name]=$index;$defaultHouseId=sanitize($_POST['defaultHouseId']??'');foreach(array_slice($rows,0,1000) as $row){$value=fn($name,$fallback=-1)=>$row[$map[strtolower(preg_replace('/[^a-z0-9]/i','',$name))]??$fallback]??'';$roomNumber=sanitize($value('roomnumber',0));if($roomNumber===''){continue;}$houseId=sanitize($value('houseid',1)?:$defaultHouseId);RoomService::create(['roomNumber'=>$roomNumber,'houseId'=>$houseId,'capacity'=>max(1,(int)$value('capacity',2)),'type'=>sanitize($value('type',3)?:'standard'),'status'=>sanitize($value('status',4)?:'available')]);$success++;}flash('success',"Imported {$success} room(s) successfully.");redirect(url('views/admin/rooms/bulk-import/bulk-import.php'));}catch(Throwable $e){$errors[]='Import failed: '.$e->getMessage();}}}
$houses=HouseService::all();$pageTitle='Bulk Room Import';$navItems=[['icon'=>'bi-speedometer2','label'=>'Dashboard','href'=>url('views/admin/dashboard.php')],['icon'=>'bi-door-closed','label'=>'Rooms','href'=>url('views/admin/rooms/index/index.php')],['icon'=>'bi-upload','label'=>'Import Rooms','href'=>url('views/admin/rooms/bulk-import/bulk-import.php'),'active'=>true]];require APP_ROOT.'/app/views/components/header/header.php';require APP_ROOT.'/app/views/components/sidebar/sidebar.php';?>
<div class="main-content">
    <?php require APP_ROOT.'/app/views/components/navbar/navbar.php';?>
    <?php require APP_ROOT.'/app/views/components/alerts/alerts.php';?>
    <div class="content-wrapper">
        <div class="admin-settings-hero mb-4">
            <div class="admin-settings-icon"><i class="bi bi-file-earmark-arrow-up"></i></div>
            <div>
                <h1>Bulk Room Import</h1>
                <p>Upload a CSV or XLSX file to add up to 1,000 rooms at once.</p>
            </div>
            <div class="admin-settings-hero-actions">
                <a class="btn btn-outline-secondary btn-sm" href="<?=url('views/admin/rooms/index/index.php')?>">
                    <i class="bi bi-arrow-left me-1"></i> Back to Rooms
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0 p-4 w-100">
            <div class="admin-settings-card-heading">
                <div class="admin-settings-card-icon blue"><i class="bi bi-upload"></i></div>
                <div>
                    <h2>Import Room Records</h2>
                    <p>Select a file and house, then upload the room records.</p>
                </div>
            </div>
            <?php foreach($errors as $error):?>
                <div class="alert alert-danger"><?=e($error)?></div
                <?php endforeach;?><form method="POST" enctype="multipart/form-data"><input type="hidden" name="action" value="import_rooms">
                <label class="form-label">CSV or XLSX file</label>
                <input type="file" name="rooms_file" class="form-control mb-3" accept=".csv,.xlsx" required>
                <label class="form-label">House</label>
                <select name="defaultHouseId" class="form-select mb-3">
                    <option value="">Use HouseId column</option>
                    <?php foreach($houses as $house): ?>
                        <option value="<?= e((string)($house['id']??'')) ?>"><?= e($house['name']??'') ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="small text-muted">Headers: RoomNumber, HouseId, Capacity, Type, Status. Maximum 1,000 rows.</p>
                <button class="btn btn-primary">Upload and import</button>
                <a class="btn btn-outline-secondary" href="<?= url('views/admin/rooms/bulk-import/bulk-import.php?download_template=1') ?>">Download template</a>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT.'/app/views/components/footer/footer.php';?>