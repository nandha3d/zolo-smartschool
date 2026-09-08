<?php

namespace App\Console\Commands;

use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Fills the demo school's tenant database with enough academic data to click through:
 * a class with sections and subjects, a teacher, a guardian and a student.
 *
 * Everything is written on the 'school' connection, because in this application the
 * academic tables live in each school's own database rather than the central one.
 */
class SeedDemoContent extends Command
{
    protected $signature = 'demo:content {--code=DEMO001 : School code to populate}';

    protected $description = 'Populate the demo school with classes, staff and students';

    private const PASSWORD = 'Demo@12345';

    public function handle(): int
    {
        DB::setDefaultConnection('mysql');

        $school = School::where('code', $this->option('code'))->first();

        if (!$school) {
            $this->components->error('No school with code ' . $this->option('code') . '. Run demo:setup first.');
            return self::FAILURE;
        }

        if (!$school->installed) {
            $this->components->error('That school is still provisioning (installed = 0). Wait for demo:setup to finish.');
            return self::FAILURE;
        }

        // Switch to the tenant database for the rest of this command.
        Config::set('database.connections.school.database', $school->database_name);
        DB::purge('school');
        DB::connection('school')->reconnect();
        DB::setDefaultConnection('school');

        $this->components->info('Populating ' . $school->name . ' (' . $school->database_name . ')');

        // SchoolDataService::preSettingsSetup() copies the administrator into the
        // tenant but only marks them verified when the school type is "demo". Without
        // this the admin signs in and is bounced to /email/verify on every route.
        DB::table('users')
            ->where('id', $school->admin_id)
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now(), 'must_change_password' => 0]);

        $sessionYearId = DB::table('session_years')->where('school_id', $school->id)->value('id')
            ?? DB::table('session_years')->insertGetId([
                'name' => Carbon::now()->format('Y'),
                'default' => 1,
                'start_date' => Carbon::now()->startOfYear(),
                'end_date' => Carbon::now()->endOfYear(),
                'school_id' => $school->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);

        $mediumId  = $this->upsert('mediums',  ['name' => 'English', 'school_id' => $school->id]);
        $sectionId = $this->upsert('sections', ['name' => 'A', 'school_id' => $school->id]);

        $classId = $this->upsert('classes', [
            'name' => 'Grade 1', 'medium_id' => $mediumId, 'school_id' => $school->id,
        ]);

        $classSectionId = $this->upsert('class_sections', [
            'class_id' => $classId, 'section_id' => $sectionId,
            'medium_id' => $mediumId, 'school_id' => $school->id,
        ]);

        $subjectIds = [];
        foreach ([['Mathematics', 'MATH01', '#4e79a7'], ['English', 'ENG01', '#59a14f'], ['Science', 'SCI01', '#e15759']] as [$name, $code, $colour]) {
            $subjectIds[$name] = $this->upsert('subjects', [
                'name' => $name, 'code' => $code, 'bg_color' => $colour,
                'medium_id' => $mediumId, 'type' => 'Compulsory', 'school_id' => $school->id,
            ]);
            $this->upsert('class_subjects', [
                'class_id' => $classId, 'subject_id' => $subjectIds[$name],
                'type' => 'Compulsory', 'school_id' => $school->id,
            ]);
        }

        $this->components->twoColumnDetail('Academic structure', 'Grade 1 - A, 3 subjects');

        $teacher  = $this->makeUser($school, 'Demo', 'Teacher',  'teacher@demo.test', '9000000002', 'Teacher');
        $guardian = $this->makeUser($school, 'Demo', 'Parent',   'parent@demo.test',  '9000000003', 'Guardian');
        $student  = $this->makeUser($school, 'Demo', 'Student',  'DEMO2026001',       '9000000004', 'Student');

        // Staff row so the teacher resolves in the staff app.
        if (!DB::table('staffs')->where('user_id', $teacher)->exists()) {
            DB::table('staffs')->insert([
                'user_id' => $teacher, 'qualification' => 'B.Ed', 'salary' => 30000,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->linkTeacher($school, $teacher, $classSectionId, $subjectIds);

        if (!DB::table('students')->where('user_id', $student)->exists()) {
            DB::table('students')->insert([
                'user_id' => $student,
                'class_section_id' => $classSectionId,
                'admission_no' => 'DEMO2026001',
                'roll_number' => 1,
                'admission_date' => Carbon::now()->toDateString(),
                'school_id' => $school->id,
                'guardian_id' => $guardian,
                'session_year_id' => $sessionYearId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->components->twoColumnDetail('Accounts', 'teacher, guardian and student created');

        DB::setDefaultConnection('mysql');

        $this->newLine();
        $this->components->info('Demo logins - password is the same for all: ' . self::PASSWORD);
        $this->table(
            ['Role', 'Sign in with', 'Where'],
            [
                ['Super Admin',  'superadmin@gmail.com', 'Web, no school code'],
                ['School Admin', 'admin@demo.test',      'Web, school code DEMO001'],
                ['Teacher',      'teacher@demo.test',    'Web + staff app, code DEMO001'],
                ['Guardian',     'parent@demo.test',     'Student/parent app, code DEMO001'],
                ['Student',      'DEMO2026001',          'Student/parent app, code DEMO001'],
            ]
        );
        $this->newLine();

        return self::SUCCESS;
    }

    /** Insert the row if an identical one is not already present, and return its id. */
    private function upsert(string $table, array $attributes): int
    {
        $existing = DB::table($table)->where($attributes)->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table($table)->insertGetId(
            $attributes + ['created_at' => now(), 'updated_at' => now()]
        );
    }

    /**
     * Students sign in with their admission number, which the application stores in
     * the email column - hence $email carrying an admission number for that role.
     */
    private function makeUser(School $school, string $first, string $last, string $email, string $mobile, string $roleName): int
    {
        $id = DB::table('users')->where('email', $email)->value('id');

        if (!$id) {
            $id = DB::table('users')->insertGetId([
                'first_name' => $first,
                'last_name'  => $last,
                'email'      => $email,
                'mobile'     => $mobile,
                'gender'     => 'male',
                'dob'        => '2015-01-01',
                'password'   => Hash::make(self::PASSWORD),
                // Demo accounts sign straight in rather than landing on the change
                // password screen.
                'must_change_password' => 0,
                'school_id'  => $school->id,
                'status'     => 1,
                'image'      => 'logo.svg',
                'email_verified_at' => now(),
                'two_factor_enabled' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $role = Role::where('name', $roleName)->where('school_id', $school->id)->first()
            ?? Role::where('name', $roleName)->first();

        if ($role && !DB::table('model_has_roles')->where(['role_id' => $role->id, 'model_id' => $id])->exists()) {
            DB::table('model_has_roles')->insert([
                'role_id' => $role->id,
                'model_type' => 'App\Models\User',
                'model_id' => $id,
            ]);
        }

        return (int) $id;
    }

    /** Best-effort: these join tables vary between versions, so failures are not fatal. */
    private function linkTeacher(School $school, int $teacherId, int $classSectionId, array $subjectIds): void
    {
        try {
            if (!DB::table('class_teachers')->where(['class_section_id' => $classSectionId, 'teacher_id' => $teacherId])->exists()) {
                DB::table('class_teachers')->insert([
                    'class_section_id' => $classSectionId,
                    'teacher_id' => $teacherId,
                    'school_id' => $school->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            foreach ($subjectIds as $subjectId) {
                // subject_teachers keys off class_subjects, not the subject directly.
                $classSubjectId = DB::table('class_subjects')
                    ->where('subject_id', $subjectId)
                    ->value('id');

                if (!$classSubjectId) {
                    continue;
                }

                if (!DB::table('subject_teachers')->where(['class_section_id' => $classSectionId, 'class_subject_id' => $classSubjectId, 'teacher_id' => $teacherId])->exists()) {
                    DB::table('subject_teachers')->insert([
                        'class_section_id' => $classSectionId,
                        'subject_id' => $subjectId,
                        'class_subject_id' => $classSubjectId,
                        'teacher_id' => $teacherId,
                        'school_id' => $school->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $this->components->warn('Teacher class/subject links skipped: ' . $e->getMessage());
        }
    }
}
