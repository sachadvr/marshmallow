import { describe, expect, it } from 'vitest'
import type { ApiErrorCode } from '@/api/contracts'
import { ERROR_MESSAGES, errorMessage } from '@/features/notifications/messages'

const ALL_CODES: ApiErrorCode[] = [
  'NO_FILE',
  'EMPTY_FILE',
  'UPLOAD_INCOMPLETE',
  'FILE_TOO_LARGE',
  'TEXT_TOO_LARGE',
  'TEXT_CONFLICT',
  'INVALID_REQUEST',
  'NOT_FOUND',
  'FORBIDDEN',
  'INTERNAL',
  'NETWORK',
]

describe('messages', () => {
  it('has exactly one non-empty message per error code', () => {
    expect(Object.keys(ERROR_MESSAGES).sort()).toEqual([...ALL_CODES].sort())

    for (const code of ALL_CODES) expect(ERROR_MESSAGES[code].trim()).not.toBe('')
  })

  it.each([
    ['FILE_TOO_LARGE', 'Le fichier dépasse la limite de 100 MB'],
    ['TEXT_TOO_LARGE', 'Le texte dépasse la limite de 512 KB'],
  ] as const)('states the limit for %s', (code, expected) => {
    expect(errorMessage(code)).toBe(expected)
  })

  it('uses the catalogue for the other codes', () => {
    expect(errorMessage('NETWORK')).toBe(ERROR_MESSAGES.NETWORK)
    expect(errorMessage('EMPTY_FILE')).toBe('Le fichier est vide')
  })
})
