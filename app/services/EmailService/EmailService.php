<?php

namespace App\Services;

// Ensure PHPMailer classes are loaded safely (supports vendor and standalone PHPMailer-master)
if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
    $phpMailerPaths = [
        APP_ROOT . '/vendor/phpmailer/phpmailer/src/',
        APP_ROOT . '/PHPMailer-master/src/',
        APP_ROOT . '/PHPMailer-master/PHPMailer-master/src/',
    ];

    foreach ($phpMailerPaths as $basePath) {
        if (file_exists($basePath . 'Exception.php') && file_exists($basePath . 'PHPMailer.php') && file_exists($basePath . 'SMTP.php')) {
            require_once $basePath . 'Exception.php';
            require_once $basePath . 'PHPMailer.php';
            require_once $basePath . 'SMTP.php';
            break;
        }
    }
}

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class EmailService
{
    public function isConfigured(): bool
    {
        $config = require APP_ROOT . '/app/config/mail/mail.php';

        return !empty($config['enabled'])
            && !empty($config['host'])
            && !empty($config['from_address']);
    }

    public function sendHtml(
        string $recipient,
        string $subject,
        string $htmlBody,
        ?string $textBody = null
    ): array {
        $config = require APP_ROOT . '/app/config/mail/mail.php';

        if (empty($config['enabled'])) {
            return [
                'success' => false,
                'message' => 'Email sending is disabled. Please enable MAIL_ENABLED in configuration.',
            ];
        }

        if (empty($config['host']) || empty($config['from_address'])) {
            return [
                'success' => false,
                'message' => 'Mail configuration is incomplete (Host and From Address are required).',
            ];
        }

        try {
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $config['host'];
            $mailer->Port = (int) ($config['port'] ?? 587);
            $mailer->SMTPAuth = !empty($config['username']);
            $mailer->Username = trim((string) ($config['username'] ?? ''));
            $password = (string) ($config['password'] ?? '');
            if ($config['host'] === 'smtp.gmail.com') {
                $trimmedPassword = str_replace(' ', '', $password);
                if (strlen($trimmedPassword) === 16) {
                    $password = $trimmedPassword;
                }
            }
            $mailer->Password = $password;

            $encryption = strtolower((string) ($config['encryption'] ?? 'tls'));
            if ($encryption === 'ssl' || $mailer->Port === 465) {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls' || $mailer->Port === 587) {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            // SMTP Options for local / self-signed certificate resilience
            $mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];

            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom($config['from_address'], $config['from_name'] ?? 'Student Dormitory System');
            $mailer->addAddress(trim($recipient));
            $mailer->isHTML(true);
            $mailer->Subject = $subject;

            // Wrap in styled HTML email template if not already wrapped
            if (!str_contains($htmlBody, '<html') && !str_contains($htmlBody, '<body')) {
                $styledHtml = $this->buildEmailTemplate($subject, $htmlBody, $config['from_name'] ?? 'Student Dormitory System');
            } else {
                $styledHtml = $htmlBody;
            }

            $mailer->Body = $styledHtml;
            $mailer->AltBody = $textBody ?? trim(strip_tags($htmlBody));
            $mailer->send();

            return [
                'success' => true,
                'message' => 'Email sent successfully via PHPMailer.',
            ];
        } catch (Exception $e) {
            $errorMessage = $mailer->ErrorInfo ?: $e->getMessage();
            error_log('PHPMailer delivery failed: ' . $errorMessage);

            return [
                'success' => false,
                'message' => 'Email delivery failed: ' . $errorMessage,
            ];
        } catch (\Throwable $e) {
            error_log('Email general error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Unable to send email: ' . $e->getMessage(),
            ];
        }
    }

    private function buildEmailTemplate(string $title, string $content, string $appName): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; color: #333333; }
        .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .email-header { background: #1e3a8a; color: #ffffff; padding: 24px; text-align: center; }
        .email-header h2 { margin: 0; font-size: 20px; font-weight: 600; letter-spacing: 0.5px; }
        .email-body { padding: 30px 24px; line-height: 1.6; font-size: 15px; }
        .email-footer { background: #f8fafc; padding: 18px 24px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h2>' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '</h2>
        </div>
        <div class="email-body">
            ' . $content . '
        </div>
        <div class="email-footer">
            <p style="margin: 0;">This is an automated notification from ' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '.</p>
            <p style="margin: 4px 0 0 0;">Please contact the school / dormitory office if you have any questions.</p>
        </div>
    </div>
</body>
</html>';
    }
}
