<?php

namespace App\Addons\Concerns;

use App\Models\School;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

/** For console commands: run a callback with the default connection on each school's database. */
trait RunsForEachSchool
{
    protected function forEachSchool(callable $callback, $onlySchoolId = null): void
    {
        $schools = School::on('mysql')->whereNotNull('database_name')
            ->when($onlySchoolId, fn($q) => $q->where('id', $onlySchoolId))
            ->get();

        foreach ($schools as $school) {
            try {
                Config::set('database.connections.school.database', $school->database_name);
                DB::purge('school');
                DB::setDefaultConnection('school');
                $callback($school);
            } catch (Throwable $e) {
                $this->error("{$school->name}: {$e->getMessage()}");
            } finally {
                DB::setDefaultConnection('mysql');
            }
        }
    }
}
