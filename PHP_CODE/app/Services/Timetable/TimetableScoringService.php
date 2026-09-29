<?php

namespace App\Services\Timetable;

class TimetableScoringService
{
    // Default soft constraint weights
    public const WEIGHT_SUBJECT_DISTRIBUTION     = 100;
    public const WEIGHT_AVOID_SAME_SUBJECT_TWICE = 80;
    public const WEIGHT_TEACHER_WORKLOAD_BALANCE = 70;
    public const WEIGHT_TEACHER_CONSECUTIVE      = 70;
    public const WEIGHT_PREFERRED_TEACHER_PERIOD = 50;
    public const WEIGHT_PREFERRED_SUBJECT_PERIOD = 50;
    public const WEIGHT_AVOID_FIRST_PERIOD       = 30;
    public const WEIGHT_AVOID_LAST_PERIOD        = 30;
    public const WEIGHT_AVOID_GAPS               = 40;

    /**
     * Calculate candidate placement score for placing a block in a specific slot.
     */
    public function scoreSlotPlacement(
        array $candidateSlot,
        array $block,
        array $currentClassSchedule,
        array $currentTeacherSchedule,
        array $requirementsMap,
        array $options = []
    ): int {
        $score = 0;
        $day = $candidateSlot['day'];
        $periodNo = $candidateSlot['period_number'];
        $subjectId = $block['subject_id'];
        $teacherId = $block['teacher_id'];
        $req = $requirementsMap[$block['class_section_id']][$subjectId] ?? null;

        // 1. Subject distribution: has this subject already been placed on this day?
        $countOnDay = 0;
        foreach ($currentClassSchedule as $slot) {
            if ($slot['day'] === $day && ($slot['subject_id'] ?? null) === $subjectId) {
                $countOnDay++;
            }
        }

        if ($countOnDay === 0) {
            $score += self::WEIGHT_SUBJECT_DISTRIBUTION;
        } else {
            // If already on this day and consecutive not allowed, heavily penalize
            if ($req && !$req->allow_consecutive) {
                $score -= self::WEIGHT_AVOID_SAME_SUBJECT_TWICE;
            }
        }

        // 2. Preferences on subject
        if ($req) {
            if ($req->avoid_first_period && $periodNo === 1) {
                $score -= self::WEIGHT_AVOID_FIRST_PERIOD;
            }
            if ($req->avoid_last_period && !empty($candidateSlot['is_last_period'])) {
                $score -= self::WEIGHT_AVOID_LAST_PERIOD;
            }
            if ($req->preferred_day && $req->preferred_day === $day) {
                $score += self::WEIGHT_PREFERRED_SUBJECT_PERIOD;
            }
        }

        // 3. Teacher consecutive periods check
        $teacherConsecutive = 0;
        if ($teacherId && isset($currentTeacherSchedule[$day])) {
            $teacherSlots = $currentTeacherSchedule[$day];
            // Check if period before has a lesson
            if (isset($teacherSlots[$periodNo - 1])) {
                $teacherConsecutive++;
            }
            if (isset($teacherSlots[$periodNo + 1])) {
                $teacherConsecutive++;
            }

            if ($teacherConsecutive >= 3) {
                $score -= self::WEIGHT_TEACHER_CONSECUTIVE;
            } elseif ($teacherConsecutive === 0) {
                $score += 10; // modest bonus for spread
            }
        }

        return $score;
    }

    /**
     * Calculate global quality metrics for a completed schedule.
     */
    public function calculateQualityMetrics(
        array $schedule,
        $requirements,
        array $workingDays,
        int $periodsPerDay
    ): array {
        $subjectDistributionViolations = 0;
        $teacherConsecutiveViolations = 0;
        $gapsCount = 0;
        $preferenceViolations = 0;

        // Index schedule by class and teacher
        $byClass = [];
        $byTeacher = [];
        foreach ($schedule as $slot) {
            if (($slot['type'] ?? 'Lecture') !== 'Lecture') continue;
            $cId = $slot['class_section_id'];
            $tId = $slot['teacher_id'] ?? 0;
            $day = $slot['day'];
            $pNo = $slot['period_number'] ?? 0;

            $byClass[$cId][$day][$pNo] = $slot;
            if ($tId) {
                $byTeacher[$tId][$day][$pNo] = $slot;
            }
        }

        // Check subject distribution per class
        foreach ($byClass as $cId => $days) {
            foreach ($days as $day => $slots) {
                $counts = [];
                foreach ($slots as $s) {
                    $counts[$s['subject_id']] = ($counts[$s['subject_id']] ?? 0) + 1;
                }
                foreach ($counts as $subId => $c) {
                    if ($c > 1) {
                        // Check if requirement allows consecutive
                        $subjectDistributionViolations += ($c - 1);
                    }
                }
            }
        }

        // Check teacher consecutive limit (> 3 periods in a row)
        foreach ($byTeacher as $tId => $days) {
            foreach ($days as $day => $slots) {
                ksort($slots);
                $streak = 0;
                $prev = null;
                foreach ($slots as $pNo => $s) {
                    if ($prev !== null && $pNo === $prev + 1) {
                        $streak++;
                        if ($streak >= 3) {
                            $teacherConsecutiveViolations++;
                        }
                    } else {
                        $streak = 1;
                    }
                    $prev = $pNo;
                }
            }
        }

        $totalViolations = $subjectDistributionViolations + $teacherConsecutiveViolations + $preferenceViolations;
        $totalLessons = count(array_filter($schedule, fn($s) => ($s['type'] ?? 'Lecture') === 'Lecture'));

        $qualityScore = 100.0;
        if ($totalLessons > 0) {
            $deduction = ($totalViolations / $totalLessons) * 50;
            $qualityScore = max(0, min(100, round(100 - $deduction, 1)));
        }

        return [
            'total_score'                   => $qualityScore,
            'quality_score'                 => $qualityScore,
            'quality_percentage'            => $qualityScore,
            'soft_constraint_violations'    => $totalViolations,
            'subject_distribution_score'    => max(0, 100 - ($subjectDistributionViolations * 10)),
            'teacher_balance_score'         => max(0, 100 - ($teacherConsecutiveViolations * 15)),
            'preference_score'              => max(0, 100 - ($preferenceViolations * 5)),
            'details' => [
                'subject_distribution_violations' => $subjectDistributionViolations,
                'teacher_consecutive_violations'  => $teacherConsecutiveViolations,
                'gaps_count'                      => $gapsCount,
            ]
        ];
    }
}
