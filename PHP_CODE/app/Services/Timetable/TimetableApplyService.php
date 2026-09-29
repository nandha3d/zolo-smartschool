<?php

namespace App\Services\Timetable;

use App\Models\SubjectTeacher;
use App\Models\Timetable;
use App\Models\TimetableGenerationEntry;
use App\Models\TimetableGenerationRun;
use App\Services\CachingService;
use App\Services\SessionYearsTrackingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class TimetableApplyService
{
    private TimetableValidationService $validationService;
    private SessionYearsTrackingsService $sessionYearsTrackingsService;
    private CachingService $cache;

    public function __construct(
        TimetableValidationService $validationService,
        SessionYearsTrackingsService $sessionYearsTrackingsService,
        CachingService $cache
    ) {
        $this->validationService = $validationService;
        $this->sessionYearsTrackingsService = $sessionYearsTrackingsService;
        $this->cache = $cache;
    }

    /**
     * Apply a completed generation run to the live timetables table in a safe database transaction.
     *
     * @param int $generationRunId
     * @param int $schoolId
     * @param int $userId
     * @return array
     */
    public function apply(int $generationRunId, int $schoolId, int $userId): array
    {
        $run = TimetableGenerationRun::where('school_id', $schoolId)
            ->where('id', $generationRunId)
            ->with('entries')
            ->firstOrFail();

        if ($run->status === 'applied') {
            throw new InvalidArgumentException("Generation run #{$generationRunId} has already been applied.");
        }

        if ($run->status !== 'completed') {
            throw new InvalidArgumentException("Only 'completed' generation runs can be applied. Current status is '{$run->status}'.");
        }

        $entries = $run->entries;
        if ($entries->isEmpty()) {
            throw new RuntimeException("No entries found in generation run #{$generationRunId}.");
        }

        $classSectionIds = $entries->pluck('class_section_id')->unique()->toArray();
        $semesterId = $run->semester_id;
        $sessionYearId = $run->session_year_id ?? ($this->cache->getDefaultSessionYear($schoolId)?->id ?? \App\Models\SessionYear::where('school_id', $schoolId)->where('default', 1)->value('id'));

        $validation = $this->validationService->validateConflictsAndOverlaps($entries->toArray());
        if (!empty($validation)) {
            $run->status = 'failed';
            $run->error_message = 'Failed pre-apply validation checks.';
            $run->save();

            return [
                'success'    => false,
                'message'    => 'Generation entries contain conflicts and cannot be applied.',
                'violations' => $validation,
            ];
        }

        return DB::transaction(function () use ($run, $entries, $schoolId, $userId, $classSectionIds, $semesterId, $sessionYearId) {
            // 1. Fetch existing locked timetable slots in affected classes to guarantee preservation
            $lockedSlots = Timetable::where('school_id', $schoolId)
                ->whereIn('class_section_id', $classSectionIds)
                ->where('is_locked', true)
                ->when($semesterId, function ($q) use ($semesterId) {
                    $q->where(function ($sq) use ($semesterId) {
                        $sq->where('semester_id', $semesterId)->orWhereNull('semester_id');
                    });
                })
                ->get()
                ->keyBy(function ($t) {
                    return "{$t->class_section_id}_{$t->day}_{$t->start_time}_{$t->end_time}";
                });

            // 2. Delete existing non-locked timetables for the affected class sections and semester
            Timetable::where('school_id', $schoolId)
                ->whereIn('class_section_id', $classSectionIds)
                ->where('is_locked', false)
                ->when($semesterId, function ($q) use ($semesterId) {
                    $q->where(function ($sq) use ($semesterId) {
                        $sq->where('semester_id', $semesterId)->orWhereNull('semester_id');
                    });
                })
                ->delete();

            // 3. Insert generated entries (skipping or updating existing locked slots)
            $appliedCount = 0;
            $preservedLockedCount = 0;

            foreach ($entries as $entry) {
                $slotKey = "{$entry->class_section_id}_{$entry->day}_{$entry->start_time}_{$entry->end_time}";

                // If this slot is already locked in DB, keep the locked live record untouched!
                if ($lockedSlots->has($slotKey)) {
                    $preservedLockedCount++;
                    continue;
                }

                // Ensure SubjectTeacher mapping exists if a teacher is assigned
                $subjectTeacherId = $entry->subject_teacher_id;
                if (!$subjectTeacherId && $entry->subject_id && $entry->teacher_id) {
                    $st = SubjectTeacher::firstOrCreate([
                        'class_section_id' => $entry->class_section_id,
                        'subject_id'       => $entry->subject_id,
                        'teacher_id'       => $entry->teacher_id,
                    ], [
                        'school_id'        => $schoolId,
                    ]);
                    $subjectTeacherId = $st->id;
                }

                $metaNote = [
                    'explanation' => $entry->explanation,
                ];

                $timetable = Timetable::create([
                    'school_id'          => $schoolId,
                    'class_section_id'   => $entry->class_section_id,
                    'subject_id'         => $entry->subject_id,
                    'subject_teacher_id' => $subjectTeacherId,
                    'period_id'          => $entry->period_id,
                    'resource_id'        => $entry->resource_id,
                    'day'                => $entry->day,
                    'start_time'         => $entry->start_time,
                    'end_time'           => $entry->end_time,
                    'type'               => $entry->type,
                    'is_locked'          => (bool)$entry->is_locked,
                    'note'               => json_encode($metaNote),
                    'semester_id'        => $semesterId,
                ]);

                if ($sessionYearId) {
                    $this->sessionYearsTrackingsService->storeSessionYearsTracking(
                        'App\Models\Timetable',
                        $timetable->id,
                        $userId,
                        $sessionYearId,
                        $schoolId,
                        $semesterId
                    );
                }

                $appliedCount++;
            }

            // 4. Update Generation Run Status to 'applied'
            $run->status = 'applied';
            $run->completed_at = Carbon::now();
            $run->save();

            return [
                'success'                => true,
                'message'                => "Timetable applied successfully! Applied {$appliedCount} slots (preserved {$preservedLockedCount} locked slots).",
                'applied_count'          => $appliedCount,
                'preserved_locked_count' => $preservedLockedCount,
                'generation_run_id'      => $run->id,
            ];
        });
    }

    /**
     * Cancel an unapplied generation run.
     */
    public function cancel(int $generationRunId, int $schoolId): array
    {
        $run = TimetableGenerationRun::where('school_id', $schoolId)
            ->where('id', $generationRunId)
            ->firstOrFail();

        if ($run->status === 'applied') {
            throw new InvalidArgumentException("Cannot cancel an already applied generation run.");
        }

        $run->status = 'cancelled';
        $run->save();

        return [
            'success' => true,
            'message' => "Generation run #{$generationRunId} has been cancelled.",
        ];
    }
}
