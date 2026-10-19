import type { components } from './generated/schema'

type Schemas = components['schemas']

export type TextState = Schemas['Text-text.read']
export type FileDto = Schemas['File-file.read']
export type ReplaceTextRequest = Schemas['Text.ReplaceTextInput']

export interface PageProps {
  initialText: TextState
  initialLatestFile: FileDto | null
  limits: { maxUploadBytes: number; maxTextBytes: number }
}

export const TOPICS = {
  text: 'urn:marshmallow:text',
  files: 'urn:marshmallow:files',
} as const

export interface RealtimeEvents {
  text: TextState
  file: FileDto
}

export const ROUTES = {
  text: '/api/text',
  latestFile: '/api/files/latest',
  files: '/api/files',
  hub: '/.well-known/mercure',
} as const

export type ApiErrorCode =
  | 'NO_FILE'
  | 'EMPTY_FILE'
  | 'UPLOAD_INCOMPLETE'
  | 'FILE_TOO_LARGE'
  | 'TEXT_TOO_LARGE'
  | 'TEXT_CONFLICT'
  | 'INVALID_REQUEST'
  | 'NOT_FOUND'
  | 'FORBIDDEN'
  | 'INTERNAL'
  | 'NETWORK'

export const ERROR_TYPE_PREFIX = 'urn:marshmallow:error:'
