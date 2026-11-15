<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allow Finance Officers to review payroll findings and complete the lifecycle
 * in single-officer organizations. All actions remain separately permission-
 * gated, anomaly-gated, and attributed in the audit log.
 */
return new class extends Migration
{
    private const NEW_RATIONALE = 'Inactive: authorized users may complete multiple payroll lifecycle stages in a single-officer workflow. Each action still requires its permission and creates an audit record.';

    private const OLD_RATIONALE = 'Running and approving the same payroll removes the maker-checker control over disbursement.';

    public function up(): void
    {
        $roleId = DB::table('roles')->where('slug', 'finance_officer')->value('id');
        if ($roleId !== null) {
            $permissionId = DB::table('permissions')->where('slug', 'payroll.anomalies.review')->value('id');
            if ($permissionId === null) {
                throw new RuntimeException('Permission payroll.anomalies.review is missing; run RolePermissionSeeder before this migration.');
            }

            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
            Cache::forget("auth:role_perms:{$roleId}");
        }

        if (Schema::hasTable('sod_conflict_rules')) {
            DB::table('sod_conflict_rules')->where('code', 'payroll_compute_vs_approve')->update([
                'active' => false,
                'rationale' => self::NEW_RATIONALE,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('slug', 'finance_officer')->value('id');
        $permissionId = DB::table('permissions')->where('slug', 'payroll.anomalies.review')->value('id');
        if ($roleId !== null && $permissionId !== null) {
            DB::table('role_permissions')->where([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ])->delete();
            Cache::forget("auth:role_perms:{$roleId}");
        }

        if (Schema::hasTable('sod_conflict_rules')) {
            DB::table('sod_conflict_rules')->where('code', 'payroll_compute_vs_approve')->update([
                'active' => true,
                'rationale' => self::OLD_RATIONALE,
                'updated_at' => now(),
            ]);
        }
    }
};
