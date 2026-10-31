<?php

namespace App\Http\Controllers;

use App\Models\ProxyTeacherAssignment;
use App\Models\StaffAttendance;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\Timetable;
use App\Models\User;
use App\Services\CachingService;
use App\Services\ResponseService;
use App\Services\Timetable\TeacherScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Teacher attendance cockpit: the admin marks each teacher for the day with a reason code,
 * sees which of an absent teacher's periods are still uncovered, and assigns proxy teachers.
 * Rows are stored in staff_attendances (type keeps its present/absent meaning for the
 * existing reports; leave_code carries the finer code).
 */
class TeacherAttendanceController extends Controller
{
    /** code => staff_attendances.type */
    public const CODES = [
        'P'  => 1, // present
        'HD' => 1, // half day
        'OD' => 1, // on duty elsewhere (paid, but not taking classes)
        'A'  => 0, // absent
        'CL' => 0, // casual leave
        'ML' => 0, // medical leave
    ];

    public function __construct(private CachingService $cache, private TeacherScheduleService $schedule)
    {
    }

    public function index()
    {
        ResponseService::noFeatureThenRedirect('Staff Attendance Management');
        ResponseService::noAnyPermissionThenRedirect(['staff-attendance-list', 'staff-attendance-create']);

        $subjects = Subject::owner()->orderBy('name')->get(['id', 'name']);
        return view('staff-attendance.teachers', compact('subjects'));
    }

    /**
     * Teachers with their periods for the date, the saved attendance and proxy cover.
     */
    public function data(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Staff Attendance Management');
        ResponseService::noAnyPermissionThenSendJson(['staff-attendance-list', 'staff-attendance-create']);
        $request->validate(['date' => 'required|date', 'subject_id' => 'nullable|integer']);

        $date = Carbon::parse($request->date);
        $teachers = User::role('Teacher')->where('status', 1)
            ->when($request->subject_id, function ($q, $subjectId) {
                $q->whereIn('id', SubjectTeacher::where('subject_id', $subjectId)->select('teacher_id'));
            })
            ->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'mobile', 'image']);

        $subjects = SubjectTeacher::with('subject:id,name')->whereIn('teacher_id', $teachers->pluck('id'))->get()
            ->groupBy('teacher_id')
            ->map(fn ($rows) => $rows->pluck('subject.name')->filter()->unique()->values()->implode(', '));

        $saved = StaffAttendance::whereIn('staff_id', $teachers->pluck('id'))
            ->whereDate('date', $date->toDateString())
            ->get()
            ->keyBy('staff_id');
        $proxies = $this->schedule->proxiesOn($date)->keyBy('timetable_id');

        $rows = $teachers->map(function (User $t) use ($date, $subjects, $saved, $proxies) {
            $lessons = $this->schedule->lessonsOf($t->id, $date);
            $row = $saved->get($t->id);
            $code = $row ? ($row->leave_code ?: ((int) $row->type === 1 ? 'P' : ((int) $row->type === 3 ? 'H' : 'A'))) : null;

            return [
                'teacher_id' => $t->id,
                'name'       => $t->full_name,
                'mobile'     => $t->mobile,
                'subjects'   => $subjects->get($t->id, ''),
                'code'       => $code,
                'remark'     => $row?->getRawOriginal('remark') ?? '',
                'periods'    => $lessons->map(fn ($l) => [
                    'timetable_id' => $l->id,
                    'label'        => ($l->period?->period_number ? 'P' . $l->period->period_number . ' ' : '')
                        . substr($l->start_time, 0, 5) . '-' . substr($l->end_time, 0, 5),
                    'class'        => $l->class_section?->full_name,
                    'subject'      => $l->subject?->name,
                    'proxy'        => $proxies->get($l->id)?->proxyTeacher?->full_name,
                ])->values(),
            ];
        });

        $marked = $rows->whereNotNull('code');
        return response()->json([
            'error'   => false,
            'date'    => $date->toDateString(),
            'day'     => $date->format('l'),
            'rows'    => $rows->values(),
            'summary' => [
                'total'   => $rows->count(),
                'present' => $marked->whereIn('code', ['P', 'HD'])->count(),
                'away'    => $marked->whereIn('code', TeacherScheduleService::AWAY_CODES)->count(),
                'unmarked'=> $rows->count() - $marked->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Staff Attendance Management');
        ResponseService::noAnyPermissionThenSendJson(['staff-attendance-create', 'staff-attendance-edit']);

        $validator = Validator::make($request->all(), [
            'date'                => 'required|date|before_or_equal:today',
            'rows'                => 'required|array|min:1',
            'rows.*.teacher_id'   => 'required|integer',
            'rows.*.code'         => 'required|in:' . implode(',', array_keys(self::CODES)),
            'rows.*.remark'       => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        $date = Carbon::parse($request->date)->toDateString();
        $teacherIds = User::role('Teacher')->whereIn('id', collect($request->rows)->pluck('teacher_id'))->pluck('id')->flip();
        $sessionYear = $this->cache->getDefaultSessionYear();

        try {
            DB::beginTransaction();
            foreach ($request->rows as $r) {
                if (!isset($teacherIds[(int) $r['teacher_id']])) {
                    continue;
                }
                $attendance = StaffAttendance::where('staff_id', $r['teacher_id'])->whereDate('date', $date)->first()
                    ?? new StaffAttendance(['staff_id' => $r['teacher_id'], 'date' => $date, 'school_id' => Auth::user()->school_id]);
                $attendance->fill([
                    'session_year_id' => $sessionYear->id,
                    'type'            => self::CODES[$r['code']],
                    'remark'          => $r['remark'] ?? null,
                ]);
                $attendance->leave_code = $r['code'];
                $attendance->save();
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'TeacherAttendanceController -> store');
            ResponseService::errorResponse();
        }

        // Absent teachers whose periods nobody covers yet: the page opens the proxy wizard.
        $day = Carbon::parse($date);
        $proxies = $this->schedule->proxiesOn($day)->pluck('timetable_id')->flip();
        $needsCover = collect($request->rows)
            ->filter(fn ($r) => in_array($r['code'], TeacherScheduleService::AWAY_CODES, true))
            ->map(function ($r) use ($day, $proxies) {
                $open = $this->schedule->lessonsOf((int) $r['teacher_id'], $day)->reject(fn ($l) => isset($proxies[$l->id]))->count();
                return $open ? ['teacher_id' => (int) $r['teacher_id'], 'uncovered' => $open] : null;
            })
            ->filter()->values();

        ResponseService::successResponse('Teacher attendance saved', ['needs_cover' => $needsCover]);
    }

    public function proxyPlan(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Staff Attendance Management');
        ResponseService::noAnyPermissionThenSendJson(['staff-attendance-create', 'staff-attendance-edit', 'timetable-edit']);
        $request->validate(['teacher_id' => 'required|integer', 'date' => 'required|date']);

        $teacher = User::role('Teacher')->find($request->teacher_id);
        if (!$teacher) {
            ResponseService::errorResponse('Teacher not found');
        }

        return response()->json([
            'error'   => false,
            'teacher' => $teacher->full_name,
            'date'    => Carbon::parse($request->date)->toDateString(),
            'lessons' => $this->schedule->proxyPlan((int) $teacher->id, Carbon::parse($request->date)),
        ]);
    }

    public function assignProxy(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Staff Attendance Management');
        ResponseService::noAnyPermissionThenSendJson(['staff-attendance-create', 'staff-attendance-edit', 'timetable-edit']);

        $validator = Validator::make($request->all(), [
            'date'             => 'required|date|after_or_equal:' . Carbon::today()->subDays(7)->toDateString(),
            'timetable_id'     => 'required|integer',
            'proxy_teacher_id' => 'required|integer',
            'notes'            => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        $date = Carbon::parse($request->date);
        $lesson = Timetable::owner()->with(['subject_teacher', 'subject', 'class_section'])->where('type', 'Lecture')->find($request->timetable_id);
        if (!$lesson || $lesson->day !== $date->format('l')) {
            ResponseService::validationError('That period is not on the timetable for this date.');
        }
        $absentId = (int) $lesson->subject_teacher?->teacher_id;
        $proxy = User::role('Teacher')->where('status', 1)->find($request->proxy_teacher_id);
        if (!$proxy || (int) $proxy->id === $absentId) {
            ResponseService::validationError('Choose another active teacher as the proxy.');
        }
        if (!$this->schedule->isFree((int) $proxy->id, $lesson, $date)) {
            ResponseService::validationError($proxy->full_name . ' is away or already teaching at that time.');
        }

        try {
            ProxyTeacherAssignment::updateOrCreate(
                ['date' => $date->toDateString(), 'timetable_id' => $lesson->id],
                [
                    'school_id'         => Auth::user()->school_id,
                    'class_section_id'  => $lesson->class_section_id,
                    'subject_id'        => $lesson->subject_id,
                    'absent_teacher_id' => $absentId,
                    'proxy_teacher_id'  => $proxy->id,
                    'assigned_by'       => Auth::id(),
                    'notes'             => $request->notes,
                ]
            );
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'TeacherAttendanceController -> assignProxy');
            ResponseService::errorResponse();
        }

        if (function_exists('send_notification')) {
            try {
                send_notification([$proxy->id], 'Proxy period assigned',
                    sprintf('%s, %s %s-%s: %s (%s)', $date->format('d M'), $lesson->class_section?->full_name,
                        substr($lesson->start_time, 0, 5), substr($lesson->end_time, 0, 5), $lesson->subject?->name, 'proxy'),
                    'timetable');
            } catch (Throwable $e) {
                Log::info('Proxy push not sent: ' . $e->getMessage());
            }
        }

        ResponseService::successResponse('Proxy teacher assigned');
    }

    public function removeProxy(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Staff Attendance Management');
        ResponseService::noAnyPermissionThenSendJson(['staff-attendance-create', 'staff-attendance-edit', 'timetable-edit']);
        $request->validate(['date' => 'required|date', 'timetable_id' => 'required|integer']);

        ProxyTeacherAssignment::whereDate('date', Carbon::parse($request->date)->toDateString())
            ->where('timetable_id', $request->timetable_id)
            ->delete();

        ResponseService::successResponse('Proxy removed');
    }
}
