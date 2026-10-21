import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import App from '@/App.vue'
import type { PageProps, FileDto, TextState } from '@/api/contracts'
import { ApiError } from '@/api/errors'
import { getLatestFile, getText, putText } from '@/api/http'
import { xhrUploader } from '@/api/uploader'
import type { RealtimeHandlers } from '@/features/connection/useRealtime'
import DropZone from '@/features/files/DropZone.vue'
import LatestFile from '@/features/files/LatestFile.vue'
import { errorMessage } from '@/features/notifications/messages'
import TextEditor from '@/features/text/TextEditor.vue'

const realtime = vi.hoisted(() => ({ handlers: null as RealtimeHandlers | null }))

vi.mock('@/api/http', () => ({ getText: vi.fn(), getLatestFile: vi.fn(), putText: vi.fn() }))
vi.mock('@/api/uploader', () => ({ xhrUploader: vi.fn() }))
vi.mock('@/features/connection/useRealtime', async () => {
  const { ref } = await import('vue')

  return {
    useRealtime: (handlers: RealtimeHandlers) => {
      realtime.handlers = handlers

      return { status: ref('open') }
    },
  }
})

enableAutoUnmount(afterEach)

function textState(content: string, version: number): TextState {
  return { content, version, updatedAt: '2026-10-18T10:00:00+00:00' }
}

function storedFile(id: string, uploadedAt: string): FileDto {
  return {
    id,
    name: `${id}.txt`,
    mimeType: 'text/plain',
    size: 5,
    uploadedAt,
    inlineImage: false,
    url: `/api/files/${id}/content`,
  }
}

const newerFile = storedFile('0192b1f0-0000-7000-8000-000000000002', '2026-10-18T10:05:00+00:00')
const olderFile = storedFile('0192b1f0-0000-7000-8000-000000000001', '2026-10-18T10:00:00+00:00')

function realtimeHandlers(): RealtimeHandlers {
  if (!realtime.handlers) throw new Error('useRealtime was not called')

  return realtime.handlers
}

function pageProps(overrides: Partial<PageProps> = {}): PageProps {
  return {
    initialText: textState('hello', 1),
    initialLatestFile: newerFile,
    limits: { maxUploadBytes: 1024, maxTextBytes: 1024 },
    ...overrides,
  }
}

async function mountApp(options: { attachTo?: HTMLElement; props?: Partial<PageProps> } = {}) {
  const wrapper = mount(App, { attachTo: options.attachTo, props: pageProps(options.props) })
  await flushPromises()

  return wrapper
}

describe('App', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    realtime.handlers = null
    vi.mocked(getText).mockResolvedValue(textState('hello', 1))
    vi.mocked(getLatestFile).mockResolvedValue(newerFile)
    vi.mocked(putText).mockImplementation((content) => Promise.resolve(textState(content, 2)))
  })

  it('shows the initial text and latest file from its props, without loading them', async () => {
    const wrapper = await mountApp()

    expect(wrapper.text()).toContain('Marshmallow (v3)')
    expect(wrapper.find('textarea').element.value).toBe('hello')
    expect(wrapper.findComponent(LatestFile).props('file')).toEqual(newerFile)
    expect(getText).not.toHaveBeenCalled()
    expect(getLatestFile).not.toHaveBeenCalled()
    expect(putText).not.toHaveBeenCalled()
  })

  it('shows no file when the page has none', async () => {
    const wrapper = await mountApp({ props: { initialLatestFile: null } })

    expect(wrapper.findComponent(LatestFile).exists()).toBe(false)
  })

  it('loads nothing while the hub stays closed', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })

    try {
      await mountApp()
      await vi.advanceTimersByTimeAsync(10_000)

      expect(getText).not.toHaveBeenCalled()
      expect(getLatestFile).not.toHaveBeenCalled()
    } finally {
      vi.useRealTimers()
    }
  })

  it('resyncs on the first open, which may follow changes made after the page render', async () => {
    vi.mocked(getText).mockResolvedValue(textState('from elsewhere', 5))
    const wrapper = await mountApp({ props: { initialLatestFile: olderFile } })

    realtimeHandlers().open()
    await flushPromises()

    expect(getText).toHaveBeenCalledOnce()
    expect(getLatestFile).toHaveBeenCalledOnce()
    expect(wrapper.find('textarea').element.value).toBe('from elsewhere')
    expect(wrapper.findComponent(LatestFile).props('file')).toEqual(newerFile)
  })

  it('resyncs again on every reconnection', async () => {
    const wrapper = await mountApp()
    realtimeHandlers().open()
    await flushPromises()
    vi.mocked(getText).mockResolvedValue(textState('after reconnect', 7))

    realtimeHandlers().open()
    await flushPromises()

    expect(getText).toHaveBeenCalledTimes(2)
    expect(getLatestFile).toHaveBeenCalledTimes(2)
    expect(wrapper.find('textarea').element.value).toBe('after reconnect')
  })

  it('ignores a file event older than the displayed file', async () => {
    const wrapper = await mountApp()

    realtimeHandlers().file(olderFile)
    await flushPromises()

    expect(wrapper.findComponent(LatestFile).props('file')).toEqual(newerFile)
  })

  it('notifies the upload error message', async () => {
    vi.mocked(xhrUploader).mockRejectedValue(new ApiError('FILE_TOO_LARGE', 413))
    const wrapper = await mountApp()

    wrapper.findComponent(DropZone).vm.$emit('file', new File(['hello'], 'a.txt'))
    await flushPromises()

    expect(wrapper.text()).toContain(errorMessage('FILE_TOO_LARGE'))
  })

  it('refuses a file above the upload limit of its props without sending it', async () => {
    const wrapper = await mountApp({ props: { limits: { maxUploadBytes: 4, maxTextBytes: 1024 } } })

    wrapper.findComponent(DropZone).vm.$emit('file', new File(['hello'], 'a.txt'))
    await flushPromises()

    expect(xhrUploader).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain(errorMessage('FILE_TOO_LARGE'))
  })

  it('clears the text and saves it', async () => {
    const wrapper = await mountApp()

    wrapper.findComponent(TextEditor).vm.$emit('clear')
    await flushPromises()

    expect(wrapper.find('textarea').element.value).toBe('')
    await vi.waitFor(() => expect(putText).toHaveBeenCalledWith('', 1))
  })

  it('merges a remote edit with the text typed offline and sends the merge before resyncing', async () => {
    const merged = 'hello theirs typed offline'
    vi.mocked(putText)
      .mockRejectedValueOnce(new ApiError('NETWORK', 0))
      .mockRejectedValueOnce(new ApiError('NETWORK', 0))
    const wrapper = await mountApp()

    wrapper.findComponent(TextEditor).vm.$emit('update:modelValue', 'hello typed offline')
    await vi.waitFor(() => expect(wrapper.text()).toContain(errorMessage('NETWORK')))
    realtimeHandlers().text(textState('hello theirs', 5))
    await flushPromises()
    expect(wrapper.find('textarea').element.value).toBe(merged)

    vi.mocked(getText).mockResolvedValue(textState('hello theirs', 5))
    vi.mocked(putText).mockResolvedValueOnce(textState(merged, 6))
    realtimeHandlers().open()
    await flushPromises()

    expect(vi.mocked(putText).mock.calls).toEqual([
      ['hello typed offline', 1],
      [merged, 5],
      [merged, 5],
    ])
    const resend = vi.mocked(putText).mock.invocationCallOrder.at(-1)!
    expect(resend).toBeLessThan(vi.mocked(getText).mock.invocationCallOrder.at(-1)!)
    expect(wrapper.find('textarea').element.value).toBe(merged)
  })

  it('keeps the caret of the focused editor when a remote edit is merged before it', async () => {
    vi.mocked(putText).mockImplementation(() => new Promise(() => {}))
    const wrapper = await mountApp({ attachTo: document.body })
    const textarea = wrapper.find('textarea').element
    textarea.focus()
    await wrapper.find('textarea').setValue('hello! there')
    textarea.setSelectionRange(6, 6)

    realtimeHandlers().text(textState('oh hello', 2))
    await flushPromises()

    expect(textarea.value).toBe('oh hello! there')
    expect([textarea.selectionStart, textarea.selectionEnd]).toEqual([9, 9])
  })
})
