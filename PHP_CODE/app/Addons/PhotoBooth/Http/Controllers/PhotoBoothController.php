<?php

namespace App\Addons\PhotoBooth\Http\Controllers;

use App\Addons\PhotoBooth\PhotoBoothServiceProvider;
use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Models\Students;
use App\Services\ResponseService;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PhotoBoothController extends Controller
{
    public function index()
    {
        ResponseService::noFeatureThenRedirect(PhotoBoothServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('student-edit');

        $class_sections = ClassSection::with('class', 'class.stream', 'section', 'medium')->get();

        return view('photobooth::index', compact('class_sections'));
    }

    /**
     * Roster for one class section, with each student's current photo state so the
     * operator can see at a glance who still needs one.
     */
    public function roster(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(PhotoBoothServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('student-edit');

        $request->validate(['class_section_id' => 'required|integer']);

        try {
            $students = Students::with('user:id,first_name,last_name,image')
                ->where('class_section_id', $request->class_section_id)
                ->get()
                ->map(static fn($student) => [
                    'student_id' => $student->id,
                    'user_id'    => $student->user_id,
                    'name'       => trim(($student->user->first_name ?? '') . ' ' . ($student->user->last_name ?? '')),
                    'roll_number' => $student->roll_number,
                    // The accessor returns the placeholder when nothing is stored, so
                    // compare against the raw column to know whether a real photo exists.
                    'has_photo'  => (bool) $student->user?->getRawOriginal('image'),
                    'image'      => $student->user->image ?? null,
                ])
                ->sortBy('roll_number')
                ->values();

            return response()->json(['error' => false, 'data' => $students]);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'PhotoBoothController -> roster');
            return response()->json(['error' => true, 'message' => trans('error_occurred')], 500);
        }
    }

    /**
     * Store one captured photograph against a student's user record.
     *
     * The student is looked up in the tenant connection the request is already
     * switched to, so a user_id from another school simply will not resolve.
     */
    public function store(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(PhotoBoothServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('student-edit');

        $request->validate([
            'student_id' => 'required|integer',
            // Comes off a canvas as a JPEG blob. Capped well above a portrait crop
            // but far below anything worth using as an upload primitive.
            'photo'      => 'required|image|mimes:jpeg,jpg,png|max:4096',
        ]);

        try {
            $student = Students::with('user')->findOrFail($request->student_id);
            $user = $student->user;

            if (!$user) {
                return response()->json(['error' => true, 'message' => trans('no_data_found')], 404);
            }

            $previous = $user->getRawOriginal('image');

            DB::beginTransaction();
            // UploadService re-encodes the image and scopes the path to this school,
            // which is also what strips anything smuggled inside the container.
            $user->image = UploadService::upload($request->file('photo'), 'user');
            $user->save();
            DB::commit();

            // Only after the new path is committed, so a failure above can never
            // leave the student with no photo at all.
            if ($previous) {
                UploadService::delete($previous);
            }

            return response()->json([
                'error'   => false,
                'message' => trans('data_update_successfully'),
                'image'   => $user->fresh()->image,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'PhotoBoothController -> store');
            return response()->json(['error' => true, 'message' => trans('error_occurred')], 500);
        }
    }
}
