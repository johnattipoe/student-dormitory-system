<?php
/**
 * Example Jobs - Background Tasks for Async Processing
 * 
 * Jobs keep web requests fast by delegating heavy operations
 * to background workers. Implement your queue system (Redis, database, etc.)
 */

namespace App\Jobs;

class SendNotificationJob
{
    public function __construct(
        private string $userId,
        private string $message,
        private string $type = 'email',
    ) {}
    
    /**
     * Execute the job (called by queue worker)
     */
    public function handle(\App\Services\NotificationService $notificationService): void
    {
        $notificationService->send($this->userId, $this->message, $this->type);
    }
}

class GenerateReportJob
{
    public function __construct(
        private string $reportType,
        private array $filters,
    ) {}
    
    public function handle(\App\Services\ReportService $reportService): void
    {
        $reportService->generate($this->reportType, $this->filters);
    }
}

class ProcessAttendanceJob
{
    public function __construct(
        private string $attendanceId,
    ) {}
    
    public function handle(\App\Services\AttendanceService $attendanceService): void
    {
        $attendanceService->process($this->attendanceId);
    }
}

// Usage in controllers:
// dispatch(new SendNotificationJob($userId, $message, 'sms'));
// dispatch(new GenerateReportJob('attendance', ['month' => 'august']));
