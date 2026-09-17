<?php

namespace App\Services;

/**
 * Instance-based wrapper around the Firebase Firestore client.
 * Models call FirebaseService::getInstance()->getDocument(...) etc.
 * Singleton keeps a single authenticated Firestore connection per request.
 */
class FirebaseService
{
    private static ?FirebaseService $instance = null;
    private ?object $client = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function client(): object
    {
        if ($this->client === null) {
            $config = require APP_ROOT . '/app/config/firebase/firebase.php';
            $credentialPath = $config['credentials_path'] ?? '';

            if (!is_string($credentialPath) || !file_exists($credentialPath)) {
                throw new \RuntimeException(sprintf(
                    'Firebase credentials file not found: %s',
                    $credentialPath
                ));
            }

            $firestoreClient = '\\Google\\Cloud\\Firestore\\FirestoreClient';
            $this->client = new $firestoreClient([
                'projectId' => $config['project_id'],
                'keyFilePath' => $credentialPath,
            ]);
        }
        return $this->client;
    }

    private function credentialsAvailable(): bool
    {
        $config = require APP_ROOT . '/app/config/firebase/firebase.php';
        $credentialPath = $config['credentials_path'] ?? '';

        return !empty($config['firebase_enabled'])
            && is_string($credentialPath)
            && $credentialPath !== ''
            && file_exists($credentialPath);
    }

    private function credentialsErrorMessage(): string
    {
        $config = require APP_ROOT . '/app/config/firebase/firebase.php';
        $credentialPath = $config['credentials_path'] ?? '';

        if (empty($config['firebase_enabled'])) {
            return 'Firebase is disabled. Set FIREBASE_ENABLED=true in .env.';
        }

        if (!is_string($credentialPath) || $credentialPath === '') {
            return 'Firebase credentials path is not configured. Set FIREBASE_CREDENTIALS or FIREBASE_CREDENTIALS_BASE64 in .env.';
        }

        return sprintf('Firebase credentials file not found: %s', $credentialPath);
    }

    private function requestLimit(int $limit): int
    {
        $requestedLimit = filter_var($_GET['limit'] ?? null, FILTER_VALIDATE_INT);
        if ($requestedLimit !== false && $requestedLimit > 0) {
            $limit = min(150, max(1, $requestedLimit));
        }

        return $limit;
    }

    private function requestOffset(int $limit, int $offset): int
    {
        $requestedOffset = filter_var($_GET['offset'] ?? null, FILTER_VALIDATE_INT);
        if ($requestedOffset !== false && $requestedOffset >= 0) {
            return $requestedOffset;
        }

        $requestedPage = filter_var($_GET['page'] ?? null, FILTER_VALIDATE_INT);
        if ($requestedPage !== false && $requestedPage > 1) {
            return ($requestedPage - 1) * $limit;
        }

        return $offset;
    }

    /** Fetch a single document as an assoc array (with 'id'), or null if it doesn't exist. */
    public function getDocument(string $collection, string $id): ?array
    {
        if (!$this->credentialsAvailable()) {
            throw new \RuntimeException($this->credentialsErrorMessage());
        }

        $doc = $this->client()->collection($collection)->document($id)->snapshot();
        if (!$doc->exists()) return null;
        return array_merge(['id' => $doc->id()], $doc->data());
    }

    /**
     * Fetch documents from a collection, optionally filtered.
     * $wheres: array of [field, operator, value] triples, e.g. [['role', '=', 'student']]
     */
    public function getCollection(string $collection, array $wheres = [], int $limit = 150, int $offset = 0): array
    {
        if (!$this->credentialsAvailable()) {
            throw new \RuntimeException($this->credentialsErrorMessage());
        }

        $limit = $this->requestLimit($limit);
        $offset = $this->requestOffset($limit, $offset);

        $query = $this->client()->collection($collection);
        foreach ($wheres as [$field, $op, $value]) {
            $query = $query->where($field, $op, $value);
        }

        $fetchLimit = $offset > 0 ? $offset + $limit : $limit;
        $query = $query->limit($fetchLimit);

        $out = [];
        $seen = 0;
        foreach ($query->documents() as $doc) {
            if (!$doc->exists()) {
                continue;
            }

            if ($offset > 0 && $seen < $offset) {
                $seen++;
                continue;
            }

            $out[] = array_merge(['id' => $doc->id()], $doc->data());
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** Efficiently count documents matching optional filters without loading the full collection. */
    public function count(string $collection, array $wheres = []): int
    {
        if (!$this->credentialsAvailable()) {
            throw new \RuntimeException($this->credentialsErrorMessage());
        }

        $query = $this->client()->collection($collection);
        foreach ($wheres as [$field, $op, $value]) {
            $query = $query->where($field, $op, $value);
        }

        return (int) $query->count();
    }

    /** Efficiently sum a numeric field across matching documents without loading the full collection. */
    public function sum(string $collection, string $field, array $wheres = []): int|float
    {
        if (!$this->credentialsAvailable()) {
            throw new \RuntimeException($this->credentialsErrorMessage());
        }

        $query = $this->client()->collection($collection);
        foreach ($wheres as [$fieldName, $op, $value]) {
            $query = $query->where($fieldName, $op, $value);
        }

        return $query->sum($field);
    }

    /** Create a document. Returns its generated (or given) id. */
    public function addDocument(string $collection, array $data, ?string $id = null): string
    {
        if (!$this->credentialsAvailable()) {
            throw new \RuntimeException($this->credentialsErrorMessage());
        }

        $data['createdAt'] = $this->now();
        $data['updatedAt'] = $this->now();

        if ($id) {
            $this->client()->collection($collection)->document($id)->set($data);
            return $id;
        }
        $ref = $this->client()->collection($collection)->newDocument();
        $ref->set($data);
        return $ref->id();
    }

    public function updateDocument(string $collection, string $id, array $data): void
    {
        if (!$this->credentialsAvailable()) {
            throw new \RuntimeException($this->credentialsErrorMessage());
        }

        $data['updatedAt'] = $this->now();

        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = ['path' => $key, 'value' => $value];
        }
        $this->client()->collection($collection)->document($id)->update($fields);
    }

    public function deleteDocument(string $collection, string $id): void
    {
        if (!$this->credentialsAvailable()) {
            throw new \RuntimeException($this->credentialsErrorMessage());
        }

        $this->client()->collection($collection)->document($id)->delete();
    }

    private function now(): string
    {
        return (new \DateTime())->format(DATE_ATOM);
    }

    /**
     * Convenience alias matching the flat where() shape used by the earlier
     * *Service classes (StudentService, RoomService, etc).
     */
    public function where(string $collection, string $field, string $op, mixed $value, int $limit = 150, int $offset = 0): array
    {
        return $this->getCollection($collection, [[$field, $op, $value]], $limit, $offset);
    }
}
