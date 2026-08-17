<?php
/**
 * Firebase helper shims for app-level configuration access.
 */
if (!function_exists('firebase_config')) {
    function firebase_config(): array
    {
        return require __DIR__ . '/../config/firebase.php';
    }
}

if (!function_exists('firebase_project_id')) {
    function firebase_project_id(): string
    {
        $config = firebase_config();
        return (string) ($config['project_id'] ?? $_ENV['FIREBASE_PROJECT_ID'] ?? '');
    }
}

if (!function_exists('firebase_api_key')) {
    function firebase_api_key(): string
    {
        $config = firebase_config();
        return (string) ($config['api_key'] ?? $_ENV['FIREBASE_API_KEY'] ?? '');
    }
}
