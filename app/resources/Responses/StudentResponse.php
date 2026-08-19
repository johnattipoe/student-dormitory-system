<?php
/**
 * Example Response - Transform data for consistent output format
 * 
 * Responses format models/data into consistent JSON or HTML structures.
 * Use in API endpoints and complex view rendering.
 */

namespace App\Resources\Responses;

class StudentResponse
{
    public function __construct(private array $student) {}
    
    /**
     * Convert student model to API response format
     */
    public function toArray(): array
    {
        return [
            'id' => $this->student['id'] ?? null,
            'name' => $this->formatName(),
            'email' => $this->student['email'] ?? null,
            'student_id' => $this->student['student_id'] ?? null,
            'house' => $this->student['house_id'] ?? null,
            'room' => $this->student['room_id'] ?? null,
            'status' => $this->student['status'] ?? 'active',
            'created_at' => $this->student['created_at'] ?? null,
        ];
    }
    
    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
    
    /**
     * Collection response
     */
    public static function collection(array $students): array
    {
        return array_map(fn($student) => (new self($student))->toArray(), $students);
    }
    
    private function formatName(): string
    {
        $first = $this->student['first_name'] ?? '';
        $last = $this->student['last_name'] ?? '';
        return trim("$first $last");
    }
}
