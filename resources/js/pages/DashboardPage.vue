<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import HealthPanel from '@/components/health/HealthPanel.vue'
import RunStatusBadge from '@/components/runs/RunStatusBadge.vue'
import { api } from '@/lib/api'
import type { Workflow } from '@/types/workflow'

const router = useRouter()
const workflows = ref<Workflow[]>([])
const isLoading = ref(false)
const search = ref('')

onMounted(async () => {
    isLoading.value = true
    try {
        const res = await api.get<{ data: Workflow[] }>('/workflows?per_page=20')
        workflows.value = res.data
    } finally {
        isLoading.value = false
    }
})

const statusColor: Record<string, string> = {
    active: 'bg-green-400',
    paused: 'bg-amber-400',
    draft: 'bg-slate-300',
    archived: 'bg-slate-200',
}
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <header class="bg-white border-b border-slate-200 px-6 py-4">
            <div class="max-w-screen-xl mx-auto flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center">
                        <span class="text-white text-sm font-bold">FF</span>
                    </div>
                    <h1 class="text-lg font-semibold text-slate-900">FlowForge</h1>
                </div>
                <input v-model="search" type="text" placeholder="Cari workflow…"
                    class="w-64 px-3 py-2 text-sm rounded-lg border border-slate-200 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
        </header>

        <main class="max-w-screen-xl mx-auto px-6 py-6 space-y-6">
            <!-- Health panel -->
            <HealthPanel />

            <!-- Workflow list -->
            <div>
                <h2 class="text-sm font-semibold text-slate-700 mb-3">Workflows</h2>

                <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="i in 6" :key="i" class="h-32 rounded-xl bg-slate-100 animate-pulse" />
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="wf in workflows.filter(w => w.name.toLowerCase().includes(search.toLowerCase()))"
                        :key="wf.id"
                        class="bg-white rounded-xl border border-slate-200 p-4 hover:border-blue-300 hover:shadow-md cursor-pointer transition-all"
                        @click="router.push({ name: 'workflow-detail', params: { workflowId: wf.id } })">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-slate-900 truncate">{{ wf.name }}</h3>
                                <p class="text-xs text-slate-400 mt-0.5 truncate">{{ wf.description ?? '—' }}</p>
                            </div>
                            <div class="flex items-center gap-1.5 ml-2 shrink-0">
                                <span class="w-2 h-2 rounded-full" :class="statusColor[wf.status] ?? 'bg-slate-300'" />
                                <span class="text-xs text-slate-500 capitalize">{{ wf.status }}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between text-xs text-slate-400">
                            <span>v{{ wf.current_version }}</span>
                            <span>
                                {{ new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short' })
                                    .format(new Date(wf.updated_at)) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
