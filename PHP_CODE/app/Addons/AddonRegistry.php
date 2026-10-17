<?php

namespace App\Addons;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions contributed by the institutional addons (Library, Hostel, Canteen).
 *
 * SchoolDataService::createPermissions() deletes every permission it does not list,
 * and createSchoolAdminRole() re-syncs the School Admin role from a fixed list, so an
 * addon that created its permissions on its own would lose them the next time a
 * school is provisioned or re-seeded. Both methods merge this registry in instead.
 */
final class AddonRegistry
{
    public const PERMISSIONS = [
        'Library Management' => [
            'library-list', 'library-create', 'library-edit', 'library-delete', 'library-circulation',
        ],
        'Hostel Management' => [
            'hostel-list', 'hostel-create', 'hostel-edit', 'hostel-delete',
            'hostel-attendance', 'hostel-gate-pass', 'hostel-fees',
        ],
        'Canteen Management' => [
            'canteen-list', 'canteen-create', 'canteen-edit', 'canteen-delete',
            'canteen-pos', 'canteen-wallet',
        ],
    ];

    /** @return string[] */
    public static function permissions(): array
    {
        return array_merge(...array_values(self::PERMISSIONS));
    }

    /** @return array<int, array{name: string}> in the shape createPermissions() upserts */
    public static function permissionRows(): array
    {
        return array_map(static fn($name) => ['name' => $name], self::permissions());
    }

    /**
     * Register an addon as a sellable feature on the master database, switched off
     * (is_default = 0) so no school gets it until a Super Admin adds it to a package
     * or sells it as an addon. Same shape as the Photo Booth registration.
     */
    public static function registerFeature(string $name): void
    {
        $db = DB::connection('mysql');
        if ($db->table('features')->where('name', $name)->exists()) {
            return;
        }

        $row = ['name' => $name, 'is_default' => 0, 'created_at' => now(), 'updated_at' => now()];
        foreach (['status' => 1, 'required_vps' => 0] as $column => $value) {
            if ($db->getSchemaBuilder()->hasColumn('features', $column)) {
                $row[$column] = $value;
            }
        }
        $db->table('features')->insert($row);
    }

    public static function unregisterFeature(string $name): void
    {
        DB::connection('mysql')->table('features')->where('name', $name)->delete();
    }

    /**
     * Create the addon permissions on the current (school) connection and grant them
     * to that school's School Admin role. Safe to run repeatedly.
     */
    public static function syncSchoolPermissions(?string $connection = null): void
    {
        $db = DB::connection($connection);
        if (!$db->getSchemaBuilder()->hasTable('permissions')) {
            return;
        }

        $now = now();
        foreach (self::permissions() as $name) {
            $db->table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                ['updated_at' => $now, 'created_at' => $now]
            );
        }

        $permissionIds = $db->table('permissions')->whereIn('name', self::permissions())->pluck('id');
        foreach ($db->table('roles')->where('name', 'School Admin')->pluck('id') as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $db->table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
