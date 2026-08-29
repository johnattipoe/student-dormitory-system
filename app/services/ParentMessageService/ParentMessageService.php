<?php

namespace App\Services;

class ParentMessageService
{
    public function all(): array
    {
        try {
            return FirebaseService::getInstance()->getCollection(\COL_PARENT_MESSAGES);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function find(string $id): ?array
    {
        if ($id === '') {
            return null;
        }

        try {
            return FirebaseService::getInstance()->getDocument(\COL_PARENT_MESSAGES, $id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function update(string $id, string $subject, string $message): array
    {
        if ($id === '' || trim($subject) === '' || trim($message) === '') {
            return ['success' => false, 'message' => 'Subject and message are required.'];
        }

        try {
            FirebaseService::getInstance()->updateDocument(\COL_PARENT_MESSAGES, $id, [
                'subject' => trim($subject),
                'message' => trim($message),
            ]);
            return ['success' => true, 'message' => 'Parent message updated successfully.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Unable to update parent message.'];
        }
    }

    public function delete(string $id): array
    {
        if ($id === '') {
            return ['success' => false, 'message' => 'Message ID is required.'];
        }

        try {
            FirebaseService::getInstance()->deleteDocument(\COL_PARENT_MESSAGES, $id);
            return ['success' => true, 'message' => 'Parent message deleted successfully.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Unable to delete parent message.'];
        }
    }

    public function send(array $student, string $subject, string $message, array $sender, string $channel = 'mail'): array
    {
        $channel = $channel === 'sms' ? 'sms' : 'mail';
        $studentId = (string) ($student['id'] ?? '');
        $guardianName = trim((string) ($student['guardianName'] ?? ''));
        $guardianPhone = trim((string) ($student['guardianPhone'] ?? ''));
        $guardianEmail = trim((string) ($student['guardianEmail'] ?? ''));

        if ($studentId === '' || $guardianName === '') {
            return ['success' => false, 'message' => 'This student does not have a parent or guardian name.'];
        }

        if (trim($subject) === '' || trim($message) === '') {
            return ['success' => false, 'message' => 'Subject and message are required.'];
        }

        if ($channel === 'mail' && $guardianEmail === '') {
            return ['success' => false, 'message' => 'This parent does not have an email address.'];
        }

        if ($channel === 'sms' && $guardianPhone === '') {
            return ['success' => false, 'message' => 'This parent does not have a phone number.'];
        }

        if ($channel === 'sms' && mb_strlen($message) > 160) {
            return ['success' => false, 'message' => 'SMS messages must be 160 characters or fewer.'];
        }

        $data = [
            'studentId' => $studentId,
            'studentName' => trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')),
            'admissionNo' => $student['admissionNo'] ?? '',
            'guardianName' => $guardianName,
            'guardianPhone' => $guardianPhone,
            'guardianEmail' => $guardianEmail,
            'subject' => trim($subject),
            'message' => trim($message),
            'sentBy' => $sender['uid'] ?? $sender['id'] ?? '',
            'sentByName' => $sender['name'] ?? $sender['email'] ?? 'Staff',
            'sentByRole' => $sender['role'] ?? '',
            'createdAt' => (new \DateTime())->format(DATE_ATOM),
            'channel' => $channel,
            'deliveryStatus' => $channel === 'sms' ? 'not_configured' : 'pending',
            'emailStatus' => $channel === 'mail' ? 'pending' : 'not_sent',
        ];

        $id = FirebaseService::getInstance()->addDocument(\COL_PARENT_MESSAGES, $data);

        if ($channel === 'mail') {
            // Use SendGrid Web API if configured, otherwise fall back to SMTP
            $emailService = class_exists(SendGridEmailService::class) ? new SendGridEmailService() : new EmailService();
            
            // Only try SendGrid if it's properly configured
            if ($emailService instanceof SendGridEmailService && !$emailService->isConfigured()) {
                $emailService = new EmailService();
            }

            $emailResult = $emailService->sendHtml(
                $guardianEmail,
                $subject,
                '<p>Dear ' . htmlspecialchars($guardianName, ENT_QUOTES, 'UTF-8') . ',</p><p>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>'
            );
            $data['emailStatus'] = $emailResult['success'] ? 'sent' : 'failed';
            $data['deliveryStatus'] = $data['emailStatus'];
            $data['deliveryNote'] = $emailResult['message'] ?? '';
            FirebaseService::getInstance()->updateDocument(\COL_PARENT_MESSAGES, $id, [
                'emailStatus' => $data['emailStatus'],
                'deliveryStatus' => $data['deliveryStatus'],
                'deliveryNote' => $data['deliveryNote'],
            ]);
        }

        return [
            'success' => true,
            'message' => $channel === 'sms'
                ? 'SMS recorded. Configure an SMS provider to deliver it.'
                : ($data['emailStatus'] === 'sent'
                ? 'Message recorded and successfully emailed to ' . $guardianEmail . '.'
                : 'Message recorded. Note: ' . ($data['deliveryNote'] ?? 'Email not sent.')),
            'id' => $id,
        ];
    }
}