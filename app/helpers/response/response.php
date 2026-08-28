<?php
/** JSON response helper for AJAX endpoints */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function json_success(string $message = 'OK', array $extra = []): void
{
    json_response(array_merge(['success' => true, 'message' => $message], $extra));
}

function json_error(string $message = 'Error', int $status = 400): void
{
    json_response(['success' => false, 'message' => $message], $status);
}
