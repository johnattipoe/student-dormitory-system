<?php
/** Consistent public-facing representation of a student record. */

namespace App\Resources\Responses;

final class StudentResponse
{
    public function __construct(private readonly array $student) {}

    public function toArray(bool $includePrivateContactData = false): array
    {
        $data = [
            'id' => $this->student['id'] ?? null,
            'admissionNo' => $this->value('admissionNo', 'student_id'),
            'firstName' => $this->value('firstName', 'first_name'),
            'lastName' => $this->value('lastName', 'last_name'),
            'name' => $this->fullName(),
            'email' => $this->value('email'), 'phone' => $this->value('phone'),
            'gender' => $this->value('gender'), 'dateOfBirth' => $this->value('dateOfBirth', 'date_of_birth'),
            'class' => $this->value('class'), 'form' => $this->value('form', 'level'), 'course' => $this->value('course'),
            'houseId' => $this->value('houseId', 'house_id'), 'roomId' => $this->value('roomId', 'room_id'),
            'status' => $this->value('status') ?: 'active',
            'createdAt' => $this->value('createdAt', 'created_at'), 'updatedAt' => $this->value('updatedAt', 'updated_at'),
        ];

        if ($includePrivateContactData) {
            $data['guardian'] = ['name' => $this->value('guardianName', 'guardian_name'), 'phone' => $this->value('guardianPhone', 'guardian_phone'), 'email' => $this->value('guardianEmail', 'guardian_email')];
            $data['nhisNumber'] = $this->value('nhisNumber', 'nhis_number');
        }
        return $data;
    }

    public function toJson(bool $includePrivateContactData = false): string { return json_encode($this->toArray($includePrivateContactData), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES); }
    public static function collection(array $students, bool $includePrivateContactData = false): array { return array_map(static fn(array $student): array => (new self($student))->toArray($includePrivateContactData), $students); }
    public function fullName(): string { return trim($this->value('firstName', 'first_name') . ' ' . $this->value('lastName', 'last_name')); }
    private function value(string $camel, string $snake = ''): mixed { return $this->student[$camel] ?? ($snake !== '' ? ($this->student[$snake] ?? null) : null); }
}
