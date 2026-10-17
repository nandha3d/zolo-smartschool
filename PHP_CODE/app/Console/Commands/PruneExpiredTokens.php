<?php

namespace App\Console\Commands;

use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * sanctum:prune-expired only sees the central database, but the mobile apps' tokens are
 * issued inside each school's own database. This walks the central database and every
 * school database and deletes tokens past config('sanctum.expiration') or their own
 * expires_at.
 */
class PruneExpiredTokens extends Command
{
    protected $signature = 'tokens:prune-expired {--hours=24 : Keep tokens that expired within this many hours}';

    protected $description = 'Delete expired Sanctum tokens from the central and every school database';

    public function handle(): int
    {
        $expiration = config('sanctum.expiration');
        $hours = (int) $this->option('hours');

        $total = $this->pruneConnection('mysql', $expiration, $hours);

        foreach (School::withTrashed()->whereNotNull('database_name')->pluck('database_name') as $database) {
            try {
                Config::set('database.connections.school.database', $database);
                DB::purge('school');
                $total += $this->pruneConnection('school', $expiration, $hours);
            } catch (Throwable $e) {
                $this->warn("Skipped {$database}: {$e->getMessage()}");
            }
        }

        $this->info("Pruned {$total} expired tokens.");

        return self::SUCCESS;
    }

    private function pruneConnection(string $connection, $expiration, int $hours): int
    {
        if (!Schema::connection($connection)->hasTable('personal_access_tokens')) {
            return 0;
        }

        $cutoff = now()->subHours($hours);
        $deleted = DB::connection($connection)->table('personal_access_tokens')
            ->where('expires_at', '<', $cutoff)
            ->delete();

        if ($expiration) {
            $deleted += DB::connection($connection)->table('personal_access_tokens')
                ->where('created_at', '<', $cutoff->copy()->subMinutes((int) $expiration))
                ->delete();
        }

        return $deleted;
    }
}
