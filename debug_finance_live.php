<?php
require __DIR__ . '/public/bootstrap.php';

$db = \App\Services\FirebaseService::getInstance();

foreach (['finance_fees', 'finance_payments', 'students'] as $collection) {
    echo "=== " . strtoupper($collection) . " ===\n";
    try {
        $items = $db->getCollection($collection, [], 20);
        echo 'COUNT=' . count($items) . "\n";
        foreach ($items as $item) {
            $id = (string) ($item['id'] ?? '');
            $name = (string) ($item['name'] ?? $item['studentName'] ?? $item['feeType'] ?? '');
            $amount = (string) ($item['amount'] ?? '');
            $status = (string) ($item['status'] ?? '');
            echo $id . ' | ' . $name . ' | ' . $amount . ' | ' . $status . "\n";
        }
    } catch (Throwable $e) {
        echo 'ERROR: ' . $e->getMessage() . "\n";
    }
    echo "\n";
}
