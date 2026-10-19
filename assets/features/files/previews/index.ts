import type { Component } from 'vue'
import type { FileDto } from '../../../api/contracts'
import ImagePreview from './ImagePreview.vue'

export type PreviewStrategy = {
  id: string
  matches(file: FileDto): boolean
  component: Component
}

const imagePreview: PreviewStrategy = {
  id: 'image',
  matches: (file) => file.inlineImage,
  component: ImagePreview,
}

export const previewStrategies: readonly PreviewStrategy[] = [imagePreview]

export function resolvePreview(file: FileDto): Component | null {
  return previewStrategies.find((strategy) => strategy.matches(file))?.component ?? null
}
