<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026_10_25_000006 turned students.roll_number into a string so it could hold
 * prefixed values such as "CL1A07". Both mobile apps parse roll_number as an int and
 * every orderBy('roll_number') started sorting "1, 10, 2". This keeps the formatted
 * value in roll_number_label and puts roll_number back to the integer sequence.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('students')) {
            return;
        }

        if (!Schema::hasColumn('students', 'roll_number_label')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('roll_number_label', 64)->nullable()->after('roll_number');
            });
        }

        // Move any formatted value into the label and keep its trailing digits as the number.
        DB::table('students')->select(['id', 'roll_number'])->whereNotNull('roll_number')
            ->orderBy('id')
            ->chunkById(500, function ($students) {
                foreach ($students as $student) {
                    $raw = trim((string) $student->roll_number);
                    if (ctype_digit($raw)) {
                        continue;
                    }
                    $number = preg_match('/(\d+)\D*$/', $raw, $m) ? (int) $m[1] : null;
                    DB::table('students')->where('id', $student->id)->update([
                        'roll_number_label' => $raw === '' ? null : $raw,
                        'roll_number'       => $number,
                    ]);
                }
            });

        Schema::table('students', function (Blueprint $table) {
            $table->integer('roll_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('students')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            $table->string('roll_number', 64)->nullable()->change();
        });

        DB::table('students')->whereNotNull('roll_number_label')
            ->update(['roll_number' => DB::raw('roll_number_label')]);

        if (Schema::hasColumn('students', 'roll_number_label')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('roll_number_label');
            });
        }
    }
};
