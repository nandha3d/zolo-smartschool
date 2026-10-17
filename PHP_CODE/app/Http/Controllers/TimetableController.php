<?php

namespace App\Http\Controllers;

use App\Models\Timetable;
use App\Models\SubjectTeacher;
use App\Models\User;
use App\Models\ClassSubject;
use App\Models\ClassSection;
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
        $classSections = $this->classSection->builder()->with(['class', 'section', 'medium'])->get();

        $schoolId = Auth::user()->school_id;
        $templates = $this->planningService->getTemplates($schoolId);
        $resources = $this->planningService->getResources($schoolId);

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

        return view('timetable.index', compact('timetableData', 'classes', 'mediums', 'classSections', 'templates', 'resources', 'readiness'));
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

        $allTeachers = $this->user->builder()->role('Teacher')->get(['id', 'first_name', 'last_name']);
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

        if (!empty($request->class_id)) {
            $sql = $sql->where('class_id', $request->class_id);
        }

        if (!empty($request->section_id)) {
            $sql = $sql->where('section_id', $request->section_id);
        }

        if (!empty($request->medium_id)) {
            $sql = $sql->where('medium_id', $request->medium_id);
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
            $operate = '<a href="' . route('timetable.edit', $row->id) . '?autogen=1" class="btn btn-xs btn-gradient-primary mr-1" title="' . trans("Auto-Generate Timetable") . '"><i class="fa fa-magic"></i> ' . trans("Generate") . '</a>';
            $operate .= BootstrapTableService::editButton(route('timetable.edit', $row->id), false);
            $operate .= BootstrapTableService::button('fa fa-trash', '#', ['delete-class-timetable', 'btn-gradient-danger'], ['title' => trans("Delete Class Timetable"), 'data-id' => $row->id]);
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
        $teacher = $this->user->findById($teacherID, ['id', 'first_name', 'last_name']);
        $timetables = $this->timetable->builder()->whereHas('subject_teacher', function ($q) use ($teacherID) {
            $q->where('teacher_id', $teacherID);
        })->with('subject:id,name,bg_color', 'class_section.class', 'class_section.section', 'class_section.medium')->get();

        // Get Timetable Settings Data
        $timetableSettingsData = $this->schoolSettings->getBulkData([
            'timetable_start_time',
            'timetable_end_time',
            'timetable_duration'
        ]);
        return view('timetable.teacher.view', compact('timetables', 'teacher', 'timetableSettingsData'));
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
                'resource:id,name,type',
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

        foreach ($timetables as $slot) {
            if (!in_array($slot->day, $activeDays)) {
                $activeDays[] = $slot->day;
            }
            $timeKey = $slot->start_time . ' - ' . $slot->end_time;
            if (!isset($timeSlots[$timeKey])) {
                $timeSlots[$timeKey] = [
                    'start_time' => $slot->start_time,
                    'end_time'   => $slot->end_time,
                    'period_no'  => $slot->period_no ?? null,
                    'type'       => $slot->type,
                    'title'      => $slot->title ?? ($slot->type === 'Break' ? 'Break' : null),
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
                'is_locked'            => (bool)$t->is_locked,
                'locked_by'            => $t->locked_by,
                'lock_reason'          => $t->lock_reason,
                'conflict'             => $conflict,
                'note'                 => $t->note,
            ];
        }

        return response()->json([
            'error'      => false,
            'days'       => $activeDays,
            'time_slots' => array_values($timeSlots),
            'matrix'     => $matrix,
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
            if ($slot->day === $day && $slot->start_time === $start && $slot->end_time === $end) {
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
                $slot->subject_teacher?->teacher_id, $slot->resource_id, $excludeIds, $periodId
            );
            if ($swap) {
                $violations = array_merge($violations, $this->validationService->validateSingleSlot(
                    $schoolId, $swap->class_section_id, $slot->day, $slot->start_time, $slot->end_time,
                    $swap->subject_teacher?->teacher_id, $swap->resource_id, $excludeIds, $slot->period_id
                ));
            }

            if (!empty($violations)) {
                DB::rollBack();
                return response()->json([
                    'error'      => true,
                    'message'    => $violations[0]['message'],
                    'violations' => $violations,
                ], 422);
            }

            $origin = ['day' => $slot->day, 'start_time' => $slot->start_time, 'end_time' => $slot->end_time, 'period_id' => $slot->period_id];
            $slot->update(['day' => $day, 'start_time' => $start, 'end_time' => $end, 'period_id' => $periodId]);
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

        $schoolId = Auth::user()->school_id;
        $absentId = (int) $request->teacher_id;
        $date = Carbon::parse($request->date);
        $day = $date->format('l');

        $dayLessons = $this->timetable->builder()
            ->where('type', 'Lecture')
            ->where('day', $day)
            ->with(['subject:id,name', 'subject_teacher:id,teacher_id', 'class_section.class', 'class_section.section', 'class_section.medium'])
            ->CurrentSemesterData()
            ->orderBy('start_time')
            ->get();

        $absentLessons = $dayLessons->filter(fn($t) => (int) $t->subject_teacher?->teacher_id === $absentId)->values();

        $onLeave = Leave::where('school_id', $schoolId)
            ->where('status', 1)
            ->whereHas('leave_detail', fn($q) => $q->whereDate('date', $date->toDateString()))
            ->pluck('user_id')
            ->all();

        $teachers = $this->user->builder()->role('Teacher')
            ->where('id', '!=', $absentId)
            ->whereNotIn('id', $onLeave)
            ->get(['id', 'first_name', 'last_name']);

        $assignments = SubjectTeacher::whereIn('teacher_id', $teachers->pluck('id'))
            ->get(['teacher_id', 'subject_id', 'class_section_id'])
            ->groupBy('teacher_id');

        $unavailable = TeacherAvailability::where('school_id', $schoolId)
            ->where('day', $day)
            ->where('status', 'unavailable')
            ->get(['teacher_id', 'period_id'])
            ->groupBy('teacher_id')
            ->map(fn($g) => $g->pluck('period_id')->flip()->all());

        $busyByTeacher = $dayLessons->groupBy(fn($t) => $t->subject_teacher?->teacher_id);

        $result = $absentLessons->map(function ($lesson) use ($teachers, $assignments, $unavailable, $busyByTeacher) {
            $candidates = [];
            foreach ($teachers as $teacher) {
                $busy = $busyByTeacher->get($teacher->id, collect());
                $clash = $busy->first(fn($b) => $b->start_time < $lesson->end_time && $b->end_time > $lesson->start_time);
                if ($clash || ($lesson->period_id && isset($unavailable[$teacher->id][$lesson->period_id]))) {
                    continue;
                }

                $teaches = $assignments->get($teacher->id, collect());
                $sameSubject = $teaches->contains('subject_id', $lesson->subject_id);
                $sameClass = $teaches->contains('class_section_id', $lesson->class_section_id);
                $reasons = [];
                if ($sameSubject) $reasons[] = 'teaches ' . ($lesson->subject?->name ?? 'this subject');
                if ($sameClass) $reasons[] = 'already teaches this class';
                $reasons[] = $busy->count() . ' lessons that day';

                $candidates[] = [
                    'teacher_id'   => $teacher->id,
                    'name'         => trim($teacher->first_name . ' ' . $teacher->last_name),
                    'score'        => ($sameSubject ? 20 : 0) + ($sameClass ? 10 : 0) - $busy->count(),
                    'lessons_that_day' => $busy->count(),
                    'reasons'      => $reasons,
                ];
            }
            usort($candidates, fn($a, $b) => [$b['score'], $a['name']] <=> [$a['score'], $b['name']]);

            return [
                'timetable_id' => $lesson->id,
                'class_name'   => $lesson->class_section?->full_name,
                'subject_name' => $lesson->subject?->name,
                'start_time'   => $lesson->start_time,
                'end_time'     => $lesson->end_time,
                'candidates'   => array_slice($candidates, 0, 5),
            ];
        })->values();

        return response()->json([
            'error'   => false,
            'day'     => $day,
            'date'    => $date->toDateString(),
            'lessons' => $result,
        ]);
    }

    private function minutesBetween(string $start, string $end): int
    {
        return (int) ((strtotime($end) - strtotime($start)) / 60);
    }
}
