<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teacher attendance needs finer codes than present/absent (half day, on duty, casual and
 * medical leave), and an absent teacher's periods need a proxy teacher for that date.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff_attendances') && !Schema::hasColumn('staff_attendances', 'leave_code')) {
            Schema::table('staff_attendances', function (Blueprint $table) {
                // P | A | HD | OD | CL | ML. `type` keeps its meaning (1 present, 0 absent,
                // 3 holiday) so existing reports and the apps keep working.
                $table->string('leave_code', 4)->nullable()->after('type');
                $table->index(['staff_id', 'date'], 'staff_att_staff_date_idx');
            });
        }

        if (!Schema::hasTable('proxy_teacher_assignments')) {
            Schema::create('proxy_teacher_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id');
                $table->date('date');
                $table->unsignedBigInteger('timetable_id');
                $table->unsignedBigInteger('class_section_id');
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->unsignedBigInteger('absent_teacher_id');
                $table->unsignedBigInteger('proxy_teacher_id');
                $table->unsignedBigInteger('assigned_by');
                $table->string('notes', 255)->nullable();
                $table->timestamps();

                $table->unique(['date', 'timetable_id'], 'pta_date_slot_unique');
                $table->index(['proxy_teacher_id', 'date'], 'pta_proxy_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('proxy_teacher_assignments');
        if (Schema::hasTable('staff_attendances') && Schema::hasColumn('staff_attendances', 'leave_code')) {
            Schema::table('staff_attendances', function (Blueprint $table) {
                $table->dropIndex('staff_att_staff_date_idx');
                $table->dropColumn('leave_code');
            });
        }
    }
};
