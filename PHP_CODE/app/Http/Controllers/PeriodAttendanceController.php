<?php

namespace App\Http\Controllers;

use App\Models\ClassSection;
use App\Models\PeriodAttendance;
use App\Models\StudentMovementLog;
use App\Models\Students;
use App\Models\Timetable;
use App\Models\TimetablePeriod;
use App\Services\CachingService;
use App\Services\ResponseService;
use App\Services\Timetable\TeacherScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Period-wise attendance: the class's period-by-period matrix for a date, and marking one
 * timetable period. Marking is limited to admins, the period's own teacher, or the proxy
 * teacher assigned for that date.
 */
class PeriodAttendanceController extends Controller
{
    public const STATUSES = ['present', 'absent', 'late', 'left_early', 'excused', 'infirmary', 'school_activity'];

    public function __construct(private CachingService $cache, private TeacherScheduleService $schedule)
    {
    }

    /**
     * Matrix for the View Attendance page: one column per period (with times), one row
     * per student.
     */
    public function matrix(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Attendance Management');
        ResponseService::noAnyPermissionThenSendJson(['class-teacher', 'attendance-list']);
        $validator = Validator::make($request->all(), ['class_section_id' => 'required|integer', 'date' => 'required|date']);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        $classSection = ClassSection::owner()->ClassTeacher()->find($request->class_section_id);
        if (!$classSection) {
            ResponseService::errorResponse('Class section not found');
        }
        $date = Carbon::parse($request->date);
        $sessionYear = $this->cache->getDefaultSessionYear();
        $columns = $this->columnsFor($classSection->id, $date);

        $students = Students::owner()->with('user:id,first_name,last_name')
            ->where('class_section_id', $classSection->id)
            ->where('session_year_id', $sessionYear->id)
            ->whereHas('user', fn ($q) => $q->whereNull('deleted_at'))
            ->orderBy('roll_number')
            ->get();

        $records = PeriodAttendance::where('class_section_id', $classSection->id)
            ->whereDate('date', $date->toDateString())
            ->get()
            ->groupBy('student_id');

        $rows = $students->map(function (Students $s) use ($columns, $records) {
            $mine = $records->get($s->id, collect())->keyBy('period_number');
            $cells = [];
            $present = 0;
            $marked = 0;
            foreach ($columns as $c) {
                $r = $mine->get($c['period_number']);
                $status = $r?->status ?? 'unmarked';
                if ($r) {
                    $marked++;
                    if (in_array($status, ['present', 'late', 'school_activity'], true)) {
                        $present++;
                    }
                }
                $cells['p_' . $c['period_number']] = ['status' => $status, 'remarks' => $r?->remarks ?? ''];
            }
            return [
                'student_id'    => $s->id,
                'roll_number'   => $s->roll_number_label ?? $s->roll_number ?? '-',
                'student_name'  => $s->user?->full_name,
                'periods'       => $cells,
                'present_count' => $present,
                'marked_count'  => $marked,
                'total_periods' => count($columns),
            ];
        });

        return response()->json(['error' => false, 'periods' => $columns, 'rows' => $rows->values()]);
    }

    /**
     * Popup data for a timetable card: what the period is and what this user may do.
     */
    public function slot(Request $request)
    {
        $request->validate(['timetable_id' => 'required|integer', 'date' => 'nullable|date']);
        $slot = Timetable::owner()->with(['subject', 'class_section.class', 'class_section.section', 'class_section.medium', 'resource', 'period', 'subject_teacher.teacher'])
            ->find($request->timetable_id);
        if (!$slot) {
            ResponseService::errorResponse('Period not found');
        }
        $date = $this->dateFor($slot, $request->date);
        $user = Auth::user();
        $allowed = $this->schedule->canHandleSlot($user, $slot, $date);
        $marked = PeriodAttendance::where('class_section_id', $slot->class_section_id)
            ->whereDate('date', $date->toDateString())
            ->where('period_number', $this->periodNumber($slot))
            ->count();

        return response()->json([
            'error'      => false,
            'id'         => $slot->id,
            'date'       => $date->toDateString(),
            'date_label' => $date->format('D, d M Y'),
            'subject'    => $slot->subject?->name ?? $slot->title,
            'class'      => $slot->class_section?->full_name,
            'class_section_id' => $slot->class_section_id,
            'time'       => substr($slot->start_time, 0, 5) . ' - ' . substr($slot->end_time, 0, 5),
            'room'       => $slot->resource?->name,
            'teacher'    => $slot->subject_teacher?->teacher?->full_name,
            'period'     => $this->periodNumber($slot),
            'can_mark'   => $allowed,
            'marked'     => $marked,
            'links'      => [
                'attendance' => $allowed ? route('period-attendance.mark', ['timetable_id' => $slot->id, 'date' => $date->toDateString()]) : null,
                'diary'      => $user->can('student-diary-list') ? route('diary.index') : null,
                'roster'     => $user->can('student-list') ? route('students.leadership.index', ['class_section_id' => $slot->class_section_id]) : null,
                'gate_pass'  => $allowed ? route('period-attendance.gate-pass') : null,
            ],
        ]);
    }

    /**
     * Mark one period's attendance.
     */
    public function mark(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Attendance Management');
        $request->validate(['timetable_id' => 'required|integer', 'date' => 'nullable|date']);

        $slot = Timetable::owner()->with(['subject', 'class_section.class', 'class_section.section', 'class_section.medium', 'period', 'subject_teacher'])
            ->where('type', 'Lecture')->find($request->timetable_id);
        if (!$slot) {
            abort(404);
        }
        $date = $this->dateFor($slot, $request->date);
        if (!$this->schedule->canHandleSlot(Auth::user(), $slot, $date)) {
            abort(403, 'You are not assigned to this period.');
        }

        $sessionYear = $this->cache->getDefaultSessionYear();
        $students = Students::with('user:id,first_name,last_name,image')
            ->where('class_section_id', $slot->class_section_id)
            ->where('session_year_id', $sessionYear->id)
            ->whereHas('user', fn ($q) => $q->whereNull('deleted_at'))
            ->orderBy('roll_number')
            ->get();
        $existing = PeriodAttendance::where('class_section_id', $slot->class_section_id)
            ->whereDate('date', $date->toDateString())
            ->where('period_number', $this->periodNumber($slot))
            ->get()->keyBy('student_id');

        return view('attendance.period-mark', [
            'slot'     => $slot,
            'date'     => $date,
            'period'   => $this->periodNumber($slot),
            'students' => $students,
            'existing' => $existing,
        ]);
    }

    public function store(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Attendance Management');
        $validator = Validator::make($request->all(), [
            'timetable_id'           => 'required|integer',
            'date'                   => 'required|date|before_or_equal:today',
            'attendance'             => 'required|array|min:1',
            'attendance.*.student_id'=> 'required|integer',
            'attendance.*.status'    => 'required|in:' . implode(',', self::STATUSES),
            'attendance.*.remarks'   => 'nullable|string|max:255',
            'attendance.*.minutes_late' => 'nullable|integer|min:1|max:120',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        $slot = Timetable::owner()->with('period', 'subject_teacher')->where('type', 'Lecture')->find($request->timetable_id);
        $date = Carbon::parse($request->date);
        if (!$slot || $slot->day !== $date->format('l')) {
            ResponseService::validationError('That period is not on the timetable for this date.');
        }
        if (!$this->schedule->canHandleSlot(Auth::user(), $slot, $date)) {
            ResponseService::errorResponse('You are not assigned to this period.', null, 403);
        }

        $sessionYear = $this->cache->getDefaultSessionYear();
        $classStudents = Students::where('class_section_id', $slot->class_section_id)
            ->where('session_year_id', $sessionYear->id)->pluck('id')->flip();
        $periodNumber = $this->periodNumber($slot);

        try {
            DB::beginTransaction();
            foreach ($request->attendance as $row) {
                if (!isset($classStudents[(int) $row['student_id']])) {
                    continue;
                }
                PeriodAttendance::updateOrCreate(
                    ['date' => $date->toDateString(), 'period_number' => $periodNumber, 'student_id' => (int) $row['student_id']],
                    [
                        'school_id'           => Auth::user()->school_id,
                        'session_year_id'     => $sessionYear->id,
                        'class_section_id'    => $slot->class_section_id,
                        'timetable_period_id' => $slot->period_id,
                        'subject_id'          => $slot->subject_id,
                        'marked_by_user_id'   => Auth::id(),
                        'status'              => $row['status'],
                        'minutes_late'        => $row['status'] === 'late' ? ($row['minutes_late'] ?? null) : null,
                        'remarks'             => $row['remarks'] ?? null,
                    ]
                );
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'PeriodAttendanceController -> store');
            ResponseService::errorResponse();
        }

        ResponseService::successResponse('Period attendance saved');
    }

    /**
     * Gate pass / infirmary slip from the period popup.
     */
    public function gatePass(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Attendance Management');
        $validator = Validator::make($request->all(), [
            'timetable_id'         => 'required|integer',
            'student_id'           => 'required|integer',
            'movement_type'        => 'required|in:gate_pass_exit,infirmary_visit,principal_office,library_pass,sports_duty',
            'expected_return_time' => 'nullable|date_format:H:i',
            'pickup_person_name'   => 'nullable|required_if:movement_type,gate_pass_exit|string|max:191',
            'pickup_person_relationship' => 'nullable|string|max:50',
            'pickup_person_phone'  => 'nullable|string|max:30',
            'reason'               => 'required|string|max:1000',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        $slot = Timetable::owner()->with('subject_teacher')->find($request->timetable_id);
        $today = Carbon::today();
        if (!$slot || !$this->schedule->canHandleSlot(Auth::user(), $slot, $today)) {
            ResponseService::errorResponse('You are not assigned to this period today.', null, 403);
        }
        $sessionYear = $this->cache->getDefaultSessionYear();
        $student = Students::where('class_section_id', $slot->class_section_id)->where('session_year_id', $sessionYear->id)->find($request->student_id);
        if (!$student) {
            ResponseService::validationError('The student is not in this class.');
        }

        StudentMovementLog::create([
            'school_id'                  => Auth::user()->school_id,
            'session_year_id'            => $sessionYear->id,
            'student_id'                 => $student->id,
            'date'                       => $today->toDateString(),
            'movement_type'              => $request->movement_type,
            'departure_time'             => Carbon::now()->format('H:i:s'),
            'expected_return_time'       => $request->expected_return_time,
            'authorized_by_staff_id'     => Auth::id(),
            'pickup_person_name'         => $request->pickup_person_name,
            'pickup_person_relationship' => $request->pickup_person_relationship,
            'pickup_person_phone'        => $request->pickup_person_phone,
            'reason'                     => $request->reason,
            'status'                     => $request->movement_type === 'gate_pass_exit' ? 'departed_campus' : 'out',
        ]);

        ResponseService::successResponse('Pass recorded');
    }

    /**
     * Students of a slot's class, for the gate pass picker.
     */
    public function students(Request $request)
    {
        $request->validate(['timetable_id' => 'required|integer']);
        $slot = Timetable::owner()->with('subject_teacher')->find($request->timetable_id);
        if (!$slot || !$this->schedule->canHandleSlot(Auth::user(), $slot, Carbon::today())) {
            ResponseService::errorResponse('You are not assigned to this period today.', null, 403);
        }
        $students = Students::with('user:id,first_name,last_name')
            ->where('class_section_id', $slot->class_section_id)
            ->where('session_year_id', $this->cache->getDefaultSessionYear()->id)
            ->orderBy('roll_number')->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => trim(($s->roll_number_label ?? $s->roll_number) . ' ' . $s->user?->full_name)]);

        return response()->json(['error' => false, 'students' => $students]);
    }

    /**
     * Period columns for a class on a date: the school's bell periods when they exist,
     * otherwise the class's own lessons that weekday in time order.
     */
    private function columnsFor(int $classSectionId, Carbon $date): array
    {
        $periods = TimetablePeriod::owner()->where('is_active', true)->where('is_instructional', true)
            ->orderBy('period_number')->get();
        if ($periods->isNotEmpty()) {
            return $periods->unique('period_number')->map(fn ($p) => [
                'period_number' => (int) $p->period_number,
                'name'          => $p->name ?: ('P' . $p->period_number),
                'time_label'    => Carbon::parse($p->start_time)->format('h:i A') . ' - ' . Carbon::parse($p->end_time)->format('h:i A'),
            ])->values()->all();
        }

        return $this->classLessons($classSectionId, $date->format('l'))
            ->map(fn ($t, $i) => [
                'period_number' => $i + 1,
                'name'          => 'P' . ($i + 1),
                'time_label'    => Carbon::parse($t->start_time)->format('h:i A') . ' - ' . Carbon::parse($t->end_time)->format('h:i A'),
            ])->values()->all();
    }

    private function classLessons(int $classSectionId, string $day): Collection
    {
        return Timetable::owner()->where('class_section_id', $classSectionId)->where('day', $day)
            ->where('type', 'Lecture')->CurrentSemesterData()->orderBy('start_time')->get();
    }

    /**
     * Period number of a slot: its bell period, else its position among the class's
     * lessons that weekday (the same numbering the matrix falls back to).
     */
    private function periodNumber(Timetable $slot): int
    {
        if ($slot->period_id && $slot->period) {
            return (int) $slot->period->period_number;
        }
        if (!empty($slot->meta['period_no'])) {
            return (int) $slot->meta['period_no'];
        }
        $index = $this->classLessons($slot->class_section_id, $slot->day)->search(fn ($t) => $t->id === $slot->id);
        return $index === false ? 1 : $index + 1;
    }

    /**
     * The date to act on: the requested date if it falls on the slot's weekday, otherwise
     * the most recent such day (today if the slot is today).
     */
    private function dateFor(Timetable $slot, ?string $requested): Carbon
    {
        if ($requested) {
            $d = Carbon::parse($requested);
            if ($d->format('l') === $slot->day) {
                return $d;
            }
        }
        $d = Carbon::today();
        for ($i = 0; $i < 7 && $d->format('l') !== $slot->day; $i++) {
            $d->subDay();
        }
        return $d;
    }
}
