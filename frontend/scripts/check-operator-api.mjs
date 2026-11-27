import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import ts from 'typescript'

// Exercise the actual HTTP adapter without installing a test framework.
const source = await readFile(new URL('../src/lib/manager-operators-api.ts', import.meta.url), 'utf8')
const compiled = ts.transpileModule(source.replace('import.meta.env.VITE_API_ORIGIN', "'http://localhost:8000'"), {
  compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 },
}).outputText
const api = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`)
const originalFetch = globalThis.fetch
const operator = { id: 2, name: 'Operator Satu', email: 'operator1@studio.test', is_active: true, assignments_count: 0 }
const meta = { current_page: 2, last_page: 3, per_page: 10, total: 21, active_count: 18 }
try {
  globalThis.document = { cookie: 'XSRF-TOKEN=test-token' }
  globalThis.fetch = async () => Response.json({ data: [operator], links: {}, meta })
  assert.deepEqual(await api.getManagerOperators('', 2), { data: [operator], ...meta })
  globalThis.fetch = async () => Response.json({ data: [], links: {}, meta: { ...meta, current_page: 1, last_page: 1, total: 0, active_count: 0 } })
  assert.deepEqual((await api.getManagerOperators()).data, [])
  globalThis.fetch = async (_url, options) => {
    assert.equal(options.headers['X-XSRF-TOKEN'], 'test-token')
    return Response.json({ data: operator })
  }
  assert.deepEqual(await api.updateManagerOperatorStatus(2, true), operator)
  assert.deepEqual(await api.createManagerOperator({ name: operator.name, email: operator.email, phone: '6281200000002', password: 'test-only', password_confirmation: 'test-only' }), operator)
  globalThis.fetch = async () => Response.json({ message: 'Unauthenticated.' }, { status: 401 })
  await assert.rejects(api.getManagerOperators(), { message: 'Unauthenticated.', status: 401 })
  console.log('PASS: operator list, pagination, empty list, create, status update, and HTTP errors')
} finally {
  globalThis.fetch = originalFetch
  delete globalThis.document
}