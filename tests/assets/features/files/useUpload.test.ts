import { describe, expect, it, vi } from 'vitest'
import type { FileDto } from '@/api/contracts'
import { ApiError } from '@/api/errors'
import { MAX_UPLOAD_BYTES } from '@/api/limits'
import type { UploadProgress } from '@/api/uploader'
import { useUpload } from '@/features/files/useUpload'

const stored: FileDto = {
  id: '0192b1f0-0000-7000-8000-000000000000',
  name: 'a.txt',
  mimeType: 'text/plain',
  size: 10,
  uploadedAt: '2026-10-18T10:00:00+00:00',
  inlineImage: false,
  url: '/api/files/0192b1f0-0000-7000-8000-000000000000/content',
}

function fileOfSize(size: number): File {
  const file = new File(['x'], 'a.txt')
  Object.defineProperty(file, 'size', { value: size })

  return file
}

describe('useUpload', () => {
  it('rejects a file above the limits.ts limit before calling the uploader', async () => {
    const uploader = vi.fn()
    const { upload, uploading } = useUpload({ uploader })

    await expect(upload(fileOfSize(MAX_UPLOAD_BYTES + 1))).rejects.toMatchObject({
      code: 'FILE_TOO_LARGE',
      status: 0,
    })
    expect(uploader).not.toHaveBeenCalled()
    expect(uploading.value).toBe(false)
  })

  it('accepts a file exactly at the limit', async () => {
    const uploader = vi.fn().mockResolvedValue(stored)
    const { upload } = useUpload({ uploader })

    await expect(upload(fileOfSize(MAX_UPLOAD_BYTES))).resolves.toBe(stored)
  })

  it('honours an injected limit', async () => {
    const uploader = vi.fn()
    const { upload } = useUpload({ uploader, maxBytes: 1024 })

    await expect(upload(fileOfSize(2048))).rejects.toMatchObject({ code: 'FILE_TOO_LARGE' })
    expect(uploader).not.toHaveBeenCalled()
  })

  it('rejects an empty file before calling the uploader', async () => {
    const uploader = vi.fn()
    const { upload } = useUpload({ uploader })

    await expect(upload(fileOfSize(0))).rejects.toMatchObject({ code: 'EMPTY_FILE' })
    expect(uploader).not.toHaveBeenCalled()
  })

  it('exposes progress while uploading and resets it afterwards', async () => {
    let reportProgress!: (progress: UploadProgress) => void
    let finish!: (response: FileDto) => void
    const uploader = vi.fn((_file: File, onProgress: (progress: UploadProgress) => void) => {
      reportProgress = onProgress

      return new Promise<FileDto>((resolve) => {
        finish = resolve
      })
    })
    const { upload, progress, uploading } = useUpload({ uploader })

    const pending = upload(fileOfSize(10))
    expect(uploading.value).toBe(true)
    expect(progress.value).toEqual({ loaded: 0, total: 10 })

    reportProgress({ loaded: 4, total: 10 })
    expect(progress.value).toEqual({ loaded: 4, total: 10 })

    finish(stored)
    await expect(pending).resolves.toEqual(stored)
    expect(uploading.value).toBe(false)
    expect(progress.value).toBeNull()
  })

  it('propagates the uploader ApiError', async () => {
    const error = new ApiError('FILE_TOO_LARGE', 413)
    const { upload, uploading, progress } = useUpload({
      uploader: vi.fn().mockRejectedValue(error),
    })

    await expect(upload(fileOfSize(3))).rejects.toBe(error)
    expect(uploading.value).toBe(false)
    expect(progress.value).toBeNull()
  })
})
