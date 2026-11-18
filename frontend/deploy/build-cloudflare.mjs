import { spawnSync } from 'node:child_process'

// Setting this in Node also works on Windows shells which remove empty env values.
process.env.VITE_API_ORIGIN = ''
for (const args of [
  ['node_modules/typescript/bin/tsc', '--noEmit', '-p', 'tsconfig.app.json'],
  ['node_modules/vite/bin/vite.js', 'build'],
]) {
  const result = spawnSync(process.execPath, args, { stdio: 'inherit', env: process.env })
  if (result.error) throw result.error
  if (result.status !== 0) process.exit(result.status ?? 1)
}
await import('./prepare-cloudflare.mjs')
