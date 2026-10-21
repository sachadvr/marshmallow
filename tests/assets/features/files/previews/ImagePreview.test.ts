import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import type { FileDto } from '@/api/contracts'
import ImagePreview from '@/features/files/previews/ImagePreview.vue'

const file: FileDto = {
  id: '01920000-0000-7000-8000-000000000003',
  name: 'cat.png',
  mimeType: 'image/png',
  size: 2048,
  uploadedAt: '2026-10-18T10:00:00+00:00',
  inlineImage: true,
  url: '/api/files/01920000-0000-7000-8000-000000000003/content',
}

describe('ImagePreview', () => {
  it('renders a lazy image linking to the file', () => {
    const wrapper = mount(ImagePreview, { props: { file } })

    expect(wrapper.get('a').attributes('href')).toBe(file.url)
    const img = wrapper.get('img')
    expect(img.attributes('src')).toBe(file.url)
    expect(img.attributes('alt')).toBe('cat.png')
    expect(img.attributes('loading')).toBe('lazy')
  })
})
