<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import RunStatusBadge from './RunStatusBadge.vue'
import RunDuration from './RunDuration.vue'
import { useWorkflowRuns } from '@/composables/useWorkflowRuns'

const props = defineProps<{ workflowId: string }>()
const router = useRouter()

const {
    runs, meta, isLoading, isEmpty,
    statusFilter, fetch, triggerRun, setStatusFilter,
} = useWorkflowRuns(props.workflowId)

onMounted(() => fetch(1))

async function handleTrigger() {
    const res = await triggerRun()
    router.push({ name: 'run-detail', params: { runId: res.run_id } })
}

function formatDate(iso: string | null): string {
    if (!iso) return '—'
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    }).format(new Date(iso))
}
</script>

<template>
    <div class="space-y-4">
        <!-- Toolbar -->
        <div class="flex items-center justify-between gap-3">
            <!-- Filter status -->
            <div class="flex gap-2 flex-wrap">
                <button v-for="s in ['', 'running', 'success', 'failed', 'pending']" :key="s"
                    class="text-xs px-3 py-1.5 rounded-full border transition-colors" :class="statusFilter === s
                        ? 'bg-slate-800 text-white border-slate-800'
                        : 'bg-white text-slate-600 border-slate-200 hover:border-slate-400'" @click="setStatusFilter(s)">
                    {{ s === '' ? 'Semua' : s.charAt(0).toUpperCase() + s.slice(1) }}
                </button>
            </div>

            <!-- Trigger manual -->
            <button
                class="flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition-colors"
                @click="handleTrigger">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3l14 9-14 9V3z" />
                </svg>
                Jalankan
            </button>
        </div>

        <!-- Loading skeleton -->
        <div v-if="isLoading" class="space-y-2">
            <div v-for="i in 5" :key="i" class="h-16 rounded-xl bg-slate-100 animate-pulse" />
        </div>

        <!-- Empty state -->
        <div v-else-if="isEmpty" class="py-16 text-center">
            <p class="text-slate-400">Belum ada run. Klik <strong>Jalankan</strong> untuk memulai.</p>
        </div>

        <!-- Run list -->
        <div v-else class="space-y-2">
            <div v-for="run in runs" :key="run.id"
                class="flex items-center gap-4 px-4 py-3 rounded-xl border border-slate-200 bg-white hover:border-slate-300 hover:shadow-sm cursor-pointer transition-all"
                @click="router.push({ name: 'run-detail', params: { runId: run.id } })">
                <RunStatusBadge :status="run.status" />

                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-800 truncate">
                        Run {{ run.id.slice(0, 8) }}…
                    </p>
                    <p class="text-xs text-slate-400">
                        {{ formatDate(run.started_at) }} · {{ run.trigger_type }}
                    </p>
                </div>

                <RunDuration :started-at="run.started_at" :completed-at="run.completed_at" :status="run.status" />

                <svg class="w-4 h-4 text-slate-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="flex items-center justify-center gap-2 pt-2">
            <button v-for="page in meta.last_page" :key="page" class="w-8 h-8 rounded-lg text-sm transition-colors" :class="meta.current_page === page
                ? 'bg-slate-800 text-white'
                : 'bg-white text-slate-600 border border-slate-200 hover:border-slate-400'" @click="fetch(page)">
                {{ page }}
            </button>
        </div>
    </div>
</template>
