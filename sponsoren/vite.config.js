import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  base: './',
  // Lokal PHP testen: cd public && php -S localhost:8787
  // Dann hier proxy aktivieren, damit fetch(api/send-sponsor.php) über den Dev-Server läuft.
  server: {
    proxy: {
      '/api': {
        target: 'http://localhost:8787',
        changeOrigin: true,
      },
    },
  },
  preview: {
    proxy: {
      '/api': {
        target: 'http://localhost:8787',
        changeOrigin: true,
      },
    },
  },
})
