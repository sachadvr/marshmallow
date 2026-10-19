import type { FileDto } from '../../api/contracts'

export function isNewerFile(candidate: FileDto, current: FileDto | null): boolean {
  if (!current) return true
  const delta = Date.parse(candidate.uploadedAt) - Date.parse(current.uploadedAt)

  return delta > 0 || (delta === 0 && candidate.id > current.id)
}
