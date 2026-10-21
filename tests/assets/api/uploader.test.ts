import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { xhrUploader } from '@/api/uploader'

class FakeXhr {
  static last: FakeXhr
  status = 0
  responseText = ''
  readonly upload = new EventTarget()
  private readonly events = new EventTarget()
  open = vi.fn()
  send = vi.fn()
  setRequestHeader = vi.fn()

  constructor() {
    FakeXhr.last = this
  }

  addEventListener(type: string, listener: EventListener): void {
    this.events.addEventListener(type, listener)
  }

  respond(status: number, body: string): void {
    this.status = status
    this.responseText = body
    this.events.dispatchEvent(new Event('load'))
  }

  fail(type: 'error' | 'abort'): void {
    this.events.dispatchEvent(new Event(type))
  }
}

function progressEvent(loaded: number, total: number): Event {
  return Object.assign(new Event('progress'), { lengthComputable: true, loaded, total })
}

describe('xhrUploader', () => {
  const file = new File(['abc'], 'a.txt')
  const stored = {
    id: '0192b1f0-0000-7000-8000-000000000000',
    name: 'a.txt',
    mimeType: 'text/plain',
    size: 3,
    uploadedAt: '2026-10-18T10:00:00+00:00',
    inlineImage: false,
    url: '/api/files/0192b1f0-0000-7000-8000-000000000000/content',
  }

  beforeEach(() => {
    vi.stubGlobal('XMLHttpRequest', FakeXhr)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('posts the file as multipart field "file" and reports progress', async () => {
    const onProgress = vi.fn()

    const pending = xhrUploader(file, onProgress)
    const xhr = FakeXhr.last
    xhr.upload.dispatchEvent(progressEvent(1, 3))
    xhr.respond(201, JSON.stringify(stored))

    await expect(pending).resolves.toEqual(stored)
    expect(xhr.open).toHaveBeenCalledWith('POST', '/api/files')
    expect(xhr.setRequestHeader).toHaveBeenCalledExactlyOnceWith('Accept', 'application/json')
    expect((xhr.send.mock.calls[0][0] as FormData).get('file')).toBeInstanceOf(File)
    expect(onProgress).toHaveBeenCalledWith({ loaded: 1, total: 3 })
  })

  it('ignores progress events without a known total', () => {
    const onProgress = vi.fn()

    void xhrUploader(file, onProgress).catch(() => {})
    FakeXhr.last.upload.dispatchEvent(new Event('progress'))

    expect(onProgress).not.toHaveBeenCalled()
  })

  it('rejects with the problem type code', async () => {
    const pending = xhrUploader(file, vi.fn())
    FakeXhr.last.respond(400, JSON.stringify({ type: 'urn:marshmallow:error:NO_FILE' }))

    await expect(pending).rejects.toMatchObject({ code: 'NO_FILE', status: 400 })
  })

  it('maps the plain-text 413 from Caddy to FILE_TOO_LARGE', async () => {
    const pending = xhrUploader(file, vi.fn())
    FakeXhr.last.respond(413, 'Request Entity Too Large')

    await expect(pending).rejects.toMatchObject({ code: 'FILE_TOO_LARGE', status: 413 })
  })

  it('rejects with INTERNAL on an unreadable success body', async () => {
    const pending = xhrUploader(file, vi.fn())
    FakeXhr.last.respond(201, 'not json')

    await expect(pending).rejects.toMatchObject({ code: 'INTERNAL', status: 201 })
  })

  it.each(['error', 'abort'] as const)('rejects with NETWORK on %s', async (type) => {
    const pending = xhrUploader(file, vi.fn())
    FakeXhr.last.fail(type)

    await expect(pending).rejects.toMatchObject({ code: 'NETWORK', status: 0 })
  })
})
