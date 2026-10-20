<script setup lang="ts">
import type { Notification } from './useNotification'

defineProps<{ notification: Notification | null }>()

const kindClass: Record<Notification['kind'], string> = {
  success: "bg-[#90ee90] before:content-['✔']",
  error: "bg-[#ff9a9a] before:content-['✖']",
}
</script>

<template>
  <div
    role="status"
    aria-atomic="true"
    :aria-live="notification?.kind === 'error' ? 'assertive' : 'polite'"
  >
    <div
      v-if="notification"
      :key="notification.id"
      :data-kind="notification.kind"
      class="animate-appearfromthetop fixed top-0 left-0 z-100 flex h-[60px] w-full items-center gap-5 pl-[30px] text-[23px] before:text-black"
      :class="kindClass[notification.kind]"
    >
      {{ notification.message }}
    </div>
  </div>
</template>
