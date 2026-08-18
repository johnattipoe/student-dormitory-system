<?php
require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

require __DIR__ . '/../public/bootstrap.php';

use App\Services\VisitorAlertService;

$threshold = $argv[1] ?? 2;
$service = new VisitorAlertService();
$alerts = $service->checkForOvrstays((int) $threshold);

if (empty($alerts)) {
    echo "No visitor overstays detected above {$threshold} hours.\n";
    exit(0);
}

echo "Detected " . count($alerts) . " overstay alert(s).\n";
foreach ($alerts as $alert) {
    echo sprintf(
        "- %s | %s | %s | %s\n",
        $alert['visitorName'] ?? 'Unknown',
        $alert['studentId'] ?? 'N/A',
        $alert['durationFormatted'] ?? '0 minutes',
        $alert['severity'] ?? 'medium'
    );
}
