<?php

namespace App\DTO;

/**
 * Typed student-create payload aligned with the Firestore students collection.
 */
class CreateStudentDTO
{
    /** @var string */
    public $firstName;

    /** @var string */
    public $lastName;

    /** @var string */
    public $admissionNo;

    /** @var string */
    public $email;

    /** @var string */
    public $phone;

    /** @var string */
    public $gender;

    /** @var string */
    public $dateOfBirth;

    /** @var string */
    public $class;

    /** @var string */
    public $form;

    /** @var string */
    public $course;

    /** @var string|null */
    public $houseId;

    /** @var string|null */
    public $roomId;

    /** @var string */
    public $guardianName;

    /** @var string */
    public $guardianPhone;

    /** @var string */
    public $guardianEmail;

    /** @var string */
    public $nhisNumber;

    /** @var string */
    public $status;

    /** @var array */
    public $emergencyContacts;

    /** @var array */
    public $metadata;

    public function __construct(array $data = [])
    {
        $this->firstName = (string) ($data['firstName'] ?? '');
        $this->lastName = (string) ($data['lastName'] ?? '');
        $this->admissionNo = (string) ($data['admissionNo'] ?? '');
        $this->email = (string) ($data['email'] ?? '');
        $this->phone = (string) ($data['phone'] ?? '');
        $this->gender = (string) ($data['gender'] ?? '');
        $this->dateOfBirth = (string) ($data['dateOfBirth'] ?? '');
        $this->class = (string) ($data['class'] ?? '');
        $this->form = (string) ($data['form'] ?? '');
        $this->course = (string) ($data['course'] ?? '');
        $this->houseId = isset($data['houseId']) && $data['houseId'] !== '' ? (string) $data['houseId'] : null;
        $this->roomId = isset($data['roomId']) && $data['roomId'] !== '' ? (string) $data['roomId'] : null;
        $this->guardianName = (string) ($data['guardianName'] ?? '');
        $this->guardianPhone = (string) ($data['guardianPhone'] ?? '');
        $this->guardianEmail = (string) ($data['guardianEmail'] ?? '');
        $this->nhisNumber = (string) ($data['nhisNumber'] ?? '');
        $this->status = (string) ($data['status'] ?? 'active');
        $this->emergencyContacts = is_array($data['emergencyContacts'] ?? null) ? $data['emergencyContacts'] : [];
        $this->metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
    }

    /**
     * Accepts both current camelCase form fields and legacy snake_case input.
     */
    public static function fromArray(array $data): self
    {
        $value = static function (string $camel, string $snake = '') use ($data) {
            return $data[$camel] ?? ($snake !== '' ? ($data[$snake] ?? null) : null);
        };

        $houseIdVal = trim((string) ($value('houseId', 'house_id') ?? ''));
        $roomIdVal = trim((string) ($value('roomId', 'room_id') ?? ''));
        $statusVal = strtolower(trim((string) ($value('status') ?? 'active')));

        return new self([
            'firstName' => trim((string) $value('firstName', 'first_name')),
            'lastName' => trim((string) $value('lastName', 'last_name')),
            'admissionNo' => trim((string) ($value('admissionNo', 'student_id') ?? '')),
            'email' => strtolower(trim((string) ($value('email') ?? ''))),
            'phone' => trim((string) ($value('phone') ?? '')),
            'gender' => strtolower(trim((string) ($value('gender') ?? ''))),
            'dateOfBirth' => trim((string) ($value('dateOfBirth', 'date_of_birth') ?? '')),
            'class' => trim((string) ($value('class') ?? '')),
            'form' => trim((string) ($value('form', 'level') ?? '')),
            'course' => trim((string) ($value('course') ?? '')),
            'houseId' => $houseIdVal !== '' ? $houseIdVal : null,
            'roomId' => $roomIdVal !== '' ? $roomIdVal : null,
            'guardianName' => trim((string) ($value('guardianName', 'guardian_name') ?? '')),
            'guardianPhone' => trim((string) ($value('guardianPhone', 'guardian_phone') ?? '')),
            'guardianEmail' => strtolower(trim((string) ($value('guardianEmail', 'guardian_email') ?? ''))),
            'nhisNumber' => trim((string) ($value('nhisNumber', 'nhis_number') ?? '')),
            'status' => $statusVal !== '' ? $statusVal : 'active',
            'emergencyContacts' => is_array($value('emergencyContacts', 'emergency_contacts')) ? $value('emergencyContacts', 'emergency_contacts') : [],
            'metadata' => is_array($value('metadata')) ? $value('metadata') : [],
        ]);
    }

    /**
     * Safe payload for StudentService::create().
     */
    public function toStudentPayload(): array
    {
        return [
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'admissionNo' => $this->admissionNo,
            'email' => $this->email,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'dateOfBirth' => $this->dateOfBirth,
            'class' => $this->class,
            'form' => $this->form,
            'level' => $this->form,
            'course' => $this->course,
            'houseId' => $this->houseId,
            'roomId' => $this->roomId,
            'guardianName' => $this->guardianName,
            'guardianPhone' => $this->guardianPhone,
            'guardianEmail' => $this->guardianEmail,
            'nhisNumber' => $this->nhisNumber,
            'status' => $this->status,
            'emergencyContacts' => $this->emergencyContacts,
            'metadata' => $this->metadata,
        ];
    }

    public function fullName()
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }
}
