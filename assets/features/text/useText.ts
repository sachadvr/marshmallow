import { computed, getCurrentScope, nextTick, onScopeDispose, shallowRef } from 'vue'
import type { Ref } from 'vue'
import type { ApiErrorCode, TextState } from '../../api/contracts'
import { apiErrorCodeOf } from '../../api/errors'
import { getText as httpGetText, putText as httpPutText } from '../../api/http'
import { merge3, merge3WithCursor } from './merge3'
import type { TextSelection } from './merge3'
import { initialTextSync, reduceTextSync } from './textSync'
import type { TextSyncEffect, TextSyncEvent } from './textSync'

export function useText(deps: {
  putText?: typeof httpPutText
  getText?: typeof httpGetText
  debounceMs?: number
  editor?: Readonly<Ref<HTMLTextAreaElement | null>>
  onError: (code: ApiErrorCode) => void
}): {
  text: Readonly<Ref<string>>
  saving: Readonly<Ref<boolean>>
  setText: (value: string) => void
  applyRemote: (state: TextState) => void
  flush: () => Promise<void>
} {
  const { putText = httpPutText, getText = httpGetText, debounceMs = 300, editor, onError } = deps
  const sync = shallowRef(initialTextSync)
  let saveTimer: ReturnType<typeof setTimeout> | undefined
  const settled: (() => void)[] = []
  let mergedSelection: TextSelection | null = null

  function focusedEditor(local: string): HTMLTextAreaElement | null {
    const element = editor?.value

    if (!element || element !== document.activeElement || element.value !== local) return null

    return element
  }

  function merge(base: string, local: string, remote: string): string {
    const element = focusedEditor(local)

    if (!element) return merge3(base, local, remote)
    const { selectionStart: start, selectionEnd: end } = element
    const merged = merge3WithCursor(base, local, remote, { start, end })
    mergedSelection = merged.cursor

    return merged.text
  }

  function restoreSelection(): void {
    const selection = mergedSelection
    const element = editor?.value
    mergedSelection = null

    if (!selection || !element) return
    void nextTick(() => element.setSelectionRange(selection.start, selection.end))
  }

  function dispatch(event: TextSyncEvent): void {
    const { state, effects } = reduceTextSync(sync.value, event, merge)
    sync.value = state
    restoreSelection()
    effects.forEach(run)

    if (state.phase !== 'inFlight') settled.splice(0).forEach((resolve) => resolve())
  }

  function flush(): Promise<void> {
    dispatch({ type: 'reconnected' })

    if (sync.value.phase !== 'inFlight') return Promise.resolve()

    return new Promise((resolve) => settled.push(resolve))
  }

  const fail = (error: unknown) => dispatch({ type: 'failed', code: apiErrorCodeOf(error) })

  function run(effect: TextSyncEffect): void {
    switch (effect.type) {
      case 'scheduleSave':
        clearTimeout(saveTimer)
        saveTimer = setTimeout(() => dispatch({ type: 'debounceElapsed' }), debounceMs)

        return
      case 'put':
        putText(effect.content, effect.baseVersion).then(
          (state) => dispatch({ type: 'saved', state }),
          fail,
        )

        return
      case 'refetch':
        getText().then((state) => dispatch({ type: 'refetched', state }), fail)

        return
      case 'error':
        onError(effect.code)
    }
  }

  const onOnline = () => void flush()

  if (getCurrentScope()) {
    window.addEventListener('online', onOnline)
    onScopeDispose(() => {
      clearTimeout(saveTimer)
      window.removeEventListener('online', onOnline)
    })
  }

  return {
    text: computed(() => sync.value.text),
    saving: computed(() => sync.value.phase === 'debouncing' || sync.value.phase === 'inFlight'),
    setText: (value) => dispatch({ type: 'edit', text: value }),
    applyRemote: (state) => dispatch({ type: 'remote', state }),
    flush,
  }
}
