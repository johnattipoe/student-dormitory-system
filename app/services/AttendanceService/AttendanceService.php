<?php

namespace App\Services;

class AttendanceService
{
    public static function all(?string $date = null, ?string $houseId = null): array
    {
        if ($date) {
            return self::forDate($date, $houseId);
        }

        return FirebaseService::getInstance()->getCollection(\COL_ATTENDANCE, [], 500);
    }

    public static function report(?string $date = null, ?string $houseId = null): array
    {
        $date = $date ?? date('Y-m-d');
        return self::summary($date, $houseId);
    }

    public static function byHouse(?string $houseId): array
    {
        if (!$houseId) {
            return [];
        }

        return FirebaseService::getInstance()->where(\COL_ATTENDANCE, 'houseId', '=', $houseId, 500);
    }

    public static function todayByHouse(?string $houseId): array
    {
        return self::forDate(date('Y-m-d'), $houseId);
    }

    public static function byHouseDate(?string $houseId, ?string $date = null): array
    {
        return self::forDate($date ?? date('Y-m-d'), $houseId);
    }

    /** Mark attendance for one student on a given date. */
    public static function mark(mixed $studentId, mixed $status = null, ?string $date = null, ?string $houseId = null, ?string $markedBy = null): array
    {
        try {
            if (is_array($studentId)) {
                $data = $studentId;
                $studentId = $data['studentId'] ?? '';
                $status = $data['status'] ?? null;
                $date = $data['date'] ?? null;
                $houseId = $data['houseId'] ?? null;
                $markedBy = $data['markedBy'] ?? null;
            }

            $studentId = trim((string) $studentId);
            $status = trim((string) ($status ?? 'present'));
            $date = trim((string) ($date ?? (new \DateTime())->format('Y-m-d')));

            $appConfig = require APP_ROOT . '/app/config/app/app.php';
            $advanced = $appConfig['advanced'] ?? [];
            $dateObject = new \DateTimeImmutable($date);
            if ($dateObject->format('N') >= 6 && empty($advanced['weekend_attendance'])) {
                return ['success' => false, 'message' => 'Weekend attendance is disabled by the administrator.'];
            }
            if ($status === 'present' && $date === date('Y-m-d')) {
                $checkInTime = (string) ($advanced['check_in_time'] ?? '14:00');
                $lateMinutes = max(0, (int) ($advanced['late_arrival_minutes'] ?? 15));
                $lateAfter = new \DateTimeImmutable($date . ' ' . $checkInTime);
                $lateAfter = $lateAfter->modify('+' . $lateMinutes . ' minutes');
                if (new \DateTimeImmutable() > $lateAfter) {
                    $status = 'late';
                }
            }

            if ($studentId === '') {
                return [
                    'success' => false,
                    'message' => 'Student is required.',
                ];
            }

            $records = FirebaseService::getInstance()->where(\COL_ATTENDANCE, 'studentId', '=', $studentId, 200);
            $existing = null;
            foreach ($records as $record) {
                if (($record['date'] ?? '') === $date) {
                    $existing = $record;
                    break;
                }
            }

            $markedByName = null;
            if ($markedBy && function_exists('current_user')) {
                $u = current_user();
                if (($u['uid'] ?? '') === $markedBy || ($u['id'] ?? '') === $markedBy) {
                    $markedByName = trim(($u['name'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? '')));
                    if (!empty($u['role'])) {
                        $markedByName .= ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')';
                    }
                }
            }

            if ($existing) {
                $updateData = [
                    'studentId' => $studentId,
                    'houseId'   => $houseId,
                    'date'      => $date,
                    'status'    => $status,
                    'markedBy'  => $markedBy,
                ];
                if ($markedByName) $updateData['markedByName'] = $markedByName;
                FirebaseService::getInstance()->updateDocument(\COL_ATTENDANCE, (string) ($existing['id'] ?? ''), $updateData);

                return [
                    'success' => true,
                    'message' => 'Attendance updated successfully.',
                    'id' => (string) ($existing['id'] ?? ''),
                ];
            }

            $addData = [
                'studentId' => $studentId,
                'houseId'   => $houseId,
                'date'      => $date,
                'status'    => $status, // present | absent | excused | late
                'markedBy'  => $markedBy,
            ];
            if ($markedByName) $addData['markedByName'] = $markedByName;
            $id = FirebaseService::getInstance()->addDocument(\COL_ATTENDANCE, $addData);

            return [
                'success' => true,
                'message' => 'Attendance marked successfully.',
                'id' => $id,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to mark attendance: ' . $e->getMessage(),
            ];
        }
    }

    /** Mark attendance for many students at once (e.g. a house roll call). */
    public static function markBulk(array $entries, string $date, ?string $houseId, string $markedBy): int
    {
        $saved = 0;
        foreach ($entries as $studentId => $status) {
            $result = self::mark((string) $studentId, (string) $status, $date, $houseId, $markedBy);
            if (!empty($result['success'])) {
                $saved++;
            }
        }

        return $saved;
    }

    public static function update(string $id, array $data): bool
    {
        FirebaseService::getInstance()->updateDocument(\COL_ATTENDANCE, $id, $data);
        return true;
    }

    public static function forDate(string $date, ?string $houseId = null): array
    {
        $records = FirebaseService::getInstance()->where(\COL_ATTENDANCE, 'date', '=', $date);
        if ($houseId) {
            $records = array_values(array_filter($records, fn($r) => ($r['houseId'] ?? null) === $houseId));
        }
        return $records;
    }

    public static function history(string $studentId, int $limit = 90): array
    {
        return FirebaseService::getInstance()->where(\COL_ATTENDANCE, 'studentId', '=', $studentId, $limit);
    }

    public static function summary(string $date, ?string $houseId = null): array
    {
        $records = self::forDate($date, $houseId);
        $counts = ['present' => 0, 'absent' => 0, 'excused' => 0, 'late' => 0];
        foreach ($records as $r) {
            $status = $r['status'] ?? 'absent';
            if (isset($counts[$status])) $counts[$status]++;
        }
        $counts['total'] = count($records);
        return $counts;
    }
}
