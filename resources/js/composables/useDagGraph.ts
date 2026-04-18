import { computed } from 'vue'
import { MarkerType, type Node, type Edge } from '@vue-flow/core'
import type { DagDefinition, DagNodeData, DagEdgeData, StepStatus } from '@/types/workflow'

const STEP_WIDTH  = 180
const STEP_HEIGHT = 64
const H_GAP       = 60
const V_GAP       = 80

/**
 * Mengkonversi DagDefinition + stepStatusMap menjadi nodes & edges
 * yang dimengerti oleh Vue Flow.
 * Layout otomatis berdasarkan topological wave (level).
 */
export function useDagGraph(
  definition: DagDefinition,
  statusMap: Record<string, StepStatus> = {},
) {
  // Hitung level (wave) tiap node menggunakan BFS
  function computeLevels(): Map<string, number> {
    const levels = new Map<string, number>()
    const inDegree: Record<string, number> = {}
    const adj: Record<string, string[]> = {}

    for (const step of definition.steps) {
      inDegree[step.key] = step.depends_on.length
      adj[step.key] = adj[step.key] ?? []
      for (const dep of step.depends_on) {
        adj[dep] = adj[dep] ?? []
        adj[dep].push(step.key)
      }
    }

    const queue = definition.steps
      .filter(s => s.depends_on.length === 0)
      .map(s => s.key)

    for (const k of queue) levels.set(k, 0)

    while (queue.length) {
      const key = queue.shift()!
      for (const neighbor of (adj[key] ?? [])) {
        const newLevel = (levels.get(key) ?? 0) + 1
        if (!levels.has(neighbor) || levels.get(neighbor)! < newLevel) {
          levels.set(neighbor, newLevel)
        }
        inDegree[neighbor]--
        if (inDegree[neighbor] === 0) queue.push(neighbor)
      }
    }
    return levels
  }

  const nodes = computed<Node<DagNodeData>[]>(() => {
    const levels = computeLevels()

    // Kelompokkan step per level
    const byLevel = new Map<number, string[]>()
    for (const [key, level] of levels) {
      if (!byLevel.has(level)) byLevel.set(level, [])
      byLevel.get(level)!.push(key)
    }

    const result: Node<DagNodeData>[] = []

    for (const [level, keys] of byLevel) {
      const totalWidth = keys.length * STEP_WIDTH + (keys.length - 1) * H_GAP
      keys.forEach((key, i) => {
        const step   = definition.steps.find(s => s.key === key)!
        const status = statusMap[key] ?? 'pending'
        const x      = i * (STEP_WIDTH + H_GAP) - totalWidth / 2 + STEP_WIDTH / 2
        const y      = level * (STEP_HEIGHT + V_GAP)

        result.push({
          id:       key,
          type:     'dagStep',          // custom node type
          position: { x, y },
          data: {
            label:    key,
            type:     step.type,
            status,
            attempt:  0,
            error:    null,
          },
        })
      })
    }

    return result
  })

  const edges = computed<Edge<DagEdgeData>[]>(() =>
    definition.steps.flatMap(step =>
      step.depends_on.map(dep => ({
        id:         `${dep}->${step.key}`,
        source:     dep,
        target:     step.key,
        animated:   statusMap[dep] === 'running',
        type:       'smoothstep',
        markerEnd:  MarkerType.ArrowClosed,
        style:      { stroke: edgeColor(statusMap[dep] ?? 'pending') },
      }))
    )
  )

  return { nodes, edges }
}

function edgeColor(status: StepStatus): string {
  return {
    success:  '#22c55e',
    failed:   '#ef4444',
    running:  '#3b82f6',
    retrying: '#f59e0b',
    skipped:  '#94a3b8',
    pending:  '#cbd5e1',
  }[status] ?? '#cbd5e1'
}
