import { ref, watch } from 'vue'
import type { Ref } from 'vue'

export type DropZoneHandlers = { onFile: (file: File) => void; onText: (text: string) => void }

export function useDropZone(
  target: Ref<HTMLElement | null>,
  handlers: DropZoneHandlers,
): { isDragging: Readonly<Ref<boolean>> } {
  const isDragging = ref(false)
  let depth = 0

  function stopDragging(): void {
    depth = 0
    isDragging.value = false
  }

  function onDragEnter(event: DragEvent): void {
    event.preventDefault()
    depth++
    isDragging.value = true
  }

  function onDragOver(event: DragEvent): void {
    event.preventDefault()
  }

  function onDragLeave(event: DragEvent): void {
    event.preventDefault()
    depth--

    if (depth <= 0) stopDragging()
  }

  function onDrop(event: DragEvent): void {
    event.preventDefault()
    stopDragging()
    const transfer = event.dataTransfer

    if (!transfer) return
    const file = transfer.files[0]

    if (file) {
      handlers.onFile(file)

      return
    }
    const text = transfer.getData('text')

    if (text) handlers.onText(text)
  }

  watch(
    target,
    (element, _previous, onCleanup) => {
      if (!element) return
      element.addEventListener('dragenter', onDragEnter)
      element.addEventListener('dragover', onDragOver)
      element.addEventListener('dragleave', onDragLeave)
      element.addEventListener('drop', onDrop)
      onCleanup(() => {
        element.removeEventListener('dragenter', onDragEnter)
        element.removeEventListener('dragover', onDragOver)
        element.removeEventListener('dragleave', onDragLeave)
        element.removeEventListener('drop', onDrop)
        stopDragging()
      })
    },
    { immediate: true, flush: 'sync' },
  )

  return { isDragging }
}
