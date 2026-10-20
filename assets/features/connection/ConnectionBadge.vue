<script setup lang="ts">
defineProps<{ status: 'connecting' | 'open' | 'closed' }>()

const appearance = {
  connecting: { label: 'Connexion…', class: 'bg-amber-500' },
  open: { label: 'Connecté', class: 'bg-green-500' },
  closed: { label: 'Déconnecté', class: 'bg-gray-500' },
} as const
</script>

<template>
  <div
    role="status"
    class="fixed top-2.5 right-4 z-50 rounded px-3 py-1 text-xs text-white"
    :class="appearance[status].class"
    :data-status="status"
  >
    ● {{ appearance[status].label }}
  </div>
</template>
