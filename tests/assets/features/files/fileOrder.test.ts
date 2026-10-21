import { describe, expect, it } from 'vitest'
import type { FileDto } from '@/api/contracts'
import { isNewerFile } from '@/features/files/fileOrder'

function storedFile(id: string, uploadedAt: string): FileDto {
  return {
    id,
    name: `${id}.txt`,
    mimeType: 'text/plain',
    size: 5,
    uploadedAt,
    inlineImage: false,
    url: `/api/files/${id}/content`,
  }
}

const ID_1 = '0192b1f0-0000-7000-8000-000000000001'
const ID_2 = '0192b1f0-0000-7000-8000-000000000002'
const at10h = '2026-10-18T10:00:00+00:00'
const at10h05 = '2026-10-18T10:05:00+00:00'
const current = storedFile(ID_1, at10h)

describe('isNewerFile', () => {
  it.each([
    { name: 'any file when none is displayed', candidate: current, current: null, newer: true },
    { name: 'a later upload', candidate: storedFile(ID_1, at10h05), current, newer: true },
    {
      name: 'an earlier upload',
      candidate: current,
      current: storedFile(ID_2, at10h05),
      newer: false,
    },
    { name: 'same instant, higher id', candidate: storedFile(ID_2, at10h), current, newer: true },
    {
      name: 'same instant, lower id',
      candidate: current,
      current: storedFile(ID_2, at10h),
      newer: false,
    },
    {
      name: 'the displayed file itself',
      candidate: storedFile(ID_1, at10h),
      current,
      newer: false,
    },
    {
      name: 'the same instant written with another offset',
      candidate: storedFile(ID_2, '2026-10-18T12:00:00+02:00'),
      current,
      newer: true,
    },
    {
      name: 'a later instant despite a lower id',
      candidate: storedFile(ID_1, at10h05),
      current: storedFile(ID_2, at10h),
      newer: true,
    },
  ])('$name → $newer', ({ candidate, current, newer }) => {
    expect(isNewerFile(candidate, current)).toBe(newer)
  })
})
