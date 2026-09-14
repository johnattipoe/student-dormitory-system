<?php
/** Central bootstrap for helpers - require this once from index.php / entry points */
require_once __DIR__ . '/../response/response.php';
require_once __DIR__ . '/../session/session.php';
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../validation/validation.php';

function app_config(): array
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/../../config/app/app.php';
    }

    return $config;
}

function url(string $path = ''): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return base_url();
    }

    if (preg_match('/^(https?:)?\/\//i', $path) === 1 || str_contains($path, '?route=')) {
        return base_url($path);
    }

    $normalized = ltrim($path, '/');
    if (str_starts_with($normalized, 'views/') || str_starts_with($normalized, 'public/views/')) {
        $route = '/' . ltrim(str_replace('public/', '', $normalized), '/');
        return base_url('index.php?route=' . urlencode($route));
    }

    return base_url($path);
}

function asset(string $path = ''): string
{
    $path = ltrim($path, '/');
    $parts = explode('/', $path, 2);

    if (count($parts) === 2 && in_array($parts[0], ['css', 'js'], true)) {
        $subPath = $parts[1];
        $hasNestedFolder = str_contains($subPath, '/');

        if (!$hasNestedFolder) {
            $file = basename($subPath);
            $path = $parts[0] . '/' . pathinfo($file, PATHINFO_FILENAME) . '/' . $file;
        } else {
            $path = $parts[0] . '/' . $subPath;
        }
    }

    return base_url('assets/' . $path);
}

function base_url(string $path = ''): string
{
    $app = app_config();
    return rtrim($app['url'], '/') . '/' . ltrim($path, '/');
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}
