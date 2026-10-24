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
        // 1. Student Groups (Study groups, bench groups, houses, activity clubs)
        if (!Schema::hasTable('student_groups')) {
            Schema::create('student_groups', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('school_id');
                $table->unsignedInteger('session_year_id');
                $table->unsignedInteger('class_section_id')->nullable();
                $table->string('name', 191);
                $table->enum('type', ['study_group', 'bench_group', 'house', 'activity_club', 'project_team'])->default('study_group');
                $table->text('description')->nullable();
                $table->unsignedInteger('leader_student_id')->nullable();
                $table->unsignedInteger('vice_leader_student_id')->nullable();
                $table->string('color_code', 20)->default('#22577A');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['school_id', 'session_year_id']);
                $table->index('class_section_id');
            });
        }

        // 2. Student Group Members
        if (!Schema::hasTable('student_group_members')) {
            Schema::create('student_group_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('student_group_id');
                $table->unsignedInteger('student_id');
                $table->integer('bench_number')->nullable();
                $table->string('role_in_group', 50)->default('member');
                $table->date('joined_at')->nullable();
                $table->timestamps();

                $table->unique(['student_group_id', 'student_id']);
                $table->foreign('student_group_id')->references('id')->on('student_groups')->onDelete('cascade');
                $table->index('student_id');
            });
        }

        // 3. Student Leadership Roles (Class leader, Bench leader, Group leader, etc.)
        if (!Schema::hasTable('student_leadership_roles')) {
            Schema::create('student_leadership_roles', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('school_id');
                $table->unsignedInteger('session_year_id');
                $table->unsignedInteger('class_section_id');
                $table->unsignedInteger('student_id');
                $table->enum('role_type', [
                    'class_leader',
                    'vice_class_leader',
                    'bench_leader',
                    'group_leader',
                    'sports_captain',
                    'discipline_prefect',
                    'lab_batch_leader'
                ]);
                $table->string('title', 100);
                $table->string('badge_icon', 50)->default('fa-star');
                $table->string('badge_color', 20)->default('#22577A');
                $table->unsignedBigInteger('assigned_by');
                $table->date('valid_from');
                $table->date('valid_until')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['student_id', 'session_year_id']);
                $table->index(['class_section_id', 'role_type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_leadership_roles');
        Schema::dropIfExists('student_group_members');
        Schema::dropIfExists('student_groups');
    }
};
