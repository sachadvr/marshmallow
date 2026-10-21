import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import type { FileDto } from '@/api/contracts'
import LatestFile from '@/features/files/LatestFile.vue'
import { resolvePreview } from '@/features/files/previews'

vi.mock('@/features/files/previews', () => ({ resolvePreview: vi.fn() }))

const file: FileDto = {
  id: '01920000-0000-7000-8000-000000000002',
  name: 'photo x.png',
  mimeType: 'image/png',
  size: 1024,
  uploadedAt: '2026-10-18T10:00:00+00:00',
  inlineImage: true,
  url: '/api/files/01920000-0000-7000-8000-000000000002/content',
}

const FakePreview = defineComponent({
  props: { file: { type: Object, required: true } },
  setup: (props) => () => h('div', { class: 'fake-preview' }, (props.file as FileDto).url),
})

describe('LatestFile', () => {
  beforeEach(() => {
    vi.mocked(resolvePreview).mockReset()
  })

  it('links to the file url and shows its name', () => {
    vi.mocked(resolvePreview).mockReturnValue(null)
    const wrapper = mount(LatestFile, { props: { file } })

    const link = wrapper.get('a')
    expect(link.attributes('href')).toBe(file.url)
    expect(link.attributes('download')).toBe('photo x.png')
    expect(link.attributes('target')).toBe('_blank')
    expect(link.text()).toBe('photo x.png')
  })

  it('renders no preview when no strategy matches', () => {
    vi.mocked(resolvePreview).mockReturnValue(null)
    const wrapper = mount(LatestFile, { props: { file } })

    expect(wrapper.find('.fake-preview').exists()).toBe(false)
  })

  it('renders the resolved preview component with the file', () => {
    vi.mocked(resolvePreview).mockReturnValue(FakePreview)
    const wrapper = mount(LatestFile, { props: { file } })

    expect(resolvePreview).toHaveBeenCalledWith(file)
    expect(wrapper.get('.fake-preview').text()).toBe(file.url)
  })
})
