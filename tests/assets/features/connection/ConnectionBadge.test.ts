import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ConnectionBadge from '@/features/connection/ConnectionBadge.vue'

describe('ConnectionBadge', () => {
  it.each([
    ['open', '● Connecté', 'bg-green-500'],
    ['connecting', '● Connexion…', 'bg-amber-500'],
    ['closed', '● Déconnecté', 'bg-gray-500'],
  ] as const)('shows the %s state', (status, label, color) => {
    const wrapper = mount(ConnectionBadge, { props: { status } })

    expect(wrapper.text()).toBe(label)
    expect(wrapper.classes()).toContain(color)
    expect(wrapper.attributes('data-status')).toBe(status)
    expect(wrapper.attributes('role')).toBe('status')
  })

  it('only shows green when the stream is open', async () => {
    const wrapper = mount(ConnectionBadge, { props: { status: 'open' } })

    await wrapper.setProps({ status: 'closed' })

    expect(wrapper.classes()).not.toContain('bg-green-500')
  })
})
