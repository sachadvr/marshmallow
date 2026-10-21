import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import TextEditor from '@/features/text/TextEditor.vue'

describe('TextEditor', () => {
  it('renders the text in a labelled textarea with the original placeholder', () => {
    const wrapper = mount(TextEditor, { props: { modelValue: 'hello' } })

    const textarea = wrapper.get('textarea')
    expect((textarea.element as HTMLTextAreaElement).value).toBe('hello')
    expect(textarea.attributes('placeholder')).toBe(
      'You can paste text here, your text will be saved automatically',
    )
    expect(wrapper.get('label').attributes('for')).toBe(textarea.attributes('id'))
  })

  it('exposes its textarea so the caret can be kept across merges', () => {
    const wrapper = mount(TextEditor, { props: { modelValue: 'hello' } })

    expect(wrapper.vm.textarea).toBe(wrapper.get('textarea').element)
  })

  it('emits update:modelValue on input', async () => {
    const wrapper = mount(TextEditor, { props: { modelValue: '' } })

    await wrapper.get('textarea').setValue('typed')

    expect(wrapper.emitted('update:modelValue')).toEqual([['typed']])
  })

  it.each([
    ['❌', 'clear'],
    ['📋', 'copy'],
    ['📄', 'paste'],
  ])('emits %s -> %s', async (label, event) => {
    const wrapper = mount(TextEditor, { props: { modelValue: '' } })

    const button = wrapper.findAll('button').find((b) => b.text() === label)
    expect(button?.attributes('type')).toBe('button')
    await button?.trigger('click')

    expect(wrapper.emitted(event)).toHaveLength(1)
  })

  it('renders extra actions in the toolbar', () => {
    const wrapper = mount(TextEditor, {
      props: { modelValue: '' },
      slots: { actions: '<a class="extra">x</a>' },
    })

    expect(wrapper.find('.extra').exists()).toBe(true)
  })
})
