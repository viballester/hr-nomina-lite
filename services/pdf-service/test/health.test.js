import { test } from 'node:test';
import assert from 'node:assert/strict';
import { withServer } from './helpers.js';

test('GET /health responde ok sin token', () => withServer(async (url) => {
  const res = await fetch(`${url}/health`);
  assert.equal(res.status, 200);
  assert.deepEqual(await res.json(), { status: 'ok' });
}));
