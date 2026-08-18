<?php

namespace App\Migrations;

use App\Services\FirebaseService;
use Dotenv\Dotenv;

/**
 * Migration Runner - Execute database migrations
 * 
 * Usage: php app/migrations/Runner.php up
 *        php app/migrations/Runner.php down
 *        php app/migrations/Runner.php status
 */
class Runner
{
    private array $migrations = [];
    private FirebaseService $firebase;

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
        $this->loadMigrations();
    }

    private function loadMigrations(): void
    {
        $this->migrations = [
            'CreateNotificationPreferencesCollection',
            'CreateMedicalRecordAuditsCollection',
        ];
    }

    public function up(): void
    {
        echo "\n=== Running Migrations (UP) ===\n\n";
        
        foreach ($this->migrations as $migration) {
            $class = "App\\Migrations\\$migration";
            if (class_exists($class)) {
                echo "[" . date('H:i:s') . "] Running: $migration\n";
                try {
                    $instance = new $class();
                    $instance->up();
                    echo "\n";
                } catch (\Throwable $e) {
                    echo "ERROR: " . $e->getMessage() . "\n\n";
                }
            }
        }
        
        echo "=== Migration Complete ===\n";
    }

    public function down(): void
    {
        echo "\n=== Rolling Back Migrations (DOWN) ===\n\n";
        
        $reversed = array_reverse($this->migrations);
        foreach ($reversed as $migration) {
            $class = "App\\Migrations\\$migration";
            if (class_exists($class)) {
                echo "[" . date('H:i:s') . "] Rolling back: $migration\n";
                try {
                    $instance = new $class();
                    $instance->down();
                    echo "\n";
                } catch (\Throwable $e) {
                    echo "ERROR: " . $e->getMessage() . "\n\n";
                }
            }
        }
        
        echo "=== Rollback Complete ===\n";
    }

    public function status(): void
    {
        echo "\n=== Migration Status ===\n\n";
        echo "Registered Migrations:\n";
        foreach ($this->migrations as $i => $migration) {
            echo ($i + 1) . ". " . $migration . "\n";
        }
        echo "\n";
    }
}

// CLI Runner
if (php_sapi_name() === 'cli') {
    require_once __DIR__ . '/../../vendor/autoload.php';

    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();

    $dir = __DIR__;
    for ($i = 0; $i < 5; $i++) {
        if (file_exists($dir . '/../../public/bootstrap.php')) {
            require_once $dir . '/../../public/bootstrap.php';
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }

    $command = $argv[1] ?? 'status';
    $runner = new Runner();

    switch (strtolower($command)) {
        case 'up':
            $runner->up();
            break;
        case 'down':
            $runner->down();
            break;
        case 'status':
            $runner->status();
            break;
        default:
            echo "Unknown command: $command\n";
            echo "Usage: php Runner.php [up|down|status]\n";
            exit(1);
    }
}
