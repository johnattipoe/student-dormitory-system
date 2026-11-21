<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class StudentPromotionService
{
    private const RUNS_COLLECTION = 'student_promotion_runs';
    private const HISTORY_COLLECTION = 'student_promotion_history';

    private FirebaseService $firebase;

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    public static function isValidAcademicYear(string $academicYear): bool
    {
        if (preg_match('/^(\d{4})\/(\d{4})$/', trim($academicYear), $matches) !== 1) {
            return false;
        }

        return (int) $matches[2] === (int) $matches[1] + 1;
    }

    public static function isValidSourceForm(string $sourceForm): bool
    {
        return in_array(trim($sourceForm), ['Form 1', 'Form 2', 'Form 3'], true);
    }

    public static function sourceFormGrade(string $sourceForm): ?int
    {
        return match (trim($sourceForm)) {
            'Form 1' => 1,
            'Form 2' => 2,
            'Form 3' => 3,
            default => null,
        };
    }

    private static function sourceFormRunYear(string $sourceForm): string
    {
        $grade = self::sourceFormGrade($sourceForm);
        if ($grade === null) {
            throw new RuntimeException('Choose a valid source form.');
        }

        return match ($grade) {
            1 => '2001/2002',
            2 => '2002/2003',
            3 => '2003/2004',
        };
    }

    public function applyBySourceForm(string $sourceForm, array $plan, string $actorId, int $batchLimit = 0): array
    {
        if (!self::isValidSourceForm($sourceForm)) {
            throw new RuntimeException('Choose a valid source form.');
        }

        $result = $this->apply(self::sourceFormRunYear($sourceForm), $plan, $actorId, $batchLimit);
        $result['sourceForm'] = $sourceForm;
        return $result;
    }

    public function undoBySourceForm(string $sourceForm, string $actorId): array
    {
        if (!self::isValidSourceForm($sourceForm)) {
            throw new RuntimeException('Choose a valid source form.');
        }

        $result = $this->undo(self::sourceFormRunYear($sourceForm), $actorId);
        $result['sourceForm'] = $sourceForm;
        return $result;
    }

    public function findRunForSourceForm(string $sourceForm): ?array
    {
        if (!self::isValidSourceForm($sourceForm)) {
            return null;
        }

        return $this->findRunForYear(self::sourceFormRunYear($sourceForm));
    }

    public static function gradeNumber(string $value): ?int
    {
        $value = trim($value);
        if (preg_match('/^(?:form|shs)\s*([1-3])$/i', $value, $matches) === 1) {
            return (int) $matches[1];
        }
        if (preg_match('/^([1-3])$/', $value, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Resolve the student's year level from the dedicated form/level fields.
     * The class field is a fallback only for legacy records with no form value.
     *
     * @return array{grade:int,value:string}|null
     */
    public static function studentGrade(array $student): ?array
    {
        $value = trim((string) ($student['form'] ?? ''));
        if ($value === '') {
            $value = trim((string) ($student['level'] ?? ''));
        }
        if ($value === '') {
            $value = trim((string) ($student['class'] ?? ''));
        }

        $grade = self::gradeNumber($value);
        if ($grade === null) {
            return null;
        }

        return ['grade' => $grade, 'value' => $value];
    }

    public static function nextGradeValue(string $currentValue, int $nextGrade): string
    {
        $currentValue = trim($currentValue);
        if (preg_match('/^(form|shs)(\s*)([1-3])$/i', $currentValue, $matches) === 1) {
            return $matches[1] . $matches[2] . $nextGrade;
        }
        if (self::gradeNumber($currentValue) !== null) {
            return (string) $nextGrade;
        }

        return 'Form ' . $nextGrade;
    }

    public static function nextClassValue(string $class, int $fromGrade, int $toGrade): string
    {
        $class = trim($class);
        if (preg_match('/^(SHS|Form)(\s*)([1-3])$/i', $class, $matches) === 1
            && (int) $matches[3] === $fromGrade) {
            return $matches[1] . $matches[2] . $toGrade;
        }
        if (preg_match('/^([1-3])(\s+.+)$/', $class, $matches) === 1
            && (int) $matches[1] === $fromGrade) {
            return $toGrade . $matches[2];
        }

        return $class;
    }

    /**
     * @return array{candidates:array<int,array<string,mixed>>,unclassified:array<int,array<string,mixed>>,inactiveCount:int}
     */
    public function preview(?string $sourceForm = null): array
    {
        $candidates = [];
        $unclassified = [];
        $inactiveCount = 0;
        $sourceGrade = $sourceForm !== null ? self::sourceFormGrade($sourceForm) : null;
        if ($sourceForm !== null && $sourceGrade === null) {
            throw new RuntimeException('Choose a valid source form: Form 1, Form 2, or Form 3.');
        }

        foreach (StudentService::allRecords() as $student) {
            $status = strtolower(trim((string) ($student['status'] ?? 'active')));
            if ($status !== 'active') {
                $inactiveCount++;
                continue;
            }

            $id = trim((string) ($student['id'] ?? ''));
            if ($id === '') {
                $unclassified[] = $student;
                continue;
            }

            $grade = self::studentGrade($student);
            if ($grade === null) {
                $unclassified[] = $student;
                continue;
            }
            if ($sourceGrade !== null && $grade['grade'] !== $sourceGrade) {
                continue;
            }

            $candidates[] = [
                'id' => $id,
                'name' => trim((string) (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))),
                'admissionNo' => trim((string) ($student['admissionNo'] ?? '')),
                'grade' => $grade['grade'],
                'fromForm' => $grade['value'],
                'fromClass' => trim((string) ($student['class'] ?? '')),
                'status' => $status,
                'defaultDecision' => $grade['grade'] === 3 ? 'graduate' : 'promote',
                'defaultTargetForm' => $grade['grade'] === 3
                    ? $grade['value']
                    : self::nextGradeValue($grade['value'], $grade['grade'] + 1),
                'defaultTargetClass' => $grade['grade'] === 3
                    ? trim((string) ($student['class'] ?? ''))
                    : self::nextClassValue(
                        (string) ($student['class'] ?? ''),
                        $grade['grade'],
                        $grade['grade'] + 1
                    ),
            ];
        }

        return [
            'candidates' => $candidates,
            'unclassified' => $unclassified,
            'inactiveCount' => $inactiveCount,
        ];
    }

    /**
     * Build a server-validated plan from the preview and submitted per-student decisions.
     *
     * @param array<int,array<string,mixed>> $candidates
     * @param array<string,mixed> $decisions
     * @return array<int,array<string,mixed>>
     */
    public static function buildPlan(array $candidates, array $decisions): array
    {
        $knownIds = [];
        $plan = [];
        foreach ($candidates as $candidate) {
            $id = (string) $candidate['id'];
            $knownIds[$id] = true;
            if (!array_key_exists($id, $decisions) || !is_string($decisions[$id])) {
                throw new RuntimeException('Choose an outcome for every student in the promotion preview.');
            }

            $decision = $decisions[$id];
            $grade = (int) $candidate['grade'];
            $allowed = $grade === 3 ? ['graduate', 'repeat'] : ['promote', 'repeat'];
            if (!in_array($decision, $allowed, true)) {
                throw new RuntimeException('A submitted promotion outcome is invalid. Review the preview and try again.');
            }

            $toGrade = $decision === 'promote' ? $grade + 1 : $grade;
            $toForm = $decision === 'promote'
                ? self::nextGradeValue((string) $candidate['fromForm'], $toGrade)
                : (string) $candidate['fromForm'];
            $toClass = $decision === 'promote'
                ? self::nextClassValue((string) $candidate['fromClass'], $grade, $toGrade)
                : (string) $candidate['fromClass'];
            $toStatus = $decision === 'graduate' ? 'graduated' : (string) $candidate['status'];

            $plan[] = [
                'studentId' => $id,
                'name' => (string) $candidate['name'],
                'admissionNo' => (string) $candidate['admissionNo'],
                'fromGrade' => $grade,
                'toGrade' => $toGrade,
                'fromForm' => (string) $candidate['fromForm'],
                'toForm' => $toForm,
                'fromClass' => (string) $candidate['fromClass'],
                'toClass' => $toClass,
                'fromStatus' => (string) $candidate['status'],
                'toStatus' => $toStatus,
                'decision' => $decision,
            ];
        }

        foreach ($decisions as $id => $_decision) {
            if (!isset($knownIds[(string) $id])) {
                throw new RuntimeException('The preview changed. Reload it before applying this promotion.');
            }
        }

        if ($plan === []) {
            throw new RuntimeException('There are no active Form 1, Form 2, or Form 3 students to process.');
        }

        return $plan;
    }

    /**
     * Apply or resume the one-time promotion run for an academic year.
     *
     * @param array<int,array<string,mixed>> $plan
     * @return array<string,mixed>
     */
    public function apply(string $academicYear, array $plan, string $actorId, int $batchLimit = 0): array
    {
        $academicYear = trim($academicYear);
        if (!self::isValidAcademicYear($academicYear)) {
            throw new RuntimeException('Enter an academic year in the format YYYY/YYYY, with consecutive years.');
        }

        $runId = 'promotion_' . str_replace('/', '_', $academicYear);
        $run = $this->firebase->getDocument(self::RUNS_COLLECTION, $runId);
        if ($run === null) {
            if ($plan === []) {
                throw new RuntimeException('There is no saved promotion plan to resume.');
            }
            if (strlen(json_encode($plan, JSON_THROW_ON_ERROR)) > 819200) {
                throw new RuntimeException('This promotion plan is too large to save safely. Please contact an administrator for a staged promotion.');
            }

            $now = date(DATE_ATOM);
            $this->firebase->createDocumentIfAbsent(self::RUNS_COLLECTION, $runId, [
                'academicYear' => $academicYear,
                'status' => 'pending',
                'total' => count($plan),
                'processed' => 0,
                'plan' => $plan,
                'createdBy' => $actorId,
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);
            $run = $this->firebase->getDocument(self::RUNS_COLLECTION, $runId);
        }

        if ($run === null) {
            throw new RuntimeException('The promotion run could not be loaded. Please try again.');
        }
        if (($run['status'] ?? '') === 'completed') {
            throw new RuntimeException('A promotion has already been completed for this academic year.');
        }

        $savedPlan = $run['plan'] ?? null;
        if (!is_array($savedPlan) || $savedPlan === []) {
            throw new RuntimeException('The saved promotion plan is missing. Do not start another run for this academic year; contact an administrator.');
        }

        $lockToken = bin2hex(random_bytes(16));
        if (!$this->firebase->claimDocumentLease(self::RUNS_COLLECTION, $runId, $lockToken)) {
            throw new RuntimeException('This academic-year promotion is already being processed. Wait for it to finish before trying again.');
        }

        $completed = 0;
        try {
            $batchProcessed = 0;
            foreach ($savedPlan as $index => $entry) {
                if (!is_array($entry)) {
                    throw new RuntimeException('The saved promotion plan contains an invalid entry.');
                }
                $historyId = hash('sha256', $runId . ':' . trim((string) ($entry['studentId'] ?? '')));
                $existingHistory = $this->firebase->getDocument(self::HISTORY_COLLECTION, $historyId);
                if (($existingHistory['status'] ?? '') === 'completed') {
                    $completed++;
                    continue;
                }
                if ($batchLimit > 0 && $batchProcessed >= $batchLimit) {
                    break;
                }
                $batchProcessed++;
                if ($index > 0 && $index % 20 === 0
                    && !$this->firebase->claimDocumentLease(self::RUNS_COLLECTION, $runId, $lockToken)) {
                    throw new RuntimeException('The promotion processing lock expired. Resume the run to continue safely.');
                }

                $studentId = trim((string) ($entry['studentId'] ?? ''));
                if ($studentId === '') {
                    throw new RuntimeException('The saved promotion plan contains a student without an ID.');
                }

                $history = $existingHistory;

                if ($history === null) {
                    $this->firebase->createDocumentIfAbsent(self::HISTORY_COLLECTION, $historyId, [
                        'runId' => $runId,
                        'academicYear' => $academicYear,
                        'studentId' => $studentId,
                        'studentName' => (string) ($entry['name'] ?? ''),
                        'admissionNo' => (string) ($entry['admissionNo'] ?? ''),
                        'action' => (string) ($entry['decision'] ?? ''),
                        'fromForm' => (string) ($entry['fromForm'] ?? ''),
                        'toForm' => (string) ($entry['toForm'] ?? ''),
                        'fromClass' => (string) ($entry['fromClass'] ?? ''),
                        'toClass' => (string) ($entry['toClass'] ?? ''),
                        'fromStatus' => (string) ($entry['fromStatus'] ?? 'active'),
                        'toStatus' => (string) ($entry['toStatus'] ?? ''),
                        'status' => 'pending',
                        'changedBy' => $actorId,
                    ]);
                }

                $student = StudentService::find($studentId);
                if ($student === null) {
                    throw new RuntimeException('A student in the saved plan no longer exists. Review the incomplete run before continuing.');
                }

                if ((string) ($student['lastPromotionRunId'] ?? '') !== $runId) {
                    $currentStatus = strtolower(trim((string) ($student['status'] ?? 'active')));
                    $currentGrade = self::studentGrade($student);
                    $currentClass = trim((string) ($student['class'] ?? ''));
                    if ($currentStatus !== (string) ($entry['fromStatus'] ?? '')
                        || $currentGrade === null
                        || $currentGrade['grade'] !== (int) ($entry['fromGrade'] ?? 0)
                        || $currentClass !== (string) ($entry['fromClass'] ?? '')) {
                        throw new RuntimeException('A student record changed after the preview. The run is paused to prevent overwriting newer data.');
                    }

                    StudentService::update($studentId, [
                        'form' => (string) ($entry['toForm'] ?? ''),
                        'level' => (string) ($entry['toForm'] ?? ''),
                        'class' => (string) ($entry['toClass'] ?? ''),
                        'status' => (string) ($entry['toStatus'] ?? ''),
                        'lastPromotionRunId' => $runId,
                        'lastPromotionAcademicYear' => $academicYear,
                    ]);
                }

                $this->firebase->updateDocument(self::HISTORY_COLLECTION, $historyId, [
                    'status' => 'completed',
                    'appliedAt' => date(DATE_ATOM),
                ]);
                $completed++;
            }

            $counts = ['promoted' => 0, 'repeated' => 0, 'graduated' => 0];
            foreach ($savedPlan as $entry) {
                $decision = (string) ($entry['decision'] ?? '');
                if ($decision === 'promote') {
                    $counts['promoted']++;
                } elseif ($decision === 'repeat') {
                    $counts['repeated']++;
                } elseif ($decision === 'graduate') {
                    $counts['graduated']++;
                }
            }

            $isComplete = $completed >= count($savedPlan);
            $released = $this->firebase->releaseDocumentLease(self::RUNS_COLLECTION, $runId, $lockToken, [
                'status' => $isComplete ? 'completed' : 'partial',
                'processed' => $completed,
                'promoted' => $counts['promoted'],
                'repeated' => $counts['repeated'],
                'graduated' => $counts['graduated'],
                'completedAt' => $isComplete ? date(DATE_ATOM) : '',
                'lastError' => $isComplete ? '' : 'A batch limit stopped this run. Resume to process the remaining students.',
            ]);
            if (!$released) {
                throw new RuntimeException('The promotion completed, but its run lock could not be finalized. Check the promotion history before retrying.');
            }

            return array_merge([
                'id' => $runId,
                'academicYear' => $academicYear,
                'status' => $isComplete ? 'completed' : 'partial',
                'remaining' => max(0, count($savedPlan) - $completed),
            ], $counts);
        } catch (Throwable $exception) {
            error_log('Student promotion run ' . $runId . ' failed: ' . $exception->getMessage());
            try {
                $released = $this->firebase->releaseDocumentLease(self::RUNS_COLLECTION, $runId, $lockToken, [
                    'status' => 'partial',
                    'processed' => $completed,
                    'lastError' => 'Processing stopped. Resume this saved run to continue.',
                ]);
                if (!$released) {
                    error_log('Student promotion run ' . $runId . ' could not release its processing lock.');
                }
            } catch (Throwable $releaseException) {
                error_log('Student promotion run ' . $runId . ' lock release failed: ' . $releaseException->getMessage());
            }

            throw new RuntimeException(
                'Promotion stopped before finishing. Completed changes are saved; use Resume to continue safely. Details: ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function recentRuns(int $limit = 10): array
    {
        $runs = $this->firebase->getCollection(self::RUNS_COLLECTION, [], max(1, min(50, $limit)), 0, false);
        usort($runs, static fn(array $first, array $second): int => strcmp(
            (string) ($second['createdAt'] ?? ''),
            (string) ($first['createdAt'] ?? '')
        ));

        return $runs;
    }

    /**
     * Undo a completed promotion run, only when every current student record still
     * matches the values applied by the run.
     */
    public function undo(string $academicYear, string $actorId): array
    {
        if (!self::isValidAcademicYear($academicYear)) {
            throw new RuntimeException('Enter a valid academic year.');
        }

        $runId = 'promotion_' . str_replace('/', '_', $academicYear);
        $run = $this->firebase->getDocument(self::RUNS_COLLECTION, $runId);
        if ($run === null) {
            throw new RuntimeException('No promotion run exists for this academic year.');
        }
        if (($run['status'] ?? '') !== 'completed') {
            throw new RuntimeException('Only a completed promotion run can be undone.');
        }

        $lockToken = bin2hex(random_bytes(16));
        if (!$this->firebase->claimDocumentLease(self::RUNS_COLLECTION, $runId, $lockToken)) {
            throw new RuntimeException('This promotion run is already being processed. Wait and try again.');
        }

        $undone = 0;
        try {
            $history = $this->historyForYear($academicYear);
            foreach ($history as $entry) {
                if (($entry['status'] ?? '') !== 'completed') {
                    continue;
                }

                $studentId = (string) ($entry['studentId'] ?? '');
                if ($studentId === '') {
                    throw new RuntimeException('A promotion history record is missing its student ID.');
                }
                $student = StudentService::find($studentId);
                if ($student === null) {
                    throw new RuntimeException('A student included in this run no longer exists.');
                }

                $currentGrade = self::studentGrade($student);
                $currentStatus = strtolower(trim((string) ($student['status'] ?? 'active')));
                $currentClass = trim((string) ($student['class'] ?? ''));
                $expectedStatus = strtolower(trim((string) ($entry['toStatus'] ?? '')));
                if ($expectedStatus === '') {
                    $expectedStatus = strtolower(trim((string) ($entry['action'] ?? '')) === 'graduate' ? 'graduated' : 'active');
                }

                $matchesStatus = $currentStatus === $expectedStatus;
                $matchesForm = $currentGrade !== null
                    && $currentGrade['value'] === (string) ($entry['toForm'] ?? '');
                $matchesClass = $currentClass === (string) ($entry['toClass'] ?? '');
                if (!$matchesStatus || !$matchesForm || !$matchesClass) {
                    throw new RuntimeException(
                        'A student record changed after promotion. Undo is paused to avoid overwriting newer data.'
                    );
                }

                StudentService::update($studentId, [
                    'form' => (string) ($entry['fromForm'] ?? ''),
                    'level' => (string) ($entry['fromForm'] ?? ''),
                    'class' => (string) ($entry['fromClass'] ?? ''),
                    'status' => (string) ($entry['fromStatus'] ?? 'active'),
                    'lastPromotionRunId' => '',
                    'lastPromotionAcademicYear' => '',
                ]);

                $historyId = hash('sha256', $runId . ':' . $studentId);
                $this->firebase->updateDocument(self::HISTORY_COLLECTION, $historyId, [
                    'status' => 'undone',
                    'undoneAt' => date(DATE_ATOM),
                    'undoneBy' => $actorId,
                ]);
                $undone++;
            }

            $released = $this->firebase->releaseDocumentLease(self::RUNS_COLLECTION, $runId, $lockToken, [
                'status' => 'undone',
                'processed' => 0,
                'undoneCount' => $undone,
                'undoneAt' => date(DATE_ATOM),
                'undoneBy' => $actorId,
                'completedAt' => '',
                'lastError' => '',
            ]);
            if (!$released) {
                throw new RuntimeException('Students were restored, but the run status could not be finalized.');
            }

            return ['academicYear' => $academicYear, 'undone' => $undone];
        } catch (Throwable $exception) {
            try {
                $this->firebase->releaseDocumentLease(self::RUNS_COLLECTION, $runId, $lockToken, [
                    'status' => 'completed',
                    'lastError' => $exception->getMessage(),
                ]);
            } catch (Throwable $releaseException) {
                error_log('Promotion undo lock release failed: ' . $releaseException->getMessage());
            }
            throw new RuntimeException(
                'The promotion undo stopped safely before additional records were changed. ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    public function findRunForYear(string $academicYear): ?array
    {
        if (!self::isValidAcademicYear($academicYear)) {
            return null;
        }

        return $this->firebase->getDocument(
            self::RUNS_COLLECTION,
            'promotion_' . str_replace('/', '_', trim($academicYear))
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function historyForYear(string $academicYear): array
    {
        $run = $this->findRunForYear($academicYear);
        if ($run === null || empty($run['id'])) {
            return [];
        }

        $history = $this->firebase->getAllCollection(
            self::HISTORY_COLLECTION,
            [['runId', '=', (string) $run['id']]]
        );

        usort($history, static fn(array $first, array $second): int => strcasecmp(
            (string) ($first['studentName'] ?? ''),
            (string) ($second['studentName'] ?? '')
        ));

        return $history;
    }
}
