<?php

namespace App\Services\Timetable;

use App\Models\ClassSection;
use App\Models\Subject;
use App\Models\SubjectScheduleRequirement;
use App\Models\SubjectTeacher;
use App\Models\TeacherAvailability;
use App\Models\ClassSectionAvailability;
use App\Models\TimetablePeriod;
use App\Models\TimetableResource;
use App\Models\TimetableResourceAvailability;
use App\Models\TimetableTemplate;
use App\Models\User;

class TimetableFeasibilityService
{
    /**
     * Run pre-solver feasibility checks before attempting generation.
     *
     * @param int $schoolId
     * @param TimetableTemplate $template
     * @param array $classSectionIds
     * @param array $requirements Collection or array of SubjectScheduleRequirement
     * @param array $options ['semester_id' => ?int, 'preserve_existing' => bool]
     * @return array ['feasible' => bool, 'conflicts' => array]
     */
    public function checkFeasibility(
        int $schoolId,
        TimetableTemplate $template,
        array $classSectionIds,
        $requirements,
        array $options = []
    ): array {
        $conflicts = [];

        // 1. Template instructional capacity
        $instructionalPeriods = $template->instructional_periods;
        $instructionalCount = $instructionalPeriods->count();
        $workingDays = $template->working_days ?? [];
        $workingDaysCount = count($workingDays);

        if ($instructionalCount === 0 || $workingDaysCount === 0) {
            $conflicts[] = [
                'code'     => 'INVALID_PERIOD_TEMPLATE',
                'severity' => 'error',
                'message'  => "Template '{$template->name}' has no active instructional periods or working days configured."
            ];
            return ['feasible' => false, 'conflicts' => $conflicts];
        }

        $totalSlotsPerClass = $instructionalCount * $workingDaysCount;
        $instructionalPeriodIds = $instructionalPeriods->pluck('id')->toArray();

        // 2. Class Capacity Check
        $requirementsByClass = [];
        foreach ($requirements as $req) {
            $cId = is_array($req) ? $req['class_section_id'] : $req->class_section_id;
            $weekly = is_array($req) ? (int)$req['weekly_periods'] : (int)$req->weekly_periods;
            $isActive = is_array($req) ? ($req['is_active'] ?? true) : $req->is_active;

            if ($isActive && $weekly > 0) {
                $requirementsByClass[$cId][] = $req;
            }
        }

        // Fetch class availabilities
        $classUnavailabilities = ClassSectionAvailability::where('school_id', $schoolId)
            ->whereIn('class_section_id', $classSectionIds)
            ->whereIn('day', $workingDays)
            ->whereIn('period_id', $instructionalPeriodIds)
            ->where('status', 'unavailable')
            ->get()
            ->groupBy('class_section_id');

        $classesMap = ClassSection::whereIn('id', $classSectionIds)->get()->keyBy('id');

        foreach ($classSectionIds as $cId) {
            $classReqs = $requirementsByClass[$cId] ?? [];
            $totalDemand = 0;
            foreach ($classReqs as $r) {
                $totalDemand += is_array($r) ? (int)$r['weekly_periods'] : (int)$r->weekly_periods;
            }

            $blockedCount = isset($classUnavailabilities[$cId]) ? $classUnavailabilities[$cId]->count() : 0;
            $availableCapacity = $totalSlotsPerClass - $blockedCount;

            if ($totalDemand > $availableCapacity) {
                $className = $classesMap[$cId]->full_name ?? "Class #{$cId}";
                $conflicts[] = [
                    'code'               => 'CLASS_CAPACITY_EXCEEDED',
                    'severity'           => 'error',
                    'class_section_id'   => $cId,
                    'class_name'         => $className,
                    'required_periods'   => $totalDemand,
                    'available_capacity' => $availableCapacity,
                    'message'            => "Class {$className} requires {$totalDemand} periods/week but has only {$availableCapacity} available instructional slots."
                ];
            }
        }

        // 3. Teacher Capacity Check across all selected classes
        // Map each requirement to its assigned teacher
        $subjectTeachers = SubjectTeacher::whereIn('class_section_id', $classSectionIds)
            ->get()
            ->groupBy('class_section_id');

        $teacherDemand = [];
        $unassignedSubjects = [];

        foreach ($classSectionIds as $cId) {
            $classReqs = $requirementsByClass[$cId] ?? [];
            $stForClass = $subjectTeachers[$cId] ?? collect();

            foreach ($classReqs as $r) {
                $subId = is_array($r) ? $r['subject_id'] : $r->subject_id;
                $weekly = is_array($r) ? (int)$r['weekly_periods'] : (int)$r->weekly_periods;

                $st = $stForClass->firstWhere('subject_id', $subId);
                if ($st && $st->teacher_id) {
                    $teacherId = $st->teacher_id;
                    $teacherDemand[$teacherId] = ($teacherDemand[$teacherId] ?? 0) + $weekly;
                } else {
                    $unassignedSubjects[] = [
                        'class_section_id' => $cId,
                        'subject_id'       => $subId,
                    ];
                }
            }
        }

        if (!empty($teacherDemand)) {
            $teacherIds = array_keys($teacherDemand);
            $teachersMap = User::whereIn('id', $teacherIds)->get(['id', 'first_name', 'last_name'])->keyBy('id');

            // Count teacher unavailable slots
            $teacherUnavailabilities = TeacherAvailability::where('school_id', $schoolId)
                ->whereIn('teacher_id', $teacherIds)
                ->whereIn('day', $workingDays)
                ->whereIn('period_id', $instructionalPeriodIds)
                ->where('status', 'unavailable')
                ->get()
                ->groupBy('teacher_id');

            foreach ($teacherDemand as $tId => $demand) {
                $teacherBlocked = isset($teacherUnavailabilities[$tId]) ? $teacherUnavailabilities[$tId]->count() : 0;
                $teacherAvailable = $totalSlotsPerClass - $teacherBlocked;

                if ($demand > $teacherAvailable) {
                    $teacherName = $teachersMap[$tId]->full_name ?? "Teacher #{$tId}";
                    $conflicts[] = [
                        'code'               => 'TEACHER_CAPACITY_EXCEEDED',
                        'severity'           => 'error',
                        'teacher_id'         => $tId,
                        'teacher_name'       => $teacherName,
                        'required_periods'   => $demand,
                        'available_periods'  => $teacherAvailable,
                        'message'            => "Teacher {$teacherName} has {$demand} required teaching periods across selected classes but only {$teacherAvailable} available slots in the week."
                    ];
                }
            }
        }

        // 4. Resource Capacity Check
        $resourceDemand = [];
        foreach ($requirements as $req) {
            $resId = is_array($req) ? ($req['resource_id'] ?? null) : $req->resource_id;
            $resReq = is_array($req) ? !empty($req['resource_required']) : (bool)$req->resource_required;
            $weekly = is_array($req) ? (int)$req['weekly_periods'] : (int)$req->weekly_periods;
            $isActive = is_array($req) ? ($req['is_active'] ?? true) : $req->is_active;

            if ($isActive && $resId && $resReq && $weekly > 0) {
                $resourceDemand[$resId] = ($resourceDemand[$resId] ?? 0) + $weekly;
            }
        }

        if (!empty($resourceDemand)) {
            $resourceIds = array_keys($resourceDemand);
            $resourcesMap = TimetableResource::whereIn('id', $resourceIds)->get()->keyBy('id');

            $resUnavailabilities = TimetableResourceAvailability::where('school_id', $schoolId)
                ->whereIn('resource_id', $resourceIds)
                ->whereIn('day', $workingDays)
                ->whereIn('period_id', $instructionalPeriodIds)
                ->where('status', 'unavailable')
                ->get()
                ->groupBy('resource_id');

            foreach ($resourceDemand as $resId => $demand) {
                $resBlocked = isset($resUnavailabilities[$resId]) ? $resUnavailabilities[$resId]->count() : 0;
                $resAvailable = $totalSlotsPerClass - $resBlocked;

                if ($demand > $resAvailable) {
                    $resName = $resourcesMap[$resId]->name ?? "Resource #{$resId}";
                    $conflicts[] = [
                        'code'               => 'RESOURCE_CAPACITY_EXCEEDED',
                        'severity'           => 'error',
                        'resource_id'        => $resId,
                        'resource_name'      => $resName,
                        'required_periods'   => $demand,
                        'available_periods'  => $resAvailable,
                        'message'            => "Resource '{$resName}' has demand for {$demand} periods but only {$resAvailable} available slots."
                    ];
                }
            }
        }

        return [
            'feasible'  => empty($conflicts),
            'conflicts' => $conflicts,
        ];
    }
}
