<?php
/**
 * Example Enum - Type-Safe Constants
 * 
 * Enums prevent "magic string" bugs and provide IDE autocomplete.
 * Use for statuses, roles, severity levels, etc.
 */

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case STUDENT = 'student';
    case SECURITY = 'security';
    case NURSE = 'nurse';
    case HOUSEPARENT = 'houseparent';
    case HOUSEMASTER = 'housemaster';
    
    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match($this) {
            self::ADMIN => 'Administrator',
            self::STUDENT => 'Student',
            self::SECURITY => 'Security Officer',
            self::NURSE => 'Nurse',
            self::HOUSEPARENT => 'House Parent',
            self::HOUSEMASTER => 'House Master',
        };
    }
    
    /**
     * Check if user is staff (non-student)
     */
    public function isStaff(): bool
    {
        return $this !== self::STUDENT;
    }
}

enum IncidentSeverity: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';
}

enum AttendanceStatus: string
{
    case PRESENT = 'present';
    case ABSENT = 'absent';
    case LATE = 'late';
    case EXCUSED = 'excused';
}
