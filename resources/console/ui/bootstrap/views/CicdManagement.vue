<template>
  <div class="page">
    <div class="page-header">
      <h2>CI/CD 跟踪</h2>
      <button class="btn btn-primary" @click="load">刷新</button>
    </div>

    <div class="row">
      <div v-for="card in cards" :key="card.key" class="col">
        <div class="card">
          <div class="card-body">
            <div class="text-muted">{{ card.label }}</div>
            <strong>{{ card.value }}</strong>
          </div>
        </div>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-body">
        <table class="table">
          <thead>
            <tr>
              <th>工作区</th>
              <th>Provider</th>
              <th>分组</th>
              <th>同步状态</th>
              <th>仓库数</th>
              <th>最近同步</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in workspaces" :key="row.workspace_id">
              <td>{{ row.name }}</td>
              <td>{{ row.provider_key }}</td>
              <td>{{ row.group_path || '-' }}</td>
              <td>{{ row.sync_status }}</td>
              <td>{{ row.repositories_count }}</td>
              <td>{{ row.last_synced_at || '-' }}</td>
              <td><button class="btn btn-sm btn-outline-primary" @click="sync(row)">同步</button></td>
            </tr>
            <tr v-if="workspaces.length === 0">
              <td colspan="7">暂无工作区</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { getOverview, getWorkspaces, syncWorkspace, type CicdOverview, type CicdWorkspace } from '@modules/Cicd/api/cicd'

const overview = ref<CicdOverview | null>(null)
const workspaces = ref<CicdWorkspace[]>([])

const cards = computed(() => [
  { key: 'workspaces', label: '工作区', value: overview.value?.workspaces ?? 0 },
  { key: 'repositories', label: '仓库', value: overview.value?.repositories ?? 0 },
  { key: 'pipelines', label: '流水线', value: overview.value?.pipelines ?? 0 },
  { key: 'releases', label: '发布', value: overview.value?.releases ?? 0 },
  { key: 'deployments', label: '部署', value: overview.value?.deployments ?? 0 },
  { key: 'failed', label: '失败构建', value: overview.value?.failed_pipelines ?? 0 },
])

async function load() {
  const [summary, page] = await Promise.all([getOverview(), getWorkspaces({ page: 1, per_page: 50 })])
  overview.value = summary
  workspaces.value = page.data
}

async function sync(row: CicdWorkspace) {
  await syncWorkspace(row.workspace_id)
  await load()
}

onMounted(load)
</script>
