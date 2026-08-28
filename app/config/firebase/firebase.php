<?php

/**
 * Firebase Configuration
 * Loads Firebase credentials and returns config array.
 */

use Dotenv\Dotenv;

// Load environment variables
$rootPath = dirname(__DIR__, 3);
if (file_exists($rootPath . '/.env')) {
    $dotenv = Dotenv::createImmutable($rootPath);
    $dotenv->safeLoad();
}

// Helper function to resolve credentials path
if (!function_exists('resolveCredentialsPath')) {
    function resolveCredentialsPath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        // Already an absolute path
        if (str_starts_with($path, '/') || str_contains($path, ':\\')) {
            if (file_exists($path)) {
                return $path;
            }
            return null;
        }

        // Relative path - resolve from app root
        $appRoot = dirname(__DIR__, 3);
        $resolved = $appRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        
        if (file_exists($resolved)) {
            return $resolved;
        }
        
        return null;
    }
}

// Helper function to load credentials from base64 environment variable
if (!function_exists('loadCredentialsFromEnv')) {
    function loadCredentialsFromEnv(): ?string
    {
        $base64Creds = $_ENV['FIREBASE_CREDENTIALS_BASE64'] ?? getenv('FIREBASE_CREDENTIALS_BASE64');
        if (!$base64Creds) {
            return null;
        }

        try {
            // Decode base64 credentials
            $jsonData = base64_decode($base64Creds, true);
            if ($jsonData === false) {
                error_log('Warning: Invalid base64 in FIREBASE_CREDENTIALS_BASE64');
                return null;
            }

            // Write to temp file (readable only by current user on Linux)
            $tempDir = sys_get_temp_dir();
            $tempPath = $tempDir . DIRECTORY_SEPARATOR . 'firebase-creds-' . getmypid() . '.json';
            
            if (!file_put_contents($tempPath, $jsonData)) {
                error_log('Warning: Could not write temporary credentials file');
                return null;
            }

            // Set file permissions to 0600 on Unix-like systems
            if (PHP_OS_FAMILY !== 'Windows') {
                chmod($tempPath, 0600);
            }

            return $tempPath;
        } catch (\Exception $e) {
            error_log('Warning: Error loading credentials from env: ' . $e->getMessage());
            return null;
        }
    }
}

// Determine credentials path: first try environment variable, then file path
$credentialsPath = loadCredentialsFromEnv() ?? resolveCredentialsPath($_ENV['FIREBASE_CREDENTIALS'] ?? getenv('FIREBASE_CREDENTIALS'));

// Return config array for FirebaseService
return [
    'project_id' => $_ENV['FIREBASE_PROJECT_ID'] ?? getenv('FIREBASE_PROJECT_ID') ?? '',
    'credentials_path' => $credentialsPath,
    'firebase_enabled' => filter_var($_ENV['FIREBASE_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'api_key' => $_ENV['FIREBASE_API_KEY'] ?? getenv('FIREBASE_API_KEY') ?? '',
];