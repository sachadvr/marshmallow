/// <reference types="vitest/config" />
import { fileURLToPath } from 'node:url'
import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'
import symfony from 'vite-plugin-symfony'

export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
    symfony({
      stimulus: './assets/controllers.json',
      viteDevServerHostname: 'localhost',
    }),
  ],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./assets', import.meta.url)) },
  },
  base: '/build/',
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    rollupOptions: {
      input: { app: './assets/app.ts' },
    },
  },
  publicDir: false,
  test: {
    environment: 'happy-dom',
    include: ['tests/assets/**/*.test.ts'],
  },
})
