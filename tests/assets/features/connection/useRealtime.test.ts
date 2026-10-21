import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope } from 'vue'
import type { FileDto, TextState } from '@/api/contracts'
import { hubUrl, useRealtime } from '@/features/connection/useRealtime'
import type { RealtimeHandlers } from '@/features/connection/useRealtime'

class FakeEventSource extends EventTarget {
  readonly CONNECTING = 0
  readonly OPEN = 1
  readonly CLOSED = 2
  readyState = this.CONNECTING
  close = vi.fn(() => {
    this.readyState = this.CLOSED
  })

  readonly url: string

  constructor(url: string) {
    super()
    this.url = url
  }

  open(): void {
    this.readyState = this.OPEN
    this.dispatchEvent(new Event('open'))
  }

  emit(type: string, data: string, lastEventId = ''): void {
    this.dispatchEvent(new MessageEvent(type, { data, lastEventId }))
  }

  fail(readyState: number): void {
    this.readyState = readyState
    this.dispatchEvent(new Event('error'))
  }
}

const textState: TextState = { content: 'hi', version: 2, updatedAt: '2026-10-18T10:00:00+00:00' }
const storedFile: FileDto = {
  id: '0192b1f0-0000-7000-8000-000000000000',
  name: 'a.png',
  mimeType: 'image/png',
  size: 12,
  uploadedAt: '2026-10-18T10:00:00+00:00',
  inlineImage: true,
  url: '/api/files/0192b1f0-0000-7000-8000-000000000000/content',
}

function setup() {
  const sources: FakeEventSource[] = []
  const handlers = { text: vi.fn(), file: vi.fn(), open: vi.fn() } satisfies RealtimeHandlers
  const scope = effectScope()
  const { status } = scope.run(() =>
    useRealtime(handlers, {
      createEventSource: (url) => {
        const source = new FakeEventSource(url)
        sources.push(source)

        return source as unknown as EventSource
      },
    }),
  )!
  const latest = (): FakeEventSource => sources.at(-1)!

  return { sources, latest, handlers, scope, status }
}

describe('hubUrl', () => {
  it('subscribes to both topics with match', () => {
    expect(hubUrl()).toBe(
      '/.well-known/mercure?match=urn%3Amarshmallow%3Atext&match=urn%3Amarshmallow%3Afiles',
    )
  })

  it('adds lastEventID when known', () => {
    expect(new URL(hubUrl('urn:uuid:42'), 'http://x').searchParams.get('lastEventID')).toBe(
      'urn:uuid:42',
    )
  })
})

describe('useRealtime', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('connects once and reports open to the handler', () => {
    const { sources, latest, handlers, status } = setup()
    expect(sources).toHaveLength(1)
    expect(latest().url).toBe(hubUrl())
    expect(status.value).toBe('connecting')

    latest().open()

    expect(status.value).toBe('open')
    expect(handlers.open).toHaveBeenCalledTimes(1)
  })

  it('dispatches typed events to their handler', () => {
    const { latest, handlers } = setup()

    latest().emit('text', JSON.stringify(textState))
    latest().emit('file', JSON.stringify(storedFile))

    expect(handlers.text).toHaveBeenCalledExactlyOnceWith(textState)
    expect(handlers.file).toHaveBeenCalledExactlyOnceWith(storedFile)
  })

  it('skips a malformed frame without breaking the stream', () => {
    const { latest, handlers } = setup()

    latest().emit('text', '{not json')
    latest().emit('text', JSON.stringify(textState))

    expect(handlers.text).toHaveBeenCalledExactlyOnceWith(textState)
  })

  it('leaves native reconnection to the browser while CONNECTING', () => {
    const { sources, latest, status } = setup()
    latest().open()

    latest().fail(0)
    vi.advanceTimersByTime(60_000)

    expect(status.value).toBe('connecting')
    expect(sources).toHaveLength(1)
  })

  it('recreates a CLOSED source after 1 s with the last event id', () => {
    const { sources, latest, status } = setup()
    latest().open()
    latest().emit('text', JSON.stringify(textState), 'urn:uuid:7')

    latest().fail(2)
    expect(status.value).toBe('closed')
    vi.advanceTimersByTime(999)
    expect(sources).toHaveLength(1)
    vi.advanceTimersByTime(1)

    expect(sources).toHaveLength(2)
    expect(sources[0].close).toHaveBeenCalled()
    expect(latest().url).toBe(hubUrl('urn:uuid:7'))
    expect(status.value).toBe('connecting')
  })

  it('doubles the backoff up to 30 s and resets it on open', () => {
    const { sources, latest } = setup()
    const delays: number[] = []

    for (let attempt = 0; attempt < 7; attempt++) {
      const before = sources.length
      latest().fail(2)
      let waited = 0

      while (sources.length === before) {
        vi.advanceTimersByTime(1000)
        waited += 1000
      }
      delays.push(waited)
    }
    expect(delays).toEqual([1000, 2000, 4000, 8000, 16000, 30000, 30000])

    latest().open()
    const before = sources.length
    latest().fail(2)
    vi.advanceTimersByTime(1000)
    expect(sources).toHaveLength(before + 1)
  })

  it('closes the source and stops reconnecting on dispose', () => {
    const { sources, latest, scope, status } = setup()
    const source = latest()

    source.fail(2)
    scope.stop()
    vi.advanceTimersByTime(60_000)

    expect(sources).toHaveLength(1)
    expect(source.close).toHaveBeenCalled()
    expect(status.value).toBe('closed')
  })

  it('closes an open source on dispose', () => {
    const { latest, scope } = setup()
    latest().open()

    scope.stop()

    expect(latest().close).toHaveBeenCalled()
  })
})
