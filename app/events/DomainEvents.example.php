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
    private \DateTimeImmutable $occurredAt;
    private string $eventId;

    public function __construct(?\DateTimeImmutable $occurredAt = null, ?string $eventId = null)
    {
        $this->occurredAt = $occurredAt ?? new \DateTimeImmutable();
        $this->eventId = $eventId ?? bin2hex(random_bytes(16));
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventId(): string
    {
        return $this->eventId;
    }
}

final class StudentCreatedEvent extends AbstractDomainEvent
{
    public string $studentId;
    public string $firstName;
    public string $email;
    public string $houseId;

    public function __construct(
        string $studentId,
        string $firstName,
        string $email,
        string $houseId,
        ?\DateTimeImmutable $occurredAt = null,
        ?string $eventId = null
    ) {
        parent::__construct($occurredAt, $eventId);
        $this->studentId = $studentId;
        $this->firstName = $firstName;
        $this->email = $email;
        $this->houseId = $houseId;
    }

    public function eventName(): string
    {
        return 'student.created';
    }

    public function payload(): array
    {
        return [
            'eventId' => $this->eventId(),
            'studentId' => $this->studentId,
            'firstName' => $this->firstName,
            'email' => $this->email,
            'houseId' => $this->houseId,
        ];
    }
}

final class StudentAllocatedEvent extends AbstractDomainEvent
{
    public string $studentId;
    public string $roomId;
    public string $allocationId;

    public function __construct(
        string $studentId,
        string $roomId,
        string $allocationId,
        ?\DateTimeImmutable $occurredAt = null,
        ?string $eventId = null
    ) {
        parent::__construct($occurredAt, $eventId);
        $this->studentId = $studentId;
        $this->roomId = $roomId;
        $this->allocationId = $allocationId;
    }

    public function eventName(): string
    {
        return 'student.allocated';
    }

    public function payload(): array
    {
        return [
            'eventId' => $this->eventId(),
            'studentId' => $this->studentId,
            'roomId' => $this->roomId,
            'allocationId' => $this->allocationId,
        ];
    }
}

final class IncidentReportedEvent extends AbstractDomainEvent
{
    public string $incidentId;
    public string $title;
    public string $severity;
    public string $reportedBy;

    public function __construct(
        string $incidentId,
        string $title,
        string $severity,
        string $reportedBy,
        ?\DateTimeImmutable $occurredAt = null,
        ?string $eventId = null
    ) {
        parent::__construct($occurredAt, $eventId);
        $this->incidentId = $incidentId;
        $this->title = $title;
        $this->severity = $severity;
        $this->reportedBy = $reportedBy;
    }

    public function eventName(): string
    {
        return 'incident.reported';
    }

    public function payload(): array
    {
        return [
            'eventId' => $this->eventId(),
            'incidentId' => $this->incidentId,
            'title' => $this->title,
            'severity' => $this->severity,
            'reportedBy' => $this->reportedBy,
        ];
    }
}

final class MedicalRecordCreatedEvent extends AbstractDomainEvent
{
    public string $recordId;
    public string $studentId;
    public string $severity;

    public function __construct(
        string $recordId,
        string $studentId,
        string $severity,
        ?\DateTimeImmutable $occurredAt = null,
        ?string $eventId = null
    ) {
        parent::__construct($occurredAt, $eventId);
        $this->recordId = $recordId;
        $this->studentId = $studentId;
        $this->severity = $severity;
    }

    public function eventName(): string
    {
        return 'medical_record.created';
    }

    public function payload(): array
    {
        return [
            'eventId' => $this->eventId(),
            'recordId' => $this->recordId,
            'studentId' => $this->studentId,
            'severity' => $this->severity,
        ];
    }
}

final class VisitorArrivedEvent extends AbstractDomainEvent
{
    public string $visitorId;
    public string $studentId;
    public \DateTimeImmutable $arrivalTime;

    public function __construct(
        string $visitorId,
        string $studentId,
        \DateTimeImmutable $arrivalTime,
        ?\DateTimeImmutable $occurredAt = null,
        ?string $eventId = null
    ) {
        parent::__construct($occurredAt, $eventId);
        $this->visitorId = $visitorId;
        $this->studentId = $studentId;
        $this->arrivalTime = $arrivalTime;
    }

    public function eventName(): string
    {
        return 'visitor.arrived';
    }

    public function payload(): array
    {
        return [
            'eventId' => $this->eventId(),
            'visitorId' => $this->visitorId,
            'studentId' => $this->studentId,
            'arrivalTime' => $this->arrivalTime->format(\DATE_ATOM),
        ];
    }
}

// Example listener map: 'medical_record.created' => [NotifyHouseStaffListener::class].
