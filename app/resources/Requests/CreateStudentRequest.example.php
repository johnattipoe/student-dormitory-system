<?php
/**
 * Example Request - Validate incoming form/API data
 * 
 * Requests are the first layer of validation and transformation.
 * Controllers use them to ensure data is valid before passing to services.
 */

namespace App\Resources\Requests;

class CreateStudentRequest
{
    public function __construct(private array $data) {}
    
    /**
     * Validate the request data
     */
    public function validate(): array
    {
        $errors = [];
        
        // Validate first name
        if (empty($this->data['first_name'])) {
            $errors['first_name'] = 'First name is required';
        }
        
        // Validate email
        if (empty($this->data['email']) || !filter_var($this->data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required';
        }
        
        // Validate student ID
        if (empty($this->data['student_id'])) {
            $errors['student_id'] = 'Student ID is required';
        }
        
        if (!empty($errors)) {
            throw new \App\Exceptions\ValidationException($errors);
        }
        
        return $this->validated();
    }
    
    /**
     * Return validated data
     */
    public function validated(): array
    {
        return [
            'first_name' => trim($this->data['first_name'] ?? ''),
            'last_name' => trim($this->data['last_name'] ?? ''),
            'email' => strtolower(trim($this->data['email'] ?? '')),
            'phone' => $this->data['phone'] ?? '',
            'student_id' => $this->data['student_id'] ?? '',
            'house_id' => $this->data['house_id'] ?? '',
            'room_id' => $this->data['room_id'] ?? '',
        ];
    }
}
