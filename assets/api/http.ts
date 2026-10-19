import { ROUTES } from './contracts'
import type { ReplaceTextRequest, FileDto, TextState } from './contracts'
import { ApiError, apiErrorFrom } from './errors'
import type { ErrorContext } from './errors'

const ACCEPT_JSON = { Accept: 'application/json' }

export const REQUEST_TIMEOUT_MS = 10_000

export interface RequestOptions {
  timeoutMs?: number
}

async function request<T>(
  url: string,
  init: RequestInit,
  { timeoutMs = REQUEST_TIMEOUT_MS }: RequestOptions,
  context?: ErrorContext,
): Promise<T> {
  let response: Response
  let bodyText: string

  try {
    response = await fetch(url, {
      ...init,
      headers: { ...ACCEPT_JSON, ...init.headers },
      signal: AbortSignal.timeout(timeoutMs),
    })
    bodyText = await response.text()
  } catch {
    throw new ApiError('NETWORK', 0)
  }

  if (!response.ok) throw apiErrorFrom(response.status, bodyText, context)

  try {
    return JSON.parse(bodyText) as T
  } catch {
    throw new ApiError('INTERNAL', response.status)
  }
}

export function getText(options: RequestOptions = {}): Promise<TextState> {
  return request<TextState>(ROUTES.text, {}, options)
}

export async function getLatestFile(options: RequestOptions = {}): Promise<FileDto | null> {
  const files = await request<FileDto[]>(ROUTES.latestFile, {}, options)

  return files[0] ?? null
}

export function putText(
  content: string,
  baseVersion: number,
  options: RequestOptions = {},
): Promise<TextState> {
  const body: ReplaceTextRequest = { content, baseVersion }

  return request<TextState>(
    ROUTES.text,
    {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    },
    options,
    'text',
  )
}
