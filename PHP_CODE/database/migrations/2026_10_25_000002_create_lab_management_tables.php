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
        // 1. Lab Batches (Batch A, Batch B, Practical groups)
        if (!Schema::hasTable('lab_batches')) {
            Schema::create('lab_batches', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('school_id');
                $table->unsignedInteger('session_year_id');
                $table->unsignedInteger('class_section_id');
                $table->unsignedInteger('resource_id'); // references timetable_resources (e.g. Physics Lab)
                $table->string('name', 100);            // e.g. 'Batch A'
                $table->string('batch_code', 50);       // e.g. '10A-PHY-A'
                $table->integer('max_capacity')->default(25);
                $table->unsignedInteger('batch_leader_student_id')->nullable();
                $table->unsignedInteger('lab_assistant_staff_id')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();

                $table->index(['class_section_id', 'resource_id']);
                $table->index(['school_id', 'session_year_id']);
            });
        }

        // 2. Lab Batch Student Mapping
        if (!Schema::hasTable('lab_batch_students')) {
            Schema::create('lab_batch_students', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('lab_batch_id');
                $table->unsignedInteger('student_id');
                $table->string('workstation_number', 30)->nullable(); // e.g. 'Station 4' / 'PC-12'
                $table->timestamps();

                $table->unique(['lab_batch_id', 'student_id']);
                $table->foreign('lab_batch_id')->references('id')->on('lab_batches')->onDelete('cascade');
                $table->index('student_id');
            });
        }

        // 3. Lab Practical Sessions & Apparatus Custody Log
        if (!Schema::hasTable('lab_sessions')) {
            Schema::create('lab_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('school_id');
                $table->unsignedBigInteger('lab_batch_id');
                $table->unsignedInteger('subject_id');
                $table->unsignedInteger('timetable_id')->nullable();
                $table->date('session_date');
                $table->string('experiment_title', 255);
                $table->unsignedInteger('conducted_by_teacher_id');
                $table->boolean('batch_leader_verified')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['lab_batch_id', 'session_date']);
                $table->index('school_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_sessions');
        Schema::dropIfExists('lab_batch_students');
        Schema::dropIfExists('lab_batches');
    }
};
