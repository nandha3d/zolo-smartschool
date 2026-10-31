<?php

namespace App\Services\Timetable;

use App\Models\Leave;
use App\Models\ProxyTeacherAssignment;
use App\Models\StaffAttendance;
use App\Models\SubjectTeacher;
use App\Models\TeacherAvailability;
use App\Models\Timetable;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Who teaches what on a given date: each teacher's lessons for the weekday, the proxy
 * assignments made for that date, and ranked proxy candidates for an absent teacher.
 * Shared by the teacher attendance cockpit, the substitutes endpoint and the
 * period-attendance permission check.
 */
class TeacherScheduleService
{
    /** Attendance codes that mean the teacher is not taking classes that day. */
    public const AWAY_CODES = ['A', 'CL', 'ML', 'OD'];

    private array $dayCache = [];

    /**
     * All lectures on the weekday of $date, in the current semester.
     */
    public function dayLessons(Carbon $date): Collection
    {
        $day = $date->format('l');
        return $this->dayCache[$day] ??= Timetable::owner()
            ->where('type', 'Lecture')
            ->where('day', $day)
            ->whereNotNull('subject_teacher_id')
            ->with([
                'subject:id,name,bg_color',
                'subject_teacher:id,teacher_id',
                'class_section.class',
                'class_section.section',
                'class_section.medium',
                'period',
                'resource:id,name',
            ])
            ->CurrentSemesterData()
            ->orderBy('start_time')
            ->get();
    }

    public function lessonsOf(int $teacherId, Carbon $date): Collection
    {
        return $this->dayLessons($date)
            ->filter(fn ($t) => (int) $t->subject_teacher?->teacher_id === $teacherId)
            ->values();
    }

    public function proxiesOn(Carbon $date): Collection
    {
        return ProxyTeacherAssignment::with('proxyTeacher:id,first_name,last_name')
            ->whereDate('date', $date->toDateString())
            ->get();
    }

    /**
     * Teachers who are away on $date: approved leave, or marked absent / on duty / on
     * leave in staff attendance.
     */
    public function awayTeacherIds(Carbon $date): array
    {
        $onLeave = Leave::where('school_id', Auth::user()->school_id)
            ->where('status', 1)
            ->whereHas('leave_detail', fn ($q) => $q->whereDate('date', $date->toDateString()))
            ->pluck('user_id');

        $marked = StaffAttendance::whereDate('date', $date->toDateString())
            ->where(function ($q) {
                $q->whereIn('leave_code', self::AWAY_CODES)
                    ->orWhere(fn ($q2) => $q2->whereNull('leave_code')->where('type', 0));
            })
            ->pluck('staff_id');

        return $onLeave->merge($marked)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Ranked proxy candidates for each of the absent teacher's lessons on $date. A
     * candidate must be present, free at that time (their own lessons and proxies they
     * already cover count) and not marked unavailable for the period.
     */
    public function proxyPlan(int $absentId, Carbon $date): Collection
    {
        $day = $date->format('l');
        $lessons = $this->lessonsOf($absentId, $date);
        $proxies = $this->proxiesOn($date);
        $away = $this->awayTeacherIds($date);

        $teachers = User::role('Teacher')
            ->where('status', 1)
            ->where('id', '!=', $absentId)
            ->whereNotIn('id', $away)
            ->get(['id', 'first_name', 'last_name']);

        $assignments = SubjectTeacher::whereIn('teacher_id', $teachers->pluck('id'))
            ->get(['teacher_id', 'subject_id', 'class_section_id'])
            ->groupBy('teacher_id');

        $unavailable = TeacherAvailability::where('school_id', Auth::user()->school_id)
            ->where('day', $day)
            ->where('status', 'unavailable')
            ->get(['teacher_id', 'period_id'])
            ->groupBy('teacher_id')
            ->map(fn ($g) => $g->pluck('period_id')->flip()->all());

        $dayLessons = $this->dayLessons($date)->keyBy('id');
        $busy = $this->dayLessons($date)->groupBy(fn ($t) => (int) $t->subject_teacher?->teacher_id);
        // A proxy already covering a slot is busy then too.
        foreach ($proxies as $p) {
            if ($slot = $dayLessons->get($p->timetable_id)) {
                $busy[(int) $p->proxy_teacher_id] = ($busy[(int) $p->proxy_teacher_id] ?? collect())->push($slot);
            }
        }

        return $lessons->map(function ($lesson) use ($teachers, $assignments, $unavailable, $busy, $proxies) {
            $candidates = [];
            foreach ($teachers as $teacher) {
                $theirs = $busy[$teacher->id] ?? collect();
                $clash = $theirs->first(fn ($b) => $b->id !== $lesson->id && $b->start_time < $lesson->end_time && $b->end_time > $lesson->start_time);
                if ($clash || ($lesson->period_id && isset($unavailable[$teacher->id][$lesson->period_id]))) {
                    continue;
                }
                $teaches = $assignments->get($teacher->id, collect());
                $sameSubject = $teaches->contains('subject_id', $lesson->subject_id);
                $sameClass = $teaches->contains('class_section_id', $lesson->class_section_id);
                $reasons = [];
                if ($sameSubject) {
                    $reasons[] = 'teaches ' . ($lesson->subject?->name ?? 'this subject');
                }
                if ($sameClass) {
                    $reasons[] = 'already teaches this class';
                }
                $reasons[] = $theirs->count() . ' lessons that day';

                $candidates[] = [
                    'teacher_id'       => $teacher->id,
                    'name'             => trim($teacher->first_name . ' ' . $teacher->last_name),
                    'score'            => ($sameSubject ? 20 : 0) + ($sameClass ? 10 : 0) - $theirs->count(),
                    'lessons_that_day' => $theirs->count(),
                    'reasons'          => $reasons,
                ];
            }
            usort($candidates, fn ($a, $b) => [$b['score'], $a['name']] <=> [$a['score'], $b['name']]);

            $current = $proxies->firstWhere('timetable_id', $lesson->id);

            return [
                'timetable_id'  => $lesson->id,
                'class_name'    => $lesson->class_section?->full_name,
                'subject_name'  => $lesson->subject?->name,
                'start_time'    => $lesson->start_time,
                'end_time'      => $lesson->end_time,
                'period_number' => $lesson->period?->period_number,
                'proxy'         => $current ? [
                    'teacher_id' => $current->proxy_teacher_id,
                    'name'       => $current->proxyTeacher?->full_name,
                ] : null,
                'candidates'    => array_slice($candidates, 0, 5),
            ];
        })->values();
    }

    /**
     * Is the teacher present and free for the lesson's time on $date (own lessons and other
     * proxy cover included)?
     */
    public function isFree(int $teacherId, Timetable $lesson, Carbon $date): bool
    {
        if (in_array($teacherId, $this->awayTeacherIds($date), true)) {
            return false;
        }
        $overlaps = fn ($t) => $t->id !== $lesson->id && $t->start_time < $lesson->end_time && $t->end_time > $lesson->start_time;
        if ($this->lessonsOf($teacherId, $date)->contains($overlaps)) {
            return false;
        }
        $covering = $this->proxiesOn($date)
            ->where('proxy_teacher_id', $teacherId)
            ->where('timetable_id', '!=', $lesson->id)
            ->pluck('timetable_id');
        return !$this->dayLessons($date)->whereIn('id', $covering)->contains($overlaps);
    }

    /**
     * Can this user take attendance / log the diary for the timetable slot on $date?
     * School admins and users with attendance-edit can; otherwise only the slot's own
     * teacher (unless a proxy was assigned, which means they are away) or the proxy.
     */
    public function canHandleSlot(User $user, Timetable $slot, Carbon $date): bool
    {
        if ($user->hasRole('School Admin') || $user->hasRole('Super Admin') || $user->can('attendance-edit')) {
            return true;
        }
        if ($slot->day !== $date->format('l')) {
            return false;
        }
        $proxy = ProxyTeacherAssignment::whereDate('date', $date->toDateString())->where('timetable_id', $slot->id)->first();
        if ($proxy) {
            return (int) $proxy->proxy_teacher_id === (int) $user->id;
        }
        return (int) $slot->subject_teacher?->teacher_id === (int) $user->id;
    }
}
