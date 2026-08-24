<?php

namespace App\Services;

class UserService
{
    private FirebaseService $firebase;

    private string $collection = 'users';

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    /**
     * Get all users.
     */
    public function all(): array
    {
        return $this->firebase->getCollection($this->collection);
    }

    /**
     * Get one user.
     */
    public function find(?string $id): ?array
    {
        if (!$id) {
            return null;
        }

        $target = trim((string) $id);
        if ($target === '') {
            return null;
        }

        try {
            $doc = $this->firebase->getDocument($this->collection, $target);
            if ($doc) {
                return $doc;
            }
        } catch (\Throwable $e) {
            // fall through below to UID lookup
        }

        try {
            foreach ($this->all() as $user) {
                if ((string) ($user['id'] ?? '') === $target || (string) ($user['uid'] ?? '') === $target) {
                    return $user;
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * Count users.
     */
    public function count(): int
    {
        return count($this->all());
    }

    /**
     * Create Firebase Authentication user
     * and Firestore profile.
     */
    public function create(array $data): array
    {
        try {
            $name = trim($data['name'] ?? '');
            $email = trim($data['email'] ?? '');
            $role = trim($data['role'] ?? '');

            if (!$name || !$email || !$role) {
                return [
                    'success' => false,
                    'message' => 'Name, email and role are required.'
                ];
            }

            // Determine password: use provided or generate a temporary one
            $password = $data['password'] ?? null;
            if (empty($password)) {
                $config = require APP_ROOT . '/app/config/app.php';
                $defaultPassword = trim((string) ($config['default_password'] ?? 'Dorm1234'));
                $password = $defaultPassword !== '' ? $defaultPassword : $this->generateTempPassword();
            }

            // Try FirebaseAuthService signup (REST API)
            $createdViaAuth = false;
            try {
                $signup = FirebaseAuthService::signUp($email, $password);
                if ($signup && isset($signup['uid'])) {
                    $uid = $signup['uid'];
                    $createdViaAuth = true;
                } else {
                    // generate a local UID
                    $uid = 'local-' . bin2hex(random_bytes(8));
                }
            } catch (\Throwable $e) {
                $uid = 'local-' . bin2hex(random_bytes(8));
            }

            // Write to Firestore (required, no fallback)
            $emailVerified = array_key_exists('emailVerified', $data)
                ? filter_var($data['emailVerified'], FILTER_VALIDATE_BOOLEAN)
                : true;

            $this->firebase->addDocument(
                $this->collection,
                array_filter([
                    'uid' => $uid,
                    'name' => $name,
                    'email' => $email,
                    'role' => $role,
                    'houseId' => $data['houseId'] ?? null,
                    'status' => $data['status'] ?? 'active',
                    'auth_created' => $createdViaAuth,
                    'temp_password' => $createdViaAuth ? null : $password,
                    'emailVerified' => $emailVerified,
                ], static fn($value) => $value !== null),
                $uid
            );

            return [
                'success' => true,
                'message' => 'User created successfully.',
                'uid' => $uid,
                'auth_created' => $createdViaAuth,
                'temp_password' => $password,
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to create user: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate a human-friendly temporary password.
     */
    private function generateTempPassword(int $length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
        $max = strlen($chars) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, $max)];
        }
        return $out;
    }

    /**
     * Update Firestore user profile.
     */
    public function update(string $id, array $data): array
    {
        try {
            unset($data['password']);
            unset($data['email']);

            $this->firebase->updateDocument(
                $this->collection,
                $id,
                $data
            );

            return [
                'success' => true,
                'message' => 'User updated successfully.'
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to update user: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete Firestore user profile.
     */
    public function delete(string $id): array
    {
        try {
            $this->firebase->deleteDocument(
                $this->collection,
                $id
            );

            return [
                'success' => true,
                'message' => 'User deleted successfully.'
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to delete user: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get users by role.
     */
    public function byRole(string $role): array
    {
        try {
            return $this->firebase->getCollection(
                $this->collection,
                [
                    ['role', '=', $role]
                ]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get users belonging to a house.
     */
    public function byHouse(string $houseId): array
    {
        try {
            return $this->firebase->getCollection(
                $this->collection,
                [
                    ['houseId', '=', $houseId]
                ]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }
}
