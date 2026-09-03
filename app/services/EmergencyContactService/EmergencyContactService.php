<?php

namespace App\Services;

class EmergencyContactService
{
    private FirebaseService $firebase;

    private string $collection = 'emergency_contacts';

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    public function all(): array
    {
        try {
            $contacts = $this->firebase->getCollection($this->collection, [], 500);
            usort($contacts, static fn(array $first, array $second): int => strcmp(
                (string) ($second['createdAt'] ?? ''),
                (string) ($first['createdAt'] ?? '')
            ));
            return $contacts;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function create(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        if ($name === '' || $phone === '') {
            return ['success' => false, 'message' => 'Name and phone number are required.'];
        }

        try {
            $id = $this->firebase->addDocument($this->collection, [
                'name' => $name,
                'phone' => $phone,
                'relationship' => trim((string) ($data['relationship'] ?? '')) ?: 'Emergency contact',
                'role' => trim((string) ($data['role'] ?? '')) ?: 'Support',
                'createdBy' => $data['createdBy'] ?? null,
            ]);
            return ['success' => true, 'message' => 'Emergency contact saved successfully.', 'id' => $id];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Unable to save emergency contact.'];
        }
    }
}
