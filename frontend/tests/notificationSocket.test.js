import { test } from 'node:test';
import assert from 'node:assert/strict';
import { connectNotifications } from '../src/lib/notificationSocket.js';

test('missing URL uses polling without opening a connection', () => {
  let status;
  const stop = connectNotifications({ url: '', Socket: class { constructor() { throw new Error('Unexpected connection'); } }, onChange() {}, onStatus(value) { status = value; } });
  assert.equal(status, 'polling'); stop();
});
test('socket hints refetch authoritative inbox and cleanup closes connection', () => {
  let socket, changes = 0; const statuses = [];
  class Socket { constructor(url) { this.url = url; socket = this; } close() { this.closed = true; } }
  const stop = connectNotifications({ url: 'wss://example.test/inbox', Socket, onChange() { changes++; }, onStatus(value) { statuses.push(value); } });
  socket.onopen(); socket.onmessage({ data: '{"untrusted":"payload"}' });
  assert.equal(changes, 2); assert.deepEqual(statuses, ['connecting', 'connected']);
  stop(); assert.equal(socket.closed, true); assert.equal(socket.onmessage, null);
});
test('invalid URL falls back without opening a socket', () => {
  let status;
  connectNotifications({ url: 'https://example.test', Socket: class { constructor() { assert.fail(); } }, onChange() {}, onStatus(value) { status = value; } });
  assert.equal(status, 'polling');
});
