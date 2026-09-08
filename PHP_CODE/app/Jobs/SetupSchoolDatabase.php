<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\School;
use App\Services\SchoolDataService;
use App\Services\SubscriptionService;
use App\Services\CachingService;
use App\Repositories\SystemSetting\SystemSettingInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SetupSchoolDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Provisioning a school runs the full tenant migration set against a brand new
     * database. That took roughly 28 minutes on a modest development machine, so the
     * previous 300 second limit killed the job mid-migration and left the school with
     * a partially built schema and installed = 0. Benchmark on the target hardware and
     * set SCHOOL_SETUP_TIMEOUT accordingly; the queue worker's own --timeout must be
     * at least as large or it will kill the job first.
     */
    public int $timeout = 3600;
    public int $backoff = 120; // 2 minute between retries

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly int $schoolId,
        private readonly ?int $packageId = null,
        private readonly ?string $schoolCodePrefix = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        SchoolDataService $schoolService,
        SubscriptionService $subscriptionService,
        CachingService $cache,
        SystemSettingInterface $systemSettings
    ): void {
        try {
            DB::setDefaultConnection('mysql');

            // Get school data
            $school = School::findOrFail($this->schoolId);
            
            // Create database
            DB::statement("CREATE DATABASE IF NOT EXISTS {$school->database_name}");

            // Run migrations
            $schoolService->createDatabaseMigration($school);

            // Setup pre-settings
            $schoolService->preSettingsSetup($school);

            // Assign package if provided
            if ($this->packageId) {
                $subscriptionService->createSubscription($this->packageId, $school->id, null, 1);
                $cache->removeSchoolCache(config('constants.CACHE.SCHOOL.SETTINGS'), $school->id);
            }

            // Update school code prefix if provided
            if ($this->schoolCodePrefix) {
                $settings = $cache->getSystemSettings();
                if (($settings['school_prefix'] ?? '') != $this->schoolCodePrefix) {
                    $settingsData[] = [
                        "name" => 'school_prefix',
                        "data" => $this->schoolCodePrefix,
                        "type" => "text"
                    ];
                    $systemSettings->upsert($settingsData, ["name"], ["data"]);
                    $cache->removeSystemCache(config('constants.CACHE.SYSTEM.SETTINGS'));
                }
            }

            // Update school status to active
            $school->update(['status' => 1, 'installed' => 1]);

            DB::setDefaultConnection('school');
            Config::set('database.connections.school.database', $school->database_name);
            DB::purge('school');
            DB::connection('school')->reconnect();
            DB::setDefaultConnection('school');
            School::on('school')->where('id', $this->schoolId)->update(['status' => 1, 'installed' => 1]);

            $school = School::with('user')->findOrFail($this->schoolId);
            $settings = $cache->getSystemSettings();

            $email_body = $this->replacePlaceholders($school, $school->user, $settings, $school->code);
            
            $data = [
                'subject'     => 'Welcome to ' . ($settings['system_name'] ?? 'Zolo Schools'),
                'email'       => $school->support_email,
                'email_body'  => $email_body
            ];

            // The school is already provisioned and marked installed by this point, so
            // a mail failure must not fail the job. It previously threw and retried
            // three times when SMTP was unconfigured - and 'mail_username' is a name,
            // not an address, so from() was being handed an invalid sender.
            try {
                $sender = filter_var($settings['mail_from_address'] ?? null, FILTER_VALIDATE_EMAIL)
                    ?: config('mail.from.address');

                if (empty(config('mail.mailers.smtp.host')) || empty($sender)) {
                    Log::info("Welcome email skipped for school {$this->schoolId}: mail is not configured.");
                } else {
                    Mail::send('schools.email', $data, static function ($message) use ($data, $sender, $settings) {
                        $message->to($data['email'])
                            ->from($sender, $settings['system_name'] ?? config('app.name'))
                            ->subject($data['subject']);
                    });

                    if (!$school->user->hasVerifiedEmail()) {
                        $school->user->sendEmailVerificationNotification();
                    }
                }
            } catch (Throwable $mailError) {
                Log::warning("Welcome email failed for school {$this->schoolId}: " . $mailError->getMessage());
            }

        } catch (Throwable $e) {
            Log::error("School database setup failed for school ID: {$this->schoolId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("School database setup job failed permanently for school ID: {$this->schoolId}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    private function replacePlaceholders($school, $user, $settings, $schoolCode): string
    {
        $templateContent = $settings['email_template_school_registration'] ?? '';
        
        $placeholders = [
            '{school_admin_name}' => $user->full_name,
            '{code}' => $schoolCode,
            '{email}' => $user->email,
            '{password}' => $user->mobile,
            '{school_name}' => $school->name ?? '',
            '{super_admin_name}' => $settings['super_admin_name'] ?? 'Super Admin',
            '{support_email}' => $settings['mail_username'] ?? '',
            '{contact}' => $settings['mobile'] ?? '',
            '{system_name}' => $settings['system_name'] ?? 'Zolo Schools',
            '{url}' => url('/'),
        ];

        foreach ($placeholders as $placeholder => $replacement) {
            $templateContent = str_replace($placeholder, $replacement, $templateContent);
        }

        return $templateContent;
    }
} 