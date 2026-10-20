<script setup lang="ts">
import type { FileDto } from '../../../api/contracts'

defineProps<{ file: FileDto }>()
</script>

<template>
  <a :href="file.url" target="_blank" rel="noopener" class="block w-full">
    <img
      :src="file.url"
      :alt="file.name"
      loading="lazy"
      decoding="async"
      class="max-h-[60vh] w-full bg-black object-contain"
    />
  </a>
</template>
