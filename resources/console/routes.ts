import type { RouteRecordRaw } from 'vue-router'
import { view } from '@multi-tenant-saas/console/module-loader'

const routes: RouteRecordRaw[] = [
  {
    path: 'cicd',
    name: 'CicdManagement',
    component: view('Cicd', 'CicdManagement'),
    meta: { title: 'CI/CD 跟踪', permission: 'cicd.view' },
  },
]

export default routes
