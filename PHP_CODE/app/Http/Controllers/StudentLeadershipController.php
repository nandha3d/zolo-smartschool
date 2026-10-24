<?php

namespace App\Http\Controllers;

use App\Models\ClassSection;
use App\Models\Students;
use App\Models\StudentGroup;
use App\Models\StudentGroupMember;
use App\Models\StudentLeadershipRole;
use App\Services\CachingService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class StudentLeadershipController extends Controller
{
    private CachingService $cache;

    public function __construct(CachingService $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Display Class Groups & Leadership management cockpit.
     */
    public function index()
    {
        $request = request();
        $sessionYear = $this->cache->getDefaultSessionYear();
        $class_sections = ClassSection::owner()->with(['class', 'section', 'medium'])->get();

        $selected_class_section_id = $request->get('class_section_id', $class_sections->first()?->id);

        $students = collect();
        $leadershipRoles = collect();
        $studentGroups = collect();

        if ($selected_class_section_id) {
            $students = Students::owner()
                ->where('class_section_id', $selected_class_section_id)
                ->where('session_year_id', $sessionYear->id)
                ->with(['user', 'activeLeadershipRoles'])
                ->orderBy('roll_number', 'asc')
                ->get();

            $leadershipRoles = StudentLeadershipRole::owner()
                ->where('class_section_id', $selected_class_section_id)
                ->where('session_year_id', $sessionYear->id)
                ->where('is_active', true)
                ->with(['student.user'])
                ->get();

            $studentGroups = StudentGroup::owner()
                ->where('class_section_id', $selected_class_section_id)
                ->where('session_year_id', $sessionYear->id)
                ->with(['leader.user', 'viceLeader.user', 'students.user'])
                ->get();
        }

        return view('students.leadership.index', compact(
            'class_sections',
            'selected_class_section_id',
            'students',
            'leadershipRoles',
            'studentGroups',
            'sessionYear'
        ));
    }

    /**
     * Get JSON list of students for dynamic AJAX loading.
     */
    public function getStudentsByClassSection($id)
    {
        $sessionYear = $this->cache->getDefaultSessionYear();
        $students = Students::owner()
            ->where('class_section_id', $id)
            ->where('session_year_id', $sessionYear->id)
            ->with(['user', 'activeLeadershipRoles'])
            ->orderBy('roll_number', 'asc')
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'roll_number' => $s->roll_number,
                    'full_name' => $s->user->full_name ?? ($s->user->first_name . ' ' . $s->user->last_name),
                    'gender' => $s->user->gender ?? 'unknown',
                    'image' => $s->user->image ?? asset('assets/no_image_available.jpg'),
                    'roles' => $s->activeLeadershipRoles->pluck('title')->toArray(),
                ];
            });

        return response()->json(['status' => 'success', 'data' => $students]);
    }

    /**
     * Assign or update a student leadership role.
     */
    public function assignRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'class_section_id' => 'required|numeric',
            'student_id' => 'required|numeric',
            'role_type' => 'required|in:class_leader,vice_class_leader,bench_leader,group_leader,sports_captain,discipline_prefect,lab_batch_leader',
            'title' => 'required|string|max:100',
            'badge_icon' => 'nullable|string|max:50',
            'badge_color' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();
            $sessionYear = $this->cache->getDefaultSessionYear();
            $schoolId = Auth::user()->school_id;

            // If class_leader or vice_class_leader, can optionally deactivate previous holder of same specific role title in section
            if (in_array($request->role_type, ['class_leader', 'vice_class_leader'])) {
                StudentLeadershipRole::where('school_id', $schoolId)
                    ->where('session_year_id', $sessionYear->id)
                    ->where('class_section_id', $request->class_section_id)
                    ->where('role_type', $request->role_type)
                    ->where('title', $request->title)
                    ->update(['is_active' => false]);
            }

            $badgeIcon = $request->badge_icon ?: match ($request->role_type) {
                'class_leader' => 'fa-star',
                'vice_class_leader' => 'fa-star-half-o',
                'bench_leader' => 'fa-flag',
                'group_leader' => 'fa-users',
                'sports_captain' => 'fa-trophy',
                'discipline_prefect' => 'fa-shield',
                'lab_batch_leader' => 'fa-flask',
                default => 'fa-certificate',
            };

            $badgeColor = $request->badge_color ?: match ($request->role_type) {
                'class_leader' => '#E67E22',
                'vice_class_leader' => '#3498DB',
                'bench_leader' => '#16A085',
                'group_leader' => '#8E44AD',
                'sports_captain' => '#D35400',
                'discipline_prefect' => '#C0392B',
                'lab_batch_leader' => '#27AE60',
                default => '#22577A',
            };

            $role = StudentLeadershipRole::create([
                'school_id' => $schoolId,
                'session_year_id' => $sessionYear->id,
                'class_section_id' => $request->class_section_id,
                'student_id' => $request->student_id,
                'role_type' => $request->role_type,
                'title' => $request->title,
                'badge_icon' => $badgeIcon,
                'badge_color' => $badgeColor,
                'assigned_by' => Auth::id(),
                'valid_from' => now()->toDateString(),
                'is_active' => true,
            ]);

            DB::commit();
            return ResponseService::successResponse('Student leadership role successfully assigned.');
        } catch (Throwable $e) {
            DB::rollBack();
            return ResponseService::errorResponse('Failed to assign leadership role: ' . $e->getMessage());
        }
    }

    /**
     * Remove / deactivate a leadership role.
     */
    public function removeRole($id)
    {
        try {
            $role = StudentLeadershipRole::owner()->findOrFail($id);
            $role->delete();
            return ResponseService::successResponse('Leadership role removed successfully.');
        } catch (Throwable $e) {
            return ResponseService::errorResponse('Failed to remove role: ' . $e->getMessage());
        }
    }

    /**
     * Create or update a student group (Bench group, Study group, Project team).
     */
    public function storeGroup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'class_section_id' => 'required|numeric',
            'name' => 'required|string|max:191',
            'type' => 'required|in:study_group,bench_group,house,activity_club,project_team',
            'leader_student_id' => 'nullable|numeric',
            'vice_leader_student_id' => 'nullable|numeric',
            'student_ids' => 'nullable|array',
            'bench_number' => 'nullable|numeric',
            'color_code' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();
            $sessionYear = $this->cache->getDefaultSessionYear();
            $schoolId = Auth::user()->school_id;

            $group = StudentGroup::create([
                'school_id' => $schoolId,
                'session_year_id' => $sessionYear->id,
                'class_section_id' => $request->class_section_id,
                'name' => $request->name,
                'type' => $request->type,
                'description' => $request->description,
                'leader_student_id' => $request->leader_student_id,
                'vice_leader_student_id' => $request->vice_leader_student_id,
                'color_code' => $request->color_code ?: '#22577A',
                'is_active' => true,
            ]);

            // If leader designated, automatically award leadership role badge
            if ($request->leader_student_id) {
                $roleType = ($request->type === 'bench_group') ? 'bench_leader' : 'group_leader';
                $roleTitle = ($request->type === 'bench_group')
                    ? ($request->name . ' Leader')
                    : ($request->name . ' Leader');

                StudentLeadershipRole::create([
                    'school_id' => $schoolId,
                    'session_year_id' => $sessionYear->id,
                    'class_section_id' => $request->class_section_id,
                    'student_id' => $request->leader_student_id,
                    'role_type' => $roleType,
                    'title' => $roleTitle,
                    'badge_icon' => ($roleType === 'bench_leader') ? 'fa-flag' : 'fa-users',
                    'badge_color' => $request->color_code ?: '#22577A',
                    'assigned_by' => Auth::id(),
                    'valid_from' => now()->toDateString(),
                    'is_active' => true,
                ]);
            }

            // Attach group members
            if (!empty($request->student_ids) && is_array($request->student_ids)) {
                foreach ($request->student_ids as $stId) {
                    StudentGroupMember::create([
                        'student_group_id' => $group->id,
                        'student_id' => $stId,
                        'bench_number' => $request->bench_number,
                        'role_in_group' => ($stId == $request->leader_student_id) ? 'leader' : 'member',
                        'joined_at' => now()->toDateString(),
                    ]);
                }
            }

            DB::commit();
            return ResponseService::successResponse('Student group successfully created with assigned leader.');
        } catch (Throwable $e) {
            DB::rollBack();
            return ResponseService::errorResponse('Failed to create group: ' . $e->getMessage());
        }
    }

    /**
     * Delete a student group.
     */
    public function deleteGroup($id)
    {
        try {
            $group = StudentGroup::owner()->findOrFail($id);
            $group->delete();
            return ResponseService::successResponse('Student group deleted successfully.');
        } catch (Throwable $e) {
            return ResponseService::errorResponse('Failed to delete group: ' . $e->getMessage());
        }
    }
}
