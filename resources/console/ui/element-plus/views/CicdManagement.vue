<template>
  <div class="page">
    <div class="page-header">
      <h2>CI/CD 跟踪</h2>
      <el-button @click="load">刷新</el-button>
    </div>

    <el-row :gutter="12">
      <el-col v-for="card in cards" :key="card.key" :span="4">
        <el-card shadow="never">
          <div class="cicd-stat">
            <span>{{ card.label }}</span>
            <strong>{{ card.value }}</strong>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never" style="margin-top: 12px">
      <el-table :data="workspaces" stripe style="width: 100%" empty-text="暂无工作区">
        <el-table-column prop="name" label="工作区" />
        <el-table-column prop="provider_key" label="Provider" width="100" />
        <el-table-column prop="group_path" label="分组" />
        <el-table-column prop="sync_status" label="同步状态" width="110" />
        <el-table-column prop="repositories_count" label="仓库数" width="90" />
        <el-table-column prop="last_synced_at" label="最近同步" />
        <el-table-column label="操作" width="100">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="sync(row)">同步</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getOverview, getWorkspaces, syncWorkspace, type CicdOverview, type CicdWorkspace } from '@modules/Cicd/api/cicd'

const overview = ref<CicdOverview | null>(null)
const workspaces = ref<CicdWorkspace[]>([])
const loading = ref(false)

const cards = computed(() => [
  { key: 'workspaces', label: '工作区', value: overview.value?.workspaces ?? 0 },
  { key: 'repositories', label: '仓库', value: overview.value?.repositories ?? 0 },
  { key: 'pipelines', label: '流水线', value: overview.value?.pipelines ?? 0 },
  { key: 'releases', label: '发布', value: overview.value?.releases ?? 0 },
  { key: 'deployments', label: '部署', value: overview.value?.deployments ?? 0 },
  { key: 'failed', label: '失败构建', value: overview.value?.failed_pipelines ?? 0 },
])

async function load() {
  loading.value = true
  try {
    const [summary, page] = await Promise.all([getOverview(), getWorkspaces({ page: 1, per_page: 50 })])
    overview.value = summary
    workspaces.value = page.data
  } catch (error) {
    ElMessage.error('加载 CI/CD 数据失败')
  } finally {
    loading.value = false
  }
}

async function sync(row: CicdWorkspace) {
  try {
    await syncWorkspace(row.workspace_id)
    ElMessage.success('同步已触发')
    await load()
  } catch (error) {
    ElMessage.error('同步失败')
  }
}

onMounted(load)
</script>

<style scoped>
.cicd-stat {
  display: flex;
  flex-direction: column;
  gap: 6px;
  color: var(--el-text-color-secondary);
}
.cicd-stat strong {
  font-size: 22px;
  color: var(--el-text-color-primary);
}
</style>
