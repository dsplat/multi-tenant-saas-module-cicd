<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use MultiTenantSaas\Modules\Cicd\Http\Controllers\CicdController;

/*
|--------------------------------------------------------------------------
| CI/CD 跟踪 — 租户 API
|--------------------------------------------------------------------------
| 基类统一附加 api + auth:sanctum + throttle:api + tenant.identify
| + VerifyOperatorTenant，前缀 api/v1。
*/

Route::prefix(config('cicd.route_prefix', ''))->middleware('operator.identify')->group(function () {
    Route::get('cicd/overview', [CicdController::class, 'overview'])
        ->middleware('capability:common.cicd.view');

    Route::get('cicd/workspaces', [CicdController::class, 'workspaces'])
        ->middleware('capability:common.cicd.view');

    Route::post('cicd/workspaces', [CicdController::class, 'storeWorkspace'])
        ->middleware('capability:common.cicd.manage');

    Route::put('cicd/workspaces/{workspace}', [CicdController::class, 'updateWorkspace'])
        ->middleware('capability:common.cicd.manage');

    Route::get('cicd/workspaces/{workspace}/repositories', [CicdController::class, 'repositories'])
        ->middleware('capability:common.cicd.view');

    Route::post('cicd/workspaces/{workspace}/sync', [CicdController::class, 'sync'])
        ->middleware('capability:common.cicd.sync');

    Route::get('cicd/projects/{project}/status', [CicdController::class, 'projectStatus'])
        ->middleware('capability:common.cicd.view');

    Route::get('cicd/projects/{project}/repositories', [CicdController::class, 'projectRepositories'])
        ->middleware('capability:common.cicd.view');

    Route::post('cicd/projects/{project}/repositories', [CicdController::class, 'linkProjectRepository'])
        ->middleware('capability:common.cicd.manage');

    Route::delete('cicd/projects/{project}/repositories/{repository}', [CicdController::class, 'unlinkProjectRepository'])
        ->middleware('capability:common.cicd.manage');

    Route::get('cicd/repositories/{repository}/pipelines', [CicdController::class, 'pipelines'])
        ->middleware('capability:common.cicd.view');

    Route::get('cicd/repositories/{repository}/releases', [CicdController::class, 'releases'])
        ->middleware('capability:common.cicd.view');

    Route::get('cicd/repositories/{repository}/deployments', [CicdController::class, 'deployments'])
        ->middleware('capability:common.cicd.view');
});
