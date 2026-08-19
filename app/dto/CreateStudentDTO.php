<?php
/**
 * Example DTO (Data Transfer Object)
 * 
 * DTOs provide type-safe data transfer between layers.
 * They validate input and provide a consistent structure.
 */

namespace App\DTO;

class CreateStudentDTO
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $phone,
        public readonly string $studentId,
        public readonly string $houseId,
        public readonly string $roomId,
        public readonly ?string $medicalInfo = null,
        public readonly array $emergencyContacts = [],
    ) {}
    
    /**
     * Create DTO from form/request data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            firstName: $data['first_name'] ?? '',
            lastName: $data['last_name'] ?? '',
            email: $data['email'] ?? '',
            phone: $data['phone'] ?? '',
            studentId: $data['student_id'] ?? '',
            houseId: $data['house_id'] ?? '',
            roomId: $data['room_id'] ?? '',
            medicalInfo: $data['medical_info'] ?? null,
            emergencyContacts: $data['emergency_contacts'] ?? [],
        );
    }
    
    /**
     * Convert to array for database storage
     */
    public function toArray(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'student_id' => $this->studentId,
            'house_id' => $this->houseId,
            'room_id' => $this->roomId,
            'medical_info' => $this->medicalInfo,
            'emergency_contacts' => $this->emergencyContacts,
            'created_at' => date('c'),
        ];
    }
}
