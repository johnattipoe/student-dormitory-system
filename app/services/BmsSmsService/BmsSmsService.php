<?php

namespace App\Services;

class BmsSmsService
{
    private string $apiKey;
    private string $senderId;

    public function __construct()
    {
        $config = require APP_ROOT . '/app/config/sms/sms.php';

        $this->apiKey = trim((string) ($config['api_key'] ?? ''));
        $this->senderId = trim((string) ($config['sender_id'] ?? 'BMS Africa'));

        if ($this->apiKey === '') {
            throw new \RuntimeException('BMS Africa API key is not configured.');
        }
    }

    public function send(string $recipient, string $message): array
    {
        $recipient = $this->normalizePhone($recipient);
        $message = trim($message);

        if ($recipient === '') {
            return ['success' => false, 'message' => 'Recipient phone number is required.'];
        }

        if ($message === '') {
            return ['success' => false, 'message' => 'Message content is required.'];
        }

        $maxLength = max(1, (int) (function_exists('app_config') ? (app_config()['sms_max_length'] ?? 160) : 160));
        if (mb_strlen($message) > $maxLength) {
            return ['success' => false, 'message' => 'SMS message must be ' . $maxLength . ' characters or fewer.'];
        }

        $payload = [
            'recipient' => [$recipient],
            'sender' => $this->senderId,
            'message' => $message,
            'is_schedule' => false,
            'schedule_date' => '',
        ];

        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.mnotify.com/api/sms/quick?key=' . urlencode($this->apiKey),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);

            if ($response === false || $error !== '') {
                return [
                    'success' => false,
                    'message' => 'SMS delivery failed: ' . ($error ?: 'cURL request failed.'),
                ];
            }

            $decoded = json_decode($response, true);
            $status = strtolower((string) ($decoded['status'] ?? ''));
            $messageText = (string) ($decoded['message'] ?? '');

            if ($httpCode >= 200 && $httpCode < 300 && ($status === 'success' || str_contains(strtolower($messageText), 'success'))) {
                return [
                    'success' => true,
                    'message' => 'SMS sent successfully via BMS Africa.',
                    'provider_response' => $decoded,
                ];
            }

            return [
                'success' => false,
                'message' => 'SMS delivery failed: ' . ($messageText !== '' ? $messageText : 'Unknown BMS Africa error.'),
                'provider_response' => $decoded,
                'http_code' => $httpCode,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'SMS delivery failed: ' . $e->getMessage(),
            ];
        }
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        $phone = preg_replace('/\s+/', '', $phone);
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if ($phone === '') {
            return '';
        }

        if (str_starts_with($phone, '0')) {
            return '+233' . substr($phone, 1);
        }

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        if (preg_match('/^233/', $phone)) {
            return '+' . $phone;
        }

        return $phone;
    }
}
