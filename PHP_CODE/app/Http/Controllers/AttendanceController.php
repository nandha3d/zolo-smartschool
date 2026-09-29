<?php

namespace App\Http\Controllers;

use App\Repositories\Attendance\AttendanceInterface;
use App\Repositories\ClassSection\ClassSectionInterface;
use App\Repositories\Student\StudentInterface;
use App\Services\CachingService;
use App\Services\ResponseService;
use App\Services\SessionYearsTrackingsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class AttendanceController extends Controller
{

    private AttendanceInterface $attendance;
    private ClassSectionInterface $classSection;
    private StudentInterface $student;
    private CachingService $cache;
    private SessionYearsTrackingsService $sessionYearsTrackingsService;

    public function __construct(AttendanceInterface $attendance, ClassSectionInterface $classSection, StudentInterface $student, CachingService $cachingService, SessionYearsTrackingsService $sessionYearsTrackingsService)
    {
        $this->attendance = $attendance;
        $this->classSection = $classSection;
        $this->student = $student;
        $this->cache = $cachingService;
        $this->sessionYearsTrackingsService = $sessionYearsTrackingsService;
    }


    public function index()
    {
        ResponseService::noFeatureThenRedirect('Attendance Management');
        ResponseService::noAnyPermissionThenRedirect(['class-teacher', 'attendance-list', 'attendance-create']);
        $classSections = $this->classSection->builder()->ClassTeacher()->with('class', 'class.stream', 'section', 'medium')->get();
        return view('attendance.index', compact('classSections'));
    }


    public function view()
    {
        ResponseService::noFeatureThenRedirect('Attendance Management');
        ResponseService::noAnyPermissionThenRedirect(['class-teacher', 'attendance-list']);
        $class_sections = $this->classSection->builder()->ClassTeacher()->with('class', 'class.stream', 'section', 'medium')->get();
        return view('attendance.view', compact('class_sections'));
    }

    public function getAttendanceData(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Attendance Management');
        ResponseService::noAnyPermissionThenSendJson(['class-teacher', 'attendance-list', 'attendance-create']);
        $response = $this->attendance->builder()->select('type')->where(['date' => date('Y-m-d', strtotime($request->date)), 'class_section_id' => $request->class_section_id])->pluck('type')->first();
        return response()->json($response);
    }

    public function store(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Attendance Management');
        ResponseService::noAnyPermissionThenSendJson(['class-teacher', 'attendance-create', 'attendance-edit']);
        $request->validate([
            'class_section_id' => 'required',
            'date' => 'required',
        ]);
        try {
            DB::beginTransaction();
            $attendanceData = array();
            $sessionYear = $this->cache->getDefaultSessionYear();
            $student_ids = array();
            foreach ($request->attendance_data as $value) {
                $data = (object) $value;
                $attendanceData[] = array(
                    "id" => $data->id ?? null,
                    'class_section_id' => $request->class_section_id,
                    'student_id' => $data->student_id,
                    'session_year_id' => $sessionYear->id,
                    'type' => $request->holiday ?? $data->type,
                    'date' => date('Y-m-d', strtotime($request->date)),
                    'remark' => $data->remark ?? null,
                );

                if ($data->type == 0) {
                    $student_ids[] = $data->student_id;
                }
            }
            $this->attendance->upsert($attendanceData, ["id"], ["class_section_id", "student_id", "session_year_id", "type", "date", "remark"]);

            DB::commit();

            if ($request->absent_notification) {
                $user = $this->student->builder()->whereIn('user_id', $student_ids)->pluck('guardian_id');
                $date = Carbon::parse(date('Y-m-d', strtotime($request->date)))->format('F jS, Y');
                $title = 'Absent';
                $body = 'Your child is absent on ' . $date;
                $type = "attendance";

                send_notification($user, $title, $body, $type);
            }


            ResponseService::successResponse('Data Stored Successfully');
        } catch (Throwable $e) {
            if (
                Str::contains($e->getMessage(), [
                    'does not exist',
                    'file_get_contents'
                ])
            ) {
                DB::commit();
                ResponseService::warningResponse("Data Stored successfully. But App push notification not send.");
            } else {
                DB::rollback();
                ResponseService::logErrorResponse($e, "Attendance Controller -> Store method");
                ResponseService::errorResponse();
            }
        }
    }

    public function show(Request $request)
    {
        ResponseService::noPermissionThenSendJson('attendance-list');
        ResponseService::noFeatureThenRedirect('Attendance Management');
        ResponseService::noAnyPermissionThenRedirect(['class-teacher', 'attendance-list']);

        //        $offset = $request->input('offset', 0);
        //        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'roll_number');
        if ($sort === 'roll_no') {
            $sort = 'roll_number';
        }
        $order = $request->input('order', 'ASC');
        $search = $request->input('search');

        $class_section_id = $request->class_section_id;
        $date = date('Y-m-d', strtotime($request->date));
        $sessionYear = $this->cache->getDefaultSessionYear();

        $attendanceData = array();
        $total = 0;

        $attendanceQuery = $this->attendance->builder()->with('user.student')->where(['date' => $date, 'class_section_id' => $class_section_id, 'session_year_id' => $sessionYear->id])->whereHas('user', function ($q) {
            $q->whereNull('deleted_at');
        })->whereHas('user.student', function ($q) use ($sessionYear) {
            $q->where('session_year_id', $sessionYear->id);
        });

        if ($date != '' && $attendanceQuery->count() > 0) {
            $attendanceQuery->when($search, function ($query) use ($search) {
                $query->where('id', 'LIKE', "%$search%")->orWhereHas('user', function ($q) use ($search) {
                    $q->whereRaw("concat(users.first_name,' ',users.last_name) LIKE ?", ["%{$search}%"]);
                });
            })->where('date', $date)->whereHas('user.student', function ($q) use ($sessionYear) {
                $q->where('session_year_id', $sessionYear->id);
            });

            $total = $attendanceQuery->count();
            $attendanceData = $attendanceQuery->get();
        } else if ($class_section_id) {
            $studentQuery = $this->student->builder()->where('session_year_id', $sessionYear->id)->where('class_section_id', $class_section_id)->with('user')
                ->whereHas('user', function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->when($search, function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")->orWhereHas('user', function ($q) use ($search) {
                        $q->whereRaw("concat(users.first_name,' ',users.last_name) LIKE ?", ["%{$search}%"])->where('deleted_at', NULL);
                    });
                })->where('session_year_id', $sessionYear->id)->where('class_section_id', $class_section_id);

            $total = $studentQuery->count();
            // $studentQuery->orderBy($sort, $order)->skip($offset)->take($limit);
            $studentQuery->orderBy($sort, $order);
            $attendanceData = $studentQuery->get();
        }

        $rows = [];
        $no = 1;

        $isExistingAttendance = ($attendanceQuery->count() > 0);

        foreach ($attendanceData as $row) {
            $type = $row->type ?? NULL;
            $remark = $row->remark ?? '';
            $userObj = $row->user ?? null;
            $fullName = trim(($userObj->first_name ?? '') . ' ' . ($userObj->last_name ?? ''));
            $image = $userObj->image ?? null;

            if ($isExistingAttendance) {
                $attId = $row->id;
                $studentId = $row->student_id;
                $admNo = $userObj?->student?->admission_no ?? '';
                $rollNo = $userObj?->student?->roll_number ?? '';
            } else {
                $attId = null;
                $studentId = $row->user_id;
                $admNo = $row->admission_no ?? '';
                $rollNo = $row->roll_number ?? '';
            }

            $rows[] = [
                'id' => $attId,
                'no' => $no,
                'student_id' => $studentId,
                'user_id' => $studentId,
                'admission_no' => $admNo,
                'roll_no' => $rollNo,
                'student_name' => $fullName,
                'image' => $image,
                'name' => $fullName,
                'type' => $type,
                'remark' => $remark,
            ];
            $no++;
        }

        $bulkData['total'] = $total;
        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }


    public function attendance_show(Request $request)
    {
        ResponseService::noPermissionThenSendJson('attendance-list');
        ResponseService::noFeatureThenRedirect('Attendance Management');
        ResponseService::noAnyPermissionThenRedirect(['class-teacher', 'attendance-list']);

        $offset = request('offset', 0);
        $limit = request('limit');
        $sort = request('sort', 'student_id');
        $order = request('order', 'ASC');
        $search = request('search');
        $attendanceType = request('attendance_type');

        $class_section_id = request('class_section_id');
        $date = date('Y-m-d', strtotime(request('date')));

        $validator = Validator::make($request->all(), ['class_section_id' => 'required', 'date' => 'required',]);
        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }

        $sessionYear = $this->cache->getDefaultSessionYear();

        $sql = $this->attendance->builder()->where(['date' => $date, 'class_section_id' => $class_section_id])->with('user.student')
            ->where(function ($query) use ($search) {
                $query->when($search, function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('id', 'LIKE', "%$search%")
                            ->orwhere('student_id', 'LIKE', "%$search%")
                            ->orWhereHas('user', function ($q) use ($search) {
                                $q->whereRaw("concat(first_name,' ',last_name) LIKE ?", ["%{$search}%"])
                                    ->orwhere('first_name', 'LIKE', "%$search%")
                                    ->orwhere('last_name', 'LIKE', "%$search%");
                            })->orWhereHas('user.student', function ($q) use ($search) {
                                $q->where('admission_no', 'LIKE', "%$search%")
                                    ->orwhere('id', 'LIKE', "%$search%")
                                    ->orwhere('user_id', 'LIKE', "%$search%")
                                    ->orwhere('roll_number', 'LIKE', "%$search%");
                            });
                    });
                });
            })
            ->when($attendanceType != null, function ($query) use ($attendanceType) {
                $query->where('type', $attendanceType);
            });
        $sql = $sql->whereHas('user.student', function ($q) use ($sessionYear) {
            $q->where('session_year_id', $sessionYear->id);
        });
        $total = $sql->count();

        $sql->orderBy($sort, $order);

        if ($limit) {
            if ($offset >= $total && $total > 0) {
                $lastPage = floor(($total - 1) / $limit) * $limit; // calculate last page offset
                $offset = $lastPage;
            }
            $sql->skip($offset)->take($limit);
        }

        $res = $sql->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;

        foreach ($res as $row) {
            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function monthWiseIndex()
    {
        ResponseService::noPermissionThenRedirect('attendance-list');
        ResponseService::noFeatureThenRedirect('Attendance Management');
        ResponseService::noAnyPermissionThenRedirect(['class-teacher', 'attendance-list']);
        $class_sections = $this->classSection->builder()->ClassTeacher()->with('class', 'class.stream', 'section', 'medium')->get();

        return view('attendance.month_wise', compact('class_sections'));
    }

    public function monthWiseShow(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Attendance Management');
        ResponseService::noAnyPermissionThenRedirect(['class-teacher', 'attendance-list']);

        $sessionYear = $this->cache->getDefaultSessionYear();
        $sql = $this->student->builder()->with('user')->whereHas('attendance', function ($q) use ($request, $sessionYear) {
            $q->where('class_section_id', $request->class_section_id)
                ->whereMonth('date', $request->month)
                ->where('session_year_id', $sessionYear->id);
        })->orderBy('roll_number', 'ASC');

        $total = $sql->count();

        $res = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();


        $month = $request->month;
        $date = Carbon::create(null, $month, 1);

        foreach ($res as $row) {
            $studentAttendance = ['full_name' => $row->user->full_name, 'roll_number' => $row->roll_number];

            for ($day = 1; $day <= $date->daysInMonth; $day++) {
                $currentDate = $date->copy()->day($day)->format('Y-m-d');
                $attendance = $row->attendance()->where('student_id', $row->user_id)->where('date', $currentDate)->first();
                $studentAttendance["day_$day"] = $attendance ? $attendance->type : null;

            }
            $tempRow[] = $studentAttendance;
            $rows = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);

    }
}
