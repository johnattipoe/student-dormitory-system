<?php
/** Type-safe role and common student-domain enum examples. */

namespace App\Enums;

final class UserRole
{
    public const ADMIN = 'admin';
    public const STUDENT = 'student';
    public const SECURITY = 'security';
    public const NURSE = 'nurse';
    public const SENIOR_HOUSEPARENT = 'senior_houseparent';
    public const HOUSE_MASTER = 'house_master';
    public const HOUSE_MISTRESS = 'house_mistress';

    /** @var string */
    private $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        switch ($this->value) {
            case self::ADMIN:
                return 'Administrator';
            case self::STUDENT:
                return 'Student';
            case self::SECURITY:
                return 'Security Officer';
            case self::NURSE:
                return 'Nurse';
            case self::SENIOR_HOUSEPARENT:
                return 'Senior Houseparent';
            case self::HOUSE_MASTER:
                return 'House Master';
            case self::HOUSE_MISTRESS:
                return 'House Mistress';
            default:
                return ucfirst(str_replace('_', ' ', $this->value));
        }
    }

    public function isStaff(): bool
    {
        return $this->value !== self::STUDENT;
    }

    public function canManageStudents(): bool
    {
        return in_array($this->value, [self::ADMIN, self::HOUSE_MASTER, self::HOUSE_MISTRESS, self::SENIOR_HOUSEPARENT], true);
    }

    public static function values(): array
    {
        return [
            self::ADMIN,
            self::STUDENT,
            self::SECURITY,
            self::NURSE,
            self::SENIOR_HOUSEPARENT,
            self::HOUSE_MASTER,
            self::HOUSE_MISTRESS,
        ];
    }

    public static function tryFromInput(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim($value));
        $cases = self::values();

        foreach ($cases as $case) {
            if ($case === $value) {
                return new self($case);
            }
        }

        return null;
    }
}

final class StudentStatus
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const SUSPENDED = 'suspended';

    /** @var string */
    private $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function canReceiveAllocation(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public static function values(): array
    {
        return [self::ACTIVE, self::INACTIVE, self::SUSPENDED];
    }

    public static function tryFromInput(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim($value));
        foreach (self::values() as $case) {
            if ($case === $value) {
                return new self($case);
            }
        }

        return null;
    }
}

final class IncidentSeverity
{
    public const LOW = 'low';
    public const MEDIUM = 'medium';
    public const HIGH = 'high';
    public const CRITICAL = 'critical';

    /** @var string */
    private $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function requiresImmediateAttention(): bool
    {
        return $this->value === self::CRITICAL;
    }

    public static function values(): array
    {
        return [self::LOW, self::MEDIUM, self::HIGH, self::CRITICAL];
    }

    public static function tryFromInput(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim($value));
        foreach (self::values() as $case) {
            if ($case === $value) {
                return new self($case);
            }
        }

        return null;
    }
}

final class AttendanceStatus
{
    public const PRESENT = 'present';
    public const ABSENT = 'absent';
    public const LATE = 'late';
    public const EXCUSED = 'excused';

    /** @var string */
    private $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function countsAsPresent(): bool
    {
        return in_array($this->value, [self::PRESENT, self::LATE, self::EXCUSED], true);
    }

    public static function values(): array
    {
        return [self::PRESENT, self::ABSENT, self::LATE, self::EXCUSED];
    }

    public static function tryFromInput(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim($value));
        foreach (self::values() as $case) {
            if ($case === $value) {
                return new self($case);
            }
        }

        return null;
    }
}
