<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. timetable_templates
        if (!Schema::hasTable('timetable_templates')) {
            Schema::create('timetable_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->references('id')->on('schools')->onDelete('cascade');
                $table->string('name');
                $table->text('description')->nullable();
                $table->json('working_days'); // e.g. ["Monday","Tuesday","Wednesday","Thursday","Friday"]
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->references('id')->on('users')->onDelete('set null');
                $table->foreignId('updated_by')->nullable()->references('id')->on('users')->onDelete('set null');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['school_id', 'is_default']);
                $table->index(['school_id', 'is_active']);
            });
        }

        // 2. timetable_periods
        if (!Schema::hasTable('timetable_periods')) {
            Schema::create('timetable_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->references('id')->on('schools')->onDelete('cascade');
                $table->foreignId('timetable_template_id')->references('id')->on('timetable_templates')->onDelete('cascade');
                $table->unsignedInteger('period_number');
                $table->string('name');
                $table->time('start_time');
                $table->time('end_time');
                $table->string('type', 32)->default('Lecture'); // 'Lecture', 'Break', 'Lunch', 'Activity', 'Assembly', 'Free'
                $table->boolean('is_instructional')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['timetable_template_id', 'period_number']);
                $table->index(['timetable_template_id', 'is_active']);
                $table->index(['school_id', 'is_active']);
            });
        }

        // 3. timetable_resources
        if (!Schema::hasTable('timetable_resources')) {
            Schema::create('timetable_resources', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->references('id')->on('schools')->onDelete('cascade');
                $table->string('name');
                $table->string('type', 64)->default('Lab'); // 'Lab', 'Room', 'Auditorium', 'Ground', etc.
                $table->unsignedInteger('capacity')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['school_id', 'is_active']);
            });
        }

        // 4. subject_schedule_requirements
        if (!Schema::hasTable('subject_schedule_requirements')) {
            Schema::create('subject_schedule_requirements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->references('id')->on('schools')->onDelete('cascade');
                $table->foreignId('class_section_id')->references('id')->on('class_sections')->onDelete('cascade');
                $table->foreignId('subject_id')->references('id')->on('subjects')->onDelete('cascade');
                $table->foreignId('class_subject_id')->nullable()->references('id')->on('class_subjects')->onDelete('cascade');
                $table->foreignId('semester_id')->nullable()->references('id')->on('semesters')->onDelete('cascade');
                $table->unsignedInteger('weekly_periods')->default(0);
                $table->unsignedInteger('min_periods_per_day')->default(0);
                $table->unsignedInteger('max_periods_per_day')->default(1);
                $table->boolean('allow_consecutive')->default(false);
                $table->unsignedInteger('required_consecutive_periods')->nullable();
                $table->string('preferred_day', 64)->nullable();
                $table->time('preferred_period_start')->nullable();
                $table->time('preferred_period_end')->nullable();
                $table->boolean('avoid_first_period')->default(false);
                $table->boolean('avoid_last_period')->default(false);
                $table->integer('priority')->default(1);
                $table->foreignId('resource_id')->nullable()->references('id')->on('timetable_resources')->onDelete('set null');
                $table->boolean('resource_required')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['class_section_id', 'subject_id', 'semester_id'], 'sub_sched_req_class_sub_sem_idx');
                $table->index(['school_id', 'is_active']);
            });
        }

        // 5. teacher_availability
        if (!Schema::hasTable('teacher_availability')) {
            Schema::create('teacher_availability', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->references('id')->on('schools')->onDelete('cascade');
                $table->foreignId('teacher_id')->references('id')->on('users')->onDelete('cascade');
                $table->enum('day', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
                $table->foreignId('period_id')->references('id')->on('timetable_periods')->onDelete('cascade');
                $table->enum('status', ['available', 'unavailable', 'preferred'])->default('available');
                $table->string('reason')->nullable();
                $table->timestamps();

                $table->unique(['teacher_id', 'day', 'period_id'], 'teacher_avail_unique');
                $table->index(['school_id', 'teacher_id', 'day'], 'teacher_avail_school_day_idx');
            });
        }

        // 6. class_section_availability
        if (!Schema::hasTable('class_section_availability')) {
            Schema::create('class_section_availability', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->references('id')->on('schools')->onDelete('cascade');
                $table->foreignId('class_section_id')->references('id')->on('class_sections')->onDelete('cascade');
                $table->enum('day', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
                $table->foreignId('period_id')->references('id')->on('timetable_periods')->onDelete('cascade');
                $table->enum('status', ['available', 'unavailable', 'preferred'])->default('available');
                $table->string('reason')->nullable();
                $table->timestamps();

                $table->unique(['class_section_id', 'day', 'period_id'], 'class_avail_unique');
                $table->index(['school_id', 'class_section_id', 'day'], 'class_avail_school_day_idx');
            });
        }

        // 7. timetable_resource_availability
        if (!Schema::hasTable('timetable_resource_availability')) {
            Schema::create('timetable_resource_availability', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->references('id')->on('schools')->onDelete('cascade');
                $table->foreignId('resource_id')->references('id')->on('timetable_resources')->onDelete('cascade');
                $table->enum('day', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
                $table->foreignId('period_id')->references('id')->on('timetable_periods')->onDelete('cascade');
                $table->enum('status', ['available', 'unavailable', 'preferred'])->default('available');
                $table->string('reason')->nullable();
                $table->timestamps();

                $table->unique(['resource_id', 'day', 'period_id'], 'resource_avail_unique');
                $table->index(['school_id', 'resource_id', 'day'], 'resource_avail_school_day_idx');
            });
        }

        // 8. subject_teacher_alternates
        if (!Schema::hasTable('subject_teacher_alternates')) {
            Schema::create('subject_teacher_alternates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->references('id')->on('schools')->onDelete('cascade');
                $table->foreignId('subject_teacher_id')->references('id')->on('subject_teachers')->onDelete('cascade');
                $table->foreignId('alternate_teacher_id')->references('id')->on('users')->onDelete('cascade');
                $table->integer('priority')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['subject_teacher_id', 'alternate_teacher_id'], 'subj_teach_alt_unique');
                $table->index(['school_id', 'is_active']);
            });
        }

        // 9. timetable_generation_runs
        if (!Schema::hasTable('timetable_generation_runs')) {
            Schema::create('timetable_generation_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->references('id')->on('schools')->onDelete('cascade');
                $table->foreignId('session_year_id')->nullable()->references('id')->on('session_years')->onDelete('cascade');
                $table->foreignId('semester_id')->nullable()->references('id')->on('semesters')->onDelete('cascade');
                $table->foreignId('template_id')->references('id')->on('timetable_templates')->onDelete('cascade');
                $table->foreignId('started_by')->nullable()->references('id')->on('users')->onDelete('set null');
                $table->enum('status', ['draft', 'running', 'completed', 'failed', 'applied', 'cancelled'])->default('draft');
                $table->string('algorithm_version', 32)->default('v2');
                $table->string('input_hash', 64)->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('error_message')->nullable();
                $table->json('statistics')->nullable();
                $table->timestamps();

                $table->index(['school_id', 'status']);
                $table->index(['school_id', 'session_year_id', 'semester_id'], 'gen_runs_school_sess_sem_idx');
            });
        }

        // 10. timetable_generation_entries
        if (!Schema::hasTable('timetable_generation_entries')) {
            Schema::create('timetable_generation_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('generation_run_id')->references('id')->on('timetable_generation_runs')->onDelete('cascade');
                $table->foreignId('class_section_id')->references('id')->on('class_sections')->onDelete('cascade');
                $table->foreignId('subject_id')->nullable()->references('id')->on('subjects')->onDelete('cascade');
                $table->foreignId('subject_teacher_id')->nullable()->references('id')->on('subject_teachers')->onDelete('set null');
                $table->foreignId('teacher_id')->nullable()->references('id')->on('users')->onDelete('set null');
                $table->foreignId('resource_id')->nullable()->references('id')->on('timetable_resources')->onDelete('set null');
                $table->enum('day', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
                $table->foreignId('period_id')->nullable()->references('id')->on('timetable_periods')->onDelete('set null');
                $table->time('start_time');
                $table->time('end_time');
                $table->enum('type', ['Lecture', 'Break'])->default('Lecture');
                $table->boolean('is_locked')->default(false);
                $table->integer('constraint_score')->default(0);
                $table->json('explanation')->nullable();
                $table->timestamps();

                $table->index(['generation_run_id', 'class_section_id'], 'gen_entries_run_class_idx');
                $table->index(['generation_run_id', 'day', 'period_id'], 'gen_entries_run_day_period_idx');
                $table->index(['generation_run_id', 'teacher_id', 'day'], 'gen_entries_run_teacher_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_generation_entries');
        Schema::dropIfExists('timetable_generation_runs');
        Schema::dropIfExists('subject_teacher_alternates');
        Schema::dropIfExists('timetable_resource_availability');
        Schema::dropIfExists('class_section_availability');
        Schema::dropIfExists('teacher_availability');
        Schema::dropIfExists('subject_schedule_requirements');
        Schema::dropIfExists('timetable_resources');
        Schema::dropIfExists('timetable_periods');
        Schema::dropIfExists('timetable_templates');
    }
};
