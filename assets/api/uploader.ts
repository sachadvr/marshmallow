import { ROUTES } from './contracts'
import type { FileDto } from './contracts'
import { ApiError, apiErrorFrom } from './errors'

export type UploadProgress = { loaded: number; total: number }

export type Uploader = (
  file: File,
  onProgress: (progress: UploadProgress) => void,
) => Promise<FileDto>

export const xhrUploader: Uploader = (file, onProgress) =>
  new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    const formData = new FormData()
    formData.append('file', file)

    xhr.upload.addEventListener('progress', (event) => {
      if (event.lengthComputable) onProgress({ loaded: event.loaded, total: event.total })
    })
    xhr.addEventListener('load', () => {
      if (xhr.status < 200 || xhr.status >= 300) {
        reject(apiErrorFrom(xhr.status, xhr.responseText, 'upload'))

        return
      }

      try {
        resolve(JSON.parse(xhr.responseText) as FileDto)
      } catch {
        reject(new ApiError('INTERNAL', xhr.status))
      }
    })
    xhr.addEventListener('error', () => reject(new ApiError('NETWORK', 0)))
    xhr.addEventListener('abort', () => reject(new ApiError('NETWORK', 0)))

    xhr.open('POST', ROUTES.files)
    xhr.setRequestHeader('Accept', 'application/json')
    xhr.send(formData)
  })
