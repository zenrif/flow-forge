import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'dashboard',
      component: () => import('@/pages/DashboardPage.vue'),
    },
    {
      path: '/workflows/:workflowId',
      name: 'workflow-detail',
      component: () => import('@/pages/WorkflowDetailPage.vue'),
    },
    {
      path: '/workflows/:workflowId/runs/:runId',
      name: 'run-detail',
      component: () => import('@/pages/WorkflowDetailPage.vue'),
    },
  ],
})

export default router
