<?php

/**
 * Firebase Configuration
 * Loads Firebase credentials and returns config array.
 */

use Dotenv\Dotenv;

// Load environment variables
$rootPath = dirname(__DIR__, 2);
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
        $appRoot = dirname(__DIR__, 2);
        $resolved = $appRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        
        if (file_exists($resolved)) {
            return $resolved;
        }
        
        return null;
    }
}

// Return config array for FirebaseService
return [
    'project_id' => $_ENV['FIREBASE_PROJECT_ID'] ?? getenv('FIREBASE_PROJECT_ID') ?? '',
    'credentials_path' => resolveCredentialsPath($_ENV['FIREBASE_CREDENTIALS'] ?? getenv('FIREBASE_CREDENTIALS')),
    'firebase_enabled' => filter_var($_ENV['FIREBASE_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'api_key' => $_ENV['FIREBASE_API_KEY'] ?? getenv('FIREBASE_API_KEY') ?? '',
];