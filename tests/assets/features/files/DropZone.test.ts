import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import DropZone from '@/features/files/DropZone.vue'

describe('DropZone', () => {
  it('renders the original copy and a label bound to the hidden file input', () => {
    const wrapper = mount(DropZone, { props: { dragging: false } })

    expect(wrapper.text()).toContain('Drag and drop files or text here')
    const label = wrapper.get('label')
    const input = wrapper.get('input[type="file"]')
    expect(label.text()).toBe('Select File')
    expect(label.attributes('for')).toBe(input.attributes('id'))
    expect(input.classes()).toContain('hidden')
  })

  it('highlights while dragging', async () => {
    const wrapper = mount(DropZone, { props: { dragging: false } })
    expect(wrapper.classes()).not.toContain('bg-blue-500!')

    await wrapper.setProps({ dragging: true })

    expect(wrapper.classes()).toContain('bg-blue-500!')
    expect(wrapper.attributes('data-dragging')).toBe('true')
  })

  it('emits the selected file and resets the input', async () => {
    const wrapper = mount(DropZone, { props: { dragging: false } })
    const input = wrapper.get('input[type="file"]').element as HTMLInputElement
    const file = new File(['hello'], 'hello.txt', { type: 'text/plain' })
    Object.defineProperty(input, 'files', { value: [file], configurable: true })

    await wrapper.get('input[type="file"]').trigger('change')

    expect(wrapper.emitted('file')).toEqual([[file]])
    expect(input.value).toBe('')
  })

  it('emits nothing when the selection is cancelled', async () => {
    const wrapper = mount(DropZone, { props: { dragging: false } })
    const input = wrapper.get('input[type="file"]').element as HTMLInputElement
    Object.defineProperty(input, 'files', { value: [], configurable: true })

    await wrapper.get('input[type="file"]').trigger('change')

    expect(wrapper.emitted('file')).toBeUndefined()
  })
})
