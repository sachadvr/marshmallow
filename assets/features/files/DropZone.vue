<script setup lang="ts">
import { useId } from 'vue'

defineProps<{ dragging: boolean }>()

const emit = defineEmits<{ file: [file: File] }>()

const inputId = useId()

function onChange(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (file) emit('file', file)
  input.value = ''
}
</script>

<template>
  <div
    class="flex flex-col bg-black p-4"
    :class="{ 'border-blue-500! bg-blue-500!': dragging }"
    :data-dragging="dragging"
  >
    <div class="mb-2 text-white">Drag and drop files or text here</div>
    <label :for="inputId" class="mt-1 block w-fit cursor-pointer bg-white px-4 py-1 text-black">
      Select File
    </label>
    <input :id="inputId" type="file" class="absolute hidden" @change="onChange" />
  </div>
</template>
