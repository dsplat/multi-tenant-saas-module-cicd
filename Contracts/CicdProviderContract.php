<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Contracts;

use MultiTenantSaas\Modules\Cicd\Models\Repository;
use MultiTenantSaas\Modules\Cicd\Models\Workspace;

/**
 * CI/CD provider 契约。
 *
 * 每个适配模块（GitLab / GitHub / 云效）实现本契约并注册到 CicdProviderRegistry。
 * 返回值是 provider 无关的数组；核心负责幂等 upsert 与租户隔离。
 */
interface CicdProviderContract
{
    /** provider 标识：gitlab|github|yunxiao */
    public function key(): string;

    /**
     * 能力声明。
     *
     * @return array{forge:bool,executor:bool,control:bool,resources:list<string>}
     */
    public function capabilities(): array;

    /**
     * 工作区下的仓库。
     *
     * @return iterable<int, array{external_id:string,path:string,name:string,default_branch?:?string,web_url?:?string,archived?:bool,last_commit_sha?:?string,last_activity_at?:?string,metadata?:array}>
     */
    public function fetchRepositories(Workspace $workspace): iterable;

    /**
     * 仓库的流水线/构建。
     *
     * @return iterable<int, array{external_id:string,ref?:?string,commit_sha?:?string,status:string,source?:?string,duration?:?int,started_at?:?string,finished_at?:?string,web_url?:?string,metadata?:array}>
     */
    public function fetchPipelines(Repository $repository): iterable;

    /**
     * 仓库的发布/tag。
     *
     * @return iterable<int, array{external_id:string,version?:?string,commit_sha?:?string,name?:?string,notes?:?string,artifact_url?:?string,released_at?:?string,web_url?:?string,metadata?:array}>
     */
    public function fetchReleases(Repository $repository): iterable;

    /**
     * 仓库的部署记录。
     *
     * @return iterable<int, array{external_id:string,environment?:?string,status:string,release_external_id?:?string,deployed_at?:?string,operator?:?string,web_url?:?string,metadata?:array}>
     */
    public function fetchDeployments(Repository $repository): iterable;
}
