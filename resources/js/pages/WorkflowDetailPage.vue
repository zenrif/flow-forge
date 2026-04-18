<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import DagVisualizer from '@/components/dag/DagVisualizer.vue'
import StepRunTable from '@/components/runs/StepRunTable.vue'
import RunHistoryList from '@/components/runs/RunHistoryList.vue'
import RunStatusBadge from '@/components/runs/RunStatusBadge.vue'
import RunDuration from '@/components/runs/RunDuration.vue'
import { useWorkflowRunStore } from '@/stores/workflowRun.store'
import { api } from '@/lib/api'
import type { Workflow, StepRun } from '@/types/workflow'

// ── Setup ────────────────────────────────────────────────────────────────────
const route = useRoute()
const store = useWorkflowRunStore()

const workflowId = route.params.workflowId as string
const activeRunId = ref<string | null>(route.params.runId as string ?? null)

const { run, stepRunsArray, stepStatusMap, isLoading, isTerminal } = storeToRefs(store)

const workflow = ref<Workflow | null>(null)
const activeTab = ref<'visualizer' | 'steps' | 'history'>('visualizer')
const selectedStep = ref<StepRun | null>(null)
const isStepDrawerOpen = ref(false)

// ── Load workflow definition ─────────────────────────────────────────────────
onMounted(async () => {
    workflow.value = await api.get<Workflow>(`/workflows/${workflowId}`)

    if (activeRunId.value) {
        await loadRun(activeRunId.value)
    }
})

async function loadRun(runId: string) {
    // Ambil tenant_id dari user yang login (dari localStorage/store auth)
    const tenantId = JSON.parse(localStorage.getItem('user') ?? '{}').tenant_id ?? ''

    store.unsubscribeRealtime(runId, tenantId)
    await store.loadRun(runId, tenantId)

    // Subscribe hanya jika run masih aktif
    if (!isTerminal.value) {
        store.subscribeRealtime(runId, tenantId)
    }
}

// Jika run selesai → berhenti subscribe
watch(isTerminal, (done) => {
    if (done && activeRunId.value) {
        const tenantId = JSON.parse(localStorage.getItem('user') ?? '{}').tenant_id ?? ''
        store.unsubscribeRealtime(activeRunId.value, tenantId)
    }
})

onUnmounted(() => {
    const tenantId = JSON.parse(localStorage.getItem('user') ?? '{}').tenant_id ?? ''
    if (activeRunId.value) store.unsubscribeRealtime(activeRunId.value, tenantId)
    store.$reset()
})

// ── Computed ─────────────────────────────────────────────────────────────────
const dagDefinition = computed(() => workflow.value?.dag?.dag_definition ?? null)

const runProgress = computed(() => {
    if (!stepRunsArray.value.length) return 0
    const done = stepRunsArray.value.filter(s =>
        ['success', 'failed', 'skipped'].includes(s.status)
    ).length
    return Math.round((done / stepRunsArray.value.length) * 100)
})

// ── Handlers ─────────────────────────────────────────────────────────────────
function onNodeClick(stepKey: string) {
    const sr = stepRunsArray.value.find(s => s.step_key === stepKey)
    if (sr) {
        selectedStep.value = sr
        isStepDrawerOpen.value = true
    }
}

function onSelectRun(runId: string) {
    activeRunId.value = runId
    activeTab.value = 'visualizer'
    loadRun(runId)
}

function formatJson(val: unknown): string {
    return JSON.stringify(val, null, 2)
}

function formatDate(iso: string | null): string {
    if (!iso) return '—'
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
    }).format(new Date(iso))
}
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <!-- ── Top bar ──────────────────────────────────────────────────────── -->
        <header class="sticky top-0 z-10 bg-white border-b border-slate-200 px-6 py-4">
            <div class="flex items-center justify-between max-w-screen-xl mx-auto">
                <div class="flex items-center gap-3">
                    <RouterLink to="/" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </RouterLink>
                    <div>
                        <h1 class="text-lg font-semibold text-slate-900">
                            {{ workflow?.name ?? 'Loading…' }}
                        </h1>
                        <p class="text-xs text-slate-400">{{ workflow?.description }}</p>
                    </div>
                </div>

                <!-- Run status jika ada run aktif -->
                <div v-if="run" class="flex items-center gap-3">
                    <RunStatusBadge :status="run.status" />
                    <RunDuration :started-at="run.started_at" :completed-at="run.completed_at" :status="run.status" />
                    <!-- Progress bar -->
                    <div v-if="!isTerminal" class="w-32">
                        <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 transition-all duration-500 rounded-full"
                                :style="{ width: `${runProgress}%` }" />
                        </div>
                        <p class="text-[10px] text-slate-400 mt-0.5 text-right">{{ runProgress }}%</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- ── Main content ─────────────────────────────────────────────────── -->
        <main class="max-w-screen-xl mx-auto px-6 py-6 space-y-5">

            <!-- Tabs -->
            <div class="flex gap-1 bg-slate-100 p-1 rounded-xl w-fit">
                <button v-for="tab in [
                    { key: 'visualizer', label: 'DAG Visualizer' },
                    { key: 'steps', label: `Steps (${stepRunsArray.length})` },
                    { key: 'history', label: 'Run History' },
                ]" :key="tab.key" class="px-4 py-1.5 rounded-lg text-sm font-medium transition-all" :class="activeTab === tab.key
    ? 'bg-white text-slate-900 shadow-sm'
    : 'text-slate-500 hover:text-slate-700'" @click="activeTab = tab.key as typeof activeTab">
                    {{ tab.label }}
                </button>
            </div>

            <!-- Tab: DAG Visualizer -->
            <div v-show="activeTab === 'visualizer'">
                <div v-if="isLoading" class="h-80 rounded-xl bg-slate-100 animate-pulse" />
                <div v-else-if="!dagDefinition"
                    class="h-80 flex items-center justify-center rounded-xl border border-dashed border-slate-300">
                    <p class="text-slate-400">Pilih workflow run dari tab History</p>
                </div>
                <DagVisualizer v-else :definition="dagDefinition" :status-map="stepStatusMap" @node-click="onNodeClick" />
            </div>

            <!-- Tab: Steps table -->
            <div v-show="activeTab === 'steps'">
                <StepRunTable :step-runs="stepRunsArray" @select="selectedStep = $event; isStepDrawerOpen = true" />
            </div>

            <!-- Tab: Run History -->
            <div v-show="activeTab === 'history'">
                <RunHistoryList :workflow-id="workflowId" @select-run="onSelectRun" />
            </div>
        </main>

        <!-- ── Step Detail Drawer ─────────────────────────────────────────── -->
        <Transition name="drawer">
            <div v-if="isStepDrawerOpen && selectedStep"
                class="fixed inset-y-0 right-0 w-96 bg-white border-l border-slate-200 shadow-xl z-20 flex flex-col">
                <!-- Drawer header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200">
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ selectedStep.step_key }}</h3>
                        <p class="text-xs text-slate-400 mt-0.5">{{ selectedStep.step_type }}</p>
                    </div>
                    <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 transition-colors"
                        @click="isStepDrawerOpen = false">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Drawer body -->
                <div class="flex-1 overflow-y-auto p-5 space-y-4">
                    <!-- Meta -->
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-xs text-slate-400 mb-0.5">Status</p>
                            <RunStatusBadge :status="selectedStep.status" size="sm" />
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 mb-0.5">Attempt</p>
                            <p class="font-medium text-slate-700">{{ selectedStep.attempt }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 mb-0.5">Mulai</p>
                            <p class="text-slate-700">{{ formatDate(selectedStep.started_at) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 mb-0.5">Selesai</p>
                            <p class="text-slate-700">{{ formatDate(selectedStep.completed_at) }}</p>
                        </div>
                    </div>

                    <!-- Error message -->
                    <div v-if="selectedStep.error_message" class="rounded-lg bg-red-50 border border-red-200 p-3">
                        <p class="text-xs font-semibold text-red-700 mb-1">Error</p>
                        <p class="text-xs text-red-600 font-mono leading-relaxed">{{ selectedStep.error_message }}</p>
                    </div>

                    <!-- Output JSON -->
                    <div v-if="selectedStep.output">
                        <p class="text-xs font-semibold text-slate-500 mb-2">Output</p>
                        <pre
                            class="text-xs bg-slate-50 rounded-lg border border-slate-200 p-3 overflow-x-auto font-mono leading-relaxed text-slate-700">{{ formatJson(selectedStep.output) }}</pre>
                    </div>

                    <!-- Input JSON -->
                    <div v-if="selectedStep.input">
                        <p class="text-xs font-semibold text-slate-500 mb-2">Input (Context)</p>
                        <pre
                            class="text-xs bg-slate-50 rounded-lg border border-slate-200 p-3 overflow-x-auto font-mono leading-relaxed text-slate-700">{{ formatJson(selectedStep.input) }}</pre>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Backdrop drawer -->
        <Transition name="fade">
            <div v-if="isStepDrawerOpen" class="fixed inset-0 bg-black/20 z-10 lg:hidden"
                @click="isStepDrawerOpen = false" />
        </Transition>
    </div>
</template>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
    transition: transform 0.25s ease;
}

.drawer-enter-from,
.drawer-leave-to {
    transform: translateX(100%);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
