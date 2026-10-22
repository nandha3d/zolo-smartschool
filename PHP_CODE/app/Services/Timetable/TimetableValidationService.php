<?php

namespace App\Services\Timetable;

use App\Models\ClassSection;
use App\Models\ClassSubject;
use App\Models\Subject;
use App\Models\SubjectScheduleRequirement;
use App\Models\SubjectTeacher;
use App\Models\SubjectTeacherAlternate;
use App\Models\TeacherAvailability;
use App\Models\ClassSectionAvailability;
use App\Models\TimetableResourceAvailability;
use App\Models\Timetable;
use App\Models\TimetablePeriod;
use App\Models\User;
use Carbon\Carbon;

class TimetableValidationService
{
    /**
     * Validate an in-memory candidate schedule or collection of entries against all hard constraints (H01-H15).
     *
     * @param array $entries Array of slot definitions:
     *   [
     *     'id'                 => nullable int (for existing persistent entries),
     *     'class_section_id'   => int,
     *     'subject_id'         => nullable int,
     *     'teacher_id'         => nullable int,
     *     'resource_id'        => nullable int,
     *     'period_id'          => nullable int,
     *     'day'                => string ('Monday', etc.),
     *     'start_time'         => string ('09:00:00'),
     *     'end_time'           => string ('09:45:00'),
     *     'type'               => string ('Lecture' or 'Break'),
     *     'is_locked'          => bool,
     *   ]
     * @param array $context [
     *   'school_id'        => int,
     *   'semester_id'      => ?int,
     *   'requirements'     => array|Collection of SubjectScheduleRequirement,
     *   'locked_entries'   => array of existing locked entries to verify against,
     *   'availabilities'   => array of teacher, class, resource availabilities,
     * ]
     * @return array ['valid' => bool, 'violations' => array, 'errors_count' => int]
     */
    public function validateCompleteTimetable(array $entries, array $context): array
    {
        $violations = [];
        $schoolId = $context['school_id'] ?? null;
        $semesterId = $context['semester_id'] ?? null;
        $requirements = $context['requirements'] ?? [];
        $lockedEntries = $context['locked_entries'] ?? [];

        // H12: School Isolation
        $violations = array_merge($violations, $this->validateSchoolScope($entries, $schoolId));

        // H11: Locked Slots verification
        if (!empty($lockedEntries)) {
            $violations = array_merge($violations, $this->validateLockedSlots($entries, $lockedEntries));
        }

        // Intra-schedule conflict checks (H01, H02, H03, H14, H15)
        $violations = array_merge($violations, $this->validateConflictsAndOverlaps($entries));

        // H04, H05, H06: Availability
        $availabilities = $context['availabilities'] ?? [];
        $violations = array_merge($violations, $this->validateAvailability($entries, $availabilities, $schoolId));

        // H07: Weekly requirements check
        if (!empty($requirements)) {
            $violations = array_merge($violations, $this->validateWeeklyRequirements($entries, $requirements));
        }

        // H08, H09: Subject & Teacher validity
        $violations = array_merge($violations, $this->validateAssignments($entries, $schoolId));

        return [
            'valid'        => empty($violations),
            'violations'   => $violations,
            'errors_count' => count($violations),
        ];
    }

    /**
     * H01, H02, H03, H14, H15: Validate conflicts and time overlaps across all candidate entries.
     */
    public function validateConflictsAndOverlaps(array $entries): array
    {
        $violations = [];
        $lectureEntries = array_filter($entries, function ($e) {
            return ($e['type'] ?? 'Lecture') === 'Lecture';
        });

        $count = count($lectureEntries);
        $indexed = array_values($lectureEntries);

        for ($i = 0; $i < $count; $i++) {
            $a = $indexed[$i];
            $dayA = $a['day'];
            $startA = $a['start_time'];
            $endA = $a['end_time'];
            $classA = $a['class_section_id'];
            $teacherA = $a['teacher_id'] ?? null;
            $resourceA = $a['resource_id'] ?? null;

            for ($j = $i + 1; $j < $count; $j++) {
                $b = $indexed[$j];
                if ($b['day'] !== $dayA) {
                    continue;
                }

                $overlap = $this->timesOverlap($startA, $endA, $b['start_time'], $b['end_time']);
                if (!$overlap) {
                    continue;
                }

                // H01: Class Conflict
                if ($classA === $b['class_section_id']) {
                    $violations[] = [
                        'code'             => 'CLASS_CONFLICT',
                        'severity'         => 'error',
                        'day'              => $dayA,
                        'class_section_id' => $classA,
                        'time'             => "$startA - $endA",
                        'message'          => "Class section #{$classA} has simultaneous lessons scheduled at {$dayA} {$startA}-{$endA}."
                    ];
                }

                // H02: Teacher Conflict
                if ($teacherA && !empty($b['teacher_id']) && $teacherA === $b['teacher_id']) {
                    $violations[] = [
                        'code'       => 'TEACHER_CONFLICT',
                        'severity'   => 'error',
                        'day'        => $dayA,
                        'teacher_id' => $teacherA,
                        'time'       => "$startA - $endA",
                        'message'    => "Teacher #{$teacherA} is assigned to simultaneous lessons at {$dayA} {$startA}-{$endA}."
                    ];
                }

                // H03: Resource Conflict
                if ($resourceA && !empty($b['resource_id']) && $resourceA === $b['resource_id']) {
                    $violations[] = [
                        'code'        => 'RESOURCE_CONFLICT',
                        'severity'    => 'error',
                        'day'         => $dayA,
                        'resource_id' => $resourceA,
                        'time'        => "$startA - $endA",
                        'message'     => "Resource #{$resourceA} is double-booked at {$dayA} {$startA}-{$endA}."
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * H04, H05, H06: Validate teacher, class, and resource availability.
     */
    public function validateAvailability(array $entries, array $availabilities = [], ?int $schoolId = null): array
    {
        $violations = [];

        // Build quick lookup maps: [entity_id][day][period_id] => status
        $teacherMap = $availabilities['teachers'] ?? [];
        $classMap = $availabilities['classes'] ?? [];
        $resourceMap = $availabilities['resources'] ?? [];

        foreach ($entries as $e) {
            if (($e['type'] ?? 'Lecture') !== 'Lecture') continue;

            $day = $e['day'];
            $periodId = $e['period_id'] ?? null;
            if (!$periodId) continue;

            $teacherId = $e['teacher_id'] ?? null;
            if ($teacherId && isset($teacherMap[$teacherId][$day][$periodId])) {
                if ($teacherMap[$teacherId][$day][$periodId] === 'unavailable') {
                    $violations[] = [
                        'code'       => 'TEACHER_UNAVAILABLE',
                        'severity'   => 'error',
                        'day'        => $day,
                        'period_id'  => $periodId,
                        'teacher_id' => $teacherId,
                        'message'    => "Teacher #{$teacherId} is explicitly marked unavailable on {$day} period #{$periodId}."
                    ];
                }
            }

            $classId = $e['class_section_id'];
            if (isset($classMap[$classId][$day][$periodId])) {
                if ($classMap[$classId][$day][$periodId] === 'unavailable') {
                    $violations[] = [
                        'code'             => 'CLASS_UNAVAILABLE',
                        'severity'         => 'error',
                        'day'              => $day,
                        'period_id'        => $periodId,
                        'class_section_id' => $classId,
                        'message'          => "Class section #{$classId} is unavailable on {$day} period #{$periodId}."
                    ];
                }
            }

            $resourceId = $e['resource_id'] ?? null;
            if ($resourceId && isset($resourceMap[$resourceId][$day][$periodId])) {
                if ($resourceMap[$resourceId][$day][$periodId] === 'unavailable') {
                    $violations[] = [
                        'code'        => 'RESOURCE_UNAVAILABLE',
                        'severity'    => 'error',
                        'day'         => $day,
                        'period_id'   => $periodId,
                        'resource_id' => $resourceId,
                        'message'     => "Resource #{$resourceId} is unavailable on {$day} period #{$periodId}."
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * H07: Required Weekly Periods verification.
     * scheduled_count must equal weekly_periods exactly.
     */
    public function validateWeeklyRequirements(array $entries, $requirements): array
    {
        $violations = [];

        // Count scheduled periods by [class_section_id][subject_id]
        $scheduledCounts = [];
        foreach ($entries as $e) {
            if (($e['type'] ?? 'Lecture') !== 'Lecture') continue;
            $classId = $e['class_section_id'];
            $subjectId = $e['subject_id'] ?? null;
            if ($subjectId) {
                $scheduledCounts[$classId][$subjectId] = ($scheduledCounts[$classId][$subjectId] ?? 0) + 1;
            }
        }

        foreach ($requirements as $req) {
            $classId = is_array($req) ? $req['class_section_id'] : $req->class_section_id;
            $subjectId = is_array($req) ? $req['subject_id'] : $req->subject_id;
            $weekly = is_array($req) ? (int)$req['weekly_periods'] : (int)$req->weekly_periods;
            $isActive = is_array($req) ? ($req['is_active'] ?? true) : $req->is_active;

            if (!$isActive || $weekly <= 0) continue;

            $actual = $scheduledCounts[$classId][$subjectId] ?? 0;
            if ($actual !== $weekly) {
                $violations[] = [
                    'code'             => 'WEEKLY_PERIOD_REQUIREMENT_UNSATISFIED',
                    'severity'         => 'error',
                    'class_section_id' => $classId,
                    'subject_id'       => $subjectId,
                    'required'         => $weekly,
                    'actual'           => $actual,
                    'message'          => "Requirement unsatisfied for Class #{$classId}, Subject #{$subjectId}: expected {$weekly} periods, but {$actual} scheduled."
                ];
            }
        }

        return $violations;
    }

    /**
     * H11: Locked Slots verification.
     * Every locked entry must exist unchanged in the target entries.
     */
    public function validateLockedSlots(array $entries, array $lockedEntries): array
    {
        $violations = [];

        foreach ($lockedEntries as $locked) {
            $lockedId = $locked['id'] ?? null;
            $day = $locked['day'];
            $start = $locked['start_time'];
            $end = $locked['end_time'];
            $classId = $locked['class_section_id'];
            $subjectId = $locked['subject_id'] ?? null;
            $teacherId = $locked['teacher_id'] ?? $locked['subject_teacher']['teacher_id'] ?? null;

            $matched = false;
            foreach ($entries as $candidate) {
                if ($candidate['class_section_id'] === $classId &&
                    $candidate['day'] === $day &&
                    $candidate['start_time'] === $start &&
                    $candidate['end_time'] === $end) {

                    $candTeacherId = $candidate['teacher_id'] ?? $candidate['subject_teacher']['teacher_id'] ?? null;
                    $candSubjectId = $candidate['subject_id'] ?? null;

                    $subjectMatch = empty($subjectId) || ($candSubjectId == $subjectId);
                    $teacherMatch = empty($teacherId) || empty($candTeacherId) || ($candTeacherId == $teacherId);

                    if ($subjectMatch && $teacherMatch) {
                        $matched = true;
                        break;
                    }
                }
            }

            if (!$matched) {
                $violations[] = [
                    'code'             => 'LOCKED_SLOT_CONFLICT',
                    'severity'         => 'error',
                    'class_section_id' => $classId,
                    'day'              => $day,
                    'time'             => "$start - $end",
                    'message'          => "Locked slot for Class #{$classId} at {$day} {$start}-{$end} was modified or displaced."
                ];
            }
        }

        return $violations;
    }

    /**
     * H08 & H09: Verify subjects and teachers are legally assigned to class.
     */
    public function validateAssignments(array $entries, ?int $schoolId = null): array
    {
        $violations = [];
        // Pre-check unique pairs of [class_section_id, subject_id, teacher_id]
        $pairs = [];
        foreach ($entries as $e) {
            if (($e['type'] ?? 'Lecture') !== 'Lecture') continue;
            $classId = $e['class_section_id'];
            $subjectId = $e['subject_id'] ?? null;
            $teacherId = $e['teacher_id'] ?? null;

            if ($subjectId && $teacherId) {
                $key = "{$classId}_{$subjectId}_{$teacherId}";
                $pairs[$key] = [
                    'class_section_id' => $classId,
                    'subject_id'       => $subjectId,
                    'teacher_id'       => $teacherId
                ];
            }
        }

        if (empty($pairs)) return $violations;

        // Fetch valid SubjectTeacher combinations
        $classIds = array_unique(array_column($pairs, 'class_section_id'));
        $validAssignments = SubjectTeacher::whereIn('class_section_id', $classIds)
            ->get(['class_section_id', 'subject_id', 'teacher_id'])
            ->map(function ($st) {
                return "{$st->class_section_id}_{$st->subject_id}_{$st->teacher_id}";
            })
            ->flip()
            ->toArray();

        // Also check alternate teachers
        $alternates = SubjectTeacherAlternate::where('is_active', true)
            ->with('subject_teacher:id,class_section_id,subject_id')
            ->get();
        foreach ($alternates as $alt) {
            if ($alt->subject_teacher) {
                $altKey = "{$alt->subject_teacher->class_section_id}_{$alt->subject_teacher->subject_id}_{$alt->alternate_teacher_id}";
                $validAssignments[$altKey] = true;
            }
        }

        foreach ($pairs as $key => $p) {
            if (!isset($validAssignments[$key])) {
                $violations[] = [
                    'code'             => 'INVALID_TEACHER_ASSIGNMENT',
                    'severity'         => 'error',
                    'class_section_id' => $p['class_section_id'],
                    'subject_id'       => $p['subject_id'],
                    'teacher_id'       => $p['teacher_id'],
                    'message'          => "Teacher #{$p['teacher_id']} is not authorized to teach Subject #{$p['subject_id']} in Class #{$p['class_section_id']}."
                ];
            }
        }

        return $violations;
    }

    /**
     * H12: Verify all records belong to the given school.
     */
    public function validateSchoolScope(array $entries, ?int $schoolId): array
    {
        $violations = [];
        if (!$schoolId) return $violations;

        // Check if any class section belongs to a different school
        $classIds = array_unique(array_column($entries, 'class_section_id'));
        $invalidClasses = ClassSection::whereIn('id', $classIds)
            ->where('school_id', '!=', $schoolId)
            ->pluck('id')
            ->toArray();

        foreach ($invalidClasses as $invId) {
            $violations[] = [
                'code'             => 'SCHOOL_ISOLATION_VIOLATION',
                'severity'         => 'error',
                'class_section_id' => $invId,
                'message'          => "Class section #{$invId} belongs to a different school!"
            ];
        }

        return $violations;
    }

    /**
     * Check single manual update or quick-edit slot for conflicts against existing persistent timetable.
     */
    public function validateSingleSlot(
        int $schoolId,
        int $classSectionId,
        string $day,
        string $startTime,
        string $endTime,
        ?int $teacherId = null,
        ?int $resourceId = null,
        int|array|null $excludeTimetableId = null,
        ?int $periodId = null
    ): array {
        $violations = [];
        // One id, or several when validating a swap of two lessons.
        $excludeIds = array_values(array_filter((array) $excludeTimetableId));

        // 1. Check Class conflict
        $classConflict = Timetable::where('school_id', $schoolId)
            ->where('class_section_id', $classSectionId)
            ->where('day', $day)
            ->where('type', 'Lecture')
            ->when($excludeIds, function ($q) use ($excludeIds) {
                $q->whereNotIn('id', $excludeIds);
            })
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
            })
            ->first();

        if ($classConflict) {
            $violations[] = [
                'code'    => 'CLASS_CONFLICT',
                'message' => "Another lesson is already scheduled for this class on {$day} from {$classConflict->start_time} to {$classConflict->end_time}."
            ];
        }

        // 2. Check Teacher conflict across whole school
        if ($teacherId) {
            $teacherConflict = Timetable::where('school_id', $schoolId)
                ->where('day', $day)
                ->where('type', 'Lecture')
                ->when($excludeIds, function ($q) use ($excludeIds) {
                    $q->whereNotIn('id', $excludeIds);
                })
                ->whereHas('subject_teacher', function ($q) use ($teacherId) {
                    $q->where('teacher_id', $teacherId);
                })
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
                })
                ->with(['class_section.class', 'class_section.section', 'class_section.medium'])
                ->first();

            if ($teacherConflict) {
                $className = $teacherConflict->class_section?->full_name ?? "Class #{$teacherConflict->class_section_id}";
                $violations[] = [
                    'code'    => 'TEACHER_CONFLICT',
                    'message' => "Teacher is already scheduled in {$className} on {$day} ({$teacherConflict->start_time} - {$teacherConflict->end_time})."
                ];
            }

            // Check Teacher Availability
            if ($periodId) {
                $isUnavailable = TeacherAvailability::where('school_id', $schoolId)
                    ->where('teacher_id', $teacherId)
                    ->where('day', $day)
                    ->where('period_id', $periodId)
                    ->where('status', 'unavailable')
                    ->exists();

                if ($isUnavailable) {
                    $violations[] = [
                        'code'    => 'TEACHER_UNAVAILABLE',
                        'message' => "Teacher is explicitly marked unavailable for this period."
                    ];
                }
            }
        }

        // 3. Check Resource conflict
        if ($resourceId) {
            $resourceConflict = Timetable::where('school_id', $schoolId)
                ->where('day', $day)
                ->where('resource_id', $resourceId)
                ->when($excludeIds, function ($q) use ($excludeIds) {
                    $q->whereNotIn('id', $excludeIds);
                })
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
                })
                ->first();

            if ($resourceConflict) {
                $violations[] = [
                    'code'    => 'RESOURCE_CONFLICT',
                    'message' => "Selected resource/room is already occupied at this time."
                ];
            }
        }

        return $violations;
    }

    /**
     * Check if two time ranges overlap.
     */
    public function timesOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        $sA = Carbon::parse($startA)->timestamp;
        $eA = Carbon::parse($endA)->timestamp;
        $sB = Carbon::parse($startB)->timestamp;
        $eB = Carbon::parse($endB)->timestamp;

        return ($sA < $eB) && ($eA > $sB);
    }
}
