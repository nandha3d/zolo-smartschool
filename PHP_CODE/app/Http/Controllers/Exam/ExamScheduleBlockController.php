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
            $staffMembers = Staff::owner()->with('user')->get();
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
        $validator = Validator::make($request->all(), [
            'exam_id' => 'required|numeric',
            'class_section_id' => 'required|numeric',
            'date' => 'required|date',
            'block_type' => 'required|in:exam,revision,practice,study_leave,lab_practical',
            'subject_id' => 'nullable|numeric',
            'start_period_id' => 'nullable|numeric',
            'end_period_id' => 'nullable|numeric',
            'custom_start_time' => 'nullable',
            'custom_end_time' => 'nullable',
            'exam_incharge_staff_id' => 'nullable|numeric',
            'assistant_incharge_staff_id' => 'nullable|numeric',
            'hall_or_resource_id' => 'nullable|numeric',
            'total_marks' => 'nullable|numeric',
            'passing_marks' => 'nullable|numeric',
            'instructions' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();
            $sessionYear = $this->cache->getDefaultSessionYear();
            $schoolId = Auth::user()->school_id;

            $classSection = ClassSection::findOrFail($request->class_section_id);

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
            return ResponseService::successResponse('Exam schedule block added successfully.');
        } catch (Throwable $e) {
            DB::rollBack();
            return ResponseService::errorResponse('Failed to create block: ' . $e->getMessage());
        }
    }

    /**
     * Delete an exam schedule block.
     */
    public function destroy($id)
    {
        try {
            $block = ExamScheduleBlock::owner()->findOrFail($id);
            $block->delete();
            return ResponseService::successResponse('Schedule block deleted successfully.');
        } catch (Throwable $e) {
            return ResponseService::errorResponse('Failed to delete block: ' . $e->getMessage());
        }
    }
}
