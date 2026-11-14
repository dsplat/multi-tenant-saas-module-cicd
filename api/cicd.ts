import { http, extractListResult } from '@multi-tenant-saas/console/shared/http'

export interface CicdOverview {
  workspaces: number
  repositories: number
  pipelines: number
  releases: number
  deployments: number
  failed_pipelines: number
}

export interface CicdWorkspace {
  workspace_id: string
  provider_key: string
  name: string
  group_path: string | null
  external_group_id: string
  sync_status: string
  last_synced_at: string | null
  last_sync_error: string | null
  repositories_count: number
}

export interface CicdRepository {
  repo_id: string
  external_id: string
  path: string
  name: string
  default_branch: string | null
  web_url: string | null
  archived: boolean
  last_commit_sha: string | null
  last_activity_at: string | null
}

export interface CicdPipeline {
  pipeline_id: string
  external_id: string
  ref: string | null
  commit_sha: string | null
  status: string
  source: string | null
  duration: number | null
  started_at: string | null
  finished_at: string | null
  web_url: string | null
}

export interface CicdRelease {
  release_id: string
  external_id: string
  version: string | null
  commit_sha: string | null
  name: string | null
  artifact_url: string | null
  released_at: string | null
  web_url: string | null
}

export interface CicdDeployment {
  deployment_id: string
  external_id: string
  environment: string | null
  status: string
  deployed_at: string | null
  operator: string | null
  web_url: string | null
}

const BASE = '/cicd'

export async function getOverview(): Promise<CicdOverview> {
  const res = await http.get<CicdOverview>(`${BASE}/overview`)
  return res.data
}

export async function getWorkspaces(params: { page?: number; per_page?: number } = {}): Promise<{ data: CicdWorkspace[]; total: number }> {
  const res = await http.get<CicdWorkspace[] | { data: CicdWorkspace[]; total: number }>(`${BASE}/workspaces`, { params })
  return extractListResult(res)
}

export async function getRepositories(workspaceId: string, params: { page?: number; per_page?: number; keyword?: string } = {}): Promise<{ data: CicdRepository[]; total: number }> {
  const res = await http.get<CicdRepository[] | { data: CicdRepository[]; total: number }>(`${BASE}/workspaces/${workspaceId}/repositories`, { params })
  return extractListResult(res)
}

export async function getPipelines(repositoryId: string, params: { page?: number; per_page?: number; status?: string } = {}): Promise<{ data: CicdPipeline[]; total: number }> {
  const res = await http.get<CicdPipeline[] | { data: CicdPipeline[]; total: number }>(`${BASE}/repositories/${repositoryId}/pipelines`, { params })
  return extractListResult(res)
}

export async function getReleases(repositoryId: string, params: { page?: number; per_page?: number } = {}): Promise<{ data: CicdRelease[]; total: number }> {
  const res = await http.get<CicdRelease[] | { data: CicdRelease[]; total: number }>(`${BASE}/repositories/${repositoryId}/releases`, { params })
  return extractListResult(res)
}

export async function getDeployments(repositoryId: string, params: { page?: number; per_page?: number; environment?: string } = {}): Promise<{ data: CicdDeployment[]; total: number }> {
  const res = await http.get<CicdDeployment[] | { data: CicdDeployment[]; total: number }>(`${BASE}/repositories/${repositoryId}/deployments`, { params })
  return extractListResult(res)
}

export async function syncWorkspace(workspaceId: string): Promise<Record<string, unknown>> {
  const res = await http.post<Record<string, unknown>>(`${BASE}/workspaces/${workspaceId}/sync`)
  return res.data
}
