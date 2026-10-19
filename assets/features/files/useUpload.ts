import { ref } from 'vue'
import type { Ref } from 'vue'
import type { FileDto } from '../../api/contracts'
import { ApiError } from '../../api/errors'
import { MAX_UPLOAD_BYTES } from '../../api/limits'
import { xhrUploader } from '../../api/uploader'
import type { UploadProgress, Uploader } from '../../api/uploader'

export function useUpload(deps: { uploader?: Uploader; maxBytes?: number } = {}): {
  progress: Readonly<Ref<UploadProgress | null>>
  uploading: Readonly<Ref<boolean>>
  upload: (file: File) => Promise<FileDto>
} {
  const uploader = deps.uploader ?? xhrUploader
  const maxBytes = deps.maxBytes ?? MAX_UPLOAD_BYTES

  const progress = ref<UploadProgress | null>(null)
  const uploading = ref(false)

  async function upload(file: File): Promise<FileDto> {
    if (file.size === 0) throw new ApiError('EMPTY_FILE', 0)

    if (file.size > maxBytes) throw new ApiError('FILE_TOO_LARGE', 0)
    uploading.value = true
    progress.value = { loaded: 0, total: file.size }

    try {
      return await uploader(file, (update) => {
        progress.value = update
      })
    } finally {
      uploading.value = false
      progress.value = null
    }
  }

  return { progress, uploading, upload }
}
