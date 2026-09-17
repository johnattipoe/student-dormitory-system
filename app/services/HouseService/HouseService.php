<?php

namespace App\Services;

class HouseService
{
    public static function all(int $limit = 150): array
    {
        return FirebaseService::getInstance()->getCollection(\COL_HOUSES, [], $limit);
    }

    public static function find(string $id): ?array
    {
        return FirebaseService::getInstance()->getDocument(\COL_HOUSES, $id);
    }

    public static function create(array $data): string
    {
        return FirebaseService::getInstance()->addDocument(\COL_HOUSES, [
            'name'           => $data['name'] ?? '',
            'gender'         => $data['gender'] ?? '',
            'capacity'       => (int) ($data['capacity'] ?? 0),
            'houseMasterId'  => $data['houseMasterId'] ?? null,
            'houseMistressId' => $data['houseMistressId'] ?? null,
            'location'       => $data['location'] ?? '',
            'status'         => $data['status'] ?? 'active',
        ]);
    }

    public static function update(string $id, array $data): void
    {
        FirebaseService::getInstance()->updateDocument(\COL_HOUSES, $id, $data);
    }

    public static function delete(string $id): void
    {
        FirebaseService::getInstance()->deleteDocument(\COL_HOUSES, $id);
    }
}
