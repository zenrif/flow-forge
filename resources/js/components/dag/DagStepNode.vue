<script setup lang="ts">
import { computed } from 'vue'
import { Handle, Position } from '@vue-flow/core'
import type { DagNodeData } from '@/types/workflow'

const props = defineProps<{ data: DagNodeData }>()

// ── Warna & icon per status ──────────────────────────────────────────────────
const statusConfig = computed(() => ({
    pending: { ring: 'ring-slate-300', bg: 'bg-slate-50', dot: 'bg-slate-400', text: 'text-slate-500', label: 'Pending' },
    running: { ring: 'ring-blue-400', bg: 'bg-blue-50', dot: 'bg-blue-500', text: 'text-blue-600', label: 'Running' },
    success: { ring: 'ring-green-400', bg: 'bg-green-50', dot: 'bg-green-500', text: 'text-green-700', label: 'Success' },
    failed: { ring: 'ring-red-400', bg: 'bg-red-50', dot: 'bg-red-500', text: 'text-red-700', label: 'Failed' },
    skipped: { ring: 'ring-slate-200', bg: 'bg-slate-50', dot: 'bg-slate-300', text: 'text-slate-400', label: 'Skipped' },
    retrying: { ring: 'ring-amber-400', bg: 'bg-amber-50', dot: 'bg-amber-500', text: 'text-amber-700', label: 'Retrying' },
}[props.data.status] ?? { ring: 'ring-slate-200', bg: 'bg-white', dot: 'bg-slate-300', text: 'text-slate-500', label: '' }))

const typeIcon = computed(() => ({
    http: '🌐',
    script: '⚙️',
    delay: '⏱️',
    condition: '⟐',
}[props.data.type] ?? '•'))

const isRunning = computed(() => props.data.status === 'running')
</script>

<template>
    <!-- Target handle — tempat edge masuk (atas) -->
    <Handle type="target" :position="Position.Top" class="!w-2 !h-2 !bg-slate-400" />

    <div class="w-44 rounded-xl ring-2 px-3 py-2.5 shadow-sm transition-all duration-300 cursor-default select-none"
        :class="[statusConfig.bg, statusConfig.ring]">
        <!-- Header baris: icon tipe + label key -->
        <div class="flex items-center gap-1.5 mb-1">
            <span class="text-sm leading-none">{{ typeIcon }}</span>
            <span class="text-xs font-semibold text-slate-700 truncate flex-1">{{ data.label }}</span>
            <!-- Pulse dot untuk running -->
            <span v-if="isRunning" class="w-2 h-2 rounded-full animate-pulse" :class="statusConfig.dot" />
            <span v-else class="w-2 h-2 rounded-full" :class="statusConfig.dot" />
        </div>

        <!-- Status label -->
        <div class="flex items-center justify-between">
            <span class="text-[10px] font-medium" :class="statusConfig.text">
                {{ statusConfig.label }}
            </span>
            <span v-if="data.duration_ms" class="text-[10px] text-slate-400">
                {{ formatDuration(data.duration_ms) }}
            </span>
        </div>

        <!-- Error message jika ada -->
        <p v-if="data.error && data.status === 'failed'" class="mt-1.5 text-[10px] text-red-600 leading-tight line-clamp-2">
            {{ data.error }}
        </p>

        <!-- Retry badge -->
        <span v-if="data.attempt > 1"
            class="mt-1 inline-block text-[9px] font-semibold bg-amber-100 text-amber-700 rounded px-1">
            Retry #{{ data.attempt }}
        </span>
    </div>

    <!-- Source handle — tempat edge keluar (bawah) -->
    <Handle type="source" :position="Position.Bottom" class="!w-2 !h-2 !bg-slate-400" />
</template>

<script lang="ts">
function formatDuration(ms: number): string {
    if (ms < 1000) return `${ms}ms`
    if (ms < 60_000) return `${(ms / 1000).toFixed(1)}s`
    return `${Math.floor(ms / 60_000)}m ${Math.floor((ms % 60_000) / 1000)}s`
}
</script>
