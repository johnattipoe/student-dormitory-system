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
use Google\Cloud\Firestore\CollectionReference;

class StudentRepository
{
    private CollectionReference $collection;
    
    public function __construct(private \App\Services\FirebaseService $firebase)
    {
        $this->collection = $firebase->collection('students');
    }
    
    /**
     * Find a student by ID
     */
    public function findById(string $id): ?Student
    {
        $doc = $this->collection->document($id)->snapshot();
        return $doc->exists() ? $this->hydrate($doc) : null;
    }
    
    /**
     * Get all active students
     */
    public function findActive(): array
    {
        $docs = $this->collection
            ->where('status', '==', 'active')
            ->documents();
        
        return array_map(fn($doc) => $this->hydrate($doc), $docs->rows());
    }
    
    /**
     * Find students by house ID
     */
    public function findByHouseId(string $houseId): array
    {
        $docs = $this->collection
            ->where('house_id', '==', $houseId)
            ->documents();
        
        return array_map(fn($doc) => $this->hydrate($doc), $docs->rows());
    }
    
    /**
     * Create a new student document
     */
    public function create(array $data): string
    {
        $docRef = $this->collection->add($data);
        return $docRef->id();
    }
    
    /**
     * Update a student document
     */
    public function update(string $id, array $data): void
    {
        $this->collection->document($id)->update($data);
    }
    
    /**
     * Delete a student document
     */
    public function delete(string $id): void
    {
        $this->collection->document($id)->delete();
    }
    
    /**
     * Hydrate Firestore document into Student model
     */
    private function hydrate($doc): Student
    {
        return new Student($doc->id(), $doc->data());
    }
}
