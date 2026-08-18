<?php
/**
 * Example Jobs - Background Tasks for Async Processing
 * 
 * Jobs keep web requests fast by delegating heavy operations
 * to background workers. Implement your queue system (Redis, database, etc.)
 */

namespace App\Jobs;

/**
 * Example Jobs - Background task classes
 * 
 * Jobs decouple heavy operations from web requests.
 * Implement your queue system (Redis, database, Supervisor, etc.)
 * and dispatch jobs asynchronously.
 * 
 * Usage pattern:
 *   dispatch(new SendNotificationJob($userId, $message, 'email'));
 * 
 * Worker processes queued jobs and calls handle().
 */
class SendNotificationJob
{
    public function __construct(
        private string $userId,
        private string $message,
        private string $type = 'email',
    ) {}
    
    /**
     * Execute when worker processes this job
     * Implement your queue to call this method
     */
    public function execute(): void
    {
        // Example: Send notification
        // $service = new NotificationService();
        // $service->sendToUser($this->userId, $this->message, $this->type);
        error_log("Sending $this->type notification to user $this->userId");
    }
}

class GenerateReportJob
{
    public function __construct(
        private string $reportType,
        private array $filters,
    ) {}
    
    public function execute(): void
    {
        // Example: Generate and store report
        // $service = new ReportService();
        // $report = $service->generate($this->reportType, $this->filters);
        error_log("Generating $this->reportType report with filters");
    }
}

class ProcessAttendanceJob
{
    public function __construct(
        private string $attendanceId,
    ) {}
    
    public function execute(): void
    {
        // Example: Process attendance check-in/out
        // $service = new AttendanceService();
        // $service->process($this->attendanceId);
        error_log("Processing attendance record: $this->attendanceId");
    }
}

// Usage in controllers:
// dispatch(new SendNotificationJob($userId, $message, 'sms'));
// dispatch(new GenerateReportJob('attendance', ['month' => 'august']));
