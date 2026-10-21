import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import NotificationBanner from '@/features/notifications/NotificationBanner.vue'

describe('NotificationBanner', () => {
  it('renders nothing visible but keeps the live region when there is no notification', () => {
    const wrapper = mount(NotificationBanner, { props: { notification: null } })

    expect(wrapper.attributes('aria-live')).toBe('polite')
    expect(wrapper.text()).toBe('')
  })

  it('renders a success notification in green', () => {
    const wrapper = mount(NotificationBanner, {
      props: { notification: { id: 1, message: 'Fichier uploadé avec succès', kind: 'success' } },
    })

    const banner = wrapper.get('[data-kind="success"]')
    expect(banner.text()).toBe('Fichier uploadé avec succès')
    expect(banner.classes()).toContain('bg-[#90ee90]')
    expect(banner.classes()).toContain('animate-appearfromthetop')
  })

  it('renders an error notification in red and announces it assertively', () => {
    const wrapper = mount(NotificationBanner, {
      props: { notification: { id: 2, message: "Erreur lors de l'upload", kind: 'error' } },
    })

    const banner = wrapper.get('[data-kind="error"]')
    expect(banner.text()).toBe("Erreur lors de l'upload")
    expect(banner.classes()).toContain('bg-[#ff9a9a]')
    expect(banner.classes()).not.toContain('bg-[#90ee90]')
    expect(wrapper.attributes('aria-live')).toBe('assertive')
  })

  it('re-creates the banner when a new notification arrives so the animation replays', async () => {
    const wrapper = mount(NotificationBanner, {
      props: { notification: { id: 1, message: 'first', kind: 'success' } },
    })
    const first = wrapper.get('[data-kind]').element

    await wrapper.setProps({ notification: { id: 2, message: 'first', kind: 'success' } })

    expect(wrapper.get('[data-kind]').element).not.toBe(first)
  })
})
