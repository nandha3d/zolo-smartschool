<?php

namespace App\Services\Timetable;

use App\Models\ClassSection;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\SubjectTeacherAlternate;
use App\Models\TeacherAvailability;
use App\Models\Timetable;
use App\Models\TimetablePeriod;
use App\Models\TimetableTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TimetableRepairService
{
    private TimetableValidationService $validationService;
    private TimetablePlanningService $planningService;

    public function __construct(
        TimetableValidationService $validationService,
        TimetablePlanningService $planningService
    ) {
        $this->validationService = $validationService;
        $this->planningService = $planningService;
    }

    /**
     * Detect and repair conflicts in an existing timetable with minimum moves.
     *
     * @param int $schoolId
     * @param array $options [
     *   'class_section_id' => ?int,
     *   'teacher_id'       => ?int,
     *   'day'              => ?string,
     *   'dry_run'          => bool (default false),
     * ]
     * @return array
     */
    public function repair(int $schoolId, array $options = []): array
    {
        $classSectionId = $options['class_section_id'] ?? null;
        $teacherId = $options['teacher_id'] ?? null;
        $dayFilter = $options['day'] ?? null;
        $dryRun = !empty($options['dry_run']);

        $template = $this->planningService->getDefaultTemplate($schoolId);
        $instructionalPeriods = $template->instructional_periods;
        $workingDays = $template->working_days ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

        // Load all active school timetables
        $allTimetables = Timetable::where('school_id', $schoolId)
            ->where('type', 'Lecture')
            ->with(['subject_teacher.teacher', 'class_section', 'subject'])
            ->get();

        // 1. Identify conflicting slots
        $conflictingEntries = [];
        $unaffectedEntries = [];

        // Check for teacher clash or availability violation
        $teacherAvailabilities = TeacherAvailability::where('school_id', $schoolId)
            ->where('status', 'unavailable')
            ->get()
            ->groupBy('teacher_id')
            ->map(fn($g) => $g->groupBy('day')->map(fn($dg) => $dg->pluck('period_id')->flip()->toArray())->toArray())
            ->toArray();

        foreach ($allTimetables as $t) {
            $tTeacherId = $t->subject_teacher?->teacher_id;
            $hasConflict = false;
            $reason = '';

            // Check if matches scope filter
            if ($classSectionId && $t->class_section_id != $classSectionId) {
                $unaffectedEntries[] = $t;
                continue;
            }
            if ($teacherId && $tTeacherId != $teacherId) {
                $unaffectedEntries[] = $t;
                continue;
            }
            if ($dayFilter && $t->day != $dayFilter) {
                $unaffectedEntries[] = $t;
                continue;
            }

            // A locked entry cannot be moved
            if ($t->is_locked) {
                $unaffectedEntries[] = $t;
                continue;
            }

            // Check teacher availability
            if ($tTeacherId && $t->period_id && isset($teacherAvailabilities[$tTeacherId][$t->day][$t->period_id])) {
                $hasConflict = true;
                $reason = "Teacher is marked unavailable on {$t->day}.";
            }

            // Check teacher collision with another class
            if (!$hasConflict && $tTeacherId) {
                $collision = $allTimetables->first(function ($other) use ($t, $tTeacherId) {
                    if ($other->id === $t->id || $other->day !== $t->day) return false;
                    $otherTId = $other->subject_teacher?->teacher_id;
                    if ($otherTId != $tTeacherId) return false;
                    return ($other->start_time < $t->end_time && $other->end_time > $t->start_time);
                });

                if ($collision) {
                    $hasConflict = true;
                    $reason = "Teacher clash with Class #{$collision->class_section_id}.";
                }
            }

            if ($hasConflict) {
                $conflictingEntries[] = [
                    'model'   => $t,
                    'reason'  => $reason,
                ];
            } else {
                $unaffectedEntries[] = $t;
            }
        }

        if (empty($conflictingEntries)) {
            return [
                'success'             => true,
                'message'             => 'No conflicts detected in the selected timetable scope.',
                'moved'               => [],
                'unchanged_count'     => count($allTimetables),
                'conflicts_remaining' => 0,
            ];
        }

        // 2. Resolve conflicting slots by finding free valid candidate slots
        $moved = [];
        $unresolved = [];

        // Build occupancy from unaffected entries
        $occupancy = [];
        foreach ($unaffectedEntries as $u) {
            $uTeacherId = $u->subject_teacher?->teacher_id;
            $occupancy[$u->day][$u->start_time]['classes'][$u->class_section_id] = true;
            if ($uTeacherId) {
                $occupancy[$u->day][$u->start_time]['teachers'][$uTeacherId] = true;
            }
            if ($u->resource_id) {
                $occupancy[$u->day][$u->start_time]['resources'][$u->resource_id] = true;
            }
        }

        DB::beginTransaction();
        try {
            foreach ($conflictingEntries as $item) {
                $t = $item['model'];
                $tTeacherId = $t->subject_teacher?->teacher_id;
                $cId = $t->class_section_id;
                $placed = false;

                // Look for alternative slot in working days and instructional periods
                foreach ($workingDays as $candDay) {
                    foreach ($instructionalPeriods as $candPeriod) {
                        $cStart = $candPeriod->start_time;
                        $cPId = $candPeriod->id;

                        // Check class free
                        if (isset($occupancy[$candDay][$cStart]['classes'][$cId])) {
                            continue;
                        }

                        // Check teacher free & available
                        if ($tTeacherId) {
                            if (isset($occupancy[$candDay][$cStart]['teachers'][$tTeacherId])) {
                                continue;
                            }
                            if (isset($teacherAvailabilities[$tTeacherId][$candDay][$cPId])) {
                                continue;
                            }
                        }

                        // Check resource free
                        if ($t->resource_id && isset($occupancy[$candDay][$cStart]['resources'][$t->resource_id])) {
                            continue;
                        }

                        // Slot is valid! Move lesson here
                        $oldDay = $t->day;
                        $oldTime = "{$t->start_time} - {$t->end_time}";
                        $newTime = "{$candPeriod->start_time} - {$candPeriod->end_time}";

                        if (!$dryRun) {
                            $t->day = $candDay;
                            $t->period_id = $cPId;
                            $t->start_time = $candPeriod->start_time;
                            $t->end_time = $candPeriod->end_time;
                            $t->save();
                        }

                        $occupancy[$candDay][$cStart]['classes'][$cId] = true;
                        if ($tTeacherId) $occupancy[$candDay][$cStart]['teachers'][$tTeacherId] = true;
                        if ($t->resource_id) $occupancy[$candDay][$cStart]['resources'][$t->resource_id] = true;

                        $moved[] = [
                            'timetable_id'     => $t->id,
                            'class_section_id' => $cId,
                            'subject_name'     => $t->subject?->name ?? 'Subject',
                            'teacher_name'     => $t->subject_teacher?->teacher?->full_name ?? 'Teacher',
                            'from'             => "{$oldDay} {$oldTime}",
                            'to'               => "{$candDay} {$newTime}",
                            'reason'           => $item['reason'],
                        ];

                        $placed = true;
                        break 2;
                    }
                }

                if (!$placed) {
                    $unresolved[] = [
                        'timetable_id' => $t->id,
                        'reason'       => "No alternative valid slot available without creating new clashes.",
                    ];
                }
            }

            if (!empty($unresolved)) {
                DB::rollBack();
                return [
                    'success'             => false,
                    'message'             => 'Automatic repair could not resolve all conflicts without creating new violations.',
                    'moved'               => [],
                    'unchanged_count'     => count($unaffectedEntries),
                    'conflicts_remaining' => count($unresolved),
                    'unresolved'          => $unresolved,
                ];
            }

            if (!$dryRun) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            return [
                'success'             => true,
                'message'             => 'Repaired ' . count($moved) . ' conflicting lessons successfully.',
                'moved'               => $moved,
                'unchanged_count'     => count($unaffectedEntries),
                'conflicts_remaining' => 0,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Repair failed: ' . $e->getMessage(),
                'moved'   => [],
            ];
        }
    }
}
