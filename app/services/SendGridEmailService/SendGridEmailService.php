<?php

namespace App\Services;

class SendGridEmailService
{
    private string $apiKey;
    private string $fromEmail;
    private string $fromName;

    public function __construct()
    {
        $config = require APP_ROOT . '/app/config/mail/mail.php';
        $this->apiKey = (string) ($config['password'] ?? '');
        $this->fromEmail = (string) ($config['from_address'] ?? '');
        $this->fromName = (string) ($config['from_name'] ?? 'Student Dormitory System');

        // Trim spaces from API key (sometimes environment variables have extra spaces)
        $this->apiKey = trim($this->apiKey);
        $this->fromEmail = trim($this->fromEmail);

        // Check if API key looks valid
        if (empty($this->apiKey) || !str_starts_with($this->apiKey, 'SG.')) {
            throw new \Exception('SendGrid API key not found or invalid in mail configuration. Key should start with "SG."');
        }
    }

    public function sendHtml(
        string $recipient,
        string $subject,
        string $htmlBody,
        ?string $textBody = null
    ): array {
        if (empty($this->apiKey) || empty($this->fromEmail)) {
            return [
                'success' => false,
                'message' => 'SendGrid API key or from address is not configured.',
            ];
        }

        if (empty($recipient) || empty($subject)) {
            return [
                'success' => false,
                'message' => 'Recipient email and subject are required.',
            ];
        }

        // Build email payload
        $payload = [
            'personalizations' => [
                [
                    'to' => [
                        ['email' => trim($recipient)]
                    ],
                    'subject' => $subject,
                ]
            ],
            'from' => [
                'email' => $this->fromEmail,
                'name' => $this->fromName,
            ],
            'content' => [
                [
                    'type' => 'text/html',
                    'value' => $htmlBody,
                ]
            ],
        ];

        // Add plain text version if provided
        if (!empty($textBody)) {
            $payload['content'][] = [
                'type' => 'text/plain',
                'value' => $textBody,
            ];
        }

        // Send via SendGrid Web API v3
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.sendgrid.com/v3/mail/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            unset($curl);

            if (!empty($error)) {
                error_log('SendGrid cURL error: ' . $error);
                return [
                    'success' => false,
                    'message' => 'Email delivery failed: ' . $error,
                ];
            }

            // SendGrid returns 202 for accepted emails
            if ($httpCode === 202) {
                return [
                    'success' => true,
                    'message' => 'Email sent successfully via SendGrid Web API.',
                ];
            }

            // Handle error responses
            $responseData = json_decode($response, true);
            $errorMsg = 'Unknown error';
            if (isset($responseData['errors']) && is_array($responseData['errors'])) {
                $errorMsg = $responseData['errors'][0]['message'] ?? 'SendGrid API error';
            } elseif (isset($responseData['error'])) {
                $errorMsg = $responseData['error'];
            }

            error_log('SendGrid API error (' . $httpCode . '): ' . $errorMsg);
            return [
                'success' => false,
                'message' => 'Email delivery failed: ' . $errorMsg,
            ];
        } catch (\Throwable $e) {
            error_log('SendGrid email exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Email delivery failed: ' . $e->getMessage(),
            ];
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->fromEmail);
    }
}
