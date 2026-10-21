import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import UploadProgress from '@/features/files/UploadProgress.vue'

function fill(wrapper: ReturnType<typeof mount>) {
  return wrapper.get('[role="progressbar"] > div')
}

describe('UploadProgress', () => {
  it('renders nothing when no upload is in progress', () => {
    const wrapper = mount(UploadProgress, { props: { progress: null } })

    expect(wrapper.find('[role="progressbar"]').exists()).toBe(false)
  })

  it('shows the bar without the percentage label at 10% or less', () => {
    const wrapper = mount(UploadProgress, { props: { progress: { loaded: 100, total: 1000 } } })

    expect(fill(wrapper).attributes('style')).toContain('width: 10%')
    expect(fill(wrapper).text()).toBe('')
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('10')
  })

  it('shows the percentage label above 10% and the transferred sizes', () => {
    const wrapper = mount(UploadProgress, {
      props: { progress: { loaded: 512 * 1024, total: 2 * 1024 * 1024 } },
    })

    expect(fill(wrapper).text()).toBe('25%')
    expect(wrapper.text()).toContain('Upload en cours... 512 KB / 2 MB')
  })

  it('caps the bar at 100%', () => {
    const wrapper = mount(UploadProgress, { props: { progress: { loaded: 2000, total: 1000 } } })

    expect(fill(wrapper).attributes('style')).toContain('width: 100%')
  })

  it('does not divide by zero on an empty total', () => {
    const wrapper = mount(UploadProgress, { props: { progress: { loaded: 0, total: 0 } } })

    expect(fill(wrapper).attributes('style')).toContain('width: 0%')
  })
})
