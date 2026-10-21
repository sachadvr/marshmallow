import { describe, expect, it } from 'vitest'
import { merge3, merge3WithCursor } from '@/features/text/merge3'

type Case = { name: string; base: string; local: string; remote: string; merged: string }

const cases: Case[] = [
  {
    name: 'returns the remote text when only the remote side changed',
    base: 'hello',
    local: 'hello',
    remote: 'hello world',
    merged: 'hello world',
  },
  {
    name: 'returns the local text when only the local side changed',
    base: 'hello',
    local: 'hello world',
    remote: 'hello',
    merged: 'hello world',
  },
  {
    name: 'returns the text once when both sides made the same change',
    base: 'hello',
    local: 'hello world',
    remote: 'hello world',
    merged: 'hello world',
  },
  {
    name: 'keeps inserts made at different places',
    base: 'the cat sat',
    local: 'the black cat sat',
    remote: 'the cat sat down',
    merged: 'the black cat sat down',
  },
  {
    name: 'puts the remote insert first when both insert at the same place',
    base: 'a b',
    local: 'a X b',
    remote: 'a Y b',
    merged: 'a Y X b',
  },
  {
    name: 'keeps an insert next to a word deleted by the other side',
    base: 'one two three',
    local: 'one three',
    remote: 'one two three four',
    merged: 'one three four',
  },
  {
    name: 'keeps an insert made inside a passage the other side deleted',
    base: 'one two three',
    local: 'one',
    remote: 'one two and a half three',
    merged: 'oneand a half ',
  },
  {
    name: 'keeps both versions of a word rewritten on both sides, remote first',
    base: 'the cat sat',
    local: 'the dog sat',
    remote: 'the cow sat',
    merged: 'the cowdog sat',
  },
  {
    name: 'keeps both versions of a word corrected on both sides, remote first',
    base: 'say helo',
    local: 'say hello',
    remote: 'say help',
    merged: 'say helphello',
  },
  {
    name: 'merges into an empty base, remote first',
    base: '',
    local: 'local',
    remote: 'remote',
    merged: 'remotelocal',
  },
  {
    name: 'merges a local clear with a remote insert',
    base: 'old text',
    local: '',
    remote: 'old text new',
    merged: ' new',
  },
  {
    name: 'counts emoji as UTF-16 pairs without splitting them',
    base: 'a 😀 b',
    local: 'a 😀 b 🎉',
    remote: '👋 a 😀 b',
    merged: '👋 a 😀 b 🎉',
  },
  {
    name: 'merges accented and CJK text',
    base: 'café 日本',
    local: 'café crème 日本',
    remote: 'café 日本語 ok',
    merged: 'café crème 日本語 ok',
  },
]

describe('merge3', () => {
  it.each(cases)('$name', ({ base, local, remote, merged }) => {
    expect(merge3(base, local, remote)).toBe(merged)
  })

  it('is deterministic: the same inputs always give the same text', () => {
    const results = Array.from({ length: 5 }, () =>
      merge3('the cat sat', 'the dog sat', 'the cow sat'),
    )

    expect(new Set(results)).toEqual(new Set(['the cowdog sat']))
  })

  it('merges a 100 kB text in under 50 ms', () => {
    const base = 'lorem ipsum dolor sit amet '.repeat(4000)
    const local = `start ${base.slice(0, 50_000)} middle ${base.slice(50_000)}`
    const remote = `${base.slice(0, 80_000)} remote ${base.slice(80_000)} end`
    merge3(base, local, remote)

    const started = performance.now()
    const merged = merge3(base, local, remote)
    const elapsed = performance.now() - started

    expect(base.length).toBeGreaterThan(100_000)
    expect(merged).toBe(
      `start ${base.slice(0, 50_000)} middle ${base.slice(50_000, 80_000)} remote ${base.slice(80_000)} end`,
    )
    expect(elapsed).toBeLessThan(50)
  })
})

describe('merge3WithCursor', () => {
  it.each([
    {
      name: 'shifts the caret when the remote side inserts before it',
      local: 'hello world!',
      remote: 'oh hello world',
      cursor: { start: 12, end: 12 },
      merged: { text: 'oh hello world!', cursor: { start: 15, end: 15 } },
    },
    {
      name: 'keeps the caret when the remote side inserts after it',
      local: '!hello world',
      remote: 'hello world, bye',
      cursor: { start: 1, end: 1 },
      merged: { text: '!hello world, bye', cursor: { start: 1, end: 1 } },
    },
    {
      name: 'keeps the caret after its own insert when the remote inserts at the same place',
      local: 'hello big world',
      remote: 'hello small world',
      cursor: { start: 9, end: 9 },
      merged: { text: 'hello small big world', cursor: { start: 15, end: 15 } },
    },
    {
      name: 'maps both ends of a selection',
      local: 'hello world!',
      remote: 'oh hello world',
      cursor: { start: 6, end: 11 },
      merged: { text: 'oh hello world!', cursor: { start: 9, end: 14 } },
    },
    {
      name: 'moves the caret over a remote emoji by its UTF-16 length',
      local: 'hello world!',
      remote: '👋 hello world',
      cursor: { start: 12, end: 12 },
      merged: { text: '👋 hello world!', cursor: { start: 15, end: 15 } },
    },
  ])('$name', ({ local, remote, cursor, merged }) => {
    expect(merge3WithCursor('hello world', local, remote, cursor)).toEqual(merged)
  })

  it('leaves the caret untouched when the remote side did not change', () => {
    const cursor = { start: 3, end: 5 }

    expect(merge3WithCursor('base', 'base text', 'base', cursor)).toEqual({
      text: 'base text',
      cursor,
    })
  })

  it('maps the caret through a remote change when the local side did not change', () => {
    expect(
      merge3WithCursor('hello world', 'hello world', 'oh hello world', { start: 5, end: 5 }),
    ).toEqual({ text: 'oh hello world', cursor: { start: 8, end: 8 } })
  })
})
