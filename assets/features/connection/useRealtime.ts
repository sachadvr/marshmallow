import { getCurrentScope, onScopeDispose, ref } from 'vue'
import type { Ref } from 'vue'
import { ROUTES, TOPICS } from '../../api/contracts'
import type { RealtimeEvents } from '../../api/contracts'

export type RealtimeStatus = 'connecting' | 'open' | 'closed'

export type RealtimeHandlers = {
  [K in keyof RealtimeEvents]: (payload: RealtimeEvents[K]) => void
} & { open: () => void }

const EVENT_TYPES: readonly (keyof RealtimeEvents)[] = ['text', 'file']
const MIN_BACKOFF_MS = 1000
const MAX_BACKOFF_MS = 30000

export function hubUrl(lastEventId?: string): string {
  const params = new URLSearchParams([
    ['match', TOPICS.text],
    ['match', TOPICS.files],
  ])

  if (lastEventId) params.set('lastEventID', lastEventId)

  return `${ROUTES.hub}?${params.toString()}`
}

export function useRealtime(
  handlers: RealtimeHandlers,
  deps: { createEventSource?: (url: string) => EventSource } = {},
): { status: Readonly<Ref<RealtimeStatus>> } {
  const createEventSource = deps.createEventSource ?? ((url) => new EventSource(url))
  const status = ref<RealtimeStatus>('connecting')
  let source: EventSource | undefined
  let lastEventId = ''
  let backoffMs = MIN_BACKOFF_MS
  let retryTimer: ReturnType<typeof setTimeout> | undefined
  let disposed = false

  function dispatch(type: keyof RealtimeEvents, event: MessageEvent<string>): void {
    if (event.lastEventId) lastEventId = event.lastEventId
    let payload: unknown

    try {
      payload = JSON.parse(event.data)
    } catch {
      return
    }
    const handle = handlers[type] as (payload: unknown) => void
    handle(payload)
  }

  function connect(): void {
    status.value = 'connecting'
    const current = createEventSource(hubUrl(lastEventId))
    source = current
    current.addEventListener('open', () => {
      backoffMs = MIN_BACKOFF_MS
      status.value = 'open'
      handlers.open()
    })
    current.addEventListener('error', () => {
      if (current.readyState !== current.CLOSED) {
        status.value = 'connecting'

        return
      }
      status.value = 'closed'
      current.close()
      scheduleReconnect()
    })

    for (const type of EVENT_TYPES) {
      current.addEventListener(type, (event) => dispatch(type, event as MessageEvent<string>))
    }
  }

  function scheduleReconnect(): void {
    if (disposed) return
    retryTimer = setTimeout(connect, backoffMs)
    backoffMs = Math.min(backoffMs * 2, MAX_BACKOFF_MS)
  }

  function dispose(): void {
    disposed = true
    clearTimeout(retryTimer)
    source?.close()
    status.value = 'closed'
  }

  connect()

  if (getCurrentScope()) onScopeDispose(dispose)

  return { status }
}
