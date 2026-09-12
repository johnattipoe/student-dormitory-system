<?php
function validate_required(array $data, array $fields): array
{
    $errors = [];
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }
    return $errors;
}

function validate_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_password_policy(string $password): ?string
{
    $config = function_exists('app_config') ? app_config() : [];
    $minimum = max(6, (int) ($config['password_min_length'] ?? 8));
    $requiresMixedCase = !empty($config['password_require_mixed_case']);

    if (strlen($password) < $minimum) {
        return 'Password must be at least ' . $minimum . ' characters.';
    }
    if ($requiresMixedCase && (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password))) {
        return 'Password must contain at least one uppercase and one lowercase letter.';
    }
    return null;
}

function validate_uploaded_file(array $file, ?array $extensions = null): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return 'Please upload a valid file.';
    }

    $config = function_exists('app_config') ? app_config() : [];
    $maxBytes = max(1, (int) ($config['max_upload_size_mb'] ?? 5)) * 1024 * 1024;
    if ((int) ($file['size'] ?? 0) > $maxBytes) {
        return 'Uploaded file must be ' . (int) ($config['max_upload_size_mb'] ?? 5) . ' MB or smaller.';
    }

    $allowed = $extensions ?? ($config['allowed_upload_extensions'] ?? []);
    $allowed = array_map('strtolower', is_array($allowed) ? $allowed : []);
    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, $allowed, true)) {
        return 'This file type is not allowed.';
    }

    return null;
}

function sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}
