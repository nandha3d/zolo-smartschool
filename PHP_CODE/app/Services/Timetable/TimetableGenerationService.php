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

    // Soft limits on a teacher's day
    private const MAX_TEACHER_PERIODS_PER_DAY = 5;
    private const MAX_TEACHER_CONSECUTIVE = 3;

    // Safety limits
    private int $maxSearchNodes = 20000;
    private int $maxExecutionSeconds = 5;

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
        // Kept short so a web request finishes; large schools can raise these in .env.
        $this->maxSearchNodes = (int) config('timetable.max_search_nodes', $this->maxSearchNodes);
        $this->maxExecutionSeconds = (int) config('timetable.max_seconds', $this->maxExecutionSeconds);
    }

    /**
     * Case- and spacing-insensitive comparison for free-text fields like block and type.
     */
    private function sameText(?string $a, ?string $b): bool
    {
        $norm = static fn ($v) => preg_replace('/\s+/', ' ', mb_strtolower(trim((string) $v)));
        return $norm($a) === $norm($b);
    }

    /**
     * A resource's name with its block removed, so "Physics Lab - Block A" and
     * "Physics Lab (Block B)" both reduce to "physics lab".
     */
    private function resourceKey(?string $name, ?string $block): string
    {
        $words = static fn ($v) => preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string) $v), -1, PREG_SPLIT_NO_EMPTY);
        $drop = array_merge($words($block), ['block']);
        return implode(' ', array_diff($words($name), $drop));
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
        $rawLockedSlots = Timetable::where('school_id', $schoolId)
            ->where('is_locked', true)
            ->when($semesterId, function ($q) use ($semesterId) {
                $q->where(function ($sq) use ($semesterId) {
                    $sq->where('semester_id', $semesterId)->orWhereNull('semester_id');
                });
            })
            ->with('subject_teacher')
            ->get();

        $lockedSlots = [];
        $externalOccupancies = [];

        // Partition locked slots: target classes go into candidate schedule;
        // other classes become external teacher/room occupancies.
        foreach ($rawLockedSlots as $lsRecord) {
            $tId = $lsRecord->teacher_id ?? $lsRecord->subject_teacher?->teacher_id;
            if (in_array($lsRecord->class_section_id, $classSectionIds)) {
                $arr = $lsRecord->toArray();
                $arr['teacher_id'] = $tId;
                $lockedSlots[] = $arr;
            } else {
                $externalOccupancies[] = [
                    'day'              => $lsRecord->day,
                    'start_time'       => $lsRecord->start_time,
                    'end_time'         => $lsRecord->end_time,
                    'class_section_id' => $lsRecord->class_section_id,
                    'teacher_id'       => $tId,
                    'resource_id'      => $lsRecord->resource_id,
                    'period_id'        => $lsRecord->period_id,
                    'is_locked'        => true,
                ];
            }
        }

        // Also fetch any other classes' unlocked timetables if running in partial mode
        if ($preserveExisting) {
            $existingOthers = Timetable::where('school_id', $schoolId)
                ->whereNotIn('class_section_id', $classSectionIds)
                ->where('type', 'Lecture')
                ->where('is_locked', false)
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
                    'is_locked'        => false,
                ];
            }
        }

        // 5. Build Lesson Blocks to Schedule
        $blocks = $this->buildLessonBlocks($classSectionIds, $requirements, $lockedSlots, $externalOccupancies, $schoolId, $params);

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

        // 10. Return Preview Response with Enriched Entries and Classes List
        $enrichedEntries = TimetableGenerationEntry::where('generation_run_id', $generationRun->id)
            ->with([
                'class_section.class',
                'class_section.section',
                'class_section.medium',
                'subject:id,name,type,bg_color',
                'teacher:id,first_name,last_name',
                'resource:id,name',
                'period:id,period_number,name'
            ])
            ->orderBy('day')
            ->orderBy('start_time')
            ->get()
            ->map(function ($entry) {
                $cs = $entry->class_section;
                $className = $cs?->full_name;
                if (!$className && $cs) {
                    $cName = $cs->class?->name ?? '';
                    $sName = $cs->section?->name ?? '';
                    $mName = $cs->medium?->name ?? '';
                    $className = trim("{$cName} {$sName} " . ($mName ? "({$mName})" : ""));
                }
                $tName = $entry->teacher ? trim($entry->teacher->first_name . ' ' . ($entry->teacher->last_name ?? '')) : '';

                return [
                    'id'                 => $entry->id,
                    'class_section_id'   => $entry->class_section_id,
                    'class_name'         => $className ?: ('Class #' . $entry->class_section_id),
                    'subject_id'         => $entry->subject_id,
                    'subject_name'       => $entry->subject?->name ?? ($entry->type === 'Break' ? 'Break' : 'Lecture'),
                    'subject_type'       => $entry->subject?->type,
                    'subject_bg_color'   => $entry->subject?->bg_color,
                    'teacher_id'         => $entry->teacher_id,
                    'teacher_name'       => $tName,
                    'resource_id'        => $entry->resource_id,
                    'resource_name'      => $entry->resource?->name ?? '',
                    'day'                => $entry->day,
                    'period_id'          => $entry->period_id,
                    'period_number'      => $entry->period?->period_number,
                    'start_time'         => $entry->start_time,
                    'end_time'           => $entry->end_time,
                    'type'               => $entry->type,
                    'is_locked'          => (bool)$entry->is_locked,
                ];
            });

        $generatedClasses = [];
        foreach ($enrichedEntries->groupBy('class_section_id') as $cId => $cEntries) {
            $first = $cEntries->first();
            $generatedClasses[] = [
                'id'          => (int)$cId,
                'name'        => $first['class_name'] ?? ('Class #' . $cId),
                'slots_count' => $cEntries->count(),
            ];
        }

        return [
            'error'             => false,
            'generation_run_id' => $generationRun->id,
            'run_id'            => $generationRun->id,
            'status'            => 'completed',
            'message'           => 'Timetable generated successfully with ' . count($finalSchedule) . ' slots across ' . count($classSectionIds) . ' classes.',
            'statistics'        => $generationRun->statistics,
            'conflicts'         => [],
            'entries'           => $enrichedEntries->values()->all(),
            'entries_count'     => count($finalSchedule),
            'entries_preview'   => $enrichedEntries->take(50)->values()->all(),
            'classes'           => $generatedClasses,
            'quality'           => $quality,
            'metrics'           => $quality,
        ];
    }

    /**
     * Build lesson blocks from requirements, deducting already-scheduled locked slots.
     */
    private function buildLessonBlocks(array $classSectionIds, $requirements, array $lockedSlots, array $externalOccupancies = [], int $schoolId = 0, array $params = []): array
    {
        if ($schoolId === 0 && count($requirements)) {
            $schoolId = $requirements->first()?->school_id ?? 0;
        }

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

        $prioritizeSameBlock = array_key_exists('prioritize_same_block', $params)
            ? (bool)$params['prioritize_same_block']
            : (bool)(\App\Models\SchoolSetting::where('school_id', $schoolId)->where('name', 'timetable_prioritize_same_block')->value('data') ?? true);

        $schoolResources = TimetableResource::where('school_id', $schoolId)->get()->keyBy('id');
        $classSectionBlocks = ClassSection::whereIn('id', $classSectionIds)->pluck('block', 'id');

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

            $classBlock = $classSectionBlocks[$classId] ?? null;
            $assignedResId = $req->resource_id;
            if ($prioritizeSameBlock && $assignedResId && !empty($classBlock)) {
                $res = $schoolResources->get($assignedResId);
                if ($res && !$this->sameText($res->block, $classBlock)) {
                    // Swap only to the same kind of room in the class's block: type alone is free
                    // text ("Lab"), so the names must also match once the block is taken out,
                    // e.g. "Physics Lab - Block A" <-> "Physics Lab - Block B".
                    $resKey = $this->resourceKey($res->name, $res->block);
                    $sameBlockRes = $schoolResources->first(fn($r) => $r->is_active
                        && $this->sameText($r->type, $res->type)
                        && $this->sameText($r->block, $classBlock)
                        && $this->resourceKey($r->name, $r->block) === $resKey);
                    if ($sameBlockRes) {
                        $assignedResId = $sameBlockRes->id;
                    }
                }
            }
            $targetRes = $assignedResId ? $schoolResources->get($assignedResId) : null;
            $resourceMatchesBlock = ($targetRes && !empty($classBlock) && $this->sameText($targetRes->block, $classBlock));

            $allowConsecutive = (bool)$req->allow_consecutive;
            $reqConsecutive = $allowConsecutive ? ((int)($req->required_consecutive_periods ?? 2)) : 1;
            if ($reqConsecutive <= 1) $reqConsecutive = 1;

            $blockIndex = 1;
            while ($remaining > 0) {
                $blockSize = ($allowConsecutive && $remaining >= $reqConsecutive && $reqConsecutive > 1)
                    ? $reqConsecutive
                    : 1;

                $blocks[] = [
                    'block_id'               => "{$classId}_{$subjectId}_{$blockIndex}",
                    'class_section_id'       => $classId,
                    'subject_id'             => $subjectId,
                    'subject_teacher_id'     => $subjectTeacherId,
                    'teacher_id'             => $teacherId,
                    'resource_id'            => $assignedResId,
                    'resource_matches_block' => $resourceMatchesBlock,
                    'block_size'             => $blockSize,
                    'priority'               => (int)$req->priority,
                    'weekly_periods'         => $weeklyTotal,
                    'avoid_first_period'     => (bool)$req->avoid_first_period,
                    'avoid_last_period'      => (bool)$req->avoid_last_period,
                    'preferred_day'          => $req->preferred_day,
                    'max_periods_per_day'    => max(1, (int)($req->max_periods_per_day ?? 2)),
                ];

                $remaining -= $blockSize;
                $blockIndex++;
            }
        }

        // External busy slots already assigned to teachers in previous classes
        $teacherExternalOccupancy = [];
        foreach ($externalOccupancies as $eo) {
            if (!empty($eo['teacher_id'])) {
                $teacherExternalOccupancy[$eo['teacher_id']] = ($teacherExternalOccupancy[$eo['teacher_id']] ?? 0) + 1;
            }
        }

        // Weekly periods each teacher must cover across every selected class.
        $teacherLoadInBlocks = [];
        foreach ($blocks as $block) {
            if ($block['teacher_id']) {
                $teacherLoadInBlocks[$block['teacher_id']] = ($teacherLoadInBlocks[$block['teacher_id']] ?? 0) + $block['block_size'];
            }
        }
        foreach ($blocks as &$block) {
            $tId = $block['teacher_id'];
            // True MRV: External busy slots + new slots. The teacher with the highest total occupancy
            // (fewest free remaining slots) is the most constrained resource and must be placed first!
            $block['teacher_load'] = $tId ? (($teacherExternalOccupancy[$tId] ?? 0) + ($teacherLoadInBlocks[$tId] ?? 0)) : 0;
        }
        unset($block);

        // Deterministic sorting (Section 21):
        // 1. Most constrained first (higher block_size double periods)
        // 2. Busiest teacher first (minimum remaining values)
        // 3. Highest priority first
        // 4. Highest weekly periods first
        // 5. Stable class_section_id, subject_id tie-breaker
        usort($blocks, function ($a, $b) {
            if ($a['block_size'] !== $b['block_size']) {
                return $b['block_size'] <=> $a['block_size'];
            }
            if ($a['teacher_load'] !== $b['teacher_load']) {
                return $b['teacher_load'] <=> $a['teacher_load'];
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
            $matchedPeriod = $pId
                ? $allPeriods->firstWhere('id', $pId)
                // Find matching period from template
                : $allPeriods->first(function ($p) use ($ls) {
                    return $p->start_time === $ls['start_time'] && $p->end_time === $ls['end_time'];
                });
            $pId = $pId ?: $matchedPeriod?->id;

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

                    // Count how many times this subject is already placed on this day for this class,
                    // and track which periods it already occupies across other days
                    $countOnDay = 0;
                    $placedPeriodIndices = [];
                    foreach ($placements as $placed) {
                        if ($placed['class_section_id'] === $classId && $placed['subject_id'] === $block['subject_id']) {
                            $placedPeriodIndices[] = $placed['period_id'] ?? $placed['period_number'];
                            if ($placed['day'] === $day) {
                                $countOnDay++;
                            }
                        }
                    }

                    $maxPerDay = $block['max_periods_per_day'] ?? 2;
                    if ($countOnDay >= $maxPerDay) {
                        continue; // Class already has max periods for this subject today
                    }

                    if ($countOnDay === 0) {
                        $softScore += 80; // primary spread reward
                    } else {
                        // Second period for this subject/teacher on this day (e.g. afternoon lab or double block)
                        $softScore += 20;
                    }

                    // Never-Repeat / Anti-Monotony rotation:
                    // Avoid placing the same subject at the exact same period across multiple days!
                    $curPeriodIdentifier = $instPeriodsArr[$pIdx]->id ?? ($pIdx + 1);
                    if (in_array($curPeriodIdentifier, $placedPeriodIndices)) {
                        $softScore -= 50; // Discourage same period repetition every day
                    } else {
                        $softScore += 35; // Reward rotating periods throughout the week
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

                    if ($teacherId) {
                        $softScore += $this->teacherLoadScore($occupancy[$day], $instPeriodsArr, $teacherId, $pIdx, $blockSize);
                    }

                    if (!empty($block['resource_matches_block'])) {
                        $softScore += 40;
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

        // Candidate ordering:
        // 1. Highest soft score first
        // 2. Diversity tie-break (shuffling across periods) so subjects don't all cluster at period 0
        usort($candidates, function ($a, $b) use ($classId, $block) {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            $hashA = ($classId * 37 + $block['subject_id'] * 19 + $a['day_index'] * 13 + $a['period_index'] * 7) % 29;
            $hashB = ($classId * 37 + $block['subject_id'] * 19 + $b['day_index'] * 13 + $b['period_index'] * 7) % 29;
            if ($hashA !== $hashB) {
                return $hashB <=> $hashA;
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

    /**
     * Soft constraints on a teacher's day: no more than MAX_TEACHER_PERIODS_PER_DAY
     * lessons and no run longer than MAX_TEACHER_CONSECUTIVE periods without a gap.
     * Returns a penalty (<= 0) for placing $blockSize periods at $pIdx.
     */
    private function teacherLoadScore(array $dayOccupancy, array $instPeriodsArr, int $teacherId, int $pIdx, int $blockSize): int
    {
        $busy = [];
        foreach ($instPeriodsArr as $index => $period) {
            $busy[$index] = isset($dayOccupancy[$period->id]['teachers'][$teacherId])
                || ($index >= $pIdx && $index < $pIdx + $blockSize);
        }

        $score = 0;
        $dailyTotal = count(array_filter($busy));
        if ($dailyTotal > self::MAX_TEACHER_PERIODS_PER_DAY) {
            $score -= 60 * ($dailyTotal - self::MAX_TEACHER_PERIODS_PER_DAY);
        }

        // Length of the run of busy periods that contains the new placement.
        $start = $pIdx;
        while ($start > 0 && $busy[$start - 1]) {
            $start--;
        }
        $end = $pIdx + $blockSize - 1;
        $last = count($instPeriodsArr) - 1;
        while ($end < $last && $busy[$end + 1]) {
            $end++;
        }
        $run = $end - $start + 1;
        if ($run > self::MAX_TEACHER_CONSECUTIVE) {
            $score -= 40 * ($run - self::MAX_TEACHER_CONSECUTIVE);
        }

        return $score;
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
