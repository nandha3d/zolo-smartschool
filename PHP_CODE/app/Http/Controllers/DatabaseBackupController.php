<?php

namespace App\Http\Controllers;

use App\Models\CertificateTemplate;
use App\Models\ClassGroup;
use App\Models\ExpenseCategory;
use App\Models\Faq;
use App\Models\FeesType;
use App\Models\File as ModelsFile;
use App\Models\FormField;
use App\Models\Grade;
use App\Models\Holiday;
use App\Models\Mediums;
use App\Models\PaymentTransaction;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\Semester;
use App\Models\SessionYear;
use App\Models\Shift;
use App\Models\Slider;
use App\Models\Staff;
use App\Models\Stream;
use App\Models\Students;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\User;
use App\Repositories\DatabaseBackup\DatabaseBackupInterface;
use App\Services\BootstrapTableService;
use App\Services\ResponseService;
use App\Services\SubscriptionService;
use App\Services\UploadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Backups are written to storage/app/private (never the web-served public disk) and
 * handed out through an authenticated download route. Each archive carries a manifest
 * signed with an HMAC derived from APP_KEY and bound to the backup scope and owner, and
 * restore refuses any archive whose signature does not verify. On top of that, restore
 * only runs CREATE TABLE IF NOT EXISTS / INSERT INTO statements against plain table names
 * of the current database, and only copies media files with an allowed upload extension.
 * Together this stops an uploaded archive from running arbitrary SQL (every tenant DB is
 * reached with the same MySQL user, so that would reach the central database) or from
 * dropping a script into the public disk.
 */
class DatabaseBackupController extends Controller
{
    private const MANIFEST_ENTRY = 'database-backup/manifest.json';

    private const SCHOOL_EXCLUDED_TABLES = ['addons', 'attachments', 'categories', 'chats', 'failed_jobs', 'features', 'feature_sections', 'feature_section_lists', 'guidances', 'languages', 'messages', 'migrations', 'packages', 'package_features', 'password_resets', 'personal_access_tokens', 'staff_support_schools', 'system_settings', 'user_status_for_next_cycles', 'database_backups'];

    private const SUPER_ADMIN_EXCLUDED_TABLES = ['addons', 'failed_jobs', 'features', 'feature_sections', 'feature_section_lists', 'guidances', 'languages', 'migrations', 'packages', 'package_features', 'password_resets', 'personal_access_tokens', 'staff_support_schools', 'system_settings'];

    private DatabaseBackupInterface $databaseBackup;
    private SubscriptionService $subscriptionService;

    public function __construct(DatabaseBackupInterface $databaseBackup, SubscriptionService $subscriptionService)
    {
        $this->databaseBackup = $databaseBackup;
        $this->subscriptionService = $subscriptionService;
    }

    public function index()
    {
        if ((Auth::user()->hasRole(['Super Admin', 'School Admin']) || Auth::user()->hasPermissionTo('database-backup'))) {
            $schoolId = Auth::user()->hasRole('School Admin') ? Auth::user()->school_id : Auth::user()->id;
            return view('database-backup.index', compact('schoolId'));

        } else {
            return redirect()->route('home')->with('error', "You Don't have enough permissions");
        }
    }

    public function store()
    {
        ResponseService::noPermissionThenSendJson('database-backup');
        [$backupType, $ownerId] = $this->backupScope();
        $current_version = SystemSetting::where('name', 'system_version')->first()['data'];

        try {
            if ($backupType === 'schools') {
                $backupData = $this->dumpSchoolDatabase($ownerId);
            } else {
                // Set default connection for Super Admin
                DB::setDefaultConnection('mysql');
                $backupData = $this->dumpSuperAdminDatabase();
            }

            $file_name = $this->writeArchive($backupType, $ownerId, $backupData, $current_version);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "DatabaseBackup Controller -> Store Method");
            ResponseService::errorResponse();
        }

        ResponseService::successResponse('Backup completed successfully', route('database-backup.download', ['file' => $file_name]));
    }

    /**
     * Stream a backup archive to its owner, then remove it so finished backups do not
     * accumulate on the server.
     */
    public function download(string $file)
    {
        ResponseService::noPermissionThenRedirect('database-backup');
        [$backupType, $ownerId] = $this->backupScope();

        if (basename($file) !== $file || !preg_match('/^database_backup_\d+_[0-9-]+-\(V-[0-9A-Za-z._-]+\)\.zip$/', $file)) {
            abort(404);
        }

        $path = $this->backupDirectory($backupType, $ownerId) . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) {
            abort(404);
        }

        return response()->download($path, $file, ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    public function show()
    {
        ResponseService::noPermissionThenRedirect('database-backup');
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');

        $sql = $this->databaseBackup->builder()
            ->where(function ($query) use ($search) {
                $query->when($search, function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('id', 'LIKE', "%$search%")->orwhere('title', 'LIKE', "%$search%")->orwhere('description', 'LIKE', "%$search%")->orwhere('date', 'LIKE', "%$search%");
                    });
                });
            });

        $total = $sql->count();
        if ($offset >= $total && $total > 0) {
            $lastPage = floor(($total - 1) / $limit) * $limit; // calculate last page offset
            $offset = $lastPage;
        }
        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;
        foreach ($res as $row) {
            $operate = BootstrapTableService::button('fa fa-refresh', '#', ['restore-database', 'btn-gradient-info'], ['title' => trans("restore"), 'data-id' => $row->id]);
            $operate .= BootstrapTableService::deleteButton(url('database-backup', $row->id));

            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function destroy($id)
    {
        ResponseService::noPermissionThenSendJson('database-backup');
        try {
            [$backupType, $ownerId] = $this->backupScope();
            $databaseBackup = $this->databaseBackup->findById($id);
            $name = basename((string) $databaseBackup->name);
            $directory = $this->backupDirectory($backupType, $ownerId);

            foreach ([$name . '.sql', $name . '.zip'] as $file) {
                if (is_file($directory . DIRECTORY_SEPARATOR . $file)) {
                    File::delete($directory . DIRECTORY_SEPARATOR . $file);
                }
            }

            $this->databaseBackup->deleteById($id);
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "DatabaseBackup Controller -> Destroy Method");
            ResponseService::errorResponse();
        }
    }

    public function restore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'zip_file' => 'required|file|mimes:zip',
        ]);

        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }

        [$backupType, $ownerId] = $this->backupScope();

        $zip = new ZipArchive;
        if ($zip->open($request->file('zip_file')->getRealPath()) !== true) {
            ResponseService::errorResponse('The backup archive could not be opened.');
        }

        // Verify everything before touching the existing data.
        try {
            $manifest = $this->verifiedManifest($zip, $backupType, $ownerId);
            $sql = $zip->getFromName($manifest['sql']);
            if ($sql === false || !hash_equals($manifest['sha256'], hash('sha256', $sql))) {
                throw new RuntimeException('The backup archive has been modified.');
            }

            if ($backupType === 'super-admin') {
                DB::setDefaultConnection('mysql');
            }

            $excludedTables = $backupType === 'schools' ? self::SCHOOL_EXCLUDED_TABLES : self::SUPER_ADMIN_EXCLUDED_TABLES;
            $currentTables = $this->listTables();
            $statements = $this->restorableStatements($sql, $currentTables, $excludedTables);
            $mediaEntries = $this->restorableMediaEntries($zip, $ownerId);
        } catch (RuntimeException $e) {
            $zip->close();
            ResponseService::errorResponse($e->getMessage());
        }

        try {
            $this->replaceMedia($zip, $ownerId, $mediaEntries);
            $zip->close();

            DB::statement("SET FOREIGN_KEY_CHECKS = 0");
            try {
                foreach (array_diff($currentTables, $excludedTables) as $table) {
                    DB::table($table)->truncate();
                }
                foreach ($statements as $statement) {
                    DB::unprepared($statement);
                }
            } finally {
                DB::statement("SET FOREIGN_KEY_CHECKS = 1");
            }

            if ($backupType === 'schools') {
                // add school current version migration
                $schools = School::withTrashed()->where('id', Auth::user()->school_id)->get();

                foreach ($schools as $school) {
                    Config::set('database.connections.school.database', $school->database_name);
                    DB::purge('school');
                    DB::connection('school')->reconnect();
                    DB::setDefaultConnection('school');

                    Artisan::call('migrate', [
                        '--database' => 'school',
                        '--path' => 'database/migrations/schools',
                        '--force' => true,
                    ]);
                }
            }
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "DatabaseBackup Controller -> Restore Method");
            ResponseService::errorResponse();
        }

        ResponseService::successResponse('Data Restore Successfully');
    }

    public function getTableNameFromQuery($query)
    {
        if (preg_match('/CREATE TABLE `?(\w+)`?/', $query, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function deleteOldRecords($subscription)
    {
        $schoolId = $subscription->school_id;
        if ($subscription) {

            $guardian_ids = Students::where('school_id', $schoolId)->pluck('guardian_id')->toArray();

            Mediums::where('school_id', $schoolId)->delete();
            User::where('school_id', $schoolId)->whereNot('id', Auth::user()->id)->whereNot('id', $subscription->school->admin_id)->delete();
            User::whereIn('id', $guardian_ids)->delete();
            CertificateTemplate::where('school_id', $schoolId)->delete();
            ClassGroup::where('school_id', $schoolId)->delete();
            ExpenseCategory::where('school_id', $schoolId)->delete();
            Faq::where('school_id', $schoolId)->delete();
            FeesType::where('school_id', $schoolId)->delete();
            ModelsFile::where('school_id', $schoolId)->delete();
            FormField::where('school_id', $schoolId)->delete();
            SessionYear::where('school_id', $schoolId)->delete();
            Grade::where('school_id', $schoolId)->delete();
            Holiday::where('school_id', $schoolId)->delete();
            SchoolSetting::where('school_id', $schoolId)->delete();
            Section::where('school_id', $schoolId)->delete();
            Semester::where('school_id', $schoolId)->delete();
            Shift::where('school_id', $schoolId)->delete();
            Slider::where('school_id', $schoolId)->delete();
            Stream::where('school_id', $schoolId)->delete();
            Subscription::where('school_id', $schoolId)->delete();
            PaymentTransaction::where('school_id', $schoolId)->delete();
        }
    }

    /**
     * @return array{0: string, 1: int} backup type ('schools' | 'super-admin') and owner id
     */
    private function backupScope(): array
    {
        $user = Auth::user();
        if ($user->hasRole('School Admin')) {
            return ['schools', (int) $user->school_id];
        }
        if ($user->hasRole('Super Admin')) {
            return ['super-admin', (int) $user->id];
        }

        ResponseService::errorResponse("You Don't have enough permissions");
    }

    private function backupDirectory(string $backupType, int $ownerId): string
    {
        return storage_path('app/private/database-backup/' . $backupType . '/' . $ownerId);
    }

    private function listTables(): array
    {
        return array_map('current', DB::select('SHOW TABLES'));
    }

    private function signingKey(): string
    {
        return hash_hmac('sha256', 'zolo-database-backup', (string) config('app.key'));
    }

    private function signature(string $backupType, int $ownerId, string $sha256): string
    {
        return hash_hmac('sha256', $backupType . '|' . $ownerId . '|' . $sha256, $this->signingKey());
    }

    private function dumpSchoolDatabase(int $user_Id): string
    {
        $tableNames = array_values(array_diff($this->listTables(), self::SCHOOL_EXCLUDED_TABLES));

        $subscription = $this->subscriptionService->active_subscription(Auth::user()->school_id);

        $staff_ids = Staff::whereHas('user', function ($q) use ($user_Id) {
            $q->where('school_id', $user_Id);
        })->pluck('id')->toArray();

        $students = Students::where('school_id', $user_Id)->withTrashed()->get();
        $guardian_ids = array_values(array_unique($students->pluck('guardian_id')->toArray()));

        $roles_id = Role::where('school_id', Auth::user()->school_id)->pluck('id')->toArray();

        // Tables requiring additional conditions
        $tablesWithAdditionalConditions = [
            'users' => function ($query) use ($user_Id, $guardian_ids) {
                $query->where(function ($q) use ($user_Id, $guardian_ids) {
                    $q->where('school_id', $user_Id)
                        ->orWhereIn('id', $guardian_ids);
                });
            },
            'fees_advance' => function ($query) use ($guardian_ids) {
                $query->whereIn('parent_id', $guardian_ids);
            },
            'staffs' => function ($query) use ($staff_ids) {
                $query->whereIn('id', $staff_ids);
            },
            'staff_salaries' => function ($query) use ($staff_ids) {
                $query->whereIn('staff_id', $staff_ids);
            },
            'model_has_roles' => function ($query) use ($roles_id) {
                $query->whereIn('role_id', $roles_id);
            },
            'role_has_permissions' => function ($query) use ($roles_id) {
                $query->whereIn('role_id', $roles_id);
            },
            'subscription_features' => function ($query) use ($subscription) {
                $query->where('subscription_id', $subscription->id);
            },
        ];

        $backupData = '';
        foreach ($tableNames as $table) {
            $createTableSQL = DB::select("SHOW CREATE TABLE `$table`");
            $createTable = $createTableSQL[0]->{'Create Table'};
            $backupData .= str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $createTable) . ";\n\n";

            $query = DB::table($table);
            if (Schema::hasColumn($table, 'school_id')) {
                if (!array_key_exists($table, $tablesWithAdditionalConditions)) {
                    $query->where('school_id', $user_Id);
                } else {
                    $tablesWithAdditionalConditions[$table]($query);
                }
            } elseif (array_key_exists($table, $tablesWithAdditionalConditions)) {
                $tablesWithAdditionalConditions[$table]($query);
            }

            foreach ($query->get() as $row) {
                $rowArray = (array) $row;

                // For class_subjects table, skip virtual_semester_id
                if ($table === 'class_subjects') {
                    unset($rowArray['virtual_semester_id']);
                }

                $values = array_map(function ($value) use ($table) {
                    if (is_null($value)) {
                        return 'NULL';
                    }

                    // Special handling for settings tables that contain HTML content or email templates
                    if (in_array($table, ['school_settings', 'system_settings'])) {
                        if (
                            strpos($value, '<') !== false || strpos($value, '>') !== false ||
                            strpos($value, '{') !== false || strpos($value, '}') !== false
                        ) {
                            $value = str_replace('\\', '\\\\', $value); // Escape backslashes first
                            $value = str_replace("'", "\\'", $value);   // Escape single quotes
                            $value = str_replace('"', '\\"', $value);   // Escape double quotes
                            return "'" . $value . "'";
                        }
                    }

                    // For all other tables/content
                    $value = str_replace("'", "''", $value); // Escape single quotes
                    $value = str_replace("\\", "\\\\", $value); // Escape backslashes
                    return "'" . $value . "'";
                }, $rowArray);

                if ($table === 'class_subjects') {
                    $columns = array_keys($rowArray);
                    $backupData .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                } else {
                    $backupData .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
                }
            }
            $backupData .= "\n";
        }

        return $backupData;
    }

    private function dumpSuperAdminDatabase(): string
    {
        $tableNames = array_values(array_diff($this->listTables(), self::SUPER_ADMIN_EXCLUDED_TABLES));

        $backupData = '';
        foreach ($tableNames as $table) {
            $createTableSQL = DB::select("SHOW CREATE TABLE `$table`");
            $createTable = $createTableSQL[0]->{'Create Table'};
            $backupData .= str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $createTable) . ";\n\n";

            foreach (DB::table($table)->get() as $row) {
                $values = array_map(function ($value) {
                    return is_null($value) ? 'NULL' : "'" . addslashes($value) . "'";
                }, (array) $row);

                $backupData .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
            }
            $backupData .= "\n";
        }

        return $backupData;
    }

    /**
     * Zip the dump, the owner's media and a signed manifest into the private backup
     * directory. Returns the archive's file name.
     */
    private function writeArchive(string $backupType, int $ownerId, string $backupData, string $current_version): string
    {
        $directory = $this->backupDirectory($backupType, $ownerId);
        File::ensureDirectoryExists($directory, 0750);

        $timestamp = Carbon::now()->format('Y-m-d');
        $safeVersion = preg_replace('/[^0-9A-Za-z._-]/', '', $current_version);
        $file_name = "database_backup_{$ownerId}_{$timestamp}-(V-{$safeVersion}).zip";
        $zip_file_path = $directory . DIRECTORY_SEPARATOR . $file_name;
        if (is_file($zip_file_path)) {
            File::delete($zip_file_path);
        }

        $sqlEntry = 'database-backup/database_backup_' . $ownerId . '_' . $timestamp . '.sql';
        $sha256 = hash('sha256', $backupData);
        $manifest = [
            'format' => 2,
            'type' => $backupType,
            'owner' => $ownerId,
            'sql' => $sqlEntry,
            'sha256' => $sha256,
            'version' => $current_version,
            'created_at' => Carbon::now()->toIso8601String(),
            'signature' => $this->signature($backupType, $ownerId, $sha256),
        ];

        $zip = new ZipArchive;
        if ($zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create backup archive.');
        }

        $zip->addFromString($sqlEntry, $backupData);
        $zip->addFromString(self::MANIFEST_ENTRY, json_encode($manifest, JSON_PRETTY_PRINT));

        $zip_media_folder = 'media';
        $zip->addEmptyDir($zip_media_folder);
        $public_folder_path = storage_path('app/public/' . $ownerId);
        if (File::isDirectory($public_folder_path)) {
            foreach (File::directories($public_folder_path) as $subfolderPath) {
                $relativeSubfolder = $zip_media_folder . '/' . $ownerId . '/' . basename($subfolderPath);
                $zip->addEmptyDir($relativeSubfolder);
                foreach (File::files($subfolderPath) as $file) {
                    $zip->addFile($file->getPathname(), $relativeSubfolder . '/' . $file->getFilename());
                }
            }
        }

        $zip->close();

        return $file_name;
    }

    private function verifiedManifest(ZipArchive $zip, string $backupType, int $ownerId): array
    {
        $raw = $zip->getFromName(self::MANIFEST_ENTRY);
        $manifest = $raw === false ? null : json_decode($raw, true);

        if (!is_array($manifest) || !isset($manifest['type'], $manifest['owner'], $manifest['sql'], $manifest['sha256'], $manifest['signature'])) {
            throw new RuntimeException('This archive was not created by this server\'s backup tool (or predates signed backups) and cannot be restored.');
        }

        $expected = $this->signature($backupType, $ownerId, (string) $manifest['sha256']);
        if ($manifest['type'] !== $backupType || (int) $manifest['owner'] !== $ownerId || !hash_equals($expected, (string) $manifest['signature'])) {
            throw new RuntimeException('The backup signature is invalid for this account.');
        }

        if (!preg_match('#^database-backup/database_backup_\d+_[0-9-]+\.sql$#', (string) $manifest['sql'])) {
            throw new RuntimeException('The backup archive is malformed.');
        }

        return $manifest;
    }

    /**
     * Split the dump into statements and refuse anything other than recreating or
     * filling a plain-named table of this database.
     */
    private function restorableStatements(string $sql, array $currentTables, array $excludedTables): array
    {
        $statements = [];
        $knownTables = $currentTables;
        foreach ($this->splitSqlStatements($sql) as $statement) {
            if (preg_match('/^CREATE TABLE IF NOT EXISTS `([A-Za-z0-9_]+)` \(/', $statement, $m)) {
                $table = $m[1];
                $knownTables[] = $table;
            } elseif (preg_match('/^INSERT INTO `([A-Za-z0-9_]+)` (\(`[A-Za-z0-9_`, ]+\) )?VALUES \(/', $statement, $m)) {
                $table = $m[1];
                if (!in_array($table, $knownTables, true)) {
                    throw new RuntimeException("The backup writes to an unknown table ({$table}).");
                }
            } else {
                throw new RuntimeException('The backup contains a statement that is not allowed in a restore.');
            }

            if (in_array($table, $excludedTables, true)) {
                throw new RuntimeException("The backup writes to a protected table ({$table}).");
            }

            $statements[] = $statement;
        }

        return $statements;
    }

    /**
     * Split on top-level semicolons, honouring quoted strings ('…', "…", `…`), backslash
     * escapes and doubled quotes, so a value containing ";\n" does not break a statement.
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $length = strlen($sql);
        $start = 0;
        $quote = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($quote !== null) {
                if ($char === '\\' && $quote !== '`') {
                    $i++;
                } elseif ($char === $quote) {
                    if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
            } elseif ($char === ';') {
                $statement = trim(substr($sql, $start, $i - $start));
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $start = $i + 1;
            }
        }

        if ($quote !== null) {
            throw new RuntimeException('The backup SQL is malformed.');
        }

        $tail = trim(substr($sql, $start));
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }

    /**
     * @return array<int, array{index: int, folder: string, file: string}>
     */
    private function restorableMediaEntries(ZipArchive $zip, int $ownerId): array
    {
        $entries = [];
        $pattern = '#^media/' . $ownerId . '/([A-Za-z0-9][A-Za-z0-9_-]*)/([A-Za-z0-9][A-Za-z0-9_.-]*)$#';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!preg_match($pattern, $name, $m)) {
                continue;
            }
            $extension = strtolower(pathinfo($m[2], PATHINFO_EXTENSION));
            if (!in_array($extension, UploadService::ALLOWED_EXTENSIONS, true)) {
                continue;
            }
            $entries[] = ['index' => $i, 'folder' => $m[1], 'file' => $m[2]];
        }

        return $entries;
    }

    private function replaceMedia(ZipArchive $zip, int $ownerId, array $entries): void
    {
        $public_folder_path = storage_path('app/public/' . $ownerId);
        File::ensureDirectoryExists($public_folder_path, 0755);

        // Old Data Delete from public folder
        foreach (File::directories($public_folder_path) as $subfolder) {
            foreach (File::files($subfolder) as $file) {
                File::delete($file);
            }
        }

        foreach ($entries as $entry) {
            $folder = $public_folder_path . '/' . $entry['folder'];
            File::ensureDirectoryExists($folder, 0755);

            $source = $zip->getStream($zip->getNameIndex($entry['index']));
            if ($source === false) {
                continue;
            }
            $target = fopen($folder . '/' . $entry['file'], 'wb');
            stream_copy_to_stream($source, $target);
            fclose($target);
            fclose($source);
        }
    }
}
