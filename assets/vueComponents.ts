import { registerVueControllerComponents } from '@symfony/ux-vue'
import type { Component } from 'vue'

export function registerVueComponents(): void {
  registerVueControllerComponents(
    import.meta.glob<{ default: Component }>('./App.vue', { eager: true }),
  )
}
