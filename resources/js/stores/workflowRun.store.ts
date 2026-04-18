import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '@/lib/api'
import echo from '@/lib/echo'
import type {
  WorkflowRun, StepRun,
  StepStatusChangedEvent, RunStatusChangedEvent,
  StepStatus, RunStatus,
} from '@/types/workflow'

/**
 * Store tunggal untuk satu workflow run yang sedang aktif/dipantau.
 * Mengelola:
 *  - data run + step runs
 *  - Pusher subscription real-time
 *  - optimistic status update
 */
export const useWorkflowRunStore = defineStore('workflowRun', () => {
  // ── State ────────────────────────────────────────────────────────────────
  const run         = ref<WorkflowRun | null>(null)
  const stepRuns    = ref<Map<string, StepRun>>(new Map())
  const isLoading   = ref(false)
  const error       = ref<string | null>(null)
  const tenantId    = ref<string | null>(null)

  // ── Computed ─────────────────────────────────────────────────────────────
  const stepRunsArray = computed(() => [...stepRuns.value.values()])

  const stepStatusMap = computed<Record<string, StepStatus>>(() => {
    const map: Record<string, StepStatus> = {}
    for (const [key, sr] of stepRuns.value) {
      map[sr.step_key] = sr.status
    }
    return map
  })

  const isTerminal = computed(() =>
    run.value
      ? ['success', 'failed', 'cancelled', 'timeout'].includes(run.value.status)
      : false
  )

  // ── Actions ──────────────────────────────────────────────────────────────

  async function loadRun(runId: string, tId: string) {
    isLoading.value = true
    error.value     = null
    tenantId.value  = tId

    try {
      const data = await api.get<WorkflowRun & { step_runs: StepRun[] }>(
        `/workflow-runs/${runId}`
      )
      run.value = data

      // Populate stepRuns map — pakai step_key sebagai key
      stepRuns.value.clear()
      for (const sr of data.step_runs ?? []) {
        // Jika ada beberapa attempt, simpan yang terbaru
        const existing = stepRuns.value.get(sr.step_key)
        if (!existing || sr.attempt > existing.attempt) {
          stepRuns.value.set(sr.step_key, sr)
        }
      }
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Gagal memuat run'
    } finally {
      isLoading.value = false
    }
  }

  function subscribeRealtime(runId: string, tId: string) {
    const channelName = `tenant.${tId}.run.${runId}`

    echo.channel(channelName)
      .listen('.step.status.changed', (event: StepStatusChangedEvent) => {
        applyStepUpdate(event)
      })

    echo.channel(`tenant.${tId}.workflows`)
      .listen('.run.status.changed', (event: RunStatusChangedEvent) => {
        if (event.workflowRunId !== runId) return
        applyRunUpdate(event)
      })
  }

  function unsubscribeRealtime(runId: string, tId: string) {
    echo.leaveChannel(`tenant.${tId}.run.${runId}`)
  }

  function applyStepUpdate(event: StepStatusChangedEvent) {
    const existing = [...stepRuns.value.values()]
      .find(sr => sr.step_key === event.stepKey)

    if (existing) {
      // Mutasi in-place supaya Vue flow reaktif
      const updated: StepRun = {
        ...existing,
        status:        event.status,
        output:        event.output,
        error_message: event.errorMessage,
        completed_at:  ['success', 'failed', 'skipped'].includes(event.status)
          ? event.timestamp
          : existing.completed_at,
        started_at: existing.started_at ?? (event.status === 'running' ? event.timestamp : null),
      }
      stepRuns.value.set(existing.step_key, updated)
    } else {
      // Step baru (misalnya retry attempt)
      const newStepRun: StepRun = {
        id:              crypto.randomUUID(),
        workflow_run_id: event.workflowRunId,
        step_key:        event.stepKey,
        step_type:       'http', // akan dikoreksi saat full reload
        status:          event.status,
        attempt:         1,
        input:           null,
        output:          event.output,
        error_message:   event.errorMessage,
        started_at:      event.timestamp,
        completed_at:    null,
      }
      stepRuns.value.set(event.stepKey, newStepRun)
    }
  }

  function applyRunUpdate(event: RunStatusChangedEvent) {
    if (!run.value) return
    run.value = {
      ...run.value,
      status:       event.status,
      completed_at: event.completedAt,
    }
  }

  function $reset() {
    run.value      = null
    stepRuns.value = new Map()
    isLoading.value = false
    error.value    = null
    tenantId.value = null
  }

  return {
    run, stepRuns, stepRunsArray, stepStatusMap,
    isLoading, error, isTerminal,
    loadRun, subscribeRealtime, unsubscribeRealtime, $reset,
  }
})
