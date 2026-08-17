<?php

namespace App\Services;

use Google\Cloud\Firestore\FirestoreClient;

/**
 * Instance-based wrapper around the Firebase Firestore client.
 * Models call FirebaseService::getInstance()->getDocument(...) etc.
 * Singleton keeps a single authenticated Firestore connection per request.
 */
class FirebaseService
{
    private static ?FirebaseService $instance = null;
    private ?FirestoreClient $client = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function client(): FirestoreClient
    {
        if ($this->client === null) {
            $config = require APP_ROOT . '/app/config/firebase.php';
            $credentialPath = $config['credentials_path'] ?? '';

            if (!is_string($credentialPath) || !file_exists($credentialPath)) {
                throw new \RuntimeException(sprintf(
                    'Firebase credentials file not found: %s',
                    $credentialPath
                ));
            }

            $this->client = new FirestoreClient([
                'projectId' => $config['project_id'],
                'keyFilePath' => $credentialPath,
            ]);
        }
        return $this->client;
    }

    private function credentialsAvailable(): bool
    {
        $appConfig = require APP_ROOT . '/app/config/app.php';
        if (empty($appConfig['firebase_enabled'])) {
            return false;
        }

        $config = require APP_ROOT . '/app/config/firebase.php';
        $credentialPath = $config['credentials_path'] ?? '';

        return is_string($credentialPath) && file_exists($credentialPath);
    }

    /** Fetch a single document as an assoc array (with 'id'), or null if it doesn't exist. */
    public function getDocument(string $collection, string $id): ?array
    {
        if (!$this->credentialsAvailable()) {
            throw new \RuntimeException(
                'Firebase credentials not available. Ensure FIREBASE_ENABLED=true and valid credentials file exists.'
            );
        }

        $doc = $this->client()->collection($collection)->document($id)->snapshot();
        if (!$doc->exists()) return null;
        return array_merge(['id' => $doc->id()], $doc->data());
    }

    /**
     * Fetch documents from a collection, optionally filtered.
     * $wheres: array of [field, operator, value] triples, e.g. [['role', '=', 'student']]
     */
    public function getCollection(string $collection, array $wheres = [], int $limit = 500): array
    {
        if (!$this->credentialsAvailable()) {
            throw new \RuntimeException(
                'Firebase credentials not available. Ensure FIREBASE_ENABLED=true and valid credentials file exists.'
            );
        }

        $query = $this->client()->collection($collection);
        foreach ($wheres as [$field, $op, $value]) {
            $query = $query->where($field, $op, $value);
        }
        $query = $query->limit($limit);

        $out = [];
        foreach ($query->documents() as $doc) {
            if ($doc->exists()) {
                $out[] = array_merge(['id' => $doc->id()], $doc->data());
            }
        }
        return $out;
    }

    /** Create a document. Returns its generated (or given) id. */
    public function addDocument(string $collection, array $data, ?string $id = null): string
    {
        if (!$this->credentialsAvailable()) {
            throw new \RuntimeException(
                'Firebase credentials not available. Ensure FIREBASE_ENABLED=true and valid credentials file exists.'
            );
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
            throw new \RuntimeException(
                'Firebase credentials not available. Ensure FIREBASE_ENABLED=true and valid credentials file exists.'
            );
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
            throw new \RuntimeException(
                'Firebase credentials not available. Ensure FIREBASE_ENABLED=true and valid credentials file exists.'
            );
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
    public function where(string $collection, string $field, string $op, $value, int $limit = 500): array
    {
        return $this->getCollection($collection, [[$field, $op, $value]], $limit);
    }
}
