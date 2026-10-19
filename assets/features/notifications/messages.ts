import type { ApiErrorCode } from '../../api/contracts'
import { MAX_TEXT_BYTES, MAX_UPLOAD_BYTES } from '../../api/limits'
import { formatBytes } from '../../shared/formatBytes'

export const ERROR_MESSAGES: Record<ApiErrorCode, string> = {
  NO_FILE: 'Aucun fichier fourni',
  EMPTY_FILE: 'Le fichier est vide',
  UPLOAD_INCOMPLETE: "L'envoi du fichier a été interrompu, réessaie",
  FILE_TOO_LARGE: 'Le fichier dépasse la taille maximale autorisée',
  TEXT_TOO_LARGE: 'Le texte dépasse la taille maximale autorisée',
  TEXT_CONFLICT: 'Le texte a été modifié ailleurs en même temps, réessayez',
  INVALID_REQUEST: 'Requête invalide',
  NOT_FOUND: 'Ressource introuvable',
  FORBIDDEN: 'Requête refusée',
  INTERNAL: 'Erreur interne du serveur',
  NETWORK: 'Erreur réseau, vérifiez votre connexion',
}

const LIMITS: Partial<Record<ApiErrorCode, { subject: string; bytes: number }>> = {
  FILE_TOO_LARGE: { subject: 'Le fichier', bytes: MAX_UPLOAD_BYTES },
  TEXT_TOO_LARGE: { subject: 'Le texte', bytes: MAX_TEXT_BYTES },
}

export function errorMessage(code: ApiErrorCode): string {
  const limit = LIMITS[code]

  return limit
    ? `${limit.subject} dépasse la limite de ${formatBytes(limit.bytes)}`
    : ERROR_MESSAGES[code]
}

export const NOTICES = {
  uploaded: 'Fichier uploadé avec succès',
  copyFailed: 'Impossible de copier le texte',
  pasteFailed: 'Impossible de coller le texte',
} as const
