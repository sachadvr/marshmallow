import { describe, expect, it } from 'vitest'
import { ApiError, apiErrorCodeOf, apiErrorFrom, errorCodeOf } from '@/api/errors'

describe('errorCodeOf', () => {
  it.each([
    ['NO_FILE', 400],
    ['EMPTY_FILE', 400],
    ['UPLOAD_INCOMPLETE', 400],
    ['FILE_TOO_LARGE', 413],
    ['TEXT_TOO_LARGE', 413],
    ['NOT_FOUND', 404],
  ])('reads the code %s from the prefixed problem type', (code, status) => {
    const problem = { type: `urn:marshmallow:error:${code}`, title: code, status }

    expect(errorCodeOf(problem, status)).toBe(code)
  })

  it('prefers the problem type over the status', () => {
    expect(errorCodeOf({ type: 'urn:marshmallow:error:TEXT_TOO_LARGE' }, 500)).toBe(
      'TEXT_TOO_LARGE',
    )
  })

  it.each([
    [{ type: '/errors/404' }, 404, 'NOT_FOUND'],
    [{ type: 'urn:marshmallow:error:UNKNOWN' }, 400, 'INVALID_REQUEST'],
    [{ type: 'urn:marshmallow:error:toString' }, 500, 'INTERNAL'],
    [{ type: 42 }, 403, 'FORBIDDEN'],
    [null, 409, 'TEXT_CONFLICT'],
    ['<html>', 405, 'INVALID_REQUEST'],
    [undefined, 415, 'INVALID_REQUEST'],
    [{}, 422, 'INVALID_REQUEST'],
    [{}, 500, 'INTERNAL'],
    [{}, 502, 'INTERNAL'],
    [null, 0, 'NETWORK'],
  ])('falls back on the status for %j / %i → %s', (body, status, code) => {
    expect(errorCodeOf(body, status)).toBe(code)
  })

  it.each([
    [undefined, 'FILE_TOO_LARGE'],
    ['upload', 'FILE_TOO_LARGE'],
    ['text', 'TEXT_TOO_LARGE'],
  ] as const)('maps a bare 413 with context %s to %s', (context, code) => {
    expect(errorCodeOf(null, 413, context)).toBe(code)
  })

  const violationOn = (propertyPath: string) => ({
    status: 422,
    type: '/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
    violations: [{ propertyPath, message: 'This value is too long.' }],
  })

  it('maps a 422 with a violation on content in the text context to TEXT_TOO_LARGE', () => {
    expect(errorCodeOf(violationOn('content'), 422, 'text')).toBe('TEXT_TOO_LARGE')
  })

  it.each([
    [violationOn('baseVersion'), 'text'],
    [violationOn('content'), 'upload'],
    [violationOn('content'), undefined],
    [{ status: 422, violations: [] }, 'text'],
    [{ status: 422, violations: 'content' }, 'text'],
    [{ status: 422, violations: [null, 'content'] }, 'text'],
    [{}, 'text'],
  ] as const)('keeps any other 422 (%j, context %s) as INVALID_REQUEST', (body, context) => {
    expect(errorCodeOf(body, 422, context)).toBe('INVALID_REQUEST')
  })

  it('only reads violations on a 422', () => {
    expect(errorCodeOf(violationOn('content'), 400, 'text')).toBe('INVALID_REQUEST')
  })
})

describe('apiErrorFrom', () => {
  it('parses a problem+json body', () => {
    const error = apiErrorFrom(400, JSON.stringify({ type: 'urn:marshmallow:error:EMPTY_FILE' }))

    expect(error).toBeInstanceOf(ApiError)
    expect(error).toMatchObject({ code: 'EMPTY_FILE', status: 400 })
  })

  it('tolerates a non JSON body such as the Caddy 413 page', () => {
    expect(apiErrorFrom(413, '413 Request Entity Too Large', 'text')).toMatchObject({
      code: 'TEXT_TOO_LARGE',
      status: 413,
    })
  })

  it('parses the validation problem of a text above the limit', () => {
    const problem = { status: 422, violations: [{ propertyPath: 'content', message: 'too long' }] }

    expect(apiErrorFrom(422, JSON.stringify(problem), 'text')).toMatchObject({
      code: 'TEXT_TOO_LARGE',
      status: 422,
    })
  })

  it('tolerates an empty body', () => {
    expect(apiErrorFrom(500, '')).toMatchObject({ code: 'INTERNAL', status: 500 })
  })
})

describe('apiErrorCodeOf', () => {
  it('keeps the code of an ApiError', () => {
    expect(apiErrorCodeOf(new ApiError('FORBIDDEN', 403))).toBe('FORBIDDEN')
  })

  it('maps anything else to INTERNAL', () => {
    expect(apiErrorCodeOf(new TypeError('boom'))).toBe('INTERNAL')
  })
})
