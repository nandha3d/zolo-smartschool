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
        if (Schema::hasTable('timetables')) {
            Schema::table('timetables', function (Blueprint $table) {
                if (!Schema::hasColumn('timetables', 'is_locked')) {
                    $table->boolean('is_locked')->default(false)->after('type');
                }
                if (!Schema::hasColumn('timetables', 'period_id')) {
                    $table->foreignId('period_id')->nullable()->after('is_locked')->references('id')->on('timetable_periods')->onDelete('set null');
                }
                if (!Schema::hasColumn('timetables', 'resource_id')) {
                    $table->foreignId('resource_id')->nullable()->after('period_id')->references('id')->on('timetable_resources')->onDelete('set null');
                }
                if (!Schema::hasColumn('timetables', 'locked_by')) {
                    $table->foreignId('locked_by')->nullable()->after('resource_id')->references('id')->on('users')->onDelete('set null');
                }
                if (!Schema::hasColumn('timetables', 'locked_at')) {
                    $table->timestamp('locked_at')->nullable()->after('locked_by');
                }
                if (!Schema::hasColumn('timetables', 'lock_reason')) {
                    $table->string('lock_reason', 512)->nullable()->after('locked_at');
                }

                // Composite indexes for fast constraint checking
                $table->index(['school_id', 'class_section_id', 'day'], 'timetables_school_class_day_idx');
                $table->index(['school_id', 'day', 'start_time', 'end_time'], 'timetables_school_day_time_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('timetables')) {
            Schema::table('timetables', function (Blueprint $table) {
                $table->dropIndex('timetables_school_day_time_idx');
                $table->dropIndex('timetables_school_class_day_idx');

                if (Schema::hasColumn('timetables', 'lock_reason')) {
                    $table->dropColumn('lock_reason');
                }
                if (Schema::hasColumn('timetables', 'locked_at')) {
                    $table->dropColumn('locked_at');
                }
                if (Schema::hasColumn('timetables', 'locked_by')) {
                    $table->dropForeign(['locked_by']);
                    $table->dropColumn('locked_by');
                }
                if (Schema::hasColumn('timetables', 'resource_id')) {
                    $table->dropForeign(['resource_id']);
                    $table->dropColumn('resource_id');
                }
                if (Schema::hasColumn('timetables', 'period_id')) {
                    $table->dropForeign(['period_id']);
                    $table->dropColumn('period_id');
                }
                if (Schema::hasColumn('timetables', 'is_locked')) {
                    $table->dropColumn('is_locked');
                }
            });
        }
    }
};
