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

function sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}
