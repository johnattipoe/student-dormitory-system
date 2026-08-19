<?php

namespace App\Services;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class EmailService
{
    public function isConfigured(): bool
    {
        $config = require APP_ROOT . '/app/config/mail.php';

        return !empty($config['enabled'])
            && $config['host'] !== ''
            && $config['from_address'] !== '';
    }

    public function sendHtml(
        string $recipient,
        string $subject,
        string $htmlBody,
        ?string $textBody = null
    ): array {
        $config = require APP_ROOT . '/app/config/mail.php';

        if (empty($config['enabled'])) {
            return [
                'success' => false,
                'message' => 'Email sending is disabled. Set MAIL_ENABLED=true to enable it.',
            ];
        }

        if ($config['host'] === '' || $config['from_address'] === '') {
            return [
                'success' => false,
                'message' => 'Mail configuration is incomplete.',
            ];
        }

        try {
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $config['host'];
            $mailer->Port = $config['port'];
            $mailer->SMTPAuth = $config['username'] !== '';
            $mailer->Username = $config['username'];
            $mailer->Password = $config['password'];

            if ($config['encryption'] === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($config['encryption'] === 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom($config['from_address'], $config['from_name']);
            $mailer->addAddress($recipient);
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $htmlBody;
            $mailer->AltBody = $textBody ?? trim(strip_tags($htmlBody));
            $mailer->send();

            return [
                'success' => true,
                'message' => 'Email sent successfully.',
            ];
        } catch (Exception $e) {
            error_log('Email delivery failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Unable to send email.',
            ];
        }
    }
}
