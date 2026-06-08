import { defineConfig } from 'vite'
import react, { reactCompilerPreset } from '@vitejs/plugin-react'
import babel from '@rolldown/plugin-babel'
import tailwindcss from '@tailwindcss/vite'
import path from 'path'

// https://vite.dev/config/
export default defineConfig({
  server: {
    allowedHosts: ['localhost', '127.0.0.1', 'stayhub.id.vn', 'www.stayhub.id.vn', 'webserver'],
  },
  resolve: {
    alias: {
      'react-datepicker/dist/react-datepicker.css': path.resolve(__dirname, 'node_modules/react-datepicker/dist/react-datepicker.css'),
    },
  },
  plugins: [
    tailwindcss(),
    react(),
    babel({ presets: [reactCompilerPreset()] })
  ],
})
