import { ERROR_TYPE_PREFIX } from './contracts'
import type { ApiErrorCode } from './contracts'

export type ErrorContext = 'upload' | 'text'

const API_ERROR_CODES: Record<ApiErrorCode, true> = {
  NO_FILE: true,
  EMPTY_FILE: true,
  UPLOAD_INCOMPLETE: true,
  FILE_TOO_LARGE: true,
  TEXT_TOO_LARGE: true,
  TEXT_CONFLICT: true,
  INVALID_REQUEST: true,
  NOT_FOUND: true,
  FORBIDDEN: true,
  INTERNAL: true,
  NETWORK: true,
}

export class ApiError extends Error {
  readonly code: ApiErrorCode
  readonly status: number

  constructor(code: ApiErrorCode, status: number) {
    super(`${code} (HTTP ${status})`)
    this.name = 'ApiError'
    this.code = code
    this.status = status
  }
}

function isApiErrorCode(value: string): value is ApiErrorCode {
  return Object.hasOwn(API_ERROR_CODES, value)
}

function codeFromType(body: unknown): ApiErrorCode | null {
  if (typeof body !== 'object' || body === null || !('type' in body)) return null
  const { type } = body

  if (typeof type !== 'string' || !type.startsWith(ERROR_TYPE_PREFIX)) return null
  const code = type.slice(ERROR_TYPE_PREFIX.length)

  return isApiErrorCode(code) ? code : null
}

function hasViolationOn(body: unknown, propertyPath: string): boolean {
  if (typeof body !== 'object' || body === null || !('violations' in body)) return false
  const { violations } = body

  return (
    Array.isArray(violations) &&
    violations.some(
      (violation: unknown) =>
        typeof violation === 'object' &&
        violation !== null &&
        'propertyPath' in violation &&
        violation.propertyPath === propertyPath,
    )
  )
}

function codeFromViolations(
  body: unknown,
  status: number,
  context?: ErrorContext,
): ApiErrorCode | null {
  return status === 422 && context === 'text' && hasViolationOn(body, 'content')
    ? 'TEXT_TOO_LARGE'
    : null
}

function codeFromStatus(status: number, context?: ErrorContext): ApiErrorCode {
  switch (status) {
    case 0:
      return 'NETWORK'
    case 400:
    case 405:
    case 415:
    case 422:
      return 'INVALID_REQUEST'
    case 403:
      return 'FORBIDDEN'
    case 404:
      return 'NOT_FOUND'
    case 409:
      return 'TEXT_CONFLICT'
    case 413:
      return context === 'text' ? 'TEXT_TOO_LARGE' : 'FILE_TOO_LARGE'
    default:
      return 'INTERNAL'
  }
}

export function errorCodeOf(body: unknown, status: number, context?: ErrorContext): ApiErrorCode {
  return (
    codeFromType(body) ??
    codeFromViolations(body, status, context) ??
    codeFromStatus(status, context)
  )
}

export function apiErrorFrom(status: number, bodyText: string, context?: ErrorContext): ApiError {
  let body: unknown = null

  try {
    body = JSON.parse(bodyText)
  } catch {}

  return new ApiError(errorCodeOf(body, status, context), status)
}

export function apiErrorCodeOf(error: unknown): ApiErrorCode {
  return error instanceof ApiError ? error.code : 'INTERNAL'
}
