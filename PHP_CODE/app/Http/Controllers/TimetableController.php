<?php

namespace App\Http\Controllers;

use App\Models\Timetable;
use App\Models\SubjectTeacher;
use App\Models\User;
use App\Models\ClassSubject;
use App\Models\ClassSection;
use App\Models\ClassTeacher;
use App\Models\TimetableTemplate;
use App\Models\TimetablePeriod;
use App\Models\TimetableResource;
use App\Models\SubjectScheduleRequirement;
use App\Models\TimetableGenerationRun;
use App\Models\TeacherAvailability;
use App\Models\Leave;
use App\Services\TimetableGeneratorService;
use App\Services\Timetable\TimetablePlanningService;
use App\Services\Timetable\TimetableGenerationService;
use App\Services\Timetable\TimetableApplyService;
use App\Services\Timetable\TimetableRepairService;
use App\Services\Timetable\TimetableValidationService;
use App\Repositories\ClassSchool\ClassSchoolInterface;
use App\Repositories\ClassSection\ClassSectionInterface;
use App\Repositories\Medium\MediumInterface;
use App\Repositories\SchoolSetting\SchoolSettingInterface;
use App\Repositories\Subject\SubjectInterface;
use App\Repositories\SubjectTeacher\SubjectTeacherInterface;
use App\Repositories\Timetable\TimetableInterface;
use App\Repositories\User\UserInterface;
use App\Services\BootstrapTableService;
use App\Services\SessionYearsTrackingsService;
use App\Services\CachingService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Throwable;

class TimetableController extends Controller
{
    private SubjectTeacherInterface $subjectTeacher;
    private SubjectInterface $subject;
    private TimetableInterface $timetable;
    private ClassSectionInterface $classSection;
    private UserInterface $user;
    private SchoolSettingInterface $schoolSettings;
    private CachingService $cache;
    private ClassSchoolInterface $class;
    private MediumInterface $medium;
    private SessionYearsTrackingsService $sessionYearsTrackingsService;
    private TimetablePlanningService $planningService;
    private TimetableGenerationService $generationService;
    private TimetableApplyService $applyService;
    private TimetableRepairService $repairService;
    private TimetableValidationService $validationService;

    public function __construct(
        SubjectTeacherInterface $subjectTeacher,
        SubjectInterface $subject,
        TimetableInterface $timetable,
        ClassSectionInterface $classSection,
        UserInterface $user,
        SchoolSettingInterface $schoolSettings,
        CachingService $cache,
        ClassSchoolInterface $class,
        MediumInterface $medium,
        SessionYearsTrackingsService $sessionYearsTrackingsService,
        TimetablePlanningService $planningService,
        TimetableGenerationService $generationService,
        TimetableApplyService $applyService,
        TimetableRepairService $repairService,
        TimetableValidationService $validationService
    ) {
        $this->subjectTeacher = $subjectTeacher;
        $this->subject = $subject;
        $this->timetable = $timetable;
        $this->classSection = $classSection;
        $this->user = $user;
        $this->schoolSettings = $schoolSettings;
        $this->cache = $cache;
        $this->class = $class;
        $this->medium = $medium;
        $this->sessionYearsTrackingsService = $sessionYearsTrackingsService;
        $this->planningService = $planningService;
        $this->generationService = $generationService;
        $this->applyService = $applyService;
        $this->repairService = $repairService;
        $this->validationService = $validationService;
    }

    public function index()
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenRedirect('timetable-list');

        // Get Timetable Settings Data
        $timetableData = $this->schoolSettings->getBulkData([
            'timetable_start_time',
            'timetable_end_time',
            'timetable_duration'
        ]);

        // Convert Timetable Duration time to number safely
        $rawDuration = $timetableData['timetable_duration'] ?? "00:45:00";
        $durationMinutes = abs((int)Carbon::parse($rawDuration)->diffInMinutes(Carbon::parse('00:00:00')));
        $timetableData['timetable_duration'] = ($durationMinutes > 0 && $durationMinutes < 1440) ? $durationMinutes : 45;

        $classes = $this->class->builder()->with('stream')->get()->pluck('full_name', 'id');
        $mediums = $this->medium->builder()->pluck('name', 'id');
        $classSections = $this->classSection->builder()->with(['class', 'section', 'medium', 'teachers'])->get();

        $schoolId = Auth::user()->school_id;
        $templates = $this->planningService->getTemplates($schoolId);
        $resources = $this->planningService->getResources($schoolId);

        $availableBlocks = collect($classSections->pluck('block')->merge($resources->pluck('block')))->filter()->unique()->values()->all();
        $blockSettings = $this->schoolSettings->getBulkData(['timetable_prioritize_same_block']);
        $prioritizeSameBlock = isset($blockSettings['timetable_prioritize_same_block']) ? (bool)$blockSettings['timetable_prioritize_same_block'] : true;

        // Per-section readiness for the school-wide batch generator: how many subject
        // requirements are set, their weekly periods, and how many lack a teacher.
        $currentSemester = $this->cache->getDefaultSemesterData();
        $requirements = SubjectScheduleRequirement::where('school_id', $schoolId)
            ->where('is_active', true)
            ->when($currentSemester, fn($q) => $q->where(fn($sq) => $sq->where('semester_id', $currentSemester->id)->orWhereNull('semester_id')))
            ->get(['class_section_id', 'subject_id', 'weekly_periods']);
        $taught = SubjectTeacher::whereIn('class_section_id', $classSections->pluck('id'))
            ->get(['class_section_id', 'subject_id'])
            ->map(fn($st) => $st->class_section_id . ':' . $st->subject_id)
            ->flip();
        $readiness = $requirements->groupBy('class_section_id')->map(fn($reqs) => [
            'subjects'   => $reqs->count(),
            'periods'    => (int) $reqs->sum('weekly_periods'),
            'no_teacher' => $reqs->filter(fn($r) => !isset($taught[$r->class_section_id . ':' . $r->subject_id]))->count(),
        ]);

        return view('timetable.index', compact('timetableData', 'classes', 'mediums', 'classSections', 'templates', 'resources', 'readiness', 'availableBlocks', 'prioritizeSameBlock'));
    }

    public function store(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenRedirect(['timetable-create']);
        $request->validate([
            'subject_teacher_id' => 'nullable|numeric',
            'class_section_id' => 'required|numeric',
            'subject_id' => 'nullable|numeric',
            'start_time' => 'required',
            'end_time' => 'required',
            'day' => 'required',
            'note' => 'nullable',
        ]);
        try {
            // Check for teacher conflicts
            if ($request->subject_teacher_id) {
                // First get the teacher_id from the subject_teacher record
                $subjectTeacher = $this->subjectTeacher->findById($request->subject_teacher_id);
                if ($subjectTeacher) {
                    $teacher_id = $subjectTeacher->teacher_id;
                    
                    // Now check for conflicts using the teacher_id
                    $conflictingTimetable = $this->timetable->builder()
                        ->where('day', $request->day)
                        ->whereHas('subject_teacher', function($q) use ($teacher_id) {
                            $q->where('teacher_id', $teacher_id);
                        })
                        ->where(function($query) use ($request) {
                            $query->where(function($q) use ($request) {
                                $q->where('start_time', '<=', $request->start_time)
                                  ->where('end_time', '>', $request->start_time);
                            })->orWhere(function($q) use ($request) {
                                $q->where('start_time', '<', $request->end_time)
                                  ->where('end_time', '>=', $request->end_time);
                            });
                        })
                        ->first();

                    if ($conflictingTimetable) {
                        ResponseService::errorResponse('Teacher is already scheduled for another class at this time.');
                    }
                }
            }
            $timetable = $this->timetable->create([
                ...$request->all(),
                'type' => (!empty($request->subject_id)) ? "Lecture" : "Break"
            ]);
            $sessionYear = $this->cache->getDefaultSessionYear();
            $semester = $this->cache->getDefaultSemesterData();
            if ($semester) {
                $this->sessionYearsTrackingsService->storeSessionYearsTracking('App\Models\Timetable', $timetable->id, Auth::user()->id, $sessionYear->id, Auth::user()->school_id, $semester->id);
            } else {
                $this->sessionYearsTrackingsService->storeSessionYearsTracking('App\Models\Timetable', $timetable->id, Auth::user()->id, $sessionYear->id, Auth::user()->school_id, null);
            }
            ResponseService::successResponse('Data Stored Successfully', $timetable);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function edit($classSectionID)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenRedirect('timetable-edit');
        $currentSemester = $this->cache->getDefaultSemesterData();
        $classSection = $this->classSection->findById($classSectionID, ['*'], ['class', 'class.stream', 'section', 'medium']);

        $subjectTeachers = $this->subjectTeacher->builder()
            ->with([
                'subject:id,name,type,bg_color',
                'teacher:id,first_name,last_name',
                'class_subject'
            ])
            ->whereHas('class_section', function ($q) use ($classSectionID) {
                $q->where('id', $classSectionID);
            })
            ->whereHas('class_subject', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->orderBy('subject_id', 'ASC')
            ->CurrentSemesterData()
            ->get();

        $subjectWithoutTeacherAssigned = $this->subject->builder()
            ->with([
                'class_subjects' => function ($query) use ($classSection) {
                    $query->where('class_id', $classSection->class_id)
                        ->CurrentSemesterData();
                }
            ])
            ->whereHas('class_subjects', function ($q) use ($classSection) {
                $q->where('class_id', $classSection->class_id)
                    ->CurrentSemesterData();
            })
            ->select(['id', 'name', 'type', 'bg_color'])
            ->whereNotIn('id', $subjectTeachers->pluck('subject_id'))
            ->get();

        $timetables = $this->timetable->builder()
            ->where('class_section_id', $classSectionID)
            ->with([
                'teacher:users.id,first_name,last_name',
                'subject:id,name,type,bg_color',
                'subject.class_subjects',
                'subject_teacher.class_subject'
            ])
            ->CurrentSemesterData()
            ->get();

        // Get Timetable Settings Data with safe defaults
        $timetableSettingsData = $this->schoolSettings->getBulkData([
            'timetable_start_time',
            'timetable_end_time',
            'timetable_duration'
        ]);
        if (empty($timetableSettingsData['timetable_start_time'])) {
            $timetableSettingsData['timetable_start_time'] = '08:30:00';
        }
        if (empty($timetableSettingsData['timetable_end_time'])) {
            $timetableSettingsData['timetable_end_time'] = '16:30:00';
        }
        if (empty($timetableSettingsData['timetable_duration'])) {
            $timetableSettingsData['timetable_duration'] = '00:45:00';
        }

        $allTeachers = $this->user->builder()->role('Teacher')
            ->with([
                'subject_teachers.subject:id,name,type',
                'class_teacher.class_section',
                'staff:id,user_id,qualification'
            ])
            ->get(['id', 'first_name', 'last_name']);

        $classTeacherRecord = ClassTeacher::where('class_section_id', $classSectionID)
            ->with('teacher:id,first_name,last_name')
            ->first();
        $classTeacherId = $classTeacherRecord ? $classTeacherRecord->teacher_id : null;
        $classTeacherName = $classTeacherRecord && $classTeacherRecord->teacher ? ($classTeacherRecord->teacher->first_name . ' ' . $classTeacherRecord->teacher->last_name) : null;

        $allClassSubjects = ClassSubject::where('class_id', $classSection->class_id)
            ->with(['subject:id,name,type,bg_color'])
            ->whereNull('deleted_at')
            ->CurrentSemesterData()
            ->get();

        $schoolId = Auth::user()->school_id ?? $classSection->school_id;
        $templates = $this->planningService->getTemplates($schoolId);
        $resources = $this->planningService->getResources($schoolId);
        $requirements = $this->planningService->getRequirements($classSectionID, $currentSemester?->id);

        return view('timetable.edit', compact(
            'subjectTeachers',
            'subjectWithoutTeacherAssigned',
            'classSection',
            'timetables',
            'timetableSettingsData',
            'currentSemester',
            'allTeachers',
            'classTeacherRecord',
            'classTeacherId',
            'classTeacherName',
            'allClassSubjects',
            'templates',
            'resources',
            'requirements'
        ));
    }

    public function update(Request $request, $id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenRedirect(['timetable-edit']);
        $request->validate([
            'start_time' => 'required',
            'end_time' => 'required',
            'day' => 'required',
        ]);
        $start_time = $request->start_time;
        $end_time = $request->end_time;
        $start_time = Carbon::createFromFormat('H:i:s', $start_time)->format('H:i:s');
        $end_time = Carbon::createFromFormat('H:i:s', $end_time)->format('H:i:s');

        $schoolSettings = $this->cache->getSchoolSettings();
        $startTimeSetting = !empty($schoolSettings['timetable_start_time']) ? $schoolSettings['timetable_start_time'] : '08:00:00';
        $endTimeSetting = !empty($schoolSettings['timetable_end_time']) ? $schoolSettings['timetable_end_time'] : '18:00:00';
        $timetable_start_time = Carbon::parse($startTimeSetting)->format('H:i:s');
        $timetable_end_time = Carbon::parse($endTimeSetting)->format('H:i:s');
        if ($timetable_start_time >= $timetable_end_time) {
            $timetable_start_time = '08:00:00';
            $timetable_end_time = '18:00:00';
        }

        try {
            if ($timetable_start_time <= $start_time && $timetable_end_time >= $end_time) {
                $this->timetable->updateOrCreate(['id' => $id,], $request->all());
                ResponseService::successResponse('Data Stored Successfully');
            } else {
                ResponseService::errorResponse('Please select a valid time');
            }
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }


    public function show(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenRedirect('timetable-list');
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');

        $schoolSettings = $this->cache->getSchoolSettings([
            'timetable_start_time',
            'timetable_end_time',
            'timetable_duration'
        ]);

        $timetableWith = [
            'subject:id,name,type,bg_color',
            'subject_teacher.teacher:id,first_name,last_name'
        ];

        $sql = $this->classSection->builder()->with([
            'class:id,name,stream_id',
            'class.stream',
            'section:id,name',
            'medium:id,name',
            'timetable' => function ($query) use ($timetableWith) {
                $query->CurrentSemesterData()->with($timetableWith);
            }
        ]);


        if (!empty($request->search)) {
            $search = $request->search;
            $sql->where(function ($query) use ($search) {
                $query->orWhereHas('section', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%$search%");
                })->orWhereHas('medium', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%$search%");
                })->orWhereHas('class', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%$search%");
                })->orWhereHas('class.stream', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%$search%");
                });
            });
        }
        if (!empty($request->medium_id)) {
            $sql = $sql->where('medium_id', $request->medium_id);
        }

        if (!empty($request->class_section_id)) {
            $sql = $sql->where('id', $request->class_section_id);
        }

        if (!empty($request->class_id)) {
            $sql = $sql->where('class_id', $request->class_id);
        }

        if (!empty($request->section_id)) {
            $sql = $sql->where('section_id', $request->section_id);
        }

        if (!empty($request->teacher_id)) {
            $sql = $sql->whereHas('class_teachers', function ($q) use ($request) {
                $q->where('teacher_id', $request->teacher_id);
            });
        }

        if (!empty($request->subject_id)) {
            $sql = $sql->whereHas('subject_teachers', function ($q) use ($request) {
                $q->where('subject_id', $request->subject_id);
            });
        }

        if (!empty($request->class_subject_id)) {
            $sql = $sql->whereHas('subject_teachers.class_subject', function ($q) use ($request) {
                $q->where('id', $request->class_subject_id);
            });
        }

        if (!empty($showDeleted)) {
            $sql = $sql->onlyTrashed();
        }

        $total = $sql->count();
        if ($offset >= $total && $total > 0) {
            $lastPage = floor(($total - 1) / $limit) * $limit; // calculate last page offset
            $offset = $lastPage;
        }
        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;
        foreach ($res as $row) {
            $operate = '<div class="d-inline-flex align-items-center" style="gap: 4px; white-space: nowrap;">'
                . '<a href="' . route('timetable.edit', $row->id) . '?autogen=1" class="btn btn-xs btn-gradient-primary" title="' . trans("Auto-Generate Timetable") . '"><i class="fa fa-magic mr-1"></i>' . trans("Generate") . '</a>'
                . BootstrapTableService::editButton(route('timetable.edit', $row->id), false)
                . BootstrapTableService::button('fa fa-trash', '#', ['delete-class-timetable', 'btn-gradient-danger'], ['title' => trans("Delete Class Timetable"), 'data-id' => $row->id])
                . '</div>';
            $tempRow = $row->toArray();
            $timetable = $row->timetable->groupBy('day')->sortBy('start_time');
            $tempRow['no'] = $no++;
            $tempRow['Monday'] = $timetable['Monday'] ?? [];
            $tempRow['Tuesday'] = $timetable['Tuesday'] ?? [];
            $tempRow['Wednesday'] = $timetable['Wednesday'] ?? [];
            $tempRow['Thursday'] = $timetable['Thursday'] ?? [];
            $tempRow['Friday'] = $timetable['Friday'] ?? [];
            $tempRow['Saturday'] = $timetable['Saturday'] ?? [];
            $tempRow['Sunday'] = $timetable['Sunday'] ?? [];
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function destroy($id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson('timetable-delete');
        try {
            Timetable::find($id)->delete();
            $sessionYear = $this->cache->getDefaultSessionYear();
            $this->sessionYearsTrackingsService->deleteSessionYearsTracking('App\Models\Timetable', $id, Auth::user()->id, $sessionYear->id, Auth::user()->school_id, null);
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function teacherIndex()
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenRedirect('timetable-list');

        // Get Timetable Settings Data
        $timetableSettingsData = $this->schoolSettings->getBulkData([
            'timetable_start_time',
            'timetable_end_time',
            'timetable_duration'
        ]);
        return view('timetable.teacher.index', compact('timetableSettingsData'));
    }

    public function teacherList(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenRedirect('timetable-list');
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $sql = $this->user->builder()->role('Teacher')->with([
            'timetable' => function ($query) {
                $query->CurrentSemesterData()->with('subject:id,name', 'class_section.class', 'class_section.section');
            }
        ]);
        if (!empty($request->search)) {
            $search = $request->search;
            $sql->where(function ($query) use ($search) {
                $query->where('id', 'LIKE', "%$search%")->orwhereRaw("concat(first_name,' ',last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        if (!empty($request->class_id)) {
            $sql->whereHas('timetable.class_section.class', function ($q) use ($request) {
                $q->where('id', $request->class_id);
            });
        }

        if (!empty($request->section_id)) {
            $sql->whereHas('timetable.class_section.section', function ($q) use ($request) {
                $q->where('id', $request->section_id);
            });
        }

        if (!empty($request->subject_id)) {
            $sql->whereHas('timetable.subject', function ($q) use ($request) {
                $q->where('id', $request->subject_id);
            });
        }

        if (!empty($request->teacher_id)) {
            $sql->where('id', $request->teacher_id);
        }

        if (!empty($request->status)) {
            $sql->where('status', $request->status);
        }

        if (!empty($request->role)) {
            $sql->where('role', $request->role);
        }

        if (!empty($request->created_at)) {
            $sql->whereDate('created_at', '=', $request->created_at);
        }

        $total = $sql->count();
        if ($offset >= $total && $total > 0) {
            $lastPage = floor(($total - 1) / $limit) * $limit; // calculate last page offset
            $offset = $lastPage;
        }
        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;
        foreach ($res as $row) {
            $operate = BootstrapTableService::button('fa fa-eye', route('timetable.teacher.show', $row->id), ['btn-gradient-success'], ['title' => "View Timetable"]);
            $tempRow = $row->toArray();
            $timetable = $row->timetable->groupBy('day')->sortBy('start_time');
            $tempRow['no'] = $no++;
            $tempRow['Monday'] = $timetable['Monday'] ?? [];
            $tempRow['Tuesday'] = $timetable['Tuesday'] ?? [];
            $tempRow['Wednesday'] = $timetable['Wednesday'] ?? [];
            $tempRow['Thursday'] = $timetable['Thursday'] ?? [];
            $tempRow['Friday'] = $timetable['Friday'] ?? [];
            $tempRow['Saturday'] = $timetable['Saturday'] ?? [];
            $tempRow['Sunday'] = $timetable['Sunday'] ?? [];
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function teacherShow($teacherID)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        // Teachers open their own timetable from the sidebar; anyone else's needs timetable-list.
        if ((int) $teacherID !== (int) Auth::id()) {
            ResponseService::noPermissionThenRedirect('timetable-list');
        }
        $teacher = $this->user->findById($teacherID, ['id', 'first_name', 'last_name']);
        $timetables = $this->timetable->builder()->whereHas('subject_teacher', function ($q) use ($teacherID) {
            $q->where('teacher_id', $teacherID);
        })->with('subject:id,name,bg_color', 'class_section.class', 'class_section.section', 'class_section.medium', 'period', 'resource:id,name')
            ->CurrentSemesterData()->get();

        $grid = $this->teacherGrid($timetables);

        // Proxy periods this teacher covers this week (date-specific, so outside the grid).
        $weekStart = Carbon::now()->startOfWeek();
        $proxyCover = \App\Models\ProxyTeacherAssignment::with(['timetable', 'classSection.class', 'classSection.section', 'classSection.medium', 'subject', 'absentTeacher'])
            ->where('proxy_teacher_id', $teacherID)
            ->whereBetween('date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
            ->orderBy('date')->get();

        return view('timetable.teacher.view', compact('teacher', 'grid', 'proxyCover'));
    }

    /**
     * Period-by-day grid for the teacher timetable. Rows are the school's bell periods
     * (breaks included) when set up, otherwise the distinct lesson times with the gaps
     * between them shown as breaks. Every lesson sits in the row(s) it overlaps, so cards
     * line up with the bell times instead of drifting on a fixed-slot calendar.
     */
    private function teacherGrid($timetables): array
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        if ($timetables->contains('day', 'Sunday')) {
            $days[] = 'Sunday';
        }

        $hm = fn ($t) => substr((string) $t, 0, 5);
        $periods = TimetablePeriod::owner()->where('is_active', true)->orderBy('start_time')->get()
            ->unique(fn ($p) => $p->start_time . '-' . $p->end_time);

        if ($periods->isNotEmpty()) {
            $rows = $periods->map(fn ($p) => [
                'label'   => $p->name ?: ($p->is_instructional ? 'Period ' . $p->period_number : 'Break'),
                'start'   => $hm($p->start_time),
                'end'     => $hm($p->end_time),
                'is_break'=> !$p->is_instructional,
                'period_id' => $p->id,
            ])->values()->all();
        } else {
            $ranges = $timetables->where('type', 'Lecture')
                ->map(fn ($t) => [$hm($t->start_time), $hm($t->end_time)])
                ->unique(fn ($r) => $r[0] . '-' . $r[1])
                ->sortBy(fn ($r) => $r[0])->values();
            $rows = [];
            $n = 0;
            foreach ($ranges as $r) {
                $last = end($rows);
                if ($last && $last['end'] < $r[0]) {
                    $rows[] = ['label' => 'Break', 'start' => $last['end'], 'end' => $r[0], 'is_break' => true, 'period_id' => null];
                }
                // Overlapping ranges from different classes share one row.
                if ($last && !$last['is_break'] && $r[0] < $last['end']) {
                    $rows[count($rows) - 1]['end'] = max($last['end'], $r[1]);
                    continue;
                }
                $rows[] = ['label' => 'Period ' . (++$n), 'start' => $r[0], 'end' => $r[1], 'is_break' => false, 'period_id' => null];
            }
        }

        $place = function ($lessons, array $rows) use ($hm) {
            $cells = [];
            $unplaced = collect();
            foreach ($lessons as $t) {
                $placed = false;
                foreach ($rows as $i => $row) {
                    $match = $hm($t->start_time) < $row['end'] && $hm($t->end_time) > $row['start'];
                    if ($match && !$row['is_break']) {
                        $cells[$i][$t->day][] = $t;
                        $placed = true;
                    }
                }
                if (!$placed) {
                    $unplaced->push($t);
                }
            }
            return [$cells, $unplaced];
        };

        $lessons = $timetables->where('type', 'Lecture');
        [$cells, $unplaced] = $place($lessons, $rows);
        // A lesson outside every bell period (e.g. made before the periods were set up)
        // still gets a row of its own rather than disappearing.
        if ($unplaced->isNotEmpty()) {
            foreach ($unplaced->map(fn ($t) => [$hm($t->start_time), $hm($t->end_time)])->unique(fn ($r) => $r[0] . '-' . $r[1]) as $r) {
                $rows[] = ['label' => $r[0], 'start' => $r[0], 'end' => $r[1], 'is_break' => false, 'period_id' => null];
            }
            usort($rows, fn ($a, $b) => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);
            [$cells] = $place($lessons, $rows);
        }

        return ['days' => $days, 'rows' => $rows, 'cells' => $cells];
    }

    public function updateTimetableSettings(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenRedirect('timetable-list');
        try {
            DB::beginTransaction();
            $settings = array(
                'timetable_start_time',
                'timetable_end_time',
                'timetable_duration'
            );

            // $timeTableExistsBeforeStartTime = $this->timetable->builder()->where('start_time', '<', date('H:i:s', strtotime($request->time_table_start_time)))->get();
            // if (!empty($timeTableExistsBeforeStartTime->toArray())) {
            //     ResponseService::errorResponse("Updates are prohibited as there are pre-existing lectures scheduled before " . $request->time_table_start_time);
            // }

            // $timeTableExistsAfterEndTime = $this->timetable->builder()->where('end_time', '>', date('H:i:s', strtotime($request->time_table_end_time)))->get();
            // if (!empty($timeTableExistsAfterEndTime->toArray())) {
            //     ResponseService::errorResponse("Updates are prohibited as there are pre-existing lectures scheduled after " . $request->time_table_end_time);
            // }

            $durationMinutes = abs((int)($request->timetable_duration ?? 45));
            if ($durationMinutes <= 0 || $durationMinutes > 300) {
                $durationMinutes = 45;
            }

            $data = array();
            foreach ($settings as $row) {
                $val = $row == 'timetable_duration'
                    ? Carbon::createFromTimestampUTC($durationMinutes * 60)->format('H:i:s')
                    : date("H:i:s", strtotime($request->$row));
                $data[] = [
                    "name" => $row,
                    "data" => $val,
                    "type" => 'time'
                ];
            }
            $this->schoolSettings->upsert($data, ["name"], ["data", "type"]);
            $this->cache->removeSchoolCache(config('constants.CACHE.SCHOOL.SETTINGS'));
            DB::commit();
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Timetable Controller -> updateTimetableSettings");
            ResponseService::errorResponse();
        }
    }

    public function deleteClassTimetable($id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson('timetable-delete');
        try {
            $this->timetable->builder()->where('class_section_id', $id)->delete();
            $sessionYear = $this->cache->getDefaultSessionYear();
            $this->sessionYearsTrackingsService->deleteSessionYearsTracking('App\Models\Timetable', $id, Auth::user()->id, $sessionYear->id, Auth::user()->school_id, null);
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function autoGenerate(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-create', 'timetable-edit']);

        $request->validate([
            'class_section_id' => 'required|numeric',
            'days'             => 'required|array',
            'start_time'       => 'required',
            'period_duration'  => 'required|numeric',
            'total_periods'    => 'required|numeric',
            'subjects'         => 'required|array',
        ]);

        $schoolId = Auth::user()->school_id;
        $classSectionId = (int)$request->class_section_id;
        $currentSemester = $this->cache->getDefaultSemesterData();
        $sessionYear = $this->cache->getDefaultSessionYear();

        try {
            DB::beginTransaction();
            // 1. Sync subjects into SubjectScheduleRequirement and SubjectTeacher
            $classSection = ClassSection::with('class')->findOrFail($classSectionId);
            $schoolId = $schoolId ?? $classSection->school_id;

            foreach ($request->subjects as $sub) {
                $subjectId = $sub['subject_id'] ?? null;
                if (!$subjectId) continue;

                $periods = (int)($sub['periods'] ?? 5);
                $teacherId = !empty($sub['teacher_id']) ? (int)$sub['teacher_id'] : null;
                $altTeacherId = !empty($sub['alt_teacher_id']) ? (int)$sub['alt_teacher_id'] : null;
                $consecutiveAllowed = !empty($sub['consecutive_allowed']);
                $consecutivePeriods = $consecutiveAllowed ? (int)($sub['consecutive_periods'] ?? 2) : null;

                $classSubject = ClassSubject::where('class_id', $classSection->class_id)
                    ->where('subject_id', $subjectId)
                    ->first();

                // Save or update requirement
                SubjectScheduleRequirement::updateOrCreate(
                    [
                        'school_id'        => $schoolId,
                        'class_section_id' => $classSectionId,
                        'subject_id'       => $subjectId,
                        'semester_id'      => $currentSemester?->id,
                    ],
                    [
                        'class_subject_id'             => $classSubject?->id,
                        'weekly_periods'               => $periods,
                        'min_periods_per_day'          => 0,
                        'max_periods_per_day'          => $consecutiveAllowed ? $consecutivePeriods : 1,
                        'allow_consecutive'            => $consecutiveAllowed,
                        'required_consecutive_periods' => $consecutivePeriods,
                        'priority'                     => 1,
                        'is_active'                    => true,
                    ]
                );

                // Assign teacher
                if ($teacherId) {
                    $st = SubjectTeacher::updateOrCreate(
                        [
                            'class_section_id' => $classSectionId,
                            'subject_id'       => $subjectId,
                        ],
                        [
                            'teacher_id'       => $teacherId,
                            'class_subject_id' => $classSubject?->id,
                            'school_id'        => $schoolId,
                        ]
                    );

                    // Assign alternate teacher if provided
                    if ($altTeacherId) {
                        $this->planningService->saveAlternates($st->id, [$altTeacherId], $schoolId);
                    }
                }
            }
            DB::commit();

            // 2. Run Timetable V2 generation
            $genResult = $this->generationService->generate([
                'school_id'         => $schoolId,
                'semester_id'       => $currentSemester?->id,
                'session_year_id'   => $sessionYear?->id,
                'class_section_ids' => [$classSectionId],
                'started_by'        => Auth::id(),
                'preserve_existing' => false,
                'mode'              => 'classes',
            ]);

            if ($genResult['error'] ?? false) {
                return response()->json([
                    'error'     => true,
                    'message'   => $genResult['message'] ?? 'Generation failed',
                    'conflicts' => $genResult['conflicts'] ?? [],
                ], 422);
            }

            // 3. Atomically apply the generation run to live timetable
            $applyResult = $this->applyService->apply($genResult['run_id'], $schoolId, Auth::id());

            return response()->json([
                'error'             => false,
                'message'           => 'Timetable generated and applied successfully!',
                'created_count'     => $applyResult['applied_count'] ?? 0,
                'has_conflicts'     => !empty($genResult['conflicts']),
                'conflicts'         => $genResult['conflicts'] ?? [],
                'generation_run_id' => $genResult['run_id'] ?? null,
                'quality_score'     => $genResult['metrics']['quality_score'] ?? 100,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "TimetableController -> autoGenerate");
            return response()->json([
                'error'   => true,
                'message' => 'An error occurred during auto-generation: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generatePreview(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-create', 'timetable-edit']);

        $schoolId = Auth::user()->school_id;
        $currentSemester = $this->cache->getDefaultSemesterData();
        $sessionYear = $this->cache->getDefaultSessionYear();

        $classSectionIds = [];
        if ($request->filled('class_section_id')) {
            $classSectionIds = [(int)$request->class_section_id];
        } elseif ($request->filled('class_section_ids')) {
            $classSectionIds = array_map('intval', (array)$request->class_section_ids);
        }

        try {
            $params = [
                'school_id'         => $schoolId,
                'template_id'       => $request->filled('template_id') ? (int)$request->template_id : null,
                'semester_id'       => $request->filled('semester_id') ? (int)$request->semester_id : ($currentSemester?->id),
                'session_year_id'   => $sessionYear?->id,
                'class_section_ids' => $classSectionIds,
                'started_by'        => Auth::id(),
                'preserve_existing' => $request->boolean('preserve_existing', true),
                'mode'              => $request->input('mode', 'full'),
            ];

            $result = $this->generationService->generate($params);

            if ($result['error'] ?? false) {
                return response()->json($result, 422);
            }

            return response()->json($result);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> generatePreview");
            return response()->json([
                'error'   => true,
                'message' => 'Generation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function applyGeneration(Request $request, $id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-create', 'timetable-edit']);

        $schoolId = Auth::user()->school_id;
        try {
            $res = $this->applyService->apply((int)$id, $schoolId, Auth::id());
            return response()->json($res);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> applyGeneration");
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function cancelGeneration(Request $request, $id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-create', 'timetable-edit']);

        $schoolId = Auth::user()->school_id;
        try {
            $res = $this->applyService->cancel((int)$id, $schoolId);
            return response()->json($res);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> cancelGeneration");
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function repair(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-create', 'timetable-edit']);

        $schoolId = Auth::user()->school_id;
        try {
            $options = [
                'class_section_id' => $request->filled('class_section_id') ? (int)$request->class_section_id : null,
                'teacher_id'       => $request->filled('teacher_id') ? (int)$request->teacher_id : null,
                'day'              => $request->input('day'),
                'dry_run'          => $request->boolean('dry_run', false),
            ];

            $res = $this->repairService->repair($schoolId, $options);
            return response()->json($res);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> repair");
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function lockSlot(Request $request, $id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-edit']);

        try {
            $slot = $this->planningService->lockSlot((int)$id, Auth::id(), $request->input('reason'));
            return response()->json([
                'error'   => false,
                'message' => 'Slot locked successfully.',
                'slot'    => $slot
            ]);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> lockSlot");
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function unlockSlot(Request $request, $id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-edit']);

        try {
            $slot = $this->planningService->unlockSlot((int)$id);
            return response()->json([
                'error'   => false,
                'message' => 'Slot unlocked successfully.',
                'slot'    => $slot
            ]);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> unlockSlot");
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function getTemplates()
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson('timetable-list');

        $schoolId = Auth::user()->school_id;
        $templates = $this->planningService->getTemplates($schoolId);
        return response()->json(['error' => false, 'data' => $templates]);
    }

    public function createTemplate(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-create']);

        $request->validate([
            'name'         => 'required|string|max:191',
            'working_days' => 'nullable|array',
            'periods'      => 'nullable|array',
        ]);

        $schoolId = Auth::user()->school_id;
        try {
            $template = $this->planningService->createTemplate($schoolId, $request->all(), Auth::id());
            return response()->json([
                'error'   => false,
                'message' => 'Template created successfully.',
                'data'    => $template
            ]);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> createTemplate");
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function updateTemplate(Request $request, $id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-edit']);

        $schoolId = Auth::user()->school_id;
        try {
            $template = $this->planningService->updateTemplate((int)$id, $request->all(), Auth::id());
            return response()->json([
                'error'   => false,
                'message' => 'Template updated successfully.',
                'data'    => $template
            ]);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> updateTemplate");
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function deleteTemplate(Request $request, $id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-delete']);

        try {
            $this->planningService->deleteTemplate((int)$id);
            return response()->json([
                'error'   => false,
                'message' => 'Template deleted successfully.'
            ]);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> deleteTemplate");
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function setDefaultTemplate(Request $request, $id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-edit']);

        $schoolId = Auth::user()->school_id;
        try {
            $template = $this->planningService->setDefaultTemplate((int)$id, $schoolId);
            return response()->json([
                'error'   => false,
                'message' => 'Default template updated.',
                'data'    => $template
            ]);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> setDefaultTemplate");
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function getResources()
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson('timetable-list');

        $schoolId = Auth::user()->school_id;
        $resources = $this->planningService->getResources($schoolId);
        return response()->json(['error' => false, 'data' => $resources]);
    }

    public function createResource(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-create']);

        $request->validate([
            'name'     => 'required|string|max:191',
            'type'     => 'nullable|string',
            'block'    => 'nullable|string|max:64',
            'capacity' => 'nullable|numeric',
        ]);

        $schoolId = Auth::user()->school_id;
        try {
            $resource = $this->planningService->createResource($schoolId, $request->all());
            return response()->json([
                'error'   => false,
                'message' => 'Resource created successfully.',
                'data'    => $resource
            ]);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> createResource");
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function updateResource(Request $request, $id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-edit']);

        $request->validate([
            'name'     => 'nullable|string|max:191',
            'type'     => 'nullable|string',
            'block'    => 'nullable|string|max:64',
            'capacity' => 'nullable|numeric',
        ]);

        try {
            $resource = $this->planningService->updateResource((int)$id, $request->all());
            return response()->json([
                'error'   => false,
                'message' => 'Resource updated successfully.',
                'data'    => $resource
            ]);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> updateResource");
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function deleteResource(Request $request, $id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-delete']);

        try {
            $this->planningService->deleteResource((int)$id);
            return response()->json([
                'error'   => false,
                'message' => 'Resource deleted successfully.'
            ]);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> deleteResource");
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function getTeacherAvailability($teacher_id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson('timetable-list');

        $schoolId = Auth::user()->school_id;
        $data = $this->planningService->getTeacherAvailability((int)$teacher_id, $schoolId);
        return response()->json(['error' => false, 'data' => $data]);
    }

    public function saveTeacherAvailability(Request $request, $teacher_id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-edit']);

        $schoolId = Auth::user()->school_id;
        try {
            $this->planningService->saveTeacherAvailability((int)$teacher_id, $request->input('grid', []), $schoolId);
            return response()->json([
                'error'   => false,
                'message' => 'Teacher availability saved successfully.'
            ]);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> saveTeacherAvailability");
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function getClassAvailability($class_section_id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson('timetable-list');

        $schoolId = Auth::user()->school_id;
        $data = $this->planningService->getClassAvailability((int)$class_section_id, $schoolId);
        return response()->json(['error' => false, 'data' => $data]);
    }

    public function saveClassAvailability(Request $request, $class_section_id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-edit']);

        $schoolId = Auth::user()->school_id;
        try {
            $this->planningService->saveClassAvailability((int)$class_section_id, $request->input('grid', []), $schoolId);
            return response()->json([
                'error'   => false,
                'message' => 'Class availability saved successfully.'
            ]);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> saveClassAvailability");
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function getResourceAvailability($resource_id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson('timetable-list');

        $schoolId = Auth::user()->school_id;
        $data = $this->planningService->getResourceAvailability((int)$resource_id, $schoolId);
        return response()->json(['error' => false, 'data' => $data]);
    }

    public function saveResourceAvailability(Request $request, $resource_id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-edit']);

        $schoolId = Auth::user()->school_id;
        try {
            $this->planningService->saveResourceAvailability((int)$resource_id, $request->input('grid', []), $schoolId);
            return response()->json([
                'error'   => false,
                'message' => 'Resource availability saved successfully.'
            ]);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> saveResourceAvailability");
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function getRequirements($class_section_id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson('timetable-list');

        $currentSemester = $this->cache->getDefaultSemesterData();
        $requirements = $this->planningService->getRequirements((int)$class_section_id, $currentSemester?->id);
        return response()->json(['error' => false, 'data' => $requirements]);
    }

    public function saveRequirements(Request $request, $class_section_id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-edit']);

        $schoolId = Auth::user()->school_id;
        $currentSemester = $this->cache->getDefaultSemesterData();
        try {
            $requirementsData = $request->input('requirements', []);
            $saved = $this->planningService->saveBulkRequirements((int)$class_section_id, $requirementsData, $schoolId, $currentSemester?->id);
            return response()->json([
                'error'   => false,
                'message' => 'Requirements saved successfully.',
                'data'    => $saved
            ]);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> saveRequirements");
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function syncTeachers(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-edit']);

        $request->validate([
            'class_section_id' => 'required|numeric',
            'assignments'      => 'required|array',
        ]);

        try {
            DB::beginTransaction();
            $classSectionId = $request->class_section_id;
            $classSection = $this->classSection->findById($classSectionId, ['*'], ['class']);
            $schoolId = Auth::user()->school_id ?? $classSection->school_id;

            foreach ($request->assignments as $item) {
                $subjectId = $item['subject_id'] ?? null;
                $teacherId = $item['teacher_id'] ?? null;

                if ($subjectId && $teacherId) {
                    $classSubject = \App\Models\ClassSubject::where('class_id', $classSection->class_id)
                        ->where('subject_id', $subjectId)
                        ->first();

                    \App\Models\SubjectTeacher::updateOrCreate(
                        [
                            'class_section_id' => $classSectionId,
                            'subject_id'       => $subjectId,
                        ],
                        [
                            'teacher_id'       => $teacherId,
                            'class_subject_id' => $classSubject?->id,
                            'school_id'        => $schoolId,
                        ]
                    );
                }
            }

            DB::commit();
            return response()->json([
                'error'   => false,
                'message' => 'Teacher-Subject assignments synchronized successfully!'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "TimetableController -> syncTeachers");
            return response()->json([
                'error'   => true,
                'message' => 'Failed to sync assignments: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getMatrix($classSectionID)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson('timetable-list');

        $schoolId = Auth::user()->school_id;
        $timetables = $this->timetable->builder()
            ->where('class_section_id', $classSectionID)
            ->with([
                'teacher:users.id,first_name,last_name',
                'subject:id,name,type,bg_color',
                'subject_teacher.teacher:id,first_name,last_name',
                'resource:id,name,type,block',
                'period'
            ])
            ->orderBy('start_time', 'ASC')
            ->CurrentSemesterData()
            ->get();

        $otherTimetables = $this->timetable->builder()
            ->where('school_id', $schoolId)
            ->where('class_section_id', '!=', $classSectionID)
            ->with('subject_teacher.teacher:id,first_name,last_name')
            ->CurrentSemesterData()
            ->get();

        $daysOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $activeDays = [];
        $timeSlots = [];

        // 1. Populate canonical periods from default bell schedule template first
        $defaultTemplate = $this->planningService->getDefaultTemplate($schoolId);
        if ($defaultTemplate && $defaultTemplate->periods->count()) {
            $activeDays = $defaultTemplate->working_days ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
            $pNum = 1;
            foreach ($defaultTemplate->periods->sortBy('start_time') as $period) {
                $timeKey = $period->start_time . ' - ' . $period->end_time;
                $pTitle = $period->name;
                if (empty($pTitle)) {
                    $pTitle = ($period->type === 'Break' || !$period->is_instructional) ? 'Break' : ('Period ' . ($period->period_number ?: $pNum++));
                }
                $timeSlots[$timeKey] = [
                    'start_time' => $period->start_time,
                    'end_time'   => $period->end_time,
                    'period_no'  => $period->period_number,
                    'period_id'  => $period->id,
                    'type'       => $period->type,
                    'title'      => $pTitle,
                ];
            }
        }

        // 2. Incorporate any slots already scheduled in this class
        $extraNum = count($timeSlots) + 1;
        foreach ($timetables as $slot) {
            if (!in_array($slot->day, $activeDays)) {
                $activeDays[] = $slot->day;
            }
            $timeKey = $slot->start_time . ' - ' . $slot->end_time;
            if (!isset($timeSlots[$timeKey])) {
                $pTitle = $slot->period?->name;
                if (empty($pTitle)) {
                    $pTitle = ($slot->type === 'Break') ? 'Break' : ('Period ' . ($slot->period_no ?: $extraNum++));
                }
                $timeSlots[$timeKey] = [
                    'start_time' => $slot->start_time,
                    'end_time'   => $slot->end_time,
                    'period_no'  => $slot->period_no ?? null,
                    'period_id'  => $slot->period_id ?? null,
                    'type'       => $slot->type,
                    'title'      => $pTitle,
                ];
            }
        }

        if (empty($activeDays)) {
            $activeDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        } else {
            usort($activeDays, function($a, $b) use ($daysOrder) {
                return array_search($a, $daysOrder) <=> array_search($b, $daysOrder);
            });
        }

        usort($timeSlots, function($a, $b) {
            return strcmp($a['start_time'], $b['start_time']);
        });

        $matrix = [];
        foreach ($timetables as $t) {
            $primaryTeacherId = $t->subject_teacher?->teacher_id ?? $t->teacher?->id;
            $primaryTeacherName = $t->primary_teacher_name ?? $t->teacher?->full_name ?? ($t->subject_teacher?->teacher ? $t->subject_teacher->teacher->first_name . ' ' . $t->subject_teacher->teacher->last_name : null);
            $altTeacherName = $t->alternate_teacher_name;

            // Real-time conflict checking
            $conflict = null;
            if ($primaryTeacherId && $t->type === 'Lecture') {
                $clash = $otherTimetables->first(function($other) use ($t, $primaryTeacherId) {
                    if ($other->day !== $t->day) return false;
                    $otherTeacherId = $other->subject_teacher?->teacher_id;
                    if ($otherTeacherId != $primaryTeacherId) return false;
                    return ($other->start_time < $t->end_time && $other->end_time > $t->start_time);
                });
                if ($clash) {
                    $otherClass = \App\Models\ClassSection::find($clash->class_section_id);
                    $className = $otherClass ? $otherClass->full_name : 'another class';
                    $conflict = "Conflict: {$primaryTeacherName} is scheduled in {$className} ({$clash->start_time} - {$clash->end_time})!";
                }
            }

            $matrix[$t->day][$t->start_time] = [
                'id'                   => $t->id,
                'type'                 => $t->type,
                'subject_id'           => $t->subject_id,
                'subject_name'         => $t->subject?->name ?? ($t->type === 'Break' ? ($t->title ?? 'Break') : 'Lecture'),
                'bg_color'             => $t->subject?->bg_color ?? '#4F46E5',
                'start_time'           => $t->start_time,
                'end_time'             => $t->end_time,
                'primary_teacher_name' => $primaryTeacherName,
                'primary_teacher_id'   => $primaryTeacherId,
                'alt_teacher_name'     => $altTeacherName,
                'alt_teacher_id'       => $t->alt_teacher_id,
                'period_no'            => $t->period_no,
                'period_id'            => $t->period_id,
                'resource_id'          => $t->resource_id,
                'resource_name'        => $t->resource?->name,
                'resource_block'       => $t->resource?->block,
                'is_locked'            => (bool)$t->is_locked,
                'locked_by'            => $t->locked_by,
                'lock_reason'          => $t->lock_reason,
                'conflict'             => $conflict,
                'note'                 => $t->note,
            ];
        }

        $classSection = \App\Models\ClassSection::with(['class', 'section', 'teachers'])->find($classSectionID);

        $palette = [];
        $classSubjects = \App\Models\ClassSubject::where('class_id', $classSection?->class_id)
            ->with(['subject'])
            ->get();

        $scheduleReqs = SubjectScheduleRequirement::where('school_id', $schoolId)
            ->where('class_section_id', $classSectionID)
            ->where('is_active', true)
            ->with(['resource'])
            ->get()
            ->keyBy('subject_id');

        $assignedCountBySubject = $timetables->where('type', 'Lecture')->groupBy('subject_id')->map->count();

        foreach ($classSubjects as $cs) {
            $sub = $cs->subject;
            if (!$sub) continue;
            $req = $scheduleReqs->get($sub->id);
            $reqWeekly = $req ? (int)$req->weekly_periods : 5;
            $assigned = $assignedCountBySubject->get($sub->id, 0);

            $st = SubjectTeacher::where('class_section_id', $classSectionID)
                ->where('subject_id', $sub->id)
                ->with('teacher')
                ->first();

            $prefResource = $req?->resource;

            $palette[] = [
                'subject_id'               => $sub->id,
                'subject_name'             => $sub->name,
                'subject_type'             => $sub->type ?? 'Theory',
                'bg_color'                 => $sub->bg_color ?: '#4F46E5',
                'teacher_id'               => $st?->teacher_id,
                'teacher_name'             => $st?->teacher ? trim($st->teacher->first_name . ' ' . $st->teacher->last_name) : null,
                'required_weekly'          => $reqWeekly,
                'assigned_periods'         => $assigned,
                'remaining_periods'        => max(0, $reqWeekly - $assigned),
                'preferred_resource_id'    => $prefResource?->id,
                'preferred_resource_name'  => $prefResource?->name,
                'preferred_resource_block' => $prefResource?->block,
            ];
        }

        $activeResources = \App\Models\TimetableResource::where('school_id', $schoolId)
            ->where('is_active', true)
            ->select(['id', 'name', 'type', 'block', 'capacity'])
            ->get();

        $availableBlocks = collect(\App\Models\ClassSection::where('school_id', $schoolId)->pluck('block'))
            ->merge($activeResources->pluck('block'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $blockSettings = $this->schoolSettings->getBulkData(['timetable_prioritize_same_block']);
        $prioritizeSameBlock = isset($blockSettings['timetable_prioritize_same_block']) ? (bool)$blockSettings['timetable_prioritize_same_block'] : true;

        return response()->json([
            'error'                 => false,
            'days'                  => $activeDays,
            'time_slots'            => array_values($timeSlots),
            'matrix'                => $matrix,
            'class_section'         => $classSection ? [
                'id'                 => $classSection->id,
                'name'               => $classSection->name,
                'full_name'          => $classSection->full_name,
                'block'              => $classSection->block,
                'class_teacher_name' => $classSection->teachers->first() ? trim($classSection->teachers->first()->first_name . ' ' . $classSection->teachers->first()->last_name) : null,
            ] : null,
            'subject_palette'       => $palette,
            'resources'             => $activeResources,
            'available_blocks'      => $availableBlocks,
            'prioritize_same_block' => $prioritizeSameBlock,
        ]);
    }

    public function quickUpdate(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson(['timetable-edit']);

        $request->validate([
            'id'             => 'required|numeric',
            'subject_id'     => 'nullable|numeric',
            'teacher_id'     => 'nullable|numeric',
            'alt_teacher_id' => 'nullable|numeric',
            'resource_id'    => 'nullable|numeric',
            'is_locked'      => 'nullable|boolean',
            'lock_reason'    => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();
            $timetable = Timetable::findOrFail($request->id);
            $schoolId = Auth::user()->school_id ?? $timetable->school_id;

            // If slot is locked and user isn't toggling lock, reject changes
            if ($timetable->is_locked && !$request->has('is_locked') && !$request->has('unlock')) {
                return response()->json([
                    'error'   => true,
                    'message' => 'This slot is locked (' . ($timetable->lock_reason ?? 'locked by admin') . '). Unlock it first to make edits.'
                ], 422);
            }

            if ($request->has('is_locked')) {
                $timetable->is_locked = (bool)$request->is_locked;
                $timetable->locked_by = $timetable->is_locked ? Auth::id() : null;
                $timetable->locked_at = $timetable->is_locked ? Carbon::now() : null;
                $timetable->lock_reason = $timetable->is_locked ? ($request->lock_reason ?? 'Manually locked') : null;
            }

            if ($request->filled('subject_id')) {
                $timetable->subject_id = $request->subject_id;
            }

            if ($request->has('resource_id')) {
                $timetable->resource_id = $request->resource_id ? (int)$request->resource_id : null;
            }

            $targetTeacherId = $request->filled('teacher_id') ? (int)$request->teacher_id : ($timetable->subject_teacher?->teacher_id ?? $timetable->teacher_id);

            // Constraint validation via TimetableValidationService
            $violations = $this->validationService->validateSingleSlot(
                $schoolId,
                $timetable->class_section_id,
                $timetable->day,
                $timetable->start_time,
                $timetable->end_time,
                $targetTeacherId,
                $timetable->resource_id,
                $timetable->id,
                $timetable->period_id
            );

            if (!empty($violations)) {
                DB::rollBack();
                return response()->json([
                    'error'      => true,
                    'message'    => $violations[0]['message'],
                    'violations' => $violations
                ], 422);
            }

            if ($request->filled('teacher_id')) {
                $classSubject = \App\Models\ClassSubject::where('class_id', $timetable->class_section?->class_id)
                    ->where('subject_id', $timetable->subject_id)
                    ->first();

                $subjectTeacher = SubjectTeacher::firstOrCreate([
                    'class_section_id' => $timetable->class_section_id,
                    'subject_id'       => $timetable->subject_id,
                    'teacher_id'       => $request->teacher_id,
                ], [
                    'class_subject_id' => $classSubject?->id,
                    'school_id'        => $schoolId,
                ]);
                $timetable->subject_teacher_id = $subjectTeacher->id;
            }

            $currentMeta = is_array($timetable->meta) ? $timetable->meta : [];
            if ($request->filled('teacher_id')) {
                $primaryTeacher = User::find($request->teacher_id);
                $currentMeta['primary_teacher_name'] = $primaryTeacher ? $primaryTeacher->first_name . ' ' . $primaryTeacher->last_name : null;
            }
            if ($request->has('alt_teacher_id')) {
                $altTeacher = $request->alt_teacher_id ? User::find($request->alt_teacher_id) : null;
                $currentMeta['alt_teacher_id'] = $request->alt_teacher_id;
                $currentMeta['alt_teacher_name'] = $altTeacher ? $altTeacher->first_name . ' ' . $altTeacher->last_name : null;
            }

            $timetable->note = json_encode($currentMeta);
            $timetable->save();

            DB::commit();
            return response()->json([
                'error'   => false,
                'message' => 'Timetable slot updated successfully!',
                'data'    => $timetable->fresh(['subject', 'resource', 'subject_teacher.teacher'])
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "TimetableController -> quickUpdate");
            return response()->json([
                'error'   => true,
                'message' => 'Update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * School-wide teacher matrix: one row per teacher, one column per day/period, so a
     * principal can see who is teaching where and move lessons without clashes.
     */
    public function teacherMatrix()
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenRedirect('timetable-list');

        return view('timetable.teacher_matrix', [
            'canEdit' => Auth::user()->can('timetable-edit'),
        ]);
    }

    public function teacherMatrixData()
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson('timetable-list');

        $schoolId = Auth::user()->school_id;
        $template = $this->planningService->getDefaultTemplate($schoolId);

        $entries = $this->timetable->builder()
            ->where('type', 'Lecture')
            ->with([
                'subject:id,name,bg_color',
                'subject_teacher:id,teacher_id',
                'class_section.class',
                'class_section.section',
                'class_section.medium',
                'resource:id,name',
            ])
            ->CurrentSemesterData()
            ->get();

        // Columns: the default bell schedule, plus any legacy slot times not on it.
        $slots = [];
        foreach ($template->periods as $period) {
            $key = $period->start_time . '-' . $period->end_time;
            $slots[$key] = [
                'key'        => $key,
                'start_time' => $period->start_time,
                'end_time'   => $period->end_time,
                'period_id'  => $period->id,
                'label'      => $period->name ?: ('P' . $period->period_number),
                'is_break'   => !$period->is_instructional,
            ];
        }
        foreach ($entries as $entry) {
            $key = $entry->start_time . '-' . $entry->end_time;
            if (!isset($slots[$key])) {
                $slots[$key] = [
                    'key'        => $key,
                    'start_time' => $entry->start_time,
                    'end_time'   => $entry->end_time,
                    'period_id'  => $entry->period_id,
                    'label'      => substr($entry->start_time, 0, 5),
                    'is_break'   => false,
                ];
            }
        }
        $slots = array_values($slots);
        usort($slots, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));

        $daysOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $days = array_values(array_unique(array_merge($template->working_days ?? [], $entries->pluck('day')->all())));
        usort($days, fn($a, $b) => array_search($a, $daysOrder) <=> array_search($b, $daysOrder));

        $teachers = $this->user->builder()->role('Teacher')->orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        $rows = $entries->map(fn($t) => [
            'id'               => $t->id,
            'teacher_id'       => $t->subject_teacher?->teacher_id,
            'class_section_id' => $t->class_section_id,
            'class_name'       => $t->class_section?->full_name,
            'subject_name'     => $t->subject?->name,
            'bg_color'         => $t->subject?->bg_color ?: '#4F46E5',
            'day'              => $t->day,
            'start_time'       => $t->start_time,
            'end_time'         => $t->end_time,
            'period_id'        => $t->period_id,
            'resource_name'    => $t->resource?->name,
            'is_locked'        => (bool) $t->is_locked,
        ])->values();

        return response()->json([
            'error'      => false,
            'days'       => $days,
            'slots'      => $slots,
            'teachers'   => $teachers->map(fn($u) => ['id' => $u->id, 'name' => trim($u->first_name . ' ' . $u->last_name)])->values(),
            'entries'    => $rows,
            'unassigned' => $rows->whereNull('teacher_id')->count(),
        ]);
    }

    /**
     * Move a lesson to another day / period. If the class already has a lesson there,
     * the two are swapped, provided both teachers (and rooms) are free at their new times.
     */
    public function moveSlot(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson('timetable-edit');

        $request->validate([
            'id'         => 'required|integer',
            'day'        => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time' => 'required|date_format:H:i:s',
            'end_time'   => 'required|date_format:H:i:s|after:start_time',
            'period_id'  => 'nullable|integer',
            'teacher_id' => 'nullable|integer',
        ]);

        $schoolId = Auth::user()->school_id;
        $day = $request->day;
        $start = $request->start_time;
        $end = $request->end_time;
        $periodId = $request->filled('period_id') ? (int) $request->period_id : null;

        try {
            DB::beginTransaction();
            $slot = $this->timetable->builder()->with('subject_teacher')->lockForUpdate()->findOrFail((int) $request->id);

            if ($slot->type !== 'Lecture') {
                DB::rollBack();
                return response()->json(['error' => true, 'message' => 'Only lessons can be moved.'], 422);
            }
            if ($slot->is_locked) {
                DB::rollBack();
                return response()->json(['error' => true, 'message' => 'This lesson is locked. Unlock it first.'], 422);
            }

            $stRecord = null;
            $activeTeacherId = $slot->subject_teacher?->teacher_id;
            if ($request->filled('teacher_id')) {
                $requestedTeacherId = (int) $request->teacher_id;
                if ($requestedTeacherId !== (int) $activeTeacherId) {
                    $classSection = ClassSection::find($slot->class_section_id);
                    $classSubject = \App\Models\ClassSubject::where('class_id', $classSection?->class_id)
                        ->where('subject_id', $slot->subject_id)
                        ->first();
                    $stRecord = SubjectTeacher::firstOrCreate([
                        'class_section_id' => $slot->class_section_id,
                        'subject_id'       => $slot->subject_id,
                        'teacher_id'       => $requestedTeacherId,
                    ], [
                        'class_subject_id' => $classSubject?->id,
                        'school_id'        => $schoolId,
                    ]);
                    $activeTeacherId = $requestedTeacherId;
                }
            }

            if ($slot->day === $day && $slot->start_time === $start && $slot->end_time === $end && !$stRecord) {
                DB::rollBack();
                return response()->json(['error' => false, 'message' => 'Nothing to change.']);
            }

            // Whatever this class already has at the target time.
            $occupants = $this->timetable->builder()
                ->with('subject_teacher')
                ->where('class_section_id', $slot->class_section_id)
                ->where('day', $day)
                ->where('id', '!=', $slot->id)
                ->where('start_time', '<', $end)
                ->where('end_time', '>', $start)
                ->CurrentSemesterData()
                ->lockForUpdate()
                ->get();

            $swap = null;
            if ($occupants->count() > 1) {
                DB::rollBack();
                return response()->json(['error' => true, 'message' => 'The target time overlaps more than one lesson of this class.'], 422);
            }
            if ($occupants->count() === 1) {
                $swap = $occupants->first();
                if ($swap->type !== 'Lecture') {
                    DB::rollBack();
                    return response()->json(['error' => true, 'message' => 'The target period is a break for this class.'], 422);
                }
                if ($swap->is_locked) {
                    DB::rollBack();
                    return response()->json(['error' => true, 'message' => 'The class lesson at the target time is locked.'], 422);
                }
                if ($swap->start_time !== $start || $swap->end_time !== $end
                    || $this->minutesBetween($slot->start_time, $slot->end_time) !== $this->minutesBetween($start, $end)) {
                    DB::rollBack();
                    return response()->json(['error' => true, 'message' => 'Lessons of different lengths cannot be swapped.'], 422);
                }
            }

            $excludeIds = array_values(array_filter([$slot->id, $swap?->id]));
            $violations = $this->validationService->validateSingleSlot(
                $schoolId, $slot->class_section_id, $day, $start, $end,
                $activeTeacherId, $slot->resource_id, $excludeIds, $periodId
            );
            if ($swap) {
                $violations = array_merge($violations, $this->validationService->validateSingleSlot(
                    $schoolId, $swap->class_section_id, $slot->day, $slot->start_time, $slot->end_time,
                    $swap->subject_teacher?->teacher_id, $swap->resource_id, $excludeIds, $slot->period_id
                ));
            }

            if (!empty($violations)) {
                DB::rollBack();
                $teacherConflict = collect($violations)->firstWhere('code', 'TEACHER_CONFLICT');
                if ($teacherConflict && !$request->boolean('force')) {
                    $conflictPayload = $this->calculateTeacherConflictOptions(
                        $schoolId,
                        $activeTeacherId,
                        $slot->subject_id,
                        $slot->class_section_id,
                        $day,
                        $start,
                        $end,
                        $slot->id
                    );
                    return response()->json(array_merge([
                        'error'                => true,
                        'has_teacher_conflict' => true,
                        'message'              => $violations[0]['message'],
                        'violations'           => $violations,
                    ], $conflictPayload), 409);
                }

                if (!$request->boolean('force')) {
                    return response()->json([
                        'error'      => true,
                        'message'    => $violations[0]['message'],
                        'violations' => $violations,
                    ], 422);
                }
            }

            $origin = ['day' => $slot->day, 'start_time' => $slot->start_time, 'end_time' => $slot->end_time, 'period_id' => $slot->period_id];
            $slotUpdate = ['day' => $day, 'start_time' => $start, 'end_time' => $end, 'period_id' => $periodId];
            if ($stRecord) {
                $slotUpdate['subject_teacher_id'] = $stRecord->id;
            }
            $slot->update($slotUpdate);
            $swap?->update($origin);

            DB::commit();
            return response()->json([
                'error'   => false,
                'message' => $swap ? 'Lessons swapped successfully.' : 'Lesson moved successfully.',
                'swapped' => $swap?->id,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "TimetableController -> moveSlot");
            return response()->json(['error' => true, 'message' => 'Move failed.'], 500);
        }
    }

    /**
     * Substitute (proxy) suggestions for a teacher who is away on a date: for each of
     * their lessons that day, teachers who are free, not on leave and not marked
     * unavailable, ranked by subject fit, class familiarity and lightest load that day.
     */
    public function substitutes(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson('timetable-list');

        $request->validate([
            'teacher_id' => 'required|integer',
            'date'       => 'required|date',
        ]);

        // Same rules as the teacher attendance cockpit (free, present, not unavailable).
        $date = Carbon::parse($request->date);
        $day = $date->format('l');
        $result = app(\App\Services\Timetable\TeacherScheduleService::class)->proxyPlan((int) $request->teacher_id, $date);

        return response()->json([
            'error'   => false,
            'day'     => $day,
            'date'    => $date->toDateString(),
            'lessons' => $result,
        ]);
    }

    public function placeSlot(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson('timetable-edit');

        $request->validate([
            'class_section_id' => 'required|numeric',
            'day'              => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time'       => 'required',
            'end_time'         => 'required',
            'subject_id'       => 'required|numeric',
            'teacher_id'       => 'nullable|numeric',
            'resource_id'      => 'nullable|numeric',
            'period_id'        => 'nullable|numeric',
            'type'             => 'nullable|string',
        ]);

        $schoolId = Auth::user()->school_id;
        $classSectionId = (int)$request->class_section_id;
        $day = $request->day;
        $startTime = Carbon::parse($request->start_time)->format('H:i:s');
        $endTime = Carbon::parse($request->end_time)->format('H:i:s');
        $subjectId = (int)$request->subject_id;
        $teacherId = $request->filled('teacher_id') ? (int)$request->teacher_id : null;
        $resourceId = $request->filled('resource_id') ? (int)$request->resource_id : null;
        $periodId = $request->filled('period_id') ? (int)$request->period_id : null;
        $slotType = $request->type ?? 'Lecture';

        try {
            DB::beginTransaction();

            $existing = $this->timetable->builder()
                ->where('class_section_id', $classSectionId)
                ->where('day', $day)
                ->where('start_time', $startTime)
                ->where('end_time', $endTime)
                ->first();

            if ($existing && $existing->is_locked) {
                DB::rollBack();
                return response()->json([
                    'error'   => true,
                    'message' => 'This slot is locked (' . ($existing->lock_reason ?? 'locked by admin') . '). Unlock it first.'
                ], 422);
            }

            $subjectTeacherId = null;
            if ($teacherId) {
                $classSection = ClassSection::find($classSectionId);
                $classSubject = \App\Models\ClassSubject::where('class_id', $classSection?->class_id)
                    ->where('subject_id', $subjectId)
                    ->first();

                $subjectTeacher = SubjectTeacher::firstOrCreate([
                    'class_section_id' => $classSectionId,
                    'subject_id'       => $subjectId,
                    'teacher_id'       => $teacherId,
                ], [
                    'class_subject_id' => $classSubject?->id,
                    'school_id'        => $schoolId,
                ]);
                $subjectTeacherId = $subjectTeacher->id;
            }

            $excludeId = $existing ? $existing->id : null;
            $violations = $this->validationService->validateSingleSlot(
                $schoolId,
                $classSectionId,
                $day,
                $startTime,
                $endTime,
                $teacherId,
                $resourceId,
                $excludeId ? [$excludeId] : [],
                $periodId
            );

            if (!empty($violations)) {
                DB::rollBack();
                $teacherConflict = collect($violations)->firstWhere('code', 'TEACHER_CONFLICT');
                if ($teacherConflict && !$request->boolean('force')) {
                    $conflictPayload = $this->calculateTeacherConflictOptions(
                        $schoolId,
                        $teacherId,
                        $subjectId,
                        $classSectionId,
                        $day,
                        $startTime,
                        $endTime,
                        $excludeId
                    );
                    return response()->json(array_merge([
                        'error'                => true,
                        'has_teacher_conflict' => true,
                        'message'              => $violations[0]['message'],
                        'violations'           => $violations,
                    ], $conflictPayload), 409);
                }

                if (!$request->boolean('force')) {
                    return response()->json([
                        'error'      => true,
                        'message'    => $violations[0]['message'],
                        'violations' => $violations
                    ], 422);
                }
            }

            $currentSemester = $this->cache->getDefaultSemesterData();
            $meta = [];
            if ($teacherId) {
                $teacherUser = User::find($teacherId);
                $meta['primary_teacher_name'] = $teacherUser ? trim($teacherUser->first_name . ' ' . $teacherUser->last_name) : null;
            }

            $attributes = [
                'school_id'          => $schoolId,
                'class_section_id'   => $classSectionId,
                'semester_id'        => $currentSemester?->id,
                'subject_id'         => $subjectId,
                'subject_teacher_id' => $subjectTeacherId,
                'day'                => $day,
                'start_time'         => $startTime,
                'end_time'           => $endTime,
                'type'               => $slotType,
                'period_id'          => $periodId,
                'resource_id'        => $resourceId,
                'note'               => json_encode($meta),
            ];

            if ($existing) {
                $existing->update($attributes);
                $timetable = $existing;
            } else {
                $timetable = $this->timetable->create($attributes);
            }

            DB::commit();

            return response()->json([
                'error'   => false,
                'message' => 'Subject assigned successfully.',
                'slot'    => $timetable->fresh(['subject', 'resource', 'subject_teacher.teacher'])
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "TimetableController -> placeSlot");
            return response()->json([
                'error'   => true,
                'message' => 'Failed to place slot: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper to compute detailed collision information and smart alternatives
     * when a teacher collision occurs.
     */
    protected function calculateTeacherConflictOptions(
        int $schoolId,
        ?int $teacherId,
        ?int $subjectId,
        int $classSectionId,
        string $targetDay,
        string $targetStart,
        string $targetEnd,
        $excludeSlotId = null
    ): array {
        if (!$teacherId) {
            return [];
        }

        $teacher = User::find($teacherId);
        $teacherName = $teacher ? trim($teacher->first_name . ' ' . $teacher->last_name) : 'Teacher';

        // 1. Fetch exact conflicting slot in another class
        $clashSlot = $this->timetable->builder()
            ->where('school_id', $schoolId)
            ->where('day', $targetDay)
            ->where('type', 'Lecture')
            ->when($excludeSlotId, fn($q) => $q->where('id', '!=', $excludeSlotId))
            ->whereHas('subject_teacher', fn($q) => $q->where('teacher_id', $teacherId))
            ->where(function ($q) use ($targetStart, $targetEnd) {
                $q->where('start_time', '<', $targetEnd)
                  ->where('end_time', '>', $targetStart);
            })
            ->with(['class_section.class', 'class_section.section', 'class_section.medium', 'subject'])
            ->first();

        $conflictingClass = $clashSlot?->class_section?->full_name ?? ('Class #' . ($clashSlot->class_section_id ?? 'Unknown'));
        $conflictingSubject = $clashSlot?->subject?->name ?? 'Subject';

        // 2. Calculate alternative free slots where BOTH this teacher & this target class section are free
        $template = $this->planningService->getDefaultTemplate($schoolId);
        $workingDays = $template?->working_days ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $periods = $template ? $template->instructional_periods->sortBy('start_time') : collect();

        // All busy slots for teacher across entire school
        $teacherBusy = $this->timetable->builder()
            ->where('school_id', $schoolId)
            ->where('type', 'Lecture')
            ->whereHas('subject_teacher', fn($q) => $q->where('teacher_id', $teacherId))
            ->get(['day', 'start_time', 'end_time']);

        // All busy slots for target class section
        $classBusy = $this->timetable->builder()
            ->where('school_id', $schoolId)
            ->where('class_section_id', $classSectionId)
            ->where('type', 'Lecture')
            ->when($excludeSlotId, fn($q) => $q->where('id', '!=', $excludeSlotId))
            ->get(['day', 'start_time', 'end_time']);

        $freeSlots = [];
        foreach ($workingDays as $d) {
            foreach ($periods as $p) {
                $tOccupied = $teacherBusy->contains(function ($b) use ($d, $p) {
                    return $b->day === $d && $b->start_time < $p->end_time && $b->end_time > $p->start_time;
                });
                if ($tOccupied) {
                    continue;
                }

                $cOccupied = $classBusy->contains(function ($b) use ($d, $p) {
                    return $b->day === $d && $b->start_time < $p->end_time && $b->end_time > $p->start_time;
                });

                $freeSlots[] = [
                    'day'          => $d,
                    'start_time'   => $p->start_time,
                    'end_time'     => $p->end_time,
                    'period_id'    => $p->id,
                    'period_title' => $p->name ?: ('Period ' . $p->period_number),
                    'is_empty'     => !$cOccupied,
                ];

                if (count($freeSlots) >= 8) {
                    break 2;
                }
            }
        }

        // 3. Find other qualified teachers for this subject who are FREE at this targetDay/targetTime
        $altTeachers = [];
        if ($subjectId) {
            $candidateTeacherIds = SubjectTeacher::where('school_id', $schoolId)
                ->where('subject_id', $subjectId)
                ->where('teacher_id', '!=', $teacherId)
                ->pluck('teacher_id')
                ->unique();

            foreach ($candidateTeacherIds as $cTid) {
                $cUser = User::find($cTid);
                if (!$cUser) continue;

                $cBusy = $this->timetable->builder()
                    ->where('school_id', $schoolId)
                    ->where('day', $targetDay)
                    ->where('type', 'Lecture')
                    ->whereHas('subject_teacher', fn($q) => $q->where('teacher_id', $cTid))
                    ->where('start_time', '<', $targetEnd)
                    ->where('end_time', '>', $targetStart)
                    ->exists();

                if (!$cBusy) {
                    $altTeachers[] = [
                        'id'   => $cUser->id,
                        'name' => trim($cUser->first_name . ' ' . $cUser->last_name),
                    ];
                }
            }
        }

        return [
            'conflict_details' => [
                'teacher_id'          => $teacherId,
                'teacher_name'        => $teacherName,
                'day'                 => $targetDay,
                'start_time'          => $targetStart,
                'end_time'            => $targetEnd,
                'conflicting_class'    => $conflictingClass,
                'clashing_class'       => $conflictingClass,
                'conflicting_subject'  => $conflictingSubject,
                'clashing_subject'     => $conflictingSubject,
                'subject_id'           => $subjectId,
            ],
            'options' => [
                'free_slots'           => $freeSlots,
                'alternative_slots'    => $freeSlots,
                'alternate_teachers'   => $altTeachers,
                'alternative_teachers' => $altTeachers,
            ]
        ];
    }

    public function clearSlot(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson('timetable-edit');

        $request->validate([
            'id'               => 'nullable|numeric',
            'class_section_id' => 'nullable|numeric',
            'day'              => 'nullable|string',
            'start_time'       => 'nullable',
            'end_time'         => 'nullable',
        ]);

        try {
            DB::beginTransaction();
            if ($request->filled('id')) {
                $slot = Timetable::findOrFail($request->id);
            } else {
                $slot = Timetable::where('class_section_id', $request->class_section_id)
                    ->where('day', $request->day)
                    ->where('start_time', Carbon::parse($request->start_time)->format('H:i:s'))
                    ->where('end_time', Carbon::parse($request->end_time)->format('H:i:s'))
                    ->firstOrFail();
            }

            if ($slot->is_locked) {
                DB::rollBack();
                return response()->json([
                    'error'   => true,
                    'message' => 'This slot is locked. Unlock it before removing.'
                ], 422);
            }

            $slot->delete();
            DB::commit();

            return response()->json([
                'error'   => false,
                'message' => 'Slot cleared successfully.'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "TimetableController -> clearSlot");
            return response()->json([
                'error'   => true,
                'message' => 'Failed to clear slot: ' . $e->getMessage()
            ], 500);
        }
    }

    public function saveClassBlock(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson('timetable-edit');

        $request->validate([
            'class_section_id' => 'required|numeric',
            'block'            => 'nullable|string|max:64',
        ]);

        try {
            $classSection = ClassSection::findOrFail($request->class_section_id);
            $classSection->block = $request->filled('block') ? trim($request->block) : null;
            $classSection->save();

            return response()->json([
                'error'   => false,
                'message' => 'Class block updated successfully.',
                'block'   => $classSection->block
            ]);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> saveClassBlock");
            return response()->json([
                'error'   => true,
                'message' => 'Failed to update block: ' . $e->getMessage()
            ], 500);
        }
    }

    public function saveBlockSettings(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson('timetable-edit');

        $request->validate([
            'prioritize_same_block' => 'required|boolean',
        ]);

        try {
            DB::beginTransaction();
            $val = (bool)$request->prioritize_same_block;
            $data = [
                [
                    'name' => 'timetable_prioritize_same_block',
                    'data' => $val ? '1' : '0',
                    'type' => 'string'
                ]
            ];
            $this->schoolSettings->upsert($data, ['name'], ['data', 'type']);
            $this->cache->removeSchoolCache(config('constants.CACHE.SCHOOL.SETTINGS'));
            DB::commit();

            return response()->json([
                'error'                 => false,
                'message'               => 'Block proximity preference updated successfully.',
                'prioritize_same_block' => $val
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "TimetableController -> saveBlockSettings");
            return response()->json([
                'error'   => true,
                'message' => 'Failed to save setting: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getCardViewData(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenSendJson('timetable-list');

        $schoolId = Auth::user()->school_id;
        $classSections = ClassSection::where('school_id', $schoolId)
            ->with(['class', 'section', 'medium', 'teachers'])
            ->get();

        $currentSemester = $this->cache->getDefaultSemesterData();
        $allTimetables = Timetable::where('school_id', $schoolId)
            ->where('type', 'Lecture')
            ->when($currentSemester, fn($q) => $q->where(fn($sq) => $sq->where('semester_id', $currentSemester->id)->orWhereNull('semester_id')))
            ->with(['subject:id,name,bg_color'])
            ->get()
            ->groupBy('class_section_id');

        $requirements = SubjectScheduleRequirement::where('school_id', $schoolId)
            ->where('is_active', true)
            ->when($currentSemester, fn($q) => $q->where(fn($sq) => $sq->where('semester_id', $currentSemester->id)->orWhereNull('semester_id')))
            ->get()
            ->groupBy('class_section_id');

        $cards = [];
        foreach ($classSections as $cs) {
            $slots = $allTimetables->get($cs->id, collect());
            $reqs = $requirements->get($cs->id, collect());

            $totalScheduled = $slots->count();
            $totalRequired = (int)$reqs->sum('weekly_periods');
            if ($totalRequired === 0) {
                $classSubjectsCount = \App\Models\ClassSubject::where('class_id', $cs->class_id)->count();
                $totalRequired = max($totalScheduled, $classSubjectsCount * 5);
                if ($totalRequired === 0) $totalRequired = 30;
            }

            $percentage = $totalRequired > 0 ? min(100, round(($totalScheduled / $totalRequired) * 100)) : 0;

            $subjectsScheduled = $slots->pluck('subject_id')->unique()->count();
            $topSubjects = $slots->groupBy('subject_id')->map(function($grp) {
                $first = $grp->first();
                return [
                    'name'     => $first->subject?->name ?? 'Subject',
                    'count'    => $grp->count(),
                    'bg_color' => $first->subject?->bg_color ?? '#4F46E5',
                ];
            })->values()->take(5);

            $status = 'Empty';
            if ($totalScheduled >= $totalRequired && $totalRequired > 0) {
                $status = 'Complete';
            } elseif ($totalScheduled > 0) {
                $status = 'Incomplete';
            }

            $classTeacherName = $cs->teachers->first()
                ? trim($cs->teachers->first()->first_name . ' ' . $cs->teachers->first()->last_name)
                : null;

            $cards[] = [
                'id'                 => $cs->id,
                'name'               => $cs->name,
                'full_name'          => $cs->full_name,
                'block'              => $cs->block,
                'medium'             => $cs->medium?->name,
                'class_teacher_name' => $classTeacherName,
                'total_scheduled'    => $totalScheduled,
                'total_required'     => $totalRequired,
                'percentage'         => $percentage,
                'subjects_count'     => $subjectsScheduled,
                'top_subjects'       => $topSubjects,
                'status'             => $status,
            ];
        }

        return response()->json([
            'error' => false,
            'data'  => $cards,
        ]);
    }

    private function minutesBetween(string $start, string $end): int
    {
        return (int) ((strtotime($end) - strtotime($start)) / 60);
    }
}
