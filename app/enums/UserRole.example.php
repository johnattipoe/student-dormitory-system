<?php
/** Type-safe role and common student-domain enum examples. */

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case STUDENT = 'student';
    case SECURITY = 'security';
    case NURSE = 'nurse';
    case SENIOR_HOUSEPARENT = 'senior_houseparent';
    case HOUSE_MASTER = 'house_master';
    case HOUSE_MISTRESS = 'house_mistress';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator', self::STUDENT => 'Student', self::SECURITY => 'Security Officer',
            self::NURSE => 'Nurse', self::SENIOR_HOUSEPARENT => 'Senior Houseparent',
            self::HOUSE_MASTER => 'House Master', self::HOUSE_MISTRESS => 'House Mistress',
        };
    }

    public function isStaff(): bool { return $this !== self::STUDENT; }
    public function canManageStudents(): bool { return in_array($this, [self::ADMIN, self::HOUSE_MASTER, self::HOUSE_MISTRESS, self::SENIOR_HOUSEPARENT], true); }
    public static function values(): array { return array_map(static fn(self $role): string => $role->value, self::cases()); }
    public static function tryFromInput(?string $value): ?self { return $value === null ? null : self::tryFrom(strtolower(trim($value))); }
}

enum StudentStatus: string
{
    case ACTIVE = 'active'; case INACTIVE = 'inactive'; case SUSPENDED = 'suspended';
    public function label(): string { return ucfirst($this->value); }
    public function canReceiveAllocation(): bool { return $this === self::ACTIVE; }
}

enum IncidentSeverity: string
{
    case LOW = 'low'; case MEDIUM = 'medium'; case HIGH = 'high'; case CRITICAL = 'critical';
    public function requiresImmediateAttention(): bool { return $this === self::CRITICAL; }
}

enum AttendanceStatus: string
{
    case PRESENT = 'present'; case ABSENT = 'absent'; case LATE = 'late'; case EXCUSED = 'excused';
    public function countsAsPresent(): bool { return in_array($this, [self::PRESENT, self::LATE, self::EXCUSED], true); }
}
