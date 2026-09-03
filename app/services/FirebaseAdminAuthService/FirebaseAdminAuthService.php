<?php

namespace App\Services;

/**
 * Minimal Firebase Admin Auth helper that lists Authentication users using
 * service account credentials. This avoids pulling in the full Admin SDK.
 */
class FirebaseAdminAuthService
{
    public static function credentialsAvailable(): bool
    {
        $config = require APP_ROOT . '/app/config/firebase/firebase.php';
        $path = $config['credentials_path'] ?? '';
        return is_string($path) && file_exists($path);
    }

    /**
     * Return an access token using service account credentials for the
     * Identity Toolkit scope. Returns null on failure.
     *
     * @return string|null
     */
    private static function fetchAccessToken(): ?string
    {
        $config = require APP_ROOT . '/app/config/firebase/firebase.php';
        $path = $config['credentials_path'] ?? '';
        if (!is_string($path) || !file_exists($path)) return null;

        // Use Google ServiceAccountCredentials to fetch an access token.
        try {
            $scope = 'https://www.googleapis.com/auth/identitytoolkit';
            $creds = new \Google\Auth\Credentials\ServiceAccountCredentials($scope, $path);
            $token = $creds->fetchAuthToken();
            if (is_array($token) && !empty($token['access_token'])) {
                return $token['access_token'];
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    public static function generatePasswordResetLink(string $email, string $continueUrl): ?string
    {
        if (!self::credentialsAvailable()) return null;
        $config = require APP_ROOT . '/app/config/firebase/firebase.php';
        $project = $config['project_id'] ?? '';
        $access = self::fetchAccessToken();
        if (!$project || !$access) return null;

        $url = sprintf('https://identitytoolkit.googleapis.com/v1/projects/%s/accounts:sendOobCode', $project);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access, 'Content-Type: application/json'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'requestType' => 'PASSWORD_RESET',
                'email' => $email,
                'returnOobLink' => true,
                'continueUrl' => $continueUrl,
            ]),
            CURLOPT_TIMEOUT => 20,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) return null;
        $response = json_decode($raw, true);
        return is_array($response) ? (string) ($response['oobLink'] ?? '') ?: null : null;
    }

    /**
     * List Auth users from Firebase Auth (Admin REST). Returns array of user
     * records or empty array on failure.
     *
     * Note: this method requires a service account JSON with project_id.
     */
    public static function listAuthUsers(int $maxResults = 1000): array
    {
        if (!self::credentialsAvailable()) return [];

        $config = require APP_ROOT . '/app/config/firebase/firebase.php';
        $project = $config['project_id'] ?? '';
        if (!$project) return [];

        $access = self::fetchAccessToken();
        if (!$access) return [];

        // Try the admin batch endpoint. Some environments support :query or :batchGet
        $endpoints = [
            sprintf('https://identitytoolkit.googleapis.com/v1/projects/%s/accounts:batchGet?maxResults=%d', $project, $maxResults),
            sprintf('https://identitytoolkit.googleapis.com/v1/projects/%s/accounts:query', $project),
        ];

        foreach ($endpoints as $url) {
            $ch = curl_init($url);
            $body = json_encode(['maxResults' => $maxResults]);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $access,
                    'Content-Type: application/json',
                ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_TIMEOUT => 20,
            ]);
            $raw = curl_exec($ch);
            if ($raw === false) { continue; }
            $resp = json_decode($raw, true);
            // curl_close() is handled automatically by PHP 8.0+ resource cleanup

            if (!$resp) continue;

            // Normalized keys: 'users' or 'accounts' or 'localUsers'
            if (!empty($resp['users']) && is_array($resp['users'])) {
                return $resp['users'];
            }
            if (!empty($resp['accounts']) && is_array($resp['accounts'])) {
                return $resp['accounts'];
            }
            if (!empty($resp['localUsers']) && is_array($resp['localUsers'])) {
                return $resp['localUsers'];
            }
            // Some batchGet returns top-level fields mapping by localId — attempt to coerce
            if (isset($resp['usersById']) && is_array($resp['usersById'])) {
                $out = [];
                foreach ($resp['usersById'] as $id => $data) {
                    $data['localId'] = $id;
                    $out[] = $data;
                }
                return $out;
            }
        }

        return [];
    }
}
