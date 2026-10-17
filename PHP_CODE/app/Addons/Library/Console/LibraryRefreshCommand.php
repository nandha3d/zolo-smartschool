<?php

namespace App\Addons\Library\Console;

use App\Addons\Concerns\RunsForEachSchool;
use App\Addons\Library\Services\LibraryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Nightly: flag overdue loans with their accrued fine and expire uncollected holds. */
class LibraryRefreshCommand extends Command
{
    use RunsForEachSchool;

    protected $signature = 'library:refresh {--school= : Only this school id}';
    protected $description = 'Mark overdue library loans, accrue fines and expire uncollected reservations';

    public function handle(): int
    {
        $this->forEachSchool(function ($school) {
            if (!Schema::connection('school')->hasTable('library_issues')) {
                return;
            }
            // A fresh service per school: it caches that school's settings row.
            $result = app()->make(LibraryService::class)->forSchool($school->id)->refresh();
            $this->line("{$school->name}: {$result['overdue']} overdue, {$result['expired']} holds expired");
        }, $this->option('school'));

        return self::SUCCESS;
    }
}
