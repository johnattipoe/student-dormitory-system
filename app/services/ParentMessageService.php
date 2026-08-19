<?php

namespace App\Services;

class ParentMessageService
{
    public function send(array $student, string $subject, string $message, array $sender): array
    {
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
            'emailStatus' => 'not_configured',
        ];

        $id = FirebaseService::getInstance()->addDocument(\COL_PARENT_MESSAGES, $data);

        if ($guardianEmail !== '') {
            $emailResult = (new EmailService())->sendHtml(
                $guardianEmail,
                $subject,
                '<p>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>'
            );
            $data['emailStatus'] = $emailResult['success'] ? 'sent' : 'failed';
            FirebaseService::getInstance()->updateDocument(\COL_PARENT_MESSAGES, $id, [
                'emailStatus' => $data['emailStatus'],
            ]);
        }

        return [
            'success' => true,
            'message' => $data['emailStatus'] === 'sent'
                ? 'Message recorded and emailed to the parent.'
                : 'Message recorded for the parent.' . ($guardianEmail === '' ? ' Add a guardian email to enable email delivery.' : ''),
            'id' => $id,
        ];
    }
}