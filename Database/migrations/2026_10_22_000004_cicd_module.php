<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CI/CD 跟踪：工作区 / 仓库 / 流水线 / 发布 / 部署投影表。
 *
 * 源数据仍在 GitLab / GitHub / 云效；本模块只做多租户投影，
 * 以 (tenant_id, external_id) 幂等 upsert，不复制代码和制品。
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->createWorkspaces();
        $this->createRepositories();
        $this->createPipelines();
        $this->createReleases();
        $this->createDeployments();
        $this->createSyncCursors();
        $this->createProjectRepositories();
    }

    public function down(): void
    {
        Schema::dropIfExists('cicd_project_repositories');
        Schema::dropIfExists('cicd_sync_cursors');
        Schema::dropIfExists('cicd_deployments');
        Schema::dropIfExists('cicd_releases');
        Schema::dropIfExists('cicd_pipelines');
        Schema::dropIfExists('cicd_repositories');
        Schema::dropIfExists('cicd_workspaces');
    }

    private function createWorkspaces(): void
    {
        if (Schema::hasTable('cicd_workspaces')) {
            return;
        }

        Schema::create('cicd_workspaces', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->primary();
            $table->unsignedBigInteger('tenant_id');
            $table->string('provider_key', 30)->comment('gitlab|github|yunxiao');
            $table->string('name', 191);
            $table->string('external_group_id', 100)->comment('provider 侧分组/组织 ID');
            $table->string('group_path', 191)->nullable();
            $table->unsignedBigInteger('connection_id')->nullable()->comment('integration_connections.connection_id');
            $table->string('sync_status', 20)->default('idle')->comment('idle|syncing|ok|failed|unsupported');
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_error', 1000)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'provider_key', 'external_group_id'], 'cicd_ws_unique');
            $table->index(['tenant_id', 'provider_key'], 'cicd_ws_provider');
        });
    }

    private function createRepositories(): void
    {
        if (Schema::hasTable('cicd_repositories')) {
            return;
        }

        Schema::create('cicd_repositories', function (Blueprint $table) {
            $table->unsignedBigInteger('repo_id')->primary();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('workspace_id');
            $table->string('external_id', 100);
            $table->string('path', 191)->comment('group/repo');
            $table->string('name', 191);
            $table->string('default_branch', 191)->nullable();
            $table->string('web_url', 500)->nullable();
            $table->boolean('archived')->default(false);
            $table->string('last_commit_sha', 64)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'external_id'], 'cicd_repo_unique');
            $table->index(['tenant_id', 'workspace_id'], 'cicd_repo_workspace');
            $table->index(['tenant_id', 'path'], 'cicd_repo_path');
        });
    }

    private function createPipelines(): void
    {
        if (Schema::hasTable('cicd_pipelines')) {
            return;
        }

        Schema::create('cicd_pipelines', function (Blueprint $table) {
            $table->unsignedBigInteger('pipeline_id')->primary();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('repo_id');
            $table->string('external_id', 100);
            $table->string('ref', 255)->nullable();
            $table->string('commit_sha', 64)->nullable();
            $table->string('status', 20)->default('queued')->comment('queued|running|passed|failed|canceled|skipped');
            $table->string('source', 50)->nullable();
            $table->unsignedInteger('duration')->nullable()->comment('秒');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('web_url', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['repo_id', 'external_id'], 'cicd_pipeline_unique');
            $table->index(['tenant_id', 'repo_id', 'status'], 'cicd_pipeline_status');
            $table->index(['tenant_id', 'repo_id', 'finished_at'], 'cicd_pipeline_finished');
        });
    }

    private function createReleases(): void
    {
        if (Schema::hasTable('cicd_releases')) {
            return;
        }

        Schema::create('cicd_releases', function (Blueprint $table) {
            $table->unsignedBigInteger('release_id')->primary();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('repo_id');
            $table->string('external_id', 100)->comment('tag 或 release id');
            $table->string('version', 100)->nullable();
            $table->string('commit_sha', 64)->nullable();
            $table->string('name', 191)->nullable();
            $table->text('notes')->nullable();
            $table->string('artifact_url', 500)->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('web_url', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['repo_id', 'external_id'], 'cicd_release_unique');
            $table->index(['tenant_id', 'repo_id', 'released_at'], 'cicd_release_time');
        });
    }

    private function createDeployments(): void
    {
        if (Schema::hasTable('cicd_deployments')) {
            return;
        }

        Schema::create('cicd_deployments', function (Blueprint $table) {
            $table->unsignedBigInteger('deployment_id')->primary();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('repo_id');
            $table->unsignedBigInteger('release_id')->nullable();
            $table->string('external_id', 100);
            $table->string('environment', 100)->nullable();
            $table->string('status', 20)->default('queued')->comment('queued|running|success|failed|canceled');
            $table->timestamp('deployed_at')->nullable();
            $table->string('operator', 191)->nullable();
            $table->string('web_url', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['repo_id', 'external_id'], 'cicd_deploy_unique');
            $table->index(['tenant_id', 'repo_id', 'environment'], 'cicd_deploy_env');
            $table->index(['tenant_id', 'repo_id', 'deployed_at'], 'cicd_deploy_time');
        });
    }

    private function createProjectRepositories(): void
    {
        if (Schema::hasTable('cicd_project_repositories')) {
            return;
        }

        Schema::create('cicd_project_repositories', function (Blueprint $table) {
            $table->unsignedBigInteger('link_id')->primary();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('project_id')->comment('平台业务项目 ID');
            $table->unsignedBigInteger('repo_id');
            $table->boolean('enabled')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'project_id', 'repo_id'], 'cicd_project_repo_unique');
            $table->index(['tenant_id', 'project_id'], 'cicd_project_repo_project');
            $table->index(['tenant_id', 'repo_id'], 'cicd_project_repo_repo');
        });
    }

    private function createSyncCursors(): void
    {
        if (Schema::hasTable('cicd_sync_cursors')) {
            return;
        }

        Schema::create('cicd_sync_cursors', function (Blueprint $table) {
            $table->unsignedBigInteger('cursor_id')->primary();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('workspace_id');
            $table->string('resource', 40)->comment('repositories|pipelines|releases|deployments');
            $table->string('cursor', 191)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'resource'], 'cicd_cursor_unique');
            $table->index(['tenant_id', 'workspace_id'], 'cicd_cursor_workspace');
        });
    }
};
