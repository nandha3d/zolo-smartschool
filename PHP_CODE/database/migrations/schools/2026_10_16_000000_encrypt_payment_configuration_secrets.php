<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PaymentConfiguration now casts secret_key and webhook_secret_key as 'encrypted'.
 * Ciphertext does not fit the old VARCHAR(255) columns, so widen them to TEXT and
 * encrypt the rows that are still plaintext. Rows that already decrypt are skipped, so
 * the migration is safe to run more than once. The same file ships in
 * database/migrations/schools for the per-school databases.
 */
return new class extends Migration {
    private const COLUMNS = ['secret_key', 'webhook_secret_key'];

    public function up(): void
    {
        if (!Schema::hasTable('payment_configurations')) {
            return;
        }

        Schema::table('payment_configurations', static function (Blueprint $table) {
            $table->text('secret_key')->nullable()->change();
            $table->text('webhook_secret_key')->nullable()->change();
        });

        DB::table('payment_configurations')->orderBy('id')->each(function ($row) {
            $changes = [];
            foreach (self::COLUMNS as $column) {
                $value = $row->{$column};
                if ($value === null || $this->isEncrypted($value)) {
                    continue;
                }
                $changes[$column] = Crypt::encryptString($value);
            }

            if ($changes) {
                DB::table('payment_configurations')->where('id', $row->id)->update($changes);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payment_configurations')) {
            return;
        }

        DB::table('payment_configurations')->orderBy('id')->each(function ($row) {
            $changes = [];
            foreach (self::COLUMNS as $column) {
                $value = $row->{$column};
                if ($value !== null && $this->isEncrypted($value)) {
                    $changes[$column] = Crypt::decryptString($value);
                }
            }

            if ($changes) {
                DB::table('payment_configurations')->where('id', $row->id)->update($changes);
            }
        });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);
            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
