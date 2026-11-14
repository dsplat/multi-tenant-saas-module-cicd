<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MultiTenantSaas\Contracts\IdGeneratorContract;

/**
 * CI/CD 跟踪：RBAC 权限注册。
 *
 * 幂等：permissions 表缺失跳过；权限按 name 复用；tenant_admin 挂接去重。
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['name' => 'cicd.view', 'display_name' => '查看 CI/CD 工作区、仓库、流水线、发布与部署'],
        ['name' => 'cicd.manage', 'display_name' => '管理工作区与仓库绑定'],
        ['name' => 'cicd.sync', 'display_name' => '触发 CI/CD 数据同步'],
    ];

    public function up(): void
    {
        $this->registerPermissions();
        $this->attachToTenantAdmin();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')
            ->whereIn('name', array_column(self::PERMISSIONS, 'name'))
            ->delete();
    }

    private function registerPermissions(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $idGenerator = app(IdGeneratorContract::class);
        $now = now();

        foreach (self::PERMISSIONS as $permission) {
            if (DB::table('permissions')->where('name', $permission['name'])->exists()) {
                continue;
            }

            DB::table('permissions')->insert([
                'permission_id' => $idGenerator->generate(),
                'name' => $permission['name'],
                'display_name' => $permission['display_name'],
                'group' => 'cicd',
                'description' => $permission['display_name'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function attachToTenantAdmin(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions') || ! Schema::hasTable('permissions')) {
            return;
        }

        $role = DB::table('roles')->where('name', 'tenant_admin')->first();
        if (! $role) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_column(self::PERMISSIONS, 'name'))
            ->pluck('permission_id')
            ->all();

        foreach ($permissionIds as $permissionId) {
            if (DB::table('role_permissions')
                ->where('role_id', $role->role_id)
                ->where('permission_id', $permissionId)
                ->exists()) {
                continue;
            }

            DB::table('role_permissions')->insert([
                'role_id' => $role->role_id,
                'permission_id' => $permissionId,
            ]);
        }
    }
};
