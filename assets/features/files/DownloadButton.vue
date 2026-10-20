<script setup lang="ts">
import type { FileDto } from '../../api/contracts'

defineProps<{ file: FileDto }>()
</script>

<template>
  <a
    :href="file.url"
    :download="file.name"
    target="_blank"
    rel="noopener"
    :aria-label="`Télécharger ${file.name}`"
    class="ml-auto cursor-pointer rounded-lg border-0 bg-black px-4 py-1 text-white no-underline outline-0"
  >
    ⬇️
  </a>
</template>
