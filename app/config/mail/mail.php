<?php

$getMailEnv = static function (string $key, mixed $default = null): mixed {
    $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($val !== false && $val !== null && $val !== '') {
        return $val;
    }

    // Direct parser fallback if PHP variables_order skips $_ENV
    static $envCache = null;
    if ($envCache === null) {
        $envCache = [];
        $root = dirname(__DIR__, 3);
        $files = [$root . '/.env', $root . '/.env.example'];
        foreach ($files as $file) {
            if (file_exists($file)) {
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    if (str_contains($line, '=')) {
                        [$k, $v] = explode('=', $line, 2);
                        $k = trim($k);
                        $v = trim($v);
                        $v = trim($v, "\"'");
                        if (!isset($envCache[$k])) {
                            $envCache[$k] = $v;
                        }
                    }
                }
            }
        }
    }

    return $envCache[$key] ?? $default;
};

$enabledRaw = $getMailEnv('MAIL_ENABLED', false);
$enabled = filter_var($enabledRaw, FILTER_VALIDATE_BOOLEAN) || in_array(strtolower((string) $enabledRaw), ['1', 'true', 'yes', 'on'], true);

return [
    'enabled' => $enabled,
    'host' => (string) $getMailEnv('MAIL_HOST', ''),
    'port' => (int) $getMailEnv('MAIL_PORT', 587),
    'username' => (string) $getMailEnv('MAIL_USERNAME', ''),
    'password' => (string) $getMailEnv('MAIL_PASSWORD', ''),
    'encryption' => strtolower((string) $getMailEnv('MAIL_ENCRYPTION', 'tls')),
    'from_address' => (string) $getMailEnv('MAIL_FROM_ADDRESS', ''),
    'from_name' => (string) $getMailEnv('MAIL_FROM_NAME', 'Student Dormitory System'),
];
