<?php

namespace App\Services;

use App\Models\ClassSection;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\Timetable;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class TimetableGeneratorService
{
    private CachingService $cache;
    private SessionYearsTrackingsService $sessionYearsTrackingsService;

    public function __construct(CachingService $cache, SessionYearsTrackingsService $sessionYearsTrackingsService)
    {
        $this->cache = $cache;
        $this->sessionYearsTrackingsService = $sessionYearsTrackingsService;
    }

    /**
     * Intelligently auto-generate a conflict-free timetable for a class section.
     *
     * @param array $params
     * @return array
     */
    public function generate(array $params): array
    {
        $classSectionId = $params['class_section_id'];
        $days = $params['days'] ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $startTimeStr = $params['start_time'] ?? '09:00:00';
        $periodDuration = (int) ($params['period_duration'] ?? 45); // in minutes
        $totalPeriodsPerDay = (int) ($params['total_periods'] ?? 6);
        $breaks = $params['breaks'] ?? [];
        $subjectsInput = $params['subjects'] ?? [];
        $clearExisting = !empty($params['clear_existing']);

        $classSection = ClassSection::with('class', 'section')->findOrFail($classSectionId);
        $schoolId = Auth::user()->school_id ?? $classSection->school_id;
        $sessionYear = $this->cache->getDefaultSessionYear();
        $semester = $this->cache->getDefaultSemesterData();

        // 1. Pre-fetch all other timetables in the school to check teacher conflicts
        $otherTimetables = Timetable::with('subject_teacher')
            ->where('school_id', $schoolId)
            ->when($clearExisting, function ($q) use ($classSectionId) {
                $q->where('class_section_id', '!=', $classSectionId);
            })
            ->whereIn('day', $days)
            ->get();

        // 2. Build daily schedule slots (periods + breaks)
        $dailySchedule = $this->buildDailySchedule($startTimeStr, $periodDuration, $totalPeriodsPerDay, $breaks);

        // 3. Build lecture distribution queue
        $lectureQueue = $this->buildLectureQueue($subjectsInput, count($days) * $totalPeriodsPerDay);

        if (empty($lectureQueue)) {
            return [
                'status'  => false,
                'message' => 'No subjects or periods configured for auto-generation.'
            ];
        }

        // 4. Run Intelligent Allocation & Conflict Detection
        $allocationResult = $this->allocateSlots(
            $classSectionId,
            $days,
            $dailySchedule,
            $lectureQueue,
            $otherTimetables,
            $subjectsInput
        );

        $schedule = $allocationResult['schedule'];
        $conflicts = $allocationResult['conflicts'];

        // 5. Persist schedule inside database transaction
        DB::beginTransaction();
        try {
            if ($clearExisting) {
                Timetable::where('class_section_id', $classSectionId)
                    ->whereIn('day', $days)
                    ->delete();
            }

            $createdEntries = [];
            foreach ($schedule as $slot) {
                // Ensure SubjectTeacher record exists if teacher is assigned
                $subjectTeacherId = null;
                if (!empty($slot['subject_id']) && !empty($slot['teacher_id'])) {
                    $classSubject = \App\Models\ClassSubject::where('class_id', $classSection->class_id)
                        ->where('subject_id', $slot['subject_id'])
                        ->first();

                    if (!$classSubject) {
                        $classSubject = \App\Models\ClassSubject::create([
                            'class_id'   => $classSection->class_id,
                            'subject_id' => $slot['subject_id'],
                            'school_id'  => $schoolId,
                            'type'       => 'Compulsory'
                        ]);
                    }

                    $st = SubjectTeacher::firstOrCreate([
                        'class_section_id' => $classSectionId,
                        'subject_id'       => $slot['subject_id'],
                        'teacher_id'       => $slot['teacher_id'],
                        'class_subject_id' => $classSubject->id,
                    ], [
                        'school_id'        => $schoolId,
                    ]);
                    $subjectTeacherId = $st->id;
                }

                $metaNote = [
                    'note'                 => $slot['note'] ?? '',
                    'alt_teacher_id'       => $slot['alt_teacher_id'] ?? null,
                    'alt_teacher_name'     => $slot['alt_teacher_name'] ?? null,
                    'primary_teacher_name' => $slot['primary_teacher_name'] ?? null,
                    'period_no'            => $slot['period_no'] ?? null,
                ];

                $timetable = Timetable::create([
                    'class_section_id'   => $classSectionId,
                    'subject_id'         => $slot['subject_id'] ?? null,
                    'subject_teacher_id' => $subjectTeacherId,
                    'start_time'         => $slot['start_time'],
                    'end_time'           => $slot['end_time'],
                    'day'                => $slot['day'],
                    'type'               => $slot['type'],
                    'note'               => json_encode($metaNote),
                    'semester_id'        => $semester?->id,
                    'school_id'          => $schoolId,
                ]);

                if ($sessionYear) {
                    $this->sessionYearsTrackingsService->storeSessionYearsTracking(
                        'App\Models\Timetable',
                        $timetable->id,
                        Auth::user()->id,
                        $sessionYear->id,
                        $schoolId,
                        $semester?->id
                    );
                }

                $createdEntries[] = $timetable;
            }

            DB::commit();

            return [
                'status'          => true,
                'message'         => 'Timetable generated successfully with ' . count($createdEntries) . ' slots!',
                'created_count'   => count($createdEntries),
                'conflicts'       => $conflicts,
                'has_conflicts'   => count($conflicts) > 0,
            ];
        } catch (Throwable $e) {
            DB::rollBack();
            return [
                'status'  => false,
                'message' => 'Failed to save generated timetable: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Build daily time periods and breaks.
     */
    private function buildDailySchedule(string $startTimeStr, int $periodDuration, int $totalPeriods, array $breaks): array
    {
        $slots = [];
        $currentTime = Carbon::parse($startTimeStr);
        $periodIndex = 1;

        // Index breaks by 'after_period'
        $breaksByPeriod = [];
        foreach ($breaks as $b) {
            $after = (int) ($b['after_period'] ?? 0);
            if ($after > 0) {
                $breaksByPeriod[$after] = $b;
            }
        }

        while ($periodIndex <= $totalPeriods) {
            $periodStart = $currentTime->copy();
            $periodEnd = $periodStart->copy()->addMinutes($periodDuration);

            $slots[] = [
                'type'       => 'Lecture',
                'period_no'  => $periodIndex,
                'start_time' => $periodStart->format('H:i:s'),
                'end_time'   => $periodEnd->format('H:i:s'),
            ];

            $currentTime = $periodEnd->copy();

            // Check if break scheduled after this period
            if (isset($breaksByPeriod[$periodIndex])) {
                $b = $breaksByPeriod[$periodIndex];
                $breakDuration = (int) ($b['duration'] ?? 15);
                $breakTitle = $b['title'] ?? 'Break';

                $breakStart = $currentTime->copy();
                $breakEnd = $breakStart->copy()->addMinutes($breakDuration);

                $slots[] = [
                    'type'       => 'Break',
                    'title'      => $breakTitle,
                    'start_time' => $breakStart->format('H:i:s'),
                    'end_time'   => $breakEnd->format('H:i:s'),
                ];

                $currentTime = $breakEnd->copy();
            }

            $periodIndex++;
        }

        return $slots;
    }

    /**
     * Expand subjects into a queue based on weekly target periods.
     */
    private function buildLectureQueue(array $subjectsInput, int $maxSlots): array
    {
        $queue = [];
        foreach ($subjectsInput as $sub) {
            $subjectId = (int) ($sub['subject_id'] ?? 0);
            $weeklyPeriods = (int) ($sub['weekly_periods'] ?? 0);
            $teacherId = !empty($sub['teacher_id']) ? (int) $sub['teacher_id'] : null;
            $altTeacherId = !empty($sub['alternate_teacher_id']) ? (int) $sub['alternate_teacher_id'] : null;

            if ($subjectId <= 0 || $weeklyPeriods <= 0) {
                continue;
            }

            for ($i = 0; $i < $weeklyPeriods; $i++) {
                $queue[] = [
                    'subject_id'     => $subjectId,
                    'teacher_id'     => $teacherId,
                    'alt_teacher_id' => $altTeacherId,
                ];
            }
        }

        // Shuffle strategically to interleave different subjects
        shuffle($queue);
        return array_slice($queue, 0, $maxSlots);
    }

    /**
     * Smart constraint allocation algorithm with conflict resolution.
     */
    private function allocateSlots(
        int $classSectionId,
        array $days,
        array $dailySchedule,
        array $lectureQueue,
        $otherTimetables,
        array $subjectsInput
    ): array {
        $schedule = [];
        $conflicts = [];

        // Preload teacher names
        $teacherIds = [];
        foreach ($subjectsInput as $s) {
            if (!empty($s['teacher_id'])) $teacherIds[] = $s['teacher_id'];
            if (!empty($s['alternate_teacher_id'])) $teacherIds[] = $s['alternate_teacher_id'];
        }
        $teachersMap = User::whereIn('id', array_unique($teacherIds))
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');

        $subjectsMap = Subject::whereIn('id', array_column($subjectsInput, 'subject_id'))
            ->get(['id', 'name', 'bg_color', 'type'])
            ->keyBy('id');

        // Track subjects scheduled per day to balance them
        $dailySubjectCounts = [];
        foreach ($days as $d) {
            $dailySubjectCounts[$d] = [];
        }

        $remainingQueue = $lectureQueue;

        foreach ($days as $day) {
            foreach ($dailySchedule as $slotDef) {
                if ($slotDef['type'] === 'Break') {
                    $schedule[] = [
                        'day'        => $day,
                        'type'       => 'Break',
                        'note'       => $slotDef['title'] ?? 'Break',
                        'start_time' => $slotDef['start_time'],
                        'end_time'   => $slotDef['end_time'],
                    ];
                    continue;
                }

                $startTime = $slotDef['start_time'];
                $endTime = $slotDef['end_time'];
                $periodNo = $slotDef['period_no'];

                // Find candidate subject in remaining queue
                $allocated = false;
                $candidateKey = null;

                // Priority 1: Find a subject not yet taught on this day, with no teacher conflict
                foreach ($remainingQueue as $key => $candidate) {
                    $subId = $candidate['subject_id'];
                    $teacherId = $candidate['teacher_id'];
                    $altId = $candidate['alt_teacher_id'];

                    // Don't repeat subject on the same day if possible
                    $alreadyTaughtToday = ($dailySubjectCounts[$day][$subId] ?? 0) > 0;
                    if ($alreadyTaughtToday && count($remainingQueue) > 3) {
                        continue;
                    }

                    // Check teacher conflict in other classes
                    $hasTeacherConflict = $teacherId && $this->hasConflict($otherTimetables, $schedule, $teacherId, $day, $startTime, $endTime);

                    if (!$hasTeacherConflict) {
                        $candidateKey = $key;
                        $allocated = true;
                        $finalTeacherId = $teacherId;
                        $finalAltId = $altId;
                        break;
                    } elseif ($altId && !$this->hasConflict($otherTimetables, $schedule, $altId, $day, $startTime, $endTime)) {
                        // Alternate teacher is free!
                        $candidateKey = $key;
                        $allocated = true;
                        $finalTeacherId = $altId;
                        $finalAltId = $teacherId; // Swap primary and alternate
                        $conflicts[] = [
                            'type'        => 'resolved_with_alternate',
                            'day'         => $day,
                            'period'      => $periodNo,
                            'time'        => "$startTime - $endTime",
                            'subject'     => $subjectsMap[$subId]->name ?? 'Subject',
                            'message'     => "Primary teacher busy. Assigned alternate teacher (" . ($teachersMap[$altId]->full_name ?? '') . ") on $day Period $periodNo."
                        ];
                        break;
                    }
                }

                // Priority 2: If strict priority couldn't find a slot, take next available and report conflict
                if (!$allocated && !empty($remainingQueue)) {
                    $candidateKey = array_key_first($remainingQueue);
                    $candidate = $remainingQueue[$candidateKey];
                    $subId = $candidate['subject_id'];
                    $finalTeacherId = $candidate['teacher_id'];
                    $finalAltId = $candidate['alt_teacher_id'];

                    if ($finalTeacherId && $this->hasConflict($otherTimetables, $schedule, $finalTeacherId, $day, $startTime, $endTime)) {
                        $conflicts[] = [
                            'type'        => 'unresolved_teacher_conflict',
                            'day'         => $day,
                            'period'      => $periodNo,
                            'time'        => "$startTime - $endTime",
                            'subject'     => $subjectsMap[$subId]->name ?? 'Subject',
                            'teacher'     => $teachersMap[$finalTeacherId]->full_name ?? 'Teacher',
                            'message'     => "Conflict: Teacher " . ($teachersMap[$finalTeacherId]->full_name ?? '') . " has an existing class on $day from $startTime to $endTime."
                        ];
                    }
                }

                if ($candidateKey !== null) {
                    $selected = $remainingQueue[$candidateKey];
                    unset($remainingQueue[$candidateKey]);

                    $subId = $selected['subject_id'];
                    $dailySubjectCounts[$day][$subId] = ($dailySubjectCounts[$day][$subId] ?? 0) + 1;

                    $primaryTeacherName = $finalTeacherId ? ($teachersMap[$finalTeacherId]->full_name ?? '') : 'Unassigned';
                    $altTeacherName = $finalAltId ? ($teachersMap[$finalAltId]->full_name ?? '') : null;

                    $schedule[] = [
                        'day'                  => $day,
                        'period_no'            => $periodNo,
                        'type'                 => 'Lecture',
                        'subject_id'           => $subId,
                        'teacher_id'           => $finalTeacherId,
                        'alt_teacher_id'       => $finalAltId,
                        'primary_teacher_name' => $primaryTeacherName,
                        'alt_teacher_name'     => $altTeacherName,
                        'start_time'           => $startTime,
                        'end_time'             => $endTime,
                        'note'                 => '',
                    ];
                }
            }
        }

        return [
            'schedule'  => $schedule,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Check whether a teacher is scheduled in any class during [startTime, endTime] on a given day.
     */
    private function hasConflict($otherTimetables, array $currentSchedule, int $teacherId, string $day, string $startTime, string $endTime): bool
    {
        // 1. Check existing database timetables
        foreach ($otherTimetables as $tt) {
            if ($tt->day !== $day || $tt->type !== 'Lecture') continue;

            $assignedTeacherId = $tt->subject_teacher?->teacher_id;
            if ($assignedTeacherId == $teacherId) {
                // Time overlap check
                if ($this->timesOverlap($startTime, $endTime, $tt->start_time, $tt->end_time)) {
                    return true;
                }
            }
        }

        // 2. Check slots already allocated in current generation run
        foreach ($currentSchedule as $slot) {
            if ($slot['day'] !== $day || $slot['type'] !== 'Lecture') continue;
            if (($slot['teacher_id'] ?? null) == $teacherId) {
                if ($this->timesOverlap($startTime, $endTime, $slot['start_time'], $slot['end_time'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if two time ranges overlap.
     */
    private function timesOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        $sA = Carbon::parse($startA)->timestamp;
        $eA = Carbon::parse($endA)->timestamp;
        $sB = Carbon::parse($startB)->timestamp;
        $eB = Carbon::parse($endB)->timestamp;

        return ($sA < $eB) && ($eA > $sB);
    }
}
