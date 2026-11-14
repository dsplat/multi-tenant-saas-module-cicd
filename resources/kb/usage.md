CI/CD 跟踪（cicd）
==================

多租户 CI/CD 投影：工作区（分组/组织）→ 仓库 → 流水线 → 发布 → 部署。
源数据仍在 GitLab / GitHub / 云效；本模块只做只读投影，不执行构建，不保管制品。

## 能力

- 工作区：provider + 分组/组织 + 同步状态
- 仓库：默认分支、最近提交、最近活跃
- 流水线：ref、commit、状态、耗时、链接
- 发布：tag/版本、commit、制品链接
- 部署：环境、状态、执行人、时间

## Provider 契约

适配模块实现 `CicdProviderContract`，在 ServiceProvider 中注册到 `CicdProviderRegistry`：

```php
$this->app->make(CicdProviderRegistry::class)->register(new GitlabCicdProvider(...));
```

核心不依赖任何厂商；未注册 provider 的工作区同步结果为 `unsupported`。

## 同步

```bash
php artisan cicd:sync --tenant=<tenant_id>
php artisan cicd:sync --workspace=<workspace_id>
php artisan cicd:sync --provider=gitlab
```

同步按 `(tenant_id, external_id)` 幂等 upsert；`cicd_sync_cursors` 记录最近同步时间。

## API

前缀 `api/v1`，认证 `auth:sanctum` + 租户识别，能力点 `common.cicd.view` / `common.cicd.sync`。

| 方法 | 路径 | 能力 |
|---|---|---|
| GET | `cicd/overview` | common.cicd.view |
| GET | `cicd/workspaces` | common.cicd.view |
| POST | `cicd/workspaces` | common.cicd.manage |
| PUT | `cicd/workspaces/{id}` | common.cicd.manage |
| GET | `cicd/workspaces/{id}/repositories` | common.cicd.view |
| POST | `cicd/workspaces/{id}/sync` | common.cicd.sync |
| GET | `cicd/repositories/{id}/pipelines` | common.cicd.view |
| GET | `cicd/repositories/{id}/releases` | common.cicd.view |
| GET | `cicd/repositories/{id}/deployments` | common.cicd.view |
| GET | `cicd/projects/{project}/status` | common.cicd.view |
| GET | `cicd/projects/{project}/repositories` | common.cicd.view |
| POST | `cicd/projects/{project}/repositories` | common.cicd.manage |
| DELETE | `cicd/projects/{project}/repositories/{repo}` | common.cicd.manage |

## 权限

| 权限 | 说明 |
|---|---|
| `cicd.view` | 查看工作区/仓库/流水线/发布/部署 |
| `cicd.manage` | 管理工作区与仓库绑定（后续） |
| `cicd.sync` | 触发同步 |

## 边界

- 不执行 build/test/release；这些在 CI 系统里。
- 不复制代码、制品、日志；只存指针和状态。
- 不做业务发布确认；发布确认在交付模块。
