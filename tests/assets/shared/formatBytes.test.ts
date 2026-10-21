import { describe, expect, it } from 'vitest'
import { formatBytes } from '@/shared/formatBytes'

describe('formatBytes', () => {
  it.each([
    [0, '0 B'],
    [-1, '0 B'],
    [512, '512 B'],
    [1024, '1 KB'],
    [1536, '1.5 KB'],
    [512 * 1024, '512 KB'],
    [100 * 1024 * 1024, '100 MB'],
    [1234567, '1.18 MB'],
    [5 * 1024 ** 4, '5120 GB'],
  ])('formats %d bytes as %s', (bytes, expected) => {
    expect(formatBytes(bytes)).toBe(expected)
  })
})
