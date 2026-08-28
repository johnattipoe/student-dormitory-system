<?php
/**
 * Front-end Firebase auth configuration helpers.
 * These values can be overridden via environment variables and are kept in one place
 * for compatibility with the app's Firebase identity setup.
 */
return [
    'api_key' => $_ENV['FIREBASE_API_KEY'] ?? '',
    'auth_domain' => $_ENV['FIREBASE_AUTH_DOMAIN'] ?? '',
    'project_id' => $_ENV['FIREBASE_PROJECT_ID'] ?? '',
    'storage_bucket' => $_ENV['FIREBASE_STORAGE_BUCKET'] ?? '',
    'app_id' => $_ENV['FIREBASE_APP_ID'] ?? '',
    'measurement_id' => $_ENV['FIREBASE_MEASUREMENT_ID'] ?? '',
];
