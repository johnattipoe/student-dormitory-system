<?php
require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

require __DIR__ . '/../public/bootstrap.php';

use App\Services\VisitorAlertService;

$threshold = isset($argv[1]) ? (int) $argv[1] : null;
$service = new VisitorAlertService();
$alerts = $service->checkForOvrstays($threshold);

if (empty($alerts)) {
    $label = $threshold === null ? 'the configured threshold' : $threshold . ' hours';
    echo "No visitor overstays detected above {$label}.\n";
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
