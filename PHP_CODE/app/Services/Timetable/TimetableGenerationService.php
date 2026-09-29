<?php

namespace App\Services\Timetable;

use App\Models\ClassSection;
use App\Models\ClassSectionAvailability;
use App\Models\Subject;
use App\Models\SubjectScheduleRequirement;
use App\Models\SubjectTeacher;
use App\Models\SubjectTeacherAlternate;
use App\Models\TeacherAvailability;
use App\Models\Timetable;
use App\Models\TimetableGenerationEntry;
use App\Models\TimetableGenerationRun;
use App\Models\TimetablePeriod;
use App\Models\TimetableResource;
use App\Models\TimetableResourceAvailability;
use App\Models\TimetableTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class TimetableGenerationService
{
    private TimetableFeasibilityService $feasibilityService;
    private TimetableValidationService $validationService;
    private TimetableScoringService $scoringService;
    private TimetablePlanningService $planningService;

    // Safety limits
    private int $maxSearchNodes = 100000;
    private int $maxExecutionSeconds = 60;

    public function __construct(
        TimetableFeasibilityService $feasibilityService,
        TimetableValidationService $validationService,
        TimetableScoringService $scoringService,
        TimetablePlanningService $planningService
    ) {
        $this->feasibilityService = $feasibilityService;
        $this->validationService = $validationService;
        $this->scoringService = $scoringService;
        $this->planningService = $planningService;
    }

    /**
     * Run deterministic multi-class timetable generation preview.
     *
     * @param array $params [
     *   'school_id'         => int,
     *   'template_id'       => ?int,
     *   'semester_id'       => ?int,
     *   'session_year_id'   => ?int,
     *   'class_section_ids' => array of int,
     *   'started_by'        => ?int,
     *   'preserve_existing' => bool (default true),
     *   'mode'              => string ('full', 'repair', 'classes', 'teacher'),
     * ]
     * @return array
     */
    public function generate(array $params): array
    {
        $schoolId = (int)$params['school_id'];
        $semesterId = !empty($params['semester_id']) ? (int)$params['semester_id'] : null;
        $sessionYearId = !empty($params['session_year_id']) ? (int)$params['session_year_id'] : null;
        $startedBy = !empty($params['started_by']) ? (int)$params['started_by'] : null;
        $preserveExisting = array_key_exists('preserve_existing', $params) ? (bool)$params['preserve_existing'] : true;
        $mode = $params['mode'] ?? 'full';

        // 1. Resolve Template
        $template = !empty($params['template_id'])
            ? TimetableTemplate::where('school_id', $schoolId)->with(['periods'])->find($params['template_id'])
            : $this->planningService->getDefaultTemplate($schoolId);

        if (!$template) {
            $template = $this->planningService->getDefaultTemplate($schoolId);
        }

        $classSectionIds = $params['class_section_ids'] ?? [];
        if (empty($classSectionIds)) {
            $classSectionIds = ClassSection::where('school_id', $schoolId)->pluck('id')->toArray();
        }

        if (empty($classSectionIds)) {
            return [
                'error'     => true,
                'status'    => 'failed',
                'message'   => 'No class sections selected for timetable generation.',
                'conflicts' => []
            ];
        }

        // 2. Fetch and prepare requirements
        $requirements = SubjectScheduleRequirement::where('school_id', $schoolId)
            ->whereIn('class_section_id', $classSectionIds)
            ->where('is_active', true)
            ->when($semesterId, function ($q) use ($semesterId) {
                $q->where(function ($sq) use ($semesterId) {
                    $sq->where('semester_id', $semesterId)->orWhereNull('semester_id');
                });
            })
            ->with(['subject', 'class_section'])
            ->get();

        // 3. Pre-Solver Feasibility Check
        $feasibility = $this->feasibilityService->checkFeasibility(
            $schoolId,
            $template,
            $classSectionIds,
            $requirements,
            ['semester_id' => $semesterId]
        );

        if (!$feasibility['feasible']) {
            $run = $this->createGenerationRunRecord($schoolId, $sessionYearId, $semesterId, $template->id, $startedBy, 'failed', [
                'error_message' => 'Pre-solver feasibility check failed.',
                'conflicts'     => $feasibility['conflicts'],
            ]);

            return [
                'error'             => true,
                'generation_run_id' => $run->id,
                'status'            => 'failed',
                'message'           => 'Cannot generate timetable due to capacity or requirement constraints.',
                'conflicts'         => $feasibility['conflicts'],
                'statistics'        => [],
                'entries'           => [],
                'quality'           => []
            ];
        }

        // 4. Load context data into memory
        $workingDays = $template->working_days ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $instructionalPeriods = $template->instructional_periods;
        $allPeriods = $template->periods;

        // Fetch locked timetable entries that must be preserved
        $lockedSlots = Timetable::where('school_id', $schoolId)
            ->where('is_locked', true)
            ->when($semesterId, function ($q) use ($semesterId) {
                $q->where(function ($sq) use ($semesterId) {
                    $sq->where('semester_id', $semesterId)->orWhereNull('semester_id');
                });
            })
            ->get()
            ->toArray();

        // Also fetch any other classes' timetables if running in partial mode
        $externalOccupancies = [];
        if ($preserveExisting) {
            $existingOthers = Timetable::where('school_id', $schoolId)
                ->whereNotIn('class_section_id', $classSectionIds)
                ->where('type', 'Lecture')
                ->when($semesterId, function ($q) use ($semesterId) {
                    $q->where(function ($sq) use ($semesterId) {
                        $sq->where('semester_id', $semesterId)->orWhereNull('semester_id');
                    });
                })
                ->with('subject_teacher')
                ->get();

            foreach ($existingOthers as $eo) {
                $tId = $eo->subject_teacher?->teacher_id;
                $externalOccupancies[] = [
                    'day'              => $eo->day,
                    'start_time'       => $eo->start_time,
                    'end_time'         => $eo->end_time,
                    'class_section_id' => $eo->class_section_id,
                    'teacher_id'       => $tId,
                    'resource_id'      => $eo->resource_id,
                    'period_id'        => $eo->period_id,
                    'is_locked'        => (bool)$eo->is_locked,
                ];
            }
        }

        // 5. Build Lesson Blocks to Schedule
        $blocks = $this->buildLessonBlocks($classSectionIds, $requirements, $lockedSlots);

        // 6. Run Deterministic Constraint Search Solver
        $solverResult = $this->solveDeterministic(
            $blocks,
            $template,
            $workingDays,
            $instructionalPeriods,
            $allPeriods,
            $lockedSlots,
            $externalOccupancies,
            $schoolId
        );

        if (!$solverResult['success']) {
            $run = $this->createGenerationRunRecord($schoolId, $sessionYearId, $semesterId, $template->id, $startedBy, 'failed', [
                'error_message' => $solverResult['message'],
                'conflicts'     => $solverResult['conflicts'],
            ]);

            return [
                'error'             => true,
                'generation_run_id' => $run->id,
                'status'            => 'failed',
                'message'           => $solverResult['message'],
                'conflicts'         => $solverResult['conflicts'],
                'statistics'        => $solverResult['statistics'] ?? [],
                'entries'           => [],
                'quality'           => []
            ];
        }

        $finalSchedule = $solverResult['schedule'];

        // 7. Validate Candidate Timetable Independently (Section 22 & 46)
        $validationContext = [
            'school_id'      => $schoolId,
            'semester_id'    => $semesterId,
            'requirements'   => $requirements,
            'locked_entries' => $lockedSlots,
        ];
        $validation = $this->validationService->validateCompleteTimetable($finalSchedule, $validationContext);

        if (!$validation['valid']) {
            $run = $this->createGenerationRunRecord($schoolId, $sessionYearId, $semesterId, $template->id, $startedBy, 'failed', [
                'error_message' => 'Post-solve validation found constraint violations.',
                'conflicts'     => $validation['violations'],
            ]);

            return [
                'error'             => true,
                'generation_run_id' => $run->id,
                'status'            => 'failed',
                'message'           => 'Post-solve validation failed.',
                'conflicts'         => $validation['violations'],
                'statistics'        => [],
                'entries'           => [],
                'quality'           => []
            ];
        }

        // 8. Calculate Quality Metrics
        $quality = $this->scoringService->calculateQualityMetrics(
            $finalSchedule,
            $requirements,
            $workingDays,
            $instructionalPeriods->count()
        );

        // 9. Persist to Staging (timetable_generation_runs & timetable_generation_entries)
        $generationRun = DB::transaction(function () use (
            $schoolId,
            $sessionYearId,
            $semesterId,
            $template,
            $startedBy,
            $finalSchedule,
            $quality,
            $classSectionIds
        ) {
            $run = TimetableGenerationRun::create([
                'school_id'         => $schoolId,
                'session_year_id'   => $sessionYearId,
                'semester_id'       => $semesterId,
                'template_id'       => $template->id,
                'started_by'        => $startedBy,
                'status'            => 'completed',
                'algorithm_version' => 'v2-deterministic',
                'started_at'        => Carbon::now(),
                'completed_at'      => Carbon::now(),
                'statistics'        => [
                    'classes_count'         => count($classSectionIds),
                    'total_slots_scheduled' => count($finalSchedule),
                    'quality'               => $quality,
                ]
            ]);

            foreach ($finalSchedule as $slot) {
                TimetableGenerationEntry::create([
                    'generation_run_id'  => $run->id,
                    'class_section_id'   => $slot['class_section_id'],
                    'subject_id'         => $slot['subject_id'] ?? null,
                    'subject_teacher_id' => $slot['subject_teacher_id'] ?? null,
                    'teacher_id'         => $slot['teacher_id'] ?? null,
                    'resource_id'        => $slot['resource_id'] ?? null,
                    'day'                => $slot['day'],
                    'period_id'          => $slot['period_id'] ?? null,
                    'start_time'         => $slot['start_time'],
                    'end_time'           => $slot['end_time'],
                    'type'               => $slot['type'] ?? 'Lecture',
                    'is_locked'          => !empty($slot['is_locked']),
                    'constraint_score'   => $slot['score'] ?? 0,
                    'explanation'        => $slot['explanation'] ?? null,
                ]);
            }

            return $run;
        });

        // 10. Return Preview Response
        $entriesPreview = array_slice($finalSchedule, 0, 50);

        return [
            'error'             => false,
            'generation_run_id' => $generationRun->id,
            'run_id'            => $generationRun->id,
            'status'            => 'completed',
            'message'           => 'Timetable generated successfully with ' . count($finalSchedule) . ' slots across ' . count($classSectionIds) . ' classes.',
            'statistics'        => $generationRun->statistics,
            'conflicts'         => [],
            'entries'           => $finalSchedule,
            'entries_count'     => count($finalSchedule),
            'entries_preview'   => $entriesPreview,
            'quality'           => $quality,
            'metrics'           => $quality,
        ];
    }

    /**
     * Build lesson blocks from requirements, deducting already-scheduled locked slots.
     */
    private function buildLessonBlocks(array $classSectionIds, $requirements, array $lockedSlots): array
    {
        // Count already locked slots by [class_section_id][subject_id]
        $lockedCounts = [];
        foreach ($lockedSlots as $ls) {
            if (($ls['type'] ?? 'Lecture') === 'Lecture' && !empty($ls['subject_id'])) {
                $cId = $ls['class_section_id'];
                $sId = $ls['subject_id'];
                $lockedCounts[$cId][$sId] = ($lockedCounts[$cId][$sId] ?? 0) + 1;
            }
        }

        // Fetch subject teacher mappings
        $subjectTeachers = SubjectTeacher::whereIn('class_section_id', $classSectionIds)
            ->get()
            ->groupBy('class_section_id');

        $blocks = [];

        foreach ($requirements as $req) {
            $classId = $req->class_section_id;
            $subjectId = $req->subject_id;
            $weeklyTotal = (int)$req->weekly_periods;

            // Deduct already locked lessons
            $alreadyLocked = $lockedCounts[$classId][$subjectId] ?? 0;
            $remaining = max(0, $weeklyTotal - $alreadyLocked);

            if ($remaining === 0) continue;

            $stList = $subjectTeachers[$classId] ?? collect();
            $st = $stList->firstWhere('subject_id', $subjectId);
            $teacherId = $st?->teacher_id;
            $subjectTeacherId = $st?->id;

            $allowConsecutive = (bool)$req->allow_consecutive;
            $reqConsecutive = $allowConsecutive ? ((int)($req->required_consecutive_periods ?? 2)) : 1;
            if ($reqConsecutive <= 1) $reqConsecutive = 1;

            $blockIndex = 1;
            while ($remaining > 0) {
                $blockSize = ($allowConsecutive && $remaining >= $reqConsecutive && $reqConsecutive > 1)
                    ? $reqConsecutive
                    : 1;

                $blocks[] = [
                    'block_id'           => "{$classId}_{$subjectId}_{$blockIndex}",
                    'class_section_id'   => $classId,
                    'subject_id'         => $subjectId,
                    'subject_teacher_id' => $subjectTeacherId,
                    'teacher_id'         => $teacherId,
                    'resource_id'        => $req->resource_id,
                    'block_size'         => $blockSize,
                    'priority'           => (int)$req->priority,
                    'weekly_periods'     => $weeklyTotal,
                    'avoid_first_period' => (bool)$req->avoid_first_period,
                    'avoid_last_period'  => (bool)$req->avoid_last_period,
                    'preferred_day'      => $req->preferred_day,
                ];

                $remaining -= $blockSize;
                $blockIndex++;
            }
        }

        // Deterministic sorting (Section 21):
        // 1. Most constrained first (higher block_size double periods)
        // 2. Highest priority first
        // 3. Highest weekly periods first
        // 4. Stable class_section_id, subject_id tie-breaker
        usort($blocks, function ($a, $b) {
            if ($a['block_size'] !== $b['block_size']) {
                return $b['block_size'] <=> $a['block_size'];
            }
            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] <=> $a['priority'];
            }
            if ($a['weekly_periods'] !== $b['weekly_periods']) {
                return $b['weekly_periods'] <=> $a['weekly_periods'];
            }
            if ($a['class_section_id'] !== $b['class_section_id']) {
                return $a['class_section_id'] <=> $b['class_section_id'];
            }
            return $a['subject_id'] <=> $b['subject_id'];
        });

        return $blocks;
    }

    /**
     * Deterministic constraint search solver with backtracking.
     */
    private function solveDeterministic(
        array $blocks,
        TimetableTemplate $template,
        array $workingDays,
        $instructionalPeriods,
        $allPeriods,
        array $lockedSlots,
        array $externalOccupancies,
        int $schoolId
    ): array {
        // Fast in-memory bitmask / occupancy maps:
        // occupancy[day][period_id] => ['classes' => [...], 'teachers' => [...], 'resources' => [...]]
        $occupancy = [];
        foreach ($workingDays as $d) {
            foreach ($allPeriods as $p) {
                $occupancy[$d][$p->id] = [
                    'classes'   => [],
                    'teachers'  => [],
                    'resources' => [],
                ];
            }
        }

        // Preload teacher & class unavailabilities
        $teacherUnavail = TeacherAvailability::where('school_id', $schoolId)
            ->where('status', 'unavailable')
            ->get()
            ->groupBy('teacher_id')
            ->map(fn($g) => $g->groupBy('day')->map(fn($dg) => $dg->pluck('period_id')->flip()->toArray())->toArray())
            ->toArray();

        $classUnavail = ClassSectionAvailability::where('school_id', $schoolId)
            ->where('status', 'unavailable')
            ->get()
            ->groupBy('class_section_id')
            ->map(fn($g) => $g->groupBy('day')->map(fn($dg) => $dg->pluck('period_id')->flip()->toArray())->toArray())
            ->toArray();

        $resourceUnavail = TimetableResourceAvailability::where('school_id', $schoolId)
            ->where('status', 'unavailable')
            ->get()
            ->groupBy('resource_id')
            ->map(fn($g) => $g->groupBy('day')->map(fn($dg) => $dg->pluck('period_id')->flip()->toArray())->toArray())
            ->toArray();

        // Seed locked slots and external occupancies into the schedule and occupancy map
        $currentSchedule = [];

        foreach ($lockedSlots as $ls) {
            $pId = $ls['period_id'] ?? null;
            if (!$pId) {
                // Find matching period from template
                $matchedPeriod = $allPeriods->first(function ($p) use ($ls) {
                    return $p->start_time === $ls['start_time'] && $p->end_time === $ls['end_time'];
                });
                $pId = $matchedPeriod?->id;
            }

            $currentSchedule[] = [
                'day'                => $ls['day'],
                'period_id'          => $pId,
                'period_number'      => $matchedPeriod?->period_number ?? 0,
                'start_time'         => $ls['start_time'],
                'end_time'           => $ls['end_time'],
                'type'               => $ls['type'],
                'class_section_id'   => $ls['class_section_id'],
                'subject_id'         => $ls['subject_id'],
                'subject_teacher_id' => $ls['subject_teacher_id'],
                'teacher_id'         => $ls['teacher_id'] ?? $ls['subject_teacher']['teacher_id'] ?? null,
                'resource_id'        => $ls['resource_id'],
                'is_locked'          => true,
            ];

            if ($pId && isset($occupancy[$ls['day']][$pId])) {
                $occupancy[$ls['day']][$pId]['classes'][$ls['class_section_id']] = true;
                $tId = $ls['teacher_id'] ?? $ls['subject_teacher']['teacher_id'] ?? null;
                if ($tId) $occupancy[$ls['day']][$pId]['teachers'][$tId] = true;
                if (!empty($ls['resource_id'])) $occupancy[$ls['day']][$pId]['resources'][$ls['resource_id']] = true;
            }
        }

        foreach ($externalOccupancies as $eo) {
            $pId = $eo['period_id'] ?? null;
            if ($pId && isset($occupancy[$eo['day']][$pId])) {
                $occupancy[$eo['day']][$pId]['classes'][$eo['class_section_id']] = true;
                if (!empty($eo['teacher_id'])) $occupancy[$eo['day']][$pId]['teachers'][$eo['teacher_id']] = true;
                if (!empty($eo['resource_id'])) $occupancy[$eo['day']][$pId]['resources'][$eo['resource_id']] = true;
            }
        }

        // Add Break/Lunch periods for all classes into currentSchedule
        foreach ($allPeriods as $p) {
            if (!$p->is_instructional) {
                foreach (array_unique(array_column($blocks, 'class_section_id')) as $cId) {
                    foreach ($workingDays as $d) {
                        $currentSchedule[] = [
                            'day'                => $d,
                            'period_id'          => $p->id,
                            'period_number'      => $p->period_number,
                            'start_time'         => $p->start_time,
                            'end_time'           => $p->end_time,
                            'type'               => $p->type,
                            'class_section_id'   => $cId,
                            'subject_id'         => null,
                            'subject_teacher_id' => null,
                            'teacher_id'         => null,
                            'resource_id'        => null,
                            'is_locked'          => false,
                        ];
                    }
                }
            }
        }

        // Ordered instructional periods array
        $instPeriodsArr = $instructionalPeriods->values()->all();

        $searchNodes = 0;
        $startTime = microtime(true);

        $placements = [];
        $solved = $this->searchBacktrack(
            0,
            $blocks,
            $workingDays,
            $instPeriodsArr,
            $allPeriods,
            $occupancy,
            $teacherUnavail,
            $classUnavail,
            $resourceUnavail,
            $placements,
            $searchNodes,
            $startTime
        );

        if (!$solved) {
            return [
                'success'    => false,
                'message'    => "Search limit or dead-end reached ({$searchNodes} nodes explored). No valid conflict-free timetable could be found.",
                'conflicts'  => [
                    [
                        'code'     => 'NO_VALID_SLOT',
                        'severity' => 'error',
                        'message'  => "Could not find valid conflict-free slot assignments for all required lesson blocks."
                    ]
                ],
                'statistics' => ['search_nodes' => $searchNodes]
            ];
        }

        // Merge placed blocks into final schedule
        foreach ($placements as $p) {
            $currentSchedule[] = $p;
        }

        return [
            'success'    => true,
            'schedule'   => $currentSchedule,
            'statistics' => [
                'search_nodes'      => $searchNodes,
                'execution_seconds' => round(microtime(true) - $startTime, 3),
            ]
        ];
    }

    /**
     * Recursive backtracking search over ordered blocks.
     */
    private function searchBacktrack(
        int $blockIndex,
        array &$blocks,
        array &$workingDays,
        array &$instPeriodsArr,
        $allPeriods,
        array &$occupancy,
        array &$teacherUnavail,
        array &$classUnavail,
        array &$resourceUnavail,
        array &$placements,
        int &$searchNodes,
        float $startTime
    ): bool {
        // Base case: all blocks successfully placed!
        if ($blockIndex >= count($blocks)) {
            return true;
        }

        $searchNodes++;
        if ($searchNodes > $this->maxSearchNodes || (microtime(true) - $startTime) > $this->maxExecutionSeconds) {
            return false;
        }

        $block = $blocks[$blockIndex];
        $blockSize = $block['block_size'];
        $classId = $block['class_section_id'];
        $teacherId = $block['teacher_id'];
        $resourceId = $block['resource_id'];

        // Find candidate valid slots
        $candidates = [];

        foreach ($workingDays as $dayIndex => $day) {
            $periodCount = count($instPeriodsArr);

            for ($pIdx = 0; $pIdx <= $periodCount - $blockSize; $pIdx++) {
                $canPlace = true;
                $slotPeriods = [];

                for ($step = 0; $step < $blockSize; $step++) {
                    $period = $instPeriodsArr[$pIdx + $step];
                    $pId = $period->id;

                    // Hard constraint 1: Period must be instructional
                    if (!$period->is_instructional) {
                        $canPlace = false;
                        break;
                    }

                    // Hard constraint 2: Class availability & occupancy
                    if (isset($occupancy[$day][$pId]['classes'][$classId]) || isset($classUnavail[$classId][$day][$pId])) {
                        $canPlace = false;
                        break;
                    }

                    // Hard constraint 3: Teacher availability & occupancy
                    if ($teacherId && (isset($occupancy[$day][$pId]['teachers'][$teacherId]) || isset($teacherUnavail[$teacherId][$day][$pId]))) {
                        $canPlace = false;
                        break;
                    }

                    // Hard constraint 4: Resource availability & occupancy
                    if ($resourceId && (isset($occupancy[$day][$pId]['resources'][$resourceId]) || isset($resourceUnavail[$resourceId][$day][$pId]))) {
                        $canPlace = false;
                        break;
                    }

                    $slotPeriods[] = $period;
                }

                if ($canPlace) {
                    // Soft score calculation
                    $softScore = 0;

                    // Count how many times this subject is already placed on this day for this class
                    $countOnDay = 0;
                    foreach ($placements as $placed) {
                        if ($placed['class_section_id'] === $classId && $placed['day'] === $day && $placed['subject_id'] === $block['subject_id']) {
                            $countOnDay++;
                        }
                    }

                    if ($countOnDay === 0) {
                        $softScore += 100; // spread reward
                    } else {
                        $softScore -= 80;  // penalty for multiple single periods on same day
                    }

                    if ($block['avoid_first_period'] && $instPeriodsArr[$pIdx]->period_number === 1) {
                        $softScore -= 30;
                    }
                    if ($block['avoid_last_period'] && $pIdx === $periodCount - 1) {
                        $softScore -= 30;
                    }
                    if (!empty($block['preferred_day']) && $block['preferred_day'] === $day) {
                        $softScore += 50;
                    }

                    $candidates[] = [
                        'day'          => $day,
                        'day_index'    => $dayIndex,
                        'period_index' => $pIdx,
                        'periods'      => $slotPeriods,
                        'score'        => $softScore,
                    ];
                }
            }
        }

        if (empty($candidates)) {
            return false;
        }

        // Deterministic candidate ordering:
        // 1. Highest soft score first
        // 2. Earliest day order
        // 3. Earliest period order
        usort($candidates, function ($a, $b) {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            if ($a['day_index'] !== $b['day_index']) {
                return $a['day_index'] <=> $b['day_index'];
            }
            return $a['period_index'] <=> $b['period_index'];
        });

        // Try candidates deterministically
        foreach ($candidates as $cand) {
            $placedSlots = [];

            // Apply placement to occupancy
            foreach ($cand['periods'] as $period) {
                $day = $cand['day'];
                $pId = $period->id;

                $occupancy[$day][$pId]['classes'][$classId] = true;
                if ($teacherId) $occupancy[$day][$pId]['teachers'][$teacherId] = true;
                if ($resourceId) $occupancy[$day][$pId]['resources'][$resourceId] = true;

                $slotData = [
                    'day'                => $day,
                    'period_id'          => $pId,
                    'period_number'      => $period->period_number,
                    'start_time'         => $period->start_time,
                    'end_time'           => $period->end_time,
                    'type'               => 'Lecture',
                    'class_section_id'   => $classId,
                    'subject_id'         => $block['subject_id'],
                    'subject_teacher_id' => $block['subject_teacher_id'],
                    'teacher_id'         => $teacherId,
                    'resource_id'        => $resourceId,
                    'is_locked'          => false,
                    'score'              => $cand['score'],
                    'explanation'        => [
                        'placed_at'   => "{$day} Period {$period->period_number}",
                        'block_size'  => $blockSize,
                        'score'       => $cand['score'],
                    ]
                ];
                $placements[] = $slotData;
                $placedSlots[] = $slotData;
            }

            // Recurse to next block
            if ($this->searchBacktrack(
                $blockIndex + 1,
                $blocks,
                $workingDays,
                $instPeriodsArr,
                $allPeriods,
                $occupancy,
                $teacherUnavail,
                $classUnavail,
                $resourceUnavail,
                $placements,
                $searchNodes,
                $startTime
            )) {
                return true;
            }

            // Backtrack: revert occupancy and placements
            foreach ($cand['periods'] as $period) {
                $day = $cand['day'];
                $pId = $period->id;

                unset($occupancy[$day][$pId]['classes'][$classId]);
                if ($teacherId) unset($occupancy[$day][$pId]['teachers'][$teacherId]);
                if ($resourceId) unset($occupancy[$day][$pId]['resources'][$resourceId]);

                array_pop($placements);
            }
        }

        return false;
    }

    private function createGenerationRunRecord(
        int $schoolId,
        ?int $sessionYearId,
        ?int $semesterId,
        int $templateId,
        ?int $startedBy,
        string $status,
        array $extra = []
    ): TimetableGenerationRun {
        return TimetableGenerationRun::create([
            'school_id'         => $schoolId,
            'session_year_id'   => $sessionYearId,
            'semester_id'       => $semesterId,
            'template_id'       => $templateId,
            'started_by'        => $startedBy,
            'status'            => $status,
            'algorithm_version' => 'v2-deterministic',
            'started_at'        => Carbon::now(),
            'completed_at'      => Carbon::now(),
            'error_message'     => $extra['error_message'] ?? null,
            'statistics'        => $extra,
        ]);
    }
}
