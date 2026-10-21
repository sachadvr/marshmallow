import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope, nextTick, shallowRef } from 'vue'
import type { EffectScope } from 'vue'
import type { TextState } from '@/api/contracts'
import { ApiError } from '@/api/errors'
import { useText } from '@/features/text/useText'

function server(content: string, version: number): TextState {
  return { content, version, updatedAt: '2026-10-18T10:00:00+00:00' }
}

function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (error: unknown) => void
  const promise = new Promise<T>((onResolve, onReject) => {
    resolve = onResolve
    reject = onReject
  })

  return { promise, resolve, reject }
}

describe('useText', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('debounces edits by 300 ms by default and sends only the last text', async () => {
    const putText = vi.fn((content: string) => Promise.resolve(server(content, 2)))
    const { text, saving, setText } = useText({ putText, onError: vi.fn() })

    setText('a')
    vi.advanceTimersByTime(200)
    setText('ab')
    vi.advanceTimersByTime(299)
    expect(putText).not.toHaveBeenCalled()
    expect(text.value).toBe('ab')
    expect(saving.value).toBe(true)

    vi.advanceTimersByTime(1)
    expect(putText).toHaveBeenCalledExactlyOnceWith('ab', 0)

    await vi.runAllTimersAsync()
    expect(saving.value).toBe(false)
  })

  it('honours a custom debounce delay', () => {
    const putText = vi.fn((content: string) => Promise.resolve(server(content, 2)))
    const { setText } = useText({ putText, debounceMs: 50, onError: vi.fn() })

    setText('x')
    vi.advanceTimersByTime(50)

    expect(putText).toHaveBeenCalledExactlyOnceWith('x', 0)
  })

  it('applies remote states when idle and ignores its own echo', async () => {
    const putText = vi.fn((content: string) => Promise.resolve(server(content, 2)))
    const { text, setText, applyRemote } = useText({ putText, onError: vi.fn() })

    applyRemote(server('loaded', 1))
    expect(text.value).toBe('loaded')

    setText('mine')
    await vi.runAllTimersAsync()
    applyRemote(server('mine', 2))
    applyRemote(server('stale', 1))

    expect(text.value).toBe('mine')
  })

  it('merges a remote state received during the PUT at once, then resends on the new base', async () => {
    const pending = deferred<TextState>()
    const putText = vi
      .fn<(content: string, baseVersion: number) => Promise<TextState>>()
      .mockReturnValueOnce(pending.promise)
      .mockResolvedValueOnce(server('theirs base mine', 4))
    const getText = vi.fn(() => Promise.resolve(server('theirs base', 3)))
    const { text, setText, applyRemote, saving } = useText({
      putText,
      getText,
      onError: vi.fn(),
    })
    applyRemote(server('base', 1))

    setText('base mine')
    vi.advanceTimersByTime(300)
    applyRemote(server('theirs base', 3))
    expect(text.value).toBe('theirs base mine')

    pending.reject(new ApiError('TEXT_CONFLICT', 409))
    await vi.runAllTimersAsync()

    expect(putText.mock.calls).toEqual([
      ['base mine', 1],
      ['theirs base mine', 3],
    ])
    expect(getText).toHaveBeenCalledOnce()
    expect(text.value).toBe('theirs base mine')
    expect(saving.value).toBe(false)
  })

  it('sends a single follow-up for what was typed during the flight', async () => {
    const first = deferred<TextState>()
    const putText = vi
      .fn<(content: string) => Promise<TextState>>()
      .mockReturnValueOnce(first.promise)
      .mockResolvedValueOnce(server('abc', 3))
    const { setText, saving } = useText({ putText, onError: vi.fn() })

    setText('a')
    vi.advanceTimersByTime(300)
    setText('ab')
    setText('abc')
    vi.advanceTimersByTime(300)
    expect(putText).toHaveBeenCalledTimes(1)

    first.resolve(server('a', 2))
    await vi.runAllTimersAsync()

    expect(putText.mock.calls).toEqual([
      ['a', 0],
      ['abc', 2],
    ])
    expect(saving.value).toBe(false)
  })

  it('refetches and resends silently on 409, then reports TEXT_CONFLICT after 3 retries', async () => {
    const putText = vi.fn().mockRejectedValue(new ApiError('TEXT_CONFLICT', 409))
    const getText = vi.fn(() => Promise.resolve(server('', 0)))
    const onError = vi.fn()
    const { setText, text } = useText({ putText, getText, onError })

    setText('mine')
    await vi.runAllTimersAsync()

    expect(putText).toHaveBeenCalledTimes(4)
    expect(getText).toHaveBeenCalledTimes(3)
    expect(onError).toHaveBeenCalledExactlyOnceWith('TEXT_CONFLICT')
    expect(text.value).toBe('mine')
  })

  it('reports other errors at once, mapping unknown failures to INTERNAL', async () => {
    const putText = vi.fn().mockRejectedValue(new Error('boom'))
    const onError = vi.fn()
    const { setText } = useText({ putText, onError })

    setText('x')
    await vi.runAllTimersAsync()

    expect(putText).toHaveBeenCalledTimes(1)
    expect(onError).toHaveBeenCalledExactlyOnceWith('INTERNAL')
  })

  it('cancels the pending save when its scope is disposed', () => {
    const putText = vi.fn()
    const scope = effectScope()
    const { setText } = scope.run(() => useText({ putText, onError: vi.fn() }))!

    setText('x')
    scope.stop()
    vi.advanceTimersByTime(300)

    expect(putText).not.toHaveBeenCalled()
  })

  describe('selection', () => {
    function setup(focused: boolean) {
      const textarea = document.createElement('textarea')
      document.body.append(textarea)

      if (focused) textarea.focus()
      const putText = vi.fn(() => new Promise<TextState>(() => {}))
      const shared = useText({ putText, editor: shallowRef(textarea), onError: vi.fn() })
      shared.applyRemote(server('hello world', 1))

      return { ...shared, textarea }
    }

    function type(textarea: HTMLTextAreaElement, setText: (value: string) => void, value: string) {
      textarea.value = value
      setText(value)
    }

    afterEach(() => document.body.replaceChildren())

    it('keeps the caret after the local edit when a remote edit is merged before it', async () => {
      const { textarea, text, setText, applyRemote } = setup(true)
      type(textarea, setText, 'hello! world')
      textarea.setSelectionRange(6, 6)

      applyRemote(server('oh hello world', 2))
      textarea.value = text.value
      await nextTick()

      expect(text.value).toBe('oh hello! world')
      expect([textarea.selectionStart, textarea.selectionEnd]).toEqual([9, 9])
    })

    it('keeps a selection in place when a remote edit is merged after it', async () => {
      const { textarea, text, setText, applyRemote } = setup(true)
      type(textarea, setText, 'hey hello world')
      textarea.setSelectionRange(0, 3)

      applyRemote(server('hello world, bye', 2))
      textarea.value = text.value
      await nextTick()

      expect(text.value).toBe('hey hello world, bye')
      expect([textarea.selectionStart, textarea.selectionEnd]).toEqual([0, 3])
    })

    it('leaves the selection alone when the editor is not focused', async () => {
      const { textarea, text, setText, applyRemote } = setup(false)
      type(textarea, setText, 'hello world!')
      const setSelectionRange = vi.spyOn(textarea, 'setSelectionRange')

      applyRemote(server('oh hello world', 2))
      await nextTick()

      expect(text.value).toBe('oh hello world!')
      expect(setSelectionRange).not.toHaveBeenCalled()
    })
  })

  describe('network outage', () => {
    const offline = () => Promise.reject(new ApiError('NETWORK', 0))
    const scopes: EffectScope[] = []
    afterEach(() => scopes.splice(0).forEach((scope) => scope.stop()))

    function setup(putText: (content: string) => Promise<TextState>) {
      const onError = vi.fn()
      const scope = effectScope()
      scopes.push(scope)
      const shared = scope.run(() => useText({ putText, onError }))!

      return { ...shared, onError, scope }
    }

    it('keeps the text typed offline, merges remote states and reports the outage once', async () => {
      const putText = vi.fn(offline)
      const { text, setText, applyRemote, onError, saving } = setup(putText)

      setText('offline')
      await vi.runAllTimersAsync()
      setText('offline more')
      await vi.runAllTimersAsync()
      applyRemote(server('theirs', 6))
      await vi.runAllTimersAsync()

      expect(putText.mock.calls).toEqual([
        ['offline', 0],
        ['offline more', 0],
        ['theirsoffline more', 6],
      ])
      expect(onError).toHaveBeenCalledExactlyOnceWith('NETWORK')
      expect(text.value).toBe('theirsoffline more')
      expect(saving.value).toBe(false)
    })

    it('resends on flush and resolves only once the resend settled', async () => {
      const resend = deferred<TextState>()
      const putText = vi
        .fn<(content: string) => Promise<TextState>>()
        .mockImplementationOnce(offline)
        .mockReturnValueOnce(resend.promise)
      const { text, setText, applyRemote, flush } = setup(putText)
      setText('offline')
      await vi.runAllTimersAsync()

      const flushed = vi.fn()
      void flush().then(flushed)
      await vi.runAllTimersAsync()
      expect(putText.mock.calls).toEqual([
        ['offline', 0],
        ['offline', 0],
      ])
      expect(flushed).not.toHaveBeenCalled()

      resend.resolve(server('offline', 7))
      await vi.runAllTimersAsync()
      expect(flushed).toHaveBeenCalledOnce()
      applyRemote(server('offline', 7))
      expect(text.value).toBe('offline')
    })

    it('resends when the browser comes back online', async () => {
      const putText = vi
        .fn<(content: string) => Promise<TextState>>()
        .mockImplementationOnce(offline)
        .mockResolvedValueOnce(server('offline', 7))
      const { setText, scope } = setup(putText)
      setText('offline')
      await vi.runAllTimersAsync()

      window.dispatchEvent(new Event('online'))
      await vi.runAllTimersAsync()

      expect(putText).toHaveBeenCalledTimes(2)
      scope.stop()
      window.dispatchEvent(new Event('online'))
      expect(putText).toHaveBeenCalledTimes(2)
    })

    it('does not resend a text refused as too large (422)', async () => {
      const putText = vi.fn(() => Promise.reject(new ApiError('TEXT_TOO_LARGE', 422)))
      const { setText, flush, onError } = setup(putText)
      setText('too big')
      await vi.runAllTimersAsync()

      await flush()
      window.dispatchEvent(new Event('online'))
      await vi.runAllTimersAsync()

      expect(putText).toHaveBeenCalledOnce()
      expect(onError).toHaveBeenCalledExactlyOnceWith('TEXT_TOO_LARGE')
    })

    it('resolves flush at once when nothing is waiting to be saved', async () => {
      const putText = vi.fn()
      const { flush } = setup(putText)

      await expect(flush()).resolves.toBeUndefined()
      expect(putText).not.toHaveBeenCalled()
    })
  })
})
