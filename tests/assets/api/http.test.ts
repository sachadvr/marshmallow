import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { Mock } from 'vitest'
import { REQUEST_TIMEOUT_MS, getLatestFile, getText, putText } from '@/api/http'

const textState = { content: 'hello', version: 3, updatedAt: '2026-10-18T10:00:00+00:00' }
const storedFile = {
  id: '0192b1f0-0000-7000-8000-000000000000',
  name: 'a.png',
  mimeType: 'image/png',
  size: 12,
  uploadedAt: '2026-10-18T10:00:00+00:00',
  inlineImage: true,
  url: '/api/files/0192b1f0-0000-7000-8000-000000000000/content',
}

function respond(status: number, body: string): Response {
  return new Response(body, { status })
}

describe('http', () => {
  let fetchMock: Mock<typeof fetch>

  beforeEach(() => {
    fetchMock = vi.fn<typeof fetch>()
    vi.stubGlobal('fetch', fetchMock)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  function lastInit(): RequestInit {
    return fetchMock.mock.calls[0][1]!
  }

  it('gets the text with Accept: application/json', async () => {
    fetchMock.mockResolvedValue(respond(200, JSON.stringify(textState)))

    await expect(getText()).resolves.toEqual(textState)
    expect(fetchMock.mock.calls[0][0]).toBe('/api/text')
    expect(lastInit().headers).toEqual({ Accept: 'application/json' })
  })

  it('returns the only element of /files/latest', async () => {
    fetchMock.mockResolvedValue(respond(200, JSON.stringify([storedFile])))

    await expect(getLatestFile()).resolves.toEqual(storedFile)
    expect(fetchMock.mock.calls[0][0]).toBe('/api/files/latest')
  })

  it('returns null when no file was uploaded yet', async () => {
    fetchMock.mockResolvedValue(respond(200, '[]'))

    await expect(getLatestFile()).resolves.toBeNull()
  })

  it('puts the text and its base version as JSON', async () => {
    fetchMock.mockResolvedValue(respond(200, JSON.stringify(textState)))

    await expect(putText('hello', 2)).resolves.toEqual(textState)
    expect(fetchMock.mock.calls[0][0]).toBe('/api/text')
    expect(lastInit()).toMatchObject({
      method: 'PUT',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ content: 'hello', baseVersion: 2 }),
    })
  })

  it('rejects with the problem type code', async () => {
    const problem = { type: 'urn:marshmallow:error:TEXT_CONFLICT', status: 409 }
    fetchMock.mockResolvedValue(respond(409, JSON.stringify(problem)))

    await expect(putText('x', 1)).rejects.toMatchObject({ code: 'TEXT_CONFLICT', status: 409 })
  })

  it('maps the 422 violation on content of PUT /text to TEXT_TOO_LARGE', async () => {
    const problem = { status: 422, violations: [{ propertyPath: 'content', message: 'too long' }] }
    fetchMock.mockResolvedValue(respond(422, JSON.stringify(problem)))

    await expect(putText('x', 1)).rejects.toMatchObject({ code: 'TEXT_TOO_LARGE', status: 422 })
  })

  it('maps a plain-text 413 on PUT /text to TEXT_TOO_LARGE', async () => {
    fetchMock.mockResolvedValue(respond(413, 'Request Entity Too Large'))

    await expect(putText('x', 1)).rejects.toMatchObject({ code: 'TEXT_TOO_LARGE' })
  })

  it('maps a 409 to TEXT_CONFLICT', async () => {
    fetchMock.mockResolvedValue(respond(409, JSON.stringify({ type: '/errors/409' })))

    await expect(putText('x', 1)).rejects.toMatchObject({ code: 'TEXT_CONFLICT', status: 409 })
  })

  it('rejects with NETWORK when fetch fails', async () => {
    fetchMock.mockRejectedValue(new TypeError('Failed to fetch'))

    await expect(getText()).rejects.toMatchObject({ code: 'NETWORK', status: 0 })
  })

  it('rejects with INTERNAL on an unreadable success body', async () => {
    fetchMock.mockResolvedValue(respond(200, '<html>'))

    await expect(getText()).rejects.toMatchObject({ code: 'INTERNAL', status: 200 })
  })

  function hangUntilAborted(_url: RequestInfo | URL, init?: RequestInit): Promise<Response> {
    return new Promise((_, reject) => {
      init?.signal?.addEventListener('abort', () =>
        reject(new DOMException('timed out', 'TimeoutError')),
      )
    })
  }

  it('sends every request with an abort signal (10 s by default)', async () => {
    const timeout = vi.spyOn(AbortSignal, 'timeout')
    fetchMock.mockResolvedValue(respond(200, JSON.stringify(textState)))

    await putText('hello', 1)

    expect(timeout).toHaveBeenCalledWith(REQUEST_TIMEOUT_MS)
    expect(REQUEST_TIMEOUT_MS).toBe(10_000)
    expect(lastInit().signal).toBeInstanceOf(AbortSignal)
    timeout.mockRestore()
  })

  it.each([
    { name: 'getText', call: () => getText({ timeoutMs: 5 }) },
    { name: 'getLatestFile', call: () => getLatestFile({ timeoutMs: 5 }) },
    { name: 'putText', call: () => putText('x', 1, { timeoutMs: 5 }) },
  ])('rejects a stuck $name with NETWORK once the timeout elapses', async ({ call }) => {
    fetchMock.mockImplementation(hangUntilAborted)

    await expect(call()).rejects.toMatchObject({ code: 'NETWORK', status: 0 })
  })

  it('rejects with NETWORK when the body read is aborted', async () => {
    const response = respond(200, '{}')
    vi.spyOn(response, 'text').mockRejectedValue(new DOMException('timed out', 'TimeoutError'))
    fetchMock.mockResolvedValue(response)

    await expect(getText()).rejects.toMatchObject({ code: 'NETWORK', status: 0 })
  })
})
