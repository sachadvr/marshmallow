import { describe, expect, it, vi } from 'vitest'
import type { ApiErrorCode, TextState } from '@/api/contracts'
import { MAX_CONFLICT_RETRIES, initialTextSync, reduceTextSync } from '@/features/text/textSync'
import type { TextSyncEffect, TextSyncEvent, TextSyncState } from '@/features/text/textSync'

function server(content: string, version: number): TextState {
  return { content, version, updatedAt: '2026-10-18T10:00:00+00:00' }
}

const edit = (text: string): TextSyncEvent => ({ type: 'edit', text })
const elapsed: TextSyncEvent = { type: 'debounceElapsed' }
const saved = (content: string, version: number): TextSyncEvent => ({
  type: 'saved',
  state: server(content, version),
})
const remote = (content: string, version: number): TextSyncEvent => ({
  type: 'remote',
  state: server(content, version),
})
const refetched = (content: string, version: number): TextSyncEvent => ({
  type: 'refetched',
  state: server(content, version),
})
const conflict: TextSyncEvent = { type: 'failed', code: 'TEXT_CONFLICT' }
const failed = (code: ApiErrorCode): TextSyncEvent => ({ type: 'failed', code })
const offline = failed('NETWORK')
const reconnected: TextSyncEvent = { type: 'reconnected' }

const scheduleSave: TextSyncEffect = { type: 'scheduleSave' }
const refetch: TextSyncEffect = { type: 'refetch' }
const put = (content: string, baseVersion: number): TextSyncEffect => ({
  type: 'put',
  content,
  baseVersion,
})
const error = (code: ApiErrorCode): TextSyncEffect => ({ type: 'error', code })

const synced: TextSyncState = {
  ...initialTextSync,
  text: 'base',
  base: { content: 'base', version: 5 },
}

function replay(start: TextSyncState, events: TextSyncEvent[]) {
  let state = start
  const effects: TextSyncEffect[] = []

  for (const event of events) {
    const result = reduceTextSync(state, event)
    state = result.state
    effects.push(...result.effects)
  }

  return { state, effects }
}

type Scenario = {
  name: string
  start?: TextSyncState
  events: TextSyncEvent[]
  text: string
  version: number
  phase: TextSyncState['phase']
  effects: TextSyncEffect[]
}

const scenarios: Scenario[] = [
  {
    name: 'applies the initial load',
    start: initialTextSync,
    events: [remote('loaded', 1)],
    text: 'loaded',
    version: 1,
    phase: 'idle',
    effects: [],
  },
  {
    name: 'applies a newer remote state when idle',
    events: [remote('other', 6)],
    text: 'other',
    version: 6,
    phase: 'idle',
    effects: [],
  },
  {
    name: 'ignores the echo of its own save',
    events: [edit('mine'), elapsed, saved('mine', 6), remote('mine', 6)],
    text: 'mine',
    version: 6,
    phase: 'idle',
    effects: [scheduleSave, put('mine', 5)],
  },
  {
    name: 'takes an echo received before the PUT response as the new base',
    events: [edit('mine'), elapsed, remote('mine', 6), saved('mine', 6)],
    text: 'mine',
    version: 6,
    phase: 'idle',
    effects: [scheduleSave, put('mine', 5)],
  },
  {
    name: 'does not merge an early echo into what was typed since, which would duplicate it',
    events: [
      edit('base one'),
      elapsed,
      edit('base one two'),
      remote('base one', 6),
      saved('base one', 6),
    ],
    text: 'base one two',
    version: 6,
    phase: 'inFlight',
    effects: [scheduleSave, put('base one', 5), put('base one two', 6)],
  },
  {
    name: 'ignores an out-of-order older remote state',
    events: [remote('newer', 8), remote('older', 7)],
    text: 'newer',
    version: 8,
    phase: 'idle',
    effects: [],
  },
  {
    name: 'ignores a remote state with the current version',
    events: [remote('same version', 5)],
    text: 'base',
    version: 5,
    phase: 'idle',
    effects: [],
  },
  {
    name: 'merges a remote state received while debouncing, then sends on its base',
    events: [edit('base mine'), remote('theirs base', 6), elapsed],
    text: 'theirs base mine',
    version: 6,
    phase: 'inFlight',
    effects: [scheduleSave, put('theirs base mine', 6)],
  },
  {
    name: 'goes idle when a remote state received while debouncing already holds the edit',
    events: [edit('base mine'), remote('base mine', 6), elapsed],
    text: 'base mine',
    version: 6,
    phase: 'idle',
    effects: [scheduleSave],
  },
  {
    name: 'restarts the debounce on every keystroke',
    events: [edit('a'), edit('ab'), edit('abc'), elapsed],
    text: 'abc',
    version: 5,
    phase: 'inFlight',
    effects: [scheduleSave, scheduleSave, scheduleSave, put('abc', 5)],
  },
  {
    name: 'merges a remote state received during the PUT at once',
    events: [edit('base mine'), elapsed, remote('theirs base', 6)],
    text: 'theirs base mine',
    version: 6,
    phase: 'inFlight',
    effects: [scheduleSave, put('base mine', 5)],
  },
  {
    name: 'remote during the PUT, then the stale PUT is refused: resends the merge on the new base',
    events: [
      edit('base mine'),
      elapsed,
      remote('theirs base', 6),
      conflict,
      refetched('theirs base', 6),
      saved('theirs base mine', 7),
    ],
    text: 'theirs base mine',
    version: 7,
    phase: 'idle',
    effects: [scheduleSave, put('base mine', 5), refetch, put('theirs base mine', 6)],
  },
  {
    name: 'remote during the PUT, then the PUT succeeds: the merged text is sent next',
    events: [edit('base mine'), elapsed, remote('theirs base', 7), saved('base mine', 6)],
    text: 'theirs base mine',
    version: 7,
    phase: 'inFlight',
    effects: [scheduleSave, put('base mine', 5), put('theirs base mine', 7)],
  },
  {
    name: 'sends once, after the response, what was typed during the flight',
    events: [edit('a'), elapsed, edit('ab'), edit('abc'), saved('a', 6)],
    text: 'abc',
    version: 6,
    phase: 'inFlight',
    effects: [scheduleSave, put('a', 5), put('abc', 6)],
  },
  {
    name: 'ignores a stale debounce tick once the flight started',
    events: [edit('a'), elapsed, elapsed],
    text: 'a',
    version: 5,
    phase: 'inFlight',
    effects: [scheduleSave, put('a', 5)],
  },
  {
    name: 'never lowers the base version on a late response',
    start: { ...synced, base: { content: 'base', version: 9 }, phase: 'inFlight' },
    events: [saved('base', 6)],
    text: 'base',
    version: 9,
    phase: 'idle',
    effects: [],
  },
  {
    name: '409 → refetch → merge → resend',
    events: [
      edit('base mine'),
      elapsed,
      conflict,
      refetched('theirs base', 6),
      saved('theirs base mine', 7),
    ],
    text: 'theirs base mine',
    version: 7,
    phase: 'idle',
    effects: [scheduleSave, put('base mine', 5), refetch, put('theirs base mine', 6)],
  },
  {
    name: 'sends what was typed during the refetch',
    events: [edit('a'), elapsed, conflict, edit('ab'), refetched('base', 5)],
    text: 'ab',
    version: 5,
    phase: 'inFlight',
    effects: [scheduleSave, put('a', 5), refetch, put('ab', 5)],
  },
  {
    name: 'goes idle when the refetched text already holds the local edit',
    events: [edit('mine'), elapsed, conflict, refetched('mine', 6)],
    text: 'mine',
    version: 6,
    phase: 'idle',
    effects: [scheduleSave, put('mine', 5), refetch],
  },
  {
    name: 'reports TEXT_CONFLICT after 3 refetches',
    events: [
      edit('mine'),
      elapsed,
      ...Array.from({ length: MAX_CONFLICT_RETRIES }, () => [
        conflict,
        refetched('base', 5),
      ]).flat(),
      conflict,
    ],
    text: 'mine',
    version: 5,
    phase: 'dirty',
    effects: [
      scheduleSave,
      put('mine', 5),
      ...Array.from({ length: MAX_CONFLICT_RETRIES }, () => [refetch, put('mine', 5)]).flat(),
      error('TEXT_CONFLICT'),
    ],
  },
  {
    name: 'reports another error immediately and keeps the local text',
    events: [edit('too big'), elapsed, failed('TEXT_TOO_LARGE')],
    text: 'too big',
    version: 5,
    phase: 'dirty',
    effects: [scheduleSave, put('too big', 5), error('TEXT_TOO_LARGE')],
  },
]

const lostSave = [edit('base offline'), elapsed, offline]
const lostSaveEffects: TextSyncEffect[] = [scheduleSave, put('base offline', 5), error('NETWORK')]

const outageScenarios: Scenario[] = [
  {
    name: 'keeps the unsaved text dirty after a network failure',
    events: lostSave,
    text: 'base offline',
    version: 5,
    phase: 'dirty',
    effects: lostSaveEffects,
  },
  {
    name: 'merges a remote state received while dirty and resends at once',
    events: [...lostSave, remote('theirs base', 6)],
    text: 'theirs base offline',
    version: 6,
    phase: 'inFlight',
    effects: [...lostSaveEffects, put('theirs base offline', 6)],
  },
  {
    name: 'recognises a save the server committed although its response was lost',
    events: [...lostSave, remote('base offline', 6)],
    text: 'base offline',
    version: 6,
    phase: 'idle',
    effects: lostSaveEffects,
  },
  {
    name: 'outage → typing → reconnection: the stale resend is refused, merged, then sent',
    events: [
      ...lostSave,
      edit('base offline more'),
      elapsed,
      offline,
      reconnected,
      conflict,
      refetched('theirs base', 6),
      saved('theirs base offline more', 7),
    ],
    text: 'theirs base offline more',
    version: 7,
    phase: 'idle',
    effects: [
      ...lostSaveEffects,
      scheduleSave,
      put('base offline more', 5),
      put('base offline more', 5),
      refetch,
      put('theirs base offline more', 6),
    ],
  },
  {
    name: 'reports the outage once, whatever the number of failed saves',
    events: [...lostSave, edit('a'), elapsed, offline, reconnected, offline],
    text: 'a',
    version: 5,
    phase: 'dirty',
    effects: [...lostSaveEffects, scheduleSave, put('a', 5), put('a', 5)],
  },
  {
    name: 'reports a new outage after a successful save',
    events: [...lostSave, reconnected, saved('base offline', 6), edit('b'), elapsed, offline],
    text: 'b',
    version: 6,
    phase: 'dirty',
    effects: [
      ...lostSaveEffects,
      put('base offline', 5),
      scheduleSave,
      put('b', 6),
      error('NETWORK'),
    ],
  },
  {
    name: 'reports a different failure during the same outage',
    events: [...lostSave, reconnected, failed('INTERNAL')],
    text: 'base offline',
    version: 5,
    phase: 'dirty',
    effects: [...lostSaveEffects, put('base offline', 5), error('INTERNAL')],
  },
  {
    name: 'reports a failed refetch like a failed save',
    events: [edit('mine'), elapsed, conflict, offline],
    text: 'mine',
    version: 5,
    phase: 'dirty',
    effects: [scheduleSave, put('mine', 5), refetch, error('NETWORK')],
  },
  {
    name: 'resends on reconnection after the conflict retries ran out',
    events: [
      edit('mine'),
      elapsed,
      ...Array.from({ length: MAX_CONFLICT_RETRIES }, () => [
        conflict,
        refetched('base', 5),
      ]).flat(),
      conflict,
      reconnected,
    ],
    text: 'mine',
    version: 5,
    phase: 'inFlight',
    effects: [
      scheduleSave,
      put('mine', 5),
      ...Array.from({ length: MAX_CONFLICT_RETRIES }, () => [refetch, put('mine', 5)]).flat(),
      error('TEXT_CONFLICT'),
      put('mine', 5),
    ],
  },
  {
    name: 'never resends a text refused as too large: no resend loop',
    events: [edit('too big'), elapsed, failed('TEXT_TOO_LARGE'), reconnected, reconnected],
    text: 'too big',
    version: 5,
    phase: 'dirty',
    effects: [scheduleSave, put('too big', 5), error('TEXT_TOO_LARGE')],
  },
  {
    name: 'sends a refused text again once the user edits it',
    events: [
      edit('too big'),
      elapsed,
      failed('TEXT_TOO_LARGE'),
      edit('ok'),
      elapsed,
      saved('ok', 6),
    ],
    text: 'ok',
    version: 6,
    phase: 'idle',
    effects: [scheduleSave, put('too big', 5), error('TEXT_TOO_LARGE'), scheduleSave, put('ok', 5)],
  },
  {
    name: 'ignores a reconnection when everything is saved',
    events: [reconnected],
    text: 'base',
    version: 5,
    phase: 'idle',
    effects: [],
  },
  {
    name: 'ignores a reconnection while a save is in flight',
    events: [edit('a'), elapsed, reconnected],
    text: 'a',
    version: 5,
    phase: 'inFlight',
    effects: [scheduleSave, put('a', 5)],
  },
  {
    name: 'ignores a reconnection while debouncing',
    events: [edit('a'), reconnected],
    text: 'a',
    version: 5,
    phase: 'debouncing',
    effects: [scheduleSave],
  },
]

function expectScenario({ start, events, text, version, phase, effects }: Scenario): void {
  const result = replay(start ?? synced, events)

  expect(result.state).toMatchObject({ text, base: { version }, phase })
  expect(result.effects).toEqual(effects)
}

describe('reduceTextSync', () => {
  it.each(scenarios)('$name', expectScenario)

  describe('network outage', () => {
    it.each(outageScenarios)('$name', expectScenario)
  })

  it('merges through the injected merge function, from the confirmed base', () => {
    const merge = vi.fn(() => 'merged')
    const debouncing = reduceTextSync(synced, edit('mine')).state

    const { state } = reduceTextSync(debouncing, remote('theirs', 6), merge)

    expect(merge).toHaveBeenCalledExactlyOnceWith('base', 'mine', 'theirs')
    expect(state).toMatchObject({ text: 'merged', base: { content: 'theirs', version: 6 } })
  })

  it('allows a fresh retry budget for the next save after a conflict error', () => {
    const conflicts = Array.from({ length: MAX_CONFLICT_RETRIES }, () => [
      conflict,
      refetched('base', 5),
    ]).flat()
    const afterError = replay(synced, [edit('a'), elapsed, ...conflicts, conflict]).state

    const next = replay(afterError, [edit('ab'), elapsed, conflict])

    expect(next.state.conflictRetries).toBe(1)
    expect(next.effects.at(-1)).toEqual(refetch)
  })

  it('does not mutate the previous state', () => {
    const before = structuredClone(synced)

    reduceTextSync(synced, edit('changed'))
    reduceTextSync(synced, remote('theirs', 6))

    expect(synced).toEqual(before)
  })
})
