type ClipboardLike = Pick<Clipboard, 'writeText' | 'readText'>

export function useClipboard(deps: { clipboard?: ClipboardLike } = {}): {
  copy: (text: string) => Promise<void>
  paste: () => Promise<string>
} {
  const clipboard = (): ClipboardLike => deps.clipboard ?? navigator.clipboard

  return {
    copy: async (text) => {
      await clipboard().writeText(text)
    },
    paste: async () => await clipboard().readText(),
  }
}
