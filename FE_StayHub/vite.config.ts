import { defineConfig } from 'vite'
import type { Plugin } from 'vite'
import react, { reactCompilerPreset } from '@vitejs/plugin-react'
import babel from '@rolldown/plugin-babel'
import tailwindcss from '@tailwindcss/vite'
import path from 'path'

const blockedRootFiles = new Set([
  '/Dockerfile',
  '/docker-compose.yml',
  '/package.json',
  '/package-lock.json',
])

function blockRootInfrastructureFiles(): Plugin {
  return {
    name: 'block-root-infrastructure-files',
    configureServer(server) {
      server.middlewares.use((request, response, next) => {
        const requestPath = request.url?.split('?')[0]

        if (requestPath && blockedRootFiles.has(requestPath)) {
          response.statusCode = 404
          response.end('Not found')
          return
        }

        next()
      })
    },
  }
}

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
    blockRootInfrastructureFiles(),
    tailwindcss(),
    react(),
    babel({ presets: [reactCompilerPreset()] })
  ],
})
