import { copyFile, writeFile, access } from 'node:fs/promises'

// Run from frontend after building with VITE_API_ORIGIN set to an empty string.
if (process.env.VITE_API_ORIGIN !== '') {
  throw new Error('Set VITE_API_ORIGIN to an empty string before building for Cloudflare Pages so session cookies use the same origin.')
}
await access('dist/index.html')
await copyFile(new URL('./cloudflare-worker.mjs', import.meta.url), 'dist/_worker.js')
await writeFile('dist/_routes.json', JSON.stringify({ version: 1, include: ['/api/*', '/sanctum/*'], exclude: [] }, null, 2) + '\n')
console.log('Cloudflare Pages output prepared. Set API_ORIGIN in the Pages runtime environment.')
