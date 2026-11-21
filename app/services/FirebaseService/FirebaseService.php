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
    private ?object $storageClient = null;
    private ?bool $readDiagnosticsEnabled = null;
    private bool $readDiagnosticsRegistered = false;
    private array $readDiagnostics = [];

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

    private function diagnosticsEnabled(): bool
    {
        if ($this->readDiagnosticsEnabled === null) {
            $config = require APP_ROOT . '/app/config/firebase/firebase.php';
            $this->readDiagnosticsEnabled = !empty($config['read_diagnostics']);
        }

        return $this->readDiagnosticsEnabled;
    }

    private function recordCollectionRead(string $collection, int $documentsRead, int $documentsReturned): void
    {
        if (!$this->diagnosticsEnabled()) {
            return;
        }

        if (!$this->readDiagnosticsRegistered) {
            register_shutdown_function([$this, 'writeReadDiagnostics']);
            $this->readDiagnosticsRegistered = true;
        }

        if (!isset($this->readDiagnostics[$collection])) {
            $this->readDiagnostics[$collection] = [
                'queries' => 0,
                'documentReads' => 0,
                'documentsReturned' => 0,
                'aggregateQueries' => 0,
            ];
        }

        $this->readDiagnostics[$collection]['queries']++;
        $this->readDiagnostics[$collection]['documentReads'] += $documentsRead;
        $this->readDiagnostics[$collection]['documentsReturned'] += $documentsReturned;
    }

    private function recordAggregateQuery(string $collection): void
    {
        if (!$this->diagnosticsEnabled()) {
            return;
        }

        if (!$this->readDiagnosticsRegistered) {
            register_shutdown_function([$this, 'writeReadDiagnostics']);
            $this->readDiagnosticsRegistered = true;
        }

        if (!isset($this->readDiagnostics[$collection])) {
            $this->readDiagnostics[$collection] = [
                'queries' => 0,
                'documentReads' => 0,
                'documentsReturned' => 0,
                'aggregateQueries' => 0,
            ];
        }

        $this->readDiagnostics[$collection]['queries']++;
        $this->readDiagnostics[$collection]['aggregateQueries']++;
    }

    public function writeReadDiagnostics(): void
    {
        if (empty($this->readDiagnostics)) {
            return;
        }

        $route = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $summary = [
            'route' => is_string($route) ? $route : '',
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
            'collections' => $this->readDiagnostics,
        ];
        error_log('Firestore read diagnostics: ' . json_encode(
            $summary,
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        ));
    }

    private function applyWheres(object $query, array $wheres): object
    {
        foreach ($wheres as [$field, $op, $value]) {
            $query = $query->where($field, $op, $value);
        }

        return $query;
    }

    private function storageClient(): object
    {
        if ($this->storageClient === null) {
            $config = require APP_ROOT . '/app/config/firebase/firebase.php';
            $endpoint = trim((string) ($config['r2_endpoint'] ?? ''));
            $accessKey = trim((string) ($config['r2_access_key_id'] ?? ''));
            $secretKey = trim((string) ($config['r2_secret_access_key'] ?? ''));
            if ($endpoint === '' || $accessKey === '' || $secretKey === '') {
                throw new \RuntimeException('Cloudflare R2 is not configured. Set R2_ENDPOINT, R2_ACCESS_KEY_ID, and R2_SECRET_ACCESS_KEY in .env.');
            }

            $s3Client = '\\Aws\\S3\\S3Client';
            $this->storageClient = new $s3Client([
                'version' => 'latest',
                'region' => 'auto',
                'endpoint' => rtrim($endpoint, '/'),
                'credentials' => [
                    'key' => $accessKey,
                    'secret' => $secretKey,
                ],
            ]);
        }

        return $this->storageClient;
    }

    public function uploadStorageFile(string $localPath, string $objectName, string $contentType): string
    {
        $config = require APP_ROOT . '/app/config/firebase/firebase.php';
        $bucket = trim((string) ($config['r2_bucket'] ?? ''));
        if ($bucket === '' || !is_file($localPath)) {
            throw new \RuntimeException('Unable to upload gallery image to Cloudflare R2.');
        }

        $this->storageClient()->putObject([
            'Bucket' => $bucket,
            'Key' => ltrim($objectName, '/'),
            'SourceFile' => $localPath,
            'ContentType' => $contentType,
        ]);

        return ltrim($objectName, '/');
    }

    public function storageUrl(string $objectName): string
    {
        if ($objectName === '') {
            return '';
        }

        $config = require APP_ROOT . '/app/config/firebase/firebase.php';
        $publicUrl = rtrim((string) ($config['r2_public_url'] ?? ''), '/');
        if ($publicUrl !== '') {
            return $publicUrl . '/' . str_replace('%2F', '/', rawurlencode(ltrim($objectName, '/')));
        }

        $command = $this->storageClient()->getCommand('GetObject', [
            'Bucket' => (string) ($config['r2_bucket'] ?? ''),
            'Key' => ltrim($objectName, '/'),
        ]);
        return (string) $this->storageClient()->createPresignedRequest($command, '+1 day')->getUri();
    }

    public function deleteStorageFile(string $objectName): void
    {
        if ($objectName === '') {
            return;
        }

        $config = require APP_ROOT . '/app/config/firebase/firebase.php';
        $this->storageClient()->deleteObject([
            'Bucket' => (string) ($config['r2_bucket'] ?? ''),
            'Key' => ltrim($objectName, '/'),
        ]);
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

    private function isAuthenticationFailure(\Throwable $exception): bool
    {
        do {
            $message = strtoupper($exception->getMessage());
            if ($exception->getCode() === 16
                || str_contains($message, 'ACCESS_TOKEN_EXPIRED')
                || str_contains($message, 'UNAUTHENTICATED')) {
                return true;
            }
            $exception = $exception->getPrevious();
        } while ($exception !== null);

        return false;
    }

    private function withAuthenticationRefresh(callable $request): mixed
    {
        try {
            return $request($this->client());
        } catch (\Throwable $exception) {
            if (!$this->isAuthenticationFailure($exception)) {
                throw $exception;
            }

            $this->client = null;
            return $request($this->client());
        }
    }

    /** Fetch a single document as an assoc array (with 'id'), or null if it does not exist. */
    public function getDocument(string $collection, string $id): ?array
    {
        if (!$this->credentialsAvailable()) {
            throw new \RuntimeException($this->credentialsErrorMessage());
        }

        $doc = $this->client()->collection($collection)->document($id)->snapshot();
        if (!$doc->exists()) {
            $this->recordCollectionRead($collection, 1, 0);
            return null;
        }
        $this->recordCollectionRead($collection, 1, 1);
        return array_merge(['id' => $doc->id()], $doc->data());
    }

    /**
     * Fetch documents from a collection, optionally filtered.
     * $wheres: array of [field, operator, value] triples, e.g. [['role', '=', 'student']]
     */
    public function getCollection(string $collection, array $wheres = [], int $limit = 150, int $offset = 0, bool $respectRequestPagination = true): array
    {
        if (!$this->credentialsAvailable()) {
            throw new \RuntimeException($this->credentialsErrorMessage());
        }

        if ($respectRequestPagination) {
            $limit = $this->requestLimit($limit);
            $offset = $this->requestOffset($limit, $offset);
        } else {
            $limit = max(1, min(150, $limit));
            $offset = max(0, $offset);
        }

        $fetchLimit = $offset > 0 ? $offset + $limit : $limit;
        return $this->withAuthenticationRefresh(function ($client) use ($collection, $wheres, $fetchLimit, $offset, $limit): array {
            $query = $this->applyWheres($client->collection($collection), $wheres)->limit($fetchLimit);

            $out = [];
            $seen = 0;
            $documentsRead = 0;
            foreach ($query->documents() as $doc) {
                $documentsRead++;
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

            $this->recordCollectionRead($collection, $documentsRead, count($out));
            return $out;
        });
    }

    /**
     * Fetch every document matching optional filters using the SDK's streaming iterator.
     * Unlike repeated offset queries, each document is visited only once.
     *
     * @param array<int,array{0:string,1:string,2:mixed}> $wheres
     * @return array<int,array<string,mixed>>
     */
    public function getAllCollection(string $collection, array $wheres = []): array
    {
        if (!$this->credentialsAvailable()) {
            throw new \RuntimeException($this->credentialsErrorMessage());
        }

        return $this->withAuthenticationRefresh(function ($client) use ($collection, $wheres): array {
            $query = $this->applyWheres($client->collection($collection), $wheres);
            $out = [];
            $documentsRead = 0;

            foreach ($query->documents() as $doc) {
                $documentsRead++;
                if (!$doc->exists()) {
                    continue;
                }

                $out[] = array_merge(['id' => $doc->id()], $doc->data());
            }

            $this->recordCollectionRead($collection, $documentsRead, count($out));
            return $out;
        });
    }

    /** Efficiently count documents matching optional filters without loading the full collection. */
    public function count(string $collection, array $wheres = []): int
    {
        if (!$this->credentialsAvailable()) {
            throw new \RuntimeException($this->credentialsErrorMessage());
        }

        $query = $this->applyWheres($this->client()->collection($collection), $wheres);

        $count = (int) $query->count();
        $this->recordAggregateQuery($collection);
        return $count;
    }

    /** Efficiently sum a numeric field across matching documents without loading the full collection. */
    public function sum(string $collection, string $field, array $wheres = []): int|float
    {
        if (!$this->credentialsAvailable()) {
            throw new \RuntimeException($this->credentialsErrorMessage());
        }

        $query = $this->applyWheres($this->client()->collection($collection), $wheres);

        $sum = $query->sum($field);
        $this->recordAggregateQuery($collection);
        return $sum;
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

    /** Create a document only if it does not already exist. */
    public function createDocumentIfAbsent(string $collection, string $id, array $data): bool
    {
        if (!$this->credentialsAvailable()) {
            throw new \RuntimeException($this->credentialsErrorMessage());
        }

        $now = $this->now();
        $data['createdAt'] = $data['createdAt'] ?? $now;
        $data['updatedAt'] = $data['updatedAt'] ?? $now;
        $document = $this->client()->collection($collection)->document($id);

        return (bool) $this->client()->runTransaction(
            static function ($transaction) use ($document, $data): bool {
                if ($transaction->snapshot($document)->exists()) {
                    return false;
                }

                $transaction->create($document, $data);
                return true;
            }
        );
    }

    /**
     * Claim or renew a time-limited document lease inside a Firestore transaction.
     */
    public function claimDocumentLease(string $collection, string $id, string $token, int $leaseSeconds = 1800): bool
    {
        if (!$this->credentialsAvailable()) {
            throw new \RuntimeException($this->credentialsErrorMessage());
        }

        $document = $this->client()->collection($collection)->document($id);
        $now = time();
        $expiresAt = (new \DateTimeImmutable('@' . ($now + max(60, $leaseSeconds))))->format(DATE_ATOM);
        $nowIso = (new \DateTimeImmutable('@' . $now))->format(DATE_ATOM);

        return (bool) $this->client()->runTransaction(
            static function ($transaction) use ($document, $token, $now, $expiresAt, $nowIso): bool {
                $snapshot = $transaction->snapshot($document);
                if (!$snapshot->exists()) {
                    return false;
                }

                $data = $snapshot->data();
                $currentToken = (string) ($data['lockToken'] ?? '');
                $currentExpiry = strtotime((string) ($data['lockExpiresAt'] ?? '')) ?: 0;
                if ($currentToken !== '' && $currentToken !== $token && $currentExpiry > $now) {
                    return false;
                }

                $transaction->update($document, [
                    ['path' => 'lockToken', 'value' => $token],
                    ['path' => 'lockExpiresAt', 'value' => $expiresAt],
                    ['path' => 'status', 'value' => 'processing'],
                    ['path' => 'updatedAt', 'value' => $nowIso],
                ]);
                return true;
            }
        );
    }

    /** Release a document lease and atomically update its final state if the token still matches. */
    public function releaseDocumentLease(string $collection, string $id, string $token, array $updates): bool
    {
        if (!$this->credentialsAvailable()) {
            throw new \RuntimeException($this->credentialsErrorMessage());
        }

        $document = $this->client()->collection($collection)->document($id);
        $now = $this->now();
        $updates['lockToken'] = '';
        $updates['lockExpiresAt'] = $now;
        $updates['updatedAt'] = $now;
        $fields = [];
        foreach ($updates as $key => $value) {
            $fields[] = ['path' => $key, 'value' => $value];
        }

        return (bool) $this->client()->runTransaction(
            static function ($transaction) use ($document, $token, $fields): bool {
                $snapshot = $transaction->snapshot($document);
                if (!$snapshot->exists() || (string) ($snapshot->data()['lockToken'] ?? '') !== $token) {
                    return false;
                }

                $transaction->update($document, $fields);
                return true;
            }
        );
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
