import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import type { FileDto } from '@/api/contracts'
import DownloadButton from '@/features/files/DownloadButton.vue'

const file: FileDto = {
  id: '01920000-0000-7000-8000-000000000001',
  name: 'a b.txt',
  mimeType: 'text/plain',
  size: 3,
  uploadedAt: '2026-10-18T10:00:00+00:00',
  inlineImage: false,
  url: '/api/files/01920000-0000-7000-8000-000000000001/content',
}

describe('DownloadButton', () => {
  it('downloads the file from its url under its name', () => {
    const wrapper = mount(DownloadButton, { props: { file } })

    expect(wrapper.attributes('href')).toBe(file.url)
    expect(wrapper.attributes('download')).toBe('a b.txt')
    expect(wrapper.attributes('rel')).toBe('noopener')
    expect(wrapper.attributes('aria-label')).toBe('Télécharger a b.txt')
    expect(wrapper.text()).toBe('⬇️')
  })
})
