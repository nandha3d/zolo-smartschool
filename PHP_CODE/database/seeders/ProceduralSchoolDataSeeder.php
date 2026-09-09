<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProceduralSchoolDataSeeder extends Seeder
{
    public function run()
    {
        $schoolId = 3;
        $dbName = 'zolo_schools_3_demo';

        echo "Switching database connection to $dbName...\n";
        Config::set('database.connections.school.database', $dbName);
        DB::purge('school');
        $db = DB::connection('school');

        $db->statement('SET FOREIGN_KEY_CHECKS=0;');

        $sessionYear = $db->table('session_years')->where('default', 1)->first();
        if (!$sessionYear) {
            $sessionYearId = $db->table('session_years')->insertGetId([
                'name' => '2025-2026',
                'default' => 1,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'school_id' => $schoolId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sessionYear = $db->table('session_years')->where('id', $sessionYearId)->first();
        }

        // 1. Medium
        $medium = $db->table('mediums')->first();
        if (!$medium) {
            $mediumId = $db->table('mediums')->insertGetId([
                'name' => 'English',
                'school_id' => $schoolId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $mediumId = $medium->id;
        }

        // 2. Sections (A and B)
        $sectionNames = ['A', 'B'];
        $sectionIds = [];
        foreach ($sectionNames as $sName) {
            $sRow = $db->table('sections')->where('name', $sName)->where('school_id', $schoolId)->first();
            if (!$sRow) {
                $sectionIds[$sName] = $db->table('sections')->insertGetId([
                    'name' => $sName,
                    'school_id' => $schoolId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $sectionIds[$sName] = $sRow->id;
            }
        }

        // 3. Streams (Science, Commerce, Arts)
        $streamNames = ['Science', 'Commerce', 'Arts'];
        $streamIds = [];
        foreach ($streamNames as $stName) {
            $stRow = $db->table('streams')->where('name', $stName)->where('school_id', $schoolId)->first();
            if (!$stRow) {
                $streamIds[$stName] = $db->table('streams')->insertGetId([
                    'name' => $stName,
                    'school_id' => $schoolId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $streamIds[$stName] = $stRow->id;
            }
        }

        // 4. Classes (Class 1 to 12)
        $classDefinitions = [
            ['name' => 'Class 1', 'stream_id' => null],
            ['name' => 'Class 2', 'stream_id' => null],
            ['name' => 'Class 3', 'stream_id' => null],
            ['name' => 'Class 4', 'stream_id' => null],
            ['name' => 'Class 5', 'stream_id' => null],
            ['name' => 'Class 6', 'stream_id' => null],
            ['name' => 'Class 7', 'stream_id' => null],
            ['name' => 'Class 8', 'stream_id' => null],
            ['name' => 'Class 9', 'stream_id' => null],
            ['name' => 'Class 10', 'stream_id' => null],
            ['name' => 'Class 11 Science', 'stream_id' => $streamIds['Science']],
            ['name' => 'Class 11 Commerce', 'stream_id' => $streamIds['Commerce']],
            ['name' => 'Class 12 Science', 'stream_id' => $streamIds['Science']],
            ['name' => 'Class 12 Commerce', 'stream_id' => $streamIds['Commerce']],
        ];

        // Ensure "Grade 1" from default install is renamed to "Class 1" if present
        $db->table('classes')->where('name', 'Grade 1')->update(['name' => 'Class 1']);

        $classIds = [];
        foreach ($classDefinitions as $cDef) {
            $cRow = $db->table('classes')->where('name', $cDef['name'])->where('school_id', $schoolId)->first();
            if (!$cRow) {
                $classIds[$cDef['name']] = $db->table('classes')->insertGetId([
                    'name' => $cDef['name'],
                    'medium_id' => $mediumId,
                    'stream_id' => $cDef['stream_id'],
                    'include_semesters' => 0,
                    'school_id' => $schoolId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $db->table('classes')->where('id', $cRow->id)->update(['stream_id' => $cDef['stream_id']]);
                $classIds[$cDef['name']] = $cRow->id;
            }
        }

        // 5. Class Sections (Class x Section A & B)
        $classSectionMap = []; // [class_name][section_name] => id
        foreach ($classIds as $className => $cId) {
            foreach ($sectionNames as $sName) {
                $sId = $sectionIds[$sName];
                $csRow = $db->table('class_sections')->where('class_id', $cId)->where('section_id', $sId)->where('school_id', $schoolId)->first();
                if (!$csRow) {
                    $classSectionMap[$className][$sName] = $db->table('class_sections')->insertGetId([
                        'class_id' => $cId,
                        'section_id' => $sId,
                        'medium_id' => $mediumId,
                        'school_id' => $schoolId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $classSectionMap[$className][$sName] = $csRow->id;
                }
            }
        }

        echo "Created " . count($classIds) . " classes and class sections.\n";

        // 6. Subjects & Class Subjects
        $subjectsData = [
            ['name' => 'English', 'code' => 'ENG', 'type' => 'Compulsory', 'bg' => '#4361EE'],
            ['name' => 'Mathematics', 'code' => 'MATH', 'type' => 'Compulsory', 'bg' => '#3A0CA3'],
            ['name' => 'Science', 'code' => 'SCI', 'type' => 'Compulsory', 'bg' => '#4CC9F0'],
            ['name' => 'Social Studies', 'code' => 'SST', 'type' => 'Compulsory', 'bg' => '#F72585'],
            ['name' => 'Hindi', 'code' => 'HIN', 'type' => 'Compulsory', 'bg' => '#7209B7'],
            ['name' => 'Physics', 'code' => 'PHY', 'type' => 'Compulsory', 'bg' => '#2A9D8F'],
            ['name' => 'Chemistry', 'code' => 'CHEM', 'type' => 'Compulsory', 'bg' => '#E76F51'],
            ['name' => 'Biology', 'code' => 'BIO', 'type' => 'Compulsory', 'bg' => '#52B788'],
            ['name' => 'Accountancy', 'code' => 'ACC', 'type' => 'Compulsory', 'bg' => '#D4A373'],
            ['name' => 'Economics', 'code' => 'ECON', 'type' => 'Compulsory', 'bg' => '#457B9D'],
            ['name' => 'Computer Science', 'code' => 'CS', 'type' => 'Compulsory', 'bg' => '#1D3557'],
        ];

        $subjectIds = [];
        foreach ($subjectsData as $s) {
            $subRow = $db->table('subjects')->where('name', $s['name'])->where('school_id', $schoolId)->first();
            if (!$subRow) {
                $subjectIds[$s['name']] = $db->table('subjects')->insertGetId([
                    'name' => $s['name'],
                    'code' => $s['code'],
                    'type' => $s['type'],
                    'bg_color' => $s['bg'],
                    'medium_id' => $mediumId,
                    'school_id' => $schoolId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $subjectIds[$s['name']] = $subRow->id;
            }
        }

        // Link Class Subjects
        $classSubjectMap = []; // [class_name][subject_name] => id
        foreach ($classIds as $className => $cId) {
            $assignedSubjects = ['English', 'Mathematics'];
            if (str_contains($className, '11') || str_contains($className, '12')) {
                if (str_contains($className, 'Science')) {
                    $assignedSubjects = array_merge($assignedSubjects, ['Physics', 'Chemistry', 'Biology', 'Computer Science']);
                } else {
                    $assignedSubjects = array_merge($assignedSubjects, ['Accountancy', 'Economics', 'Computer Science']);
                }
            } else {
                $assignedSubjects = array_merge($assignedSubjects, ['Science', 'Social Studies', 'Hindi', 'Computer Science']);
            }

            foreach ($assignedSubjects as $subName) {
                $subId = $subjectIds[$subName];
                $csRow = $db->table('class_subjects')->where('class_id', $cId)->where('subject_id', $subId)->where('school_id', $schoolId)->first();
                if (!$csRow) {
                    $classSubjectMap[$className][$subName] = $db->table('class_subjects')->insertGetId([
                        'class_id' => $cId,
                        'subject_id' => $subId,
                        'type' => 'Compulsory',
                        'school_id' => $schoolId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $classSubjectMap[$className][$subName] = $csRow->id;
                }
            }
        }

        // 7. Teachers (24 Teachers to match the design metric "24 Teachers")
        $teachersData = [
            ['first' => 'Priya', 'last' => 'Menon', 'gender' => 'female', 'sub' => 'English'],
            ['first' => 'Rahul', 'last' => 'Sharma', 'gender' => 'male', 'sub' => 'Mathematics'],
            ['first' => 'Anita', 'last' => 'Das', 'gender' => 'female', 'sub' => 'Science'],
            ['first' => 'Vikram', 'last' => 'Singh', 'gender' => 'male', 'sub' => 'Physics'],
            ['first' => 'Neha', 'last' => 'Patel', 'gender' => 'female', 'sub' => 'Chemistry'],
            ['first' => 'Suresh', 'last' => 'Kumar', 'gender' => 'male', 'sub' => 'Biology'],
            ['first' => 'Rajesh', 'last' => 'Iyer', 'gender' => 'male', 'sub' => 'Accountancy'],
            ['first' => 'Pooja', 'last' => 'Nair', 'gender' => 'female', 'sub' => 'Economics'],
            ['first' => 'Sunita', 'last' => 'Gupta', 'gender' => 'female', 'sub' => 'Hindi'],
            ['first' => 'Arvind', 'last' => 'Mehta', 'gender' => 'male', 'sub' => 'Computer Science'],
            ['first' => 'Manoj', 'last' => 'Tiwari', 'gender' => 'male', 'sub' => 'Social Studies'],
            ['first' => 'Sneha', 'last' => 'Reddy', 'gender' => 'female', 'sub' => 'Mathematics'],
            ['first' => 'Kavita', 'last' => 'Joshi', 'gender' => 'female', 'sub' => 'English'],
            ['first' => 'Amit', 'last' => 'Verma', 'gender' => 'male', 'sub' => 'Physics'],
            ['first' => 'Deepak', 'last' => 'Chopra', 'gender' => 'male', 'sub' => 'Chemistry'],
            ['first' => 'Divya', 'last' => 'Pillai', 'gender' => 'female', 'sub' => 'Biology'],
            ['first' => 'Gaurav', 'last' => 'Bhatia', 'gender' => 'male', 'sub' => 'Computer Science'],
            ['first' => 'Ritu', 'last' => 'Saxena', 'gender' => 'female', 'sub' => 'Hindi'],
            ['first' => 'Anil', 'last' => 'Kapoor', 'gender' => 'male', 'sub' => 'Social Studies'],
            ['first' => 'Meenakshi', 'last' => 'Sundaram', 'gender' => 'female', 'sub' => 'Mathematics'],
            ['first' => 'Tarun', 'last' => 'Ghosh', 'gender' => 'male', 'sub' => 'Economics'],
            ['first' => 'Swati', 'last' => 'Deshmukh', 'gender' => 'female', 'sub' => 'Science'],
            ['first' => 'Sanjay', 'last' => 'Rathore', 'gender' => 'male', 'sub' => 'Accountancy'],
            ['first' => 'Preeti', 'last' => 'Bose', 'gender' => 'female', 'sub' => 'English'],
        ];

        $teacherUserIds = [];
        $teacherRole = $db->table('roles')->where('name', 'Teacher')->where('school_id', $schoolId)->first();
        $teacherRoleId = $teacherRole ? $teacherRole->id : 4;

        foreach ($teachersData as $idx => $t) {
            $email = strtolower($t['first'] . '.' . $t['last'] . '@demo.test');
            // If already existing teacher@demo.test
            if ($idx === 0 && $db->table('users')->where('email', 'teacher@demo.test')->exists()) {
                $email = 'teacher@demo.test';
            }

            $uRow = $db->table('users')->where('email', $email)->first();
            if (!$uRow) {
                $tId = $db->table('users')->insertGetId([
                    'first_name' => $t['first'],
                    'last_name' => $t['last'],
                    'email' => $email,
                    'password' => Hash::make('Demo@12345'),
                    'mobile' => '9876543' . str_pad($idx, 3, '0', STR_PAD_LEFT),
                    'gender' => $t['gender'],
                    'dob' => '1985-05-15',
                    'school_id' => $schoolId,
                    'status' => 1,
                    'two_factor_enabled' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $db->table('model_has_roles')->insertOrIgnore([
                    'role_id' => $teacherRoleId,
                    'model_type' => 'App\Models\User',
                    'model_id' => $tId,
                ]);

                $db->table('staffs')->insertOrIgnore([
                    'user_id' => $tId,
                    'qualification' => 'M.Sc, B.Ed',
                    'salary' => 45000 + ($idx * 1200),
                    'joining_date' => '2022-06-01',
                    'session_year_id' => $sessionYear->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $tId = $uRow->id;
                $db->table('model_has_roles')->insertOrIgnore([
                    'role_id' => $teacherRoleId,
                    'model_type' => 'App\Models\User',
                    'model_id' => $tId,
                ]);
            }

            $teacherUserIds[] = $tId;
        }

        // Assign Class Teachers to Class Sections
        $flatClassSectionIds = [];
        foreach ($classSectionMap as $cName => $secs) {
            foreach ($secs as $sName => $csId) {
                $flatClassSectionIds[] = $csId;
            }
        }

        foreach ($flatClassSectionIds as $idx => $csId) {
            $assignedTeacherId = $teacherUserIds[$idx % count($teacherUserIds)];
            $db->table('class_teachers')->updateOrInsert(
                ['class_section_id' => $csId, 'school_id' => $schoolId],
                ['teacher_id' => $assignedTeacherId, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        echo "Created/verified " . count($teacherUserIds) . " teachers.\n";

        // 8. Guardians & Students
        // Target: ~480 students across 28 class sections (approx 17-18 per section)
        $guardianRole = $db->table('roles')->where('name', 'Guardian')->where('school_id', $schoolId)->first();
        $guardianRoleId = $guardianRole ? $guardianRole->id : 2;

        $studentRole = $db->table('roles')->where('name', 'Student')->where('school_id', $schoolId)->first();
        $studentRoleId = $studentRole ? $studentRole->id : 3;

        $boyFirstNames = ['Aarav', 'Kabir', 'Rohan', 'Vivaan', 'Advait', 'Aditya', 'Reyansh', 'Shaurya', 'Arjun', 'Sai', 'Dev', 'Manav', 'Ishaan', 'Aryan', 'Samar', 'Krishna', 'Kunal', 'Dhruv', 'Yash', 'Parth', 'Harsh', 'Mohit', 'Nikhil', 'Varun'];
        $girlFirstNames = ['Ishita', 'Ananya', 'Riya', 'Diya', 'Meera', 'Tanvi', 'Saanvi', 'Myra', 'Ira', 'Avani', 'Tara', 'Kiara', 'Navya', 'Anika', 'Siya', 'Pari', 'Prisha', 'Aditi', 'Shreya', 'Pooja', 'Rhea', 'Sneha', 'Simran', 'Kritika'];
        $lastNames = ['Sharma', 'Nair', 'Shah', 'Patel', 'Gupta', 'Iyer', 'Singh', 'Reddy', 'Verma', 'Joshi', 'Chopra', 'Das', 'Roy', 'Mehta', 'Bose', 'Kumar', 'Mishra', 'Pandey', 'Sen', 'Pillai', 'Rao', 'Bhat', 'Menon', 'Hegde'];

        $existingStudentCount = $db->table('students')->where('school_id', $schoolId)->count();
        $targetTotal = 486; // Match mockup exactly!
        $studentsToCreate = max(0, $targetTotal - $existingStudentCount);

        echo "Current students: $existingStudentCount. Generating $studentsToCreate students...\n";

        $studentUserRecords = [];
        $guardianCache = []; // Cache guardians to reuse for siblings
        $studentIndex = $existingStudentCount + 1;

        $allCreatedStudentUserIds = $db->table('students')->where('school_id', $schoolId)->pluck('user_id')->toArray();
        $studentsPerSection = ceil($studentsToCreate / count($flatClassSectionIds));

        $currCsIdx = 0;
        for ($i = 0; $i < $studentsToCreate; $i++) {
            $isBoy = ($i % 2 === 0);
            $firstName = $isBoy ? $boyFirstNames[$i % count($boyFirstNames)] : $girlFirstNames[$i % count($girlFirstNames)];
            $lastName = $lastNames[($i + intdiv($i, 10)) % count($lastNames)];
            $gender = $isBoy ? 'male' : 'female';
            $admNo = 'DEMO2026' . str_pad($studentIndex, 3, '0', STR_PAD_LEFT);
            $email = strtolower($firstName . '.' . $admNo . '@student.demo.test');

            // Guardian: every ~1.2 students shares a guardian
            $gIndex = intdiv($i, 2);
            if (!isset($guardianCache[$gIndex])) {
                $gEmail = 'parent' . $gIndex . '@demo.test';
                $gUser = $db->table('users')->where('email', $gEmail)->first();
                if (!$gUser) {
                    $gId = $db->table('users')->insertGetId([
                        'first_name' => 'Guardian of',
                        'last_name' => $lastName,
                        'email' => $gEmail,
                        'password' => Hash::make('Demo@12345'),
                        'mobile' => '9123456' . str_pad($gIndex, 3, '0', STR_PAD_LEFT),
                        'gender' => 'male',
                        'school_id' => $schoolId,
                        'status' => 1,
                        'two_factor_enabled' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $db->table('model_has_roles')->insertOrIgnore([
                        'role_id' => $guardianRoleId,
                        'model_type' => 'App\Models\User',
                        'model_id' => $gId,
                    ]);
                    $guardianCache[$gIndex] = $gId;
                } else {
                    $guardianCache[$gIndex] = $gUser->id;
                }
            }
            $guardianId = $guardianCache[$gIndex];

            // Student user
            $sUserId = $db->table('users')->insertGetId([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password' => Hash::make('Demo@12345'),
                'mobile' => '9988776' . str_pad($studentIndex, 3, '0', STR_PAD_LEFT),
                'gender' => $gender,
                'dob' => Carbon::now()->subYears(6 + ($currCsIdx % 12))->format('Y-m-d'),
                'school_id' => $schoolId,
                'status' => 1,
                'two_factor_enabled' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $db->table('model_has_roles')->insertOrIgnore([
                'role_id' => $studentRoleId,
                'model_type' => 'App\Models\User',
                'model_id' => $sUserId,
            ]);

            $csId = $flatClassSectionIds[$currCsIdx % count($flatClassSectionIds)];
            $csRow = $db->table('class_sections')->where('id', $csId)->first();
            $rollNo = ($i % 25) + 1;

            $db->table('students')->insert([
                'user_id' => $sUserId,
                'class_id' => $csRow->class_id,
                'class_section_id' => $csId,
                'admission_no' => $admNo,
                'roll_number' => $rollNo,
                'admission_date' => '2026-04-01',
                'school_id' => $schoolId,
                'application_status' => 1,
                'guardian_id' => $guardianId,
                'session_year_id' => $sessionYear->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $allCreatedStudentUserIds[] = $sUserId;
            $studentIndex++;
            if ($i % 17 === 0 && $i > 0) {
                $currCsIdx++;
            }
        }

        echo "Students generated. Total active student users: " . count($allCreatedStudentUserIds) . "\n";

        // 9. Attendance (Today and past 5 days, 88% Present, 8% Absent, 4% Half day)
        echo "Generating attendance data...\n";
        $attendanceDates = [
            Carbon::now()->format('Y-m-d'),
            Carbon::now()->subDays(1)->format('Y-m-d'),
            Carbon::now()->subDays(2)->format('Y-m-d'),
            Carbon::now()->subDays(3)->format('Y-m-d'),
            Carbon::now()->subDays(4)->format('Y-m-d'),
        ];

        // Fetch all students with class_section_id
        $studentsList = $db->table('students')->where('school_id', $schoolId)->get(['user_id', 'class_section_id']);

        $attendanceBatch = [];
        foreach ($attendanceDates as $date) {
            // Check if attendance already exists for this date
            $hasAttendance = $db->table('attendances')->where('date', $date)->where('school_id', $schoolId)->exists();
            if ($hasAttendance) {
                continue;
            }

            foreach ($studentsList as $st) {
                $hash = crc32($st->user_id . $date);
                $mod = abs($hash) % 100;
                $type = 1; // Present (88%)
                if ($mod < 8) {
                    $type = 0; // Absent
                } elseif ($mod < 12) {
                    $type = 2; // Half day
                }

                $attendanceBatch[] = [
                    'class_section_id' => $st->class_section_id,
                    'student_id' => $st->user_id,
                    'session_year_id' => $sessionYear->id,
                    'type' => $type,
                    'date' => $date,
                    'remark' => '',
                    'school_id' => $schoolId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($attendanceBatch) >= 1000) {
                    $db->table('attendances')->insert($attendanceBatch);
                    $attendanceBatch = [];
                }
            }
        }
        if (!empty($attendanceBatch)) {
            $db->table('attendances')->insert($attendanceBatch);
        }
        echo "Attendance records seeded.\n";

        // 10. Expenses (Monthly distribution from Apr 2026 to Sep 2026 to match ₹18,42,000 mockup)
        echo "Seeding expenses...\n";
        $expenseCategories = [
            'Infrastructure & Maintenance',
            'Laboratory Equipment',
            'Sports & Cultural Events',
            'Utilities & Electricity',
            'Software & IT Licenses',
            'Office & Classroom Supplies',
        ];

        $expCatIds = [];
        foreach ($expenseCategories as $cName) {
            $catRow = $db->table('expense_categories')->where('name', $cName)->where('school_id', $schoolId)->first();
            if (!$catRow) {
                $expCatIds[$cName] = $db->table('expense_categories')->insertGetId([
                    'name' => $cName,
                    'school_id' => $schoolId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $expCatIds[$cName] = $catRow->id;
            }
        }

        // Monthly targets matching the mockup:
        // Apr: 2,10,000 | May: 2,45,000 | Jun: 1,32,000 | Jul: 2,80,000 | Aug: 2,12,000 | Sep: 1,83,000 | Oct-Mar budgeted
        $monthlyExpenses = [
            ['month' => 4, 'title' => 'Campus Renovation & Annual Painting', 'amount' => 125000, 'cat' => 'Infrastructure & Maintenance'],
            ['month' => 4, 'title' => 'New Academic Year Textbook Printing', 'amount' => 85000, 'cat' => 'Office & Classroom Supplies'],
            ['month' => 5, 'title' => 'Science Lab Chemical & Glassware Restock', 'amount' => 140000, 'cat' => 'Laboratory Equipment'],
            ['month' => 5, 'title' => 'Campus High-Speed Internet & Server Hosting', 'amount' => 105000, 'cat' => 'Software & IT Licenses'],
            ['month' => 6, 'title' => 'Mid-Summer Facility Power & AC Maintenance', 'amount' => 132000, 'cat' => 'Utilities & Electricity'],
            ['month' => 7, 'title' => 'Sports Day Ground Preparation & Equipment', 'amount' => 160000, 'cat' => 'Sports & Cultural Events'],
            ['month' => 7, 'title' => 'Computer Lab Desktop Upgrades (10 units)', 'amount' => 120000, 'cat' => 'Software & IT Licenses'],
            ['month' => 8, 'title' => 'Independence Day Cultural Event Expenses', 'amount' => 95000, 'cat' => 'Sports & Cultural Events'],
            ['month' => 8, 'title' => 'Monthly Generator Fuel & Electrical Overhaul', 'amount' => 117000, 'cat' => 'Utilities & Electricity'],
            ['month' => 9, 'title' => 'Term 1 Exam Printing & Stationery Kits', 'amount' => 88000, 'cat' => 'Office & Classroom Supplies'],
            ['month' => 9, 'title' => 'Digital Smart Board Licenses (Term 2)', 'amount' => 95000, 'cat' => 'Software & IT Licenses'], // Total Sep: 1,83,000 (Matches mockup blue bar!)
            ['month' => 10, 'title' => 'Library Encyclopedia & Reference Books', 'amount' => 145000, 'cat' => 'Infrastructure & Maintenance'],
            ['month' => 11, 'title' => 'Annual Science Fair Pavilion Setup', 'amount' => 175000, 'cat' => 'Laboratory Equipment'],
            ['month' => 12, 'title' => 'Winter Heating & Facility Management', 'amount' => 110000, 'cat' => 'Utilities & Electricity'],
        ];

        // Clean existing test expenses to align graph perfectly
        $db->table('expenses')->where('school_id', $schoolId)->delete();

        foreach ($monthlyExpenses as $idx => $exp) {
            $expDate = Carbon::create(2026, $exp['month'], 10 + ($idx % 15))->format('Y-m-d');
            $db->table('expenses')->insert([
                'category_id' => $expCatIds[$exp['cat']],
                'ref_no' => 'EXP-2026-' . str_pad($idx + 1, 3, '0', STR_PAD_LEFT),
                'title' => $exp['title'],
                'amount' => $exp['amount'],
                'date' => $expDate,
                'month' => $exp['month'],
                'year' => 2026,
                'school_id' => $schoolId,
                'session_year_id' => $sessionYear->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        echo "Expenses seeded.\n";

        // 11. Fees (Compulsory fees and payments for 64% Paid, 22% Partial, 14% Overdue)
        echo "Seeding fees and payments...\n";
        $feeTypes = [
            'Tuition Fee',
            'Annual Development Fee',
            'Computer & Lab Fee',
            'Sports & Activity Fee'
        ];
        $fTypeIds = [];
        foreach ($feeTypes as $ftName) {
            $ftRow = $db->table('fees_types')->where('name', $ftName)->where('school_id', $schoolId)->first();
            if (!$ftRow) {
                $fTypeIds[$ftName] = $db->table('fees_types')->insertGetId([
                    'name' => $ftName,
                    'description' => $ftName . ' for academic year',
                    'school_id' => $schoolId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $fTypeIds[$ftName] = $ftRow->id;
            }
        }

        // Create Fee structure for classes
        $dueDate = Carbon::now()->subDays(18)->format('Y-m-d'); // 18 days overdue for overdue students!
        $classFeeMap = []; // [class_id] => fee_id

        foreach ($classIds as $className => $cId) {
            $fRow = $db->table('fees')->where('class_id', $cId)->where('session_year_id', $sessionYear->id)->where('school_id', $schoolId)->first();
            if (!$fRow) {
                $feeId = $db->table('fees')->insertGetId([
                    'name' => 'Annual Composite Fee - ' . $className,
                    'due_date' => $dueDate,
                    'due_charges' => 200,
                    'due_charges_amount' => 200,
                    'class_id' => $cId,
                    'school_id' => $schoolId,
                    'session_year_id' => $sessionYear->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $feeId = $fRow->id;
            }
            $classFeeMap[$cId] = $feeId;

            // Compulsory fee components
            $components = [
                ['type' => 'Tuition Fee', 'amt' => 14000],
                ['type' => 'Annual Development Fee', 'amt' => 3000],
                ['type' => 'Computer & Lab Fee', 'amt' => 2000],
            ];
            foreach ($components as $comp) {
                $db->table('fees_class_types')->updateOrInsert(
                    ['class_id' => $cId, 'fees_id' => $feeId, 'fees_type_id' => $fTypeIds[$comp['type']], 'school_id' => $schoolId],
                    ['amount' => $comp['amt'], 'optional' => 0, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        // Seed Fees Paid (64% fully paid, 20% partial, 16% overdue)
        $db->table('fees_paids')->where('school_id', $schoolId)->delete();
        $feesPaidBatch = [];

        foreach ($studentsList as $sIdx => $st) {
            $csRow = $db->table('class_sections')->where('id', $st->class_section_id)->first();
            if (!$csRow || !isset($classFeeMap[$csRow->class_id])) {
                continue;
            }
            $feeId = $classFeeMap[$csRow->class_id];
            $totalFeeAmount = 19000;

            // Deterministic distribution based on student index
            $bucket = $sIdx % 100;
            if ($bucket < 64) {
                // Fully paid
                $feesPaidBatch[] = [
                    'fees_id' => $feeId,
                    'student_id' => $st->user_id,
                    'is_fully_paid' => 1,
                    'is_used_installment' => 0,
                    'amount' => $totalFeeAmount,
                    'date' => Carbon::now()->subDays(25)->format('Y-m-d'),
                    'school_id' => $schoolId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            } elseif ($bucket < 84) {
                // Partially paid (overdue on remainder)
                $feesPaidBatch[] = [
                    'fees_id' => $feeId,
                    'student_id' => $st->user_id,
                    'is_fully_paid' => 0,
                    'is_used_installment' => 1,
                    'amount' => 9500,
                    'date' => Carbon::now()->subDays(40)->format('Y-m-d'),
                    'school_id' => $schoolId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            // Remainder 16%: Unpaid (no fees_paids record -> overdue!)

            if (count($feesPaidBatch) >= 500) {
                $db->table('fees_paids')->insert($feesPaidBatch);
                $feesPaidBatch = [];
            }
        }
        if (!empty($feesPaidBatch)) {
            $db->table('fees_paids')->insert($feesPaidBatch);
        }
        echo "Fees structure and payment records seeded.\n";

        // 12. Exams & Exam Results (Mid Term 2026)
        echo "Seeding Exams & Exam Results...\n";
        $examName = 'Mid Term 2026';
        $db->table('exams')->where('name', $examName)->where('school_id', $schoolId)->delete();

        foreach ($classIds as $className => $cId) {
            $examId = $db->table('exams')->insertGetId([
                'name' => $examName,
                'description' => 'Mid Term Examination 2025-2026',
                'class_id' => $cId,
                'session_year_id' => $sessionYear->id,
                'start_date' => '2026-08-10',
                'end_date' => '2026-08-20',
                'last_result_submission_date' => '2026-08-25',
                'publish' => 1,
                'school_id' => $schoolId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Add timetable for 1 subject to link timetable marks
            $firstSubject = array_values($classSubjectMap[$className] ?? [])[0] ?? null;
            if ($firstSubject) {
                $ttId = $db->table('exam_timetables')->insertGetId([
                    'exam_id' => $examId,
                    'class_subject_id' => $firstSubject,
                    'total_marks' => 100,
                    'passing_marks' => 40,
                    'date' => '2026-08-12',
                    'start_time' => '09:30:00',
                    'end_time' => '12:30:00',
                    'session_year_id' => $sessionYear->id,
                    'school_id' => $schoolId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Results per student in this class
            $studentsInClass = $db->table('students')->where('class_id', $cId)->where('school_id', $schoolId)->get();
            $examResultsBatch = [];
            foreach ($studentsInClass as $stIdx => $st) {
                // Pass percentage ~76% - 91%
                $isPass = ($stIdx % 10 < 8);
                $obtained = $isPass ? rand(52, 96) : rand(22, 38);
                $status = $isPass ? 1 : 0;
                $grade = $obtained >= 90 ? 'A+' : ($obtained >= 75 ? 'A' : ($obtained >= 60 ? 'B' : ($obtained >= 40 ? 'C' : 'F')));

                $examResultsBatch[] = [
                    'exam_id' => $examId,
                    'class_section_id' => $st->class_section_id,
                    'student_id' => $st->user_id,
                    'total_marks' => 100,
                    'obtained_marks' => $obtained,
                    'percentage' => $obtained,
                    'grade' => $grade,
                    'status' => $status,
                    'session_year_id' => $sessionYear->id,
                    'school_id' => $schoolId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            $db->table('exam_results')->insert($examResultsBatch);
        }
        echo "Exams & Exam Results seeded.\n";

        // 13. Staff Leaves (Priya Menon, Rahul Sharma, Anita Das to match mockup)
        echo "Seeding Staff Leaves...\n";
        $leaveMaster = $db->table('leave_masters')->where('school_id', $schoolId)->first();
        if (!$leaveMaster) {
            $leaveMasterId = $db->table('leave_masters')->insertGetId([
                'leaves' => 15,
                'holiday' => 'Sunday',
                'session_year_id' => $sessionYear->id,
                'school_id' => $schoolId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $leaveMasterId = $leaveMaster->id;
        }

        $db->table('leaves')->where('school_id', $schoolId)->delete();
        $db->table('leave_details')->where('school_id', $schoolId)->delete();

        $today = Carbon::now()->format('Y-m-d');
        $leavesToSeed = [
            ['teacher_idx' => 0, 'reason' => 'Family Emergency', 'type' => 'Full', 'status' => 0, 'date' => $today], // Priya Menon - Pending
            ['teacher_idx' => 1, 'reason' => 'Medical Checkup', 'type' => 'First Half', 'status' => 1, 'date' => $today], // Rahul Sharma - Approved
            ['teacher_idx' => 2, 'reason' => 'Personal Work', 'type' => 'Full', 'status' => 1, 'date' => $today], // Anita Das - Approved
            ['teacher_idx' => 3, 'reason' => 'Dental Appointment', 'type' => 'Second Half', 'status' => 1, 'date' => Carbon::now()->addDay()->format('Y-m-d')],
            ['teacher_idx' => 4, 'reason' => 'Out of station', 'type' => 'Full', 'status' => 0, 'date' => Carbon::now()->addDays(2)->format('Y-m-d')],
        ];

        foreach ($leavesToSeed as $l) {
            $uId = $teacherUserIds[$l['teacher_idx']] ?? $teacherUserIds[0];
            $lId = $db->table('leaves')->insertGetId([
                'user_id' => $uId,
                'reason' => $l['reason'],
                'from_date' => $l['date'],
                'to_date' => $l['date'],
                'status' => $l['status'],
                'leave_master_id' => $leaveMasterId,
                'school_id' => $schoolId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $db->table('leave_details')->insert([
                'leave_id' => $lId,
                'date' => $l['date'],
                'type' => $l['type'],
                'school_id' => $schoolId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        echo "Staff Leaves seeded.\n";

        // 14. Announcements
        echo "Seeding announcements...\n";
        $announcementsData = [
            ['title' => 'Annual Sports Day 2026', 'desc' => 'The Annual Sports Meet is scheduled for October 15th. Students are requested to submit event entries to their Physical Education instructors.'],
            ['title' => 'Parent-Teacher Meeting (PTM) for Term 1', 'desc' => 'Parent-Teacher Conference will be held on Saturday from 9:00 AM to 1:00 PM. Parents can review academic progress and report cards.'],
            ['title' => 'Science & Innovation Exhibition', 'desc' => 'Calling all budding scientists! Register your working models and science projects with the Physics and Chemistry department by next Friday.'],
            ['title' => 'Library Week & Scholastic Book Fair', 'desc' => 'A 3-day book fair is being organized in the central auditorium featuring regional and international publishers.'],
        ];

        $db->table('announcements')->where('school_id', $schoolId)->delete();
        $db->table('announcement_classes')->where('school_id', $schoolId)->delete();

        foreach ($announcementsData as $ann) {
            $annId = $db->table('announcements')->insertGetId([
                'title' => $ann['title'],
                'description' => $ann['desc'],
                'session_year_id' => $sessionYear->id,
                'school_id' => $schoolId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Link to first class section
            $db->table('announcement_classes')->insert([
                'announcement_id' => $annId,
                'class_section_id' => null,
                'class_subject_id' => null,
                'school_id' => $schoolId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 15. Holidays
        echo "Seeding upcoming holidays...\n";
        $holidaysData = [
            ['title' => 'Gandhi Jayanti', 'date' => '2026-10-02', 'desc' => 'National Holiday in commemoration of Mahatma Gandhi'],
            ['title' => 'Dussehra Holiday', 'date' => '2026-10-20', 'desc' => 'Vijayadashami festival break'],
            ['title' => 'Diwali Vacation', 'date' => '2026-11-08', 'desc' => 'Festival of lights school recess'],
            ['title' => 'Christmas Break', 'date' => '2026-12-25', 'desc' => 'Christmas celebration and winter holidays'],
        ];

        $db->table('holidays')->where('school_id', $schoolId)->delete();
        foreach ($holidaysData as $h) {
            $db->table('holidays')->insert([
                'title' => $h['title'],
                'date' => $h['date'],
                'description' => $h['desc'],
                'school_id' => $schoolId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $db->statement('SET FOREIGN_KEY_CHECKS=1;');

        echo "\n=== Procedural School Seeding Finished Successfully! ===\n";
    }
}
