<script setup lang="ts">
import type { RunStatus, StepStatus } from '@/types/workflow'

defineProps<{ status: RunStatus | StepStatus; size?: 'sm' | 'md' }>()

const config: Record<string, { class: string; label: string }> = {
    pending: { class: 'bg-slate-100 text-slate-600', label: 'Pending' },
    running: { class: 'bg-blue-100 text-blue-700', label: 'Running' },
    success: { class: 'bg-green-100 text-green-700', label: 'Success' },
    failed: { class: 'bg-red-100 text-red-700', label: 'Failed' },
    cancelled: { class: 'bg-slate-100 text-slate-500', label: 'Cancelled' },
    timeout: { class: 'bg-orange-100 text-orange-700', label: 'Timeout' },
    skipped: { class: 'bg-slate-100 text-slate-400', label: 'Skipped' },
    retrying: { class: 'bg-amber-100 text-amber-700', label: 'Retrying' },
}
</script>

<template>
    <span class="inline-flex items-center gap-1 font-medium rounded-full" :class="[
        config[status]?.class ?? 'bg-slate-100 text-slate-500',
        size === 'sm' ? 'text-[10px] px-2 py-0.5' : 'text-xs px-2.5 py-1'
    ]">
        <span v-if="status === 'running'" class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse" />
        {{ config[status]?.label ?? status }}
    </span>
</template>
