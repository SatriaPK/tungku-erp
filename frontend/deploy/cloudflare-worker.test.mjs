import { test } from 'node:test'
import assert from 'node:assert/strict'
import worker from './cloudflare-worker.mjs'

test('SPA and assets do not contact the API', async () => {
  const request = new Request('https://studio.pages.dev/client/booking?package=2')
  const response = await worker.fetch(request, { ASSETS: { fetch: async (received) => { assert.equal(received, request); return new Response('SPA') } } })
  assert.equal(await response.text(), 'SPA')
})

test('unconfigured, unsafe, or recursive upstream fails closed', async () => {
  for (const API_ORIGIN of [undefined, 'http://api.example.com', 'https://studio.pages.dev', 'https://user:secret@api.example.com', 'https://api.example.com/path']) {
    const response = await worker.fetch(new Request('https://studio.pages.dev/api/v1/auth/me'), { API_ORIGIN })
    assert.equal(response.status, 503)
    assert.equal(response.headers.get('cache-control'), 'no-store')
  }
})

test('proxy preserves POST, session, CSRF, origin, query and response cookies', async (t) => {
  t.mock.method(globalThis, 'fetch', async (request, options) => {
    assert.equal(request.url, 'https://api.example.com/api/v1/auth/login?source=web')
    assert.equal(request.method, 'POST')
    assert.equal(request.headers.get('cookie'), 'session=encrypted')
    assert.equal(request.headers.get('x-xsrf-token'), 'csrf')
    assert.equal(request.headers.get('origin'), 'https://studio.pages.dev')
    assert.equal(request.headers.get('x-forwarded-for'), '192.0.2.1')
    assert.equal(request.headers.get('forwarded'), null)
    assert.equal(options.redirect, 'manual')
    assert.deepEqual(await request.json(), { email: 'client@example.test' })
    const headers = new Headers({ 'Content-Type': 'application/json' })
    headers.append('Set-Cookie', 'session=new; Path=/; Secure; HttpOnly; SameSite=Lax')
    headers.append('Set-Cookie', 'XSRF-TOKEN=csrf; Path=/; Secure; SameSite=Lax')
    return new Response('{"data":{"role":"client"}}', { headers })
  })
  const response = await worker.fetch(new Request('https://studio.pages.dev/api/v1/auth/login?source=web', {
    method: 'POST', headers: { Cookie: 'session=encrypted', 'X-XSRF-TOKEN': 'csrf', Origin: 'https://studio.pages.dev', 'Content-Type': 'application/json', 'CF-Connecting-IP': '192.0.2.1', 'X-Forwarded-For': 'forged', Forwarded: 'forged' }, body: JSON.stringify({ email: 'client@example.test' }),
  }), { API_ORIGIN: 'https://api.example.com' })
  assert.equal(response.status, 200)
  assert.equal(response.headers.getSetCookie().length, 2)
  assert.equal(response.headers.get('cache-control'), 'no-store')
})

test('CSRF route forwards backend errors without replacing their status', async (t) => {
  t.mock.method(globalThis, 'fetch', async (request) => {
    assert.equal(request.url, 'https://api.example.com/sanctum/csrf-cookie')
    return Response.json({ message: 'Expired' }, { status: 419 })
  })
  const response = await worker.fetch(new Request('https://studio.pages.dev/sanctum/csrf-cookie'), { API_ORIGIN: 'https://api.example.com' })
  assert.equal(response.status, 419)
})

test('network failure returns JSON and does not cache authentication responses', async (t) => {
  t.mock.method(globalThis, 'fetch', async () => { throw new Error('offline') })
  const response = await worker.fetch(new Request('https://studio.pages.dev/api/v1/auth/me'), { API_ORIGIN: 'https://api.example.com' })
  assert.equal(response.status, 502)
  assert.equal(response.headers.get('cache-control'), 'no-store')
})
