import { describe, expect, it } from 'vitest'
import App from '@/App.vue'
import { registerVueComponents } from '@/vueComponents'

describe('registerVueComponents', () => {
  it('lets the ux-vue controller resolve the root component by the name used in Twig', () => {
    registerVueComponents()

    expect(window.resolveVueComponent('App')).toBe(App)
  })

  it('rejects a component Twig does not mount', () => {
    registerVueComponents()

    expect(() => window.resolveVueComponent('Missing')).toThrow(
      'Vue controller "Missing" does not exist',
    )
  })
})
