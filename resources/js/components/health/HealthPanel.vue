<script setup lang="ts">
import { onMounted, onUnmounted, computed } from 'vue'
import { useHealthStore } from '@/stores/health.store'

const store = useHealthStore()

onMounted(() => store.startPolling())
onUnmounted(() => store.stopPolling())

const avgDuration = computed(() => {
    const ms = store.stats?.avg_duration_ms_24h ?? 0
    if (ms < 1000) return `${ms}ms`
    if (ms < 60_000) return `${(ms / 1000).toFixed(1)}s`
    return `${Math.floor(ms / 60_000)}m`
})

const successRate = computed(() =>
    store.stats ? `${store.stats.success_rate_24h.toFixed(1)}%` : '—'
)

const cards = computed(() => [
    {
        label: 'Active Runs',
        value: store.stats?.active_runs ?? '—',
        sub: 'sedang berjalan',
        color: 'text-blue-600',
        bgColor: 'bg-blue-50',
        icon: '▶',
    },
    {
        label: 'Success Rate',
        value: successRate.value,
        sub: '24 jam terakhir',
        color: 'text-green-600',
        bgColor: 'bg-green-50',
        icon: '✓',
    },
    {
        label: 'Gagal',
        value: store.stats?.failed_count_24h ?? '—',
        sub: '24 jam terakhir',
        color: 'text-red-600',
        bgColor: 'bg-red-50',
        icon: '✕',
    },
    {
        label: 'Rata-rata Durasi',
        value: avgDuration.value,
        sub: 'per run',
        color: 'text-slate-700',
        bgColor: 'bg-slate-50',
        icon: '⏱',
    },
])
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Health Overview</h2>
            <span class="text-xs text-slate-400">
                Auto-refresh 30s
                <span v-if="store.isLoading" class="inline-block w-1.5 h-1.5 ml-1 rounded-full bg-blue-400 animate-pulse" />
            </span>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div v-for="card in cards" :key="card.label"
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500">{{ card.label }}</span>
                    <span class="w-6 h-6 rounded-md flex items-center justify-center text-xs"
                        :class="[card.bgColor, card.color]">
                        {{ card.icon }}
                    </span>
                </div>
                <p class="text-2xl font-bold tabular-nums" :class="card.color">
                    {{ card.value }}
                </p>
                <p class="text-[11px] text-slate-400">{{ card.sub }}</p>
            </div>
        </div>

        <!-- Success/Failure mini bar chart -->
        <div v-if="store.stats && store.stats.total_runs_24h > 0"
            class="rounded-xl border border-slate-200 bg-white px-4 py-3">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs text-slate-500">Distribusi Run (24h)</span>
                <span class="text-xs text-slate-400">Total: {{ store.stats.total_runs_24h }}</span>
            </div>
            <div class="flex h-3 rounded-full overflow-hidden gap-px">
                <div class="bg-green-400 transition-all duration-500" :style="{ width: `${store.stats.success_rate_24h}%` }"
                    :title="`Success: ${store.stats.success_count_24h}`" />
                <div class="bg-red-400 transition-all duration-500"
                    :style="{ width: `${100 - store.stats.success_rate_24h}%` }"
                    :title="`Failed: ${store.stats.failed_count_24h}`" />
            </div>
            <div class="flex justify-between mt-1">
                <span class="text-[10px] text-green-600">✓ {{ store.stats.success_count_24h }} sukses</span>
                <span class="text-[10px] text-red-500">✕ {{ store.stats.failed_count_24h }} gagal</span>
            </div>
        </div>
    </div>
</template>
