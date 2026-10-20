<script setup lang="ts">
import { computed } from 'vue'
import type { FileDto } from '../../api/contracts'
import { resolvePreview } from './previews'

const props = defineProps<{ file: FileDto }>()

const preview = computed(() => resolvePreview(props.file))
</script>

<template>
  <div>
    <div class="top-0 left-0 z-10 w-full bg-black pl-2 text-white md:relative">
      <a
        :href="file.url"
        :download="file.name"
        target="_blank"
        rel="noopener"
        class="my-[10px] block py-[10px] text-sky-400 underline"
      >
        <strong>{{ file.name }}</strong>
      </a>
    </div>
    <component :is="preview" v-if="preview" :file="file" />
  </div>
</template>
