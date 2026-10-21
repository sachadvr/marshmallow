import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope } from 'vue'
import { useNotification } from '@/features/notifications/useNotification'

describe('useNotification', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('hides the notification after 3 seconds by default', () => {
    const { current, show } = useNotification()

    show('Fichier uploadé avec succès', 'success')
    expect(current.value).toMatchObject({ message: 'Fichier uploadé avec succès', kind: 'success' })

    vi.advanceTimersByTime(2999)
    expect(current.value).not.toBeNull()
    vi.advanceTimersByTime(1)
    expect(current.value).toBeNull()
  })

  it('replaces the current notification and restarts the timer', () => {
    const { current, show } = useNotification(1000)

    show('first', 'success')
    const firstId = current.value!.id
    vi.advanceTimersByTime(800)
    show('second', 'error')

    expect(current.value).toMatchObject({ message: 'second', kind: 'error' })
    expect(current.value!.id).not.toBe(firstId)
    vi.advanceTimersByTime(800)
    expect(current.value?.message).toBe('second')
    vi.advanceTimersByTime(200)
    expect(current.value).toBeNull()
  })

  it('clears the pending timer when its scope is disposed', () => {
    const scope = effectScope()
    const { current, show } = scope.run(() => useNotification(1000))!

    show('message', 'success')
    scope.stop()
    vi.advanceTimersByTime(1000)

    expect(current.value?.message).toBe('message')
  })
})
