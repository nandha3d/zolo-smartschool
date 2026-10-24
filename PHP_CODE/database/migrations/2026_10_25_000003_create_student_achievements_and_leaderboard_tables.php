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
        // 1. Extracurricular Achievements & Honors
        if (!Schema::hasTable('student_achievements')) {
            Schema::create('student_achievements', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('school_id');
                $table->unsignedInteger('session_year_id');
                $table->unsignedInteger('student_id');
                $table->unsignedInteger('class_section_id');
                $table->enum('category', [
                    'sports',
                    'arts_culture',
                    'stem_robotics',
                    'literary_debate',
                    'academics',
                    'leadership',
                    'community'
                ]);
                $table->enum('competition_level', [
                    'intra_school',
                    'inter_school',
                    'district',
                    'state',
                    'national',
                    'international'
                ])->default('intra_school');
                $table->string('title', 255);
                $table->enum('position', [
                    'first_gold',
                    'second_silver',
                    'third_bronze',
                    'consolation',
                    'participation'
                ]);
                $table->date('award_date');
                $table->string('event_organizer', 191)->nullable();
                $table->string('certificate_media_url', 255)->nullable();
                $table->text('citation_notes')->nullable();
                $table->boolean('is_featured_on_wall')->default(true);
                $table->timestamps();

                $table->index(['school_id', 'category'], 'sa_school_category_idx');
                $table->index(['session_year_id', 'position'], 'sa_session_position_idx');
                $table->index('student_id', 'sa_student_idx');
            });
        }

        // Drop partially created table if needed
        Schema::dropIfExists('academic_leaderboard_snapshots');

        // 2. Materialized Academic Rank Snapshots (Cached Top 3 & Leaderboard data)
        Schema::create('academic_leaderboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('school_id');
            $table->unsignedInteger('session_year_id');
            $table->unsignedInteger('exam_id')->nullable(); // NULL = Cumulative Session Year
            $table->unsignedInteger('class_id');
            $table->unsignedInteger('class_section_id');
            $table->unsignedInteger('student_id');
            $table->integer('rank_in_section'); // 1, 2, 3...
            $table->integer('rank_in_class');   // 1, 2, 3... across sections
            $table->decimal('total_marks_obtained', 8, 2);
            $table->decimal('total_max_marks', 8, 2);
            $table->decimal('percentage', 5, 2);
            $table->string('grade', 10)->default('A');
            $table->timestamp('calculated_at');

            // Explicit short index names to stay well under MySQL's 64-char limit
            $table->index(['class_section_id', 'rank_in_section'], 'als_section_rank_idx');
            $table->index(['class_id', 'rank_in_class'], 'als_class_rank_idx');
            $table->index(['session_year_id', 'exam_id', 'student_id'], 'als_session_exam_student_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_leaderboard_snapshots');
        Schema::dropIfExists('student_achievements');
    }
};
