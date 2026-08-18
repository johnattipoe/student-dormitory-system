<?php
/**
 * Example Events - Domain Events for Async Processing
 * 
 * Events decouple the system: one action can trigger multiple reactions
 * without tight coupling. Listeners subscribe and react independently.
 */

namespace App\Events;

class StudentCreatedEvent
{
    public function __construct(
        public readonly string $studentId,
        public readonly string $firstName,
        public readonly string $email,
        public readonly string $houseId,
    ) {}
}

class IncidentReportedEvent
{
    public function __construct(
        public readonly string $incidentId,
        public readonly string $title,
        public readonly string $severity,
        public readonly string $reportedBy,
    ) {}
}

class VisitorArrivedEvent
{
    public function __construct(
        public readonly string $visitorId,
        public readonly string $studentId,
        public readonly \DateTime $arrivalTime,
    ) {}
}

class VisitorDepartedEvent
{
    public function __construct(
        public readonly string $visitorId,
        public readonly string $studentId,
        public readonly \DateTime $departureTime,
        public readonly int $durationMinutes,
    ) {}
}

// Usage in services:
// event(new StudentCreatedEvent($id, $name, $email, $house));
// This triggers listeners like:
// - SendWelcomeEmailListener
// - LogActivityListener
// - NotifyHousemasterListener
