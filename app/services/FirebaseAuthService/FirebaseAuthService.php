<?php

namespace App\Services;

/**
 * Talks to the Firebase Identity Toolkit REST API for email/password
 * sign-in. Using REST here (instead of the Admin SDK) keeps login fast
 * and simple — the Admin SDK is reserved for trusted server-side
 * Firestore access in FirebaseService.
 *
 * Docs: https://firebase.google.com/docs/reference/rest/auth
 */
class FirebaseAuthService
{
    private const SIGN_IN_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=%s';
    private const SIGN_UP_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:signUp?key=%s';
    private const SEND_OOB_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:sendOobCode?key=%s';
    private const RESET_PASSWORD_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:resetPassword?key=%s';

    private static function apiKey(): string
    {
        $appConfig = require APP_ROOT . '/app/config/app/app.php';
        if (empty($appConfig['firebase_enabled'])) {
            throw new \RuntimeException('Firebase is not enabled');
        }

        $config = require APP_ROOT . '/app/config/firebase/firebase.php';
        $apiKey = $config['api_key'] ?? null;
        
        if (!$apiKey) {
            throw new \RuntimeException('FIREBASE_API_KEY is not configured in .env');
        }
        
        return $apiKey;
    }

    /**
     * @return array{uid:string, idToken:string, refreshToken:string, email:string}|null
     */
    public static function signIn(string $email, string $password): ?array
    {
        $response = self::post(sprintf(self::SIGN_IN_URL, self::apiKey()), [
            'email' => $email,
            'password' => $password,
            'returnSecureToken' => true,
        ]);

        if (!$response || isset($response['error'])) {
            return null;
        }

        return [
            'uid'          => $response['localId'],
            'idToken'      => $response['idToken'],
            'refreshToken' => $response['refreshToken'],
            'email'        => $response['email'],
        ];
    }

    public static function signUp(string $email, string $password): ?array
    {
        $response = self::post(sprintf(self::SIGN_UP_URL, self::apiKey()), [
            'email' => $email,
            'password' => $password,
            'returnSecureToken' => true,
        ]);

        if (!$response || isset($response['error'])) {
            return null;
        }

        return [
            'uid'          => $response['localId'],
            'idToken'      => $response['idToken'],
            'refreshToken' => $response['refreshToken'],
            'email'        => $response['email'],
        ];
    }

    public static function sendPasswordResetEmail(string $email): array
    {
        $response = self::post(sprintf(self::SEND_OOB_URL, self::apiKey()), [
            'requestType' => 'PASSWORD_RESET',
            'email' => $email,
        ]);

        if (!$response || isset($response['error'])) {
            return [
                'success' => false,
                'message' => $response['error']['message'] ?? 'Unable to send password reset email.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Password reset email sent successfully.',
        ];
    }

    public static function sendEmailVerification(string $email): array
    {
        $response = self::post(sprintf(self::SEND_OOB_URL, self::apiKey()), [
            'requestType' => 'VERIFY_EMAIL',
            'email' => $email,
        ]);

        if (!$response || isset($response['error'])) {
            return [
                'success' => false,
                'message' => $response['error']['message'] ?? 'Unable to send account verification email.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Verification email sent successfully.',
        ];
    }

    public static function confirmPasswordReset(string $oobCode, string $newPassword): array
    {
        $response = self::post(sprintf(self::RESET_PASSWORD_URL, self::apiKey()), [
            'oobCode' => $oobCode,
            'newPassword' => $newPassword,
        ]);

        if (!$response || isset($response['error'])) {
            return [
                'success' => false,
                'message' => $response['error']['message'] ?? 'Password reset request is invalid or expired.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Your password was reset successfully.',
        ];
    }

    private static function post(string $url, array $body): ?array
    {
        if (self::apiKey() === '') {
            return ['error' => ['message' => 'Firebase authentication is disabled by the administrator.']];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_TIMEOUT => 15,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            return null;
        }

        return json_decode($raw, true);
    }
}
