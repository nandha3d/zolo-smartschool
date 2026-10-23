<?php

namespace App\Services\Timetable;

use App\Models\ClassSection;
use App\Models\ClassSectionAvailability;
use App\Models\ClassSubject;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\SubjectScheduleRequirement;
use App\Models\SubjectTeacher;
use App\Models\SubjectTeacherAlternate;
use App\Models\TeacherAvailability;
use App\Models\Timetable;
use App\Models\TimetablePeriod;
use App\Models\TimetableResource;
use App\Models\TimetableResourceAvailability;
use App\Models\TimetableTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TimetablePlanningService
{
    // =========================================================================
    // 1. TEMPLATES & PERIODS
    // =========================================================================

    public function getTemplates(int $schoolId)
    {
        return TimetableTemplate::where('school_id', $schoolId)
            ->with(['periods' => function ($q) {
                $q->orderBy('period_number', 'ASC');
            }])
            ->orderBy('is_default', 'DESC')
            ->orderBy('name', 'ASC')
            ->get();
    }

    public function getDefaultTemplate(int $schoolId): TimetableTemplate
    {
        $template = TimetableTemplate::where('school_id', $schoolId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->with(['periods' => function ($q) {
                $q->orderBy('period_number', 'ASC');
            }])
            ->first();

        if ($template) {
            return $template;
        }

        // Check if any active template exists
        $template = TimetableTemplate::where('school_id', $schoolId)
            ->where('is_active', true)
            ->with(['periods' => function ($q) {
                $q->orderBy('period_number', 'ASC');
            }])
            ->first();

        if ($template) {
            $template->is_default = true;
            $template->save();
            return $template;
        }

        // Auto-bootstrap default template from school settings
        return $this->createDefaultTemplateFromSettings($schoolId);
    }

    public function createTemplate(int $schoolId, array $data, ?int $userId = null): TimetableTemplate
    {
        return DB::transaction(function () use ($schoolId, $data, $userId) {
            $isDefault = !empty($data['is_default']);

            if ($isDefault) {
                TimetableTemplate::where('school_id', $schoolId)->update(['is_default' => false]);
            }

            $template = TimetableTemplate::create([
                'school_id'    => $schoolId,
                'name'         => $data['name'],
                'description'  => $data['description'] ?? null,
                'working_days' => $data['working_days'] ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                'is_default'   => $isDefault,
                'is_active'    => $data['is_active'] ?? true,
                'created_by'   => $userId,
                'updated_by'   => $userId,
            ]);

            if (!empty($data['periods']) && is_array($data['periods'])) {
                $this->savePeriods($template->id, $data['periods'], $schoolId);
            }

            return $template->fresh(['periods']);
        });
    }

    public function updateTemplate(int $templateId, array $data, ?int $userId = null): TimetableTemplate
    {
        return DB::transaction(function () use ($templateId, $data, $userId) {
            $template = TimetableTemplate::findOrFail($templateId);

            if (!empty($data['is_default']) && !$template->is_default) {
                TimetableTemplate::where('school_id', $template->school_id)->update(['is_default' => false]);
            }

            $template->fill([
                'name'         => $data['name'] ?? $template->name,
                'description'  => array_key_exists('description', $data) ? $data['description'] : $template->description,
                'working_days' => $data['working_days'] ?? $template->working_days,
                'is_default'   => array_key_exists('is_default', $data) ? (bool)$data['is_default'] : $template->is_default,
                'is_active'    => array_key_exists('is_active', $data) ? (bool)$data['is_active'] : $template->is_active,
                'updated_by'   => $userId,
            ]);
            $template->save();

            if (isset($data['periods']) && is_array($data['periods'])) {
                $this->savePeriods($template->id, $data['periods'], $template->school_id);
            }

            return $template->fresh(['periods']);
        });
    }

    public function deleteTemplate(int $templateId): bool
    {
        $template = TimetableTemplate::findOrFail($templateId);
        return (bool)$template->delete();
    }

    public function setDefaultTemplate(int $templateId, int $schoolId): TimetableTemplate
    {
        return DB::transaction(function () use ($templateId, $schoolId) {
            TimetableTemplate::where('school_id', $schoolId)->update(['is_default' => false]);
            $template = TimetableTemplate::where('school_id', $schoolId)->findOrFail($templateId);
            $template->is_default = true;
            $template->is_active = true;
            $template->save();
            return $template;
        });
    }

    public function savePeriods(int $templateId, array $periodsData, int $schoolId): array
    {
        // 1. Sort and validate periods
        usort($periodsData, function ($a, $b) {
            return strcmp($a['start_time'], $b['start_time']);
        });

        // Check for time overlap
        for ($i = 0; $i < count($periodsData) - 1; $i++) {
            $currentEnd = Carbon::parse($periodsData[$i]['end_time']);
            $nextStart = Carbon::parse($periodsData[$i + 1]['start_time']);
            if ($currentEnd->gt($nextStart)) {
                throw new InvalidArgumentException("Periods cannot overlap: Period " . ($i + 1) . " ends at " . $periodsData[$i]['end_time'] . " but next period starts at " . $periodsData[$i + 1]['start_time']);
            }
        }

        return DB::transaction(function () use ($templateId, $periodsData, $schoolId) {
            // Keep track of existing period IDs to delete removed ones
            $existingPeriodIds = TimetablePeriod::where('timetable_template_id', $templateId)->pluck('id')->toArray();
            $touchedIds = [];
            $savedPeriods = [];

            $periodNumber = 1;
            foreach ($periodsData as $pData) {
                $periodId = $pData['id'] ?? null;
                $isInstructional = array_key_exists('is_instructional', $pData)
                    ? (bool)$pData['is_instructional']
                    : (($pData['type'] ?? 'Lecture') === 'Lecture');

                $payload = [
                    'school_id'             => $schoolId,
                    'timetable_template_id' => $templateId,
                    'period_number'         => $pData['period_number'] ?? $periodNumber,
                    'name'                  => $pData['name'] ?? ('Period ' . $periodNumber),
                    'start_time'            => Carbon::parse($pData['start_time'])->format('H:i:s'),
                    'end_time'              => Carbon::parse($pData['end_time'])->format('H:i:s'),
                    'type'                  => $pData['type'] ?? 'Lecture',
                    'is_instructional'      => $isInstructional,
                    'is_active'             => array_key_exists('is_active', $pData) ? (bool)$pData['is_active'] : true,
                ];

                if ($periodId && in_array($periodId, $existingPeriodIds)) {
                    $period = TimetablePeriod::where('id', $periodId)->update($payload);
                    $period = TimetablePeriod::find($periodId);
                } else {
                    $period = TimetablePeriod::create($payload);
                }

                $touchedIds[] = $period->id;
                $savedPeriods[] = $period;
                $periodNumber++;
            }

            // Clean up deleted periods
            $toDelete = array_diff($existingPeriodIds, $touchedIds);
            if (!empty($toDelete)) {
                TimetablePeriod::whereIn('id', $toDelete)->delete();
            }

            return $savedPeriods;
        });
    }

    private function createDefaultTemplateFromSettings(int $schoolId): TimetableTemplate
    {
        return DB::transaction(function () use ($schoolId) {
            $settings = SchoolSetting::where('school_id', $schoolId)
                ->whereIn('name', ['timetable_start_time', 'timetable_end_time', 'timetable_duration'])
                ->pluck('data', 'name');

            $startTimeStr = $settings['timetable_start_time'] ?? '08:30:00';
            $durationMinutes = 45;
            if (!empty($settings['timetable_duration'])) {
                $durationMinutes = abs((int)Carbon::parse($settings['timetable_duration'])->diffInMinutes(Carbon::parse('00:00:00')));
                if ($durationMinutes <= 0 || $durationMinutes > 180) {
                    $durationMinutes = 45;
                }
            }

            $template = TimetableTemplate::create([
                'school_id'    => $schoolId,
                'name'         => 'Default Weekly Schedule',
                'description'  => 'Standard school timetable template',
                'working_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                'is_default'   => true,
                'is_active'    => true,
            ]);

            // Generate 7 periods with a break after P3 and lunch after P5
            $currentTime = Carbon::parse($startTimeStr);
            $periodsData = [];
            for ($p = 1; $p <= 7; $p++) {
                $pStart = $currentTime->copy();
                $pEnd = $pStart->copy()->addMinutes($durationMinutes);

                $periodsData[] = [
                    'period_number'    => count($periodsData) + 1,
                    'name'             => "Period $p",
                    'start_time'       => $pStart->format('H:i:s'),
                    'end_time'         => $pEnd->format('H:i:s'),
                    'type'             => 'Lecture',
                    'is_instructional' => true,
                    'is_active'        => true,
                ];
                $currentTime = $pEnd->copy();

                if ($p === 3) {
                    // Morning break: 15 mins
                    $bStart = $currentTime->copy();
                    $bEnd = $bStart->copy()->addMinutes(15);
                    $periodsData[] = [
                        'period_number'    => count($periodsData) + 1,
                        'name'             => "Morning Break",
                        'start_time'       => $bStart->format('H:i:s'),
                        'end_time'         => $bEnd->format('H:i:s'),
                        'type'             => 'Break',
                        'is_instructional' => false,
                        'is_active'        => true,
                    ];
                    $currentTime = $bEnd->copy();
                } elseif ($p === 5) {
                    // Lunch break: 40 mins
                    $lStart = $currentTime->copy();
                    $lEnd = $lStart->copy()->addMinutes(40);
                    $periodsData[] = [
                        'period_number'    => count($periodsData) + 1,
                        'name'             => "Lunch Break",
                        'start_time'       => $lStart->format('H:i:s'),
                        'end_time'         => $lEnd->format('H:i:s'),
                        'type'             => 'Lunch',
                        'is_instructional' => false,
                        'is_active'        => true,
                    ];
                    $currentTime = $lEnd->copy();
                }
            }

            $this->savePeriods($template->id, $periodsData, $schoolId);

            return $template->fresh(['periods']);
        });
    }

    // =========================================================================
    // 2. RESOURCES
    // =========================================================================

    public function getResources(int $schoolId)
    {
        return TimetableResource::where('school_id', $schoolId)
            ->orderBy('name', 'ASC')
            ->get();
    }

    public function createResource(int $schoolId, array $data): TimetableResource
    {
        return TimetableResource::create([
            'school_id'   => $schoolId,
            'name'        => $data['name'],
            'type'        => $data['type'] ?? 'Lab',
            'block'       => $data['block'] ?? null,
            'capacity'    => !empty($data['capacity']) ? (int)$data['capacity'] : null,
            'description' => $data['description'] ?? null,
            'is_active'   => $data['is_active'] ?? true,
        ]);
    }

    public function updateResource(int $resourceId, array $data): TimetableResource
    {
        $resource = TimetableResource::findOrFail($resourceId);
        $resource->update([
            'name'        => $data['name'] ?? $resource->name,
            'type'        => $data['type'] ?? $resource->type,
            'block'       => array_key_exists('block', $data) ? $data['block'] : $resource->block,
            'capacity'    => array_key_exists('capacity', $data) ? ($data['capacity'] ? (int)$data['capacity'] : null) : $resource->capacity,
            'description' => array_key_exists('description', $data) ? $data['description'] : $resource->description,
            'is_active'   => array_key_exists('is_active', $data) ? (bool)$data['is_active'] : $resource->is_active,
        ]);
        return $resource;
    }

    public function deleteResource(int $resourceId): bool
    {
        $resource = TimetableResource::findOrFail($resourceId);
        return (bool)$resource->delete();
    }

    // =========================================================================
    // 3. AVAILABILITY (TEACHER, CLASS, RESOURCE)
    // =========================================================================

    public function getTeacherAvailability(int $teacherId, int $schoolId)
    {
        return TeacherAvailability::where('school_id', $schoolId)
            ->where('teacher_id', $teacherId)
            ->with('period')
            ->get();
    }

    public function saveTeacherAvailability(int $teacherId, array $grid, int $schoolId): void
    {
        DB::transaction(function () use ($teacherId, $grid, $schoolId) {
            foreach ($grid as $item) {
                $day = $item['day'];
                $periodId = $item['period_id'];
                $status = $item['status'] ?? 'available';
                $reason = $item['reason'] ?? null;

                if ($status === 'available' && empty($reason)) {
                    // Default status can be cleaned up to keep table lean
                    TeacherAvailability::where('school_id', $schoolId)
                        ->where('teacher_id', $teacherId)
                        ->where('day', $day)
                        ->where('period_id', $periodId)
                        ->delete();
                } else {
                    TeacherAvailability::updateOrCreate(
                        [
                            'school_id'  => $schoolId,
                            'teacher_id' => $teacherId,
                            'day'        => $day,
                            'period_id'  => $periodId,
                        ],
                        [
                            'status' => $status,
                            'reason' => $reason,
                        ]
                    );
                }
            }
        });
    }

    public function getClassAvailability(int $classSectionId, int $schoolId)
    {
        return ClassSectionAvailability::where('school_id', $schoolId)
            ->where('class_section_id', $classSectionId)
            ->with('period')
            ->get();
    }

    public function saveClassAvailability(int $classSectionId, array $grid, int $schoolId): void
    {
        DB::transaction(function () use ($classSectionId, $grid, $schoolId) {
            foreach ($grid as $item) {
                $day = $item['day'];
                $periodId = $item['period_id'];
                $status = $item['status'] ?? 'available';
                $reason = $item['reason'] ?? null;

                if ($status === 'available' && empty($reason)) {
                    ClassSectionAvailability::where('school_id', $schoolId)
                        ->where('class_section_id', $classSectionId)
                        ->where('day', $day)
                        ->where('period_id', $periodId)
                        ->delete();
                } else {
                    ClassSectionAvailability::updateOrCreate(
                        [
                            'school_id'        => $schoolId,
                            'class_section_id' => $classSectionId,
                            'day'              => $day,
                            'period_id'        => $periodId,
                        ],
                        [
                            'status' => $status,
                            'reason' => $reason,
                        ]
                    );
                }
            }
        });
    }

    public function getResourceAvailability(int $resourceId, int $schoolId)
    {
        return TimetableResourceAvailability::where('school_id', $schoolId)
            ->where('resource_id', $resourceId)
            ->with('period')
            ->get();
    }

    public function saveResourceAvailability(int $resourceId, array $grid, int $schoolId): void
    {
        DB::transaction(function () use ($resourceId, $grid, $schoolId) {
            foreach ($grid as $item) {
                $day = $item['day'];
                $periodId = $item['period_id'];
                $status = $item['status'] ?? 'available';
                $reason = $item['reason'] ?? null;

                if ($status === 'available' && empty($reason)) {
                    TimetableResourceAvailability::where('school_id', $schoolId)
                        ->where('resource_id', $resourceId)
                        ->where('day', $day)
                        ->where('period_id', $periodId)
                        ->delete();
                } else {
                    TimetableResourceAvailability::updateOrCreate(
                        [
                            'school_id'   => $schoolId,
                            'resource_id' => $resourceId,
                            'day'         => $day,
                            'period_id'   => $periodId,
                        ],
                        [
                            'status' => $status,
                            'reason' => $reason,
                        ]
                    );
                }
            }
        });
    }

    // =========================================================================
    // 4. SUBJECT SCHEDULE REQUIREMENTS
    // =========================================================================

    public function getRequirements(int $classSectionId, ?int $semesterId = null)
    {
        return SubjectScheduleRequirement::where('class_section_id', $classSectionId)
            ->when($semesterId, function ($q) use ($semesterId) {
                $q->where(function ($sq) use ($semesterId) {
                    $sq->where('semester_id', $semesterId)->orWhereNull('semester_id');
                });
            })
            ->with(['subject:id,name,type,bg_color', 'resource:id,name,type', 'class_subject'])
            ->orderBy('priority', 'DESC')
            ->orderBy('weekly_periods', 'DESC')
            ->get();
    }

    public function saveRequirement(int $schoolId, array $data): SubjectScheduleRequirement
    {
        $weekly = (int)($data['weekly_periods'] ?? 0);
        $minPerDay = (int)($data['min_periods_per_day'] ?? 0);
        $maxPerDay = (int)($data['max_periods_per_day'] ?? 1);

        if ($weekly < 0) {
            throw new InvalidArgumentException("Weekly periods cannot be negative");
        }
        if ($maxPerDay < $minPerDay) {
            throw new InvalidArgumentException("Max periods per day cannot be less than min periods per day");
        }

        $allowConsecutive = !empty($data['allow_consecutive']);
        $reqConsecutive = $allowConsecutive ? (!empty($data['required_consecutive_periods']) ? (int)$data['required_consecutive_periods'] : 2) : null;

        return SubjectScheduleRequirement::updateOrCreate(
            [
                'school_id'        => $schoolId,
                'class_section_id' => $data['class_section_id'],
                'subject_id'       => $data['subject_id'],
                'semester_id'      => $data['semester_id'] ?? null,
            ],
            [
                'class_subject_id'             => $data['class_subject_id'] ?? null,
                'weekly_periods'               => $weekly,
                'min_periods_per_day'          => $minPerDay,
                'max_periods_per_day'          => $maxPerDay,
                'allow_consecutive'            => $allowConsecutive,
                'required_consecutive_periods' => $reqConsecutive,
                'preferred_day'                => $data['preferred_day'] ?? null,
                'preferred_period_start'       => $data['preferred_period_start'] ?? null,
                'preferred_period_end'         => $data['preferred_period_end'] ?? null,
                'avoid_first_period'           => !empty($data['avoid_first_period']),
                'avoid_last_period'            => !empty($data['avoid_last_period']),
                'priority'                     => (int)($data['priority'] ?? 1),
                'resource_id'                  => $data['resource_id'] ?? null,
                'resource_required'            => !empty($data['resource_required']) && !empty($data['resource_id']),
                'is_active'                    => array_key_exists('is_active', $data) ? (bool)$data['is_active'] : true,
            ]
        );
    }

    public function saveBulkRequirements(int $classSectionId, array $requirementsData, int $schoolId, ?int $semesterId = null): array
    {
        return DB::transaction(function () use ($classSectionId, $requirementsData, $schoolId, $semesterId) {
            $saved = [];
            foreach ($requirementsData as $rData) {
                $rData['class_section_id'] = $classSectionId;
                $rData['semester_id'] = $semesterId;
                $saved[] = $this->saveRequirement($schoolId, $rData);
            }
            return $saved;
        });
    }

    // =========================================================================
    // 5. ALTERNATE TEACHERS
    // =========================================================================

    public function getAlternates(int $subjectTeacherId)
    {
        return SubjectTeacherAlternate::where('subject_teacher_id', $subjectTeacherId)
            ->with('alternate_teacher:id,first_name,last_name,email')
            ->orderBy('priority', 'ASC')
            ->get();
    }

    public function saveAlternates(int $subjectTeacherId, array $alternateIds, int $schoolId): void
    {
        DB::transaction(function () use ($subjectTeacherId, $alternateIds, $schoolId) {
            SubjectTeacherAlternate::where('subject_teacher_id', $subjectTeacherId)->delete();

            $priority = 1;
            foreach ($alternateIds as $altId) {
                if ($altId) {
                    SubjectTeacherAlternate::create([
                        'school_id'            => $schoolId,
                        'subject_teacher_id'   => $subjectTeacherId,
                        'alternate_teacher_id' => $altId,
                        'priority'             => $priority++,
                        'is_active'            => true,
                    ]);
                }
            }
        });
    }

    // =========================================================================
    // 6. SLOT LOCKING / UNLOCKING
    // =========================================================================

    public function lockSlot(int $timetableId, ?int $userId = null, ?string $reason = null): Timetable
    {
        $slot = Timetable::findOrFail($timetableId);
        $slot->is_locked = true;
        $slot->locked_by = $userId;
        $slot->locked_at = Carbon::now();
        $slot->lock_reason = $reason ?? 'Locked by administrator';
        $slot->save();
        return $slot;
    }

    public function unlockSlot(int $timetableId): Timetable
    {
        $slot = Timetable::findOrFail($timetableId);
        $slot->is_locked = false;
        $slot->locked_by = null;
        $slot->locked_at = null;
        $slot->lock_reason = null;
        $slot->save();
        return $slot;
    }
}
