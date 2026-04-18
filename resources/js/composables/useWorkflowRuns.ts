import { ref, computed } from 'vue'
import { api } from '@/lib/api'
import type { WorkflowRun } from '@/types/workflow'

interface PaginatedResponse<T> {
  data: T[]
  meta: { current_page: number; last_page: number; total: number; per_page: number }
}

/**
 * Composable untuk list + pagination run history per workflow.
 * Terpisah dari store supaya bisa di-instantiate di beberapa komponen.
 */
export function useWorkflowRuns(workflowId: string) {
  const runs        = ref<WorkflowRun[]>([])
  const meta        = ref({ current_page: 1, last_page: 1, total: 0, per_page: 15 })
  const isLoading   = ref(false)
  const error       = ref<string | null>(null)
  const statusFilter = ref<string>('')

  const isEmpty = computed(() => !isLoading.value && runs.value.length === 0)

  async function fetch(page = 1) {
    isLoading.value = true
    error.value     = null
    try {
      const params = new URLSearchParams({ page: String(page), per_page: '15' })
      if (statusFilter.value) params.set('status', statusFilter.value)

      const res = await api.get<PaginatedResponse<WorkflowRun>>(
        `/workflows/${workflowId}/runs?${params}`
      )
      runs.value = res.data
      meta.value = res.meta
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Gagal memuat run history'
    } finally {
      isLoading.value = false
    }
  }

  async function triggerRun(payload: Record<string, unknown> = {}) {
    return api.post<{ run_id: string; status: string }>(
      `/workflows/${workflowId}/trigger`,
      { payload }
    )
  }

  function setStatusFilter(status: string) {
    statusFilter.value = status
    fetch(1)
  }

  return { runs, meta, isLoading, error, isEmpty, statusFilter, fetch, triggerRun, setStatusFilter }
}
