<?php
/** Normalises and validates a student create request before DTO/service use. */

namespace App\Resources\Requests;

final class CreateStudentRequest
{
    private array $errors = [];

    public function __construct(private readonly array $data) {}

    public function validate(): array
    {
        $validated = $this->validated();
        foreach (['firstName' => 'First name', 'lastName' => 'Last name', 'admissionNo' => 'Admission number'] as $field => $label) {
            if ($validated[$field] === '') $this->errors[$field] = $label . ' is required.';
        }
        if ($validated['email'] === '' || !filter_var($validated['email'], FILTER_VALIDATE_EMAIL)) $this->errors['email'] = 'A valid email address is required.';
        if ($validated['guardianEmail'] !== '' && !filter_var($validated['guardianEmail'], FILTER_VALIDATE_EMAIL)) $this->errors['guardianEmail'] = 'Guardian email must be valid.';
        if ($validated['dateOfBirth'] !== '' && !\DateTimeImmutable::createFromFormat('Y-m-d', $validated['dateOfBirth'])) $this->errors['dateOfBirth'] = 'Date of birth must use YYYY-MM-DD.';
        if (!in_array($validated['status'], ['active', 'inactive', 'suspended'], true)) $this->errors['status'] = 'Choose a valid student status.';

        if ($this->errors !== []) throw new \InvalidArgumentException('Student validation failed: ' . json_encode($this->errors));
        return $validated;
    }

    public function passes(): bool { try { $this->validate(); return true; } catch (\InvalidArgumentException) { return false; } }
    public function errors(): array { return $this->errors; }

    /** Current Firestore field names; accepts matching legacy snake_case input. */
    public function validated(): array
    {
        $get = fn(string $camel, string $snake = ''): mixed => $this->data[$camel] ?? ($snake !== '' ? ($this->data[$snake] ?? null) : null);
        $text = fn(string $camel, string $snake = ''): string => trim((string) ($get($camel, $snake) ?? ''));
        return [
            'firstName' => $text('firstName', 'first_name'), 'lastName' => $text('lastName', 'last_name'),
            'admissionNo' => $text('admissionNo', 'student_id'), 'email' => strtolower($text('email')),
            'phone' => $text('phone'), 'gender' => strtolower($text('gender')), 'dateOfBirth' => $text('dateOfBirth', 'date_of_birth'),
            'class' => $text('class'), 'form' => $text('form', 'level'), 'course' => $text('course'),
            'houseId' => $text('houseId', 'house_id'), 'roomId' => $text('roomId', 'room_id'),
            'guardianName' => $text('guardianName', 'guardian_name'), 'guardianPhone' => $text('guardianPhone', 'guardian_phone'),
            'guardianEmail' => strtolower($text('guardianEmail', 'guardian_email')), 'nhisNumber' => $text('nhisNumber', 'nhis_number'),
            'status' => strtolower($text('status') ?: 'active'),
        ];
    }
}
