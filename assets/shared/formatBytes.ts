const UNITS = ['B', 'KB', 'MB', 'GB'] as const
const STEP = 1024

export function formatBytes(bytes: number): string {
  if (bytes <= 0) return '0 B'
  const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(STEP)), UNITS.length - 1)
  const value = Math.round((bytes / STEP ** exponent) * 100) / 100

  return `${value} ${UNITS[exponent]}`
}
