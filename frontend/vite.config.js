import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// Production builds drop Vite HMR CSP exceptions from index.html meta tag.
const PROD_CSP =
  "default-src 'self'; base-uri 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data: blob:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self';"

export default defineConfig(({ command }) => ({
  plugins: [
    react(),
    {
      name: 'ircub-prod-csp',
      transformIndexHtml(html) {
        if (command !== 'build') {
          return html
        }
        return html.replace(
          /<meta http-equiv="Content-Security-Policy"[^>]*>/i,
          `<meta http-equiv="Content-Security-Policy" content="${PROD_CSP}">`,
        )
      },
    },
  ],
}))
