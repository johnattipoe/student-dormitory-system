<?php
require __DIR__ . '/_context.php';

use App\Services\BedService;
$id = sanitize($_POST['id'] ?? ''); $bed = BedService::find($id); $room = $bed ? ($roomMap[(string)($bed['roomId']??'')] ?? null) : null;
if (!$bed || !house_master_bed_allowed($room, $houseId)) { flash('error', 'Bed not found in your assigned house.'); house_master_bed_redirect(); }
$result = BedService::unassign($id); flash(!empty($result['success'])?'success':'error', $result['message']??'Unable to unassign bed.'); house_master_bed_redirect();
