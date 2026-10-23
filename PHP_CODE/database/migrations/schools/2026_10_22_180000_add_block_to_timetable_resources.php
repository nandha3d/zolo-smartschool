<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('timetable_resources')) {
            Schema::table('timetable_resources', function (Blueprint $table) {
                if (!Schema::hasColumn('timetable_resources', 'block')) {
                    $table->string('block', 64)->nullable()->after('type');
                }
            });
        }
        if (Schema::hasTable('class_sections')) {
            Schema::table('class_sections', function (Blueprint $table) {
                if (!Schema::hasColumn('class_sections', 'block')) {
                    $table->string('block', 64)->nullable()->after('medium_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('timetable_resources')) {
            Schema::table('timetable_resources', function (Blueprint $table) {
                if (Schema::hasColumn('timetable_resources', 'block')) {
                    $table->dropColumn('block');
                }
            });
        }
        if (Schema::hasTable('class_sections')) {
            Schema::table('class_sections', function (Blueprint $table) {
                if (Schema::hasColumn('class_sections', 'block')) {
                    $table->dropColumn('block');
                }
            });
        }
    }
};
