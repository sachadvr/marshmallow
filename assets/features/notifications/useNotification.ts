import { getCurrentScope, onScopeDispose, ref } from 'vue'
import type { Ref } from 'vue'

export type NotificationKind = 'success' | 'error'

export type Notification = { id: number; message: string; kind: NotificationKind }

export function useNotification(durationMs = 3000): {
  current: Readonly<Ref<Notification | null>>
  show: (message: string, kind: NotificationKind) => void
} {
  const current = ref<Notification | null>(null)
  let nextId = 1
  let hideTimer: ReturnType<typeof setTimeout> | undefined

  function show(message: string, kind: NotificationKind): void {
    clearTimeout(hideTimer)
    current.value = { id: nextId++, message, kind }
    hideTimer = setTimeout(() => {
      current.value = null
    }, durationMs)
  }

  if (getCurrentScope()) onScopeDispose(() => clearTimeout(hideTimer))

  return { current, show }
}
