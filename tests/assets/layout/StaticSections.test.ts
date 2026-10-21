import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import AboutSection from '@/layout/AboutSection.vue'
import AppFooter from '@/layout/AppFooter.vue'

describe('static sections', () => {
  it('AboutSection keeps the original copy', () => {
    const text = mount(AboutSection).text()

    expect(text).toContain('marshmallow.rf.gd is a simple website where you can paste text')
    expect(text).toContain('The text will be saved automatically and displayed on the page.')
  })

  it('AppFooter credits the author', () => {
    expect(mount(AppFooter).text()).toBe('Created by sachadvr')
  })
})
