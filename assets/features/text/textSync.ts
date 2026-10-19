import type { ApiErrorCode, TextState } from '../../api/contracts'
import { merge3 } from './merge3'

export const MAX_CONFLICT_RETRIES = 3

const RETRYABLE: ReadonlySet<ApiErrorCode> = new Set(['NETWORK', 'INTERNAL', 'TEXT_CONFLICT'])

export type TextSyncPhase = 'idle' | 'debouncing' | 'inFlight' | 'dirty'

export type TextBase = Pick<TextState, 'content' | 'version'>

export type Merge = (base: string, local: string, remote: string) => string

export interface TextSyncState {
  text: string
  base: TextBase
  phase: TextSyncPhase
  sent: string | null
  conflictRetries: number
  failure: ApiErrorCode | null
}

export type TextSyncEvent =
  | { type: 'edit'; text: string }
  | { type: 'debounceElapsed' }
  | { type: 'saved'; state: TextState }
  | { type: 'failed'; code: ApiErrorCode }
  | { type: 'remote'; state: TextState }
  | { type: 'refetched'; state: TextState }
  | { type: 'reconnected' }

export type TextSyncEffect =
  | { type: 'scheduleSave' }
  | { type: 'put'; content: string; baseVersion: number }
  | { type: 'refetch' }
  | { type: 'error'; code: ApiErrorCode }

export interface TextSyncResult {
  state: TextSyncState
  effects: TextSyncEffect[]
}

export const initialTextSync: TextSyncState = {
  text: '',
  base: { content: '', version: 0 },
  phase: 'idle',
  sent: null,
  conflictRetries: 0,
  failure: null,
}

const unchanged = (state: TextSyncState): TextSyncResult => ({ state, effects: [] })

function put(state: TextSyncState): TextSyncResult {
  return {
    state: { ...state, phase: 'inFlight', sent: state.text },
    effects: [{ type: 'put', content: state.text, baseVersion: state.base.version }],
  }
}

function settleOrSend(state: TextSyncState): TextSyncResult {
  if (state.text !== state.base.content) return put(state)

  return unchanged({ ...state, phase: 'idle', conflictRetries: 0, failure: null })
}

function rebase(state: TextSyncState, remote: TextState, merge: Merge): TextSyncState {
  if (remote.version <= state.base.version) return state
  const text =
    remote.content === state.sent
      ? state.text
      : merge(state.base.content, state.text, remote.content)

  return { ...state, text, base: { content: remote.content, version: remote.version }, sent: null }
}

function receive(state: TextSyncState, remote: TextState, merge: Merge): TextSyncResult {
  const rebased = rebase(state, remote, merge)

  if (rebased === state || state.phase === 'inFlight') return unchanged(rebased)

  if (state.phase === 'debouncing' && rebased.text !== rebased.base.content) {
    return unchanged(rebased)
  }

  return settleOrSend(rebased)
}

export function reduceTextSync(
  state: TextSyncState,
  event: TextSyncEvent,
  merge: Merge = merge3,
): TextSyncResult {
  switch (event.type) {
    case 'edit':
      if (state.phase === 'inFlight') return unchanged({ ...state, text: event.text })

      return {
        state: { ...state, text: event.text, phase: 'debouncing' },
        effects: [{ type: 'scheduleSave' }],
      }

    case 'debounceElapsed':
      return state.phase === 'debouncing' ? put(state) : unchanged(state)

    case 'saved': {
      const { content, version } = event.state
      const base = version > state.base.version ? { content, version } : state.base

      return settleOrSend({ ...state, base, sent: null, conflictRetries: 0, failure: null })
    }

    case 'failed':
      if (event.code === 'TEXT_CONFLICT' && state.conflictRetries < MAX_CONFLICT_RETRIES) {
        return {
          state: { ...state, conflictRetries: state.conflictRetries + 1 },
          effects: [{ type: 'refetch' }],
        }
      }

      return {
        state: { ...state, phase: 'dirty', conflictRetries: 0, failure: event.code },
        effects: state.failure === event.code ? [] : [{ type: 'error', code: event.code }],
      }

    case 'refetched':
      return settleOrSend(rebase(state, event.state, merge))

    case 'reconnected':
      if (state.phase !== 'dirty' || !state.failure || !RETRYABLE.has(state.failure)) {
        return unchanged(state)
      }

      return put(state)

    case 'remote':
      return receive(state, event.state, merge)
  }
}
