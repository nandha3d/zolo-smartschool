<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Period-Wise Granular Attendance
        if (!Schema::hasTable('period_attendances')) {
            Schema::create('period_attendances', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('school_id');
                $table->unsignedInteger('session_year_id');
                $table->unsignedInteger('class_section_id');
                $table->unsignedInteger('student_id');
                $table->date('date');
                $table->unsignedInteger('timetable_period_id')->nullable();
                $table->integer('period_number'); // 1, 2, 3, 4, 5, 6, 7, 8...
                $table->unsignedInteger('subject_id')->nullable();
                $table->unsignedBigInteger('marked_by_user_id'); // Teacher ID
                $table->enum('status', [
                    'present',
                    'absent',
                    'late',
                    'left_early',
                    'excused',
                    'infirmary',
                    'school_activity'
                ])->default('present');
                $table->integer('minutes_late')->nullable();
                $table->string('remarks', 255)->nullable();
                $table->timestamps();

                $table->unique(['date', 'period_number', 'student_id'], 'pa_date_period_student_unique');
                $table->index(['class_section_id', 'date', 'period_number'], 'pa_class_date_period_idx');
                $table->index(['student_id', 'date'], 'pa_student_date_idx');
            });
        }

        // 2. Student Movement & Gate Pass Log (Infirmary, Early Departure, Principal Office)
        if (!Schema::hasTable('student_movement_logs')) {
            Schema::create('student_movement_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('school_id');
                $table->unsignedInteger('session_year_id');
                $table->unsignedInteger('student_id');
                $table->date('date');
                $table->enum('movement_type', [
                    'gate_pass_exit',
                    'infirmary_visit',
                    'principal_office',
                    'library_pass',
                    'sports_duty'
                ]);
                $table->time('departure_time');
                $table->time('expected_return_time')->nullable();
                $table->time('actual_return_time')->nullable();
                $table->unsignedInteger('authorized_by_staff_id');
                $table->string('pickup_person_name', 191)->nullable();
                $table->string('pickup_person_relationship', 50)->nullable();
                $table->string('pickup_person_phone', 30)->nullable();
                $table->text('reason');
                $table->enum('status', ['out', 'returned', 'departed_campus'])->default('out');
                $table->timestamps();

                $table->index(['student_id', 'date'], 'sml_student_date_idx');
                $table->index(['school_id', 'movement_type'], 'sml_school_movement_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_movement_logs');
        Schema::dropIfExists('period_attendances');
    }
};
