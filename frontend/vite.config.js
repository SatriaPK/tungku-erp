import react from '@vitejs/plugin-react'
import Icons from 'unplugin-icons/vite'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), Icons({ compiler: 'jsx', jsx: 'react' })],
  server: {
    host: 'localhost', port: 5175, strictPort: true, open: '/staff/login',
    proxy: {
      '/api': { target: 'http://localhost:8001' },
      '/sanctum': { target: 'http://localhost:8001' },
    },
  },
})
