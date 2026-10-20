<script setup lang="ts">
import { computed, ref, useTemplateRef } from 'vue'
import type { PageProps, FileDto } from './api/contracts'
import { apiErrorCodeOf } from './api/errors'
import { getLatestFile, getText } from './api/http'
import ConnectionBadge from './features/connection/ConnectionBadge.vue'
import { useRealtime } from './features/connection/useRealtime'
import DownloadButton from './features/files/DownloadButton.vue'
import DropZone from './features/files/DropZone.vue'
import LatestFile from './features/files/LatestFile.vue'
import UploadProgress from './features/files/UploadProgress.vue'
import { isNewerFile } from './features/files/fileOrder'
import { useDropZone } from './features/files/useDropZone'
import { useUpload } from './features/files/useUpload'
import NotificationBanner from './features/notifications/NotificationBanner.vue'
import { errorMessage, NOTICES } from './features/notifications/messages'
import { useNotification } from './features/notifications/useNotification'
import TextEditor from './features/text/TextEditor.vue'
import { useClipboard } from './features/text/useClipboard'
import { useText } from './features/text/useText'
import AboutSection from './layout/AboutSection.vue'
import AppFooter from './layout/AppFooter.vue'

const props = defineProps<PageProps>()

const notification = useNotification()
const notifyError = (error: unknown) =>
  notification.show(errorMessage(apiErrorCodeOf(error)), 'error')

const textEditor = useTemplateRef<InstanceType<typeof TextEditor>>('textEditor')
const textarea = computed(() => textEditor.value?.textarea ?? null)
const {
  text,
  setText,
  applyRemote,
  flush: flushText,
} = useText({
  editor: textarea,
  onError: (code) => notification.show(errorMessage(code), 'error'),
})
applyRemote(props.initialText)
const clipboard = useClipboard()
const { progress, upload } = useUpload({ maxBytes: props.limits.maxUploadBytes })

const latestFile = ref<FileDto | null>(props.initialLatestFile)

function showFile(file: FileDto | null): void {
  if (file && isNewerFile(file, latestFile.value)) latestFile.value = file
}

function resync(): void {
  Promise.all([getText(), getLatestFile()]).then(([state, file]) => {
    applyRemote(state)
    showFile(file)
  }, notifyError)
}

function onOpen(): void {
  void flushText().then(resync)
}

const { status } = useRealtime({ text: applyRemote, file: showFile, open: onOpen })

function onFile(file: File): void {
  upload(file).then((stored) => {
    showFile(stored)
    notification.show(NOTICES.uploaded, 'success')
  }, notifyError)
}

function onCopy(): void {
  clipboard.copy(text.value).catch(() => notification.show(NOTICES.copyFailed, 'error'))
}

function onPaste(): void {
  clipboard.paste().then(setText, () => notification.show(NOTICES.pasteFailed, 'error'))
}

const dropZone = useTemplateRef<InstanceType<typeof DropZone>>('dropZone')
const dropTarget = computed<HTMLElement | null>(() => dropZone.value?.$el ?? null)
const { isDragging } = useDropZone(dropTarget, { onFile, onText: setText })
</script>

<template>
  <NotificationBanner :notification="notification.current.value" />
  <ConnectionBadge :status="status" />
  <div class="flex h-full w-full max-w-[700px] flex-col p-2 md:block md:h-auto">
    <LatestFile v-if="latestFile" :file="latestFile" />
    <h1 class="mt-0 mb-0 p-0 text-2xl md:mt-[70px]">
      Marshmallow <span class="text-[0.5rem] text-[#d3d3d3]">(v3)</span>
    </h1>
    <DropZone ref="dropZone" :dragging="isDragging" @file="onFile" />
    <UploadProgress :progress="progress" />
    <TextEditor
      ref="textEditor"
      :model-value="text"
      @update:model-value="setText"
      @clear="setText('')"
      @copy="onCopy"
      @paste="onPaste"
    >
      <template #actions>
        <DownloadButton v-if="latestFile" :file="latestFile" />
      </template>
    </TextEditor>
    <AboutSection />
  </div>
  <AppFooter />
</template>
