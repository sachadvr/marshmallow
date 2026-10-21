import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { useDropZone } from '@/features/files/useDropZone'
import type { DropZoneHandlers } from '@/features/files/useDropZone'

function mountDropZone() {
  const handlers: DropZoneHandlers = { onFile: vi.fn(), onText: vi.fn() }
  let isDragging!: { value: boolean }
  const wrapper = mount(
    defineComponent({
      setup() {
        const zone = ref<HTMLElement | null>(null)
        isDragging = useDropZone(zone, handlers).isDragging

        return () => h('div', { ref: zone }, [h('span', { class: 'child' })])
      },
    }),
    { attachTo: document.body },
  )

  return { wrapper, handlers, isDragging, zone: wrapper.element as HTMLElement }
}

function dragEvent(
  type: string,
  dataTransfer?: { files: File[]; getData(format: string): string },
) {
  const event = new Event(type, { bubbles: true, cancelable: true })
  Object.defineProperty(event, 'dataTransfer', { value: dataTransfer ?? null })

  return event
}

describe('useDropZone', () => {
  it('stays dragging while moving over nested children', () => {
    const { zone, isDragging, wrapper } = mountDropZone()
    const child = zone.querySelector('.child')!

    zone.dispatchEvent(dragEvent('dragenter'))
    child.dispatchEvent(dragEvent('dragenter'))
    child.dispatchEvent(dragEvent('dragleave'))
    expect(isDragging.value).toBe(true)

    zone.dispatchEvent(dragEvent('dragleave'))
    expect(isDragging.value).toBe(false)
    wrapper.unmount()
  })

  it('hands over only the first dropped file', () => {
    const { zone, handlers, isDragging, wrapper } = mountDropZone()
    const first = new File(['1'], 'first.txt')
    const second = new File(['2'], 'second.txt')

    zone.dispatchEvent(dragEvent('dragenter'))
    const drop = dragEvent('drop', { files: [first, second], getData: () => 'ignored' })
    zone.dispatchEvent(drop)

    expect(drop.defaultPrevented).toBe(true)
    expect(handlers.onFile).toHaveBeenCalledExactlyOnceWith(first)
    expect(handlers.onText).not.toHaveBeenCalled()
    expect(isDragging.value).toBe(false)
    wrapper.unmount()
  })

  it('hands over dropped text when there is no file', () => {
    const { zone, handlers, wrapper } = mountDropZone()
    const getData = vi.fn().mockReturnValue('dropped text')

    zone.dispatchEvent(dragEvent('drop', { files: [], getData }))

    expect(getData).toHaveBeenCalledWith('text')
    expect(handlers.onText).toHaveBeenCalledExactlyOnceWith('dropped text')
    expect(handlers.onFile).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('prevents default on dragover so the browser allows dropping', () => {
    const { zone, wrapper } = mountDropZone()
    const dragover = dragEvent('dragover')

    zone.dispatchEvent(dragover)

    expect(dragover.defaultPrevented).toBe(true)
    wrapper.unmount()
  })

  it('detaches listeners on unmount', () => {
    const { zone, handlers, isDragging, wrapper } = mountDropZone()

    wrapper.unmount()
    zone.dispatchEvent(dragEvent('dragenter'))
    zone.dispatchEvent(dragEvent('drop', { files: [new File(['x'], 'x')], getData: () => '' }))

    expect(isDragging.value).toBe(false)
    expect(handlers.onFile).not.toHaveBeenCalled()
  })
})
