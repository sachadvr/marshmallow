import diff from 'fast-diff'
import * as Y from 'yjs'

export interface TextSelection {
  start: number
  end: number
}

export interface MergeResult {
  text: string
  cursor: TextSelection
}

const BASE_CLIENT = 1
const REMOTE_CLIENT = 2
const LOCAL_CLIENT = 3

function docFrom(baseUpdate: Uint8Array, clientID: number): Y.Doc {
  const doc = new Y.Doc()
  doc.clientID = clientID
  Y.applyUpdate(doc, baseUpdate)

  return doc
}

const TOKEN = /[\p{L}\p{N}_]+|[^\p{L}\p{N}_]/gu
const MAX_TOKENS = 0xd800

function wordDiff(from: string, to: string): diff.Diff[] {
  const codes = new Map<string, string>()
  const words: string[] = []
  const encode = (text: string) =>
    (text.match(TOKEN) ?? [])
      .map((word) => {
        let code = codes.get(word)

        if (code === undefined) {
          code = String.fromCharCode(words.push(word) - 1)
          codes.set(word, code)
        }

        return code
      })
      .join('')
  const encodedFrom = encode(from)
  const encodedTo = encode(to)

  if (words.length > MAX_TOKENS) return diff(from, to)

  const decode = (encoded: string) =>
    Array.from(encoded, (code) => words[code.charCodeAt(0)]).join('')

  return diff(encodedFrom, encodedTo).map(([op, chunk]) => [op, decode(chunk)])
}

function applyDiff(text: Y.Text, from: string, to: string): void {
  text.doc!.transact(() => {
    let index = 0

    for (const [op, chunk] of wordDiff(from, to)) {
      if (op === diff.DELETE) {
        text.delete(index, chunk.length)
        continue
      }

      if (op === diff.INSERT) text.insert(index, chunk)
      index += chunk.length
    }
  })
}

function mapIndex(position: Y.RelativePosition, doc: Y.Doc, fallback: number): number {
  return Y.createAbsolutePositionFromRelativePosition(position, doc)?.index ?? fallback
}

export function merge3WithCursor(
  base: string,
  local: string,
  remote: string,
  cursor: TextSelection,
): MergeResult {
  if (remote === base || local === remote) return { text: local, cursor }

  const baseDoc = new Y.Doc()
  baseDoc.clientID = BASE_CLIENT
  baseDoc.getText().insert(0, base)
  const baseUpdate = Y.encodeStateAsUpdate(baseDoc)
  const localDoc = docFrom(baseUpdate, LOCAL_CLIENT)
  const remoteDoc = docFrom(baseUpdate, REMOTE_CLIENT)

  try {
    const localText = localDoc.getText()
    applyDiff(localText, base, local)
    applyDiff(remoteDoc.getText(), base, remote)
    const start = Y.createRelativePositionFromTypeIndex(localText, cursor.start)
    const end = Y.createRelativePositionFromTypeIndex(localText, cursor.end)

    Y.applyUpdate(localDoc, Y.encodeStateAsUpdate(remoteDoc, Y.encodeStateVector(localDoc)))

    const text = localText.toJSON()

    return {
      text,
      cursor: {
        start: mapIndex(start, localDoc, text.length),
        end: mapIndex(end, localDoc, text.length),
      },
    }
  } finally {
    baseDoc.destroy()
    localDoc.destroy()
    remoteDoc.destroy()
  }
}

export function merge3(base: string, local: string, remote: string): string {
  if (local === base) return remote

  return merge3WithCursor(base, local, remote, { start: 0, end: 0 }).text
}
