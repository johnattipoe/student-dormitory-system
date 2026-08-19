<?php
/**
 * Example Repository - Data Access Layer
 * 
 * Repositories abstract Firestore queries and provide a clean interface
 * to the rest of the application. Services use repositories instead of
 * directly querying models.
 */

namespace App\Repositories;

use App\Models\Student;
use App\Services\FirebaseService;

/**
 * Example Repository - Wraps data access for Student entities
 * 
 * In production, inject FirebaseService via dependency injection
 * and call its methods: getDocument(), getCollection(), addDocument(), etc.
 */
class StudentRepository
{
    private FirebaseService $firebase;
    
    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }
    
    /**
     * Find a student by ID
     */
    public function findById(string $id): ?array
    {
        return $this->firebase->getDocument('students', $id);
    }
    
    /**
     * Get all active students
     */
    public function findActive(): array
    {
        return $this->firebase->where('students', 'status', '==', 'active');
    }
    
    /**
     * Find students by house ID
     */
    public function findByHouseId(string $houseId): array
    {
        return $this->firebase->where('students', 'house_id', '==', $houseId);
    }
    
    /**
     * Create a new student document
     */
    public function create(array $data): string
    {
        return $this->firebase->addDocument('students', $data);
    }
    
    /**
     * Update a student document
     */
    public function update(string $id, array $data): void
    {
        $this->firebase->updateDocument('students', $id, $data);
    }
    
    /**
     * Delete a student document
     */
    public function delete(string $id): void
    {
        $this->firebase->deleteDocument('students', $id);
    }
}
