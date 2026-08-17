<?php
/** Central bootstrap for helpers - require this once from index.php / entry points */
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';

function app_config(): array
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/../config/app.php';
    }

    return $config;
}

function url(string $path = ''): string
{
    return base_url($path);
}

function asset(string $path = ''): string
{
    return base_url('assets/' . ltrim($path, '/'));
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
