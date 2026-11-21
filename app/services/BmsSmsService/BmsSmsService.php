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
            for ($attempt = 1; $attempt <= 2; $attempt++) {
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
                    CURLOPT_TIMEOUT => 20,
                    CURLOPT_CONNECTTIMEOUT => 5,
                ]);

                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $connectTime = (float) curl_getinfo($curl, CURLINFO_CONNECT_TIME);
                $curlErrorCode = curl_errno($curl);
                $error = curl_error($curl);
                curl_close($curl);

                if ($response === false || $error !== '') {
                    $connectionWasNotEstablished = $connectTime === 0.0
                        && in_array($curlErrorCode, [
                            CURLE_COULDNT_RESOLVE_HOST,
                            CURLE_COULDNT_CONNECT,
                            CURLE_OPERATION_TIMEDOUT,
                        ], true);

                    if ($attempt === 1 && $connectionWasNotEstablished) {
                        usleep(250000);
                        continue;
                    }

                    $failureMessage = $connectionWasNotEstablished
                        ? ($attempt > 1
                            ? 'Could not connect to BMS Africa after one safe retry: '
                            : 'Could not connect to BMS Africa: ')
                        : 'BMS Africa did not confirm the SMS request: ';

                    return [
                        'success' => false,
                        'message' => $failureMessage . ($error ?: 'cURL request failed.'),
                        'curl_error_code' => $curlErrorCode,
                        'http_code' => $httpCode,
                        'connect_time' => $connectTime,
                    ];
                }

                $decoded = json_decode($response, true);
                $status = strtolower((string) ($decoded['status'] ?? ''));
                $messageText = (string) ($decoded['message'] ?? '');

                if ($httpCode >= 200 && $httpCode < 300 && $status === 'success') {
                    $campaignId = (string) ($decoded['summary']['_id'] ?? $decoded['_id'] ?? '');

                    return [
                        'success' => true,
                        'message' => 'SMS request accepted by BMS Africa; delivery is not confirmed.',
                        'campaign_id' => $campaignId,
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'SMS delivery failed: ' . ($messageText !== '' ? $messageText : 'Unknown BMS Africa error.'),
                    'provider_response' => $decoded,
                    'http_code' => $httpCode,
                ];
            }

            return [
                'success' => false,
                'message' => 'Could not connect to BMS Africa after retrying the connection.',
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

        if (str_starts_with($phone, '+233')) {
            return '0' . substr($phone, 4);
        }

        if (str_starts_with($phone, '233')) {
            return '0' . substr($phone, 3);
        }

        if (str_starts_with($phone, '0')) {
            return $phone;
        }

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        return $phone;
    }
}
