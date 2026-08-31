<?php
/** Domain event templates. Dispatch from services only after a successful write. */

namespace App\Events;

interface DomainEvent
{
    public function eventName(): string;
    public function occurredAt(): \DateTimeImmutable;
    public function payload(): array;
}

abstract class AbstractDomainEvent implements DomainEvent
{
    private readonly \DateTimeImmutable $occurredAt;
    private readonly string $eventId;
    public function __construct(?\DateTimeImmutable $occurredAt = null, ?string $eventId = null) { $this->occurredAt = $occurredAt ?? new \DateTimeImmutable(); $this->eventId = $eventId ?? bin2hex(random_bytes(16)); }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
    public function eventId(): string { return $this->eventId; }
}

final class StudentCreatedEvent extends AbstractDomainEvent
{
    public function __construct(public readonly string $studentId, public readonly string $firstName, public readonly string $email, public readonly string $houseId, ?\DateTimeImmutable $occurredAt = null) { parent::__construct($occurredAt); }
    public function eventName(): string { return 'student.created'; }
    public function payload(): array { return ['eventId' => $this->eventId(), 'studentId' => $this->studentId, 'firstName' => $this->firstName, 'email' => $this->email, 'houseId' => $this->houseId]; }
}

final class StudentAllocatedEvent extends AbstractDomainEvent
{
    public function __construct(public readonly string $studentId, public readonly string $roomId, public readonly string $allocationId, ?\DateTimeImmutable $occurredAt = null) { parent::__construct($occurredAt); }
    public function eventName(): string { return 'student.allocated'; }
    public function payload(): array { return ['eventId' => $this->eventId(), 'studentId' => $this->studentId, 'roomId' => $this->roomId, 'allocationId' => $this->allocationId]; }
}

final class IncidentReportedEvent extends AbstractDomainEvent
{
    public function __construct(public readonly string $incidentId, public readonly string $title, public readonly string $severity, public readonly string $reportedBy, ?\DateTimeImmutable $occurredAt = null) { parent::__construct($occurredAt); }
    public function eventName(): string { return 'incident.reported'; }
    public function payload(): array { return ['eventId' => $this->eventId(), 'incidentId' => $this->incidentId, 'title' => $this->title, 'severity' => $this->severity, 'reportedBy' => $this->reportedBy]; }
}

final class MedicalRecordCreatedEvent extends AbstractDomainEvent
{
    public function __construct(public readonly string $recordId, public readonly string $studentId, public readonly string $severity, ?\DateTimeImmutable $occurredAt = null) { parent::__construct($occurredAt); }
    public function eventName(): string { return 'medical_record.created'; }
    public function payload(): array { return ['eventId' => $this->eventId(), 'recordId' => $this->recordId, 'studentId' => $this->studentId, 'severity' => $this->severity]; }
}

final class VisitorArrivedEvent extends AbstractDomainEvent
{
    public function __construct(public readonly string $visitorId, public readonly string $studentId, public readonly \DateTimeImmutable $arrivalTime, ?\DateTimeImmutable $occurredAt = null) { parent::__construct($occurredAt); }
    public function eventName(): string { return 'visitor.arrived'; }
    public function payload(): array { return ['eventId' => $this->eventId(), 'visitorId' => $this->visitorId, 'studentId' => $this->studentId, 'arrivalTime' => $this->arrivalTime->format(DATE_ATOM)]; }
}

// Example listener map: 'medical_record.created' => [NotifyHouseStaffListener::class].
