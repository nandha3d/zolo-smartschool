<?php

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\ClassSchool;
use App\Models\ClassSection;
use App\Models\Exam;
use App\Models\ExamScheduleBlock;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\TimetablePeriod;
use App\Models\TimetableResource;
use App\Services\CachingService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ExamScheduleBlockController extends Controller
{
    private CachingService $cache;

    public function __construct(CachingService $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Display Exam Block Schedule grid & visual block builder.
     */
    public function index()
    {
        ResponseService::noFeatureThenRedirect('Exam Management');
        ResponseService::noPermissionThenRedirect('exam-list');
        $request = request();
        $sessionYear = $this->cache->getDefaultSessionYear();
        $schoolId = Auth::user()->school_id;

        $exams = Exam::owner()->where('session_year_id', $sessionYear->id)->get();
        $class_sections = ClassSection::owner()->with(['class', 'section', 'medium'])->get();

        $selected_exam_id = $request->get('exam_id', $exams->first()?->id);
        $selected_class_section_id = $request->get('class_section_id', $class_sections->first()?->id);

        $blocks = collect();
        $periods = collect();
        $subjects = collect();
        $staffMembers = collect();
        $resources = collect();

        if ($selected_exam_id && $selected_class_section_id) {
            $blocks = ExamScheduleBlock::owner()
                ->where('exam_id', $selected_exam_id)
                ->where('class_section_id', $selected_class_section_id)
                ->with(['subject', 'examIncharge', 'assistantIncharge', 'hallResource', 'startPeriod', 'endPeriod'])
                ->orderBy('date', 'asc')
                ->orderBy('custom_start_time', 'asc')
                ->get();

            $periods = TimetablePeriod::owner()->orderBy('start_time', 'asc')->get();
            $cs = ClassSection::with('class.all_subjects')->find($selected_class_section_id);
            $subjects = $cs?->class?->all_subjects ?? Subject::owner()->get();
            $staffMembers = Staff::with('user')->get();
            $resources = TimetableResource::owner()->where('is_active', true)->get();
        }

        return view('exams.blocks.index', compact(
            'exams',
            'class_sections',
            'selected_exam_id',
            'selected_class_section_id',
            'blocks',
            'periods',
            'subjects',
            'staffMembers',
            'resources',
            'sessionYear'
        ));
    }

    /**
     * Store an exam / revision / practice schedule block.
     */
    public function store(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Exam Management');
        ResponseService::noPermissionThenSendJson('exam-create');
        $validator = Validator::make($request->all(), [
            'exam_id' => 'required|numeric',
            'class_section_id' => 'required|numeric',
            'date' => 'required|date',
            'block_type' => 'required|in:exam,revision,practice,study_leave,lab_practical',
            'subject_id' => 'nullable|numeric',
            'start_period_id' => 'nullable|numeric',
            'end_period_id' => 'nullable|numeric',
            'custom_start_time' => 'nullable|date_format:H:i,H:i:s',
            'custom_end_time' => 'nullable|date_format:H:i,H:i:s',
            'exam_incharge_staff_id' => 'nullable|numeric',
            'assistant_incharge_staff_id' => 'nullable|numeric|different:exam_incharge_staff_id',
            'hall_or_resource_id' => 'nullable|numeric',
            'total_marks' => 'nullable|numeric|min:0',
            'passing_marks' => 'nullable|numeric|min:0|lte:total_marks',
            'instructions' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        $sessionYear = $this->cache->getDefaultSessionYear();
        $schoolId = Auth::user()->school_id;

        $exam = Exam::owner()->where('session_year_id', $sessionYear->id)->find($request->exam_id);
        $classSection = ClassSection::owner()->find($request->class_section_id);
        if (!$exam || !$classSection) {
            ResponseService::validationError('Invalid exam or class section.');
        }

        $periods = TimetablePeriod::owner()->get()->keyBy('id');
        [$start, $end] = $this->blockWindow(
            $request->custom_start_time,
            $request->custom_end_time,
            $request->start_period_id,
            $request->end_period_id,
            $periods
        );
        if ($start >= $end) {
            ResponseService::validationError('End time must be after start time.');
        }

        // A class, a hall and an invigilator can each only be in one block at a time.
        $staffIds = array_filter([(int)$request->exam_incharge_staff_id, (int)$request->assistant_incharge_staff_id]);
        $sameDay = ExamScheduleBlock::owner()
            ->whereDate('date', $request->date)
            ->where(function ($q) use ($request, $staffIds) {
                $q->where('class_section_id', $request->class_section_id);
                if ($request->hall_or_resource_id) {
                    $q->orWhere('hall_or_resource_id', $request->hall_or_resource_id);
                }
                if (!empty($staffIds)) {
                    $q->orWhereIn('exam_incharge_staff_id', $staffIds)
                        ->orWhereIn('assistant_incharge_staff_id', $staffIds);
                }
            })
            ->get();

        foreach ($sameDay as $existing) {
            [$exStart, $exEnd] = $this->blockWindow(
                $existing->custom_start_time,
                $existing->custom_end_time,
                $existing->start_period_id,
                $existing->end_period_id,
                $periods
            );
            if ($start >= $exEnd || $end <= $exStart) {
                continue;
            }
            if ($existing->class_section_id == $request->class_section_id) {
                ResponseService::validationError('This class already has a block scheduled at that time.');
            }
            if ($request->hall_or_resource_id && $existing->hall_or_resource_id == $request->hall_or_resource_id) {
                ResponseService::validationError('This hall / room is already booked at that time.');
            }
            ResponseService::validationError('The selected invigilator is already assigned to another block at that time.');
        }

        try {
            DB::beginTransaction();

            ExamScheduleBlock::create([
                'school_id' => $schoolId,
                'session_year_id' => $sessionYear->id,
                'exam_id' => $request->exam_id,
                'class_id' => $classSection->class_id,
                'class_section_id' => $request->class_section_id,
                'date' => $request->date,
                'block_type' => $request->block_type,
                'subject_id' => $request->subject_id,
                'start_period_id' => $request->start_period_id,
                'end_period_id' => $request->end_period_id,
                'custom_start_time' => $request->custom_start_time,
                'custom_end_time' => $request->custom_end_time,
                'exam_incharge_staff_id' => $request->exam_incharge_staff_id,
                'assistant_incharge_staff_id' => $request->assistant_incharge_staff_id,
                'hall_or_resource_id' => $request->hall_or_resource_id,
                'total_marks' => $request->total_marks,
                'passing_marks' => $request->passing_marks,
                'instructions' => $request->instructions,
            ]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'ExamScheduleBlockController -> store');
            ResponseService::errorResponse('Failed to create block.');
        }
        ResponseService::successResponse('Exam schedule block added successfully.');
    }

    /**
     * Delete an exam schedule block.
     */
    public function destroy($id)
    {
        ResponseService::noFeatureThenSendJson('Exam Management');
        ResponseService::noPermissionThenSendJson('exam-delete');
        $block = ExamScheduleBlock::owner()->find($id);
        if (!$block) {
            ResponseService::errorResponse('Schedule block not found.');
        }
        try {
            $block->delete();
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'ExamScheduleBlockController -> destroy');
            ResponseService::errorResponse('Failed to delete block.');
        }
        ResponseService::successResponse('Schedule block deleted successfully.');
    }

    /**
     * Time window of a block as "HH:MM:SS" strings: custom times win, then the period
     * range, and a block with neither occupies the whole day.
     */
    private function blockWindow($customStart, $customEnd, $startPeriodId, $endPeriodId, $periods): array
    {
        $normalize = static fn ($t) => $t ? date('H:i:s', strtotime($t)) : null;

        $start = $normalize($customStart) ?? $normalize($periods->get($startPeriodId)?->start_time);
        $end = $normalize($customEnd)
            ?? $normalize($periods->get($endPeriodId ?: $startPeriodId)?->end_time);

        return [$start ?? '00:00:00', $end ?? '23:59:59'];
    }
}
