<?php

return [
    'enabled' => filter_var($_ENV['MAIL_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'host' => (string) ($_ENV['MAIL_HOST'] ?? ''),
    'port' => (int) ($_ENV['MAIL_PORT'] ?? 587),
    'username' => (string) ($_ENV['MAIL_USERNAME'] ?? ''),
    'password' => (string) ($_ENV['MAIL_PASSWORD'] ?? ''),
    'encryption' => strtolower((string) ($_ENV['MAIL_ENCRYPTION'] ?? 'tls')),
    'from_address' => (string) ($_ENV['MAIL_FROM_ADDRESS'] ?? ''),
    'from_name' => (string) ($_ENV['MAIL_FROM_NAME'] ?? 'Student Dormitory System'),
];
