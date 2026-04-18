<script setup lang="ts">
import { computed, watch, ref } from 'vue'
import { VueFlow, useVueFlow, Panel } from '@vue-flow/core'
import { Background } from '@vue-flow/background'
import { Controls } from '@vue-flow/controls'
import { MiniMap } from '@vue-flow/minimap'
import DagStepNode from './DagStepNode.vue'
import { useDagGraph } from '@/composables/useDagGraph'
import type { DagDefinition, StepStatus } from '@/types/workflow'

// ── Props ──────────────────────────────────────────────────────────────────
const props = defineProps<{
    definition: DagDefinition
    statusMap?: Record<string, StepStatus>
    readonly?: boolean
}>()

const emit = defineEmits<{
    (e: 'node-click', stepKey: string): void
}>()

// ── Graph data dari composable ─────────────────────────────────────────────
const { nodes, edges } = useDagGraph(props.definition, props.statusMap ?? {})

// ── Vue Flow instance ──────────────────────────────────────────────────────
const { fitView, onNodeClick } = useVueFlow()

// Auto-fit saat nodes berubah (misal: pertama kali load)
watch(nodes, () => {
    setTimeout(() => fitView({ padding: 0.2, duration: 400 }), 50)
}, { once: true })

// Emit ke parent ketika node diklik
onNodeClick(({ node }) => emit('node-click', node.id))

// Custom node types — daftarkan DagStepNode sebagai tipe 'dagStep'
const nodeTypes = { dagStep: DagStepNode as any }

// Hitung tinggi canvas berdasarkan jumlah level
const canvasHeight = computed(() => {
    const maxLevel = nodes.value.reduce((max, n) => Math.max(max, n.position.y), 0)
    return Math.max(320, maxLevel + 200)
})
</script>

<template>
    <div class="relative w-full rounded-xl border border-slate-200 bg-slate-50 overflow-hidden"
        :style="{ height: `${canvasHeight}px` }">
        <VueFlow :nodes="nodes" :edges="edges" :node-types="nodeTypes" :nodes-draggable="!readonly"
            :nodes-connectable="false" :zoom-on-scroll="true" fit-view-on-init class="bg-transparent">
            <Background pattern-color="#e2e8f0" :gap="24" />

            <Controls :show-fit-view="true" :show-interactive="false" class="!bottom-4 !left-4" />

            <MiniMap :node-color="minimapNodeColor" class="!bottom-4 !right-4 !rounded-lg !border !border-slate-200" />

            <!-- Legend overlay -->
            <Panel position="top-right" class="m-3">
                <div
                    class="flex flex-wrap gap-2 rounded-lg bg-white/90 backdrop-blur px-3 py-2 shadow-sm border border-slate-100 text-[11px]">
                    <LegendDot color="bg-slate-400" label="Pending" />
                    <LegendDot color="bg-blue-500" label="Running" />
                    <LegendDot color="bg-green-500" label="Success" />
                    <LegendDot color="bg-red-500" label="Failed" />
                    <LegendDot color="bg-amber-500" label="Retrying" />
                    <LegendDot color="bg-slate-300" label="Skipped" />
                </div>
            </Panel>
        </VueFlow>
    </div>
</template>

<script lang="ts">
import type { Node } from '@vue-flow/core'
import type { DagNodeData } from '@/types/workflow'

// Definisikan LegendDot sebagai komponen inline
const LegendDot = {
    props: ['color', 'label'],
    template: `
    <span class="flex items-center gap-1">
      <span class="w-2 h-2 rounded-full" :class="color" />
      <span class="text-slate-600">{{ label }}</span>
    </span>
  `,
}

function minimapNodeColor(node: Node<DagNodeData>): string {
    return {
        success: '#22c55e',
        failed: '#ef4444',
        running: '#3b82f6',
        retrying: '#f59e0b',
        skipped: '#94a3b8',
        pending: '#cbd5e1',
    }[node.data?.status ?? 'pending'] ?? '#cbd5e1'
}
</script>

<!-- Import Vue Flow styles — WAJIB ada -->
<style>
@import '@vue-flow/core/dist/style.css';
@import '@vue-flow/core/dist/theme-default.css';
@import '@vue-flow/controls/dist/style.css';
@import '@vue-flow/minimap/dist/style.css';
</style>
