<script setup lang="ts">
import { useId, useTemplateRef } from 'vue'

defineProps<{ modelValue: string }>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  clear: []
  copy: []
  paste: []
}>()

const textareaId = useId()
const textarea = useTemplateRef<HTMLTextAreaElement>('textarea')

defineExpose({ textarea })

const buttonClass = 'cursor-pointer rounded-none border-0 bg-black px-4 py-2 text-white outline-0'
</script>

<template>
  <div class="flex flex-col max-md:flex-1">
    <label :for="textareaId" class="sr-only">Texte partagé</label>
    <textarea
      :id="textareaId"
      ref="textarea"
      :value="modelValue"
      placeholder="You can paste text here, your text will be saved automatically"
      class="m-0 box-border w-full max-w-[700px] min-w-0 border border-black p-4 leading-6 wrap-break-word placeholder:text-base placeholder:text-[#d3d3d3] focus:placeholder:text-transparent max-md:flex-1 md:h-[300px]"
      @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    />
    <div class="my-2 flex gap-1 md:mt-5">
      <button
        type="button"
        title="Effacer"
        aria-label="Effacer"
        :class="buttonClass"
        @click="emit('clear')"
      >
        ❌
      </button>
      <button
        type="button"
        title="Copier"
        aria-label="Copier"
        :class="buttonClass"
        @click="emit('copy')"
      >
        📋
      </button>
      <button
        type="button"
        title="Coller"
        aria-label="Coller"
        :class="buttonClass"
        @click="emit('paste')"
      >
        📄
      </button>
      <slot name="actions" />
    </div>
  </div>
</template>
