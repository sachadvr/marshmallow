<script setup lang="ts">
import { computed } from 'vue'
import type { UploadProgress } from '../../api/uploader'
import { formatBytes } from '../../shared/formatBytes'

const props = defineProps<{ progress: UploadProgress | null }>()

const percent = computed(() => {
  if (!props.progress || props.progress.total <= 0) return 0

  return Math.min(100, Math.round((props.progress.loaded / props.progress.total) * 100))
})
</script>

<template>
  <div v-if="progress" class="mt-4">
    <div
      role="progressbar"
      aria-label="Upload"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-valuenow="percent"
      class="h-4 overflow-hidden rounded-full bg-gray-200"
    >
      <div
        class="flex h-full items-center justify-center bg-blue-500 text-xs font-bold text-white transition-all duration-300 ease-out"
        :style="{ width: `${percent}%` }"
      >
        <template v-if="percent > 10">{{ percent }}%</template>
      </div>
    </div>
    <div class="mt-2 text-sm text-gray-600">
      Upload en cours... {{ formatBytes(progress.loaded) }} / {{ formatBytes(progress.total) }}
    </div>
  </div>
</template>
