// ─── Core domain types ───────────────────────────────────────────────────────

export type StepType = 'http' | 'script' | 'delay' | 'condition'
export type StepStatus = 'pending' | 'running' | 'success' | 'failed' | 'skipped' | 'retrying'
export type RunStatus = 'pending' | 'running' | 'success' | 'failed' | 'cancelled' | 'timeout'
export type WorkflowStatus = 'draft' | 'active' | 'paused' | 'archived'
export type TriggerType = 'manual' | 'cron' | 'webhook'

export interface DagStep {
  key: string
  type: StepType
  config: Record<string, unknown>
  depends_on: string[]
  max_retries: number
  timeout_seconds: number
}

export interface DagDefinition {
  steps: DagStep[]
}

export interface WorkflowVersion {
  id: string
  workflow_id: string
  version_number: number
  dag_definition: DagDefinition
  created_by: string
  created_at: string
}

export interface Workflow {
  id: string
  tenant_id: string
  name: string
  description: string | null
  status: WorkflowStatus
  current_version: number
  dag?: WorkflowVersion
  created_at: string
  updated_at: string
}

export interface StepRun {
  id: string
  workflow_run_id: string
  step_key: string
  step_type: StepType
  status: StepStatus
  attempt: number
  input: Record<string, unknown> | null
  output: Record<string, unknown> | null
  error_message: string | null
  started_at: string | null
  completed_at: string | null
  duration_ms?: number
}

export interface WorkflowRun {
  id: string
  workflow_id: string
  version_id: string
  triggered_by: string | null
  status: RunStatus
  trigger_type: TriggerType
  input_payload: Record<string, unknown> | null
  started_at: string | null
  completed_at: string | null
  duration_ms?: number
  step_runs?: StepRun[]
}

// ─── DAG visual graph types (untuk Vue Flow) ─────────────────────────────────

export interface DagNodeData {
  label: string
  type: StepType
  status: StepStatus
  attempt: number
  duration_ms?: number
  error?: string | null
}

export interface DagEdgeData {
  animated: boolean
}

// ─── Health panel ─────────────────────────────────────────────────────────────

export interface HealthStats {
  active_runs: number
  success_count_24h: number
  failed_count_24h: number
  success_rate_24h: number        // 0–100
  avg_duration_ms_24h: number
  total_runs_24h: number
}

// ─── Pusher real-time events ──────────────────────────────────────────────────

export interface StepStatusChangedEvent {
  workflowRunId: string
  tenantId: string
  stepKey: string
  status: StepStatus
  output: Record<string, unknown> | null
  errorMessage: string | null
  timestamp: string
}

export interface RunStatusChangedEvent {
  workflowRunId: string
  workflowId: string
  tenantId: string
  status: RunStatus
  completedAt: string | null
}
