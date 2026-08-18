<?php
/**
 * Example Traits - Reusable Behavior
 * 
 * Traits provide shared functionality across multiple classes
 * without inheritance. Use for timestamping, auditing, logging, etc.
 */

namespace App\Traits;

/**
 * HasTimestamps - Auto-manage created_at and updated_at
 */
trait HasTimestamps
{
    private ?\DateTime $createdAt = null;
    private ?\DateTime $updatedAt = null;
    
    public function touch(): void
    {
        $now = new \DateTime();
        if (!$this->createdAt) {
            $this->createdAt = $now;
        }
        $this->updatedAt = $now;
    }
    
    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }
    
    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }
}

/**
 * Auditable - Track who changed what
 */
trait Auditable
{
    private ?string $changedBy = null;
    private ?string $changeReason = null;
    
    public function recordChange(string $userId, string $reason): void
    {
        $this->changedBy = $userId;
        $this->changeReason = $reason;
        $this->touch();
    }
    
    public function getChangedBy(): ?string
    {
        return $this->changedBy;
    }
}

/**
 * Loggable - Auto-log actions
 */
trait Loggable
{
    public function log(string $action, array $data = []): void
    {
        // Log to activity_logs collection
        error_log(json_encode([
            'action' => $action,
            'class' => static::class,
            'data' => $data,
            'timestamp' => date('c'),
        ]));
    }
}

// Usage in models/services:
// class Student {
//     use HasTimestamps, Auditable;
// }
