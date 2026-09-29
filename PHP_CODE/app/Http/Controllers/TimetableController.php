<?php

namespace App\Http\Controllers;

use App\Models\Timetable;
use App\Models\SubjectTeacher;
use App\Models\User;
use App\Models\ClassSubject;
use App\Models\ClassSection;
use App\Services\TimetableGeneratorService;
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

    public function __construct(SubjectTeacherInterface $subjectTeacher, SubjectInterface $subject, TimetableInterface $timetable, ClassSectionInterface $classSection, UserInterface $user, SchoolSettingInterface $schoolSettings, CachingService $cache, ClassSchoolInterface $class, MediumInterface $medium, SessionYearsTrackingsService $sessionYearsTrackingsService)
    {
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

        return view('timetable.index', compact('timetableData', 'classes', 'mediums', 'classSections'));
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

        return view('timetable.edit', compact(
            'subjectTeachers',
            'subjectWithoutTeacherAssigned',
            'classSection',
            'timetables',
            'timetableSettingsData',
            'currentSemester',
            'allTeachers',
            'allClassSubjects'
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

        try {
            $generatorService = app(\App\Services\TimetableGeneratorService::class);
            $result = $generatorService->generate($request->all());

            if (!$result['status']) {
                return response()->json([
                    'error'   => true,
                    'message' => $result['message'],
                ], 422);
            }

            return response()->json([
                'error'         => false,
                'message'       => $result['message'],
                'created_count' => $result['created_count'] ?? 0,
                'has_conflicts' => $result['has_conflicts'] ?? false,
                'conflicts'     => $result['conflicts'] ?? [],
            ]);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> autoGenerate");
            return response()->json([
                'error'   => true,
                'message' => 'An error occurred during auto-generation: ' . $e->getMessage()
            ], 500);
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
                'subject_teacher.teacher:id,first_name,last_name'
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
            'id'                 => 'required|numeric',
            'subject_id'         => 'nullable|numeric',
            'teacher_id'         => 'nullable|numeric',
            'alt_teacher_id'     => 'nullable|numeric',
        ]);

        try {
            $timetable = Timetable::findOrFail($request->id);
            $schoolId = Auth::user()->school_id ?? $timetable->school_id;

            if ($request->filled('subject_id')) {
                $timetable->subject_id = $request->subject_id;
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

            return response()->json([
                'error'   => false,
                'message' => 'Timetable slot updated successfully!',
                'data'    => $timetable
            ]);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, "TimetableController -> quickUpdate");
            return response()->json([
                'error'   => true,
                'message' => 'Update failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
