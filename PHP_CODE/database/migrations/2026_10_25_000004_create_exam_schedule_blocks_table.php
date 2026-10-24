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
        // Exam Schedule Blocks: Overlaid on standard timetable grid for Morning Exam + Afternoon Revision
        if (!Schema::hasTable('exam_schedule_blocks')) {
            Schema::create('exam_schedule_blocks', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('school_id');
                $table->unsignedInteger('session_year_id');
                $table->unsignedInteger('exam_id'); // references exams.id
                $table->unsignedInteger('class_id');
                $table->unsignedInteger('class_section_id');
                $table->date('date');
                $table->enum('block_type', [
                    'exam',
                    'revision',
                    'practice',
                    'study_leave',
                    'lab_practical'
                ])->default('exam');
                $table->unsignedInteger('subject_id')->nullable();
                $table->unsignedInteger('start_period_id')->nullable();
                $table->unsignedInteger('end_period_id')->nullable();
                $table->time('custom_start_time')->nullable();
                $table->time('custom_end_time')->nullable();
                $table->unsignedInteger('exam_incharge_staff_id')->nullable();     // Primary invigilator
                $table->unsignedInteger('assistant_incharge_staff_id')->nullable(); // Relieving/Assistant invigilator
                $table->unsignedInteger('hall_or_resource_id')->nullable();        // Room or Hall
                $table->decimal('total_marks', 6, 2)->nullable();
                $table->decimal('passing_marks', 6, 2)->nullable();
                $table->text('instructions')->nullable();
                $table->timestamps();

                $table->index(['school_id', 'exam_id']);
                $table->index(['class_section_id', 'date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_schedule_blocks');
    }
};
