import { afterEach, describe, expect, it, vi } from 'vitest'
import { useClipboard } from '@/features/text/useClipboard'

describe('useClipboard', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('writes and reads through the injected clipboard', async () => {
    const clipboard = {
      writeText: vi.fn().mockResolvedValue(undefined),
      readText: vi.fn().mockResolvedValue('pasted'),
    }
    const { copy, paste } = useClipboard({ clipboard })

    await copy('copied')

    expect(clipboard.writeText).toHaveBeenCalledExactlyOnceWith('copied')
    await expect(paste()).resolves.toBe('pasted')
  })

  it('propagates a permission refusal', async () => {
    const refusal = new DOMException('denied', 'NotAllowedError')
    const clipboard = { writeText: vi.fn(), readText: vi.fn().mockRejectedValue(refusal) }

    await expect(useClipboard({ clipboard }).paste()).rejects.toBe(refusal)
  })

  it('rejects instead of throwing when the Clipboard API is unavailable', async () => {
    vi.stubGlobal('navigator', {})
    const { copy, paste } = useClipboard()

    await expect(copy('x')).rejects.toBeInstanceOf(TypeError)
    await expect(paste()).rejects.toBeInstanceOf(TypeError)
  })
})
