<?php

namespace Tests\Feature;

use App\Models\ClassSection;
use App\Models\ClassSubject;
use App\Models\Mediums;
use App\Models\School;
use App\Models\Subject;
use App\Models\SubjectScheduleRequirement;
use App\Models\SubjectTeacher;
use App\Models\TeacherAvailability;
use App\Models\Timetable;
use App\Models\TimetableGenerationRun;
use App\Models\TimetablePeriod;
use App\Models\TimetableResource;
use App\Models\TimetableTemplate;
use App\Models\User;
use App\Services\Timetable\TimetableApplyService;
use App\Services\Timetable\TimetableFeasibilityService;
use App\Services\Timetable\TimetableGenerationService;
use App\Services\Timetable\TimetablePlanningService;
use App\Services\Timetable\TimetableRepairService;
use App\Services\Timetable\TimetableScoringService;
use App\Services\Timetable\TimetableValidationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimetableV2GenerationTest extends TestCase
{
    private int $schoolId = 3;
    private TimetablePlanningService $planningService;
    private TimetableGenerationService $generationService;
    private TimetableApplyService $applyService;
    private TimetableValidationService $validationService;
    private TimetableRepairService $repairService;

    protected function setUp(): void
    {
        parent::setUp();

        // Switch to tenant demo database
        Config::set('database.connections.school.database', 'zolo_schools_3_demo');
        DB::purge('school');
        DB::connection('school')->reconnect();
        DB::setDefaultConnection('school');

        $this->planningService = app(TimetablePlanningService::class);
        $this->generationService = app(TimetableGenerationService::class);
        $this->applyService = app(TimetableApplyService::class);
        $this->validationService = app(TimetableValidationService::class);
        $this->repairService = app(TimetableRepairService::class);

        $user = User::where('school_id', $this->schoolId)->first();
        if ($user) {
            $this->actingAs($user);
        }
    }

    public function test_tenant_db_connection_and_template_bootstrapping(): void
    {
        $template = $this->planningService->getDefaultTemplate($this->schoolId);

        $this->assertNotNull($template);
        $this->assertEquals($this->schoolId, $template->school_id);
        $this->assertNotEmpty($template->working_days);
        $this->assertGreaterThan(0, $template->periods()->count());
        $this->assertGreaterThan(0, $template->instructional_periods()->count());
    }

    public function test_slot_locking_and_unlocking(): void
    {
        $slot = Timetable::where('school_id', $this->schoolId)->first();
        if (!$slot) {
            $this->markTestSkipped('No timetable slots available in test school.');
        }

        $user = User::first();
        $userId = $user ? $user->id : null;

        // Lock slot
        $locked = $this->planningService->lockSlot($slot->id, $userId, 'Manual Test Lock');
        $this->assertTrue($locked->is_locked);
        $this->assertEquals('Manual Test Lock', $locked->lock_reason);
        $this->assertEquals($userId, $locked->locked_by);
        $this->assertNotNull($locked->locked_at);

        // Verify in DB
        $fresh = Timetable::find($slot->id);
        $this->assertTrue($fresh->is_locked);

        // Unlock slot
        $unlocked = $this->planningService->unlockSlot($slot->id);
        $this->assertFalse($unlocked->is_locked);
        $this->assertNull($unlocked->lock_reason);
        $this->assertNull($unlocked->locked_by);
    }

    public function test_validation_service_detects_teacher_conflict(): void
    {
        // Find an existing lecture
        $existing = Timetable::where('school_id', $this->schoolId)
            ->where('type', 'Lecture')
            ->whereNotNull('subject_teacher_id')
            ->first();

        if (!$existing || !$existing->subject_teacher) {
            $this->markTestSkipped('No existing lecture with subject teacher found.');
        }

        $teacherId = $existing->subject_teacher->teacher_id;

        // Try to validate a slot at the exact same day and time for another class
        $otherClassSectionId = 999999;
        $violations = $this->validationService->validateSingleSlot(
            $this->schoolId,
            $otherClassSectionId,
            $existing->day,
            $existing->start_time,
            $existing->end_time,
            $teacherId,
            null,
            null, // excludeTimetableId = null
            $existing->period_id
        );

        $this->assertNotEmpty($violations);
        $this->assertEquals('TEACHER_CONFLICT', $violations[0]['code']);
    }

    public function test_validation_service_detects_resource_conflict(): void
    {
        $resource = TimetableResource::firstOrCreate(
            ['school_id' => $this->schoolId, 'name' => 'Physics Lab Test'],
            ['type' => 'Lab', 'capacity' => 30, 'is_active' => true]
        );

        // Create a temporary slot using this resource
        $slot1 = Timetable::create([
            'school_id'        => $this->schoolId,
            'class_section_id' => 1,
            'subject_id'       => null,
            'day'              => 'Monday',
            'start_time'       => '10:00:00',
            'end_time'         => '10:45:00',
            'type'             => 'Lecture',
            'resource_id'      => $resource->id,
            'is_locked'        => false,
        ]);

        // Validate another slot requesting the same resource at overlapping time
        $violations = $this->validationService->validateSingleSlot(
            $this->schoolId,
            2,
            'Monday',
            '10:15:00',
            '11:00:00',
            null,
            $resource->id,
            null,
            null
        );

        // Clean up
        $slot1->delete();

        $this->assertNotEmpty($violations);
        $this->assertContains('RESOURCE_CONFLICT', array_column($violations, 'code'));
    }

    public function test_determinism_two_runs_produce_identical_results(): void
    {
        $classSection = ClassSection::where('school_id', $this->schoolId)->first();
        if (!$classSection) {
            $this->markTestSkipped('No class section available.');
        }

        $params = [
            'school_id'         => $this->schoolId,
            'class_section_ids' => [$classSection->id],
            'preserve_existing' => false,
            'mode'              => 'classes',
        ];

        $run1 = $this->generationService->generate($params);
        $run2 = $this->generationService->generate($params);

        $this->assertFalse($run1['error'] ?? false);
        $this->assertFalse($run2['error'] ?? false);

        // Verify identical entry count
        $this->assertEquals($run1['entries_count'], $run2['entries_count']);

        // Verify identical quality metrics
        $this->assertEquals($run1['metrics']['quality_score'], $run2['metrics']['quality_score']);

        // Verify deterministic assignments: each slot matches exactly
        $p1 = $run1['entries_preview'];
        $p2 = $run2['entries_preview'];
        $this->assertCount(count($p1), $p2);

        for ($i = 0; $i < count($p1); $i++) {
            $this->assertEquals($p1[$i]['day'], $p2[$i]['day']);
            $this->assertEquals($p1[$i]['start_time'], $p2[$i]['start_time']);
            $this->assertEquals($p1[$i]['subject_id'], $p2[$i]['subject_id']);
            $this->assertEquals($p1[$i]['teacher_id'], $p2[$i]['teacher_id']);
        }
    }

    public function test_feasibility_check_detects_overloaded_capacity(): void
    {
        $classSection = ClassSection::where('school_id', $this->schoolId)->first();
        if (!$classSection) {
            $this->markTestSkipped('No class section available.');
        }

        $template = $this->planningService->getDefaultTemplate($this->schoolId);
        $instCount = $template->instructional_periods()->count();
        $daysCount = count($template->working_days);
        $maxCapacity = $instCount * $daysCount;

        $medium = Mediums::first();

        // Create an impossible requirement: demand 100 periods in a 30-period week
        $subject = Subject::firstOrCreate(
            ['school_id' => $this->schoolId, 'name' => 'Overload Subject Test'],
            [
                'type'      => 'Theory',
                'bg_color'  => '#123456',
                'medium_id' => $medium ? $medium->id : 1,
            ]
        );

        $req = SubjectScheduleRequirement::updateOrCreate(
            [
                'school_id'        => $this->schoolId,
                'class_section_id' => $classSection->id,
                'subject_id'       => $subject->id,
            ],
            [
                'weekly_periods'      => $maxCapacity + 50, // Impossible demand!
                'min_periods_per_day' => 0,
                'max_periods_per_day' => 4,
                'allow_consecutive'   => false,
                'is_active'           => true,
            ]
        );

        $params = [
            'school_id'         => $this->schoolId,
            'class_section_ids' => [$classSection->id],
            'preserve_existing' => false,
            'mode'              => 'classes',
        ];

        $result = $this->generationService->generate($params);

        // Clean up the test requirement
        $req->delete();

        // Must fail gracefully with feasibility error before writing to live timetables
        $this->assertTrue($result['error']);
        $this->assertEquals('failed', $result['status']);
        $this->assertNotEmpty($result['conflicts']);
        $this->assertStringContainsString('CLASS_CAPACITY_EXCEEDED', $result['conflicts'][0]['code'] ?? '');
    }

    public function test_locked_slot_is_never_overwritten_by_apply(): void
    {
        $classSection = ClassSection::where('school_id', $this->schoolId)->first();
        if (!$classSection) {
            $this->markTestSkipped('No class section available.');
        }

        $template = $this->planningService->getDefaultTemplate($this->schoolId);
        $firstPeriod = $template->instructional_periods()->first();
        if (!$firstPeriod) {
            $this->markTestSkipped('No instructional period found.');
        }

        $day = $template->working_days[0];
        $medium = Mediums::first();
        $user = User::first();
        $userId = $user ? $user->id : null;

        // Create a locked slot
        $specialSubject = Subject::firstOrCreate(
            ['school_id' => $this->schoolId, 'name' => 'Special Locked Subject'],
            [
                'type'      => 'Practical',
                'bg_color'  => '#FF0000',
                'medium_id' => $medium ? $medium->id : 1,
            ]
        );

        $lockedSlot = Timetable::create([
            'school_id'        => $this->schoolId,
            'class_section_id' => $classSection->id,
            'subject_id'       => $specialSubject->id,
            'day'              => $day,
            'start_time'       => $firstPeriod->start_time,
            'end_time'         => $firstPeriod->end_time,
            'period_id'        => $firstPeriod->id,
            'type'             => 'Lecture',
            'is_locked'        => true,
            'lock_reason'      => 'Crucial Fixed Assembly',
            'locked_by'        => $userId,
            'locked_at'        => Carbon::now(),
        ]);

        try {
            // Run generation with preserve_existing => true
            $runResult = $this->generationService->generate([
                'school_id'         => $this->schoolId,
                'class_section_ids' => [$classSection->id],
                'preserve_existing' => true,
                'mode'              => 'classes',
            ]);

            if (!($runResult['error'] ?? false)) {
                // Apply the generation run
                $applyResult = $this->applyService->apply($runResult['run_id'], $this->schoolId, $userId);
                $this->assertTrue($applyResult['success']);
                $this->assertGreaterThanOrEqual(1, $applyResult['preserved_locked_count']);
            }

            // Verify the locked slot is STILL present and intact
            $verifiedSlot = Timetable::find($lockedSlot->id);
            $this->assertNotNull($verifiedSlot);
            $this->assertTrue($verifiedSlot->is_locked);
            $this->assertEquals($specialSubject->id, $verifiedSlot->subject_id);
            $this->assertEquals('Crucial Fixed Assembly', $verifiedSlot->lock_reason);
        } finally {
            // Always clean up locked slot
            $lockedSlot->delete();
        }
    }

    public function test_quick_update_rejects_locked_slot_modification(): void
    {
        $user = User::first();
        $userId = $user ? $user->id : null;
        $slot = Timetable::where('school_id', $this->schoolId)->first();
        if (!$slot || !$user) {
            $this->markTestSkipped('No timetable slot or user found.');
        }

        \Illuminate\Support\Facades\Auth::login($user);

        // Lock slot
        $this->planningService->lockSlot($slot->id, $userId, 'Test Lock Prevention');

        try {
            // Attempting to quickUpdate via controller logic
            $controller = app(\App\Http\Controllers\TimetableController::class);
            $request = new \Illuminate\Http\Request([
                'id' => $slot->id,
                'subject_id' => $slot->subject_id,
                'day' => $slot->day,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
            ]);

            $response = $controller->quickUpdate($request);
            $responseData = $response->getData(true);

            $this->assertTrue($responseData['error'] ?? false);
            $this->assertStringContainsString('locked', strtolower($responseData['message'] ?? ''));
        } finally {
            // Unlock for cleanup
            $this->planningService->unlockSlot($slot->id);
            \Illuminate\Support\Facades\Auth::logout();
        }
    }

    public function test_zero_randomness_in_timetable_services(): void
    {
        $serviceDir = app_path('Services/Timetable');
        $files = glob($serviceDir . '/*.php');
        $forbidden = ['rand(', 'mt_rand(', 'shuffle(', 'random_int('];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            foreach ($forbidden as $token) {
                $this->assertStringNotContainsString(
                    $token,
                    $content,
                    "Found forbidden pseudo-random token '{$token}' in {$file}"
                );
            }
        }
    }

    public function test_period_column_clean_titles_and_teacher_conflict_options(): void
    {
        $classSection = ClassSection::first();
        if (!$classSection) {
            $this->markTestSkipped('No class section available');
        }

        $controller = app(\App\Http\Controllers\TimetableController::class);

        // 1. Test getMatrix period titles are clean
        $matrixResponse = $controller->getMatrix($classSection->id);
        $matrixData = $matrixResponse->getData(true);
        $this->assertNotEmpty($matrixData['time_slots'] ?? []);
        foreach ($matrixData['time_slots'] as $slot) {
            // Period title should never contain subject - teacher separator
            $this->assertStringNotContainsString(' - ', $slot['title'] ?? '');
            $this->assertTrue(
                str_contains(strtolower($slot['title'] ?? ''), 'period') ||
                str_contains(strtolower($slot['title'] ?? ''), 'break') ||
                str_starts_with($slot['title'] ?? '', 'P') ||
                empty($slot['title'])
            );
        }

        // 2. Test calculateTeacherConflictOptions helper
        $teacher = User::role('Teacher')->first();
        $subject = Subject::first();
        if ($teacher && $subject) {
            $reflection = new \ReflectionClass($controller);
            $method = $reflection->getMethod('calculateTeacherConflictOptions');
            $method->setAccessible(true);

            $result = $method->invoke(
                $controller,
                $this->schoolId,
                $teacher->id,
                $subject->id,
                $classSection->id,
                'Monday',
                '09:00:00',
                '09:45:00',
                null
            );

            $this->assertArrayHasKey('conflict_details', $result);
            $this->assertArrayHasKey('options', $result);
            $this->assertArrayHasKey('alternative_slots', $result['options']);
            $this->assertArrayHasKey('alternative_teachers', $result['options']);
        }
    }
}

