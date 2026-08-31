<?php
/** Background job templates. A queue worker calls handle() and manages retries. */

namespace App\Jobs;

interface JobContract
{
    /** @return array<string, mixed> */
    public function handle(): array;
    public function name(): string;
    public function maxAttempts(): int;
}

abstract class QueueableJob implements JobContract
{
    public function __construct(protected readonly string $jobId, protected readonly int $attempt = 1) {}
    public function maxAttempts(): int { return 3; }
    protected function completed(array $data = []): array { return ['jobId' => $this->jobId, 'job' => $this->name(), 'attempt' => $this->attempt, 'completedAt' => date(DATE_ATOM)] + $data; }
}

final class SendNotificationJob extends QueueableJob
{
    public function __construct(string $jobId, private readonly string $userId, private readonly string $title, private readonly string $message, private readonly string $type = 'info', int $attempt = 1) { parent::__construct($jobId, $attempt); }
    public function name(): string { return 'send_notification'; }
    public function handle(): array
    {
        if ($this->userId === '' || $this->title === '') throw new \InvalidArgumentException('A user and notification title are required.');
        // Production worker: (new NotificationService())->create([...]);
        error_log("Queued notification for {$this->userId}: {$this->title}");
        return $this->completed(['recipientId' => $this->userId, 'type' => $this->type]);
    }
}

final class GenerateReportJob extends QueueableJob
{
    public function __construct(string $jobId, private readonly string $reportType, private readonly array $filters = [], int $attempt = 1) { parent::__construct($jobId, $attempt); }
    public function name(): string { return 'generate_report'; }
    public function handle(): array
    {
        if ($this->reportType === '') throw new \InvalidArgumentException('Report type is required.');
        // Production worker: generate, persist and notify the report requester.
        return $this->completed(['reportType' => $this->reportType, 'filters' => $this->filters]);
    }
}

final class ProcessAttendanceJob extends QueueableJob
{
    public function __construct(string $jobId, private readonly string $attendanceId, int $attempt = 1) { parent::__construct($jobId, $attempt); }
    public function name(): string { return 'process_attendance'; }
    public function handle(): array
    {
        if ($this->attendanceId === '') throw new \InvalidArgumentException('Attendance ID is required.');
        return $this->completed(['attendanceId' => $this->attendanceId]);
    }
}

// Worker example: try { $result = $job->handle(); } catch (Throwable $error) { /* retry or fail */ }
