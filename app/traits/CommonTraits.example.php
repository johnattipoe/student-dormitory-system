<?php
/** Reusable traits for models and services. Copy only what a concrete class needs. */

namespace App\Traits;

trait HasTimestamps
{
    private ?\DateTimeImmutable $createdAt = null;
    private ?\DateTimeImmutable $updatedAt = null;

    public function touch(?\DateTimeImmutable $when = null): void
    {
        $when ??= new \DateTimeImmutable();
        $this->createdAt ??= $when;
        $this->updatedAt = $when;
    }

    public function setTimestamps(?string $createdAt, ?string $updatedAt = null): void
    {
        $this->createdAt = $createdAt ? new \DateTimeImmutable($createdAt) : null;
        $this->updatedAt = $updatedAt ? new \DateTimeImmutable($updatedAt) : $this->createdAt;
    }

    public function createdAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function timestampAttributes(): array { return ['createdAt' => $this->createdAt?->format(DATE_ATOM), 'updatedAt' => $this->updatedAt?->format(DATE_ATOM)]; }
}

trait Auditable
{
    private ?string $changedBy = null;
    private ?string $changeReason = null;
    private ?\DateTimeImmutable $changedAt = null;

    public function recordChange(string $userId, string $reason = ''): void
    {
        $this->changedBy = trim($userId) ?: null;
        $this->changeReason = trim($reason) ?: null;
        $this->changedAt = new \DateTimeImmutable();
        if (method_exists($this, 'touch')) $this->touch($this->changedAt);
    }

    public function auditAttributes(): array { return ['changedBy' => $this->changedBy, 'changeReason' => $this->changeReason, 'changedAt' => $this->changedAt?->format(DATE_ATOM)]; }
}

trait HasMetadata
{
    private array $metadata = [];
    public function setMetadata(string $key, mixed $value): void { $key = trim($key); if ($key === '') throw new \InvalidArgumentException('Metadata key cannot be empty.'); $this->metadata[$key] = $value; }
    public function metadata(?string $key = null, mixed $default = null): mixed { return $key === null ? $this->metadata : ($this->metadata[$key] ?? $default); }
    public function removeMetadata(string $key): void { unset($this->metadata[$key]); }
}

trait Loggable
{
    public function log(string $action, array $context = []): void
    {
        error_log(json_encode(['action' => $action, 'class' => static::class, 'context' => $context, 'occurredAt' => (new \DateTimeImmutable())->format(DATE_ATOM)], JSON_UNESCAPED_SLASHES));
    }
}

// Example: final class StudentProfile { use HasTimestamps, Auditable, HasMetadata; }
