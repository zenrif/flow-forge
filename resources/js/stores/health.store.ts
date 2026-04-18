import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/lib/api'
import type { HealthStats } from '@/types/workflow'

export const useHealthStore = defineStore('health', () => {
  const stats     = ref<HealthStats | null>(null)
  const isLoading = ref(false)
  let   intervalId: ReturnType<typeof setInterval> | null = null

  async function fetch() {
    isLoading.value = true
    try {
      stats.value = await api.get<HealthStats>('/health/stats')
    } finally {
      isLoading.value = false
    }
  }

  // Poll setiap 30 detik
  function startPolling() {
    fetch()
    intervalId = setInterval(fetch, 30_000)
  }

  function stopPolling() {
    if (intervalId) clearInterval(intervalId)
  }

  return { stats, isLoading, fetch, startPolling, stopPolling }
})
