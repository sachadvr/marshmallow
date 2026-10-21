import { describe, expect, it } from 'vitest'
import type { FileDto } from '@/api/contracts'
import ImagePreview from '@/features/files/previews/ImagePreview.vue'
import { previewStrategies, resolvePreview } from '@/features/files/previews/index'

function storedFile(overrides: Partial<FileDto>): FileDto {
  return {
    id: '01920000-0000-7000-8000-000000000004',
    name: 'file.bin',
    mimeType: 'application/octet-stream',
    size: 1,
    uploadedAt: '2026-10-18T10:00:00+00:00',
    inlineImage: false,
    url: '/api/files/01920000-0000-7000-8000-000000000004/content',
    ...overrides,
  }
}

describe('resolvePreview', () => {
  it('previews files the server flags as inline images', () => {
    expect(resolvePreview(storedFile({ name: 'cat.png', inlineImage: true }))).toBe(ImagePreview)
  })

  it.each([
    ['logo.svg', 'image/svg+xml'],
    ['page.html', 'text/html'],
    ['doc.pdf', 'application/pdf'],
  ])('does not preview %s (%s) when the server does not flag it inline', (name, mimeType) => {
    expect(resolvePreview(storedFile({ name, mimeType, inlineImage: false }))).toBeNull()
  })

  it('trusts the server flag rather than the file name', () => {
    expect(resolvePreview(storedFile({ name: 'cat.png', inlineImage: false }))).toBeNull()
  })

  it('registers the image strategy', () => {
    expect(previewStrategies.map((strategy) => strategy.id)).toEqual(['image'])
  })
})
