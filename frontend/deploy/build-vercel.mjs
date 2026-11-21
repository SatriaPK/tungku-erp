import { spawnSync } from 'node:child_process'

// Keep API and session cookies on the frontend's origin through Vercel rewrites.
process.env.VITE_API_ORIGIN = ''
for (const args of [
  ['node_modules/typescript/bin/tsc', '--noEmit', '-p', 'tsconfig.app.json'],
  ['node_modules/vite/bin/vite.js', 'build'],
]) {
  const result = spawnSync(process.execPath, args, { stdio: 'inherit', env: process.env })
  if (result.error) throw result.error
  if (result.status !== 0) process.exit(result.status ?? 1)
}
